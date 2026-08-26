<?php
/**
 * Migration: gather the transfer menus under one group in the Admin Panel.
 *
 *   অ্যাডমিন প্যানেল
 *     └ বদলি কার্যক্রম
 *         ├ নতুন বদলির আদেশ  (views/transfer/new.php — new page, was a modal)
 *         ├ বদলি ব্যবস্থাপনা   (views/transfer/manage.php — moved from কনফিগারেশন)
 *         ├ সেকশন বরাদ্দ      (views/transfer/section-assign.php — was a tab on
 *         │                    the employee list)
 *         └ বদলির রিপোর্ট     (views/transfer/report.php — page existed, no menu)
 *
 * বদলি ব্যবস্থাপনা crosses modules, so its group_access_permission rows move with
 * it: the sidebar buckets a submodule by gap.module_id, and a row left naming
 * কনফিগারেশন would render it back there, detached from its parent.
 *
 * The new menus are granted to whichever groups already hold বদলি
 * ব্যবস্থাপনা — the pages carry their own HQ/Super-Admin gate, so a group that
 * can see the list but not initiate simply gets the refusal notice.
 *
 * Needs add_submenu_parent.php (the parent_id column) to have run first.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/group_transfer_menus.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
$log = [];

$ADMIN_MODULE_ID = 45;   // অ্যাডমিন প্যানেল
$PARENT_SLUG     = 'transfer-group';
$PARENT_ORDER    = 11;
$EXISTING_SLUG   = 'employee-transfer';   // বদলি ব্যবস্থাপনা

$hasParent = false;
$res = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($res && mysqli_num_rows($res) > 0) $hasParent = true;

/** Live submodule row by slug, or null. */
function tr_sub($con, $slug) {
    $stmt = mysqli_prepare($con,
        "SELECT dataID, module_id, parent_id FROM submodules WHERE slug = ? AND deleted = 0 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

if (!$hasParent) {
    $log[] = "ABORT: submodules.parent_id missing — run add_submenu_parent.php first";
} else {
    // ── Step 1: the grouping row ──────────────────────────────────────
    $parent = tr_sub($con, $PARENT_SLUG);
    if ($parent) {
        $parentId = (int)$parent['dataID'];
        $log[] = "SKIP: বদলি কার্যক্রম already exists (id $parentId)";
    } else {
        $stmt = mysqli_prepare($con,
            "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date)
             VALUES ('বদলি কার্যক্রম', ?, 0, '#', ?, ?, 0, NOW())");
        mysqli_stmt_bind_param($stmt, 'isi', $ADMIN_MODULE_ID, $PARENT_SLUG, $PARENT_ORDER);
        mysqli_stmt_execute($stmt);
        $parentId = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);
        $log[] = "ADDED submodule: বদলি কার্যক্রম (id $parentId)";
    }

    // ── Step 2: the groups that should see the new menus ──────────────
    $groups = [];
    $existing = tr_sub($con, $EXISTING_SLUG);
    if ($existing) {
        $gq = mysqli_prepare($con,
            "SELECT DISTINCT user_group_id FROM group_access_permission WHERE submodule_id = ?");
        mysqli_stmt_bind_param($gq, 'i', $existing['dataID']);
        mysqli_stmt_execute($gq);
        $gr = mysqli_stmt_get_result($gq);
        while ($g = mysqli_fetch_assoc($gr)) $groups[] = (int)$g['user_group_id'];
        mysqli_stmt_close($gq);
    }
    if (!$groups) $groups = [1];

    // ── Step 3: place the four children ───────────────────────────────
    $children = [
        ['slug' => 'employee-transfer-new',    'name' => 'নতুন বদলির আদেশ', 'link' => 'views/transfer/new.php',    'order' => 1],
        ['slug' => $EXISTING_SLUG,              'name' => 'বদলি ব্যবস্থাপনা', 'link' => 'views/transfer/manage.php',         'order' => 2],
        ['slug' => 'employee-transfer-section', 'name' => 'সেকশন বরাদ্দ',    'link' => 'views/transfer/section-assign.php', 'order' => 3],
        ['slug' => 'employee-transfer-report',  'name' => 'বদলির রিপোর্ট',   'link' => 'views/transfer/report.php',          'order' => 4],
    ];

    foreach ($children as $c) {
        $sub = tr_sub($con, $c['slug']);

        if ($sub) {
            $subId  = (int)$sub['dataID'];
            $oldMod = (int)$sub['module_id'];
            $upd = mysqli_prepare($con,
                "UPDATE submodules SET submodule_name = ?, module_id = ?, parent_id = ?, page_link = ?, display_order = ?
                  WHERE dataID = ?");
            mysqli_stmt_bind_param($upd, 'siisii', $c['name'], $ADMIN_MODULE_ID, $parentId, $c['link'], $c['order'], $subId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $log[] = "MOVED {$c['slug']} (id $subId) → module $ADMIN_MODULE_ID, parent $parentId";

            if ($oldMod !== $ADMIN_MODULE_ID) {
                $perm = mysqli_prepare($con,
                    "UPDATE group_access_permission SET module_id = ? WHERE submodule_id = ? AND module_id <> ?");
                mysqli_stmt_bind_param($perm, 'iii', $ADMIN_MODULE_ID, $subId, $ADMIN_MODULE_ID);
                mysqli_stmt_execute($perm);
                $log[] = "  repointed " . mysqli_stmt_affected_rows($perm) . " permission row(s) from module $oldMod";
                mysqli_stmt_close($perm);
            }
        } else {
            $ins = mysqli_prepare($con,
                "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date)
                 VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
            mysqli_stmt_bind_param($ins, 'siissi', $c['name'], $ADMIN_MODULE_ID, $parentId, $c['link'], $c['slug'], $c['order']);
            mysqli_stmt_execute($ins);
            $subId = mysqli_insert_id($con);
            mysqli_stmt_close($ins);
            $log[] = "ADDED submodule: {$c['name']} (id $subId)";
        }

        // Grant to the same groups that hold বদলি ব্যবস্থাপনা.
        $granted = 0;
        foreach ($groups as $gid) {
            $chk = mysqli_prepare($con,
                "SELECT id FROM group_access_permission WHERE user_group_id = ? AND submodule_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'ii', $gid, $subId);
            mysqli_stmt_execute($chk);
            $have = mysqli_stmt_get_result($chk)->fetch_assoc();
            mysqli_stmt_close($chk);
            if ($have) continue;

            $g = mysqli_prepare($con,
                "INSERT INTO group_access_permission (user_group_id, module_id, submodule_id) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($g, 'iii', $gid, $ADMIN_MODULE_ID, $subId);
            if (mysqli_stmt_execute($g)) $granted++;
            mysqli_stmt_close($g);
        }
        if ($granted) $log[] = "  granted to $granted group(s)";
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "TRANSFER MENU GROUPING\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
