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
if (!isset($_POST['dataID']) || empty($_POST['dataID'])) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ অনুরোধ!']);
    exit;
}

if (!isset($_POST['leaveTitle']) || empty(trim($_POST['leaveTitle']))) {
    echo json_encode(['status' => 0, 'message' => 'ছুটির নাম প্রদান করুন!']);
    exit;
}

$dataID = intval($_POST['dataID']);
$leaveTitle = mysqli_real_escape_string($con, trim($_POST['leaveTitle']));

// Check for duplicate (excluding current record)
$checkQuery = "SELECT leaveID FROM leave_types WHERE leaveTitle = ? AND leaveID != ?";
$checkStmt = mysqli_prepare($con, $checkQuery);

if (!$checkStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($checkStmt, "si", $leaveTitle, $dataID);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই ছুটির প্রকার ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    mysqli_close($con);
    exit;
}

mysqli_stmt_close($checkStmt);

// Update leave type
$updateQuery = "UPDATE leave_types SET leaveTitle = ? WHERE leaveID = ?";
$updateStmt = mysqli_prepare($con, $updateQuery);

if (!$updateStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "si", $leaveTitle, $dataID);

if (mysqli_stmt_execute($updateStmt)) {
    if (mysqli_stmt_affected_rows($updateStmt) > 0) {
        echo json_encode(['status' => 1, 'message' => 'ছুটির প্রকার সফলভাবে আপডেট করা হয়েছে!']);
    } else {
        echo json_encode(['status' => 1, 'message' => 'কোনো পরিবর্তন হয়নি!']);
    }
} else {
    echo json_encode(['status' => 0, 'message' => 'ছুটির প্রকার আপডেট করতে ব্যর্থ হয়েছে!']);
}

mysqli_stmt_close($updateStmt);
mysqli_close($con);
?>
