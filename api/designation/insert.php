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
if (!isset($_POST['job_title_name']) || empty(trim($_POST['job_title_name']))) {
    echo json_encode(['status' => 0, 'message' => 'পদবীর নাম প্রদান করুন!']);
    exit;
}

$job_title_name = mysqli_real_escape_string($con, trim($_POST['job_title_name']));
$display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;

// Check if 'deleted' column exists in job_title table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM job_title LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

// Check for duplicate (only among non-deleted records if column exists)
if ($hasDeletedColumn) {
    $checkQuery = "SELECT id FROM job_title WHERE job_title_name = ? AND deleted = 0";
} else {
    $checkQuery = "SELECT id FROM job_title WHERE job_title_name = ?";
}

$checkStmt = mysqli_prepare($con, $checkQuery);

if (!$checkStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($checkStmt, "s", $job_title_name);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই পদবী ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    mysqli_close($con);
    exit;
}

mysqli_stmt_close($checkStmt);

// Insert new designation
if ($hasDeletedColumn) {
    $insertQuery = "INSERT INTO job_title (job_title_name, display_order, deleted) VALUES (?, ?, 0)";
} else {
    $insertQuery = "INSERT INTO job_title (job_title_name, display_order) VALUES (?, ?)";
}

$insertStmt = mysqli_prepare($con, $insertQuery);

if (!$insertStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($insertStmt, "si", $job_title_name, $display_order);

if (mysqli_stmt_execute($insertStmt)) {
    if (function_exists('audit_log')) {
        audit_log('designation_created', [
            'target_type' => 'job_title',
            'target_id'   => mysqli_insert_id($con),
            'note'        => 'name=' . mb_substr($job_title_name, 0, 100),
        ]);
    }
    echo json_encode(['status' => 1, 'message' => 'পদবী সফলভাবে যোগ করা হয়েছে!']);
} else {
    echo json_encode(['status' => 0, 'message' => 'পদবী যোগ করতে ব্যর্থ হয়েছে!']);
}

mysqli_stmt_close($insertStmt);
mysqli_close($con);
?>
