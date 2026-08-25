<?php
/**
 * One submodule row, for the edit modal.
 * GET: dataID
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

function reply($ok, $msg = '', $data = null) {
    echo json_encode(['status' => $ok ? 1 : 0, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['username'])) reply(false, 'লগইন করা নেই');

// Same gate as the page: this data drives edits to everyone's sidebar.
$u = mysqli_prepare($con, "SELECT user_group_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($u, 's', $_SESSION['username']);
mysqli_stmt_execute($u);
$me = mysqli_stmt_get_result($u)->fetch_assoc();
mysqli_stmt_close($u);
if ((int)($me['user_group_id'] ?? 0) !== 1) reply(false, 'অনুমতি নেই');

$dataID = (int)($_GET['dataID'] ?? 0);
if ($dataID <= 0) reply(false, 'ভুল আইডি');

$hasParent = false;
$c = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($c && mysqli_num_rows($c) > 0) $hasParent = true;
$parentSel = $hasParent ? 'parent_id' : '0 AS parent_id';

$stmt = mysqli_prepare($con,
    "SELECT dataID, module_id, $parentSel, submodule_name, page_link, slug, display_order
     FROM submodules WHERE dataID = ? AND deleted = 0");
mysqli_stmt_bind_param($stmt, 'i', $dataID);
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$row) reply(false, 'সাবমেনুটি পাওয়া যায়নি');
reply(true, '', $row);
