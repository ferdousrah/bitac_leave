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
if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate inputs
if (!isset($_POST['listedemp']) || !is_array($_POST['listedemp']) || count($_POST['listedemp']) == 0) {
    echo json_encode(['status' => 0, 'message' => 'অনুগ্রহ করে অন্তত একটি আইটেম নির্বাচন করুন!']);
    exit;
}

$todayDate = todayDate();
$loggedUserID = intval($_SESSION['userID']);

// Get last signatory
$checkLastManSigQ = mysqli_query($con, "SELECT * FROM salary_increment_approvals ORDER BY approvalSL DESC LIMIT 1");
$checkLastManSigQRW = mysqli_fetch_assoc($checkLastManSigQ);
$lastSig = intval($checkLastManSigQRW['employeeID'] ?? 0);

$successCount = 0;
$errorCount = 0;

// Start transaction
mysqli_begin_transaction($con);

try {
    foreach ($_POST['listedemp'] as $dataID) {
        $dataID = intval($dataID);

        // Get data details
        $getDataQuery = "SELECT * FROM increment_data_for_approval WHERE dataID = ?";
        $getDataStmt = mysqli_prepare($con, $getDataQuery);
        mysqli_stmt_bind_param($getDataStmt, "i", $dataID);
        mysqli_stmt_execute($getDataStmt);
        $dataResult = mysqli_stmt_get_result($getDataStmt);
        $dataRow = mysqli_fetch_assoc($dataResult);
        mysqli_stmt_close($getDataStmt);

        if (!$dataRow) {
            $errorCount++;
            continue;
        }

        $dataRef = intval($dataRow['dataRef']);
        $isApproved = 1; // Always approve in bulk

        if ($loggedUserID == $lastSig) {
            // Final approval
            $updateQuery = "UPDATE increment_data_for_approval SET isApproved = 1 WHERE dataID = ? AND signatory = ?";
            $updateStmt = mysqli_prepare($con, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "ii", $dataID, $loggedUserID);

            if (mysqli_stmt_execute($updateStmt)) {
                mysqli_stmt_close($updateStmt);

                // Update yearly salary increment status and approved date
                $updateIncQuery = "UPDATE yearly_salary_increment SET status = 1, approvedDate = ? WHERE dataID = ?";
                $updateIncStmt = mysqli_prepare($con, $updateIncQuery);
                mysqli_stmt_bind_param($updateIncStmt, "si", $todayDate, $dataRef);

                if (mysqli_stmt_execute($updateIncStmt)) {
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

                        if (mysqli_stmt_execute($updateEmpStmt)) {
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                        mysqli_stmt_close($updateEmpStmt);
                    } else {
                        $successCount++;
                    }
                } else {
                    mysqli_stmt_close($updateIncStmt);
                    $errorCount++;
                }
            } else {
                mysqli_stmt_close($updateStmt);
                $errorCount++;
            }

        } else {
            // Intermediate approval
            $updateQuery = "UPDATE increment_data_for_approval SET isApproved = 1 WHERE dataID = ? AND signatory = ?";
            $updateStmt = mysqli_prepare($con, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "ii", $dataID, $loggedUserID);

            if (mysqli_stmt_execute($updateStmt)) {
                $successCount++;
            } else {
                $errorCount++;
            }
            mysqli_stmt_close($updateStmt);
        }
    }

    // Commit transaction
    mysqli_commit($con);

    if ($successCount > 0) {
        if (function_exists('audit_log')) {
            audit_log('increment_bulk_approved', [
                'target_type' => 'yearly_salary_increment',
                'target_id'   => 0,
                'note'        => "approved=$successCount; errors=$errorCount",
            ]);
        }
        echo json_encode([
            'status' => 1,
            'message' => 'মোট ' . $successCount . ' টি সফলভাবে অনুমোদিত হয়েছে!' . ($errorCount > 0 ? ' (' . $errorCount . ' টি ব্যর্থ হয়েছে)' : '')
        ]);
    } else {
        echo json_encode(['status' => 0, 'message' => 'কোন আইটেম অনুমোদন করা যায়নি!']);
    }

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
}

mysqli_close($con);
?>
