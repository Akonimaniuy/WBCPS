<?php
session_start();
ob_start();
include_once("lib/config.php");

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$major_id = $_GET['id'] ?? null;
if (!$major_id) {
    header("Location: major_tracks_driven.php");
    exit();
}

// Handle form submission for updating
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_major'])) {
    $category_id = $_POST['category_id'];
    $major_name = $_POST['major'];
    $description = $_POST['description'];
    $old_image = $_POST['old_image'];
    $imageName = $old_image;

    // Handle new image upload
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "uploads/";
        $imageName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $imageName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            // Delete old image if a new one is uploaded
            if (!empty($old_image) && file_exists($targetDir . $old_image)) {
                unlink($targetDir . $old_image);
            }
        } else {
            $imageName = $old_image; // Revert to old image if upload fails
        }
    }

    $stmt = $conn->prepare("UPDATE majors SET category_id = ?, major = ?, description = ?, image = ? WHERE id = ?");
    $stmt->bind_param("isssi", $category_id, $major_name, $description, $imageName, $major_id);
    $stmt->execute();

    $_SESSION['message'] = "Major updated successfully!";
    header("Location: major_tracks_driven.php");
    exit();
}

// Fetch the major to edit
$stmt = $conn->prepare("SELECT * FROM majors WHERE id = ?");
$stmt->bind_param("i", $major_id);
$stmt->execute();
$result = $stmt->get_result();
$major = $result->fetch_assoc();

if (!$major) {
    header("Location: major_tracks_driven.php");
    exit();
}

// Fetch categories for the dropdown
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Edit Major</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
<style>
  .form-container { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
  .form-container h3 { margin-top: 0; margin-bottom: 15px; color: #2a3442; }
  .form-container input, .form-container textarea, .form-container select { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
  .form-container button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; }
  .current-image { max-width: 100px; border-radius: 6px; margin-top: 10px; display: block; }
</style>
</head>
<body>
  <!-- Sidebar -->
  <?php include "php-include/navigation.php"; ?>

  <!-- Main -->
  <div class="main">
    <?php include "php-include/topbar.php"; ?>

    <div class="main-content">
      <div class="content" style="text-align: left;">
        <div class="form-container">
          <h3>Edit Major</h3>
          <form action="edit_major.php?id=<?php echo $major_id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($major['image']); ?>">
            
            <label>Category</label>
            <select name="category_id" required>
              <option value="">Select a Category</option>
              <?php while ($category = $categories_result->fetch_assoc()): ?>
                <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $major['category_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($category['name']); ?>
                </option>
              <?php endwhile; ?>
            </select>

            <label>Major Name</label>
            <input type="text" name="major" placeholder="Major Name" value="<?php echo htmlspecialchars($major['major']); ?>" required>

            <label>Description</label>
            <textarea name="description" placeholder="Description" rows="4" required><?php echo htmlspecialchars($major['description']); ?></textarea>

            <label>Update Image (optional)</label>
            <input type="file" name="image" accept="image/*">
            <?php if (!empty($major['image'])): ?>
              <p>Current Image:</p>
              <img src="uploads/<?php echo htmlspecialchars($major['image']); ?>" alt="Current Image" class="current-image">
            <?php endif; ?>

            <button type="submit" name="update_major" style="margin-top: 20px;">Update Major</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>