<?php
require_once("lib/config.php");

// Start session and perform authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // You can redirect or show an error
    die("Unauthorized access.");
}

// Set headers for CSV download
$filename = "major_stats_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Write the CSV header row
fputcsv($output, ['Category/Track', 'Major', 'Assessments Taken', 'Average Score']);

// Fetch the same data as the stats page
$majorStatsQuery = $conn->query("
  SELECT c.name as category_name, m.major, COUNT(ua.id) AS taken, AVG(ua.score) AS avg_score
  FROM majors m
  JOIN categories c ON m.category_id = c.id
  LEFT JOIN user_assessments ua ON m.id = ua.major_id
  GROUP BY m.id
  ORDER BY c.name, m.major
");

if ($majorStatsQuery && $majorStatsQuery->num_rows > 0) {
    // Loop through the rows and write them to the CSV file
    while ($row = $majorStatsQuery->fetch_assoc()) {
        fputcsv($output, [
            $row['category_name'],
            $row['major'],
            $row['taken'],
            $row['avg_score'] ? round($row['avg_score'], 2) : 0
        ]);
    }
}

fclose($output);
exit();