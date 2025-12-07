<?php
// This check should be at the top of every admin page, before any HTML output.
// Since this file is included, the check is now here.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

// Redirect if not an admin
if (!$is_admin) {
    // You might want to redirect to the home page or a login page
    header("Location: /WBCPS/login.php");
    exit();
}

// Determine the active page to highlight it in the navbar
$current_page = basename($_SERVER['PHP_SELF']);

function admin_nav_link($route, $text, $current_page) {
    // Map 'index.php' to the 'dashboard' route for active state checking
    $target_file = $route . '.php';
    if ($route === 'dashboard') {
        $target_file = 'index.php';
    }

    $is_active = ($current_page === $target_file);
    // Point the href directly to the PHP file.
    $href = '/WBCPS/admin/' . $target_file;

    $base_class = 'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200';
    $active_class = 'bg-yellow-500 text-gray-900 font-bold';
    $inactive_class = 'text-gray-300 hover:bg-gray-700 hover:text-white';
    $class = $is_active ? $active_class : $inactive_class;

    echo "<li><a href='$href' class='$base_class $class'>$text</a></li>";
}
?>

<!-- Sidebar (visible on md and larger screens) -->
<aside id="sidebar" class="bg-gray-800 text-white w-64 min-h-screen p-4 flex-col fixed md:flex hidden z-20">
    <a href="/WBCPS/admin/index.php" class="flex items-center pb-4 border-b border-gray-700">
        <span class="text-white text-2xl font-bold font-serif">Admin Panel</span>
    </a>
    <nav class="flex-grow mt-4">
        <ul class="space-y-2">
            <?php admin_nav_link('dashboard', 'Dashboard', $current_page); ?>
            <?php admin_nav_link('user_driven', 'Manage Users', $current_page); ?>
            <?php admin_nav_link('major_tracks_driven', 'Majors & Tracks', $current_page); ?>
            <?php admin_nav_link('manage_categories', 'Manage Categories', $current_page); ?>
            <?php admin_nav_link('assessments_driven', 'Manage Assessment', $current_page); ?>
            <?php admin_nav_link('stats_driven', 'Assessment Stats', $current_page); ?>
        </ul>
    </nav>
    <div class="pt-4 border-t border-gray-700">
        <ul class="space-y-2">
            <li><a href="/WBCPS/" target="_blank" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">View Site</a></li>
            <li><a href="/WBCPS/logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Logout</a></li>
        </ul>
    </div>
</aside>

<!-- Top bar (visible on mobile) -->
<header class="md:hidden bg-gray-800 shadow-lg p-4 flex justify-between items-center fixed top-0 left-0 right-0 z-30">
    <a href="/WBCPS/admin/index.php" class="text-white text-xl font-bold font-serif">Admin Panel</a>
    <button id="mobile-menu-button" class="text-white focus:outline-none">
        <svg id="hamburger-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
        <svg id="close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
</header>

<!-- Mobile Menu (hidden by default) -->
<div id="mobile-menu" class="md:hidden fixed top-16 left-0 right-0 bottom-0 bg-gray-800 p-4 z-20 hidden overflow-y-auto">
    <nav class="flex-grow mt-4">
        <ul class="space-y-2">
            <?php admin_nav_link('dashboard', 'Dashboard', $current_page); ?>
            <?php admin_nav_link('user_driven', 'Manage Users', $current_page); ?>
            <?php admin_nav_link('major_tracks_driven', 'Majors & Tracks', $current_page); ?>
            <?php admin_nav_link('manage_categories', 'Manage Categories', $current_page); ?>
            <?php admin_nav_link('assessments_driven', 'Manage Assessment', $current_page); ?>
            <?php admin_nav_link('stats_driven', 'Assessment Stats', $current_page); ?>
        </ul>
    </nav>
    <div class="pt-4 border-t border-gray-700">
        <ul class="space-y-2">
            <li><a href="/WBCPS/" target="_blank" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">View Site</a></li>
            <li><a href="/WBCPS/logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">Logout</a></li>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const hamburgerIcon = document.getElementById('hamburger-icon');
        const closeIcon = document.getElementById('close-icon');

        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
                hamburgerIcon.classList.toggle('hidden');
                closeIcon.classList.toggle('hidden');
            });
        }
    });
</script>
