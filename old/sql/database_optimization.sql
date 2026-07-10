-- =====================================================
-- BITAC Leave Management System
-- Database Optimization & Foreign Key Relations
-- =====================================================
--
-- IMPORTANT: BACKUP YOUR DATABASE BEFORE RUNNING THIS!
-- Run: mysqldump -u root bitac_leave_dev > backup_$(date +%Y%m%d).sql
--
-- This script adds:
-- 1. Foreign key constraints for referential integrity
-- 2. Indexes for performance optimization
-- 3. Data integrity checks
-- =====================================================

USE bitac_leave_dev;

-- Disable foreign key checks temporarily for modifications
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- SECTION 1: ADD MISSING PRIMARY KEYS
-- =====================================================

-- Ensure all tables have proper primary keys
ALTER TABLE `employee_list`
    MODIFY COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `leave_applications`
    MODIFY COLUMN `dataID` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `leave_types`
    MODIFY COLUMN `leaveID` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `organization`
    MODIFY COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `sections`
    MODIFY COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `job_title`
    MODIFY COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `user_list`
    MODIFY COLUMN `dataID` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `user_group`
    MODIFY COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `grade`
    MODIFY COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE `salary_group`
    MODIFY COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY;

-- =====================================================
-- SECTION 2: CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Employee List Indexes
ALTER TABLE `employee_list`
    ADD INDEX `idx_organization` (`organization_id`),
    ADD INDEX `idx_section` (`section_id`),
    ADD INDEX `idx_designation` (`designation`),
    ADD INDEX `idx_employment_status` (`employment_status`),
    ADD INDEX `idx_employee_id` (`employee_id`),
    ADD INDEX `idx_pay_scale` (`pay_scale`),
    ADD INDEX `idx_salary_group` (`salary_group_id`),
    ADD UNIQUE INDEX `idx_employee_id_unique` (`employee_id`);

-- Leave Applications Indexes
ALTER TABLE `leave_applications`
    ADD INDEX `idx_applicant` (`applicantID`),
    ADD INDEX `idx_status` (`status`),
    ADD INDEX `idx_leave_type` (`leaveType`),
    ADD INDEX `idx_organization` (`organization_id`),
    ADD INDEX `idx_date_range` (`dateFrom`, `dateTo`),
    ADD INDEX `idx_applicant_status` (`applicantID`, `status`),
    ADD INDEX `idx_submit_date` (`submitDate`);

-- Leave Approval Data Indexes
ALTER TABLE `leave_data_for_approval`
    ADD INDEX `idx_leave_app` (`leaveApplicationID`),
    ADD INDEX `idx_signatory` (`signatory`),
    ADD INDEX `idx_is_approved` (`isApproved`),
    ADD INDEX `idx_leave_status` (`leaveApplicationID`, `isApproved`);

-- Leave Deduction History Indexes
ALTER TABLE `leave_deduction_history`
    ADD INDEX `idx_employee` (`employeeID`),
    ADD INDEX `idx_leave_type` (`leaveID`),
    ADD INDEX `idx_approved` (`isApproved`),
    ADD INDEX `idx_create_date` (`createDate`);

-- Previous Leave Deduction Indexes
ALTER TABLE `previous_leave_deduction`
    ADD INDEX `idx_employee` (`employeeID`),
    ADD INDEX `idx_approved` (`isApproved`);

-- Salary Increment Indexes
ALTER TABLE `yearly_salary_increment`
    ADD INDEX `idx_employee` (`employeeID`),
    ADD INDEX `idx_year` (`incrementYear`),
    ADD INDEX `idx_organization` (`organization_id`),
    ADD INDEX `idx_section` (`section_id`);

-- User List Indexes
ALTER TABLE `user_list`
    ADD INDEX `idx_employee_id` (`employee_id`),
    ADD INDEX `idx_user_group` (`user_group_id`),
    ADD UNIQUE INDEX `idx_user_id_unique` (`user_id`);

-- Leave Approval Signatory Indexes
ALTER TABLE `leave_approval_signatory`
    ADD INDEX `idx_organization` (`organization_id`),
    ADD INDEX `idx_designation` (`designationID`),
    ADD INDEX `idx_approval_order` (`organization_id`, `approvalSL`);

-- Notification Indexes
ALTER TABLE `notification`
    ADD INDEX `idx_user` (`userID`),
    ADD INDEX `idx_datetime` (`dateTime`);

-- =====================================================
-- SECTION 3: ADD FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Employee List Foreign Keys
ALTER TABLE `employee_list`
    ADD CONSTRAINT `fk_employee_designation`
        FOREIGN KEY (`designation`)
        REFERENCES `job_title`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_employee_organization`
        FOREIGN KEY (`organization_id`)
        REFERENCES `organization`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_employee_section`
        FOREIGN KEY (`section_id`)
        REFERENCES `sections`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_employee_pay_scale`
        FOREIGN KEY (`pay_scale`)
        REFERENCES `grade`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_employee_salary_group`
        FOREIGN KEY (`salary_group_id`)
        REFERENCES `salary_group`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- Leave Applications Foreign Keys
ALTER TABLE `leave_applications`
    ADD CONSTRAINT `fk_leave_applicant`
        FOREIGN KEY (`applicantID`)
        REFERENCES `employee_list`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_leave_type`
        FOREIGN KEY (`leaveType`)
        REFERENCES `leave_types`(`leaveID`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_leave_organization`
        FOREIGN KEY (`organization_id`)
        REFERENCES `organization`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- Leave Approval Data Foreign Keys
