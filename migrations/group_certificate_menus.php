<?php
/**
 * Migration: gather the three leave-certificate menus under one group.
 *
 *   অ্যাডমিন প্যানেল
 *     └ ছুটির সনদ
 *         ├ সনদ তৈরি      (views/leave-certificate/generate.php)
 *         ├ অনুমোদন        (views/leave-certificate/approval.php)
 *         └ সনদ প্রিন্ট     (views/leave-certificate/yearly.php)
 *
 * অনুমোদন and সনদ প্রিন্ট move across from the ছুটি module, so their
 * group_access_permission rows have to follow — the sidebar groups a submodule
 * under gap.module_id, not under submodules.module_id, and a row left pointing
 * at the old module would render the item back under ছুটি, detached from its
 * parent.
 *
 * Needs add_submenu_parent.php (the parent_id column) to have run first;
 * without it the three menus are simply left where they are.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/group_certificate_menus.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
$log = [];

$ADMIN_MODULE_ID = 45;   // অ্যাডমিন প্যানেল
$PARENT_SLUG     = 'leave-certificate-group';
$PARENT_ORDER    = 10;   // where সনদ তৈরি sat, so the group keeps its place

$hasParent = false;
$res = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($res && mysqli_num_rows($res) > 0) $hasParent = true;

if (!$hasParent) {
    $log[] = "ABORT: submodules.parent_id missing — run add_submenu_parent.php first";
} else {
    // ── Step 1: the grouping row ──────────────────────────────────────
    $stmt = mysqli_prepare($con, "SELECT dataID FROM submodules WHERE slug = ? AND deleted = 0 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $PARENT_SLUG);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    $parentId = (int)($row['dataID'] ?? 0);

    if ($parentId) {
        $log[] = "SKIP: ছুটির সনদ group already exists (id $parentId)";
    } else {
        // page_link '#': grouping only, no page of its own, so it needs no
        // permission row — the sidebar shows it whenever a child is permitted.
        $stmt = mysqli_prepare($con,
            "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date)
             VALUES ('ছুটির সনদ', ?, 0, '#', ?, ?, 0, NOW())");
        mysqli_stmt_bind_param($stmt, 'isi', $ADMIN_MODULE_ID, $PARENT_SLUG, $PARENT_ORDER);
        if (mysqli_stmt_execute($stmt)) {
            $parentId = mysqli_insert_id($con);
            $log[]    = "ADDED submodule: ছুটির সনদ (id $parentId)";
        } else {
            $log[] = "ERROR inserting group: " . mysqli_error($con);
        }
        mysqli_stmt_close($stmt);
    }

    // ── Step 2: move, rename and order the three children ─────────────
    $children = [
        ['slug' => 'generate-leave-certificate-form', 'name' => 'সনদ তৈরি',   'order' => 1],
        ['slug' => 'leave-certificate-approval',      'name' => 'অনুমোদন',    'order' => 2],
        ['slug' => 'yearly-leave-certificate-form',   'name' => 'সনদ প্রিন্ট', 'order' => 3],
    ];

    if ($parentId) {
        foreach ($children as $c) {
            $find = mysqli_prepare($con,
                "SELECT dataID, module_id, parent_id FROM submodules WHERE slug = ? AND deleted = 0 LIMIT 1");
            mysqli_stmt_bind_param($find, 's', $c['slug']);
            mysqli_stmt_execute($find);
            $sub = mysqli_stmt_get_result($find)->fetch_assoc();
            mysqli_stmt_close($find);

            if (!$sub) { $log[] = "SKIP: {$c['slug']} not present on this install"; continue; }

            $subId  = (int)$sub['dataID'];
            $oldMod = (int)$sub['module_id'];

            $upd = mysqli_prepare($con,
                "UPDATE submodules
                    SET submodule_name = ?, module_id = ?, parent_id = ?, display_order = ?
                  WHERE dataID = ?");
            mysqli_stmt_bind_param($upd, 'siiii', $c['name'], $ADMIN_MODULE_ID, $parentId, $c['order'], $subId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $log[] = "MOVED {$c['slug']} (id $subId) → module $ADMIN_MODULE_ID, parent $parentId, name {$c['name']}";

            // The permission rows must name the new module too, or the sidebar
            // keeps grouping this item under the module it came from.
            if ($oldMod !== $ADMIN_MODULE_ID) {
                $perm = mysqli_prepare($con,
                    "UPDATE group_access_permission SET module_id = ? WHERE submodule_id = ? AND module_id <> ?");
                mysqli_stmt_bind_param($perm, 'iii', $ADMIN_MODULE_ID, $subId, $ADMIN_MODULE_ID);
                mysqli_stmt_execute($perm);
                $n = mysqli_stmt_affected_rows($perm);
                mysqli_stmt_close($perm);
                $log[] = "  repointed $n permission row(s) from module $oldMod to $ADMIN_MODULE_ID";
            }
        }
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "CERTIFICATE MENU GROUPING\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
