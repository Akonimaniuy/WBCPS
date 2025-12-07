<?php
session_start();
include_once("lib/config.php");

header('Content-Type: application/json');

// Check for user ID and admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_GET['user_id']) || !isset($_GET['taken_at'])) {
    echo json_encode(['error' => 'Missing required parameters.']);
    exit();
}

$user_id = (int)$_GET['user_id'];
$taken_at = $_GET['taken_at'];

// Fetch user's assessment results for a specific timestamp
$stmt = $conn->prepare(
    "SELECT u.name as student_name, m.major, m.description, ua.score 
     FROM user_assessments ua
     JOIN majors m ON ua.major_id = m.id
     JOIN users u ON ua.user_id = u.id
     WHERE ua.user_id = ? AND ua.taken_at = ?
     ORDER BY ua.score DESC"
);

if (!$stmt) {
    echo json_encode(['error' => 'Database query preparation failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("is", $user_id, $taken_at);
$stmt->execute();
$result = $stmt->get_result();

$assessment_results = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assessment_results[] = $row;
    }
}
$stmt->close();

echo json_encode($assessment_results);
exit();
?>