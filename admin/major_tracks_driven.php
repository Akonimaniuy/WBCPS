<?php
ob_start(); // Start output buffering for header redirects
require_once("lib/config.php"); // Include your database connection
  
// The header.php file now handles session starting and authentication.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize message variable
$message = '';
$error_message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_major'])) {
    $major = $_POST['major'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $link = $_POST['link'];
    $passing_percentage = (int)$_POST['passing_percentage'];
    $interest_weight = (int)$_POST['interest_weight'];
    $skills_weight = (int)$_POST['skills_weight'];
    $strengths_weight = (int)$_POST['strengths_weight'];

    if (($interest_weight + $skills_weight + $strengths_weight) !== 100) {
        $_SESSION['error_message'] = "Error: The sum of Interest, Skills, and Strengths weights must be exactly 100.";
    } else {

    // Handle image upload
    $imageName = "";
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);

        $imageName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $imageName;

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $imageName = "";
        }
    }

        $stmt = $conn->prepare("INSERT INTO majors (category_id, major, description, link, image, passing_percentage, interest_weight, skills_weight, strengths_weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssiiii", $category_id, $major, $description, $link, $imageName, $passing_percentage, $interest_weight, $skills_weight, $strengths_weight);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Major added successfully!";
    } else {
            $message = "Error: Could not add the major. " . $stmt->error;
        }
    }
    header("Location: major_tracks_driven.php");
    exit();
}

// Handle Update Major (from AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_major'])) {
    header('Content-Type: application/json');
    $major_id = $_POST['major_id'];
    $category_id = $_POST['category_id'];
    $major_name = $_POST['major'];
    $description = $_POST['description'];
    $link = $_POST['link'];
    $old_image = $_POST['old_image'];
    $passing_percentage = (int)$_POST['passing_percentage'];
    $interest_weight = (int)$_POST['interest_weight'];
    $skills_weight = (int)$_POST['skills_weight'];
    $strengths_weight = (int)$_POST['strengths_weight'];

    if (($interest_weight + $skills_weight + $strengths_weight) !== 100) {
        echo json_encode(['success' => false, 'message' => 'Error: The sum of Interest, Skills, and Strengths weights must be exactly 100.']);
        exit();
    }
    $imageName = $old_image;

    // Handle new image upload
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "uploads/";
        $imageName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $imageName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            // Delete old image if a new one is uploaded and exists
            if (!empty($old_image) && file_exists($targetDir . $old_image)) {
                unlink($targetDir . $old_image);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload new image.']);
            exit();
        }
    }

    $stmt = $conn->prepare("UPDATE majors SET category_id = ?, major = ?, description = ?, link = ?, image = ?, passing_percentage = ?, interest_weight = ?, skills_weight = ?, strengths_weight = ? WHERE id = ?");
    $stmt->bind_param("issssiiiii", $category_id, $major_name, $description, $link, $imageName, $passing_percentage, $interest_weight, $skills_weight, $strengths_weight, $major_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Major updated successfully!']);
    exit();
}

// Handle Delete Major (from AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_major'])) {
    header('Content-Type: application/json');
    $major_id = (int)$_POST['major_id'];

    // First, get the image filename to delete it from the server
    $img_stmt = $conn->prepare("SELECT image FROM majors WHERE id = ?");
    $img_stmt->bind_param("i", $major_id);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result()->fetch_assoc();
    if ($img_result && !empty($img_result['image']) && file_exists("uploads/" . $img_result['image'])) {
        unlink("uploads/" . $img_result['image']);
    }
    $img_stmt->close();

    $stmt = $conn->prepare("DELETE FROM majors WHERE id = ?");
    $stmt->bind_param("i", $major_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Major deleted successfully.']);
    } else {
        // It's good practice to also check for related records (e.g., in `assessments`) before deleting.
        echo json_encode(['success' => false, 'message' => 'Failed to delete major. It might be in use.']);
    }
    $stmt->close();
    exit();
}

// Fetch categories for the dropdown
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

