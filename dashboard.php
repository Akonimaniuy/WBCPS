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
        <h2 class="text-3xl font-bold text-gray-900 mb-4">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <p class="text-gray-600 text-lg mb-8">You're on the right path to discovering your future career. Here’s what you can do next:</p>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- Take Assessment Card -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <h3 class="text-2xl font-semibold text-gray-800 mb-3">Take the Assessment</h3>
                <p class="text-gray-600 mb-4">Answer a few questions to find out which career pathways best match your interests and skills.</p>
                <a href="assessment.php" class="bg-yellow-400 text-gray-900 px-6 py-2 font-bold rounded-full hover:bg-yellow-500 transition-colors">Start Assessment</a>
            </div>

            <!-- Explore Pathways Card -->
            <div class="bg-gray-50 border-l-4 border-gray-400 p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <h3 class="text-2xl font-semibold text-gray-800 mb-3">Explore Pathways</h3>
                <p class="text-gray-600 mb-4">Browse through all available career pathways to learn more about different fields and opportunities.</p>
                <a href="pathways.php" class="bg-gray-700 text-white px-6 py-2 font-bold rounded-full hover:bg-gray-800 transition-colors">Explore Now</a>
            </div>
        </div>
    </div>
</main>

</body>
</html>