<?php
session_start();
require 'admin/lib/config.php';

header('Content-Type: application/json');

// --- Security and Input Validation ---
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$question_id = isset($data['question_id']) ? (int)$data['question_id'] : 0;
$user_answer = isset($data['answer']) ? $data['answer'] : null;

if (!$question_id || !$user_answer || !isset($_SESSION['adaptive_assessment_state'])) {
    echo json_encode(['error' => 'Invalid request data.']);
    exit;
}

$state = &$_SESSION['adaptive_assessment_state'];

// --- Increment question counter at the beginning ---
$state['questions_answered']++;

// --- 1. Check the submitted answer and update score ---
$stmt = $conn->prepare("SELECT major_id, correct_answer, difficulty_level, question_type FROM assessments WHERE id = ?");
$stmt->bind_param("i", $question_id);
$stmt->execute();
$result = $stmt->get_result();
$question_data = $result->fetch_assoc();
$stmt->close();

if (!$question_data) {
    echo json_encode(['error' => 'Question not found.']);
    exit;
}

$major_id = $question_data['major_id'];
$question_type = $question_data['question_type']; // e.g., 'Interest', 'Skills'
$is_correct = ($user_answer === $question_data['correct_answer']);

if ($is_correct) {
    // Add points based on difficulty
    $points = $question_data['difficulty_level'];
    // Reset consecutive wrong answers for this major
    $state['consecutive_wrong_answers'][$major_id] = 0;
    $state['scores'][$major_id]['total'] += $points;
    if (array_key_exists($question_type, $state['scores'][$major_id])) {
        $state['scores'][$major_id][$question_type] += $points;
    }
} else {
    // Increment consecutive wrong answers for this major
    $state['consecutive_wrong_answers'][$major_id]++;
}

// Track the maximum possible points for EVERY question asked, regardless of correctness.
if (!isset($state['max_possible_scores'][$major_id][$question_type])) {
    $state['max_possible_scores'][$major_id][$question_type] = 0;
}
$state['max_possible_scores'][$major_id][$question_type] += $question_data['difficulty_level'];

// Store the major_id of the question that was just answered.
$state['last_major_id'] = $major_id;

// Mark question as answered
$state['answered_questions'][] = $question_id;

// Update the current difficulty for this major
$current_difficulty = $state['difficulty_levels'][$major_id];
if ($is_correct) {
    $state['difficulty_levels'][$major_id] = min(3, $current_difficulty + 1); // Increase difficulty, max 3
} else {
    $state['difficulty_levels'][$major_id] = max(1, $current_difficulty - 1); // Decrease difficulty, min 1
}

// --- 2. Determine the next major to ask a question from ---
$num_majors = count($state['selected_majors']);
$baseline_questions_total = $num_majors * 5;

if ($state['questions_answered'] < $baseline_questions_total) { // Still in baseline
    // --- Baseline Phase: Simple Rotation ---
    // Ask 5 questions for one major, then 5 for the next, and so on.
    $next_major_index = floor($state['questions_answered'] / 5);
    $next_major_id = $state['selected_majors'][$next_major_index];
} elseif ($state['questions_answered'] === $baseline_questions_total) { // Just finished baseline
    // --- Transition to Adaptive Phase ---
    // Find the major with the highest score from the baseline.
    uasort($state['scores'], function($a, $b) { return $b['total'] <=> $a['total']; });
    $next_major_id = array_key_first($state['scores']);
    // Store this as the current focus for the adaptive phase.
    $state['current_adaptive_major_id'] = $next_major_id;
} else {
    // --- Adaptive Phase: Performance-based Rotation ---
    // Default to staying on the current adaptive major.
    $next_major_id = $state['current_adaptive_major_id'];

    // Check if the user has 3 consecutive wrong answers for the current major.
    if ($state['consecutive_wrong_answers'][$next_major_id] >= 3) {
        // Reset the counter for this major since we are switching away.
        $state['consecutive_wrong_answers'][$next_major_id] = 0;
        
        // Find the index of the major we are switching from.
        $current_index = array_search($next_major_id, $state['selected_majors']);
        
        // Move to the next major in the list (round-robin).
        $next_index = ($current_index + 1) % $num_majors;
        $next_major_id = $state['selected_majors'][$next_index];

        // Update the state to reflect the new focus major.
        $state['current_adaptive_major_id'] = $next_major_id;
        
        // Set difficulty to 1 for the new major
        $state['difficulty_levels'][$next_major_id] = 1;
    }
}

