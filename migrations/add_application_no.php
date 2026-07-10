<?php
/**
 * Migration: add `application_no` column to leave_applications and backfill existing rows.
 * Format: BITAC/{year}/{dataID}  (e.g., BITAC/2026/15)
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_application_no.php once.
 */
require_once(__DIR__ . '/../config/connection.php');

$log = [];

// ── Step 1: Add column if missing ──────────────────────────────────────
$colExists = false;
$res = mysqli_query($con, "SHOW COLUMNS FROM leave_applications LIKE 'application_no'");
if (mysqli_num_rows($res) > 0) $colExists = true;

if (!$colExists) {
    $sql = "ALTER TABLE leave_applications ADD COLUMN application_no VARCHAR(30) NULL AFTER dataID";
    if (mysqli_query($con, $sql)) {
        $log[] = "ADDED column: application_no VARCHAR(30) NULL";
    } else {
        $log[] = "ERROR adding column: " . mysqli_error($con);
    }
} else {
    $log[] = "SKIP: application_no column already exists";
}

// ── Step 2: Add unique index if missing ────────────────────────────────
$indexExists = false;
$res = mysqli_query($con, "SHOW INDEX FROM leave_applications WHERE Key_name='idx_application_no'");
if (mysqli_num_rows($res) > 0) $indexExists = true;

if (!$indexExists) {
    $sql = "CREATE UNIQUE INDEX idx_application_no ON leave_applications (application_no)";
    if (mysqli_query($con, $sql)) {
        $log[] = "ADDED unique index: idx_application_no";
    } else {
        $log[] = "WARN creating unique index: " . mysqli_error($con) . " (may have duplicates — will fall back to non-unique)";
        // Try non-unique fallback
        $sql2 = "CREATE INDEX idx_application_no ON leave_applications (application_no)";
        if (mysqli_query($con, $sql2)) {
            $log[] = "ADDED non-unique index: idx_application_no";
        }
    }
} else {
    $log[] = "SKIP: idx_application_no already exists";
}

// ── Step 3: Backfill existing rows where application_no is NULL/empty ──
$sql = "SELECT dataID, submitDate FROM leave_applications WHERE application_no IS NULL OR application_no = '' ORDER BY dataID ASC";
$res = mysqli_query($con, $sql);
$updated = 0;
$failed = 0;
$update = mysqli_prepare($con, "UPDATE leave_applications SET application_no = ? WHERE dataID = ?");
while ($row = mysqli_fetch_assoc($res)) {
    $year = ($row['submitDate'] && $row['submitDate'] !== '0000-00-00')
        ? date('Y', strtotime($row['submitDate']))
        : date('Y');
    $appNo = "BITAC/$year/" . (int)$row['dataID'];
    mysqli_stmt_bind_param($update, 'si', $appNo, $row['dataID']);
    if (mysqli_stmt_execute($update)) {
        $updated++;
    } else {
        $failed++;
    }
}
mysqli_stmt_close($update);
$log[] = "BACKFILLED $updated row(s)" . ($failed > 0 ? " ($failed failed)" : "");

// ── Output ─────────────────────────────────────────────────────────────
echo "<h2>Migration: add_application_no</h2><pre style='background:#f3f4f6;padding:20px;font-family:monospace;'>";
foreach ($log as $line) echo $line . "\n";
echo "</pre>";
echo "<p><strong>Done.</strong> Format: <code>BITAC/{year}/{dataID}</code> &middot; example: <code>BITAC/" . date('Y') . "/15</code></p>";