ALTER TABLE `leave_data_for_approval`
    ADD CONSTRAINT `fk_approval_leave_app`
        FOREIGN KEY (`leaveApplicationID`)
        REFERENCES `leave_applications`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_approval_signatory`
        FOREIGN KEY (`signatory`)
        REFERENCES `employee_list`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_approval_prev_signatory`
        FOREIGN KEY (`prevSignatory`)
        REFERENCES `employee_list`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Leave Joining Application Foreign Keys
ALTER TABLE `leave_joining_application`
    ADD CONSTRAINT `fk_joining_leave_app`
        FOREIGN KEY (`leaveApplicationID`)
        REFERENCES `leave_applications`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Leave Deduction History Foreign Keys
ALTER TABLE `leave_deduction_history`
    ADD CONSTRAINT `fk_deduction_employee`
        FOREIGN KEY (`employeeID`)
        REFERENCES `employee_list`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_deduction_leave_type`
        FOREIGN KEY (`leaveID`)
        REFERENCES `leave_types`(`leaveID`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- Previous Leave Deduction Foreign Keys
ALTER TABLE `previous_leave_deduction`
    ADD CONSTRAINT `fk_prev_deduction_employee`
        FOREIGN KEY (`employeeID`)
        REFERENCES `employee_list`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Leave Approval Signatory Foreign Keys
ALTER TABLE `leave_approval_signatory`
    ADD CONSTRAINT `fk_signatory_organization`
        FOREIGN KEY (`organization_id`)
        REFERENCES `organization`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_signatory_designation`
        FOREIGN KEY (`designationID`)
        REFERENCES `job_title`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- User List Foreign Keys
ALTER TABLE `user_list`
    ADD CONSTRAINT `fk_user_group`
        FOREIGN KEY (`user_group_id`)
        REFERENCES `user_group`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- Group Access Permission Foreign Keys
ALTER TABLE `group_access_permission`
    ADD CONSTRAINT `fk_permission_user_group`
        FOREIGN KEY (`user_group_id`)
        REFERENCES `user_group`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_permission_module`
        FOREIGN KEY (`module_id`)
        REFERENCES `modules`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_permission_submodule`
        FOREIGN KEY (`submodule_id`)
        REFERENCES `submodules`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Yearly Salary Increment Foreign Keys
ALTER TABLE `yearly_salary_increment`
    ADD CONSTRAINT `fk_increment_employee`
        FOREIGN KEY (`employeeID`)
        REFERENCES `employee_list`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_increment_designation`
        FOREIGN KEY (`designation`)
        REFERENCES `job_title`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_increment_section`
        FOREIGN KEY (`section_id`)
        REFERENCES `sections`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_increment_organization`
        FOREIGN KEY (`organization_id`)
        REFERENCES `organization`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_increment_salary_group`
        FOREIGN KEY (`salary_group_id`)
        REFERENCES `salary_group`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- Notification Foreign Keys
ALTER TABLE `notification`
    ADD CONSTRAINT `fk_notification_user`
        FOREIGN KEY (`userID`)
        REFERENCES `user_list`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- =====================================================
-- SECTION 4: ADD TIMESTAMP COLUMNS FOR AUDIT TRAIL
-- =====================================================

-- Add created_at and updated_at to major tables if not exists
ALTER TABLE `employee_list`
    ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `leave_applications`
    ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `user_list`
    ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- =====================================================
-- SECTION 5: DATA INTEGRITY CHECKS
-- =====================================================

-- Check for orphaned records before enabling FK constraints
-- (These are SELECT queries to identify issues - run them first!)

-- Find employees with invalid organization_id
SELECT id, employee_name, organization_id
FROM employee_list
WHERE organization_id NOT IN (SELECT id FROM organization)
LIMIT 10;

-- Find employees with invalid section_id
SELECT id, employee_name, section_id
FROM employee_list
WHERE section_id NOT IN (SELECT id FROM sections)
LIMIT 10;

-- Find employees with invalid designation
SELECT id, employee_name, designation
FROM employee_list
WHERE designation NOT IN (SELECT id FROM job_title)
LIMIT 10;

-- Find leave applications with invalid applicantID
SELECT dataID, applicantID
FROM leave_applications
WHERE applicantID NOT IN (SELECT id FROM employee_list)
LIMIT 10;

-- Find leave applications with invalid organization_id
SELECT dataID, organization_id
FROM leave_applications
WHERE organization_id NOT IN (SELECT id FROM organization)
LIMIT 10;

-- =====================================================
-- SECTION 6: OPTIMIZATION SETTINGS
-- =====================================================

-- Optimize tables after adding indexes
OPTIMIZE TABLE `employee_list`;
OPTIMIZE TABLE `leave_applications`;
OPTIMIZE TABLE `leave_data_for_approval`;
OPTIMIZE TABLE `leave_deduction_history`;
OPTIMIZE TABLE `yearly_salary_increment`;
OPTIMIZE TABLE `user_list`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- SECTION 7: VERIFY FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Query to see all foreign keys created
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = 'bitac_leave_dev'
    AND TABLE_SCHEMA = 'bitac_leave_dev'
ORDER BY
    TABLE_NAME, CONSTRAINT_NAME;

-- =====================================================
-- COMPLETED!
-- =====================================================
--
-- After running this script:
-- 1. Test all CRUD operations in the application
-- 2. Monitor query performance with EXPLAIN
-- 3. Adjust indexes based on actual query patterns
-- 4. Set up regular OPTIMIZE TABLE maintenance
--
-- =====================================================
