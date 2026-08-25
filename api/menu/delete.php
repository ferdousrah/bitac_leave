<?php
/**
 * Soft-delete a submodule and drop the permission rows that pointed at it.
 * POST: dataID
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

function reply($ok, $msg = '') {
    echo json_encode(['status' => $ok ? 1 : 0, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['username'])) reply(false, 'লগইন করা নেই');

$u = mysqli_prepare($con, "SELECT dataID, user_group_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($u, 's', $_SESSION['username']);
mysqli_stmt_execute($u);
$me = mysqli_stmt_get_result($u)->fetch_assoc();
mysqli_stmt_close($u);
if ((int)($me['user_group_id'] ?? 0) !== 1) reply(false, 'অনুমতি নেই');

$dataID = (int)($_POST['dataID'] ?? 0);
if ($dataID <= 0) reply(false, 'ভুল আইডি');

$stmt = mysqli_prepare($con, "SELECT submodule_name FROM submodules WHERE dataID = ? AND deleted = 0");
mysqli_stmt_bind_param($stmt, 'i', $dataID);
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if (!$row) reply(false, 'সাবমেনুটি পাওয়া যায়নি');

// Deleting a parent would orphan its children — they would vanish from every
// sidebar with no sign of why. Make the caller deal with them first.
$c = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($c && mysqli_num_rows($c) > 0) {
    $kstmt = mysqli_prepare($con, "SELECT COUNT(*) AS n FROM submodules WHERE parent_id = ? AND deleted = 0");
    mysqli_stmt_bind_param($kstmt, 'i', $dataID);
    mysqli_stmt_execute($kstmt);
    $kids = (int)(mysqli_stmt_get_result($kstmt)->fetch_assoc()['n'] ?? 0);
    mysqli_stmt_close($kstmt);
    if ($kids > 0) reply(false, 'এর নিচে ' . $kids . ' টি সাবমেনু আছে — আগে সেগুলো সরান বা অন্যত্র বসান');
}

mysqli_begin_transaction($con);
try {
    $d = mysqli_prepare($con,
        "UPDATE submodules SET deleted = 1, deleted_by = ?, deleted_date = NOW() WHERE dataID = ?");
    mysqli_stmt_bind_param($d, 'ii', $me['dataID'], $dataID);
    if (!mysqli_stmt_execute($d)) throw new Exception('মুছতে ব্যর্থ');
    mysqli_stmt_close($d);

    // A permission row pointing at a deleted submodule keeps its module visible
    // in the sidebar with nothing under it.
    $p = mysqli_prepare($con, "DELETE FROM group_access_permission WHERE submodule_id = ?");
    mysqli_stmt_bind_param($p, 'i', $dataID);
    mysqli_stmt_execute($p);
    mysqli_stmt_close($p);

    mysqli_commit($con);
} catch (Throwable $e) {
    mysqli_rollback($con);
    reply(false, $e->getMessage());
}

if (function_exists('audit_log')) {
    audit_log('menu_submodule_deleted', [
        'target_type' => 'submodule', 'target_id' => $dataID,
        'note' => $row['submodule_name'],
    ]);
}

reply(true, 'সাবমেনু মুছে ফেলা হয়েছে');
