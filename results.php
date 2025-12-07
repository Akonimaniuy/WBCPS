<?php
session_start();
require 'admin/lib/config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Unset assessment session variables now that the user is on the results page
unset($_SESSION['assessment_majors']);
unset($_SESSION['adaptive_assessment_state']);

$taken_at = null;

// Check if a specific timestamp is provided in the URL
if (isset($_GET['taken_at'])) {
    $taken_at = $_GET['taken_at'];
} else {
    // Otherwise, fetch the most recent assessment timestamp for the user
    $stmt_ts = $conn->prepare("SELECT MAX(taken_at) as last_taken FROM user_assessments WHERE user_id = ?");
    $stmt_ts->bind_param("i", $user_id);
    $stmt_ts->execute();
    $result_ts = $stmt_ts->get_result();
    $last_assessment = $result_ts->fetch_assoc();
    $stmt_ts->close();
    if ($last_assessment) {
        $taken_at = $last_assessment['last_taken'];
    }
}

$assessment_results = [];
if ($taken_at) {
    // Fetch user's assessment results for the determined timestamp
    $stmt = $conn->prepare(
        "SELECT u.name as student_name, m.major, m.description, m.image, m.link, ua.score, 
                ua.interest_score, ua.skills_score, ua.strengths_score, ua.max_interest_score, ua.max_skills_score, ua.max_strengths_score
         FROM user_assessments ua
         JOIN majors m ON ua.major_id = m.id
         JOIN users u ON ua.user_id = u.id
         WHERE ua.user_id = ? AND ua.taken_at = ?
         ORDER BY ua.score DESC"
    );
    $stmt->bind_param("is", $user_id, $taken_at);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $assessment_results[] = $row;
        }
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Assessment Results - Career Pathway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        /* Carousel styles */
        .carousel-container { position: relative; }
        .carousel {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .carousel::-webkit-scrollbar { display: none; /* Chrome, Safari and Opera */ }
        .carousel-card { flex: 0 0 90%; }
        @media (min-width: 640px) { .carousel-card { flex: 0 0 22rem; /* ~352px */ } }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(31, 41, 55, 0.6); /* bg-gray-800 with opacity */
            color: white;
            border-radius: 9999px;
            width: 3rem; height: 3rem;
            display: flex; align-items: center; justify-content: center;
            z-index: 10; cursor: pointer; transition: background-color 0.2s;
        }
        .carousel-btn:hover { background-color: rgba(17, 24, 39, 0.8); }
        .carousel-btn.disabled { opacity: 0.3; cursor: not-allowed; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="bg-white p-8 rounded-lg shadow-lg">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Your Assessment Results</h2>
            <p class="text-gray-600 text-lg mb-6">Based on your answers, here are the career pathways we recommend for you.</p>
            <button id="openRetakeModalBtn" data-retake-url="/WBCPS/retake-assessment?taken_at=<?php echo urlencode($taken_at); ?>" class="inline-flex items-center gap-3 bg-yellow-400 text-gray-900 px-8 py-3 font-bold rounded-full hover:bg-yellow-500 transition-colors shadow-lg text-lg mb-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 4l1.5 1.5A9 9 0 0120.5 10.5M20 20l-1.5-1.5A9 9 0 003.5 13.5" />
                </svg>
                <span>Retake Assessment</span>
            </button>
        </div>

        <?php if (empty($assessment_results)): ?>
            <div class="text-center py-12">
                <p class="text-gray-500">You have not completed any assessments yet.</p>
                <a href="/WBCPS/assessment" class="mt-4 inline-block bg-yellow-400 text-gray-900 px-6 py-2 font-bold rounded-full hover:bg-yellow-500 transition-colors">Take Assessment</a>
            </div>
        <?php else: 
            $top_recommendation = null;
            $other_recommendations = [];

            if (!empty($assessment_results) && $assessment_results[0]['score'] > 0) {
                // If the top score is greater than 0, we have a true top recommendation.
                $top_recommendation = $assessment_results[0];
                $other_recommendations = array_slice($assessment_results, 1);
            } elseif (!empty($assessment_results)) {
                // If all scores are 0, treat all results as "other" recommendations.
                $other_recommendations = $assessment_results;
            }
        ?>
        <?php if ($top_recommendation): ?>
            <!-- Top Recommendation (General) -->
            <div class="bg-yellow-50 border-2 border-yellow-400 rounded-2xl shadow-xl p-8 mb-12 transform hover:scale-105 transition-transform duration-300">
                <h3 class="text-xl font-bold text-yellow-800 mb-4 text-center font-serif tracking-wider">TOP RECOMMENDATION</h3>
                <div class="flex flex-col md:flex-row items-center gap-8">
                    <img class="h-48 w-48 object-cover rounded-lg shadow-md flex-shrink-0" src="admin/uploads/<?php echo htmlspecialchars($top_recommendation['image'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($top_recommendation['major']); ?>">
                    <div class="text-center md:text-left">
                        <h4 class="text-4xl font-bold text-gray-900 font-serif"><?php echo htmlspecialchars($top_recommendation['major']); ?></h4>
                        <p class="text-gray-600 mt-3 text-base"><?php echo htmlspecialchars($top_recommendation['description']); ?></p>
                        
                        <!-- Score Breakdown -->
                        <?php
                            // Calculate percentages for the top recommendation
                            $interest_pct = ($top_recommendation['max_interest_score'] > 0) ? round(($top_recommendation['interest_score'] / $top_recommendation['max_interest_score']) * 100) : 0;
                            $skills_pct = ($top_recommendation['max_skills_score'] > 0) ? round(($top_recommendation['skills_score'] / $top_recommendation['max_skills_score']) * 100) : 0;
                            $strengths_pct = ($top_recommendation['max_strengths_score'] > 0) ? round(($top_recommendation['strengths_score'] / $top_recommendation['max_strengths_score']) * 100) : 0;
                        ?>
                        <div class="mt-6 flex flex-wrap justify-center md:justify-start gap-4 text-sm">
                            <div class="flex items-center gap-2 bg-blue-100 text-blue-800 font-semibold px-3 py-1 rounded-full">
                                <span>Interest:</span>
                                <span><?php echo $interest_pct; ?>%</span>
                            </div>
                            <div class="flex items-center gap-2 bg-green-100 text-green-800 font-semibold px-3 py-1 rounded-full">
                                <span>Skills:</span>
                                <span><?php echo $skills_pct; ?>%</span>
                            </div>
                            <div class="flex items-center gap-2 bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full">
                                <span>Strengths:</span>
                                <span><?php echo $strengths_pct; ?>%</span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button 
                                class="breakdown-btn inline-block bg-gray-800 text-white px-6 py-2 font-bold rounded-full hover:bg-gray-900 transition-colors"
                                data-major="<?php echo htmlspecialchars($top_recommendation['major']); ?>"
                                data-description="<?php echo htmlspecialchars($top_recommendation['description']); ?>"
                                data-image="admin/uploads/<?php echo htmlspecialchars($top_recommendation['image'] ?: 'default.jpg'); ?>"
                                data-link="<?php echo htmlspecialchars($top_recommendation['link'] ?? '#'); ?>"
                                data-interest-score="<?php echo $top_recommendation['interest_score']; ?>"
                                data-max-interest-score="<?php echo $top_recommendation['max_interest_score']; ?>"
                                data-skills-score="<?php echo $top_recommendation['skills_score']; ?>"
                                data-max-skills-score="<?php echo $top_recommendation['max_skills_score']; ?>"
                                data-strengths-score="<?php echo $top_recommendation['strengths_score']; ?>"
                                data-max-strengths-score="<?php echo $top_recommendation['max_strengths_score']; ?>">
                                View Breakdown
                            </button>
                        </div>
                    </div>
                    <div class="bg-yellow-400 text-gray-900 rounded-full flex flex-col justify-center items-center p-6 shadow-inner w-32 h-32 flex-shrink-0">
                        <span class="text-4xl font-extrabold"><?php echo $top_recommendation['score']; ?>%</span>
                        <span class="font-bold text-sm">Match</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

            <!-- Other Recommendations -->
            <?php if (!empty($other_recommendations)): ?>
                <div class="mt-16">
                    <h3 class="text-2xl font-bold text-gray-800 mb-8 text-center">Other Pathways to Explore</h3>
                    <div id="otherRecsCarouselContainer" class="carousel-container">
                        <div id="otherRecsCarousel" class="carousel flex overflow-x-auto snap-x snap-mandatory space-x-6 pb-4">
                            <?php foreach ($other_recommendations as $result): ?>
                                <div class="carousel-card snap-start bg-white p-6 rounded-lg shadow-md border border-gray-200 flex-shrink-0 flex flex-col">
                                    <img class="h-32 w-full object-cover rounded-md mb-4" src="admin/uploads/<?php echo htmlspecialchars($result['image'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($result['major']); ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="text-xl font-semibold text-gray-800 flex-grow"><?php echo htmlspecialchars($result['major']); ?></h4>
                                        <div class="bg-gray-700 text-white rounded-full flex flex-col justify-center items-center w-16 h-16 flex-shrink-0 -mt-2">
                                            <span class="text-xl font-bold"><?php echo htmlspecialchars($result['score']); ?>%</span>
                                            <span class="text-xs font-semibold">Match</span>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 text-sm mb-4 flex-grow"><?php echo htmlspecialchars(substr($result['description'], 0, 100)) . (strlen($result['description']) > 100 ? '...' : ''); ?></p>
                                    <?php
                                        // Calculate percentages for other recommendations
                                        $interest_pct_other = ($result['max_interest_score'] > 0) ? round(($result['interest_score'] / $result['max_interest_score']) * 100) : 0;
                                        $skills_pct_other = ($result['max_skills_score'] > 0) ? round(($result['skills_score'] / $result['max_skills_score']) * 100) : 0;
                                        $strengths_pct_other = ($result['max_strengths_score'] > 0) ? round(($result['strengths_score'] / $result['max_strengths_score']) * 100) : 0;
                                    ?>
                                    <div class="flex flex-wrap gap-2 text-xs mb-4">
                                        <span class="bg-blue-100 text-blue-800 font-medium px-2 py-1 rounded">Interest: <?php echo $interest_pct_other; ?>%</span>
                                        <span class="bg-green-100 text-green-800 font-medium px-2 py-1 rounded">Skills: <?php echo $skills_pct_other; ?>%</span>
                                        <span class="bg-purple-100 text-purple-800 font-medium px-2 py-1 rounded">Strengths: <?php echo $strengths_pct_other; ?>%</span>
                                    </div>
                                    <div class="mt-auto">
                                        <button 
                                            class="breakdown-btn w-full text-center block bg-gray-800 text-white px-4 py-2 font-bold rounded-full hover:bg-gray-900 transition-colors text-sm"
                                            data-major="<?php echo htmlspecialchars($result['major']); ?>"
                                            data-description="<?php echo htmlspecialchars($result['description']); ?>"
                                            data-image="admin/uploads/<?php echo htmlspecialchars($result['image'] ?: 'default.jpg'); ?>"
                                            data-link="<?php echo htmlspecialchars($result['link'] ?? '#'); ?>"
                                            data-interest-score="<?php echo $result['interest_score']; ?>"
                                            data-max-interest-score="<?php echo $result['max_interest_score']; ?>"
                                            data-skills-score="<?php echo $result['skills_score']; ?>"
                                            data-max-skills-score="<?php echo $result['max_skills_score']; ?>"
                                            data-strengths-score="<?php echo $result['strengths_score']; ?>"
                                            data-max-strengths-score="<?php echo $result['max_strengths_score']; ?>">
                                            View Breakdown
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button id="prevBtn" class="carousel-btn left-0 -ml-4 sm:-ml-5 flex">&#10094;</button>
                        <button id="nextBtn" class="carousel-btn right-0 -mr-4 sm:-mr-5 flex">&#10095;</button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Breakdown Modal -->
<div id="breakdownModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 items-center justify-center z-50 p-4 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 id="modalMajorName" class="text-2xl font-bold text-gray-800 font-serif"></h3>
            <button id="closeBreakdownModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <div class="p-8 max-h-[75vh] overflow-y-auto">
            <div class="flex flex-col md:flex-row items-start gap-6">
                <img id="modalMajorImage" src="" alt="Major Image" class="h-32 w-32 object-cover rounded-lg shadow-md flex-shrink-0">
                <p id="modalMajorDescription" class="text-gray-600"></p>
            </div>
            <h4 class="text-xl font-bold text-gray-800 mt-8 mb-4">Score Analysis</h4>
            <div class="space-y-4">
                <!-- Progress Bars will be injected here -->
                <div id="breakdown-Interest"></div>
                <div id="breakdown-Skills"></div>
                <div id="breakdown-Strengths"></div>
            </div>
        </div>
        <div class="flex justify-end p-6 border-t bg-gray-50 rounded-b-lg">
            <a id="modalLearnMoreLink" href="#" target="_blank" rel="noopener noreferrer" class="bg-gray-800 text-white px-6 py-2 font-bold rounded-full hover:bg-gray-900 transition-colors">
                Learn More on External Site
            </a>
        </div>
    </div>
</div>

<!-- Retake Confirmation Modal -->
<div id="retakeConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 items-center justify-center z-50 p-4 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md text-center p-8">
        <svg class="mx-auto mb-4 text-yellow-400 w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Retake Assessment?</h3>
        <p class="text-gray-600 mb-6">
            Are you sure you want to retake this assessment? A new attempt will be started using the same majors.
        </p>
        <div class="flex justify-center gap-4">
            <button id="cancelRetakeBtn" class="bg-gray-300 text-gray-800 px-8 py-3 font-bold rounded-full hover:bg-gray-400 transition-colors">
                Cancel
            </button>
            <a id="confirmRetakeBtn" href="#" class="bg-yellow-400 text-gray-900 px-8 py-3 font-bold rounded-full hover:bg-yellow-500 transition-colors">
                Yes, Retake
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.getElementById('otherRecsCarousel');
    if (!carousel) return;

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    function isCarouselScrollable() {
        return carousel.scrollWidth > carousel.clientWidth;
    }

    function updateButtons() {
        if (!isCarouselScrollable()) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            return;
        }
        prevBtn.style.display = 'flex';
        nextBtn.style.display = 'flex';

        const scrollLeft = carousel.scrollLeft;
        prevBtn.classList.toggle('disabled', scrollLeft <= 0);
        nextBtn.classList.toggle('disabled', scrollLeft >= carousel.scrollWidth - carousel.clientWidth - 1);
    }

    prevBtn.addEventListener('click', () => carousel.scrollBy({ left: -300, behavior: 'smooth' }));
    nextBtn.addEventListener('click', () => carousel.scrollBy({ left: 300, behavior: 'smooth' }));

    carousel.addEventListener('scroll', updateButtons);
    window.addEventListener('resize', updateButtons); // Recalculate on resize
    updateButtons(); // Initial check

    // --- Breakdown Modal Logic ---
    const modal = document.getElementById('breakdownModal');
    const closeBtn = document.getElementById('closeBreakdownModal');
    const breakdownBtns = document.querySelectorAll('.breakdown-btn');

    const modalMajorName = document.getElementById('modalMajorName');
    const modalMajorImage = document.getElementById('modalMajorImage');
    const modalMajorDescription = document.getElementById('modalMajorDescription');
    const modalLearnMoreLink = document.getElementById('modalLearnMoreLink');

    const createProgressBar = (containerId, category, score, maxScore) => {
        const container = document.getElementById(containerId);
        const percentage = maxScore > 0 ? Math.round((score / maxScore) * 100) : 0;
        
        let bgColor, progressColor;
        switch(category) {
            case 'Interest': bgColor = 'bg-blue-200'; progressColor = 'bg-blue-500'; break;
            case 'Skills': bgColor = 'bg-green-200'; progressColor = 'bg-green-500'; break;
            case 'Strengths': bgColor = 'bg-purple-200'; progressColor = 'bg-purple-500'; break;
            default: bgColor = 'bg-gray-200'; progressColor = 'bg-gray-500';
        }

        container.innerHTML = `
            <div>
                <div class="flex justify-between mb-1">
                    <span class="text-base font-medium text-gray-700">${category}</span>
                    <span class="text-sm font-medium text-gray-700">${percentage}% (${score}/${maxScore} pts)</span>
                </div>
                <div class="w-full ${bgColor} rounded-full h-4">
                    <div class="${progressColor} h-4 rounded-full" style="width: ${percentage}%"></div>
                </div>
            </div>
        `;
    };

    breakdownBtns.forEach(button => {
        button.addEventListener('click', () => {
            const data = button.dataset;

            // Populate modal with data
            modalMajorName.textContent = data.major;
            modalMajorImage.src = data.image;
            modalMajorDescription.textContent = data.description;
            modalLearnMoreLink.href = data.link;

            // Create progress bars
            createProgressBar('breakdown-Interest', 'Interest', parseInt(data.interestScore), parseInt(data.maxInterestScore));
            createProgressBar('breakdown-Skills', 'Skills', parseInt(data.skillsScore), parseInt(data.maxSkillsScore));
            createProgressBar('breakdown-Strengths', 'Strengths', parseInt(data.strengthsScore), parseInt(data.maxStrengthsScore));

            // Show modal
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    });

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    closeBtn.addEventListener('click', closeModal);

    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    // --- Retake Confirmation Modal Logic ---
    const retakeModal = document.getElementById('retakeConfirmModal');
    const openRetakeBtn = document.getElementById('openRetakeModalBtn');
    const cancelRetakeBtn = document.getElementById('cancelRetakeBtn');
    const confirmRetakeBtn = document.getElementById('confirmRetakeBtn');

    if (openRetakeBtn) {
        openRetakeBtn.addEventListener('click', () => {
            const retakeUrl = openRetakeBtn.dataset.retakeUrl;
            confirmRetakeBtn.href = retakeUrl;
            retakeModal.classList.remove('hidden');
            retakeModal.classList.add('flex');
        });
    }

    if (cancelRetakeBtn) {
        cancelRetakeBtn.addEventListener('click', () => {
            retakeModal.classList.add('hidden');
            retakeModal.classList.remove('flex');
        });
    }
});
</script>

</body>
</html>