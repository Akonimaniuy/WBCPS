<?php
session_start();
require 'admin/lib/config.php'; // Using the mysqli connection from your login page

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    // Set a message and action for the auth modal, then redirect to the home page.
    $_SESSION['auth_message'] = "You must be logged in to take the assessment.";
    $_SESSION['auth_message_type'] = 'error';
    header("Location: /WBCPS/"); // Redirect to home route
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$selected_majors = $_SESSION['assessment_majors'] ?? null;
$initial_question = null;

// --- Handle Major Selection ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_assessment'])) {
    if (isset($_POST['major_ids']) && count($_POST['major_ids']) >= 2) {
        $posted_major_ids = array_map('intval', $_POST['major_ids']);
        
        $valid_majors = [];
        $invalid_majors_names = [];

        // Prepare a statement to check for questions for each major
        $stmt_check = $conn->prepare("SELECT m.major, (SELECT COUNT(*) FROM assessments WHERE major_id = m.id) as question_count FROM majors m WHERE m.id = ?");

        foreach ($posted_major_ids as $major_id) {
            $stmt_check->bind_param("i", $major_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result()->fetch_assoc();

            if ($result && $result['question_count'] > 0) {
                $valid_majors[] = $major_id;
            } else {
                $invalid_majors_names[] = $result['major'] ?? "Major ID #$major_id";
            }
        }
        $stmt_check->close();

        // Check if there are enough valid majors to proceed
        if (count($valid_majors) < 2) {
            $message = "The selected majors do not have enough questions to start an assessment. Please select at least two different, valid majors.";
            $selected_majors = null; // Prevent assessment from starting
        } else {
            $selected_majors = $valid_majors;
            $_SESSION['assessment_majors'] = $selected_majors;

        // --- Initialize Adaptive Assessment State ---
        $scores = [];
        $difficulty_levels = [];
        $consecutive_wrong_answers = [];
        foreach ($selected_majors as $major_id) {
                $scores[$major_id] = [
                    'total' => 0,
                    'Interest' => 0,
                    'Skills' => 0,
                    'Strengths' => 0
                ];
                $difficulty_levels[$major_id] = 2; // Start at medium difficulty
                $consecutive_wrong_answers[$major_id] = 0;
            }

            $_SESSION['adaptive_assessment_state'] = [
                'selected_majors' => $selected_majors,
                'scores' => $scores,
                'difficulty_levels' => $difficulty_levels,
                'consecutive_wrong_answers' => $consecutive_wrong_answers,
                'answered_questions' => [0], // Start with a dummy value to prevent SQL errors
                'current_major_index' => -1, // Will be incremented to 0 on first question
                'questions_answered' => 0,
                'total_questions_to_ask' => 100 // Define how many total questions to ask
            ];
            // Initialize leave attempts counter
            $_SESSION['adaptive_assessment_state']['leave_attempts'] = 0;

            // Set a flag to show the warning modal on the next page load
            $_SESSION['show_assessment_warning'] = true;

            // If some majors were invalid, store their names to show a notification
            if (!empty($invalid_majors_names)) {
                $_SESSION['invalid_majors_notice'] = "The following majors were excluded as they have no questions available: " . implode(', ', $invalid_majors_names);
            }

            header("Location: /WBCPS/assessment");
            exit();
        }
    } else {
        $message = "Please select at least two majors to begin the assessment.";
    }
}

// --- Fetch Questions for Display ---
if ($selected_majors) {
    // If majors are selected but the adaptive state is not set, something is wrong.
    // This can happen on a page refresh. Reset the process.
    if (!isset($_SESSION['adaptive_assessment_state'])) {
        unset($_SESSION['assessment_majors']);
        header("Location: /WBCPS/assessment");
        exit();
    }

    // --- Fetch major names for display ---
    $major_names = [];
    if (!empty($selected_majors)) {
        $ids_placeholder = implode(',', array_fill(0, count($selected_majors), '?'));
        $types = str_repeat('i', count($selected_majors));
        $majors_stmt = $conn->prepare("SELECT major FROM majors WHERE id IN ($ids_placeholder) ORDER BY major");
        $majors_stmt->bind_param($types, ...$selected_majors);
        $majors_stmt->execute();
        $majors_result = $majors_stmt->get_result();
        while ($row = $majors_result->fetch_assoc()) {
            $major_names[] = $row['major'];
        }
        $majors_stmt->close();
    }
    // Fetch the very first question to start the assessment
    // We'll start with the first major in the list at medium difficulty
    $first_major_id = $selected_majors[0];
    $stmt = $conn->prepare(
        "SELECT a.id, a.question, a.option_a, a.option_b, a.option_c, a.option_d, m.major
         FROM assessments a 
         JOIN majors m ON a.major_id = m.id
         WHERE a.major_id = ? AND a.difficulty_level = 2
         ORDER BY RAND()
         LIMIT 1"
    );
    $stmt->bind_param("i", $first_major_id);
    $stmt->execute();
    $initial_question = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Update state with the first question
    if ($initial_question) $_SESSION['adaptive_assessment_state']['answered_questions'][] = $initial_question['id'];
}

