<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/../../connection.php');

// Validate session
if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 'error', 'message' => 'অননুমোদিত অ্যাক্সেস']);
    exit;
}

// Accepts either batch_key (multi-row office order) or dataID (legacy).
$batchKey   = isset($_POST['batch_key']) ? trim($_POST['batch_key']) : '';
$dataID     = isset($_POST['dataID']) ? intval($_POST['dataID']) : 0;
$isApproved = isset($_POST['isApproved']) ? intval($_POST['isApproved']) : 0;
$reason     = isset($_POST['reason']) ? trim($_POST['reason']) : '';

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
// Authorization — override_signatory_id takes precedence over org-default routing.
if ($userOrgID > 0) {
    $overrideSig = null;
    if ($targetBatchId !== '') {
        $ovQ = mysqli_prepare($con, "SELECT override_signatory_id FROM leave_deduction_history WHERE batch_id = ? LIMIT 1");
        mysqli_stmt_bind_param($ovQ, "s", $targetBatchId);
    } else {
        $ovQ = mysqli_prepare($con, "SELECT override_signatory_id FROM leave_deduction_history WHERE dataID = ? LIMIT 1");
        mysqli_stmt_bind_param($ovQ, "i", $dataID);
    }
    mysqli_stmt_execute($ovQ);
    $ovRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ovQ));
    mysqli_stmt_close($ovQ);
    if ($ovRow) $overrideSig = $ovRow['override_signatory_id'] === null ? null : (int)$ovRow['override_signatory_id'];

    if ($overrideSig !== null) {
        if ($overrideSig !== $userEmpID) {
            echo json_encode(['status' => 'error', 'message' => 'আপনি এই আবেদনের নির্ধারিত স্বাক্ষরকারী নন']);
            exit;
        }
    } else {
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

// Look up current user_list.dataID for the audit trail (approvedBy / rejectedBy)
$meStmt = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
$meStmt->bind_param("s", $_SESSION['username']);
$meStmt->execute();
$meRow = $meStmt->get_result()->fetch_assoc();
$meStmt->close();
$actorUserId = (int)($meRow['dataID'] ?? 0);

// One statement handles both single-row (dataID) and batch (batch_id) targets.
if ($targetBatchId !== '') {
    if ($isApproved == 1) {
        $stmt = mysqli_prepare($con,
            "UPDATE leave_deduction_history
             SET isApproved = 1, approvedBy = ?, approvedDate = NOW(),
                 rejectedBy = NULL, rejectedDate = NULL, rejectionReason = NULL
             WHERE batch_id = ? AND isApproved = 0");
        mysqli_stmt_bind_param($stmt, "is", $actorUserId, $targetBatchId);
    } else {
        $stmt = mysqli_prepare($con,
            "UPDATE leave_deduction_history
             SET isApproved = 2, rejectedBy = ?, rejectedDate = NOW(), rejectionReason = ?,
                 approvedBy = NULL, approvedDate = NULL
             WHERE batch_id = ? AND isApproved = 0");
        mysqli_stmt_bind_param($stmt, "iss", $actorUserId, $reason, $targetBatchId);
    }
} else {
    if ($isApproved == 1) {
        $stmt = mysqli_prepare($con,
            "UPDATE leave_deduction_history
             SET isApproved = 1, approvedBy = ?, approvedDate = NOW(),
                 rejectedBy = NULL, rejectedDate = NULL, rejectionReason = NULL
             WHERE dataID = ?");
        mysqli_stmt_bind_param($stmt, "ii", $actorUserId, $dataID);
    } else {
        $stmt = mysqli_prepare($con,
            "UPDATE leave_deduction_history
             SET isApproved = 2, rejectedBy = ?, rejectedDate = NOW(), rejectionReason = ?,
                 approvedBy = NULL, approvedDate = NULL
             WHERE dataID = ?");
        mysqli_stmt_bind_param($stmt, "isi", $actorUserId, $reason, $dataID);
    }
}
$result   = mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($result) {
    if (function_exists('audit_log')) {
        audit_log($isApproved == 1 ? 'leave_deduction_approved' : 'leave_deduction_rejected', [
            'target_type'     => 'leave_deduction',
            'target_id'       => $targetBatchId !== '' ? 0 : $dataID,
            'organization_id' => $userOrgID > 0 ? $userOrgID : null,
            'note'            => ($targetBatchId !== '' ? "batch=$targetBatchId; rows=$affected; " : '')
                               . ($isApproved == 2 ? 'reason=' . mb_substr($reason, 0, 200) : ''),
        ]);
    }
    $countPart = ($targetBatchId !== '' && $affected > 1) ? " ($affected টি এন্ট্রি)" : '';
    $message = ($isApproved == 1 ? 'সফলভাবে অনুমোদন করা হয়েছে' : 'সফলভাবে প্রত্যাখ্যান করা হয়েছে') . $countPart;
    echo json_encode(['status' => 'success', 'message' => $message]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'ডেটা আপডেট করতে ব্যর্থ হয়েছে']);
}
