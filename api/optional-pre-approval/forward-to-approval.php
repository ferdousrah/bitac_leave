<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(__DIR__ . '/../../includes/optional_pre_approval_helper.php');
ob_end_clean();

if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    echo json_encode(['status'=>0,'message'=>'অননুমোদিত']);
    exit;
}

$pid           = (int)($_POST['id']            ?? 0);
$approvedDays  = isset($_POST['approved_days']) ? (float)$_POST['approved_days'] : null;
$adminNote     = trim($_POST['admin_note']     ?? '');
$actorUserID   = (int)$_SESSION['userID'];

if ($pid <= 0) { echo json_encode(['status'=>0,'message'=>'অবৈধ আইডি']); exit; }

// Resolve actor identity
$actorStmt = mysqli_prepare($con,
    "SELECT dataID, employee_id, isCenterAdmin, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$un = $_SESSION['username'];
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);

$actorEmpID = (int)($actor['employee_id'] ?? 0);
$myGroupID  = (int)($actor['user_group_id'] ?? 0);
if ($actorEmpID <= 0) {
    echo json_encode(['status'=>0,'message'=>'আপনার account resolve করা যায়নি']);
    exit;
}

// Load pre-approval + supervisor row
$opaQ = mysqli_prepare($con,
    "SELECT opa.*, e.organization_id AS applicant_org
     FROM optional_leave_pre_approval opa
     INNER JOIN employee_list e ON opa.employee_id = e.id
     WHERE opa.id = ? LIMIT 1");
mysqli_stmt_bind_param($opaQ, 'i', $pid);
mysqli_stmt_execute($opaQ);
$opa = mysqli_fetch_assoc(mysqli_stmt_get_result($opaQ));
mysqli_stmt_close($opaQ);
if (!$opa) { echo json_encode(['status'=>0,'message'=>'আবেদন পাওয়া যায়নি']); exit; }
if ((int)$opa['status'] !== 0) {
    echo json_encode(['status'=>0,'message'=>'এই আবেদন আর পেন্ডিং নয়']);
    exit;
}

// Authorization: actor must be center admin of the applicant's center
$actorEmpQ = mysqli_prepare($con,
    "SELECT organization_id FROM employee_list WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($actorEmpQ, 'i', $actorEmpID);
mysqli_stmt_execute($actorEmpQ);
$actorEmp = mysqli_fetch_assoc(mysqli_stmt_get_result($actorEmpQ)) ?: [];
mysqli_stmt_close($actorEmpQ);
$actorOrg = (int)($actorEmp['organization_id'] ?? 0);

$isCenterAdmin = (int)($actor['isCenterAdmin'] ?? 0);

// Broaden: any user whose group has been granted the forward-queue submodule can act.
if (!$isCenterAdmin && $myGroupID > 0) {
    $_permStmt = mysqli_prepare($con,
        "SELECT 1 FROM group_access_permission gap
         INNER JOIN submodules sm ON gap.submodule_id = sm.dataID
         WHERE gap.user_group_id = ? AND sm.slug = 'optional-pre-approval-forward-queue'
         LIMIT 1");
    mysqli_stmt_bind_param($_permStmt, 'i', $myGroupID);
    mysqli_stmt_execute($_permStmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($_permStmt))) {
        $isCenterAdmin = 1;
    }
    mysqli_stmt_close($_permStmt);
}

if (!$isCenterAdmin || $actorOrg !== (int)$opa['applicant_org']) {
    echo json_encode(['status'=>0,'message'=>'আপনার এই আবেদন forward করার অধিকার নেই']);
    exit;
}

// Supervisor must have already recommended
$supChk = mysqli_prepare($con,
    "SELECT isApproved FROM optional_leave_pre_approval_signatory
     WHERE preApprovalID = ? AND isSupervisor = 1 LIMIT 1");
mysqli_stmt_bind_param($supChk, 'i', $pid);
mysqli_stmt_execute($supChk);
$sup = mysqli_fetch_assoc(mysqli_stmt_get_result($supChk));
mysqli_stmt_close($supChk);
if (!$sup) { echo json_encode(['status'=>0,'message'=>'সুপারভাইজার row পাওয়া যায়নি']); exit; }
if ((int)$sup['isApproved'] !== 1) {
    echo json_encode(['status'=>0,'message'=>'সুপারভাইজার এখনো সুপারিশ করেননি']);
    exit;
}

// Check if there's a real chain to forward to
$chainChk = mysqli_prepare($con,
    "SELECT COUNT(*) AS c FROM optional_leave_pre_approval_signatory
     WHERE preApprovalID = ? AND isSupervisor = 0");
mysqli_stmt_bind_param($chainChk, 'i', $pid);
mysqli_stmt_execute($chainChk);
$chainCnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($chainChk))['c'] ?? 0);
mysqli_stmt_close($chainChk);
if ($chainCnt === 0) {
    echo json_encode(['status'=>0,'message'=>'অনুমোদন চেইনে কোনো signatory নেই — routing rule / signatory setup যাচাই করুন']);
    exit;
}

// Validate approved_days (if provided)
$requestedDays = (float)$opa['requested_days'];
if ($approvedDays === null || $approvedDays <= 0) {
    $approvedDays = $requestedDays; // default to requested
}
if ($approvedDays <= 0 || $approvedDays > $requestedDays) {
    echo json_encode(['status'=>0,'message'=>"অনুমোদিত দিন 0 এর বেশি এবং চাহিত ($requestedDays) দিনের কম বা সমান হতে হবে"]);
    exit;
}

mysqli_autocommit($con, false);
try {
    // 1. Update parent with admin's decision
    $upd = mysqli_prepare($con,
        "UPDATE optional_leave_pre_approval
         SET approved_days = ?, admin_note = ?, admin_initiator = ?, admin_forward_date = NOW()
         WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'dsii', $approvedDays, $adminNote, $actorUserID, $pid);
    if (!mysqli_stmt_execute($upd)) throw new Exception('parent update failed: ' . mysqli_error($con));
    mysqli_stmt_close($upd);

    // 2. Gate open: flip isSentbyAdmin=1 on all signatory rows
    $flip = mysqli_prepare($con,
        "UPDATE optional_leave_pre_approval_signatory
         SET isSentbyAdmin = 1
         WHERE preApprovalID = ?");
    mysqli_stmt_bind_param($flip, 'i', $pid);
    if (!mysqli_stmt_execute($flip)) throw new Exception('gate update failed: ' . mysqli_error($con));
    mysqli_stmt_close($flip);

    mysqli_commit($con);
    mysqli_autocommit($con, true);

    if (function_exists('audit_log')) {
        audit_log('optional_pre_approval_forwarded', [
            'target_type' => 'optional_pre_approval',
            'target_id'   => $pid,
            'note'        => "approved_days=$approvedDays; admin_user=$actorUserID",
        ]);
    }

    echo json_encode(['status'=>1, 'message'=>'আবেদনটি অনুমোদনের জন্য পাঠানো হয়েছে']);
} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    echo json_encode(['status'=>0, 'message'=>$e->getMessage()]);
}
