<?php
/**
 * Migration: rebuild joining (যোগদানপত্র) flow
 *
 * Wipes legacy broken data + aligns schema with main leave flow:
 *   - leave_joining_application       → add audit + reject columns
 *   - leave_joining_data_for_approval → add org snapshot + isRead + rejectionReason
 *
 * User-confirmed design (2026-05-22, see [[joining_multisegment_design]]):
 *   - Type 1 = no-op on segments
 *   - Type 2 = truncate proposed segments at joiningDate
 *   - Type 3 = append new proposed segment for the extension
 *
 * Usage: open http://localhost/bitac_leave/migrations/rebuild_joining_flow.php once.
 */
require_once(__DIR__ . '/../config/connection.php');

header('Content-Type: text/plain; charset=utf-8');
echo "Migration: rebuild joining flow\n";
echo "===============================\n\n";

function tableExists($con, $table) {
    $r = mysqli_query($con, "SHOW TABLES LIKE '" . mysqli_real_escape_string($con, $table) . "'");
    return $r && mysqli_num_rows($r) > 0;
}

function colExists($con, $table, $col) {
    $r = mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($con, $col) . "'");
    return $r && mysqli_num_rows($r) > 0;
}

function runOrDie($con, $sql, $label) {
    if (mysqli_query($con, $sql)) {
        echo "OK   : $label\n";
    } else {
        echo "FAIL : $label — " . mysqli_error($con) . "\n";
        exit(1);
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Wipe legacy data
// ──────────────────────────────────────────────────────────────────────────────
echo "[1] Wiping legacy data\n";
if (tableExists($con, 'leave_joining_data_for_approval')) {
    $cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) c FROM leave_joining_data_for_approval"))['c'] ?? 0;
    runOrDie($con, "DELETE FROM leave_joining_data_for_approval", "wipe chain rows (was $cnt)");
    runOrDie($con, "ALTER TABLE leave_joining_data_for_approval AUTO_INCREMENT = 1", "reset chain AI");
}
if (tableExists($con, 'leave_joining_application')) {
    $cnt = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) c FROM leave_joining_application"))['c'] ?? 0;
    runOrDie($con, "DELETE FROM leave_joining_application", "wipe applications (was $cnt)");
    runOrDie($con, "ALTER TABLE leave_joining_application AUTO_INCREMENT = 1", "reset application AI");
}

// ──────────────────────────────────────────────────────────────────────────────
// 2. ALTER leave_joining_application — audit & status columns
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[2] Aligning leave_joining_application columns\n";
$alters = [];
if (!colExists($con, 'leave_joining_application', 'submitBy'))        $alters[] = "ADD COLUMN submitBy INT DEFAULT NULL AFTER applicantID";
if (!colExists($con, 'leave_joining_application', 'submitTime'))      $alters[] = "ADD COLUMN submitTime VARCHAR(100) DEFAULT NULL AFTER submitDate";
if (!colExists($con, 'leave_joining_application', 'approvedBy'))      $alters[] = "ADD COLUMN approvedBy INT DEFAULT NULL AFTER approvedDate";
if (!colExists($con, 'leave_joining_application', 'rejectedBy'))      $alters[] = "ADD COLUMN rejectedBy INT DEFAULT NULL";
if (!colExists($con, 'leave_joining_application', 'rejectedDate'))    $alters[] = "ADD COLUMN rejectedDate VARCHAR(40) DEFAULT NULL";
if (!colExists($con, 'leave_joining_application', 'rejectionReason')) $alters[] = "ADD COLUMN rejectionReason TEXT DEFAULT NULL";
if (!colExists($con, 'leave_joining_application', 'organization_id')) $alters[] = "ADD COLUMN organization_id INT DEFAULT NULL";
if (!colExists($con, 'leave_joining_application', 'lastUpdate'))      $alters[] = "ADD COLUMN lastUpdate VARCHAR(40) DEFAULT NULL";
if (!colExists($con, 'leave_joining_application', 'attachment'))      $alters[] = "ADD COLUMN attachment VARCHAR(255) DEFAULT NULL";

if (!empty($alters)) {
    $sql = "ALTER TABLE leave_joining_application " . implode(', ', $alters);
    runOrDie($con, $sql, "leave_joining_application columns (" . count($alters) . ")");
} else {
    echo "SKIP : leave_joining_application — all columns exist\n";
}

// Index on status if not present
$idxQ = mysqli_query($con, "SHOW INDEX FROM leave_joining_application WHERE Key_name = 'idx_status'");
if ($idxQ && mysqli_num_rows($idxQ) === 0) {
    runOrDie($con, "ALTER TABLE leave_joining_application ADD INDEX idx_status (status)", "add idx_status");
}

// ──────────────────────────────────────────────────────────────────────────────
// 3. ALTER leave_joining_data_for_approval — mirror leave_data_for_approval
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[3] Aligning leave_joining_data_for_approval columns\n";
$alters2 = [];
if (!colExists($con, 'leave_joining_data_for_approval', 'isRead'))           $alters2[] = "ADD COLUMN isRead INT NOT NULL DEFAULT 0";
if (!colExists($con, 'leave_joining_data_for_approval', 'rejectionReason')) $alters2[] = "ADD COLUMN rejectionReason TEXT DEFAULT NULL";
if (!colExists($con, 'leave_joining_data_for_approval', 'organization_id')) $alters2[] = "ADD COLUMN organization_id INT DEFAULT NULL";
if (!colExists($con, 'leave_joining_data_for_approval', 'department_id'))   $alters2[] = "ADD COLUMN department_id INT DEFAULT NULL";
if (!colExists($con, 'leave_joining_data_for_approval', 'section_id'))      $alters2[] = "ADD COLUMN section_id INT DEFAULT NULL";
if (!colExists($con, 'leave_joining_data_for_approval', 'designation_id'))  $alters2[] = "ADD COLUMN designation_id INT DEFAULT NULL";
if (!colExists($con, 'leave_joining_data_for_approval', 'pay_scale'))       $alters2[] = "ADD COLUMN pay_scale VARCHAR(50) DEFAULT NULL";

if (!empty($alters2)) {
    $sql = "ALTER TABLE leave_joining_data_for_approval " . implode(', ', $alters2);
    runOrDie($con, $sql, "leave_joining_data_for_approval columns (" . count($alters2) . ")");
} else {
    echo "SKIP : leave_joining_data_for_approval — all columns exist\n";
}

// Indexes
$idxStmts = [
    'idx_leave_app_j' => "ADD INDEX idx_leave_app_j (leaveApplicationID)",
    'idx_sig_j'       => "ADD INDEX idx_sig_j (signatory)",
    'idx_status_j'    => "ADD INDEX idx_status_j (leaveApplicationID, isApproved)",
];
$addIdx = [];
foreach ($idxStmts as $name => $stmt) {
    $iq = mysqli_query($con, "SHOW INDEX FROM leave_joining_data_for_approval WHERE Key_name = '$name'");
    if ($iq && mysqli_num_rows($iq) === 0) $addIdx[] = $stmt;
}
if (!empty($addIdx)) {
    runOrDie($con, "ALTER TABLE leave_joining_data_for_approval " . implode(', ', $addIdx), "add chain indexes (" . count($addIdx) . ")");
}

echo "\nDone.\n";
