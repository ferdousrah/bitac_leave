<?php
/**
 * Approve or reject a yearly leave certificate.
 * POST: leaveSummaryID, isApproved (1 approve / 2 reject), reason (required on reject)
 */
session_start();
header('Content-Type: application/json');
require_once(__DIR__ . '/../../config/connection.php');

function reply($ok, $msg) {
    echo json_encode(['status' => $ok ? 'success' : 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['username'])) reply(false, 'অননুমোদিত অ্যাক্সেস');

$id         = (int)($_POST['leaveSummaryID'] ?? 0);
$isApproved = (int)($_POST['isApproved']     ?? 0);
$reason     = trim((string)($_POST['reason'] ?? ''));

if ($id <= 0)                              reply(false, 'অবৈধ সনদ আইডি');
if ($isApproved !== 1 && $isApproved !== 2) reply(false, 'অবৈধ অনুমোদন মান');
if ($isApproved === 2 && $reason === '')    reply(false, 'অননুমোদিত করার কারণ আবশ্যক');

$rowStmt = mysqli_prepare($con,
    "SELECT yls.leaveSummaryID, yls.employeeID, yls.isApproved, yls.year,
            el.organization_id, el.employee_name
     FROM yearly_leave_summary yls
     INNER JOIN employee_list el ON yls.employeeID = el.id
     WHERE yls.leaveSummaryID = ? LIMIT 1");
mysqli_stmt_bind_param($rowStmt, 'i', $id);
mysqli_stmt_execute($rowStmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));
mysqli_stmt_close($rowStmt);

if (!$row)                            reply(false, 'সনদটি পাওয়া যায়নি');
if ((int)$row['isApproved'] !== 0)    reply(false, 'এই সনদ ইতিমধ্যে নিষ্পত্তি হয়েছে');

$empOrgID = (int)$row['organization_id'];

$uStmt = mysqli_prepare($con, "SELECT employee_id, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
mysqli_stmt_close($uStmt);

$actorEmpId   = (int)($actor['employee_id']   ?? 0);
$isSuperAdmin = ((int)($actor['user_group_id'] ?? 0) === 1);

// A signatory may only sign for the centres they are assigned to. Checked here
// as well as on the page, because the page only decides what is shown.
if (!$isSuperAdmin) {
    if ($actorEmpId <= 0) reply(false, 'আপনার এই অনুমোদন দেওয়ার অনুমতি নেই');

    $sigStmt = mysqli_prepare($con,
        "SELECT dataID FROM leave_edit_approval_signatory
         WHERE employeeID = ? AND organization_id = ? LIMIT 1");
    mysqli_stmt_bind_param($sigStmt, 'ii', $actorEmpId, $empOrgID);
    mysqli_stmt_execute($sigStmt);
    $sig = mysqli_fetch_assoc(mysqli_stmt_get_result($sigStmt));
    mysqli_stmt_close($sigStmt);
    if (!$sig) reply(false, 'এই কেন্দ্রের সনদ অনুমোদনের জন্য আপনি নিযুক্ত সিগনেটরি নন');
}

$now      = date('Y-m-d H:i:s');
$rejected = ($isApproved === 2) ? $reason : null;

$upd = mysqli_prepare($con,
    "UPDATE yearly_leave_summary
        SET isApproved = ?, approvedBy = ?, approvedDate = ?, rejectionReason = ?
      WHERE leaveSummaryID = ? AND isApproved = 0");
mysqli_stmt_bind_param($upd, 'iissi', $isApproved, $actorEmpId, $now, $rejected, $id);
$ok = mysqli_stmt_execute($upd);
$changed = mysqli_stmt_affected_rows($upd);
mysqli_stmt_close($upd);

if (!$ok)          reply(false, 'ডেটা আপডেট করতে ব্যর্থ হয়েছে');
// isApproved = 0 in the WHERE clause makes a double submit a no-op rather than
// letting a second click overwrite the first decision.
if ($changed < 1)  reply(false, 'এই সনদ ইতিমধ্যে নিষ্পত্তি হয়েছে');

if (function_exists('audit_log')) {
    audit_log($isApproved === 1 ? 'leave_certificate_approved' : 'leave_certificate_rejected', [
        'target_type'     => 'yearly_leave_summary',
        'target_id'       => $id,
        'organization_id' => $empOrgID,
        'note'            => 'employeeID=' . (int)$row['employeeID']
                           . '; employee=' . mb_substr((string)$row['employee_name'], 0, 80)
                           . '; year=' . $row['year']
                           . ($isApproved === 2 ? '; reason=' . mb_substr($reason, 0, 200) : ''),
    ]);
}

try {
    $affectedUserID = user_id_for_employee((int)$row['employeeID']);
    $msg = $isApproved === 1
        ? 'আপনার ' . $row['year'] . ' সালের ছুটির সনদ অনুমোদিত হয়েছে'
        : 'আপনার ' . $row['year'] . ' সালের ছুটির সনদ অননুমোদিত হয়েছে। কারণ: ' . mb_substr($reason, 0, 120);
    send_notification([$affectedUserID], $msg, [
        'type'        => $isApproved === 1 ? 'leave_certificate_approved' : 'leave_certificate_rejected',
        'link'        => 'views/leave-certificate/yearly.php?menuslug=yearly-leave-certificate-form',
        'isImportant' => $isApproved === 2 ? 1 : 0,
    ]);
} catch (\Throwable $e) { /* notification failure must not undo the decision */ }

reply(true, $isApproved === 1 ? 'সনদ অনুমোদন করা হয়েছে' : 'সনদ অননুমোদিত করা হয়েছে');
