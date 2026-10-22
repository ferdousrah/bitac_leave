<?php
/**
 * Migration: used-leave totals carried over from the old BITAC software.
 *
 *  - legacy_leave_totals — the old system's figures as of the last sync, one
 *    row per employee × leave bucket × year × source (prev / application /
 *    deduction). Replaced wholesale on every sync; nothing else writes it.
 *  - legacy_leave_sync_log — who synced when, and what came back.
 *  - Admin Panel menu "পুরনো সিস্টেম সিঙ্ক" (views/legacy/sync.php), Super Admin only.
 *
 * Until the first sync the table is empty and every balance stays exactly as
 * it was.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_legacy_leave_totals.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
mysqli_set_charset($con, 'utf8mb4');
$log = [];

$run = function ($sql, $label) use ($con, &$log) {
    if (mysqli_query($con, $sql)) { $log[] = "OK: $label"; return true; }
    $log[] = "ERROR ($label): " . mysqli_error($con);
    return false;
};

// bucket: 1 পূর্ণ গড়, 2 অর্ধ-গড় (actual days), 3 নৈমিত্তিক, 4 বিনা বেতনে,
//         5 ঐচ্ছিক, 6 অ-কর্তনযোগ্য, 10 অসাধারণ — the codes leave_deduction_history uses.
// year:   the calendar year for নৈমিত্তিক/ঐচ্ছিক, 0 for the rest.
$run("CREATE TABLE IF NOT EXISTS legacy_leave_totals (
        employee_id INT NOT NULL,
        bucket      TINYINT NOT NULL,
        year        SMALLINT NOT NULL DEFAULT 0,
        source      ENUM('prev','application','deduction') NOT NULL,
        days        INT NOT NULL,
        PRIMARY KEY (employee_id, bucket, year, source)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'legacy_leave_totals');

$run("CREATE TABLE IF NOT EXISTS legacy_leave_sync_log (
        dataID        INT AUTO_INCREMENT PRIMARY KEY,
        synced_at     DATETIME NOT NULL,
        synced_by     INT NOT NULL,
        ok            TINYINT(1) NOT NULL,
        row_count     INT NOT NULL DEFAULT 0,
        employees     INT NOT NULL DEFAULT 0,
        unknown_employees INT NOT NULL DEFAULT 0,
        latest_change VARCHAR(40) DEFAULT NULL,
        message       TEXT DEFAULT NULL,
        KEY idx_synced_at (synced_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'legacy_leave_sync_log');

// ── Menu: Admin Panel → পুরনো সিস্টেম সিঙ্ক (Super Admin) ─────────────────
$ADMIN_MODULE_ID = 45;
$SLUG = 'legacy-leave-sync';
$stmt = mysqli_prepare($con, "SELECT dataID FROM submodules WHERE slug = ? AND deleted = 0 LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $SLUG);
mysqli_stmt_execute($stmt);
$sub = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if ($sub) {
    $subId = (int)$sub['dataID'];
    $log[] = "SKIP: menu already exists (id $subId)";
} else {
    $hasParent = mysqli_num_rows(mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'")) > 0;
    $ins = mysqli_prepare($con, $hasParent
        ? "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date)
           VALUES ('পুরনো সিস্টেম সিঙ্ক', ?, 0, 'views/legacy/sync.php', ?, 30, 0, NOW())"
        : "INSERT INTO submodules (submodule_name, module_id, page_link, slug, display_order, deleted, create_date)
           VALUES ('পুরনো সিস্টেম সিঙ্ক', ?, 'views/legacy/sync.php', ?, 30, 0, NOW())");
    mysqli_stmt_bind_param($ins, 'is', $ADMIN_MODULE_ID, $SLUG);
    mysqli_stmt_execute($ins);
    $subId = mysqli_insert_id($con);
    mysqli_stmt_close($ins);
    $log[] = "ADDED menu: পুরনো সিস্টেম সিঙ্ক (id $subId)";
}

$chk = mysqli_query($con, "SELECT id FROM group_access_permission WHERE user_group_id = 1 AND submodule_id = $subId LIMIT 1");
if (mysqli_num_rows($chk) === 0) {
    $run("INSERT INTO group_access_permission (user_group_id, module_id, submodule_id) VALUES (1, $ADMIN_MODULE_ID, $subId)",
         'granted to Super Admin');
} else {
    $log[] = "SKIP: Super Admin already has the menu";
}

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "LEGACY LEAVE TOTALS\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
