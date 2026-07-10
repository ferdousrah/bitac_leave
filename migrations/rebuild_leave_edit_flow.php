<?php
/**
 * Migration: rebuild leave edit (সংশোধন) flow
 *
 * Wipes legacy single-date data + rebuilds schema to mirror main leave flow:
 *   - leave_edit_data                  → parent edit-request record
 *   - leave_edit_application_segments  → multi-segment storage (kind='requested'/'proposed')
 *   - leave_edit_data_for_approval     → signatory chain (mirror of leave_data_for_approval)
 *   - leave_edit_return_history        → return-to-applicant / return-to-previous audit
 *
 * User-confirmed (2026-05-22):
 *   - Wipe all legacy rows
 *   - Approval chain = same as main leave (buildSignatoryChain)
 *   - On final approve → update leave_applications + replace segments kind='proposed'
 *   - Only triggered on approved leaves (status=1)
 *
 * Usage: open http://localhost/bitac_leave/migrations/rebuild_leave_edit_flow.php once.
 */
require_once(__DIR__ . '/../config/connection.php');

header('Content-Type: text/plain; charset=utf-8');
echo "Migration: rebuild leave edit flow\n";
echo "==================================\n\n";

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
// 1. Wipe legacy data
// ──────────────────────────────────────────────────────────────────────────────
echo "[1] Wiping legacy data\n";
if (tableExists($con, 'leave_edit_data_for_approval')) {
    $oldChain = mysqli_query($con, "SELECT COUNT(*) c FROM leave_edit_data_for_approval");
    $cnt = mysqli_fetch_assoc($oldChain)['c'] ?? 0;
    runOrDie($con, "DROP TABLE leave_edit_data_for_approval", "drop leave_edit_data_for_approval (had $cnt rows)");
}
if (tableExists($con, 'leave_edit_data')) {
    $oldData = mysqli_query($con, "SELECT COUNT(*) c FROM leave_edit_data");
    $cnt = mysqli_fetch_assoc($oldData)['c'] ?? 0;
    runOrDie($con, "DROP TABLE leave_edit_data", "drop leave_edit_data (had $cnt rows)");
}

