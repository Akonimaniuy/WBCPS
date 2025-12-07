
<?php
// This file is now included by index.php, which has already started the session.
// Fetch majors grouped by category
$all_majors = [];
$sql = "SELECT c.name as category_name, m.id, m.major, m.description, m.image, m.link 
        FROM majors m 
        JOIN categories c ON m.category_id = c.id 
        ORDER BY c.id, m.id";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_majors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Future, Unlocked - Career Pathway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        .hero-bg { background: url(images/background.png) no-repeat center center / cover; }
        /* Hide scrollbar for the carousel */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        /* Ensure cards have a consistent size in the carousel */
        .carousel-card { flex: 0 0 16rem; /* 256px on mobile */ }
        @media (min-width: 640px) { .carousel-card { flex: 0 0 18rem; /* 288px on sm and up */ } }
        .carousel-container { mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent); }
    </style>
</head>
<body class="bg-gray-900 text-white">

<?php include 'header.php'; ?>

<!-- Hero Section -->
<div class="hero-bg">
    <div class="bg-black bg-opacity-60 min-h-screen flex flex-col justify-center items-center p-4 sm:p-6 lg:p-8">
        <div class="text-center">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold font-serif tracking-tight">
                Your Future, <span class="text-yellow-400">Unlocked.</span>
            </h1>
            <p class="mt-4 max-w-2xl mx-auto text-lg sm:text-xl text-gray-300">
                Discover the perfect career path tailored to your skills and interests.
            </p>
        </div>

        <?php if (empty($all_majors)): ?>
            <p class="text-center text-gray-400 mt-12">No majors found. Please check back later.</p>
        <?php else: ?>
            <div class="mt-12 w-full max-w-7xl carousel-container overflow-x-hidden">
                <div id="majorsCarousel" class="flex space-x-8 no-scrollbar">
                    <!-- PHP loop will be duplicated by JS for infinite effect -->
                    <?php foreach ($all_majors as $major): ?>
                    <div class="carousel-card relative flex flex-col rounded-lg shadow-lg overflow-hidden bg-white text-gray-900 transform hover:-translate-y-1 transition-transform duration-300">
                        <span class="absolute top-2 left-2 bg-yellow-400 text-gray-900 px-2 py-1 text-xs font-bold rounded-full z-10"><?php echo htmlspecialchars($major['category_name']); ?></span>
                        <div class="flex-shrink-0 h-48">
                            <img class="h-full w-full object-cover" src="admin/uploads/<?php echo htmlspecialchars($major['image'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($major['major']); ?>">
                        </div>
                        <div class="flex-1 p-6 flex flex-col justify-between">
                            <div class="flex-1">
                                <h3 class="text-xl font-semibold font-serif"><?php echo htmlspecialchars($major['major']); ?></h3>
                                <p class="mt-3 text-sm text-gray-500 h-20 overflow-hidden"><?php echo htmlspecialchars($major['description']); ?></p>
                            </div>
                            <div class="mt-4">
                                <a href="<?php echo $is_logged_in && !empty($major['link']) ? htmlspecialchars($major['link']) : $read_more_link; ?>" <?php if (!$is_logged_in) { echo 'onclick="document.getElementById(\'authModal\').classList.remove(\'hidden\'); return false;"'; } ?> target="_blank" rel="noopener noreferrer" class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-gray-800 hover:bg-gray-900">
                                    <?php echo $is_logged_in ? 'Explore' : 'Login to Explore'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'auth_modal.php'; ?>
<?php include 'forgot_password_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.getElementById('majorsCarousel');
    if (!carousel) return;

    // Duplicate items for a seamless loop
    const originalContent = carousel.innerHTML;
    carousel.innerHTML += originalContent;

    let isPaused = false;
    let scrollAmount = 0;
    const scrollWidth = carousel.scrollWidth / 2;

    function scrollCarousel() {
        if (!isPaused) {
            scrollAmount += 0.5; // Adjust speed here (lower is slower)
            if (scrollAmount >= scrollWidth) {
                scrollAmount = 0;
            }
            carousel.style.transform = `translateX(-${scrollAmount}px)`;
        }
        requestAnimationFrame(scrollCarousel);
    }

    carousel.addEventListener('mouseenter', () => isPaused = true);
    carousel.addEventListener('mouseleave', () => isPaused = false);

    requestAnimationFrame(scrollCarousel);
});
</script>
</body>
</html>
