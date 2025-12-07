<?php
require_once("lib/config.php");

// Start session and perform authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Redirect or show an error for unauthorized access
    die("Unauthorized access.");
}

// Set headers for CSV download
$filename = "user_list_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Write the CSV header row
fputcsv($output, ['ID', 'Name', 'Email', 'Status', 'Role']);

// Fetch user data from the database
$usersQuery = $conn->query("SELECT id, name, email, status, role FROM users ORDER BY id ASC");

if ($usersQuery && $usersQuery->num_rows > 0) {
    // Loop through the rows and write them to the CSV file
    while ($row = $usersQuery->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['status'],
            $row['role']
        ]);
    }
}

fclose($output);
exit();