<?php
/**
 * Migration: register the মেনু ব্যবস্থাপনা page.
 *
 * Adds views/menu/manage.php to the কনফিগারেশন menu and grants it to super
 * admin (user group 1). The page carries its own user_group_id = 1 gate, so
 * this row only decides whose sidebar it appears in.
 *
 * Run add_submenu_parent.php first if you want the nesting controls to work;
 * without that column the page still edits names, links and order, and says so.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_menu_manage_page.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
$log = [];

// The কনফিগারেশন module. Change this if the menu lives elsewhere on your install.
$CONFIG_MODULE_ID = 49;

$hasParent = false;
$res = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($res && mysqli_num_rows($res) > 0) $hasParent = true;

// ── Step 1: the submodule row ─────────────────────────────────────────
$stmt = mysqli_prepare($con, "SELECT dataID FROM submodules WHERE slug = 'manage-menu' AND deleted = 0 LIMIT 1");
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
$menuPageId = (int)($row['dataID'] ?? 0);

if ($menuPageId) {
    $log[] = "SKIP: manage-menu already exists (id $menuPageId)";
} else {
    $sql = $hasParent
        ? "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date)
           VALUES ('মেনু ব্যবস্থাপনা', ?, 0, 'views/menu/manage.php', 'manage-menu', 13, 0, NOW())"
        : "INSERT INTO submodules (submodule_name, module_id, page_link, slug, display_order, deleted, create_date)
           VALUES ('মেনু ব্যবস্থাপনা', ?, 'views/menu/manage.php', 'manage-menu', 13, 0, NOW())";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $CONFIG_MODULE_ID);
    if (mysqli_stmt_execute($stmt)) {
        $menuPageId = mysqli_insert_id($con);
        $log[] = "ADDED submodule: মেনু ব্যবস্থাপনা (id $menuPageId)";
    } else {
        $log[] = "ERROR inserting submodule: " . mysqli_error($con);
    }
    mysqli_stmt_close($stmt);
}

// ── Step 2: grant it to super admin ───────────────────────────────────
if ($menuPageId) {
    $chk = mysqli_prepare($con,
        "SELECT id FROM group_access_permission WHERE user_group_id = 1 AND submodule_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, 'i', $menuPageId);
    mysqli_stmt_execute($chk);
    $have = mysqli_stmt_get_result($chk)->fetch_assoc();
    mysqli_stmt_close($chk);

    if ($have) {
        $log[] = "SKIP: super admin already has manage-menu";
    } else {
        $ins = mysqli_prepare($con,
            "INSERT INTO group_access_permission (user_group_id, module_id, submodule_id) VALUES (1, ?, ?)");
        mysqli_stmt_bind_param($ins, 'ii', $CONFIG_MODULE_ID, $menuPageId);
        if (mysqli_stmt_execute($ins)) $log[] = "GRANTED manage-menu to user group 1";
        else                            $log[] = "ERROR granting: " . mysqli_error($con);
        mysqli_stmt_close($ins);
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "MENU MANAGE PAGE MIGRATION\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
