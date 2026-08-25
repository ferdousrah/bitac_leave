<?php
/**
 * Migration: third-level sidebar menus.
 *
 *  - Add `submodules.parent_id` — points at another submodule in the same
 *    module. 0 = ordinary second-level item; a real id = third level, drawn in
 *    a flyout beside the sidebar.
 *  - Example grouping: put the three role menus under a রোল কার্যক্রম parent.
 *
 * The sidebar checks for the column and renders the menu flat when it is
 * absent, so nothing breaks before this runs — but nesting only works after.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_submenu_parent.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
$log = [];

// The কনফিগারেশন module. Change this if the menu lives elsewhere on your install.
$CONFIG_MODULE_ID = 49;

// ── Step 1: parent_id column ──────────────────────────────────────────
$colExists = false;
$res = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($res && mysqli_num_rows($res) > 0) $colExists = true;

if (!$colExists) {
    $sql = "ALTER TABLE submodules
            ADD COLUMN parent_id INT NOT NULL DEFAULT 0 AFTER module_id,
            ADD KEY idx_parent (parent_id)";
    if (mysqli_query($con, $sql)) {
        $log[] = "ADDED column: submodules.parent_id";
    } else {
        $log[] = "ERROR adding column: " . mysqli_error($con);
    }
} else {
    $log[] = "SKIP: parent_id already exists";
}

/** Returns the id of a live submodule by slug, or 0. */
function sub_id_by_slug($con, $slug) {
    $stmt = mysqli_prepare($con, "SELECT dataID FROM submodules WHERE slug = ? AND deleted = 0 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return (int)($row['dataID'] ?? 0);
}

/** Inserts a submodule if its slug is free; returns the id either way. */
function ensure_sub($con, $name, $moduleId, $parentId, $link, $slug, $order) {
    $existing = sub_id_by_slug($con, $slug);
    if ($existing) return $existing;
    $stmt = mysqli_prepare($con,
        "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date)
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
    mysqli_stmt_bind_param($stmt, 'siissi', $name, $moduleId, $parentId, $link, $slug, $order);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);
    return (int)$id;
}

// ── Step 2: example grouping — রোল কার্যক্রম ──────────────────────────
// A grouping row: page_link '#' because it has no page of its own. It needs no
// permission row — the sidebar shows a parent whenever any child is permitted,
// so the children's existing permissions keep working untouched.
if ($colExists || sub_id_by_slug($con, 'role-approval')) {
    $roleParentId = sub_id_by_slug($con, 'role-activities');
    if ($roleParentId) {
        $log[] = "SKIP: রোল কার্যক্রম already exists (id $roleParentId)";
    } else {
        $roleParentId = ensure_sub($con, 'রোল কার্যক্রম', $CONFIG_MODULE_ID, 0, '#', 'role-activities', 8);
        $log[] = "ADDED submodule: রোল কার্যক্রম (id $roleParentId)";
    }

    if ($roleParentId) {
        $stmt = mysqli_prepare($con,
            "UPDATE submodules SET parent_id = ?
             WHERE slug IN ('role-approval', 'honour-board', 'role-audit-log')
               AND deleted = 0 AND parent_id <> ?");
        mysqli_stmt_bind_param($stmt, 'ii', $roleParentId, $roleParentId);
        mysqli_stmt_execute($stmt);
        $log[] = "Nested " . mysqli_stmt_affected_rows($stmt) . " role menu(s) under রোল কার্যক্রম";
        mysqli_stmt_close($stmt);
    }
} else {
    $log[] = "SKIP: role menus not present on this install";
}

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "SUBMENU PARENT MIGRATION\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
