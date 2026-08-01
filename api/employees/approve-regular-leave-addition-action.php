<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
ob_end_clean();

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 'error', 'message' => 'অননুমোদিত অ্যাক্সেস']);
    exit;
}

// Accepts either batch_key (new: multi-row office order) or dataID (legacy).
// batch_key prefixed '_solo_' means "no real batch_id, use the dataID after the prefix".
$batchKey   = isset($_POST['batch_key']) ? trim($_POST['batch_key']) : '';
$dataID     = intval($_POST['dataID'] ?? 0);
$isApproved = intval($_POST['isApproved'] ?? 0);
$reason     = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Resolve action target: single dataID or a batch_id string
$targetBatchId = '';
if ($batchKey !== '') {
    if (strpos($batchKey, '_solo_') === 0) {
        $dataID = (int)substr($batchKey, 6);
    } else {
        $targetBatchId = $batchKey;
    }
}

if ($targetBatchId === '' && $dataID <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'অবৈধ ডেটা আইডি']);
    exit;
}

if ($isApproved != 1 && $isApproved != 2) {
    echo json_encode(['status' => 'error', 'message' => 'অবৈধ অনুমোদন মান']);
    exit;
}

// Reject requires a reason
if ($isApproved == 2 && $reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'প্রত্যাখ্যানের কারণ আবশ্যক']);
    exit;
}

// Verify signatory authorization
$orgStmt = $con->prepare("SELECT isCenterAdmin, organization_id, employee_id FROM user_list WHERE user_id = ?");
$orgStmt->bind_param("s", $_SESSION['username']);
$orgStmt->execute();
$orgUserRow = $orgStmt->get_result()->fetch_assoc();
$orgStmt->close();
if (!empty($orgUserRow['isCenterAdmin'])) {
    $userOrgID = intval($orgUserRow['organization_id']);
    $userEmpID = intval($orgUserRow['employee_id'] ?? 0);
} elseif (!empty($orgUserRow['employee_id'])) {
    $userEmpID  = intval($orgUserRow['employee_id']);
    $empOrgStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $empOrgStmt->bind_param("i", $userEmpID);
    $empOrgStmt->execute();
    $empOrgRow  = $empOrgStmt->get_result()->fetch_assoc();
    $empOrgStmt->close();
    $userOrgID  = intval($empOrgRow['organization_id'] ?? 0);
} else {
    $userOrgID = 0;
    $userEmpID = 0;
}
// Authorization — per-batch/per-row (not just org-wide).
// Rules:
//   1. Super admin (userOrgID=0) can approve anything.
//   2. If the target row/batch has override_signatory_id set → user's emp_id must match it.
//   3. Else (legacy row) → user must be the org's default signatory.
if ($userOrgID > 0) {
    // Fetch the override_signatory_id for the target
    $overrideSig = null;
    if ($targetBatchId !== '') {
        $ovQ = $con->prepare("SELECT override_signatory_id FROM leave_addition_history WHERE batch_id = ? LIMIT 1");
        $ovQ->bind_param("s", $targetBatchId);
    } else {
        $ovQ = $con->prepare("SELECT override_signatory_id FROM leave_addition_history WHERE dataID = ? LIMIT 1");
        $ovQ->bind_param("i", $dataID);
    }
    $ovQ->execute();
    $ovRow = $ovQ->get_result()->fetch_assoc();
    $ovQ->close();
    if ($ovRow) $overrideSig = $ovRow['override_signatory_id'] === null ? null : (int)$ovRow['override_signatory_id'];

    if ($overrideSig !== null) {
        // Explicit signatory routing — only this employee can act
        if ($overrideSig !== $userEmpID) {
            echo json_encode(['status' => 'error', 'message' => 'আপনি এই আবেদনের নির্ধারিত স্বাক্ষরকারী নন']);
            exit;
        }
    } else {
        // Legacy path — org's default signatory
        $sigStmt = $con->prepare("SELECT dataID FROM leave_edit_approval_signatory WHERE employeeID = ? AND organization_id = ? LIMIT 1");
        $sigStmt->bind_param("ii", $userEmpID, $userOrgID);
        $sigStmt->execute();
        $sigRow = $sigStmt->get_result()->fetch_assoc();
        $sigStmt->close();
        if (!$sigRow) {
            echo json_encode(['status' => 'error', 'message' => 'আপনার এই অনুমোদন দেওয়ার অনুমতি নেই!']);
            exit;
        }
    }
}

// Look up actor user_list.dataID for audit trail
$meStmt = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
$meStmt->bind_param("s", $_SESSION['username']);
$meStmt->execute();
$meRow = $meStmt->get_result()->fetch_assoc();
$meStmt->close();
$actorUserId = (int)($meRow['dataID'] ?? 0);