// Fetch categories and majors for the selection form
$categories_with_majors = [];
$result = $conn->query("SELECT c.id as category_id, c.name as category_name, m.id as major_id, m.major FROM categories c JOIN majors m ON c.id = m.category_id ORDER BY c.name, m.major");
while($row = $result->fetch_assoc()) {
    $categories_with_majors[$row['category_name']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment - Career Pathway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        .question-card { display: none; }
        .question-card.active { display: block; }
        .major-checkbox:checked + label {
            background-color: #FBBF24; /* bg-yellow-400 */
            border-color: #F59E0B; /* border-yellow-500 */
        }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<main class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="bg-white p-8 rounded-lg shadow-lg">

        <?php if (!$selected_majors): ?>
            <!-- Step 1: Major Selection -->
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Start Your Assessment</h2>
            <p class="text-gray-600 mb-6">First, select a category, then choose at least two majors you are interested in exploring.</p>

            <?php if (!empty($message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <form action="/WBCPS/assessment" method="POST">
                <!-- Category Filter Dropdown -->
                <div class="mb-8">
                    <label for="categoryFilter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Category</label>
                    <select id="categoryFilter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm rounded-md shadow-sm">
                        <option value="all">Show All</option>
                        <?php foreach ($categories_with_majors as $category_name => $majors): ?>
                            <option value="<?php echo $majors[0]['category_id']; ?>"><?php echo htmlspecialchars($category_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php foreach ($categories_with_majors as $category_name => $majors): ?>
                    <div class="category-majors mb-8" data-category-id="<?php echo $majors[0]['category_id']; ?>">
                        <h3 class="text-2xl font-semibold text-gray-700 border-b-2 border-yellow-400 pb-2 mb-4"><?php echo htmlspecialchars($category_name); ?></h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($majors as $major): ?>
                                <div>
                                    <input type="checkbox" name="major_ids[]" value="<?php echo $major['major_id']; ?>" id="major_<?php echo $major['major_id']; ?>" class="major-checkbox hidden">
                                    <label for="major_<?php echo $major['major_id']; ?>" class="block p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-yellow-500 transition-colors">
                                        <span class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($major['major']); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-8">
                    <button type="submit" name="start_assessment" class="bg-yellow-400 text-gray-900 px-12 py-4 font-bold rounded-full hover:bg-yellow-500 transition-colors shadow-lg text-lg">
                        Start Assessment
                    </button>
                </div>
            </form>

        <?php else: ?>
            <!-- Step 2: The Assessment -->
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Career Fit Assessment</h2>
            <p class="text-gray-600 mb-6">Answer the following questions. Your responses will help us recommend the best path for you.</p>

            <!-- Majors being assessed -->
            <div class="mb-8 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <h4 class="font-semibold text-gray-700 mb-2">Majors being assessed:</h4>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($major_names as $name): ?>
                        <span class="bg-yellow-100 text-yellow-800 text-sm font-medium px-3 py-1 rounded-full"><?php echo htmlspecialchars($name); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (empty($initial_question)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
                    <p class="font-bold">No Questions Available</p>
                    <p>There are no assessment questions for your selected majors yet. Please check back later or <a href="/WBCPS/assessment" class="font-bold underline">choose different majors</a>.</p>
                </div>
            <?php else: ?>
                <div id="assessmentContainer">
                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="bg-gray-200 rounded-full h-4">
                            <div id="progressBar" class="bg-yellow-400 h-4 rounded-full" style="width: 0%;"></div>
                        </div>
                        <p id="progressText" class="text-center text-sm text-gray-600 mt-1">Question 1 / <?php echo $_SESSION['adaptive_assessment_state']['total_questions_to_ask']; ?></p>
                    </div>

                    <!-- Question content will be injected here -->
                    <div id="questionCard" data-question-id="<?php echo $initial_question['id']; ?>">
                        <div class="mb-2">
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                Major: <?php echo htmlspecialchars($initial_question['major']); ?>
                            </span>
                        </div>
                        <!-- Initial question template is included -->
                        <?php include 'assessment_question_template.php'; ?>
                    </div>

                    <div class="flex justify-end items-center mt-8">
                        <button type="button" id="nextBtn" class="bg-yellow-400 text-gray-900 px-8 py-3 font-bold rounded-full hover:bg-yellow-500 transition-colors w-full md:w-auto">Next</button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Start Warning Modal -->
<?php if (isset($_SESSION['show_assessment_warning']) && $_SESSION['show_assessment_warning']): ?>
<div id="startWarningModal" class="modal active fixed inset-0 bg-gray-600 bg-opacity-75 items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md text-center p-8">
        <svg class="mx-auto mb-4 text-yellow-400 w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Assessment Rules</h3>
        <p class="text-gray-600 mb-6">
            You are about to begin. Please do not leave or refresh this page. Leaving the page multiple times will result in disqualification.
        </p>
        <button id="continueToAssessmentBtn" class="bg-yellow-400 text-gray-900 px-8 py-3 font-bold rounded-full hover:bg-yellow-500 transition-colors">
            I Understand, Continue
        </button>
    </div>
</div>
<?php unset($_SESSION['show_assessment_warning']); // Unset the flag so it doesn't show again on refresh ?>
<?php endif; ?>

<!-- Invalid Majors Notice Modal -->
<?php if (isset($_SESSION['invalid_majors_notice'])): ?>
<div id="invalidMajorsModal" class="modal active fixed inset-0 bg-gray-600 bg-opacity-75 items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md text-center p-8">
        <svg class="mx-auto mb-4 text-blue-500 w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Notice</h3>
        <p class="text-gray-600 mb-6">
            <?php echo htmlspecialchars($_SESSION['invalid_majors_notice']); ?>
        </p>
        <button id="closeInvalidMajorsBtn" class="bg-yellow-400 text-gray-900 px-8 py-3 font-bold rounded-full hover:bg-yellow-500 transition-colors">
            OK
        </button>
    </div>
</div>
<?php unset($_SESSION['invalid_majors_notice']); ?>
<?php endif; ?>

<!-- Leave Warning Modal -->
<div id="leaveWarningModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-75 items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md text-center p-8">
        <svg class="mx-auto mb-4 text-yellow-400 w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Warning!</h3>
        <p class="text-gray-600 mb-6">
            Leaving the page during the assessment is not recommended. Your progress is saved, but leaving multiple times will result in disqualification.
        </p>
        <p id="leaveWarningCounter" class="text-lg font-semibold text-red-600 mb-6"></p>
        <button id="returnToAssessmentBtn" class="bg-yellow-400 text-gray-900 px-8 py-3 font-bold rounded-full hover:bg-yellow-500 transition-colors">
            Return to Assessment
        </button>
    </div>
</div>

<!-- Custom Alert Modal -->
<div id="alertModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-75 items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md text-center p-8">
        <svg class="mx-auto mb-4 text-red-500 w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Incomplete Answer</h3>
        <p id="alertModalMessage" class="text-gray-600 mb-6">
            Please select an answer before proceeding.
        </p>
        <button id="closeAlertModalBtn" class="bg-yellow-400 text-gray-900 px-8 py-3 font-bold rounded-full hover:bg-yellow-500 transition-colors">
            OK
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const assessmentContainer = document.getElementById('assessmentContainer');

    // --- Major Selection Filtering ---
    const categoryFilter = document.getElementById('categoryFilter');
    const majorGroups = document.querySelectorAll('.category-majors');

    if (categoryFilter) {
        const showMajorsForCategory = (categoryId) => {
            majorGroups.forEach(group => {
                if (categoryId === 'all' || group.dataset.categoryId === categoryId) {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                }
            });
        };

        categoryFilter.addEventListener('change', (e) => {
            showMajorsForCategory(e.target.value);
        });

        // Initially show the first category's majors, or all if there's only one category.
        if (majorGroups.length > 0) {
            const firstCategoryId = majorGroups[0].dataset.categoryId;
            categoryFilter.value = firstCategoryId;
            showMajorsForCategory(firstCategoryId);
        }
    }
    // --- End Major Selection ---

    if (!assessmentContainer) return;

    const nextBtn = document.getElementById('nextBtn');
    const questionCard = document.getElementById('questionCard');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');

    // --- Page Leave Detection ---
    const leaveModal = document.getElementById('leaveWarningModal');
    const returnBtn = document.getElementById('returnToAssessmentBtn');
    const leaveCounterText = document.getElementById('leaveWarningCounter');
    const alertModal = document.getElementById('alertModal');
    const closeAlertModalBtn = document.getElementById('closeAlertModalBtn');
    const startWarningModal = document.getElementById('startWarningModal');
    const continueToAssessmentBtn = document.getElementById('continueToAssessmentBtn');
    const invalidMajorsModal = document.getElementById('invalidMajorsModal');
    const closeInvalidMajorsBtn = document.getElementById('closeInvalidMajorsBtn');

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            // User has switched tabs or minimized the window.
            // We only trigger this if the assessment is in progress.
            if (!assessmentContainer || assessmentContainer.innerHTML.includes('Assessment Complete!')) {
                return;
            }

            fetch('ajax_track_leave.php', { method: 'POST', keepalive: true })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'disqualified') {
                        // The server has disqualified the user. Redirect them.
                        window.location.href = '/WBCPS/dashboard';
                    } else if (data.status === 'warned') {
                        // Store data to show modal when they return
                        sessionStorage.setItem('showLeaveWarning', 'true');
                        sessionStorage.setItem('leaveAttempts', data.attempts);
                        sessionStorage.setItem('maxLeaves', data.max);
                    }
                });
        } else if (document.visibilityState === 'visible' && sessionStorage.getItem('showLeaveWarning') === 'true') {
            // User has returned to the page. Show the modal.
            const attempts = sessionStorage.getItem('leaveAttempts');
            const max = sessionStorage.getItem('maxLeaves');
            leaveCounterText.textContent = `You have left the page ${attempts} out of ${max} times.`;
            leaveModal.classList.add('active');
            sessionStorage.removeItem('showLeaveWarning');
        }
    };

    nextBtn.addEventListener('click', function() {
        const currentQuestionId = questionCard.dataset.questionId;
        const radio = questionCard.querySelector('input[type="radio"]:checked');

        if (!radio) {
            // Show custom modal instead of alert
            alertModal.classList.add('active');
            return;
        }

        nextBtn.disabled = true;
        nextBtn.textContent = 'Loading...';

        fetch('ajax_process_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question_id: currentQuestionId,
                answer: radio.value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('An error occurred: ' + data.error);
                window.location.href = 'dashboard.php'; // Redirect on error
                return;
            }

            // Update progress bar
            const progressPercentage = (data.progress / data.total) * 100;
            progressBar.style.width = `${progressPercentage}%`;
            progressText.textContent = `Question ${data.progress} / ${data.total}`;

            if (data.is_complete || !data.next_question) {
                // Assessment is finished
                assessmentContainer.innerHTML = `
                    <div class="text-center">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">Assessment Complete!</h2>
                        <p class="text-gray-600 mb-6">Thank you for completing the assessment. We are now calculating your results.</p>
                        <a href="/WBCPS/results" class="bg-green-500 text-white px-12 py-4 font-bold rounded-full hover:bg-green-600 transition-colors shadow-lg text-lg">
                            View My Results
                        </a>
                    </div>`;
            } else {
                // Load the next question
                const q = data.next_question;
                questionCard.dataset.questionId = q.id;
                questionCard.innerHTML = `
                    <div class="mb-2">
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Major: ${q.major}</span>
                    </div>
                    <div class="mb-6 p-4 bg-gray-50 rounded-md">
                        <p class="font-medium text-gray-800 mb-2">${data.progress + 1}. ${q.question}</p>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer"><input type="radio" class="form-radio text-yellow-500" name="answer" value="a" required><span class="ml-3 text-gray-700">A. ${q.option_a}</span></label>
                            <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer"><input type="radio" class="form-radio text-yellow-500" name="answer" value="b" required><span class="ml-3 text-gray-700">B. ${q.option_b}</span></label>
                            <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer"><input type="radio" class="form-radio text-yellow-500" name="answer" value="c" required><span class="ml-3 text-gray-700">C. ${q.option_c}</span></label>
                            <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer"><input type="radio" class="form-radio text-yellow-500" name="answer" value="d" required><span class="ml-3 text-gray-700">D. ${q.option_d}</span></label>
                        </div>
                    </div>`;
                nextBtn.disabled = false;
                nextBtn.textContent = 'Next';
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('A network error occurred. Please try again.');
            nextBtn.disabled = false;
            nextBtn.textContent = 'Next';
        });
    });

    if (returnBtn) {
        returnBtn.addEventListener('click', () => {
            leaveModal.classList.remove('active');
        });
    }

    if (continueToAssessmentBtn) {
        continueToAssessmentBtn.addEventListener('click', () => {
            startWarningModal.classList.remove('active');
        });
    }

    if (closeAlertModalBtn) {
        closeAlertModalBtn.addEventListener('click', () => {
            alertModal.classList.remove('active');
        });
    }

    if (closeInvalidMajorsBtn) {
        closeInvalidMajorsBtn.addEventListener('click', () => {
            invalidMajorsModal.classList.remove('active');
        });
    }

    document.addEventListener('visibilitychange', handleVisibilityChange);
});
</script>

</body>
</html>