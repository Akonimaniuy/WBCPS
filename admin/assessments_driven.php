<?php
ob_start();
require_once("lib/config.php");
  
// Include Composer autoloader for PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// The header.php file now handles session starting and authentication.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle adding question/answer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_question'])) {
    $questions = $_POST['questions'] ?? [];
    $addedCount = 0;
    $skippedCount = 0;

    if (!empty($questions)) {
        $conn->begin_transaction();
        try {
            // Prepare statements for reuse
            $insert_stmt = $conn->prepare("INSERT INTO assessments (major_id, question, option_a, option_b, option_c, option_d, correct_answer, question_type, difficulty_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Prepare statement for checking duplicates
            $check_stmt = $conn->prepare("SELECT id FROM assessments WHERE major_id = ? AND question = ?");

            foreach ($questions as $q) {
                $major_id = $q['major_id'];
                $question = $q['question'];
                $option_a = $q['option_a'];
                $option_b = $q['option_b'];
                $option_c = $q['option_c'];
                $option_d = $q['option_d'];
                $correct_answer = $q['correct_answer'];
                $question_type = $q['question_type'];
                $difficulty_level = $q['difficulty_level'];

                // Basic validation
                if (empty($major_id) || empty($question)) continue;

                // Check for duplicates before inserting
                $check_stmt->bind_param("is", $major_id, $question);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {
                    $skippedCount++;
                    continue; // Skip this question as it's a duplicate
                }

                $insert_stmt->bind_param("isssssssi", $major_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $question_type, $difficulty_level);
                $insert_stmt->execute();
                $addedCount++;
            }
            $check_stmt->close();
            $conn->commit();
            $message = "Successfully added " . $addedCount . " questions.";
            if ($skippedCount > 0) $message .= " Skipped " . $skippedCount . " duplicate questions.";
            $_SESSION['message'] = $message;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error adding questions: " . $e->getMessage();
        }
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
            $skippedCount = 0;

            // Start transaction
            $conn->begin_transaction();

            // Prepare statements for reuse
            $insert_stmt = $conn->prepare("INSERT INTO assessments (major_id, question, option_a, option_b, option_c, option_d, correct_answer, question_type, difficulty_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Prepare statement for checking duplicates
            $check_stmt = $conn->prepare("SELECT id FROM assessments WHERE major_id = ? AND question = ?");

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
                $question_type = trim($sheet->getCell('H' . $row)->getValue());
                $difficulty_level = (int)trim($sheet->getCell('I' . $row)->getValue());

                // Basic validation: skip empty rows
                if (empty($major_id) || empty($question)) {
                    continue;
                }

                // Check for duplicates before inserting
                $check_stmt->bind_param("is", $major_id, $question);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {
                    $skippedCount++;
                    continue; // Skip this row as it's a duplicate
                }

                $insert_stmt->bind_param("isssssssi", $major_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $question_type, $difficulty_level);
                $insert_stmt->execute();
                $importedCount++;
            }
            $check_stmt->close();

            // Commit transaction
            $conn->commit();
            $message = "Successfully imported " . $importedCount . " questions.";
            if ($skippedCount > 0) $message .= " Skipped " . $skippedCount . " duplicate questions.";
            $_SESSION['message'] = $message;

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

// Handle Update Question (from AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_question'])) {
    header('Content-Type: application/json');
    
    $assessment_id = $_POST['assessment_id'];
    $major_id = $_POST['major_id'];
    $question = $_POST['question'];
    $option_a = $_POST['option_a'];
    $option_b = $_POST['option_b'];
    $option_c = $_POST['option_c'];
    $option_d = $_POST['option_d'];
    $correct_answer = $_POST['correct_answer'];
    $question_type = $_POST['question_type'];
    $difficulty_level = $_POST['difficulty_level'];

    if (empty($assessment_id) || empty($major_id) || empty($question)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE assessments SET major_id = ?, question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, question_type = ?, difficulty_level = ? WHERE id = ?");
    $stmt->bind_param("isssssssii", $major_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $question_type, $difficulty_level, $assessment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Question updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating question: ' . $stmt->error]);
    }
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

// --- Data Fetching for New Layout ---

// 1. Fetch all categories
$categories = [];
$category_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row;
}

// 2. Fetch all majors, grouped by category_id
$majors_by_category = [];
$majors_result = $conn->query("SELECT id, category_id, major, description FROM majors ORDER BY major ASC");
while ($row = $majors_result->fetch_assoc()) {
    $majors_by_category[$row['category_id']][] = $row;
}

// 3. Fetch all assessment questions, grouped by major_id
$questions_by_major = [];
$assessments_result = $conn->query("SELECT * FROM assessments ORDER BY id ASC");
while ($row = $assessments_result->fetch_assoc()) {
    $questions_by_major[$row['major_id']][] = $row;
}

// 4. Fetch majors for the "Add Question" modal dropdown
$majors_for_modal = [];
$majors_dropdown_result = $conn->query("SELECT m.id, m.major, c.name as category_name 
                                        FROM majors m
                                        JOIN categories c ON m.category_id = c.id 
                                        ORDER BY c.name, m.major");
while ($row = $majors_dropdown_result->fetch_assoc()) {
    $majors_for_modal[] = $row;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assessments - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
        .options-list { list-style-type: none; padding-left: 0; }
        .options-list li.correct { font-weight: bold; color: #16a34a; /* green-600 */ }
    </style>
</head>
<body class="bg-gray-100 flex">

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="w-full md:pl-64">
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 pt-20 md:pt-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Manage Assessments</h1>

        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                <p class="font-bold">Success</p>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8 flex flex-wrap gap-4 items-center">
            <button id="openAddQuestionModal" class="bg-gray-800 text-white px-5 py-2 rounded-lg hover:bg-gray-700 font-semibold text-sm">Add Question</button>
            <a href="download_assessment_template.php" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 font-semibold text-sm">Download Template</a>
            <button id="openBulkImportModal" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 font-semibold text-sm">Bulk Import</button>
        </div>

        <!-- Add Question Modal -->
        <div id="addQuestionModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-start justify-center z-50 pt-10">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl">
                <div class="flex justify-between items-center p-6 border-b">
                    <h3 class="text-2xl font-bold text-gray-800">Add Assessment Questions</h3>
                    <button id="closeAddQuestionModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
                </div>
                <form action="assessments_driven.php" method="POST">
                    <div id="questionsContainer" class="p-6 space-y-6 max-h-[65vh] overflow-y-auto">
                        <!-- Question blocks will be inserted here by JavaScript -->
                    </div>
                    <div class="flex justify-between items-center p-6 border-t bg-gray-50 rounded-b-lg">
                        <button type="button" id="addAnotherQuestion" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 font-semibold text-sm">Add Another Question</button>
                        <button type="submit" name="add_question" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-bold text-sm">Save All Questions</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Template for a single question block (hidden) -->
        <div id="questionTemplate" style="display: none;">
            <div class="question-block bg-gray-50 p-5 rounded-lg border border-gray-200 relative space-y-4">
                <button type="button" class="remove-question-btn absolute top-2 right-3 text-gray-500 hover:text-red-600 text-2xl font-bold">&times;</button>
                <select name="questions[__INDEX__][major_id]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <option value="">Select Major</option>
                    <?php foreach ($majors_for_modal as $major) { ?>
                        <option value="<?= $major['id'] ?>"><?= htmlspecialchars($major['category_name']) ?> - <?= htmlspecialchars($major['major']) ?></option>
                    <?php } ?>
                </select>
                <textarea name="questions[__INDEX__][question]" placeholder="Enter question..." rows="2" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></textarea>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" name="questions[__INDEX__][option_a]" placeholder="Option A" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <input type="text" name="questions[__INDEX__][option_b]" placeholder="Option B" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <input type="text" name="questions[__INDEX__][option_c]" placeholder="Option C" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <input type="text" name="questions[__INDEX__][option_d]" placeholder="Option D" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <select name="questions[__INDEX__][correct_answer]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"><option value="">Correct Answer</option><option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option></select>
                    <select name="questions[__INDEX__][question_type]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"><option value="">Question Type</option><option value="Interest">Interest</option><option value="Skills">Skills</option><option value="Strengths">Strengths</option></select>
                    <select name="questions[__INDEX__][difficulty_level]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"><option value="">Difficulty</option><option value="1">1 (Easy)</option><option value="2">2 (Medium)</option><option value="3">3 (Hard)</option></select>
                </div>
            </div>
        </div>

        <!-- New Layout: Category Filter and Majors Grid -->
        <div class="bg-white p-8 rounded-lg shadow-lg">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="text-2xl font-bold text-gray-800">Browse Questions by Major</h2>
                <select id="categoryFilter" class="w-full md:w-72 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="majorsGridContainer">
                <?php foreach ($categories as $category): ?>
                    <div class="majors-list mb-8" data-category-id="<?php echo $category['id']; ?>">
                        <h3 class="text-xl font-bold text-gray-700 mb-4 pb-2 border-b-2 border-gray-200"><?php echo htmlspecialchars($category['name']); ?></h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                            <?php if (isset($majors_by_category[$category['id']])): ?>
                                <?php foreach ($majors_by_category[$category['id']] as $major): ?>
                                    <div class="major-card bg-gray-50 p-5 rounded-lg shadow-sm border border-gray-200 cursor-pointer transition-all duration-200 hover:shadow-md hover:border-yellow-400 hover:-translate-y-1 text-center" data-major-id="<?php echo $major['id']; ?>" data-major-name="<?php echo htmlspecialchars($major['major']); ?>">
                                        <h4 class="text-md font-bold text-gray-800 truncate"><?php echo htmlspecialchars($major['major']); ?></h4>
                                        <p class="text-sm text-gray-500 mt-1">
                                            <?php echo isset($questions_by_major[$major['id']]) ? count($questions_by_major[$major['id']]) : 0; ?> Questions
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 col-span-full">No majors in this category.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

    <!-- Bulk Import Modal -->
    <div id="bulkImportModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-2xl font-bold text-gray-800">Bulk Import Questions</h3>
                <button id="closeBulkImportModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <p class="text-sm text-gray-600 mb-6">Upload an Excel file (.xlsx) using the template. Ensure the 'major_id' column corresponds to the IDs in the 'Majors List' sheet of the template.</p>
            <form action="assessments_driven.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="file" name="import_file" id="import_file_input" accept=".xlsx" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
                <div class="flex gap-4">
                    <button type="button" id="previewBtn" class="w-1/2 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 font-semibold text-sm">Preview</button>
                    <button type="submit" name="bulk_import" class="w-1/2 bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 font-semibold text-sm">Upload and Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Questions Modal -->
    <div id="viewQuestionsModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-start justify-center z-50 pt-10">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 id="viewQuestionsModalTitle" class="text-2xl font-bold text-gray-800">Questions for Major</h3>
                <button id="closeViewQuestionsModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div id="viewQuestionsModalBody" class="p-6 max-h-[75vh] overflow-y-auto"></div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-start justify-center z-50 pt-10">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-2xl font-bold text-gray-800">File Preview</h3>
                <button id="closePreviewModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div id="previewTableContainer" class="p-6 max-h-[75vh] overflow-y-auto"></div>
        </div>
    </div>

    <!-- Edit Assessment Modal -->
    <div id="editAssessmentModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-start justify-center z-50 pt-10">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-2xl font-bold text-gray-800">Edit Assessment Question</h3>
                <button id="closeEditAssessmentModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <form id="editAssessmentForm" method="POST">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <input type="hidden" name="update_question" value="1">
                    <input type="hidden" id="edit_assessment_id" name="assessment_id">

                    <div>
                        <label for="edit_major_id" class="block text-sm font-medium text-gray-700">Major/Track</label>
                        <select id="edit_major_id" name="major_id" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                            <option value="">Select Major</option>
                            <?php foreach ($majors_for_modal as $major) { ?>
                                <option value="<?= $major['id'] ?>"><?= htmlspecialchars($major['category_name']) ?> - <?= htmlspecialchars($major['major']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit_question" class="block text-sm font-medium text-gray-700">Question</label>
                        <textarea id="edit_question" name="question" rows="3" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label for="edit_option_a" class="block text-sm font-medium text-gray-700">Option A</label><input type="text" id="edit_option_a" name="option_a" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></div>
                        <div><label for="edit_option_b" class="block text-sm font-medium text-gray-700">Option B</label><input type="text" id="edit_option_b" name="option_b" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></div>
                        <div><label for="edit_option_c" class="block text-sm font-medium text-gray-700">Option C</label><input type="text" id="edit_option_c" name="option_c" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></div>
                        <div><label for="edit_option_d" class="block text-sm font-medium text-gray-700">Option D</label><input type="text" id="edit_option_d" name="option_d" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label for="edit_correct_answer" class="block text-sm font-medium text-gray-700">Correct Answer</label><select id="edit_correct_answer" name="correct_answer" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"><option value="">Select</option><option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option></select></div>
                        <div><label for="edit_question_type" class="block text-sm font-medium text-gray-700">Question Type</label><select id="edit_question_type" name="question_type" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"><option value="">Select</option><option value="Interest">Interest</option><option value="Skills">Skills</option><option value="Strengths">Strengths</option></select></div>
                        <div><label for="edit_difficulty_level" class="block text-sm font-medium text-gray-700">Difficulty</label><select id="edit_difficulty_level" name="difficulty_level" required class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"><option value="">Select</option><option value="1">1 (Easy)</option><option value="2">2 (Medium)</option><option value="3">3 (Hard)</option></select></div>
                    </div>
                </div>
                <div class="flex justify-end p-6 border-t bg-gray-50 rounded-b-lg">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-bold text-sm">Update Question</button>
                </div>
            </form>
        </div>
    </div>

<script>
// Pass PHP data to JavaScript
const allQuestionsByMajor = <?php echo json_encode($questions_by_major); ?>;

</script>

<script>
// Modal handling script
document.addEventListener("DOMContentLoaded", function() {
    // Generic modal handler
    function setupModal(modalId, openBtnId, closeBtnId) {
        const modal = document.getElementById(modalId);
        const openBtn = document.getElementById(openBtnId);
        const closeBtn = document.getElementById(closeBtnId);

        if (openBtn) openBtn.onclick = () => modal.classList.add('active');
        if (closeBtn) closeBtn.onclick = () => modal.classList.remove('active');
    }

    setupModal("addQuestionModal", "openAddQuestionModal", "closeAddQuestionModal"); 
    setupModal("bulkImportModal", "openBulkImportModal", "closeBulkImportModal");
    setupModal("viewQuestionsModal", null, "closeViewQuestionsModal"); // View modal is opened programmatically
    setupModal("previewModal", "previewBtn", "closePreviewModal");
    setupModal("editAssessmentModal", null, "closeEditAssessmentModal"); // Edit modal is also opened programmatically

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }

    // Preview button is inside the bulk import modal, so it needs special handling
    const previewBtn = document.getElementById('previewBtn');
    previewBtn.addEventListener('click', function() {
        const fileInput = document.getElementById('import_file_input');
        if (fileInput.files.length === 0) {
            alert('Please select a file to preview first.');
            return;
        }
        // The 'change' event handler below will trigger and populate the preview
        // Now we just need to show the preview modal
        const previewModal = document.getElementById('previewModal');
        previewModal.classList.add('active');
    });

    // Dynamic "Add Question" form logic
    const questionsContainer = document.getElementById('questionsContainer');
    const addAnotherBtn = document.getElementById('addAnotherQuestion');
    const questionTemplate = document.getElementById('questionTemplate').innerHTML;
    let questionIndex = 0;

    function addQuestionBlock() {
        const newBlockHTML = questionTemplate.replace(/__INDEX__/g, questionIndex);
        const newBlock = document.createElement('div');
        newBlock.innerHTML = newBlockHTML;
        questionsContainer.appendChild(newBlock.firstElementChild);
        questionIndex++;
    }

    // Add the first block when the modal is opened
    document.getElementById('openAddQuestionModal').addEventListener('click', () => {
        if (questionsContainer.children.length === 0) {
            addQuestionBlock();
        }
    });

    addAnotherBtn.addEventListener('click', addQuestionBlock);

    // Event delegation for remove buttons
    questionsContainer.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-question-btn')) {
            e.target.closest('.question-block').remove();
        }
    });

    // New layout logic
    const categoryFilter = document.getElementById('categoryFilter');
    const majorsLists = document.querySelectorAll('.majors-list');
    const majorsGridContainer = document.getElementById('majorsGridContainer');

    categoryFilter.addEventListener('change', function() {
        const selectedCategoryId = this.value;
        majorsLists.forEach(list => {
            if (selectedCategoryId === 'all' || list.dataset.categoryId === selectedCategoryId) {
                list.style.display = 'block';
            } else {
                list.style.display = 'none';
            }
        });
    });

    // Major card click logic
    majorsGridContainer.addEventListener('click', function(e) {
        const majorCard = e.target.closest('.major-card');
        if (!majorCard) return;

        const majorId = majorCard.dataset.majorId;
        const majorName = majorCard.dataset.majorName;
        const questions = allQuestionsByMajor[majorId] || [];

        const modal = document.getElementById('viewQuestionsModal');
        const modalTitle = document.getElementById('viewQuestionsModalTitle');
        const modalBody = document.getElementById('viewQuestionsModalBody');

        modalTitle.textContent = `Questions for ${majorName}`;
        
        if (questions.length === 0) {
            modalBody.innerHTML = '<p>No questions have been added for this major yet.</p>';
        } else {
            let tableHTML = `
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-800"><tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Question</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Options</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Difficulty</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                    </tr></thead>
                    <tbody class="bg-white divide-y divide-gray-200">`;
            questions.forEach(q => {
                tableHTML += `
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-700 align-top">${q.question}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 align-top"><ul class="options-list space-y-1">
                            <li class="${q.correct_answer == 'a' ? 'correct' : ''}">A: ${q.option_a}</li><li class="${q.correct_answer == 'b' ? 'correct' : ''}">B: ${q.option_b}</li><li class="${q.correct_answer == 'c' ? 'correct' : ''}">C: ${q.option_c}</li><li class="${q.correct_answer == 'd' ? 'correct' : ''}">D: ${q.option_d}</li>
                        </ul></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 align-top">${q.question_type}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 align-top">${q.difficulty_level}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2 align-top">
                            <button class="edit-assessment-btn inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-xs"
                                data-id="${q.id}"
                                data-major-id="${q.major_id}"
                                data-question="${q.question}"
                                data-option-a="${q.option_a}"
                                data-option-b="${q.option_b}"
                                data-option-c="${q.option_c}"
                                data-option-d="${q.option_d}"
                                data-correct-answer="${q.correct_answer}"
                                data-question-type="${q.question_type}"
                                data-difficulty-level="${q.difficulty_level}"
                            >Edit</button>
                            <a href="assessments_driven.php?delete_id=${q.id}" class="inline-block bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-xs" onclick="return confirm('Are you sure?');">Delete</a>
                        </td>
                    </tr>`;
            });
            tableHTML += `</tbody></table></div>`;
            modalBody.innerHTML = tableHTML;
        }
        modal.classList.add('active');
    });

    // Edit Assessment Modal Logic
    const viewQuestionsModalBody = document.getElementById('viewQuestionsModalBody');
    const editAssessmentModal = document.getElementById('editAssessmentModal');

    viewQuestionsModalBody.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('edit-assessment-btn')) {
            const button = e.target;
            const dataset = button.dataset;

            // Populate the edit modal form
            document.getElementById('edit_assessment_id').value = dataset.id;
            document.getElementById('edit_major_id').value = dataset.majorId;
            document.getElementById('edit_question').value = dataset.question;
            document.getElementById('edit_option_a').value = dataset.optionA;
            document.getElementById('edit_option_b').value = dataset.optionB;
            document.getElementById('edit_option_c').value = dataset.optionC;
            document.getElementById('edit_option_d').value = dataset.optionD;
            document.getElementById('edit_correct_answer').value = dataset.correctAnswer;
            document.getElementById('edit_question_type').value = dataset.questionType;
            document.getElementById('edit_difficulty_level').value = dataset.difficultyLevel;

            // Show the edit modal
            editAssessmentModal.classList.add('active');
        }
    });

    // Handle edit form submission
    document.getElementById('editAssessmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('assessments_driven.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) window.location.reload();
        })
        .catch(error => console.error('Error:', error));
    });
});

// SheetJS Preview Script
document.getElementById('import_file_input').addEventListener('change', function(e) {
    const previewTableContainer = document.getElementById('previewTableContainer');
    previewTableContainer.innerHTML = ''; // Clear previous preview

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
            var table = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">';
            // Header
            table += '<thead class="bg-gray-100">';
            json_data[0].forEach(function(cell) {
                table += '<th class="px-4 py-2 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">' + cell + '</th>';
            });
            table += '</tr></thead>';
            // Body
            table += '<tbody class="bg-white divide-y divide-gray-200">';
            for (var i = 1; i < json_data.length; i++) {
                table += '<tr>';
                json_data[i].forEach(function(cell) {
                    table += '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">' + (cell !== null ? cell : '') + '</td>';
                });
                table += '</tr>';
            }
            table += '</tbody></table></div>';

            previewTableContainer.innerHTML = table;
        }
    };
    reader.readAsArrayBuffer(file);
});
</script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
</body>

</html>