// Check for success message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
// Check for error message
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Majors & Tracks - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100 flex">

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="w-full md:pl-64">
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 pt-20 md:pt-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Manage Majors & Tracks</h1>

        <!-- Success Message -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                <p class="font-bold">Success</p>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Display Majors/Tracks -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-8">
                 <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Existing Majors & Tracks</h2>
                    <div class="flex items-center gap-4">
                        <input type="text" id="majorSearchInput" class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500" placeholder="Search majors or categories...">
                        <button id="openAddMajorModal" class="bg-gray-800 text-white px-5 py-2 rounded-lg hover:bg-gray-700 font-semibold text-sm whitespace-nowrap">Add New Major</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="majorsTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">#</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Image</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Major</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Passing %</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Weights (I/S/St)</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="majorsTableBody" class="bg-white divide-y divide-gray-200">
                            <?php
                            $sql = "SELECT m.id, m.category_id, c.name as category_name, m.major, m.description, m.link, m.image, 
                                           m.passing_percentage, m.interest_weight, m.skills_weight, m.strengths_weight
                                    FROM majors m
                                    JOIN categories c ON m.category_id = c.id
                                    ORDER BY c.name, m.major ASC";
                            $result = $conn->query($sql);
                            $majorCounter = 1;
                            if ($result && $result->num_rows > 0) { // Check if there are rows
                                while ($row = $result->fetch_assoc()) { ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $majorCounter++; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if (!empty($row['image'])): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" alt="Major Image" class="h-12 w-12 object-cover rounded-md">
                                            <?php else: ?>
                                                <div class="h-12 w-12 bg-gray-200 rounded-md flex items-center justify-center text-xs text-gray-500">No Image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($row['major']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?php echo $row['passing_percentage']; ?>%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            <?php echo "{$row['interest_weight']}% / {$row['skills_weight']}% / {$row['strengths_weight']}%"; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">                                            
                                            <button 
                                                class="edit-major-btn inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-xs"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-category-id="<?php echo $row['category_id']; ?>"
                                                data-major="<?php echo htmlspecialchars($row['major']); ?>"
                                                data-description="<?php echo htmlspecialchars($row['description']); ?>"
                                                data-link="<?php echo htmlspecialchars($row['link'] ?? ''); ?>"
                                                data-image="<?php echo htmlspecialchars($row['image']); ?>"
                                                data-passing-percentage="<?php echo $row['passing_percentage']; ?>"
                                                data-interest-weight="<?php echo $row['interest_weight']; ?>"
                                                data-skills-weight="<?php echo $row['skills_weight']; ?>"
                                                data-strengths-weight="<?php echo $row['strengths_weight']; ?>"
                                            >
                                                Edit
                                            </button>
                                            <button 
                                                class="delete-major-btn inline-block bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-xs"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-major="<?php echo htmlspecialchars($row['major']); ?>"
                                            >Delete</button>
                                        </td>
                                    </tr>
                                <?php }
                            } else {
                                echo "<tr><td colspan='8' class='px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center'>No majors/tracks found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div id="pagination" class="px-8 py-4 flex justify-center items-center space-x-2"></div>
            </div>
        </div>
    </main>
</div>

<!-- Add Major Modal -->
<div id="addMajorModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Add New Major/Track</h3>
            <button id="closeAddModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700">Category</label>
                <select id="category_id" name="category_id" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <option value="">Select a Category</option>
                    <?php
                    // Reset pointer and loop through categories again for the modal
                    if ($categories_result) $categories_result->data_seek(0);
                    if ($categories_result && $categories_result->num_rows > 0) {
                        while ($category = $categories_result->fetch_assoc()) {
                            echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="major" class="block text-sm font-medium text-gray-700">Major Name</label>
                <input type="text" id="major" name="major" placeholder="e.g., Software Engineering" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
            </div>
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="description" name="description" placeholder="A brief description of the major" rows="3" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></textarea>
            </div>
            <div>
                <label for="link" class="block text-sm font-medium text-gray-700">Learn More Link (Optional)</label>
                <input type="url" id="link" name="link" placeholder="https://example.com/major-details" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="passing_percentage" class="block text-sm font-medium text-gray-700">Passing Percentage (%)</label>
                    <input type="number" id="passing_percentage" name="passing_percentage" value="75" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="interest_weight" class="block text-sm font-medium text-gray-700">Interest Weight (%)</label>
                    <input type="number" id="interest_weight" name="interest_weight" value="30" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                <div>
                    <label for="skills_weight" class="block text-sm font-medium text-gray-700">Skills Weight (%)</label>
                    <input type="number" id="skills_weight" name="skills_weight" value="40" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                <div>
                    <label for="strengths_weight" class="block text-sm font-medium text-gray-700">Strengths Weight (%)</label>
                    <input type="number" id="strengths_weight" name="strengths_weight" value="30" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                </div>
            </div>
            <p id="addWeightError" class="hidden text-sm text-red-600 font-semibold text-center">
                The sum of Interest, Skills, and Strengths weights must be exactly 100.
            </p>
            <div>
                <label for="image" class="block text-sm font-medium text-gray-700">Image (Optional)</label>
                <input type="file" id="image" name="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
            </div>
            <div>
                <button type="submit" name="add_major" class="w-full bg-gray-800 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors font-semibold">Add Major/Track</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Major Modal -->
<div id="editMajorModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Edit Major</h3>
            <button id="closeEditModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <form id="editMajorForm" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="update_major" value="1">
            <input type="hidden" id="edit_major_id" name="major_id">
            <input type="hidden" id="edit_old_image" name="old_image">
            
            <div>
                <label for="edit_category_id" class="block text-sm font-medium text-gray-700">Category</label>
                <select id="edit_category_id" name="category_id" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <option value="">Select a Category</option>
                    <?php
                    // Reset pointer and loop through categories again for the modal
                    if ($categories_result) $categories_result->data_seek(0);
                    if ($categories_result && $categories_result->num_rows > 0) {
                        while ($category = $categories_result->fetch_assoc()) {
                            echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="edit_major" class="block text-sm font-medium text-gray-700">Major Name</label>
                <input type="text" id="edit_major" name="major" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
            </div>
            <div>
                <label for="edit_description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="edit_description" name="description" rows="3" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></textarea>
            </div>
            <div>
                <label for="edit_link" class="block text-sm font-medium text-gray-700">Learn More Link (Optional)</label>
                <input type="url" id="edit_link" name="link" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
            </div>
            <div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_passing_percentage" class="block text-sm font-medium text-gray-700">Passing Percentage (%)</label>
                        <input type="number" id="edit_passing_percentage" name="passing_percentage" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label for="edit_interest_weight" class="block text-sm font-medium text-gray-700">Interest Weight (%)</label>
                        <input type="number" id="edit_interest_weight" name="interest_weight" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    </div>
                    <div>
                        <label for="edit_skills_weight" class="block text-sm font-medium text-gray-700">Skills Weight (%)</label>
                        <input type="number" id="edit_skills_weight" name="skills_weight" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    </div>
                    <div>
                        <label for="edit_strengths_weight" class="block text-sm font-medium text-gray-700">Strengths Weight (%)</label>
                        <input type="number" id="edit_strengths_weight" name="strengths_weight" min="0" max="100" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    </div>
                </div>
            </div>
            <p id="editWeightError" class="hidden text-sm text-red-600 font-semibold text-center">
                The sum of Interest, Skills, and Strengths weights must be exactly 100.
            </p>
            <div>
                <label for="edit_image" class="block text-sm font-medium text-gray-700">Update Image (Optional)</label>
                <input type="file" id="edit_image" name="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                <div id="currentImageContainer" class="mt-4">
                    <p class="text-sm text-gray-600">Current Image:</p>
                    <img id="currentImage" src="" alt="Current Image" class="mt-2 h-20 w-20 object-cover rounded-md">
                </div>
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold">Update Major</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteMajorModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-gray-800">Confirm Deletion</h3>
            <button id="closeDeleteModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <p class="text-gray-600 mb-6">Are you sure you want to permanently delete the major <strong id="deleteMajorName" class="font-bold"></strong>? This action cannot be undone.</p>
        <form id="deleteMajorForm" method="POST">
            <input type="hidden" name="delete_major" value="1">
            <input type="hidden" name="major_id" id="delete_major_id">
            <div class="flex justify-end gap-4 mt-8">
                <button type="button" id="cancelDeleteBtn" class="px-6 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 font-semibold">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-semibold">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Result/Success Modal -->
<div id="resultModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-sm text-center">
        <div class="flex justify-center mb-4">
            <!-- Success Icon -->
            <svg id="resultSuccessIcon" xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Error Icon -->
            <svg id="resultErrorIcon" xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h3 id="resultModalTitle" class="text-2xl font-bold text-gray-800 mb-2"></h3>
        <p id="resultModalMessage" class="text-gray-600 mb-8"></p>
        <button id="resultModalOkButton" class="w-full px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-semibold">OK</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addModal = document.getElementById('addMajorModal');
    const openAddModalBtn = document.getElementById('openAddMajorModal');
    const closeAddModalBtn = document.getElementById('closeAddModal');
    const editModal = document.getElementById('editMajorModal');
    const closeEditModalBtn = document.getElementById('closeEditModal');
    const deleteModal = document.getElementById('deleteMajorModal');

    const resultModal = document.getElementById('resultModal');
    const resultSuccessIcon = document.getElementById('resultSuccessIcon');
    const resultErrorIcon = document.getElementById('resultErrorIcon');
    const resultModalTitle = document.getElementById('resultModalTitle');
    const resultModalMessage = document.getElementById('resultModalMessage');
    const resultModalOkButton = document.getElementById('resultModalOkButton');

    openAddModalBtn.addEventListener('click', () => addModal.classList.add('active'));
    closeAddModalBtn.addEventListener('click', () => addModal.classList.remove('active'));

    document.querySelectorAll('.edit-major-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('edit_major_id').value = button.dataset.id;
            document.getElementById('edit_category_id').value = button.dataset.categoryId;
            document.getElementById('edit_major').value = button.dataset.major;
            document.getElementById('edit_description').value = button.dataset.description;
            document.getElementById('edit_link').value = button.dataset.link;
            document.getElementById('edit_old_image').value = button.dataset.image;
            document.getElementById('edit_passing_percentage').value = button.dataset.passingPercentage;
            document.getElementById('edit_interest_weight').value = button.dataset.interestWeight;
            document.getElementById('edit_skills_weight').value = button.dataset.skillsWeight;
            document.getElementById('edit_strengths_weight').value = button.dataset.strengthsWeight;

            const currentImageContainer = document.getElementById('currentImageContainer');
            if (button.dataset.image) {
                document.getElementById('currentImage').src = 'uploads/' + button.dataset.image;
                currentImageContainer.style.display = 'block';
            } else {
                currentImageContainer.style.display = 'none';
            }
            editModal.classList.add('active');
        });
    });

    closeEditModalBtn.addEventListener('click', () => editModal.classList.remove('active'));
    window.addEventListener('click', (e) => { 
        if (e.target === editModal) editModal.classList.remove('active'); 
        if (e.target === addModal) addModal.classList.remove('active');
        if (e.target === deleteModal) deleteModal.classList.remove('active');
        if (e.target === resultModal) resultModal.classList.remove('active');
    });

    // --- Delete Modal Logic ---
    document.querySelectorAll('.delete-major-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('delete_major_id').value = button.dataset.id;
            document.getElementById('deleteMajorName').textContent = button.dataset.major;
            deleteModal.classList.add('active');
        });
    });

    document.getElementById('closeDeleteModal').addEventListener('click', () => deleteModal.classList.remove('active'));
    document.getElementById('cancelDeleteBtn').addEventListener('click', () => deleteModal.classList.remove('active'));

    document.getElementById('deleteMajorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('major_tracks_driven.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            deleteModal.classList.remove('active');

            // Populate and show the result modal
            resultModalTitle.textContent = data.success ? 'Success' : 'Error';
            resultModalMessage.textContent = data.message;
            resultSuccessIcon.classList.toggle('hidden', !data.success);
            resultErrorIcon.classList.toggle('hidden', data.success);
            resultModal.classList.add('active');

            // Define what the OK button does
            resultModalOkButton.onclick = () => {
                resultModal.classList.remove('active');
                if (data.success) {
                    window.location.reload();
                }
            };
        })
        .catch(error => console.error('Error:', error));
    });

    document.getElementById('editMajorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        // This correctly creates a FormData object from the form,
        // ensuring all fields (including new percentage weights) are included.
        const formData = new FormData(this);
        
        fetch('major_tracks_driven.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            editModal.classList.remove('active');

            // Populate and show the result modal
            resultModalTitle.textContent = data.success ? 'Success' : 'Error';
            resultModalMessage.textContent = data.message;
            resultSuccessIcon.classList.toggle('hidden', !data.success);
            resultErrorIcon.classList.toggle('hidden', data.success);
            resultModal.classList.add('active');

            // Define what the OK button does
            resultModalOkButton.onclick = () => {
                resultModal.classList.remove('active');
                if (data.success) {
                    window.location.reload();
                }
            };
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
        });
    });


    // --- Search and Pagination Functionality ---
    const searchInput = document.getElementById('majorSearchInput');
    const tableBody = document.getElementById('majorsTableBody');
    const allRows = Array.from(tableBody.getElementsByTagName('tr'));
    const paginationContainer = document.getElementById('pagination');
    const rowsPerPage = 10;
    let currentPage = 1;

    function applyFiltersAndPagination() {
        const searchTerm = searchInput.value.toLowerCase();

        // 1. Filter rows based on search term
        const visibleRows = allRows.filter(row => {
            const majorText = row.cells[2]?.textContent.toLowerCase() || ''; // Major name
            const categoryText = row.cells[3]?.textContent.toLowerCase() || ''; // Category name
            const isVisible = majorText.includes(searchTerm) || categoryText.includes(searchTerm);
            row.style.display = 'none'; // Hide all rows initially
            return isVisible;
        });

        // 2. Paginate the filtered (visible) rows
        const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        visibleRows.slice(start, end).forEach(row => {
            row.style.display = ''; // Show only the rows for the current page
        });

        // 3. Render pagination controls
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        paginationContainer.innerHTML = "";
        if (totalPages <= 1) return;

        // Previous button
        if (currentPage > 1) {
            const prevBtn = document.createElement("button");
            prevBtn.innerHTML = "&laquo; Prev";
            prevBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
            prevBtn.onclick = () => { currentPage--; applyFiltersAndPagination(); };
            paginationContainer.appendChild(prevBtn);
        }

        // Page number buttons (simplified for brevity)
        const pageInfo = document.createElement('span');
        pageInfo.className = 'px-4 py-2 text-sm font-medium text-gray-700';
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        paginationContainer.appendChild(pageInfo);

        // Next button
        if (currentPage < totalPages) {
            const nextBtn = document.createElement("button");
            nextBtn.innerHTML = "Next &raquo;";
            nextBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
            nextBtn.onclick = () => { currentPage++; applyFiltersAndPagination(); };
            paginationContainer.appendChild(nextBtn);
        }
    }

    searchInput.addEventListener('keyup', () => {
        currentPage = 1; // Reset to the first page on a new search
        applyFiltersAndPagination();
    });

    // Initial load
    applyFiltersAndPagination();
});

