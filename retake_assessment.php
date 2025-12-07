<?php
session_start();
require 'admin/lib/config.php'; // Need DB connection

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /WBCPS/");
    exit;
}

// Clear any old assessment state first to ensure a clean start
unset($_SESSION['assessment_majors']);
unset($_SESSION['adaptive_assessment_state']);

$user_id = $_SESSION['user_id'];
$taken_at = $_GET['taken_at'] ?? null;

if (!$taken_at) {
    // If no timestamp is provided, just go to the assessment selection page
    header("Location: /WBCPS/assessment");
    exit();
}

// Fetch the major IDs from the specified previous assessment
$stmt = $conn->prepare("SELECT DISTINCT major_id FROM user_assessments WHERE user_id = ? AND taken_at = ?");
$stmt->bind_param("is", $user_id, $taken_at);
$stmt->execute();
$result = $stmt->get_result();

$selected_majors = [];
while ($row = $result->fetch_assoc()) {
    $selected_majors[] = $row['major_id'];
}
$stmt->close();

if (count($selected_majors) > 0) {
    // We have the majors, now initialize the assessment state, just like in assessment.php
    $_SESSION['assessment_majors'] = $selected_majors;

    $scores = [];
    $difficulty_levels = [];
    $consecutive_wrong_answers = [];
    foreach ($selected_majors as $major_id) {
        $scores[$major_id] = ['total' => 0, 'Interest' => 0, 'Skills' => 0, 'Strengths' => 0];
        $difficulty_levels[$major_id] = 2; // Start at medium difficulty
        $consecutive_wrong_answers[$major_id] = 0;
    }

    $_SESSION['adaptive_assessment_state'] = [
        'selected_majors' => $selected_majors,
        'scores' => $scores,
        'difficulty_levels' => $difficulty_levels,
        'consecutive_wrong_answers' => $consecutive_wrong_answers,
        'answered_questions' => [0], // Dummy value to prevent SQL errors
        'questions_answered' => 0,
        'total_questions_to_ask' => 100,
        'leave_attempts' => 0
    ];

    // Set flag to show the warning modal
    $_SESSION['show_assessment_warning'] = true;
}

// Redirect to the assessment page. It will now skip major selection and start the questions.
header("Location: /WBCPS/assessment");
exit();
?>
