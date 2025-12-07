<?php
session_start();
include_once("lib/config.php");

// Check for user ID and admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$category_id = $_GET['id'] ?? null;
if (!$category_id) {
    header("Location: manage_categories.php");
    exit();
}

// Handle form submission for updating
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_category'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $description, $category_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_categories.php");
    exit();
}

// Fetch the category to edit
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    header("Location: manage_categories.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Edit Category</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
<style>
  .form-container {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  }
  .form-container h3 { margin-top: 0; margin-bottom: 15px; color: #2a3442; }
  .form-container input, .form-container textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
  }
  .form-container button {
    background: #4CAF50;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
  }
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
          <h3>Edit Category</h3>
          <form action="edit_category.php?id=<?php echo $category_id; ?>" method="POST">
            <input type="text" name="name" placeholder="Category Name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
            <textarea name="description" placeholder="Category Description" rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
            <button type="submit" name="update_category">Update Category</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>