// --- Client-side weight validation ---
document.addEventListener('DOMContentLoaded', () => {
    // --- ADD MODAL VALIDATION ---
    const addMajorForm = document.querySelector('#addMajorModal form');
    const addInterest = document.getElementById('interest_weight');
    const addSkills = document.getElementById('skills_weight');
    const addStrengths = document.getElementById('strengths_weight');
    const addWeightError = document.getElementById('addWeightError');

    addMajorForm.addEventListener('submit', function(e) {
        const total = parseInt(addInterest.value || 0) + parseInt(addSkills.value || 0) + parseInt(addStrengths.value || 0);
        if (total !== 100) {
            e.preventDefault(); // Stop form submission
            addWeightError.classList.remove('hidden'); // Show error message
        } else {
            addWeightError.classList.add('hidden'); // Hide error message
        }
    });

    // --- EDIT MODAL VALIDATION ---
    // Note: The edit form already has an ID: 'editMajorForm'
    const editMajorForm = document.getElementById('editMajorForm');
    const editInterest = document.getElementById('edit_interest_weight');
    const editSkills = document.getElementById('edit_skills_weight');
    const editStrengths = document.getElementById('edit_strengths_weight');
    const editWeightError = document.getElementById('editWeightError');

    // We need to re-select the form here because its submit event is already being used for AJAX.
    // We will attach our validation logic to the existing listener.
    const originalSubmitHandler = editMajorForm.onsubmit; // In case there's an inline one

    editMajorForm.addEventListener('submit', function(e) {
        // This part runs before the existing AJAX submission logic
        const total = parseInt(editInterest.value || 0) + parseInt(editSkills.value || 0) + parseInt(editStrengths.value || 0);
        
        if (total !== 100) {
            e.preventDefault(); // Stop form submission
            e.stopImmediatePropagation(); // IMPORTANT: Stop other submit listeners (like the AJAX one)
            editWeightError.classList.remove('hidden');
        } else {
            editWeightError.classList.add('hidden');
        }
    }, true); // Use capture phase to run this listener first.

});

</script>

</body>
</html>
