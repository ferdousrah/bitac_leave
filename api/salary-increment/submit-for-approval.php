<?php
// Start session first
session_start();

// Set JSON header
header('Content-Type: application/json');

// Start output buffering
ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../bddate.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate input
if (!isset($_POST['listedemp']) || empty($_POST['listedemp']) || !is_array($_POST['listedemp'])) {
    echo json_encode(['status' => 0, 'message' => 'অনুগ্রহ করে অন্তত একজন কর্মচারী নির্বাচন করুন!']);
    exit;
}

$createdBy = $_SESSION['userID'];
$submitDate = todayDate();
$incrementYear = date('Y');

// Start transaction
mysqli_begin_transaction($con);

try {
    $successCount = 0;

    foreach ($_POST['listedemp'] as $empID) {
        $empID = intval($empID);

        // Get increment data for this employee
        $getDataQuery = "SELECT * FROM yearly_salary_increment WHERE incrementYear = ? AND employeeID = ?";
        $getDataStmt = mysqli_prepare($con, $getDataQuery);
        mysqli_stmt_bind_param($getDataStmt, "si", $incrementYear, $empID);
        mysqli_stmt_execute($getDataStmt);
        $getDataResult = mysqli_stmt_get_result($getDataStmt);
        $getDataDetailsQRW = mysqli_fetch_assoc($getDataResult);
        mysqli_stmt_close($getDataStmt);

        if (!$getDataDetailsQRW) {
            continue; // Skip if no increment data found
        }

        // Check for duplicate and delete if exists
        $checkDuplicateQuery = "SELECT dataID FROM increment_data_for_approval WHERE incrementYear = ? AND employeeID = ?";
        $checkDuplicateStmt = mysqli_prepare($con, $checkDuplicateQuery);
        mysqli_stmt_bind_param($checkDuplicateStmt, "si", $incrementYear, $getDataDetailsQRW['employeeID']);
        mysqli_stmt_execute($checkDuplicateStmt);
        $checkDuplicateResult = mysqli_stmt_get_result($checkDuplicateStmt);
        mysqli_stmt_close($checkDuplicateStmt);

        if (mysqli_num_rows($checkDuplicateResult) > 0) {
            // Delete existing records
            $deleteQuery = "DELETE FROM increment_data_for_approval WHERE incrementYear = ? AND employeeID = ?";
            $deleteStmt = mysqli_prepare($con, $deleteQuery);
            mysqli_stmt_bind_param($deleteStmt, "si", $incrementYear, $getDataDetailsQRW['employeeID']);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);
        }

        // Get signatories
        $getSignatoryQ = mysqli_query($con, "SELECT * FROM `salary_increment_approvals` ORDER BY approvalSL ASC");

        $prevSignatory = 0;

        while ($sigRow = mysqli_fetch_array($getSignatoryQ)) {
            // Insert approval record
            $insertQuery = "INSERT INTO increment_data_for_approval (dataRef, incrementYear, employeeID, signatory, prevSignatory, isApproved, serial) VALUES (?, ?, ?, ?, ?, 0, ?)";
            $insertStmt = mysqli_prepare($con, $insertQuery);

            if (!$insertStmt) {
                throw new Exception('ডাটাবেস ত্রুটি!');
            }

            mysqli_stmt_bind_param($insertStmt, "isiiii",
                $getDataDetailsQRW['dataID'],
                $incrementYear,
                $getDataDetailsQRW['employeeID'],
                $sigRow['employeeID'],
                $prevSignatory,
                $sigRow['approvalSL']
            );

            if (!mysqli_stmt_execute($insertStmt)) {
                throw new Exception('রেকর্ড সংরক্ষণ করতে ব্যর্থ হয়েছে!');
            }

            mysqli_stmt_close($insertStmt);

            $prevSignatory = $sigRow['employeeID'];
        }

        $successCount++;
    }

    // Commit transaction
    mysqli_commit($con);

    if ($successCount > 0) {
        if (function_exists('audit_log')) {
            audit_log('increment_submitted_for_approval', [
                'target_type' => 'increment_data_for_approval',
                'target_id'   => 0,
                'note'        => "count=$successCount",
            ]);
        }
        echo json_encode([
            'status' => 1,
            'message' => 'ডেটা সফলভাবে সংরক্ষিত হয়েছে! মোট ' . $successCount . ' জন কর্মচারীর জন্য।'
        ]);
    } else {
        echo json_encode(['status' => 0, 'message' => 'কোন রেকর্ড সংরক্ষণ করা যায়নি!']);
    }

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
}

mysqli_close($con);
?>
