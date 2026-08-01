<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/../../config/connection.php');

// Validate session
if (!isset($_SESSION['userID']) || !isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'অননুমোদিত অ্যাক্সেস']);
    exit;
}

// Get POST data
$dataID     = (int)($_POST['dataID']     ?? 0);
$isApproved = (int)($_POST['isApproved'] ?? 0);
$reason     = trim($_POST['reason']      ?? '');

if ($dataID <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'অবৈধ ডেটা আইডি']);
    exit;
}
if ($isApproved !== 1 && $isApproved !== 2) {
    echo json_encode(['status' => 'error', 'message' => 'অবৈধ অনুমোদন মান']);
    exit;
}
if ($isApproved === 2 && $reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'প্রত্যাখ্যানের কারণ আবশ্যক']);
    exit;
}

// Load row + employee's org
$rowStmt = mysqli_prepare($con,
    "SELECT pld.dataID, pld.employeeID, pld.isApproved, el.organization_id, el.employee_name
     FROM previous_leave_deduction pld
     INNER JOIN employee_list el ON pld.employeeID = el.id
     WHERE pld.dataID = ? LIMIT 1");
mysqli_stmt_bind_param($rowStmt, 'i', $dataID);
mysqli_stmt_execute($rowStmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));
mysqli_stmt_close($rowStmt);

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'রেকর্ড পাওয়া যায়নি']);
    exit;
}
if ((int)$row['isApproved'] !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'এই রেকর্ড ইতিমধ্যে নিষ্পত্তি হয়েছে']);
    exit;
}

$empOrgID = (int)$row['organization_id'];

// Resolve actor + verify signatory authorization
$uStmt = mysqli_prepare($con,
    "SELECT employee_id, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
mysqli_stmt_close($uStmt);

$actorEmpId    = (int)($actor['employee_id']    ?? 0);
$actorUserGrp  = (int)($actor['user_group_id']  ?? 0);
$isSuperAdmin  = ($actorUserGrp === 1);

if (!$isSuperAdmin) {
    if ($actorEmpId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'আপনার এই অনুমোদন দেওয়ার অনুমতি নেই']);
        exit;
    }
    // Verify actor is the configured signatory for this employee's org
    $sigStmt = mysqli_prepare($con,
        "SELECT dataID FROM leave_edit_approval_signatory
         WHERE employeeID = ? AND organization_id = ? LIMIT 1");
    mysqli_stmt_bind_param($sigStmt, 'ii', $actorEmpId, $empOrgID);
    mysqli_stmt_execute($sigStmt);
    $sig = mysqli_fetch_assoc(mysqli_stmt_get_result($sigStmt));
    mysqli_stmt_close($sigStmt);
    if (!$sig) {
        echo json_encode(['status' => 'error', 'message' => 'এই কেন্দ্রের তথ্য অনুমোদনের জন্য আপনি নিযুক্ত সিগনেটরি নন']);
        exit;
    }
}

// Update — also stamp lastUpdate (existing column)
$now = date('Y-m-d H:i:s');
$upd = mysqli_prepare($con,
    "UPDATE previous_leave_deduction SET isApproved = ?, lastUpdate = ? WHERE dataID = ?");
mysqli_stmt_bind_param($upd, 'isi', $isApproved, $now, $dataID);
$ok = mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'ডেটা আপডেট করতে ব্যর্থ হয়েছে']);
    exit;
}

if (function_exists('audit_log')) {
    audit_log($isApproved === 1 ? 'previous_leave_approved' : 'previous_leave_rejected', [
        'target_type'     => 'previous_leave_deduction',
        'target_id'       => $dataID,
        'organization_id' => $empOrgID,
        'note'            => 'employeeID=' . (int)$row['employeeID']
                           . '; employee=' . mb_substr((string)$row['employee_name'], 0, 80)
                           . ($isApproved === 2 ? '; reason=' . mb_substr($reason, 0, 200) : ''),
    ]);
}

// Notify affected employee
try {
    $affectedUserID = user_id_for_employee((int)$row['employeeID']);
    $msg = $isApproved === 1
        ? 'আপনার পূর্ববর্তী ছুটির তথ্য অনুমোদিত হয়েছে'
        : 'আপনার পূর্ববর্তী ছুটির তথ্য প্রত্যাখ্যাত হয়েছে। কারণ: ' . mb_substr($reason, 0, 120);
    send_notification([$affectedUserID], $msg, [
        'type' => $isApproved === 1 ? 'previous_leave_approved' : 'previous_leave_rejected',
        'link' => 'views/leave/all-applications.php?menuslug=all-leave-application',
        'isImportant' => $isApproved === 2 ? 1 : 0,
    ]);
} catch (\Throwable $e) { /* silent */ }

echo json_encode([
    'status'  => 'success',
    'message' => $isApproved === 1 ? 'সফলভাবে অনুমোদন করা হয়েছে' : 'সফলভাবে প্রত্যাখ্যান করা হয়েছে',
]);
