<?php
session_start();
require 'admin/lib/config.php'; // Include DB connection

header('Content-Type: application/json');

// Only proceed if an assessment is in progress
if (!isset($_SESSION['adaptive_assessment_state'])) {
    echo json_encode(['status' => 'no_assessment']);
    exit;
}

$max_leaves = 3;
$state = &$_SESSION['adaptive_assessment_state'];

// Initialize leave attempts if not set
if (!isset($state['leave_attempts'])) {
    $state['leave_attempts'] = 0;
}

$state['leave_attempts']++;

if ($state['leave_attempts'] > $max_leaves) {
    // --- Log the disqualified attempt to the database ---
    if (isset($_SESSION['user_id']) && !empty($state['selected_majors'])) {
        $user_id = $_SESSION['user_id'];
        $taken_at = date('Y-m-d H:i:s');

        // Insert a zero-score record for each major in the attempt to mark it as disqualified.
        $stmt = $conn->prepare(
            "INSERT INTO user_assessments (user_id, major_id, score, interest_score, skills_score, strengths_score, taken_at) 
             VALUES (?, ?, 0, 0, 0, 0, ?)"
        );

        foreach ($state['selected_majors'] as $major_id) {
            $stmt->bind_param("iis", $user_id, $major_id, $taken_at);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Disqualify the user: clear assessment session data
    unset($_SESSION['assessment_majors']);
    unset($_SESSION['adaptive_assessment_state']);
    
    $_SESSION['auth_message'] = "You have been disqualified from the assessment for leaving the page too many times.";
    $_SESSION['auth_message_type'] = 'error';

    echo json_encode(['status' => 'disqualified', 'attempts' => $state['leave_attempts']]);
} else {
    echo json_encode(['status' => 'warned', 'attempts' => $state['leave_attempts'], 'max' => $max_leaves]);
}
?>