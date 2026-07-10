<?php
// Start session first
session_start();

// Set JSON header
header('Content-Type: application/json');

// Start output buffering
ob_start();
require_once(__DIR__ . '/../../connection.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate input
if (!isset($_POST['templateData']) || empty(trim($_POST['templateData']))) {
    echo json_encode(['status' => 0, 'message' => 'টেম্পলেট ডাটা প্রদান করুন!']);
    exit;
}

if (!isset($_POST['templateType']) || empty($_POST['templateType'])) {
    echo json_encode(['status' => 0, 'message' => 'টেম্পলেট টাইপ নির্বাচন করুন!']);
    exit;
}

$templateData = mysqli_real_escape_string($con, trim($_POST['templateData']));
$templateType = intval($_POST['templateType']);

// Check for duplicate
$checkQuery = "SELECT dataID FROM leave_templates WHERE templateData = ? AND templateType = ?";
$checkStmt = mysqli_prepare($con, $checkQuery);

if (!$checkStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($checkStmt, "si", $templateData, $templateType);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই টেম্পলেট ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    mysqli_close($con);
    exit;
}

mysqli_stmt_close($checkStmt);

// Insert new template
$insertQuery = "INSERT INTO leave_templates (templateData, templateType) VALUES (?, ?)";
$insertStmt = mysqli_prepare($con, $insertQuery);

if (!$insertStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($insertStmt, "si", $templateData, $templateType);

if (mysqli_stmt_execute($insertStmt)) {
    echo json_encode(['status' => 1, 'message' => 'টেম্পলেট সফলভাবে যোগ করা হয়েছে!']);
} else {
    echo json_encode(['status' => 0, 'message' => 'টেম্পলেট যোগ করতে ব্যর্থ হয়েছে!']);
}

mysqli_stmt_close($insertStmt);
mysqli_close($con);
?>
