<?php
session_start();
require 'lib/config.php';

// Redirect to login if not logged in as an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /WBCPS/login.php");
    exit();
}

$admin_name = $_SESSION['name'] ?? 'Admin';

// Example data fetching (you can replace these with actual queries)
$user_count_result = $conn->query("SELECT COUNT(*) as count FROM users");
$user_count = $user_count_result->fetch_assoc()['count'];

$major_count_result = $conn->query("SELECT COUNT(*) as count FROM majors");
$major_count = $major_count_result->fetch_assoc()['count'];

$assessment_taken_result = $conn->query("SELECT COUNT(DISTINCT user_id, taken_at) as count FROM user_assessments");
$assessment_taken_count = $assessment_taken_result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Career Pathway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
    </style>
</head>
<body class="bg-gray-100 flex">

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="flex-1 md:ml-64">
<main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 pt-24 md:pt-12">
    <div class="bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-gray-900 mb-4">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h2>
        <p class="text-gray-600 text-lg mb-8">This is your control center. Manage users, pathways, and assessments from here.</p>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-blue-50 p-6 rounded-lg shadow-md border-l-4 border-blue-400">
                <h3 class="text-xl font-semibold text-gray-800">Total Users</h3>
                <p class="text-4xl font-bold text-blue-600 mt-2"><?php echo $user_count; ?></p>
            </div>
            <div class="bg-green-50 p-6 rounded-lg shadow-md border-l-4 border-green-400">
                <h3 class="text-xl font-semibold text-gray-800">Career Majors</h3>
                <p class="text-4xl font-bold text-green-600 mt-2"><?php echo $major_count; ?></p>
            </div>
            <div class="bg-yellow-50 p-6 rounded-lg shadow-md border-l-4 border-yellow-400">
                <h3 class="text-xl font-semibold text-gray-800">Assessments Taken</h3>
                <p class="text-4xl font-bold text-yellow-600 mt-2"><?php echo $assessment_taken_count; ?></p>
            </div>
        </div>

    </div>
</main>
</div>

</body>
</html>