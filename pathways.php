<?php
session_start();
require 'admin/lib/config.php';
require 'pathways_logic.php'; // Include the shared logic for pathway links

// Get selected category from URL, default to 0 (all)
$selected_category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Fetch all categories for the dropdown
$categories = [];
$category_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch majors from the database
$majors = [];
$sql = "SELECT id, major, description, image, link FROM majors";
if ($selected_category_id > 0) {
    $sql .= " WHERE category_id = ? ORDER BY major ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_category_id);
} else {
    $sql .= " ORDER BY major ASC";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $majors[] = $row;
    }
}
$stmt->close();
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

        <!-- Category Filter Dropdown -->
        <div class="mb-10 flex justify-center">
            <form action="/WBCPS/pathways" method="GET" class="w-full max-w-sm">
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1 text-center">Filter by Category</label>
                <select id="category" name="category" onchange="this.form.submit()" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm rounded-md shadow-sm">
                    <option value="0" <?php if ($selected_category_id == 0) echo 'selected'; ?>>All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php if ($selected_category_id == $category['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($majors)): ?>
            <p class="text-center text-gray-500">No career pathways have been added yet. Please check back later.</p>
        <?php else: ?>
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
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
                            <a href="<?php echo $is_logged_in && !empty($major['link']) ? htmlspecialchars($major['link']) : $read_more_link; ?>" <?php if (!$is_logged_in) { echo 'onclick="document.getElementById(\'authModal\').classList.remove(\'hidden\'); return false;"'; } ?> target="_blank" rel="noopener noreferrer" class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-gray-800 hover:bg-gray-900">
                                <?php echo $is_logged_in ? 'Learn More' : 'Login to Learn More'; ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'auth_modal.php'; ?>
</body>
</html>
