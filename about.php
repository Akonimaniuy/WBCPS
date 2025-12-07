<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Career Pathway</title>
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
        <h2 class="text-3xl font-bold text-gray-900 mb-4">About Career Pathway</h2>
        <p class="text-gray-600 text-lg mb-6">
            Welcome to the Career Pathway system, a dedicated platform designed to guide students toward their ideal career paths. Our mission is to simplify the complex process of career selection by providing insightful, personalized recommendations.
        </p>
        <h3 class="text-2xl font-semibold text-gray-700 mt-8 mb-3">Our Vision</h3>
        <p class="text-gray-600 text-lg mb-6">
            We believe that every student deserves to find a career that aligns with their passions and skills. Our adaptive assessment tool analyzes your responses to suggest pathways where you are most likely to succeed and find fulfillment.
        </p>
    </div>
</main>

<?php include 'auth_modal.php'; ?>

</body>
</html>