if ($state['questions_answered'] < $baseline_questions_total) {
    // --- Baseline Phase: Force difficulty to 2 ---
    $next_difficulty = 2;
} elseif ($state['questions_answered'] === $baseline_questions_total) {
    // --- First question after baseline: Force difficulty to 3 ---
    $next_difficulty = 3;
} else {
    // --- Adaptive Phase: Use the stored adaptive difficulty ---
    $next_difficulty = $state['difficulty_levels'][$next_major_id];
}

// --- 3. Fetch the next adaptive question ---
$answered_ids_placeholder = implode(',', array_fill(0, count($state['answered_questions']), '?'));
$types = 'ii' . str_repeat('i', count($state['answered_questions']));
$params = [$next_major_id, $next_difficulty, ...$state['answered_questions']];

// Try to find a question at the target difficulty. If not found, try other difficulties.
$sql = "SELECT a.id, a.question, a.option_a, a.option_b, a.option_c, a.option_d, m.major
        FROM assessments a
        JOIN majors m ON a.major_id = m.id
        WHERE a.major_id = ? AND a.difficulty_level = ? AND a.id NOT IN ($answered_ids_placeholder)
        ORDER BY RAND() LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$next_question = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fallback: if no question found at the target difficulty, find any question for that major
if (!$next_question) {
    $sql_fallback = "SELECT a.id, a.question, a.option_a, a.option_b, a.option_c, a.option_d, m.major
                     FROM assessments a
                     JOIN majors m ON a.major_id = m.id
                     WHERE a.major_id = ? AND a.id NOT IN ($answered_ids_placeholder)
                     ORDER BY RAND() LIMIT 1";
    $params_fallback = [$next_major_id, ...$state['answered_questions']];
    $types_fallback = 'i' . str_repeat('i', count($state['answered_questions']));
    $stmt = $conn->prepare($sql_fallback);
    $stmt->bind_param($types_fallback, ...$params_fallback);
    $stmt->execute();
    $next_question = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// --- 4. Check if the assessment is complete ---
$is_complete = ($state['questions_answered'] >= $state['total_questions_to_ask']);

if ($is_complete || !$next_question) {
    // Finalize scores in the database
    $user_id = $_SESSION['user_id'];
    $taken_at = date('Y-m-d H:i:s');

    $insert_stmt = $conn->prepare(
        "INSERT INTO user_assessments (user_id, major_id, score, interest_score, skills_score, strengths_score, max_interest_score, max_skills_score, max_strengths_score, taken_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($state['scores'] as $m_id => $score_data) {
        // --- Percentage Calculation ---
        // 1. Fetch the weights for this major
        $weights_stmt = $conn->prepare("SELECT interest_weight, skills_weight, strengths_weight FROM majors WHERE id = ?");
        $weights_stmt->bind_param("i", $m_id);
        $weights_stmt->execute();
        $weights = $weights_stmt->get_result()->fetch_assoc();
        $weights_stmt->close();

        $final_percentage = 0;
        $category_types = ['Interest', 'Skills', 'Strengths'];

        foreach ($category_types as $type) {
            $user_score = $score_data[$type] ?? 0;
            $max_score = $state['max_possible_scores'][$m_id][$type] ?? 0;
            $weight_key = strtolower($type) . '_weight';
            $weight = $weights[$weight_key] ?? 0;

            if ($max_score > 0) {
                // Calculate the percentage achieved for this category
                $category_percentage = ($user_score / $max_score) * 100;
                // Apply the major's weight to this category's percentage
                $final_percentage += ($category_percentage / 100) * $weight;
            }
        }

        // The final score is now the calculated percentage, rounded.
        $final_score = round($final_percentage);

        // Get max possible scores for insertion
        $max_interest = $state['max_possible_scores'][$m_id]['Interest'] ?? 0;
        $max_skills = $state['max_possible_scores'][$m_id]['Skills'] ?? 0;
        $max_strengths = $state['max_possible_scores'][$m_id]['Strengths'] ?? 0;

        $insert_stmt->bind_param("iiiiiiiiss", 
            $user_id, 
            $m_id, 
            $final_score, // Store the final percentage as the main score
            $score_data['Interest'], 
            $score_data['Skills'], 
            $score_data['Strengths'],
            $max_interest,
            $max_skills,
            $max_strengths,
            $taken_at
        );
        $insert_stmt->execute();
    }
    $insert_stmt->close();
    unset($_SESSION['adaptive_assessment_state']);
}

echo json_encode([
    'next_question' => $next_question,
    'is_complete' => $is_complete,
    'progress' => $state['questions_answered'],
    'total' => $state['total_questions_to_ask']
]);
?>