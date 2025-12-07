<?php
session_start();
ob_start();
include_once("lib/config.php");
  
// Include Composer autoloader for PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Check for user ID and admin role
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'){
  header("Location: ../index.php"); // Redirect non-admins to the main site
  exit();
}

// Handle adding question/answer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_question'])) {
    $major_id = $_POST['major_id'];
    $question = $_POST['question'];
    $option_a = $_POST['option_a'];
    $option_b = $_POST['option_b'];
    $option_c = $_POST['option_c'];
    $option_d = $_POST['option_d'];
    $correct_answer = $_POST['correct_answer'];

    $stmt = $conn->prepare("INSERT INTO assessments (major_id, question, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $major_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Question added successfully!";
    } else {
        $_SESSION['message'] = "Error adding question.";
    }
    header("Location: assessments_driven.php");
    exit();
}

// Handle Bulk Import
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_import'])) {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
        $fileName = $_FILES['import_file']['tmp_name'];
        
        try {
            $spreadsheet = IOFactory::load($fileName);
            $sheet = $spreadsheet->getSheetByName('Questions');

            if (!$sheet) {
                throw new Exception("Worksheet 'Questions' not found in the uploaded file.");
            }

            $highestRow = $sheet->getHighestRow();
            $importedCount = 0;

            // Start transaction
            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO assessments (major_id, question, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");

            // Loop through each row of the worksheet (starting from row 2 to skip headers)
            for ($row = 2; $row <= $highestRow; $row++) {
                $major_id_string = trim($sheet->getCell('A' . $row)->getValue());
                
                // Extract the numeric ID from the "ID - Major Name" string
                preg_match('/^(\d+)/', $major_id_string, $matches);
                $major_id = isset($matches[1]) ? (int)$matches[1] : 0;

                $question = trim($sheet->getCell('B' . $row)->getValue());
                $option_a = trim($sheet->getCell('C' . $row)->getValue());
                $option_b = trim($sheet->getCell('D' . $row)->getValue());
                $option_c = trim($sheet->getCell('E' . $row)->getValue());
                $option_d = trim($sheet->getCell('F' . $row)->getValue());
                $correct_answer = strtolower(trim($sheet->getCell('G' . $row)->getValue()));

                // Basic validation: skip empty rows
                if (empty($major_id) || empty($question)) {
                    continue;
                }

                $stmt->bind_param("issssss", $major_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer);
                $stmt->execute();
                $importedCount++;
            }

            // Commit transaction
            $conn->commit();
            $_SESSION['message'] = "Successfully imported " . $importedCount . " questions.";

        } catch (Exception $e) {
            $conn->rollback(); // Rollback on error
            $_SESSION['message'] = "Error during import: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "Error: No file uploaded or an upload error occurred.";
    }
    header("Location: assessments_driven.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id_to_delete = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM assessments WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Question deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting question.";
    }
    header("Location: assessments_driven.php");
    exit();
}

