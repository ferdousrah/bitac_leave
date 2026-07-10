<?php
/**
 * Migration: multi-segment leave application
 *
 * Creates two tables:
 *   1. leave_application_segments — each row = one (type + date range) within a parent application
 *   2. leave_segment_history       — audit log: who added / edited / removed which segment, when
 *
 * Backfills: existing leave_applications rows get one synthetic segment each (so legacy data still
 * displays correctly).
 *
 * Usage: open http://localhost/bitac_leave/migrations/add_leave_segments.php once.
 */
require_once(__DIR__ . '/../config/connection.php');

header('Content-Type: text/plain; charset=utf-8');
echo "Migration: leave_application_segments + history\n";
echo "================================================\n";

function tableExists($con, $table) {
    $r = mysqli_query($con, "SHOW TABLES LIKE '" . mysqli_real_escape_string($con, $table) . "'");
    return $r && mysqli_num_rows($r) > 0;
}

// 1. Segments table
if (!tableExists($con, 'leave_application_segments')) {
    $sql = "
    CREATE TABLE leave_application_segments (
        dataID         INT AUTO_INCREMENT PRIMARY KEY,
        applicationID  INT NOT NULL,
        leaveType      INT NOT NULL,
        leaveTypeInTwo INT DEFAULT NULL,
        dateFrom       DATE NOT NULL,
        dateTo         DATE NOT NULL,
        days           INT NOT NULL,
        approvedDays   INT DEFAULT NULL,
        serial         INT NOT NULL DEFAULT 1,
        createdBy      INT DEFAULT NULL,
        createdAt      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updatedAt      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_app    (applicationID),
        INDEX idx_lt     (leaveType),
        INDEX idx_dates  (dateFrom, dateTo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (mysqli_query($con, $sql)) {
        echo "ADD: leave_application_segments\n";
    } else {
        echo "FAIL: leave_application_segments — " . mysqli_error($con) . "\n";
    }
} else {
    echo "SKIP: leave_application_segments (already exists)\n";
}

// 2. History table
if (!tableExists($con, 'leave_segment_history')) {
    $sql = "
    CREATE TABLE leave_segment_history (
        dataID         INT AUTO_INCREMENT PRIMARY KEY,
        applicationID  INT NOT NULL,
        segmentID      INT DEFAULT NULL,
        action         ENUM('created','edited','removed') NOT NULL,
        signatoryLevel INT DEFAULT NULL,
        changedBy      INT DEFAULT NULL,
        changedByName  VARCHAR(200) DEFAULT NULL,
        oldData        TEXT,
        newData        TEXT,
        note           VARCHAR(255) DEFAULT NULL,
        changedAt      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app  (applicationID),
        INDEX idx_seg  (segmentID),
        INDEX idx_who  (changedBy)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (mysqli_query($con, $sql)) {
        echo "ADD: leave_segment_history\n";
    } else {
        echo "FAIL: leave_segment_history — " . mysqli_error($con) . "\n";
    }
} else {
    echo "SKIP: leave_segment_history (already exists)\n";
}

// 3. Backfill: for every leave_applications row, create one segment if it doesn't already exist
$cntQ = mysqli_query($con, "
    SELECT la.dataID, la.leaveType, la.leaveTypeInTwo, la.dateFrom, la.dateTo,
           la.approvedDays, la.submitBy, la.submitDate, la.submitTime
    FROM leave_applications la
    LEFT JOIN leave_application_segments s ON s.applicationID = la.dataID
    WHERE s.dataID IS NULL
");
$backfilled = 0; $errors = 0;
if ($cntQ) {
    while ($row = mysqli_fetch_assoc($cntQ)) {
        $appID    = (int)$row['dataID'];
        $lt       = (int)$row['leaveType'];
        $lt2      = $row['leaveTypeInTwo'] !== null ? (int)$row['leaveTypeInTwo'] : null;
        $df       = $row['dateFrom'];
        $dt       = $row['dateTo'];
        if (!$df || !$dt) continue;
        $days     = max(1, (int)((strtotime($dt) - strtotime($df)) / 86400) + 1);
        $apprDays = $row['approvedDays'] !== null ? (int)$row['approvedDays'] : null;
        $by       = $row['submitBy'] !== null ? (int)$row['submitBy'] : null;
        $createdAt = ($row['submitDate'] && $row['submitTime']) ? ($row['submitDate'] . ' ' . $row['submitTime']) : date('Y-m-d H:i:s');

        $stmt = mysqli_prepare($con,
            "INSERT INTO leave_application_segments
             (applicationID, leaveType, leaveTypeInTwo, dateFrom, dateTo, days, approvedDays, serial, createdBy, createdAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
        if (!$stmt) { $errors++; continue; }
        mysqli_stmt_bind_param($stmt, 'iiissiiis',
            $appID, $lt, $lt2, $df, $dt, $days, $apprDays, $by, $createdAt);
        if (mysqli_stmt_execute($stmt)) {
            $backfilled++;
        } else {
            $errors++;
        }
        mysqli_stmt_close($stmt);
    }
}
echo "BACKFILL: $backfilled segment rows created from existing leave_applications";
if ($errors > 0) echo " (errors: $errors)";
echo "\n";

echo "\nDone.\n";
