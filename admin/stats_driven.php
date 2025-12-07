<?php
require_once("lib/config.php");
  
// The header.php file now handles session starting and authentication.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch stats
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$totalAssessments = $conn->query("SELECT COUNT(*) AS total FROM user_assessments")->fetch_assoc()['total'];
$avgScore = $conn->query("SELECT AVG(score) AS avg_score FROM user_assessments")->fetch_assoc()['avg_score'];

// Grouped by Major
$majorStats = $conn->query("
  SELECT c.name as category_name, m.major, COUNT(ua.id) AS taken, AVG(ua.score) AS avg_score
  FROM majors m
  JOIN categories c ON m.category_id = c.id
  LEFT JOIN user_assessments ua ON m.id = ua.major_id
  GROUP BY m.id
  ORDER BY c.name, m.major
");

// Fetch user assessment history (grouped by user and timestamp)
$userHistory = $conn->query("
    SELECT 
        u.id as user_id,
        u.name as student_name,
        ua.taken_at,
        MAX(ua.score) as max_score,
        GROUP_CONCAT(CONCAT(m.major, ' (', ua.score, ')') ORDER BY ua.score DESC SEPARATOR ', ') as results_summary,
        GROUP_CONCAT(m.major ORDER BY m.major SEPARATOR ', ') as disqualified_majors
    FROM user_assessments ua
    JOIN users u ON ua.user_id = u.id
    JOIN majors m ON ua.major_id = m.id
    WHERE ua.score >= 0 -- Exclude any potential negative score markers if used in the future
    GROUP BY u.id, u.name, ua.taken_at
    ORDER BY ua.taken_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Stats - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100 flex">

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="w-full md:pl-64">
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 pt-20 md:pt-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Assessment Statistics</h1>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-blue-50 p-6 rounded-lg shadow-md border-l-4 border-blue-400">
                <h3 class="text-xl font-semibold text-gray-800">Total Users</h3>
                <p class="text-4xl font-bold text-blue-600 mt-2"><?= $totalUsers ?></p>
            </div>
            <div class="bg-green-50 p-6 rounded-lg shadow-md border-l-4 border-green-400">
                <h3 class="text-xl font-semibold text-gray-800">Assessments Taken</h3>
                <p class="text-4xl font-bold text-green-600 mt-2"><?= $totalAssessments ?></p>
            </div>
            <div class="bg-yellow-50 p-6 rounded-lg shadow-md border-l-4 border-yellow-400">
                <h3 class="text-xl font-semibold text-gray-800">Overall Average Score</h3>
                <p class="text-4xl font-bold text-yellow-600 mt-2"><?= $avgScore ? round($avgScore, 2) : 'N/A' ?></p>
            </div>
        </div>

        <!-- Major/Track Stats -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-8">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Stats by Major/Track</h2>
                    <div class="flex items-center gap-4">
                        <input type="text" id="majorStatsSearchInput" class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500" placeholder="Search majors or categories...">
                        <a href="export_major_stats.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md text-sm whitespace-nowrap">Export CSV</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="majorStatsTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Category/Track</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Major</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Assessments Taken</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Average Score</th>
                            </tr>
                        </thead>
                        <tbody id="majorStatsTableBody" class="bg-white divide-y divide-gray-200">
                            <?php if ($majorStats->num_rows > 0) {
                                while ($row = $majorStats->fetch_assoc()) { ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['category_name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700"><?= htmlspecialchars($row['major']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= $row['taken'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= $row['avg_score'] ? round($row['avg_score'], 2) : 'N/A' ?></td>
                                    </tr>
                            <?php } } else { ?>
                                <tr><td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No assessment data found.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Per-user Assessment History -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Per-User Assessment History</h2>
                <div class="overflow-x-auto">
                    <table id="userHistoryTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Student Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Results Summary</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Date Taken</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userHistoryTableBody" class="bg-white divide-y divide-gray-200">
                            <?php if ($userHistory && $userHistory->num_rows > 0) {
                                while ($row = $userHistory->fetch_assoc()) { ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700 align-top"><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500 align-top">
                                            <?php if ($row['max_score'] > 0): ?>
                                                <?= htmlspecialchars($row['results_summary']) ?>
                                            <?php else: ?>
                                                <div class="font-semibold text-red-600">Disqualified</div>
                                                <div class="text-xs text-gray-400">Majors: <?= htmlspecialchars($row['disqualified_majors']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('F j, Y, g:i a', strtotime($row['taken_at'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button class="preview-btn inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-xs
                                                <?php if ($row['max_score'] == 0) echo 'opacity-50 cursor-not-allowed'; ?>" 
                                                data-user-id="<?= $row['user_id'] ?>" data-taken-at="<?= $row['taken_at'] ?>" <?php if ($row['max_score'] == 0) echo 'disabled'; ?>>
                                                Preview
                                            </button>
                                        </td>
                                    </tr>
                            <?php } } else { ?>
                                <tr><td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No user assessment history found.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div id="historyPagination" class="px-8 py-4 flex justify-center items-center space-x-2"></div>
            </div>
        </div>
    </main>
</div>

  <!-- Preview Modal -->
    <div id="previewModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 id="previewModalTitle" class="text-2xl font-bold text-gray-800">Assessment Results</h3>
                <button id="closePreviewModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div id="previewContent" class="p-8 max-h-[75vh] overflow-y-auto">
                <!-- AJAX content will be loaded here -->
                <div class="text-center text-gray-500 py-10">Loading results...</div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('previewModal');
    const closeBtn = document.getElementById('closePreviewModal');
    const previewContent = document.getElementById('previewContent');
    const modalTitle = document.getElementById('previewModalTitle');

    document.querySelectorAll('.preview-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const takenAt = this.dataset.takenAt;

            modal.classList.add('active');
            previewContent.innerHTML = '<div class="text-center text-gray-500 py-10">Loading results...</div>';
            modalTitle.textContent = 'Assessment Results';

            fetch(`ajax_get_user_results.php?user_id=${userId}&taken_at=${takenAt}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        previewContent.innerHTML = `<p class="text-red-600 font-semibold">Error: ${data.error}</p>`;
                        return;
                    }
                    if (data.length === 0) {
                        previewContent.innerHTML = '<p class="text-gray-500">No results found for this assessment.</p>';
                        return;
                    }

                    const topRec = data[0];
                    modalTitle.textContent = `Results for ${topRec.student_name}`;

                    let html = `
                        <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6 text-center mb-8">
                            <h4 class="text-lg font-bold text-gray-800">Top Recommendation</h4>
                            <p class="text-3xl font-bold text-yellow-600 my-2">${topRec.major}</p>
                            <p class="text-gray-600 text-sm">${topRec.description}</p>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Score Breakdown</h4>
                        <div class="space-y-3">`;

                    data.forEach(result => {
                        html += `
                            <div class="bg-gray-100 p-3 rounded-lg flex justify-between items-center">
                                <span class="font-semibold text-gray-700">${result.major}</span>
                                <span class="bg-yellow-400 text-gray-800 text-xs font-bold px-3 py-1 rounded-full">${result.score} Points</span>
                            </div>`;
                    });

                    html += '</div>';
                    previewContent.innerHTML = html;
                })
                .catch(error => {
                    previewContent.innerHTML = `<p class="text-red-600 font-semibold">An error occurred while fetching the results.</p>`;
                    console.error('Error:', error);
                });
        });
    });

    closeBtn.onclick = () => modal.classList.remove('active');
    window.onclick = (event) => { if (event.target == modal) { modal.classList.remove('active'); } };

    // --- Search for Major Stats Table ---
    const majorStatsSearchInput = document.getElementById('majorStatsSearchInput');
    if (majorStatsSearchInput) {
        const majorStatsTableBody = document.getElementById('majorStatsTableBody');
        const majorStatsAllRows = Array.from(majorStatsTableBody.getElementsByTagName('tr'));

        majorStatsSearchInput.addEventListener('keyup', () => {
            const searchTerm = majorStatsSearchInput.value.toLowerCase();

            majorStatsAllRows.forEach(row => {
                if (row.getElementsByTagName('td').length > 1) {
                    const categoryText = row.cells[0]?.textContent.toLowerCase() || '';
                    const majorText = row.cells[1]?.textContent.toLowerCase() || '';
                    if (categoryText.includes(searchTerm) || majorText.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    }

    // --- Pagination for User History Table ---
    const historyTableBody = document.getElementById('userHistoryTableBody');
    if (historyTableBody) {
        const historyAllRows = Array.from(historyTableBody.getElementsByTagName('tr'));
        const historyPaginationContainer = document.getElementById('historyPagination');
        const historyRowsPerPage = 10;
        let historyCurrentPage = 1;

        function paginateHistoryTable() {
            const totalRows = historyAllRows.length;
            const totalPages = Math.ceil(totalRows / historyRowsPerPage);

            // Hide all rows
            historyAllRows.forEach(row => row.style.display = 'none');

            // Show rows for the current page
            const start = (historyCurrentPage - 1) * historyRowsPerPage;
            const end = start + historyRowsPerPage;
            historyAllRows.slice(start, end).forEach(row => row.style.display = '');

            // Render pagination controls
            renderHistoryPagination(totalPages);
        }

        function renderHistoryPagination(totalPages) {
            historyPaginationContainer.innerHTML = "";
            if (totalPages <= 1) return;

            if (historyCurrentPage > 1) {
                const prevBtn = document.createElement("button");
                prevBtn.innerHTML = "&laquo; Prev";
                prevBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
                prevBtn.onclick = () => { historyCurrentPage--; paginateHistoryTable(); };
                historyPaginationContainer.appendChild(prevBtn);
            }

            const pageInfo = document.createElement('span');
            pageInfo.className = 'px-4 py-2 text-sm font-medium text-gray-700';
            pageInfo.textContent = `Page ${historyCurrentPage} of ${totalPages}`;
            historyPaginationContainer.appendChild(pageInfo);

            if (historyCurrentPage < totalPages) {
                const nextBtn = document.createElement("button");
                nextBtn.innerHTML = "Next &raquo;";
                nextBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
                nextBtn.onclick = () => { historyCurrentPage++; paginateHistoryTable(); };
                historyPaginationContainer.appendChild(nextBtn);
            }
        }

        if (historyAllRows.length > 0 && historyAllRows[0].getElementsByTagName('td').length > 1) {
             paginateHistoryTable();
        }
    }
});
</script>
</body>
</html>
