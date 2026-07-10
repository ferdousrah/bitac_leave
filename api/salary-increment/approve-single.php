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
if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate inputs
if (!isset($_POST['dataID']) || empty($_POST['dataID'])) {
    echo json_encode(['status' => 0, 'message' => 'ডেটা আইডি প্রয়োজন!']);
    exit;
}

if (!isset($_POST['isApproved'])) {
    echo json_encode(['status' => 0, 'message' => 'অনুমোদনের ধরন প্রয়োজন!']);
    exit;
}

$dataID = intval($_POST['dataID']);
$dataRef = intval($_POST['dataRef'] ?? 0);
$isApproved = intval($_POST['isApproved']);
$loggedUserID = intval($_SESSION['userID']);

// Start transaction
mysqli_begin_transaction($con);

try {
    // Get last signatory
    $checkLastManSigQ = mysqli_query($con, "SELECT * FROM salary_increment_approvals ORDER BY approvalSL DESC LIMIT 1");
    $checkLastManSigQRW = mysqli_fetch_assoc($checkLastManSigQ);
    $lastSig = intval($checkLastManSigQRW['employeeID'] ?? 0);

    if ($isApproved == 1 && $loggedUserID == $lastSig) {
        // Final approval - update signature and increment data
        $updateQuery = "UPDATE increment_data_for_approval SET isApproved = 1 WHERE dataID = ? AND signatory = ?";
        $updateStmt = mysqli_prepare($con, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "ii", $dataID, $loggedUserID);

        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception('অনুমোদন আপডেট করতে ব্যর্থ হয়েছে!');
        }
        mysqli_stmt_close($updateStmt);

        // Update yearly salary increment status
        $updateIncQuery = "UPDATE yearly_salary_increment SET status = 1 WHERE dataID = ?";
        $updateIncStmt = mysqli_prepare($con, $updateIncQuery);
        mysqli_stmt_bind_param($updateIncStmt, "i", $dataRef);

        if (!mysqli_stmt_execute($updateIncStmt)) {
            throw new Exception('বেতন বৃদ্ধি স্ট্যাটাস আপডেট করতে ব্যর্থ হয়েছে!');
        }
        mysqli_stmt_close($updateIncStmt);

        // Get yearly salary increment data
        $getIncQuery = "SELECT * FROM yearly_salary_increment WHERE dataID = ?";
        $getIncStmt = mysqli_prepare($con, $getIncQuery);
        mysqli_stmt_bind_param($getIncStmt, "i", $dataRef);
        mysqli_stmt_execute($getIncStmt);
        $incResult = mysqli_stmt_get_result($getIncStmt);
        $incData = mysqli_fetch_assoc($incResult);
        mysqli_stmt_close($getIncStmt);

        if ($incData) {
            // Update employee salary
            $updateEmpQuery = "UPDATE employee_list SET pay_scale = ?, basic_salary = ? WHERE id = ?";
            $updateEmpStmt = mysqli_prepare($con, $updateEmpQuery);
            mysqli_stmt_bind_param($updateEmpStmt, "sdi",
                $incData['incrementSalaryGrade'],
                $incData['incrementSalary'],
                $incData['employeeID']
            );

            if (!mysqli_stmt_execute($updateEmpStmt)) {
                throw new Exception('কর্মচারীর বেতন আপডেট করতে ব্যর্থ হয়েছে!');
            }
            mysqli_stmt_close($updateEmpStmt);
        }

    } else if ($isApproved == 1 && $loggedUserID != $lastSig) {
        // Intermediate approval - just update signature
        $updateQuery = "UPDATE increment_data_for_approval SET isApproved = 1 WHERE dataID = ? AND signatory = ?";
        $updateStmt = mysqli_prepare($con, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "ii", $dataID, $loggedUserID);

        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception('অনুমোদন আপডেট করতে ব্যর্থ হয়েছে!');
        }
        mysqli_stmt_close($updateStmt);

    } else if ($isApproved == 2) {
        // Rejection
        $updateQuery = "UPDATE increment_data_for_approval SET isApproved = 2 WHERE dataID = ? AND signatory = ?";
        $updateStmt = mysqli_prepare($con, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "ii", $dataID, $loggedUserID);

        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception('বাতিল করতে ব্যর্থ হয়েছে!');
        }
        mysqli_stmt_close($updateStmt);
    }

    // Commit transaction
    mysqli_commit($con);

    $message = ($isApproved == 1) ? 'সফলভাবে অনুমোদিত হয়েছে!' : 'সফলভাবে বাতিল করা হয়েছে!';
    echo json_encode(['status' => 1, 'message' => $message]);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
}

mysqli_close($con);
?>