// One statement handles both single-row (dataID) and batch (batch_id) targets.
if ($targetBatchId !== '') {
    if ($isApproved == 1) {
        $stmt = $con->prepare(
            "UPDATE leave_addition_history
             SET isApproved = 1, approvedBy = ?, approvedDate = NOW(),
                 rejectedBy = NULL, rejectedDate = NULL, rejectionReason = NULL
             WHERE batch_id = ? AND isApproved = 0");
        $stmt->bind_param("is", $actorUserId, $targetBatchId);
    } else {
        $stmt = $con->prepare(
            "UPDATE leave_addition_history
             SET isApproved = 2, rejectedBy = ?, rejectedDate = NOW(), rejectionReason = ?,
                 approvedBy = NULL, approvedDate = NULL
             WHERE batch_id = ? AND isApproved = 0");
        $stmt->bind_param("iss", $actorUserId, $reason, $targetBatchId);
    }
} else {
    if ($isApproved == 1) {
        $stmt = $con->prepare(
            "UPDATE leave_addition_history
             SET isApproved = 1, approvedBy = ?, approvedDate = NOW(),
                 rejectedBy = NULL, rejectedDate = NULL, rejectionReason = NULL
             WHERE dataID = ?");
        $stmt->bind_param("ii", $actorUserId, $dataID);
    } else {
        $stmt = $con->prepare(
            "UPDATE leave_addition_history
             SET isApproved = 2, rejectedBy = ?, rejectedDate = NOW(), rejectionReason = ?,
                 approvedBy = NULL, approvedDate = NULL
             WHERE dataID = ?");
        $stmt->bind_param("isi", $actorUserId, $reason, $dataID);
    }
}
$result = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($result) {
    if (function_exists('audit_log')) {
        audit_log($isApproved == 1 ? 'leave_addition_approved' : 'leave_addition_rejected', [
            'target_type'     => 'leave_addition',
            'target_id'       => $targetBatchId !== '' ? 0 : $dataID,
            'organization_id' => $userOrgID > 0 ? $userOrgID : null,
            'note'            => ($targetBatchId !== '' ? "batch=$targetBatchId; rows=$affected; " : '')
                               . ($isApproved == 2 ? 'reason=' . mb_substr($reason, 0, 200) : ''),
        ]);
    }

    // Notify affected employee(s) — a batch may touch multiple employees;
    // dedupe user IDs so each person gets one notification per action.
    try {
        $affectedEmpIDs = [];
        if ($targetBatchId !== '') {
            $eQ = mysqli_prepare($con,
                "SELECT DISTINCT employeeID FROM leave_addition_history WHERE batch_id = ?");
            mysqli_stmt_bind_param($eQ, 's', $targetBatchId);
            mysqli_stmt_execute($eQ);
            $er = mysqli_stmt_get_result($eQ);
            while ($r = mysqli_fetch_assoc($er)) $affectedEmpIDs[] = (int)$r['employeeID'];
            mysqli_stmt_close($eQ);
        } elseif ($dataID > 0) {
            $eQ = mysqli_prepare($con,
                "SELECT employeeID FROM leave_addition_history WHERE dataID = ? LIMIT 1");
            mysqli_stmt_bind_param($eQ, 'i', $dataID);
            mysqli_stmt_execute($eQ);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($eQ));
            if ($r) $affectedEmpIDs[] = (int)$r['employeeID'];
            mysqli_stmt_close($eQ);
        }
        $userIDs = user_ids_for_employees($affectedEmpIDs);

        $msg = $isApproved == 1
            ? 'আপনার ছুটির সংযোজনের অফিস আদেশ অনুমোদিত হয়েছে'
            : 'আপনার ছুটির সংযোজনের অফিস আদেশ প্রত্যাখ্যাত হয়েছে। কারণ: ' . mb_substr($reason, 0, 120);
        send_notification($userIDs, $msg, [
            'type' => $isApproved == 1 ? 'leave_addition_approved' : 'leave_addition_rejected',
            'link' => 'views/leave/all-applications.php?menuslug=all-leave-application',
            'isImportant' => $isApproved == 2 ? 1 : 0,
        ]);
    } catch (\Throwable $e) { /* silent */ }

    $countPart = ($targetBatchId !== '' && $affected > 1) ? " ($affected টি এন্ট্রি)" : '';
    $message = ($isApproved == 1 ? 'সফলভাবে অনুমোদন করা হয়েছে' : 'সফলভাবে প্রত্যাখ্যান করা হয়েছে') . $countPart;
    echo json_encode(['status' => 'success', 'message' => $message]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'ডেটা আপডেট করতে ব্যর্থ হয়েছে']);
}
?>
