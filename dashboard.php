<?php
session_start();
require 'admin/lib/config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user's name
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$user_name = $user ? explode(' ', $user['name'])[0] : 'User';

// Fetch user's assessment history
$assessment_history = [];
$history_stmt = $conn->prepare("
    SELECT 
        ua.taken_at,
        (SELECT m.major FROM user_assessments ua2 JOIN majors m ON ua2.major_id = m.id WHERE ua2.user_id = ua.user_id AND ua2.taken_at = ua.taken_at ORDER BY ua2.score DESC LIMIT 1) as top_major,
        MAX(ua.score) as max_score
    FROM user_assessments ua
    WHERE ua.user_id = ?
    GROUP BY ua.taken_at
    ORDER BY ua.taken_at DESC
");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$assessment_history = $history_result->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Career Pathway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
    </style>
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-gray-900 mb-4">Welcome, <?php echo htmlspecialchars(strtoupper($user_name)); ?>!</h2>
        <p class="text-gray-600 text-lg mb-8">You're on the right path to discovering your future career. Here’s what you can do next:</p>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Take Assessment Card -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <h3 class="text-2xl font-semibold text-gray-800 mb-3">Take the Assessment</h3>
                <p class="text-gray-600 mb-4">Answer a few questions to find out which career pathways best match your interests and skills.</p>
                <a href="/WBCPS/assessment" class="bg-yellow-400 text-gray-900 px-6 py-2 font-bold rounded-full hover:bg-yellow-500 transition-colors">Start Assessment</a>
            </div>

            <!-- Explore Pathways Card -->
            <div class="bg-gray-50 border-l-4 border-gray-400 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <h3 class="text-2xl font-semibold text-gray-800 mb-3">Explore Pathways</h3>
                <p class="text-gray-600 mb-4">Browse through all available career pathways to learn more about different fields and opportunities.</p>
                <a href="/WBCPS/pathways" class="bg-gray-700 text-white px-6 py-2 font-bold rounded-full hover:bg-gray-800 transition-colors">Explore Now</a>
            </div>
        </div>

        <?php if (!empty($assessment_history)): ?>
        <div class="mt-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-3">Your Assessment History</h3>
            <div id="historyContainer" class="space-y-4">
                <?php foreach ($assessment_history as $history): ?>
                    <div class="history-item bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex items-center justify-between hover:shadow-md transition-shadow">
                        <div>
                            <p class="font-bold text-gray-800">
                                Assessment from <?php echo date('F j, Y \a\t g:i a', strtotime($history['taken_at'])); ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <?php if ($history['max_score'] > 0): ?>
                                    Top Recommendation: <span class="font-semibold"><?php echo htmlspecialchars($history['top_major']); ?></span>
                                <?php else: ?>
                                    Status: <span class="font-semibold text-red-600">Disqualified</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($history['max_score'] > 0): ?>
                            <a href="/WBCPS/results?taken_at=<?php echo urlencode($history['taken_at']); ?>" class="bg-gray-200 text-gray-700 px-4 py-2 text-sm font-bold rounded-full hover:bg-gray-300 transition-colors">
                                View Results
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Pagination controls will be inserted here -->
            <div id="historyPagination" class="mt-6 flex justify-center items-center space-x-2"></div>
        </div>
        <?php endif; ?>

    </div>
</main>

</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const historyContainer = document.getElementById('historyContainer');
    if (!historyContainer) return;

    const paginationContainer = document.getElementById('historyPagination');
    const items = Array.from(historyContainer.getElementsByClassName('history-item'));
    const itemsPerPage = 5;
    const totalItems = items.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    let currentPage = 1;

    function displayPage(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;

        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        items.forEach((item, index) => {
            item.style.display = (index >= start && index < end) ? 'flex' : 'none';
        });

        renderPaginationControls();
    }

    function renderPaginationControls() {
        paginationContainer.innerHTML = '';
        if (totalPages <= 1) return;

        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '&laquo; Prev';
        prevBtn.className = 'px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-50';
        prevBtn.disabled = (currentPage === 1);
        prevBtn.onclick = () => displayPage(currentPage - 1);
        paginationContainer.appendChild(prevBtn);

        // Page number buttons
        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.innerText = i;
            pageBtn.className = `px-4 py-2 text-sm font-medium rounded-md border ${i === currentPage ? 'bg-yellow-400 text-black border-yellow-400 z-10' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'}`;
            pageBtn.onclick = () => displayPage(i);
            paginationContainer.appendChild(pageBtn);
        }

        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = 'Next &raquo;';
        nextBtn.className = 'px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50 disabled:opacity-50';
        nextBtn.disabled = (currentPage === totalPages);
        nextBtn.onclick = () => displayPage(currentPage + 1);
        paginationContainer.appendChild(nextBtn);
    }

    // Initial display
    if (totalItems > itemsPerPage) {
        displayPage(1);
    }
});
</script>
</html>