<?php
session_start();
include_once("lib/config.php");
  
// Check for user ID and admin role
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'){
  header("Location: ../index.php"); // Redirect non-admins to the main site
  exit();
}

// --- CRUD Operations ---

// Add Category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_categories.php");
    exit();
}

// Delete Category
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_categories.php");
    exit();
}

// Fetch all categories for display
$categories = [];
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Manage Categories</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
<style>
  .form-container, .table-container {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
  }
  .form-container h3, .table-container h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #2a3442;
  }
  .form-container input, 
  .form-container textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
  }
  .form-container button {
    background: #2a3442;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
  }
  .table-container table {
    width: 100%;
    border-collapse: collapse;
  }
  .table-container th, .table-container td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
  }
  .table-container th { background: #2a3442; color: white; }
  .action-btn { text-decoration: none; padding: 5px 10px; border-radius: 4px; color: white; margin-right: 5px; }
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
        
        <!-- Add Category Form -->
        <div class="form-container">
          <h3>Add New Category</h3>
          <form action="manage_categories.php" method="POST">
            <input type="text" name="name" placeholder="Category Name" required>
            <textarea name="description" placeholder="Category Description" rows="3"></textarea>
            <button type="submit" name="add_category">Add Category</button>
          </form>
        </div>

        <!-- Categories List -->
        <div class="table-container">
          <h3>Existing Categories</h3>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($categories)): ?>
                <tr><td colspan="4">No categories found.</td></tr>
              <?php else: ?>
                <?php foreach ($categories as $category): ?>
                  <tr>
                    <td><?php echo $category['id']; ?></td>
                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                    <td>
                      <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="action-btn" style="background-color: #4CAF50;">Edit</a>
                      <a href="manage_categories.php?delete_id=<?php echo $category['id']; ?>" class="action-btn" style="background-color: #f44336;" onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</body>
</html>