<?php
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

// Determine the active page to highlight it in the navbar
$active_page = basename($_SERVER['PHP_SELF']);

function nav_link($href, $text, $active_page) {
    $is_active = ($href === $active_page);
    $class = $is_active
        ? 'bg-yellow-500 text-black font-bold'
        : 'hover:bg-gray-700 hover:text-white';
    // Special case for the login button to trigger the modal
    if ($text === 'Login') {
        echo "<button id='openAuthModalBtn' class='px-3 py-2 rounded-md text-sm font-medium text-gray-300 $class'>$text</button>";
    } else {
        echo "<a href='$href' class='px-3 py-2 rounded-md text-sm font-medium text-gray-300 $class'>$text</a>";
    }
}
?>
<header class="bg-gray-800 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex-shrink-0">
                <a href="index.php" class="text-white text-2xl font-bold font-serif">Career Pathway</a>
            </div>
            <div class="hidden md:block">
                <div class="ml-10 flex items-baseline space-x-4">
                    <?php if ($is_admin): ?>
                        <?php nav_link('index.php', 'Home', $active_page); ?>
                        <?php nav_link('dashboard.php', 'Dashboard', $active_page); ?>
                        <?php nav_link('pathways.php', 'Pathways', $active_page); ?>
                        <?php nav_link('assessment.php', 'Assessment', $active_page); ?>
                        <a href="admin/index.php" class='px-3 py-2 rounded-md text-sm font-medium text-gray-300 bg-red-600 hover:bg-red-700'>Admin Panel</a>
                        <?php nav_link('logout.php', 'Logout', $active_page); ?>
                    <?php elseif ($is_logged_in): ?>
                        <?php nav_link('index.php', 'Home', $active_page); ?>
                        <?php nav_link('dashboard.php', 'Dashboard', $active_page); ?>
                        <?php nav_link('pathways.php', 'Pathways', $active_page); ?>
                        <?php nav_link('assessment.php', 'Assessment', $active_page); ?>
                        <?php nav_link('logout.php', 'Logout', $active_page); ?>
                    <?php else: ?>
                        <?php nav_link('index.php', 'Home', $active_page); ?>
                        <?php nav_link('about.php', 'About', $active_page); ?>
                        <?php nav_link('pathways.php', 'Pathways', $active_page); ?>
                        <?php nav_link('assessment.php', 'Assessment', $active_page); ?>
                        <?php nav_link('login.php', 'Login', $active_page); ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Mobile menu button can be added here if needed -->
        </div>
    </div>
</header>