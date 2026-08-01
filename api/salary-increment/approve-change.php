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

// Validate inputs
if (!isset($_POST['dataID']) || empty($_POST['dataID'])) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ অনুরোধ!']);
    exit;
}

if (!isset($_POST['isApproved']) || !in_array($_POST['isApproved'], ['1', '2'])) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ কার্যক্রম!']);
    exit;
}

$dataID = intval($_POST['dataID']);
$isApproved = intval($_POST['isApproved']);
$approvedBy = $_SESSION['userID'];

// Get data details using prepared statement
$getDataQuery = "SELECT * FROM increment_data_update_permission WHERE dataID = ?";
$getDataStmt = mysqli_prepare($con, $getDataQuery);

if (!$getDataStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($getDataStmt, "i", $dataID);
mysqli_stmt_execute($getDataStmt);
$getDataResult = mysqli_stmt_get_result($getDataStmt);
$getDataDetailsQRW = mysqli_fetch_assoc($getDataResult);
mysqli_stmt_close($getDataStmt);

if (!$getDataDetailsQRW) {
    echo json_encode(['status' => 0, 'message' => 'তথ্য খুঁজে পাওয়া যায়নি!']);
    exit;
}

// Start transaction
mysqli_begin_transaction($con);

try {
    if ($isApproved == 1) {
        // Update salary increment data
        $updateSalaryQuery = "UPDATE yearly_salary_increment SET incrementAmount = ?, incrementSalary = ? WHERE incrementYear = ? AND employeeID = ?";
        $updateSalaryStmt = mysqli_prepare($con, $updateSalaryQuery);

        if (!$updateSalaryStmt) {
            throw new Exception('ডাটাবেস ত্রুটি!');
        }

        mysqli_stmt_bind_param($updateSalaryStmt, "ddsi",
            $getDataDetailsQRW['salary_increment_rate_edited'],
            $getDataDetailsQRW['salary_increment_basic_edited'],
            $getDataDetailsQRW['incrementYear'],
            $getDataDetailsQRW['employeeID']
        );

        if (!mysqli_stmt_execute($updateSalaryStmt)) {
            throw new Exception('বেতন বৃদ্ধি তথ্য আপডেট করতে ব্যর্থ হয়েছে!');
        }
        mysqli_stmt_close($updateSalaryStmt);
    }

    // Update permission record
    $updatePermissionQuery = "UPDATE increment_data_update_permission SET isApproved = ?, approvedBy = ? WHERE dataID = ?";
    $updatePermissionStmt = mysqli_prepare($con, $updatePermissionQuery);

    if (!$updatePermissionStmt) {
        throw new Exception('ডাটাবেস ত্রুটি!');
    }

    mysqli_stmt_bind_param($updatePermissionStmt, "iii", $isApproved, $approvedBy, $dataID);

    if (!mysqli_stmt_execute($updatePermissionStmt)) {
        throw new Exception('অনুমতি রেকর্ড আপডেট করতে ব্যর্থ হয়েছে!');
    }
    mysqli_stmt_close($updatePermissionStmt);

    // Commit transaction
    mysqli_commit($con);

    if (function_exists('audit_log')) {
        audit_log($isApproved == 1 ? 'increment_change_approved' : 'increment_change_rejected', [
            'target_type' => 'increment_data_update_permission',
            'target_id'   => (int)$dataID,
        ]);
    }

    $message = $isApproved == 1 ? 'পরিবর্তন সফলভাবে অনুমোদন করা হয়েছে!' : 'পরিবর্তন সফলভাবে বাতিল করা হয়েছে!';
    echo json_encode(['status' => 1, 'message' => $message]);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
}

mysqli_close($con);
?>
