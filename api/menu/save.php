<?php
/**
 * Insert or update a submodule, including where it sits in the menu tree.
 * POST: dataID (0 = new), module_id, parent_id, submodule_name, page_link, slug, display_order
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

$hasParent = false;
$c = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($c && mysqli_num_rows($c) > 0) $hasParent = true;

$dataID   = (int)($_POST['dataID']    ?? 0);
$moduleId = (int)($_POST['module_id'] ?? 0);
$parentId = $hasParent ? (int)($_POST['parent_id'] ?? 0) : 0;
$name     = trim((string)($_POST['submodule_name'] ?? ''));
$link     = trim((string)($_POST['page_link'] ?? ''));
$slug     = trim((string)($_POST['slug'] ?? ''));
$order    = (int)($_POST['display_order'] ?? 0);

if ($name === '')                 reply(false, 'নাম খালি রাখা যাবে না');
if (mb_strlen($name) > 200)       reply(false, 'নাম ২০০ অক্ষরের বেশি হতে পারবে না');
if ($slug === '')                 reply(false, 'স্লাগ খালি রাখা যাবে না');
if (mb_strlen($slug) > 200)       reply(false, 'স্লাগ ২০০ অক্ষরের বেশি হতে পারবে না');
if (mb_strlen($link) > 80)        reply(false, 'লিংক ৮০ অক্ষরের বেশি হতে পারবে না');
if ($link === '')                 $link = '#';
if ($order < 0)                   $order = 0;

// The module must exist, or the row would be invisible in every sidebar.
$mchk = mysqli_prepare($con, "SELECT dataID FROM modules WHERE dataID = ? AND deleted = 0");
mysqli_stmt_bind_param($mchk, 'i', $moduleId);
mysqli_stmt_execute($mchk);
$mok = mysqli_stmt_get_result($mchk)->fetch_assoc();
mysqli_stmt_close($mchk);
if (!$mok) reply(false, 'মূল মেনুটি পাওয়া যায়নি');

// The sidebar draws exactly three levels. Guard the parent so a deeper tree —
// which would silently drop rows from the menu — cannot be saved.
if ($parentId > 0) {
    if ($parentId === $dataID) reply(false, 'একটি সাবমেনু নিজের নিচে বসতে পারে না');

    $pstmt = mysqli_prepare($con,
        "SELECT dataID, module_id, parent_id FROM submodules WHERE dataID = ? AND deleted = 0");
    mysqli_stmt_bind_param($pstmt, 'i', $parentId);
    mysqli_stmt_execute($pstmt);
    $parent = mysqli_stmt_get_result($pstmt)->fetch_assoc();
    mysqli_stmt_close($pstmt);

    if (!$parent) reply(false, 'যে সাবমেনুর নিচে বসাতে চাইছেন সেটি পাওয়া যায়নি');
    if ((int)$parent['module_id'] !== $moduleId) reply(false, 'অভিভাবক সাবমেনুটি অন্য মূল মেনুর অধীনে');
    if ((int)$parent['parent_id'] > 0) reply(false, 'সাইডবারে তিন স্তরের বেশি দেখানো যায় না');

    // Moving a row that already has children under another parent would create
    // that fourth level from the other direction.
    if ($dataID > 0) {
        $kstmt = mysqli_prepare($con,
            "SELECT COUNT(*) AS n FROM submodules WHERE parent_id = ? AND deleted = 0");
        mysqli_stmt_bind_param($kstmt, 'i', $dataID);
        mysqli_stmt_execute($kstmt);
        $kids = (int)(mysqli_stmt_get_result($kstmt)->fetch_assoc()['n'] ?? 0);
        mysqli_stmt_close($kstmt);
        if ($kids > 0) reply(false, 'এই সাবমেনুর নিচে অন্য মেনু আছে, তাই একে আরেকটির নিচে বসানো যাবে না');
    }
}

// A duplicate slug makes both menus highlight on the same page.
$sstmt = mysqli_prepare($con,
    "SELECT dataID FROM submodules WHERE slug = ? AND deleted = 0 AND dataID <> ? LIMIT 1");
mysqli_stmt_bind_param($sstmt, 'si', $slug, $dataID);
mysqli_stmt_execute($sstmt);
$dupe = mysqli_stmt_get_result($sstmt)->fetch_assoc();
mysqli_stmt_close($sstmt);
if ($dupe) reply(false, 'এই স্লাগটি আরেকটি সাবমেনু ব্যবহার করছে');

if ($dataID > 0) {
    $sql = $hasParent
        ? "UPDATE submodules SET module_id = ?, parent_id = ?, submodule_name = ?, page_link = ?, slug = ?, display_order = ?, last_update_date = NOW(), updated_by = ? WHERE dataID = ?"
        : "UPDATE submodules SET module_id = ?, submodule_name = ?, page_link = ?, slug = ?, display_order = ?, last_update_date = NOW(), updated_by = ? WHERE dataID = ?";
    $stmt = mysqli_prepare($con, $sql);
    if ($hasParent) {
        mysqli_stmt_bind_param($stmt, 'iisssiii', $moduleId, $parentId, $name, $link, $slug, $order, $me['dataID'], $dataID);
    } else {
        mysqli_stmt_bind_param($stmt, 'isssiii', $moduleId, $name, $link, $slug, $order, $me['dataID'], $dataID);
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) reply(false, 'সংরক্ষণ ব্যর্থ');

    if (function_exists('audit_log')) {
        audit_log('menu_submodule_updated', [
            'target_type' => 'submodule', 'target_id' => $dataID,
            'note' => $name . ' (parent=' . $parentId . ', slug=' . $slug . ')',
        ]);
    }
    reply(true, 'সাবমেনু হালনাগাদ হয়েছে');
}

$sql = $hasParent
    ? "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date, created_by) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), ?)"
    : "INSERT INTO submodules (submodule_name, module_id, page_link, slug, display_order, deleted, create_date, created_by) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?)";
$stmt = mysqli_prepare($con, $sql);
if ($hasParent) {
    mysqli_stmt_bind_param($stmt, 'siissii', $name, $moduleId, $parentId, $link, $slug, $order, $me['dataID']);
} else {
    mysqli_stmt_bind_param($stmt, 'sissii', $name, $moduleId, $link, $slug, $order, $me['dataID']);
}
$ok = mysqli_stmt_execute($stmt);
$newId = mysqli_insert_id($con);
mysqli_stmt_close($stmt);
if (!$ok) reply(false, 'সংরক্ষণ ব্যর্থ');

if (function_exists('audit_log')) {
    audit_log('menu_submodule_created', [
        'target_type' => 'submodule', 'target_id' => $newId,
        'note' => $name . ' (parent=' . $parentId . ', slug=' . $slug . ')',
    ]);
}

reply(true, 'সাবমেনু যোগ হয়েছে। অনুমতি দিতে ইউজার গ্রুপের অ্যাক্সেস তালিকায় এটি বাছুন।');
