<?php
session_start();
require 'admin/lib/config.php'; // Using the mysqli connection from your login page

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    // Set a message and action for the auth modal, then redirect to the home page.
    $_SESSION['auth_message'] = "You must be logged in to take the assessment.";
    $_SESSION['auth_message_type'] = 'error'; // This will show a red-styled message.
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- Process Assessment Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assessment'])) {
    $scores = [];
    $total_questions_per_major = [];

    // Fetch all questions to compare answers
    $all_questions_result = $conn->query("SELECT id, major_id, correct_answer FROM assessments");
    $correct_answers = [];
    while ($q = $all_questions_result->fetch_assoc()) {
        $correct_answers[$q['id']] = $q['correct_answer'];
        if (!isset($scores[$q['major_id']])) {
            $scores[$q['major_id']] = 0;
            $total_questions_per_major[$q['major_id']] = 0;
        }
        $total_questions_per_major[$q['major_id']]++;
    }

    // Calculate scores based on user answers
    foreach ($_POST['answers'] as $question_id => $user_answer) {
        $question_id = (int)$question_id;
        if (isset($correct_answers[$question_id]) && $user_answer === $correct_answers[$question_id]) {
            // Find which major this question belongs to
            $stmt = $conn->prepare("SELECT major_id FROM assessments WHERE id = ?");
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $major_id = $row['major_id'];
                $scores[$major_id]++;
            }
            $stmt->close();
        }
    }

    // Save scores to the database
    $conn->query("DELETE FROM user_assessments WHERE user_id = $user_id"); // Clear previous results
    $stmt = $conn->prepare("INSERT INTO user_assessments (user_id, major_id, score) VALUES (?, ?, ?)");
    foreach ($scores as $major_id => $score) {
        $stmt->bind_param("iii", $user_id, $major_id, $score);
        $stmt->execute();
    }
    $stmt->close();

    // Redirect to a results page (or display here)
    header("Location: results.php");
    exit();
}

// --- Fetch Questions for Display ---
$questions_by_major = [];
$result = $conn->query("SELECT m.major, a.id, a.question, a.option_a, a.option_b, a.option_c, a.option_d 
                       FROM assessments a 
                       JOIN majors m ON a.major_id = m.id 
                       ORDER BY m.major, a.id");

while ($row = $result->fetch_assoc()) {
    $questions_by_major[$row['major']][] = $row;
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
    </style>
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<main class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-gray-900 mb-2">Career Fit Assessment</h2>
        <p class="text-gray-600 mb-6">Answer the following questions to the best of your ability. There are no wrong answers, just what's right for you.</p>

        <?php if (empty($questions_by_major)): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
                <p class="font-bold">No Assessment Available</p>
                <p>The assessment questions have not been set up yet. Please check back later.</p>
            </div>
        <?php else: ?>
            <form action="assessment.php" method="POST">
                <?php foreach ($questions_by_major as $major => $questions): ?>
                    <div class="mb-8">
                        <h3 class="text-2xl font-semibold text-gray-700 border-b-2 border-yellow-400 pb-2 mb-4"><?php echo htmlspecialchars($major); ?></h3>
                        <?php foreach ($questions as $index => $q): ?>
                            <div class="mb-6 p-4 bg-gray-50 rounded-md">
                                <p class="font-medium text-gray-800 mb-2"><?php echo ($index + 1) . ". " . htmlspecialchars($q['question']); ?></p>
                                <div>
                                    <div class="space-y-2">
                                        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer">
                                            <input type="radio" class="form-radio text-yellow-500" name="answers[<?php echo $q['id']; ?>]" value="a" required>
                                            <span class="ml-3 text-gray-700">A. <?php echo htmlspecialchars($q['option_a']); ?></span>
                                        </label>
                                        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer">
                                            <input type="radio" class="form-radio text-yellow-500" name="answers[<?php echo $q['id']; ?>]" value="b" required>
                                            <span class="ml-3 text-gray-700">B. <?php echo htmlspecialchars($q['option_b']); ?></span>
                                        </label>
                                        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer">
                                            <input type="radio" class="form-radio text-yellow-500" name="answers[<?php echo $q['id']; ?>]" value="c" required>
                                            <span class="ml-3 text-gray-700">C. <?php echo htmlspecialchars($q['option_c']); ?></span>
                                        </label>
                                        <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-yellow-50 cursor-pointer">
                                            <input type="radio" class="form-radio text-yellow-500" name="answers[<?php echo $q['id']; ?>]" value="d" required>
                                            <span class="ml-3 text-gray-700">D. <?php echo htmlspecialchars($q['option_d']); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="text-center mt-8">
                    <button type="submit" name="submit_assessment" class="bg-yellow-400 text-gray-900 px-12 py-4 font-bold rounded-full hover:bg-yellow-500 transition-colors shadow-lg text-lg">
                        Submit & See My Results
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

</body>
</html>