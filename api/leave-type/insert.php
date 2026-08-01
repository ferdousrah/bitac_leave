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
if (!isset($_POST['leaveTitle']) || empty(trim($_POST['leaveTitle']))) {
    echo json_encode(['status' => 0, 'message' => 'ছুটির নাম প্রদান করুন!']);
    exit;
}

$leaveTitle = mysqli_real_escape_string($con, trim($_POST['leaveTitle']));

// Check for duplicate
$checkQuery = "SELECT leaveID FROM leave_types WHERE leaveTitle = ?";
$checkStmt = mysqli_prepare($con, $checkQuery);

if (!$checkStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($checkStmt, "s", $leaveTitle);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই ছুটির প্রকার ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    mysqli_close($con);
    exit;
}

mysqli_stmt_close($checkStmt);

// Insert new leave type
$insertQuery = "INSERT INTO leave_types (leaveTitle) VALUES (?)";
$insertStmt = mysqli_prepare($con, $insertQuery);

if (!$insertStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($insertStmt, "s", $leaveTitle);

if (mysqli_stmt_execute($insertStmt)) {
    if (function_exists('audit_log')) {
        audit_log('leave_type_created', [
            'target_type' => 'leave_types',
            'target_id'   => mysqli_insert_id($con),
            'note'        => 'title=' . mb_substr($leaveTitle, 0, 100),
        ]);
    }
    echo json_encode(['status' => 1, 'message' => 'ছুটির প্রকার সফলভাবে যোগ করা হয়েছে!']);
} else {
    echo json_encode(['status' => 0, 'message' => 'ছুটির প্রকার যোগ করতে ব্যর্থ হয়েছে!']);
}

mysqli_stmt_close($insertStmt);
mysqli_close($con);
?>
