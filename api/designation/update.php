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

if (!isset($_POST['job_title_name']) || empty(trim($_POST['job_title_name']))) {
    echo json_encode(['status' => 0, 'message' => 'পদবীর নাম প্রদান করুন!']);
    exit;
}

$dataID = intval($_POST['dataID']);
$job_title_name = mysqli_real_escape_string($con, trim($_POST['job_title_name']));
$display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;

// Check if 'deleted' column exists in job_title table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM job_title LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

// Check for duplicate (excluding current record)
if ($hasDeletedColumn) {
    $checkQuery = "SELECT id FROM job_title WHERE job_title_name = ? AND id != ? AND deleted = 0";
} else {
    $checkQuery = "SELECT id FROM job_title WHERE job_title_name = ? AND id != ?";
}

$checkStmt = mysqli_prepare($con, $checkQuery);

if (!$checkStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($checkStmt, "si", $job_title_name, $dataID);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই পদবী ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    mysqli_close($con);
    exit;
}

mysqli_stmt_close($checkStmt);

// Update designation
if ($hasDeletedColumn) {
    $updateQuery = "UPDATE job_title SET job_title_name = ?, display_order = ? WHERE id = ? AND deleted = 0";
} else {
    $updateQuery = "UPDATE job_title SET job_title_name = ?, display_order = ? WHERE id = ?";
}

$updateStmt = mysqli_prepare($con, $updateQuery);

if (!$updateStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "sii", $job_title_name, $display_order, $dataID);

if (mysqli_stmt_execute($updateStmt)) {
    if (mysqli_stmt_affected_rows($updateStmt) > 0) {
        if (function_exists('audit_log')) {
            audit_log('designation_updated', [
                'target_type' => 'job_title',
                'target_id'   => $dataID,
                'note'        => 'name=' . mb_substr($job_title_name, 0, 100),
            ]);
        }
        echo json_encode(['status' => 1, 'message' => 'পদবী সফলভাবে আপডেট করা হয়েছে!']);
    } else {
        // No rows affected could mean the data is the same or record doesn't exist
        echo json_encode(['status' => 1, 'message' => 'কোনো পরিবর্তন হয়নি!']);
    }
} else {
    echo json_encode(['status' => 0, 'message' => 'পদবী আপডেট করতে ব্যর্থ হয়েছে!']);
}

mysqli_stmt_close($updateStmt);
mysqli_close($con);
?>
