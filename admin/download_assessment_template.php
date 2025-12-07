<?php
session_start();
include_once("lib/config.php");

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Include the Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

$spreadsheet = new Spreadsheet();

// --- Create the "Questions" worksheet ---
$questionsSheet = $spreadsheet->getActiveSheet();
$questionsSheet->setTitle('Questions');

// Set headers
$headers = ['major_id', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'question_type', 'difficulty_level'];
$questionsSheet->fromArray($headers, NULL, 'A1');

// Style headers
$headerStyle = $questionsSheet->getStyle('A1:I1');
$headerStyle->getFont()->setBold(true);
$headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');

// Add example row
$exampleData = [
    1, 
    'What is the primary purpose of this technology?', 
    'Option A answer', 
    'Option B answer', 
    'Option C answer', 
    'Option D answer', 
    'a',
    'Skills',
    1
];
$questionsSheet->fromArray($exampleData, NULL, 'A2');

// Add instructions/notes
$questionsSheet->setCellValue('K1', 'Instructions');
$questionsSheet->getStyle('K1')->getFont()->setBold(true);
$questionsSheet->setCellValue('K2', "1. For 'major_id', please select an option from the dropdown list.");
$questionsSheet->setCellValue('K3', "2. 'correct_answer' must be 'a', 'b', 'c', or 'd'.");
$questionsSheet->setCellValue('K4', "3. 'question_type' must be 'Interest', 'Skills', or 'Strengths'.");
$questionsSheet->setCellValue('K5', "4. 'difficulty_level' should be a number (e.g., 1, 2, 3).");
$questionsSheet->mergeCells('K2:N2');
$questionsSheet->mergeCells('K3:N3');
$questionsSheet->mergeCells('K4:N4');
$questionsSheet->mergeCells('K5:N5');

// Auto-size columns
foreach (range('A', 'I') as $col) {
    $questionsSheet->getColumnDimension($col)->setAutoSize(true);
}
$questionsSheet->getColumnDimension('K')->setAutoSize(true);


// --- Create the "Majors List" worksheet ---
$majorsSheet = $spreadsheet->createSheet();
$majorsSheet->setTitle('Majors List');

// Fetch majors data
$majors_result = $conn->query("SELECT m.id, c.name as category_name, m.major 
                               FROM majors m
                               JOIN categories c ON m.category_id = c.id 
                               ORDER BY c.name, m.major");

// Set headers for majors list
$majorsHeaders = ['ID', 'Category', 'Major', 'ID - Major'];
$majorsSheet->fromArray($majorsHeaders, NULL, 'A1');
$majorsSheet->getStyle('A1:D1')->getFont()->setBold(true);
$majorsSheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');


// Populate majors data
$rowIndex = 2;
if ($majors_result && $majors_result->num_rows > 0) {
    while ($major = $majors_result->fetch_assoc()) {
        $id = $major['id'];
        $category = $major['category_name'];
        $majorName = $major['major'];
        $majorsSheet->setCellValue('A' . $rowIndex, $id);
        $majorsSheet->setCellValue('B' . $rowIndex, $category);
        $majorsSheet->setCellValue('C' . $rowIndex, $majorName);
        $majorsSheet->setCellValue('D' . $rowIndex, "{$id} - {$majorName}"); // Combined field
        $rowIndex++;
    }
}

// Auto-size columns for majors list
foreach (range('A', 'D') as $col) {
    $majorsSheet->getColumnDimension($col)->setAutoSize(true);
}

// --- Add Data Validation to the "Questions" sheet ---
if ($rowIndex > 2) { // Only add validation if there are majors
    $lastMajorRow = $rowIndex - 1;
    // Apply data validation to the major_id column for 1000 rows
    for ($i = 2; $i <= 1002; $i++) {
        $validation = $questionsSheet->getCell('A' . $i)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid Major ID');
        $validation->setError('Please select a valid Major from the list.');
        // Point the validation to the new combined column 'D'
        $validation->setFormula1("'Majors List'!\$D\$2:\$D\${$lastMajorRow}");

        // Data validation for question_type
        $validationType = $questionsSheet->getCell('H' . $i)->getDataValidation();
        $validationType->setType(DataValidation::TYPE_LIST);
        $validationType->setErrorStyle(DataValidation::STYLE_STOP);
        $validationType->setAllowBlank(false);
        $validationType->setShowDropDown(true);
        $validationType->setErrorTitle('Invalid Type');
        $validationType->setError("Please select 'Interest', 'Skills', or 'Strengths'.");
        $validationType->setFormula1('"Interest,Skills,Strengths"');

    }
    // Hide the helper column to keep the sheet clean
    $majorsSheet->getColumnDimension('D')->setVisible(false);
}

// Set the active sheet back to the first one
$spreadsheet->setActiveSheetIndex(0);

// --- Output the file to the browser ---
$writer = new Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="assessment_questions_template.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit();