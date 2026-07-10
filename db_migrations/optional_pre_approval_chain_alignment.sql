-- ============================================================================
-- Migration: Align optional_leave_pre_approval flow with regular leave chain
-- Date: 2026-07-06
-- ----------------------------------------------------------------------------
-- Adds parity columns on optional_leave_pre_approval_signatory so the
-- supervisor → center admin (forward) → signatory chain pattern used by
-- leave_data_for_approval works identically here.
--
-- Also truncates existing rows — per project decision, existing pre-approval
-- data is discarded (dev-stage) and re-enters via the new flow.
-- ============================================================================

-- 1. Parity columns on optional_leave_pre_approval_signatory
ALTER TABLE optional_leave_pre_approval_signatory
    ADD COLUMN IF NOT EXISTS isSentbyAdmin TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Set to 1 when center admin has forwarded to the signatory chain (gates non-supervisor rows).',
    ADD COLUMN IF NOT EXISTS isForwarded TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Marks the specific bridge row (if any) inserted by admin on forward.',
    ADD COLUMN IF NOT EXISTS isDG TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = DG-level appended signatory (mirrors leave_data_for_approval.isDG).';

-- 2. Admin-forward columns on parent
ALTER TABLE optional_leave_pre_approval
    ADD COLUMN IF NOT EXISTS admin_note TEXT DEFAULT NULL
        COMMENT 'Center admin note captured at forward step.',
    ADD COLUMN IF NOT EXISTS admin_initiator INT DEFAULT NULL
        COMMENT 'user_list.dataID of the admin who forwarded.',
    ADD COLUMN IF NOT EXISTS admin_forward_date DATETIME DEFAULT NULL
        COMMENT 'Timestamp of the forward-to-approval action.',
    ADD COLUMN IF NOT EXISTS approved_days DECIMAL(4,1) DEFAULT NULL
        COMMENT 'Days the admin approved (may differ from requested_days).';

-- 3. Fresh start — clear existing rows
-- FK from signatory → parent means we disable checks briefly.
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE optional_leave_pre_approval_signatory;
TRUNCATE TABLE optional_leave_pre_approval;
SET FOREIGN_KEY_CHECKS = 1;
