<?php
/**
 * Migration: Employee lifecycle (probationary/permanent) + transfer history
 *
 * User-confirmed design (2026-06-08, see [[employee_lifecycle_design]]):
 *   - শিক্ষানবিশ (probationary) entry type with auto P-YYYY-NNN temp IDs
 *   - Permanent employees (existing or post-promotion)
 *   - Full transfer history with from/to org + dates + order details
 *   - Existing employees treated as 'permanent', backfilled with initial posting history
 *
 * Usage: open http://localhost/bitac_leave/migrations/employee_lifecycle.php once.
 */
require_once(__DIR__ . '/../config/connection.php');

header('Content-Type: text/plain; charset=utf-8');
echo "Migration: employee lifecycle + transfer history\n";
echo "================================================\n\n";

function colExists($con, $table, $col) {
    $r = mysqli_query($con, "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($con, $col) . "'");
    return $r && mysqli_num_rows($r) > 0;
}
function tableExists($con, $table) {
    $r = mysqli_query($con, "SHOW TABLES LIKE '" . mysqli_real_escape_string($con, $table) . "'");
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
// 1. ALTER employee_list — add lifecycle columns
// ──────────────────────────────────────────────────────────────────────────────
echo "[1] Aligning employee_list columns\n";
$alters = [];
if (!colExists($con, 'employee_list', 'employment_type')) {
    $alters[] = "ADD COLUMN employment_type ENUM('probationary','permanent') NOT NULL DEFAULT 'permanent' AFTER employment_status";
}
if (!colExists($con, 'employee_list', 'probation_start_date')) {
    $alters[] = "ADD COLUMN probation_start_date DATE NULL AFTER employment_type";
}
if (!colExists($con, 'employee_list', 'permanent_from_date')) {
    $alters[] = "ADD COLUMN permanent_from_date DATE NULL AFTER probation_start_date";
}
if (!colExists($con, 'employee_list', 'permanent_emp_id')) {
    $alters[] = "ADD COLUMN permanent_emp_id VARCHAR(40) NULL AFTER permanent_from_date";
}

if (!empty($alters)) {
    runOrDie($con, "ALTER TABLE employee_list " . implode(', ', $alters), "employee_list columns (" . count($alters) . ")");
} else {
    echo "SKIP : employee_list — all columns already exist\n";
}

// Index on employment_type
$idxQ = mysqli_query($con, "SHOW INDEX FROM employee_list WHERE Key_name = 'idx_emp_type'");
if ($idxQ && mysqli_num_rows($idxQ) === 0) {
    runOrDie($con, "ALTER TABLE employee_list ADD INDEX idx_emp_type (employment_type)", "add idx_emp_type");
}

// ──────────────────────────────────────────────────────────────────────────────
// 2. Backfill existing rows → 'permanent', permanent_from_date = joining_date
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[2] Backfilling existing employees as permanent\n";
$backfillQ = mysqli_query($con,
    "UPDATE employee_list
     SET employment_type = 'permanent',
         permanent_from_date = COALESCE(permanent_from_date, joining_date)
     WHERE (employment_type IS NULL OR employment_type = '')
        OR (employment_type = 'permanent' AND permanent_from_date IS NULL)");
if ($backfillQ) {
    echo "OK   : backfilled " . mysqli_affected_rows($con) . " rows\n";
} else {
    echo "FAIL : backfill — " . mysqli_error($con) . "\n";
}

// ──────────────────────────────────────────────────────────────────────────────
// 3. CREATE employee_transfer_history
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[3] Creating employee_transfer_history table\n";
if (tableExists($con, 'employee_transfer_history')) {
    echo "SKIP : employee_transfer_history already exists\n";
} else {
    runOrDie($con, "
    CREATE TABLE employee_transfer_history (
        dataID               INT AUTO_INCREMENT PRIMARY KEY,
        employee_ref_id      INT NOT NULL              COMMENT 'FK → employee_list.id',
        from_organization_id INT DEFAULT NULL          COMMENT 'NULL on initial posting',
        to_organization_id   INT NOT NULL,
        transfer_date        DATE NOT NULL,
        effective_to         DATE DEFAULT NULL         COMMENT 'set when next transfer happens',
        order_number         VARCHAR(50) DEFAULT NULL,
        order_date           DATE DEFAULT NULL,
        reason               TEXT DEFAULT NULL,
        attachment           VARCHAR(255) DEFAULT NULL,
        createdBy            INT DEFAULT NULL,
        createdAt            DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_emp  (employee_ref_id),
        INDEX idx_org  (to_organization_id),
        INDEX idx_date (transfer_date),
        CONSTRAINT fk_eth_emp FOREIGN KEY (employee_ref_id) REFERENCES employee_list(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_eth_to_org FOREIGN KEY (to_organization_id) REFERENCES organization(id) ON DELETE NO ACTION ON UPDATE CASCADE,
        CONSTRAINT fk_eth_from_org FOREIGN KEY (from_organization_id) REFERENCES organization(id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ", "employee_transfer_history");
}

// ──────────────────────────────────────────────────────────────────────────────
// 4. Backfill initial posting history for every existing employee
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[4] Backfilling initial posting history\n";
$existQ = mysqli_query($con, "SELECT COUNT(*) c FROM employee_transfer_history");
$existCount = ($existQ && $r = mysqli_fetch_assoc($existQ)) ? (int)$r['c'] : 0;

if ($existCount > 0) {
    echo "SKIP : transfer history already has $existCount row(s) — leaving as-is to avoid duplicates\n";
} else {
    $bfQ = mysqli_query($con, "
        INSERT INTO employee_transfer_history
            (employee_ref_id, from_organization_id, to_organization_id, transfer_date, reason, createdAt)
        SELECT id, NULL, organization_id, COALESCE(joining_date, CURDATE()), 'Initial posting (backfilled)', NOW()
        FROM employee_list
        WHERE organization_id IS NOT NULL AND organization_id > 0
    ");
    if ($bfQ) {
        echo "OK   : inserted " . mysqli_affected_rows($con) . " initial posting rows\n";
    } else {
        echo "FAIL : history backfill — " . mysqli_error($con) . "\n";
    }
}

echo "\nDone.\n";
