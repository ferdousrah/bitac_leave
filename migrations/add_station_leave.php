<?php
/**
 * Migration: station leave (স্টেশন লিভ) on the regular leave application.
 *
 *  - geo_divisions / geo_districts / geo_thanas, seeded from
 *    migrations/data/bd_geo.php — 8 divisions, 64 districts and 594 upazilas and
 *    metropolitan thanas. The picker cascades division → district → thana.
 *  - leave_applications.stationLeave — 1 when the applicant declared they will
 *    travel beyond 25 km of the workplace during the leave.
 *  - leave_station_addresses — the addresses they will stay at, in order.
 *
 * Each address row keeps the Bangla names it was saved with, not just the ids.
 * The letter is the applicant's signed declaration: correcting a place name in
 * the geography tables later must not rewrite what an old letter says.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_station_leave.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
mysqli_set_charset($con, 'utf8mb4');
$log = [];

$run = function ($sql, $label) use ($con, &$log) {
    if (mysqli_query($con, $sql)) { $log[] = "OK: $label"; return true; }
    $log[] = "ERROR ($label): " . mysqli_error($con);
    return false;
};

// ── Step 1: geography tables ──────────────────────────────────────────
$run("CREATE TABLE IF NOT EXISTS geo_divisions (
        id       INT NOT NULL PRIMARY KEY,
        name_en  VARCHAR(80)  NOT NULL,
        name_bn  VARCHAR(80)  NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'geo_divisions');

$run("CREATE TABLE IF NOT EXISTS geo_districts (
        id          INT NOT NULL PRIMARY KEY,
        division_id INT NOT NULL,
        name_en     VARCHAR(80) NOT NULL,
        name_bn     VARCHAR(80) NOT NULL,
        KEY idx_division (division_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'geo_districts');

$run("CREATE TABLE IF NOT EXISTS geo_thanas (
        id          INT NOT NULL PRIMARY KEY,
        district_id INT NOT NULL,
        name_en     VARCHAR(80) NOT NULL,
        name_bn     VARCHAR(80) NOT NULL,
        kind        ENUM('upazila','thana') NOT NULL DEFAULT 'upazila',
        KEY idx_district (district_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'geo_thanas');

// ── Step 2: seed, only into empty tables ──────────────────────────────
// Re-running must never duplicate or silently overwrite rows someone has
// corrected by hand; to reseed, empty the tables first.
$geo = require __DIR__ . '/data/bd_geo.php';

$seed = function ($table, $cols, $rows, $types) use ($con, &$log) {
    $have = (int)mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM $table"))[0];
    if ($have > 0) { $log[] = "SKIP: $table already holds $have row(s)"; return; }

    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $stmt = mysqli_prepare($con, "INSERT INTO $table (" . implode(',', $cols) . ") VALUES ($ph)");
    mysqli_begin_transaction($con);
    $n = 0;
    foreach ($rows as $r) {
        mysqli_stmt_bind_param($stmt, $types, ...$r);
        if (mysqli_stmt_execute($stmt)) $n++;
    }
    mysqli_commit($con);
    mysqli_stmt_close($stmt);
    $log[] = "SEEDED: $table — $n row(s)";
};

$seed('geo_divisions', ['id', 'name_en', 'name_bn'],                        $geo['divisions'], 'iss');
$seed('geo_districts', ['id', 'division_id', 'name_en', 'name_bn'],         $geo['districts'], 'iiss');
$seed('geo_thanas',    ['id', 'district_id', 'name_en', 'name_bn', 'kind'], $geo['thanas'],    'iisss');

// ── Step 3: the application flag ──────────────────────────────────────
$c = mysqli_query($con, "SHOW COLUMNS FROM leave_applications LIKE 'stationLeave'");
if ($c && mysqli_num_rows($c) > 0) {
    $log[] = "SKIP: leave_applications.stationLeave already exists";
} else {
    $run("ALTER TABLE leave_applications ADD COLUMN stationLeave TINYINT NOT NULL DEFAULT 0",
         'leave_applications.stationLeave');
}

// ── Step 4: the addresses ─────────────────────────────────────────────
$run("CREATE TABLE IF NOT EXISTS leave_station_addresses (
        dataID        INT AUTO_INCREMENT PRIMARY KEY,
        applicationID INT NOT NULL,
        serial        INT NOT NULL DEFAULT 1,
        division_id   INT NOT NULL,
        district_id   INT NOT NULL,
        thana_id      INT NOT NULL,
        division_bn   VARCHAR(80)  NOT NULL,
        district_bn   VARCHAR(80)  NOT NULL,
        thana_bn      VARCHAR(80)  NOT NULL,
        detail        VARCHAR(255) NULL,
        createdAt     DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_application (applicationID)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'leave_station_addresses');

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "STATION LEAVE MIGRATION\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
