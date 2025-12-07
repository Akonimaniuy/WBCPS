<?php
session_start();
require 'admin/lib/config.php';

// Simple Router
$route = $_GET['route'] ?? '';

// Define routes and their corresponding files
$routes = [
    '' => 'home.php',
    'dashboard' => 'dashboard.php',
    'login' => 'login.php',
    'logout' => 'logout.php',
    'pathways' => 'pathways.php',
    'assessment' => 'assessment.php',
    'results' => 'results.php',
    'retake-assessment' => 'retake_assessment.php',
];

// Dynamic route for pathway details, e.g., /pathway/12
if (preg_match('#^pathway/(\d+)$#', $route, $matches)) {
    $_GET['id'] = $matches[1]; // Make the ID available to the included file
    $file_to_include = 'pathway_details.php';
} elseif (isset($routes[$route])) {
    $file_to_include = $routes[$route];
} else {
    // Handle 404 Not Found
    http_response_code(404);
    $file_to_include = '404.php'; // You can create a 404.php for a nice error page
}

if (file_exists($file_to_include)) {
    // The logic for pathway links needs to be available for multiple pages
    // We need to check if the file exists before requiring it.
    if (file_exists('pathways_logic.php') && in_array($file_to_include, ['home.php', 'pathways.php', 'pathway_details.php'])) {
        require 'pathways_logic.php';
    }
    include $file_to_include;
} else {
    // Fallback for missing files
    echo "Error: The requested page could not be found.";
}