// ──────────────────────────────────────────────────────────────────────────────
// 2. leave_edit_data — parent record (1 row per edit request)
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[2] Creating leave_edit_data\n";
runOrDie($con, "
CREATE TABLE leave_edit_data (
    dataID              INT AUTO_INCREMENT PRIMARY KEY,
    leaveApplicationID  INT NOT NULL,
    applicantID         INT NOT NULL,
    organization_id     INT DEFAULT NULL,
    adminInitiator      INT NOT NULL DEFAULT 0       COMMENT 'user_list.dataID who initiated the edit (admin-on-behalf)',
    adminNote           TEXT DEFAULT NULL            COMMENT 'reason for correction',
    attachment          VARCHAR(255) DEFAULT NULL,
    status              TINYINT NOT NULL DEFAULT 0   COMMENT '0=pending, 1=approved, 2=rejected, 3=returned',
    submitBy            INT DEFAULT NULL             COMMENT 'user_list.dataID',
    submitDate          VARCHAR(40) DEFAULT NULL,
    submitTime          VARCHAR(100) DEFAULT NULL,
    approvedBy          INT DEFAULT NULL,
    approvedDate        VARCHAR(40) DEFAULT NULL,
    rejectedBy          INT DEFAULT NULL,
    rejectedDate        VARCHAR(40) DEFAULT NULL,
    rejectionReason     TEXT DEFAULT NULL,
    lastUpdate          VARCHAR(40) DEFAULT NULL,
    INDEX idx_app       (leaveApplicationID),
    INDEX idx_applicant (applicantID),
    INDEX idx_status    (status),
    INDEX idx_org       (organization_id),
    CONSTRAINT fk_le_app FOREIGN KEY (leaveApplicationID) REFERENCES leave_applications(dataID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
", "leave_edit_data");

// ──────────────────────────────────────────────────────────────────────────────
// 3. leave_edit_application_segments — multi-segment storage
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[3] Creating leave_edit_application_segments\n";
runOrDie($con, "
CREATE TABLE leave_edit_application_segments (
    dataID          INT AUTO_INCREMENT PRIMARY KEY,
    editRequestID   INT NOT NULL                  COMMENT 'FK → leave_edit_data.dataID',
    kind            ENUM('requested','proposed') NOT NULL DEFAULT 'requested'
                                                  COMMENT 'requested = what applicant/admin asked for; proposed = what signatory finally set',
    leaveType       INT NOT NULL,
    leaveTypeInTwo  INT DEFAULT NULL,
    dateFrom        DATE NOT NULL,
    dateTo          DATE NOT NULL,
    days            INT NOT NULL,
    approvedDays    INT DEFAULT NULL,
    serial          INT NOT NULL DEFAULT 1,
    createdBy       INT DEFAULT NULL,
    createdAt       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_req   (editRequestID),
    INDEX idx_kind  (kind),
    INDEX idx_dates (dateFrom, dateTo),
    CONSTRAINT fk_les_req FOREIGN KEY (editRequestID) REFERENCES leave_edit_data(dataID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
", "leave_edit_application_segments");

// ──────────────────────────────────────────────────────────────────────────────
// 4. leave_edit_data_for_approval — signatory chain (mirror of leave_data_for_approval)
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[4] Creating leave_edit_data_for_approval\n";
runOrDie($con, "
CREATE TABLE leave_edit_data_for_approval (
    dataID            INT AUTO_INCREMENT PRIMARY KEY,
    editRequestID     INT NOT NULL                  COMMENT 'FK → leave_edit_data.dataID',
    signatory         INT DEFAULT NULL              COMMENT 'employee_list.id (not user_list)',
    isSupervisor      INT NOT NULL DEFAULT 0,
    isSentbyAdmin     INT NOT NULL DEFAULT 0,
    prevSignatory     INT DEFAULT NULL,
    isApproved        INT DEFAULT 0                 COMMENT '0=pending, 1=approved, 2=rejected',
    approvedDate      VARCHAR(40) DEFAULT NULL,
    serial            INT NOT NULL DEFAULT 0,
    approvedDays      INT NOT NULL DEFAULT 0,
    note              TEXT DEFAULT NULL,
    isForwarded       INT NOT NULL DEFAULT 0,
    signature         LONGBLOB DEFAULT NULL,
    isDG              INT NOT NULL DEFAULT 0,
    isRead            INT NOT NULL DEFAULT 0,
    rejectionReason   TEXT DEFAULT NULL,
    organization_id   INT DEFAULT NULL,
    department_id     INT DEFAULT NULL,
    section_id        INT DEFAULT NULL,
    designation_id    INT DEFAULT NULL,
    pay_scale         VARCHAR(50) DEFAULT NULL,
    INDEX idx_req       (editRequestID),
    INDEX idx_signatory (signatory),
    INDEX idx_status    (editRequestID, isApproved),
    CONSTRAINT fk_leda_req  FOREIGN KEY (editRequestID) REFERENCES leave_edit_data(dataID)   ON DELETE CASCADE   ON UPDATE CASCADE,
    CONSTRAINT fk_leda_sig  FOREIGN KEY (signatory)     REFERENCES employee_list(id)         ON DELETE NO ACTION ON UPDATE CASCADE,
    CONSTRAINT fk_leda_prev FOREIGN KEY (prevSignatory) REFERENCES employee_list(id)         ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
", "leave_edit_data_for_approval");

// ──────────────────────────────────────────────────────────────────────────────
// 5. leave_edit_return_history — return audit trail
// ──────────────────────────────────────────────────────────────────────────────
echo "\n[5] Creating leave_edit_return_history\n";
if (tableExists($con, 'leave_edit_return_history')) {
    echo "SKIP : leave_edit_return_history (already exists)\n";
} else {
    runOrDie($con, "
    CREATE TABLE leave_edit_return_history (
        dataID          INT AUTO_INCREMENT PRIMARY KEY,
        editRequestID   INT NOT NULL,
        returnedBy      INT NOT NULL,
        returnedByName  VARCHAR(255) DEFAULT NULL,
        returnedByTitle VARCHAR(255) DEFAULT NULL,
        returnedTo      INT DEFAULT 0,
        returnedToName  VARCHAR(255) DEFAULT NULL,
        returnType      ENUM('to_applicant','to_previous_signatory','to_admin') NOT NULL,
        note            TEXT NOT NULL,
        createdAt       DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_req   (editRequestID),
        CONSTRAINT fk_lerh_req FOREIGN KEY (editRequestID) REFERENCES leave_edit_data(dataID) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ", "leave_edit_return_history");
}

echo "\nDone.\n";
