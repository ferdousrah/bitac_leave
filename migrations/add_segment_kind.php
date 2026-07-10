<?php
/**
 * Migration: split leave_application_segments into requested vs proposed.
 *
 *  - Add `kind` column ENUM('requested','proposed').
 *  - Existing rows = 'requested' (employee's original ask, frozen).
 *  - For each application, COPY each requested row as a 'proposed' row
 *    (initial proposal mirrors the request; signatories will edit).
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_segment_kind.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
$log = [];

// ── Step 1: Add `kind` column if missing ──────────────────────────────
$colExists = false;
$res = mysqli_query($con, "SHOW COLUMNS FROM leave_application_segments LIKE 'kind'");
if ($res && mysqli_num_rows($res) > 0) $colExists = true;

if (!$colExists) {
    $sql = "ALTER TABLE leave_application_segments
            ADD COLUMN kind ENUM('requested','proposed') NOT NULL DEFAULT 'requested' AFTER applicationID";
    if (mysqli_query($con, $sql)) {
        $log[] = "ADDED column: kind ENUM('requested','proposed')";
    } else {
        $log[] = "ERROR adding column: " . mysqli_error($con);
    }
} else {
    $log[] = "SKIP: kind column already exists";
}

// ── Step 2: Mark all existing rows as 'requested' (idempotent) ─────────
$res = mysqli_query($con, "UPDATE leave_application_segments SET kind='requested' WHERE kind IS NULL OR kind=''");
$log[] = "Backfilled kind='requested' on " . mysqli_affected_rows($con) . " row(s)";

// ── Step 3: For each application, ensure a 'proposed' copy exists ──────
// Only insert proposed rows where they don't already exist for the application.
$res = mysqli_query($con, "
    SELECT applicationID, leaveType, dateFrom, dateTo, days, serial, createdBy, createdAt
    FROM leave_application_segments
    WHERE kind = 'requested'
    ORDER BY applicationID, serial
");

if (!$res) {
    $log[] = "ERROR reading requested rows: " . mysqli_error($con);
} else {
    $insertStmt = mysqli_prepare($con,
        "INSERT INTO leave_application_segments
         (applicationID, kind, leaveType, dateFrom, dateTo, days, serial, createdBy, createdAt)
         VALUES (?, 'proposed', ?, ?, ?, ?, ?, ?, ?)");

    // Group requested rows by applicationID so we can check for proposed existence first
    $byApp = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $byApp[$r['applicationID']][] = $r;
    }

    $copiedCount = 0;
    $skippedCount = 0;
    foreach ($byApp as $appId => $rows) {
        // Check if proposed rows already exist for this application
        $check = mysqli_prepare($con, "SELECT COUNT(*) c FROM leave_application_segments WHERE applicationID = ? AND kind = 'proposed'");
        mysqli_stmt_bind_param($check, 'i', $appId);
        mysqli_stmt_execute($check);
        $pCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($check))['c'] ?? 0);
        mysqli_stmt_close($check);

        if ($pCount > 0) {
            $skippedCount++;
            continue;
        }

        foreach ($rows as $r) {
            mysqli_stmt_bind_param($insertStmt, 'iissiiis',
                $r['applicationID'], $r['leaveType'], $r['dateFrom'], $r['dateTo'],
                $r['days'], $r['serial'], $r['createdBy'], $r['createdAt']);
            mysqli_stmt_execute($insertStmt);
            $copiedCount++;
        }
    }
    mysqli_stmt_close($insertStmt);
    $log[] = "Created proposed copies: $copiedCount row(s) across new applications";
    $log[] = "Skipped: $skippedCount application(s) that already had proposed rows";
}

// ── Output ─────────────────────────────────────────────────────────────
echo "<h2>Migration: add_segment_kind</h2><pre style='background:#f3f4f6;padding:20px;font-family:monospace;'>";
foreach ($log as $line) echo $line . "\n";
echo "</pre>";
echo "<p><strong>Done.</strong> Now <code>leave_application_segments</code> has separate <code>kind='requested'</code> (frozen) and <code>kind='proposed'</code> (mutable) rows.</p>";
