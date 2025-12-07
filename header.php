<?php
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

// Determine the active page from the route to highlight it in the navbar
$current_route = $_GET['route'] ?? '';

function nav_link($route, $text, $current_route, $is_mobile = false) {
    $href = '/WBCPS/' . $route;
    $is_active = ($route === $current_route);
    $base_class = $is_mobile ? 'block px-3 py-2 rounded-md text-base font-medium' : 'px-3 py-2 rounded-md text-sm font-medium';
    $active_class = 'bg-yellow-500 text-black font-bold';
    $inactive_class = 'text-gray-300 hover:bg-gray-700 hover:text-white';
    $class = $is_active ? $active_class : $inactive_class;

    // Special case for the login button to trigger the modal
    if ($text === 'Login') {
        echo "<button class='openAuthModalBtn $base_class $class'>$text</button>";
    } else {
        echo "<a href='$href' class='$base_class $class'>$text</a>";
    }
}
?>
<header class="bg-gray-800 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex-shrink-0">
                <a href="/WBCPS/" class="text-white text-2xl font-bold font-serif">Career Pathway</a>
            </div>
            <div class="hidden md:block">
                <div class="ml-10 flex items-baseline space-x-4">
                    <?php if ($is_admin): ?>
                        <?php nav_link('', 'Home', $current_route, false); ?>
                        <?php nav_link('dashboard', 'Dashboard', $current_route, false); ?>
                        <?php nav_link('pathways', 'Pathways', $current_route, false); ?>
                        <?php nav_link('assessment', 'Assessment', $current_route, false); ?>
                        <a href="admin/index.php" class='px-3 py-2 rounded-md text-sm font-medium text-gray-300 bg-red-600 hover:bg-red-700'>Admin Panel</a>
                        <?php nav_link('logout', 'Logout', $current_route, false); ?>
                    <?php elseif ($is_logged_in): ?>
                        <?php nav_link('', 'Home', $current_route, false); ?>
                        <?php nav_link('dashboard', 'Dashboard', $current_route, false); ?>
                        <?php nav_link('pathways', 'Pathways', $current_route, false); ?>
                        <?php nav_link('assessment', 'Assessment', $current_route, false); ?>
                        <?php nav_link('logout', 'Logout', $current_route, false); ?>
                    <?php else: ?>
                        <?php nav_link('', 'Home', $current_route, false); ?>
                        <?php nav_link('about', 'About', $current_route, false); // Assuming you will create an about.php ?>
                        <?php nav_link('pathways', 'Pathways', $current_route, false); ?>
                        <?php nav_link('assessment', 'Assessment', $current_route, false); ?>
                        <?php nav_link('login', 'Login', $current_route, false); ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Mobile menu button -->
            <div class="-mr-2 flex md:hidden">
                <button id="mobile-menu-button" type="button" class="bg-gray-800 inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg id="hamburger-icon" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg id="close-icon" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu, show/hide based on menu state. -->
    <div class="md:hidden hidden" id="mobile-menu">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <?php if ($is_admin): ?>
                <?php nav_link('', 'Home', $current_route, true); ?>
                <?php nav_link('dashboard', 'Dashboard', $current_route, true); ?>
                <?php nav_link('pathways', 'Pathways', $current_route, true); ?>
                <?php nav_link('assessment', 'Assessment', $current_route, true); ?>
                <a href="admin/index.php" class='block px-3 py-2 rounded-md text-base font-medium text-gray-300 bg-red-600 hover:bg-red-700'>Admin Panel</a>
                <?php nav_link('logout', 'Logout', $current_route, true); ?>
            <?php elseif ($is_logged_in): ?>
                <?php nav_link('', 'Home', $current_route, true); ?>
                <?php nav_link('dashboard', 'Dashboard', $current_route, true); ?>
                <?php nav_link('pathways', 'Pathways', $current_route, true); ?>
                <?php nav_link('assessment', 'Assessment', $current_route, true); ?>
                <?php nav_link('logout', 'Logout', $current_route, true); ?>
            <?php else: ?>
                <?php nav_link('', 'Home', $current_route, true); ?>
                <?php nav_link('about', 'About', $current_route, true); ?>
                <?php nav_link('pathways', 'Pathways', $current_route, true); ?>
                <?php nav_link('assessment', 'Assessment', $current_route, true); ?>
                <?php nav_link('login', 'Login', $current_route, true); ?>
            <?php endif; ?>
        </div>
    </div>
</header>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const hamburgerIcon = document.getElementById('hamburger-icon');
        const closeIcon = document.getElementById('close-icon');

        mobileMenuButton.addEventListener('click', () => {
            const isExpanded = mobileMenuButton.getAttribute('aria-expanded') === 'true';
            mobileMenuButton.setAttribute('aria-expanded', !isExpanded);
            mobileMenu.classList.toggle('hidden');
            hamburgerIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');
        });
    });
</script>