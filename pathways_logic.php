<?php
// This file is included on pages that need to generate links to pathway details.

// Determine the link for "Explore" or "Learn More" based on login status.
$is_logged_in = isset($_SESSION['user_id']);

// If the user is logged in, the link goes to pathway_details.php with the major's ID.
// If not, it triggers the login modal on the current page.
$read_more_link = $is_logged_in ? '/WBCPS/pathway/' : '#';