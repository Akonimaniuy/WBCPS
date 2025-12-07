<?php
session_start();
ob_start(); // Start output buffering after session start
include_once("lib/config.php"); // Include your database connection
  
// Check for user ID and admin role
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'){
  header("Location: ../index.php"); // Redirect non-admins to the main site
  exit();
}

$message = '';
$message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_major'])) {
    $major = $_POST['major'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];

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

    $stmt = $conn->prepare("INSERT INTO majors (category_id, major, description, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $category_id, $major, $description, $imageName);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Major added successfully!";
        header("Location: major_tracks_driven.php");
        exit();
    } else {
        $message = "Error: Could not add the major. " . $stmt->error;
    }
}

// Handle Delete Major
if (isset($_GET['delete_id'])) {
    $id_to_delete = $_GET['delete_id'];

    // First, get the image filename to delete it from the server
    $img_stmt = $conn->prepare("SELECT image FROM majors WHERE id = ?");
    $img_stmt->bind_param("i", $id_to_delete);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result()->fetch_assoc();
    if ($img_result && !empty($img_result['image']) && file_exists("uploads/" . $img_result['image'])) {
        unlink("uploads/" . $img_result['image']);
    }

    $stmt = $conn->prepare("DELETE FROM majors WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    $stmt->execute();
    $_SESSION['message'] = "Major deleted successfully!";
    header("Location: major_tracks_driven.php");
    exit();
}

// Fetch categories for the dropdown
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
<style>
.message {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 8px;
  color: #fff;
  font-weight: bold;
  background-color: #4CAF50; /* Green for success */
}
.form-container {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}
.form-container h3 {
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
}
.form-container button {
  background: #2a3442;
  color: white;
  padding: 10px 15px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}
.action-btn { text-decoration: none; padding: 5px 10px; border-radius: 4px; color: white; margin-right: 5px; font-size: 14px; }
.table-container {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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
.table-container th {
  background: #2a3442;
  color: white;
}
.table-container img {
  width: 80px;
  border-radius: 6px;
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
      <div class="content">

        <?php if (!empty($message)): ?>
          <div class="message">
            <?php echo $message; ?>
          </div>
        <?php endif; ?>

        <!-- Add Major/Track Form -->
        <div class="form-container">
          <h3>Add Major/Track</h3>
          <form method="POST" enctype="multipart/form-data">
            <select name="category_id" required style="width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 6px;">
              <option value="">Select a Category</option>
              <?php
                if ($categories_result && $categories_result->num_rows > 0) {
                    while ($category = $categories_result->fetch_assoc()) {
                        echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                    }
                }
              ?>
            </select>
            <input type="text" name="major" placeholder="Major" required>
            <textarea name="description" placeholder="Description" rows="3" required></textarea>
            <input type="file" name="image" accept="image/*">
            <button type="submit" name="add_major">Add Major/Track</button>
          </form>
        </div>

        <!-- Display Majors/Tracks -->
        <div class="table-container">
          <h3>Majors & Tracks List</h3>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Major</th>
                <th>Description</th>
                <th>Image</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sql = "SELECT m.id, c.name as category_name, m.major, m.description, m.image 
                      FROM majors m
                      JOIN categories c ON m.category_id = c.id
                      ORDER BY m.id DESC";
              $result = $conn->query($sql);
              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      echo "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['category_name']) . "</td>
                        <td>" . htmlspecialchars($row['major']) . "</td>
                        <td>" . htmlspecialchars($row['description']) . "</td>
                        <td>";
                        if (!empty($row['image'])) {
                            echo "<img src='uploads/" . htmlspecialchars($row['image']) . "' alt='Major Image'>";
                        } else {
                            echo "No image";
                        }
                      echo "</td>
                        <td>
                          <a href='edit_major.php?id={$row['id']}' class='action-btn' style='background-color: #4CAF50;'>Edit</a>
                          <a href='major_tracks_driven.php?delete_id={$row['id']}' class='action-btn' style='background-color: #f44336;' onclick=\"return confirm('Are you sure you want to delete this major? This action cannot be undone.');\">Delete</a>
                        </td>
                      </tr>";
                  }
              } else {
                  echo "<tr><td colspan='6'>No majors/tracks found.</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</body>
</html>
