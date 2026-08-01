<?php
/**
 * Unlock a locked user account.
 *
 * Super admin only (user_group_id = 1). Called from views/users/manage.php
 * when the admin clicks the "Unlock" action on a locked user row.
 *
 * POST params:
 *   dataID  — user_list.dataID of the locked account
 *
 * Response JSON: { status: 0|1, message }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../config/connection.php');

function reply($ok, $message) {
    echo json_encode(['status' => $ok ? 1 : 0, 'message' => $message]);
    exit;
}

if (empty($_SESSION['username'])) reply(false, 'আপনি লগইন করেননি');

// Super admin only
$g = mysqli_prepare($con, "SELECT user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($g, 's', $_SESSION['username']);
mysqli_stmt_execute($g);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($g)) ?: [];
mysqli_stmt_close($g);
if ((int)($row['user_group_id'] ?? 0) !== 1) reply(false, 'শুধুমাত্র সুপার অ্যাডমিন অ্যাকাউন্ট আনলক করতে পারবেন');

$dataID = (int)($_POST['dataID'] ?? 0);
if ($dataID <= 0) reply(false, 'অবৈধ user id');

$u = mysqli_prepare($con,
    "UPDATE user_list
     SET is_locked = 0, failed_login_attempts = 0, locked_at = NULL, last_failed_login = NULL
     WHERE dataID = ?");
mysqli_stmt_bind_param($u, 'i', $dataID);
$ok = mysqli_stmt_execute($u);
$affected = mysqli_stmt_affected_rows($u);
mysqli_stmt_close($u);

if (!$ok) reply(false, 'ডাটাবেস ত্রুটি');

if (function_exists('audit_log')) {
    audit_log('user_unlocked', [
        'target_type' => 'user_list',
        'target_id'   => $dataID,
        'note'        => 'unlocked by super admin',
    ]);
}

reply(true, 'অ্যাকাউন্ট আনলক করা হয়েছে');
