<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: text/plain');

if (!isset($_SESSION['username'])) {
    echo 0; exit;
}

$applicationID = (int)($_POST['applicationID'] ?? 0);
if ($applicationID <= 0) {
    echo 0; exit;
}

// ── Security: only the applicant may delete, and only if no signatory approved ──
$uStmt = mysqli_prepare($con, "SELECT employee_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
mysqli_stmt_close($uStmt);
$userEmpId = (int)($userRow['employee_id'] ?? 0);
if ($userEmpId <= 0) { echo 0; exit; }

// Verify ownership + state
$aStmt = mysqli_prepare($con,
    "SELECT applicantID, status, attachment FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($aStmt, 'i', $applicationID);
mysqli_stmt_execute($aStmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));
mysqli_stmt_close($aStmt);

if (!$app) { echo 0; exit; }
if ((int)$app['applicantID'] !== $userEmpId) { echo 0; exit; }       // not your application
if ((int)$app['status'] !== 0) { echo 0; exit; }                      // already approved/cancelled

// Check no signatory has approved yet
$cStmt = mysqli_prepare($con,
    "SELECT COUNT(*) c FROM leave_data_for_approval WHERE leaveApplicationID = ? AND isApproved = 1");
mysqli_stmt_bind_param($cStmt, 'i', $applicationID);
mysqli_stmt_execute($cStmt);
$cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['c'] ?? 0);
mysqli_stmt_close($cStmt);
if ($cnt > 0) { echo 0; exit; }

// ── Cascade delete in transaction ──
mysqli_begin_transaction($con);
try {
    $tables = [
        "DELETE FROM leave_segment_history     WHERE applicationID = ?",
        "DELETE FROM leave_application_segments WHERE applicationID = ?",
        "DELETE FROM leave_data_for_approval   WHERE leaveApplicationID = ?",
        "DELETE FROM office_notice_record      WHERE leaveApplicationID = ?",
        "DELETE FROM leave_applications        WHERE dataID = ?",
    ];
    foreach ($tables as $sql) {
        $s = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($s, 'i', $applicationID);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
    }
    mysqli_commit($con);

    // Delete attachment file
    if (!empty($app['attachment'])) {
        $path = __DIR__ . '/../../uploads/' . $app['attachment'];
        if (file_exists($path)) @unlink($path);
    }

    echo 1;
} catch (Exception $e) {
    mysqli_rollback($con);
    echo 0;
}
