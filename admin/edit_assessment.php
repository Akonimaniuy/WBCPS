<?php
session_start();
ob_start();
include_once("lib/config.php");

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$assessment_id = $_GET['id'] ?? null;
if (!$assessment_id) {
    header("Location: assessments_driven.php");
    exit();
}

// Handle form submission for updating
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_question'])) {
    $major_id = $_POST['major_id'];
    $question = $_POST['question'];
    $option_a = $_POST['option_a'];
    $option_b = $_POST['option_b'];
    $option_c = $_POST['option_c'];
    $option_d = $_POST['option_d'];
    $correct_answer = $_POST['correct_answer'];

    $stmt = $conn->prepare("UPDATE assessments SET major_id = ?, question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ? WHERE id = ?");
    $stmt->bind_param("issssssi", $major_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $assessment_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Question updated successfully!";
    } else {
        $_SESSION['message'] = "Error updating question.";
    }
    header("Location: assessments_driven.php");
    exit();
}

// Fetch the assessment to edit
$stmt = $conn->prepare("SELECT * FROM assessments WHERE id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();
$assessment = $result->fetch_assoc();

if (!$assessment) {
    $_SESSION['message'] = "Question not found.";
    header("Location: assessments_driven.php");
    exit();
}

// Fetch majors/tracks for the dropdown
$majors_result = $conn->query("SELECT m.id, m.major, c.name as category_name 
                               FROM majors m
                               JOIN categories c ON m.category_id = c.id 
                               ORDER BY c.name, m.major");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Edit Assessment</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
<style>
  .form-container { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
  .form-container h3 { margin-top: 0; margin-bottom: 15px; color: #2a3442; }
  .form-container input, .form-container textarea, .form-container select { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
  .form-container button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; }
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
          <h3>Edit Assessment Question</h3>
          <form action="edit_assessment.php?id=<?php echo $assessment_id; ?>" method="POST">
            
            <label>Major/Track</label>
            <select name="major_id" required>
              <option value="">Select a Major/Track</option>
              <?php while ($major = $majors_result->fetch_assoc()): ?>
                <option value="<?php echo $major['id']; ?>" <?php echo ($major['id'] == $assessment['major_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($major['category_name']) . ' - ' . htmlspecialchars($major['major']); ?>
                </option>
              <?php endwhile; ?>
            </select>

            <label>Question</label>
            <textarea name="question" placeholder="Question" rows="4" required><?php echo htmlspecialchars($assessment['question']); ?></textarea>

            <label>Option A</label>
            <input type="text" name="option_a" placeholder="Option A" value="<?php echo htmlspecialchars($assessment['option_a']); ?>" required>

            <label>Option B</label>
            <input type="text" name="option_b" placeholder="Option B" value="<?php echo htmlspecialchars($assessment['option_b']); ?>" required>

            <label>Option C</label>
            <input type="text" name="option_c" placeholder="Option C" value="<?php echo htmlspecialchars($assessment['option_c']); ?>" required>

            <label>Option D</label>
            <input type="text" name="option_d" placeholder="Option D" value="<?php echo htmlspecialchars($assessment['option_d']); ?>" required>

            <label>Correct Answer</label>
            <select name="correct_answer" required>
              <option value="">Select Correct Answer</option>
              <option value="a" <?php echo ($assessment['correct_answer'] == 'a') ? 'selected' : ''; ?>>Option A</option>
              <option value="b" <?php echo ($assessment['correct_answer'] == 'b') ? 'selected' : ''; ?>>Option B</option>
              <option value="c" <?php echo ($assessment['correct_answer'] == 'c') ? 'selected' : ''; ?>>Option C</option>
              <option value="d" <?php echo ($assessment['correct_answer'] == 'd') ? 'selected' : ''; ?>>Option D</option>
            </select>

            <button type="submit" name="update_question" style="margin-top: 20px;">Update Question</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>