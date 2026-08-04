<?php
/**
 * Persist the drag-drop order — POST order[] with row IDs in the new
 * visual sequence. Serial is rewritten 1..N in that order.
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

function reply($ok, $msg = '') {
    echo json_encode(['status' => $ok ? 1 : 0, 'message' => $msg]);
    exit;
}

if (empty($_SESSION['username'])) reply(false, 'লগইন করা নেই');

$order = (array)($_POST['order'] ?? []);
if (empty($order)) reply(false, 'ক্রম তথ্য পাওয়া যায়নি');

$stmt = mysqli_prepare($con, "UPDATE default_notice_copies SET serial = ? WHERE dataID = ?");
$serial = 0;
$allOk = true;
foreach ($order as $id) {
    $id = (int)$id;
    if ($id <= 0) continue;
    $serial++;
    mysqli_stmt_bind_param($stmt, 'ii', $serial, $id);
    if (!mysqli_stmt_execute($stmt)) { $allOk = false; }
}
mysqli_stmt_close($stmt);

if (function_exists('audit_log')) {
    audit_log('default_notice_copies_reordered', [
        'target_type' => 'default_notice_copy',
        'target_id'   => 0,
        'note'        => 'count=' . count($order),
    ]);
}

reply($allOk, $allOk ? 'সফল' : 'কিছু আপডেট ব্যর্থ');
