<?php
/**
 * Delete a row from default_notice_copies. Historical office notices
 * already have their own leave_notice_copy rows so are unaffected.
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

function reply($ok, $msg = '') {
    echo json_encode(['status' => $ok ? 1 : 0, 'message' => $msg]);
    exit;
}

if (empty($_SESSION['username'])) reply(false, 'লগইন করা নেই');

$dataID = (int)($_POST['dataID'] ?? 0);
if ($dataID <= 0) reply(false, 'অবৈধ id');

// Grab label first for audit
$labelForAudit = '';
$fq = mysqli_prepare($con, "SELECT label FROM default_notice_copies WHERE dataID = ?");
mysqli_stmt_bind_param($fq, 'i', $dataID);
mysqli_stmt_execute($fq);
if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($fq))) {
    $labelForAudit = $row['label'] ?? '';
}
mysqli_stmt_close($fq);

$stmt = mysqli_prepare($con, "DELETE FROM default_notice_copies WHERE dataID = ?");
mysqli_stmt_bind_param($stmt, 'i', $dataID);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($ok && function_exists('audit_log')) {
    audit_log('default_notice_copy_deleted', [
        'target_type' => 'default_notice_copy',
        'target_id'   => $dataID,
        'note'        => 'label=' . mb_substr($labelForAudit, 0, 120),
    ]);
}

reply($ok, $ok ? 'সফল' : 'ডাটাবেস ত্রুটি');
