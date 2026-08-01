<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/../../config/connection.php');
require_once(__DIR__ . '/../../bddate.php');

// Helper: handle a single file upload, return unique filename or empty string
function handleUpload($fieldName, $uploadDir) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $file = $_FILES[$fieldName];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpeg', 'jpg', 'png', 'pdf'];

    if (!in_array($ext, $allowed)) {
        return '';
    }
    if ($file['size'] > 2097152) { // 2MB
        return '';
    }

    $newName = time() . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        return $newName;
    }
    return '';
}

// Validate session
if (empty($_SESSION['userID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

// Ensure undeductibleLeaveRemaining column exists (runs once, fast check)
$colCheck = $con->query("SHOW COLUMNS FROM previous_leave_deduction LIKE 'undeductibleLeaveRemaining'");
if ($colCheck && $colCheck->num_rows === 0) {
    $con->query("ALTER TABLE previous_leave_deduction ADD COLUMN undeductibleLeaveRemaining VARCHAR(50) DEFAULT NULL AFTER undeductibleLeaveFile");
}

// Get form data - used (ভোগকৃত)
$employeeID = (int)($_POST['employeeID'] ?? 0);
$avgSalary = trim($_POST['avgSalary'] ?? '');
$halfAvgSalary = trim($_POST['halfAvgSalary'] ?? '');
$casual = trim($_POST['casual'] ?? '');
$leaveWithoutPay = trim($_POST['leaveWithoutPay'] ?? '');
$undeductibleLeave = trim($_POST['undeductibleLeave'] ?? '');

// Get form data - remaining (অবশিষ্ট) → stored in note columns
$avgSalaryRemaining = trim($_POST['avgSalaryRemaining'] ?? '');
$halfAvgSalaryRemaining = trim($_POST['halfAvgSalaryRemaining'] ?? '');
$casualRemaining = trim($_POST['casualRemaining'] ?? '');
$leaveWithoutPayRemaining = trim($_POST['leaveWithoutPayRemaining'] ?? '');
$undeductibleLeaveRemaining = trim($_POST['undeductibleLeaveRemaining'] ?? '');

$submitDate = ShowBangladeshTime();

if ($employeeID === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid employee.']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/';

// Handle file uploads
$fileFields = ['avgSalaryFile', 'halfAvgSalaryFile', 'casualFile', 'leaveWithoutPayFile', 'undeductibleLeaveFile'];
$uploadedFiles = [];
foreach ($fileFields as $field) {
    $uploadedFiles[$field] = handleUpload($field, $uploadDir);
}

// Check if record exists
$checkStmt = $con->prepare("SELECT dataID, avgSalaryFile, halfAvgSalaryFile, casualFile, leaveWithoutPayFile, undeductibleLeaveFile FROM previous_leave_deduction WHERE employeeID = ?");
$checkStmt->bind_param("i", $employeeID);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

// For UPDATE: keep existing files if no new file uploaded
if ($existing) {
    foreach ($fileFields as $field) {
        if ($uploadedFiles[$field] === '') {
            $uploadedFiles[$field] = $existing[$field] ?? '';
        }
    }
}

if ($existing) {
    // UPDATE
    $stmt = $con->prepare("UPDATE previous_leave_deduction SET
        avgSalary = ?, halfAvgSalary = ?, casual = ?, leaveWithoutPay = ?, undeductibleLeave = ?,
        avgSalaryNote = ?, halfAvgSalaryNote = ?, casualNote = ?, leaveWithoutPayNote = ?, undeductibleLeaveRemaining = ?,
        avgSalaryFile = ?, halfAvgSalaryFile = ?, casualFile = ?, leaveWithoutPayFile = ?, undeductibleLeaveFile = ?,
        isApproved = 0, lastUpdate = ?
        WHERE employeeID = ?");
    $stmt->bind_param("ssssssssssssssssi",
        $avgSalary, $halfAvgSalary, $casual, $leaveWithoutPay, $undeductibleLeave,
        $avgSalaryRemaining, $halfAvgSalaryRemaining, $casualRemaining, $leaveWithoutPayRemaining, $undeductibleLeaveRemaining,
        $uploadedFiles['avgSalaryFile'], $uploadedFiles['halfAvgSalaryFile'],
        $uploadedFiles['casualFile'], $uploadedFiles['leaveWithoutPayFile'],
        $uploadedFiles['undeductibleLeaveFile'],
        $submitDate, $employeeID
    );
} else {
    // INSERT
    $stmt = $con->prepare("INSERT INTO previous_leave_deduction
        (employeeID, avgSalary, halfAvgSalary, casual, leaveWithoutPay, undeductibleLeave,
         avgSalaryNote, halfAvgSalaryNote, casualNote, leaveWithoutPayNote, undeductibleLeaveRemaining,
         avgSalaryFile, halfAvgSalaryFile, casualFile, leaveWithoutPayFile, undeductibleLeaveFile, lastUpdate)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssssssssssss",
        $employeeID,
        $avgSalary, $halfAvgSalary, $casual, $leaveWithoutPay, $undeductibleLeave,
        $avgSalaryRemaining, $halfAvgSalaryRemaining, $casualRemaining, $leaveWithoutPayRemaining, $undeductibleLeaveRemaining,
        $uploadedFiles['avgSalaryFile'], $uploadedFiles['halfAvgSalaryFile'],
        $uploadedFiles['casualFile'], $uploadedFiles['leaveWithoutPayFile'],
        $uploadedFiles['undeductibleLeaveFile'],
        $submitDate
    );
}

if ($stmt->execute()) {
    if (function_exists('audit_log')) {
        audit_log('previous_leave_recorded', [
            'target_type' => 'previous_leave_deduction',
            'target_id'   => (int)$employeeID,
            'note'        => "avg=$avgSalary; half=$halfAvgSalary; casual=$casual; unpaid=$leaveWithoutPay",
        ]);
    }
    echo json_encode(['status' => 'success', 'message' => 'তথ্য সফলভাবে সংরক্ষণ করা হয়েছে।']);
} else {
    error_log("Previous leave save error: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'তথ্য সংরক্ষণে ত্রুটি হয়েছে।']);
}

$stmt->close();
mysqli_close($con);
?>
