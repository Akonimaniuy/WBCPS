<?php
session_start();
require 'admin/lib/config.php';

// If the user is sent here to log in, set the session message and redirect to show the modal.
if (isset($_GET['action']) && $_GET['action'] === 'require_login') {
    $_SESSION['auth_message'] = "You must be logged in to view pathway details.";
    $_SESSION['auth_message_type'] = 'error';
    header('Location: pathways.php');
    exit;
}

// Fetch majors from the database
$majors = [];
$result = $conn->query("SELECT id, major, description, image FROM majors ORDER BY major ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $majors[] = $row;
    }
}

// Determine the link for "Read more" based on login status
$is_logged_in = isset($_SESSION['user_id']);
$read_more_link = $is_logged_in ? 'pathway_details.php?id=' : 'pathways.php?action=require_login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Explore Pathways - Career Pathway</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
    </style>
</head>
<body class="bg-gray-100">

<?php include 'header.php'; ?>

<main>
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl font-serif">
                Explore Career Pathways
            </h2>
            <p class="mt-4 text-lg text-gray-500">
                Browse our curated list of career majors to find your perfect fit.
            </p>
        </div>

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php if (empty($majors)): ?>
                <p class="col-span-full text-center text-gray-500">No career pathways have been added yet. Please check back later.</p>
            <?php else: ?>
                <?php foreach ($majors as $major): ?>
                <div class="flex flex-col rounded-lg shadow-lg overflow-hidden transform hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex-shrink-0">
                        <img class="h-48 w-full object-cover" src="admin/uploads/<?php echo htmlspecialchars($major['image'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($major['major']); ?>">
                    </div>
                    <div class="flex-1 bg-white p-6 flex flex-col justify-between">
                        <div class="flex-1">
                            <p class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($major['major']); ?></p>
                            <p class="mt-3 text-base text-gray-500 h-24 overflow-hidden"><?php echo htmlspecialchars($major['description']); ?></p>
                        </div>
                        <div class="mt-6">
                            <a href="<?php echo $read_more_link . ($is_logged_in ? $major['id'] : ''); ?>" class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-gray-800 hover:bg-gray-900">
                                <?php echo $is_logged_in ? 'Learn More' : 'Login to Learn More'; ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'auth_modal.php'; ?>
</body>
</html>
