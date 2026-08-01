<?php
/**
 * Bulk approve "as-proposed" — approves multiple leave_data_for_approval rows
 * with the values already on the leave_applications row (proposed dateFrom/dateTo,
 * leaveType, leaveTypeInTwo). Mirrors approve-action.php's non-last-signatory path.
 *
 * For safety, this endpoint REJECTS rows where the current user would be the
 * last signatory — those must be approved individually so the office-notice
 * + notification side-effects fire correctly.
 *
 * Body (POST): dataIDs[] — array of leave_data_for_approval.dataID
 * Returns:    { success, approved, skipped, errors[] }
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(__DIR__ . '/bddate.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userID']) || !isset($_SESSION['employeeID'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$createdBy    = $_SESSION['userID'];
$loggedUserID = $_SESSION['employeeID'];

$userR = mysqli_query($con, "SELECT signature FROM user_list WHERE dataID = '$createdBy'");
$userRow = mysqli_fetch_assoc($userR);
$signature = mysqli_real_escape_string($con, $userRow['signature'] ?? '');

$ids = $_POST['dataIDs'] ?? [];
if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No items selected']);
    exit;
}

// DATETIME (not DATE) so multiple approvals on the same day sort chronologically
// in the comment thread. approvedDate is varchar(40); display code uses
// strtotime + date('d/m/Y', …) which parses DATETIME fine.
$today = date('Y-m-d H:i:s');
$approved = 0;
$skipped  = 0;
$errors   = [];

foreach ($ids as $dataID) {
    $dataID = (int)$dataID;
    if ($dataID <= 0) { $skipped++; continue; }

    // Verify the row belongs to this user and is still pending
    $check = mysqli_query($con, "SELECT * FROM leave_data_for_approval WHERE dataID = $dataID AND signatory = '$loggedUserID' AND isApproved = 0");
    if (!$check || mysqli_num_rows($check) === 0) {
        $skipped++;
        $errors[] = "ID $dataID: not pending or not assigned to you";
        continue;
    }
    $row = mysqli_fetch_assoc($check);
    $leaveAppID = (int)$row['leaveApplicationID'];

    // Refuse to bulk-approve if this user would be the last signatory for this application
    // (last signatory triggers office-notice + applicant notifications which need individual handling)
    $lastR = mysqli_query($con, "SELECT signatory FROM leave_data_for_approval WHERE leaveApplicationID = $leaveAppID AND isSupervisor = 0 AND isSentbyAdmin = 1 ORDER BY serial DESC LIMIT 1");
    $lastSig = mysqli_fetch_assoc($lastR)['signatory'] ?? null;
    if ($lastSig !== null && (string)$lastSig === (string)$loggedUserID) {
        $skipped++;
        $errors[] = "Application #$leaveAppID: চূড়ান্ত অনুমোদন — পৃথকভাবে অনুমোদন করুন";
        continue;
    }

    // Pull proposed values (use approved* if already set by an earlier signatory, else the original)
    $appR = mysqli_query($con, "SELECT dateFrom, dateTo, leaveType, leaveTypeInTwo, approvedDateFrom, approvedDateTo, approvedLeaveType FROM leave_applications WHERE dataID = $leaveAppID");
    $app = mysqli_fetch_assoc($appR);
    if (!$app) { $skipped++; continue; }

    $dateFrom       = !empty($app['approvedDateFrom']) ? $app['approvedDateFrom'] : $app['dateFrom'];
    $dateTo         = !empty($app['approvedDateTo'])   ? $app['approvedDateTo']   : $app['dateTo'];
    $leaveType      = !empty($app['approvedLeaveType']) ? $app['approvedLeaveType'] : $app['leaveType'];
    $leaveTypeInTwo = (int)($app['leaveTypeInTwo'] ?? 0);

    $diff = (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1;
    $approvedDays = max(0, (int)round($diff));

    // Mutations — same as non-last-signatory path of approve-action.php
    $sigUpd = mysqli_query($con, "UPDATE leave_data_for_approval SET isApproved=1, approvedDays='$approvedDays', signature='$signature', approvedDate='$today' WHERE dataID = $dataID AND signatory = '$loggedUserID'");
    if (!$sigUpd) { $errors[] = "ID $dataID: signature update failed"; $skipped++; continue; }

    mysqli_query($con, "UPDATE leave_applications SET approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', approvedLeaveType='$leaveType', leaveTypeInTwo='$leaveTypeInTwo' WHERE dataID = $leaveAppID");

    $approved++;
}

if (function_exists('audit_log')) {
    audit_log('leave_bulk_approved_asis', [
        'target_type' => 'leave_application',
        'target_id'   => 0,
        'note'        => "approved=$approved; skipped=$skipped; errors=" . count($errors),
    ]);
}

echo json_encode([
    'success'  => true,
    'approved' => $approved,
    'skipped'  => $skipped,
    'errors'   => $errors,
]);
