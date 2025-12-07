<?php
session_start();
require 'admin/lib/config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's assessment results
$stmt = $conn->prepare(
    "SELECT m.major, m.description, m.image, ua.score 
     FROM user_assessments ua
     JOIN majors m ON ua.major_id = m.id
     WHERE ua.user_id = ?
     ORDER BY ua.score DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result();

$assessment_results = [];
while ($row = $results->fetch_assoc()) {
    $assessment_results[] = $row;
}
$stmt->close();

$top_recommendation = !empty($assessment_results) ? $assessment_results[0] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results - Career Pathway</title>
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
        <h2 class="text-3xl font-bold text-gray-900 mb-4">Your Assessment Results</h2>

        <?php if (empty($assessment_results)): ?>
            <p class="text-gray-600">You have not completed the assessment yet. <a href="assessment.php" class="text-yellow-600 hover:underline">Take it now!</a></p>
        <?php else: ?>
            <!-- Top Recommendation -->
            <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6 mb-8 text-center">
                <h3 class="text-2xl font-bold text-gray-800">Your Top Recommendation</h3>
                <h4 class="text-4xl font-extrabold text-yellow-600 my-3"><?php echo htmlspecialchars($top_recommendation['major']); ?></h4>
                <p class="text-gray-600 max-w-2xl mx-auto"><?php echo htmlspecialchars($top_recommendation['description']); ?></p>
            </div>

            <!-- All Results -->
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Your Score Breakdown</h3>
            <div class="space-y-4">
                <?php foreach ($assessment_results as $result): ?>
                <div class="bg-gray-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <span class="font-bold text-lg text-gray-700"><?php echo htmlspecialchars($result['major']); ?></span>
                    <span class="bg-yellow-400 text-gray-900 font-bold px-4 py-1 rounded-full"><?php echo htmlspecialchars($result['score']); ?> Points</span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-8">
                <a href="assessment.php" class="text-yellow-600 hover:underline font-medium">Want to try again? Retake the Assessment</a>
            </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>