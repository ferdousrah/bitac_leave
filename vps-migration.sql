-- ──────────────────────────────────────────────────────────────────
-- VPS migration — run on your VPS bitac_leave DB once.
-- Includes everything added in the recent multi-role + audit-log work.
-- Safe to re-run: every CREATE uses IF NOT EXISTS, ALTERs are guarded.
-- ──────────────────────────────────────────────────────────────────

-- 1. Multi-role assignment table
CREATE TABLE IF NOT EXISTS user_group_assignment (
    dataID INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_group (user_id, group_id),
    INDEX idx_user (user_id),
    INDEX idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Backfill from existing user_list.user_group_id (idempotent — only inserts if missing)
INSERT IGNORE INTO user_group_assignment (user_id, group_id, is_default)
SELECT dataID, user_group_id, 1 FROM user_list
WHERE user_group_id IS NOT NULL;

-- 3. Tenure columns on user_group_assignment (guarded — add only if missing)
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_group_assignment' AND COLUMN_NAME = 'effective_from');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE user_group_assignment
        ADD COLUMN effective_from DATETIME DEFAULT NULL AFTER assigned_date,
        ADD COLUMN effective_to DATETIME DEFAULT NULL AFTER effective_from,
        ADD COLUMN proposal_id INT DEFAULT NULL AFTER effective_to,
        ADD COLUMN attachment VARCHAR(255) DEFAULT NULL AFTER proposal_id,
        ADD INDEX idx_proposal (proposal_id),
        ADD INDEX idx_effective (effective_from, effective_to)',
    'SELECT "tenure columns already exist"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE user_group_assignment SET effective_from = assigned_date WHERE effective_from IS NULL;

-- 4. Regional role types (idempotent inserts by id)
INSERT IGNORE INTO user_group (id, group_name, display_order, deleted) VALUES
    (7, 'Regional Super Admin', 7, 0),
    (8, 'Regional Op. Admin',   8, 0);

-- Soft-delete legacy Center Admin if not already done
UPDATE user_group SET deleted = 1 WHERE id = 2 AND deleted = 0;

-- 5. Role assignment workflow
CREATE TABLE IF NOT EXISTS role_assignment_proposal (
    dataID INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    role_id INT NOT NULL,
    employee_id INT NOT NULL,
    target_user_id INT DEFAULT NULL,
    proposed_username VARCHAR(200) NULL,
    proposed_password VARCHAR(255) NULL,
    proposed_full_name VARCHAR(200) NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    note TEXT,
    proposed_by INT NOT NULL,
    approver_id INT DEFAULT NULL,
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0=pending, 1=approved, 2=rejected',
    approver_note TEXT,
    decided_at DATETIME DEFAULT NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_org_role (organization_id, role_id),
    INDEX idx_approver (approver_id),
    INDEX idx_target_user (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_assignment_log (
    dataID INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT DEFAULT NULL,
    action VARCHAR(40) NOT NULL,
    actor_user_id INT NOT NULL,
    actor_name VARCHAR(200),
    target_user_id INT DEFAULT NULL,
    target_employee_id INT DEFAULT NULL,
    organization_id INT DEFAULT NULL,
    role_id INT DEFAULT NULL,
    note TEXT,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proposal (proposal_id),
    INDEX idx_actor (actor_user_id),
    INDEX idx_org_role (organization_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_approver_config (
    dataID INT AUTO_INCREMENT PRIMARY KEY,
    approver_user_id INT NOT NULL,
    updated_by INT,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Universal audit log
CREATE TABLE IF NOT EXISTS audit_log (
    dataID INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(80) NOT NULL,
    actor_user_id INT DEFAULT NULL,
    actor_name VARCHAR(255) DEFAULT NULL,
    actor_username VARCHAR(255) DEFAULT NULL,
    target_type VARCHAR(60) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    organization_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    request_url VARCHAR(500) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_actor (actor_user_id),
    INDEX idx_target (target_type, target_id),
    INDEX idx_created (createdAt),
    INDEX idx_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Leave-feature support tables added during the return-leave / admin-note flow
CREATE TABLE IF NOT EXISTS leave_return_history (
    dataID INT AUTO_INCREMENT PRIMARY KEY,
    leaveApplicationID INT NOT NULL,
    returnedBy INT NOT NULL,
    returnedByName VARCHAR(255),
    returnedByTitle VARCHAR(255),
    returnedTo INT DEFAULT 0,
    returnedToName VARCHAR(255),
    returnType ENUM('to_applicant','to_previous_signatory','to_admin') NOT NULL,
    note TEXT NOT NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app (leaveApplicationID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_admin_note_history (
    dataID INT AUTO_INCREMENT PRIMARY KEY,
    leaveApplicationID INT NOT NULL,
    adminInitiator INT NOT NULL,
    adminInitiatorName VARCHAR(255),
    adminInitiatorTitle VARCHAR(255),
    note TEXT NOT NULL,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app (leaveApplicationID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