// Fetch majors/tracks
$majors = $conn->query("SELECT m.id, m.major, c.name as category_name 
                         FROM majors m
                         JOIN categories c ON m.category_id = c.id 
                         ORDER BY c.name, m.major");

// Fetch assessments with majors
$assessments = $conn->query("SELECT a.*, c.name as category_name, m.major 
                             FROM assessments a 
                             JOIN majors m ON a.major_id = m.id 
                             JOIN categories c ON m.category_id = c.id
                             ORDER BY c.name, m.major, a.id DESC");

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Assessments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
<style>
.message {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 8px;
  color: #fff;
  background-color: #4CAF50;
}
.form-container {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}
.form-container select,
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
  vertical-align: top;
  word-break: break-word;
}
.table-container th {
  background: #2a3442;
  color: white;
}
.action-btn { text-decoration: none; padding: 5px 10px; border-radius: 4px; color: white; margin-right: 5px; font-size: 14px; display: inline-block; margin-top: 5px; }
.options-list { list-style-type: none; padding-left: 0; }
.options-list li.correct {
  font-weight: bold;
  color: #4CAF50;
}
.modal {
  display: none; 
  position: fixed; 
  z-index: 1001; 
  left: 0;
  top: 0;
  width: 100%; 
  height: 100%; 
  overflow: auto; 
  background-color: rgba(0,0,0,0.6);
  padding-top: 60px;
}
.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 25px;
  border: 1px solid #888;
  width: 50%;
  border-radius: 12px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.close-btn {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
}
.close-btn:hover,
.close-btn:focus {
  color: black;
  text-decoration: none;
  cursor: pointer;
}
.preview-container {
  max-height: 300px; overflow-y: auto; margin-top: 20px; border: 1px solid #ddd; border-radius: 8px;
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
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <!-- Add Question Form -->
        <div class="form-container">
          <h3>Add Assessment Question</h3>
          <form method="POST">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3>Add Assessment Question</h3>
            <div>
              <a href="download_assessment_template.php" class="action-btn" style="background-color: #0275d8; text-decoration: none;">Download Template</a>
              <button id="openBulkImportModal" class="action-btn" style="background-color: #5cb85c; border: none; cursor: pointer;">Bulk Import</button>
            </div>
          </div>
          <form action="assessments_driven.php" method="POST">
            <select name="major_id" required>
              <option value="">Select Major/Track</option>
              <?php while ($row = $majors->fetch_assoc()) { ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['category_name']) ?> - <?= htmlspecialchars($row['major']) ?></option>
              <?php } ?>
            </select>
            <textarea name="question" placeholder="Enter question..." rows="3" required></textarea>
            <input type="text" name="option_a" placeholder="Option A" required>
            <input type="text" name="option_b" placeholder="Option B" required>
            <input type="text" name="option_c" placeholder="Option C" required>
            <input type="text" name="option_d" placeholder="Option D" required>
            <select name="correct_answer" required>
              <option value="">Select Correct Answer</option>
              <option value="a">Option A</option>
              <option value="b">Option B</option>
              <option value="c">Option C</option>
              <option value="d">Option D</option>
            </select>
            <button type="submit" name="add_question">Add Question</button>
          </form>
        </div>

        <!-- Questions List -->
        <div class="table-container">
          <h3>Assessment Questions</h3>
          <table>
            <thead>
              <tr>
                <th>Major/Track</th>
                <th>Question</th>
                <th>Options</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($assessments->num_rows > 0) {
                while ($row = $assessments->fetch_assoc()) { ?>
                  <tr>
                    <td style="width: 20%;"><b><?= htmlspecialchars($row['category_name']) ?> - <?= htmlspecialchars($row['major']) ?></b></td>
                    <td style="width: 35%;"><?= nl2br(htmlspecialchars($row['question'])) ?></td>
                    <td style="width: 30%;">
                      <ul class="options-list">
                        <li class="<?= $row['correct_answer'] == 'a' ? 'correct' : '' ?>">A: <?= htmlspecialchars($row['option_a']) ?></li>
                        <li class="<?= $row['correct_answer'] == 'b' ? 'correct' : '' ?>">B: <?= htmlspecialchars($row['option_b']) ?></li>
                        <li class="<?= $row['correct_answer'] == 'c' ? 'correct' : '' ?>">C: <?= htmlspecialchars($row['option_c']) ?></li>
                        <li class="<?= $row['correct_answer'] == 'd' ? 'correct' : '' ?>">D: <?= htmlspecialchars($row['option_d']) ?></li>
                      </ul>
                    </td>
                    <td style="width: 15%;">
                      <a href="edit_assessment.php?id=<?= $row['id'] ?>" class="action-btn" style="background-color: #4CAF50;">Edit</a>
                      <a href="assessments_driven.php?delete_id=<?= $row['id'] ?>" class="action-btn" style="background-color: #f44336;" onclick="return confirm('Are you sure you want to delete this question?');">Delete</a>
                    </td>
                  </tr>
              <?php } } else { ?>
                  <tr><td colspan="4">No questions added yet.</td></tr>
              <?php } ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- Bulk Import Modal -->
    <div id="bulkImportModal" class="modal">
      <div class="modal-content">
        <span id="closeBulkImportModal" class="close-btn">&times;</span>
        <h3>Bulk Import Assessment Questions</h3>
        <p style="margin-bottom: 15px; font-size: 14px; color: #555;">Upload an Excel file (.xlsx) using the template provided. Ensure the 'major_id' column corresponds to the IDs in the 'Majors List' sheet of the template.</p>
        <form action="assessments_driven.php" method="POST" enctype="multipart/form-data">
          <input type="file" name="import_file" id="import_file_input" accept=".xlsx" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px;">
          <button type="submit" name="bulk_import" style="background: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer;">Upload and Import</button>
        </form>
        <div id="previewContainer" class="preview-container" style="display: none;">
          <h4 style="padding: 10px; margin: 0; background: #f7f7f7; border-bottom: 1px solid #ddd;">File Preview</h4>
          <div id="previewTableContainer"></div>
        </div>
      </div>
    </div>

  </div>

<script>
// Modal handling script
document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById("bulkImportModal");
    var btn = document.getElementById("openBulkImportModal");
    var span = document.getElementById("closeBulkImportModal");

    btn.onclick = function() {
        modal.style.display = "block";
    }
    span.onclick = function() {
        modal.style.display = "none";
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
});

// SheetJS Preview Script
document.getElementById('import_file_input').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) {
        return;
    }

    var reader = new FileReader();
    reader.onload = function(e) {
        var data = new Uint8Array(e.target.result);
        var workbook = XLSX.read(data, {type: 'array'});

        var sheetName = 'Questions';
        var worksheet = workbook.Sheets[sheetName];
        if (!worksheet) {
            alert("Error: Worksheet 'Questions' not found in the file.");
            return;
        }

        var json_data = XLSX.utils.sheet_to_json(worksheet, {header: 1});
        
        if (json_data.length > 0) {
            var table = '<table style="width: 100%; border-collapse: collapse;">';
            // Header
            table += '<thead><tr style="background: #f2f2f2;">';
            json_data[0].forEach(function(cell) {
                table += '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' + cell + '</th>';
            });
            table += '</tr></thead>';
            // Body
            table += '<tbody>';
            for (var i = 1; i < json_data.length; i++) {
                table += '<tr>';
                json_data[i].forEach(function(cell) {
                    table += '<td style="padding: 8px; border: 1px solid #ddd;">' + (cell !== null ? cell : '') + '</td>';
                });
                table += '</tr>';
            }
            table += '</tbody></table>';

            document.getElementById('previewTableContainer').innerHTML = table;
            document.getElementById('previewContainer').style.display = 'block';
        }
    };
    reader.readAsArrayBuffer(file);
});
</script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
</body>
</html>
