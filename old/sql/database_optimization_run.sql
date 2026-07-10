-- =====================================================
-- BITAC Leave Management System
-- Database Optimization & Foreign Key Relations
-- PRODUCTION RUN (Skip Primary Keys - Already Exist)
-- =====================================================

USE bitac_leave_dev;

-- Disable foreign key checks temporarily for modifications
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- SECTION 2: CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Employee List Indexes
ALTER TABLE `employee_list`
    ADD INDEX IF NOT EXISTS `idx_organization` (`organization_id`),
    ADD INDEX IF NOT EXISTS `idx_section` (`section_id`),
    ADD INDEX IF NOT EXISTS `idx_designation` (`designation`),
    ADD INDEX IF NOT EXISTS `idx_employment_status` (`employment_status`),
    ADD INDEX IF NOT EXISTS `idx_employee_id` (`employee_id`),
    ADD INDEX IF NOT EXISTS `idx_pay_scale` (`pay_scale`),
    ADD INDEX IF NOT EXISTS `idx_salary_group` (`salary_group_id`);

-- Leave Applications Indexes
ALTER TABLE `leave_applications`
    ADD INDEX IF NOT EXISTS `idx_applicant` (`applicantID`),
    ADD INDEX IF NOT EXISTS `idx_status` (`status`),
    ADD INDEX IF NOT EXISTS `idx_leave_type` (`leaveType`),
    ADD INDEX IF NOT EXISTS `idx_organization` (`organization_id`),
    ADD INDEX IF NOT EXISTS `idx_date_range` (`dateFrom`, `dateTo`),
    ADD INDEX IF NOT EXISTS `idx_applicant_status` (`applicantID`, `status`),
    ADD INDEX IF NOT EXISTS `idx_submit_date` (`submitDate`);

-- Leave Approval Data Indexes
ALTER TABLE `leave_data_for_approval`
    ADD INDEX IF NOT EXISTS `idx_leave_app` (`leaveApplicationID`),
    ADD INDEX IF NOT EXISTS `idx_signatory` (`signatory`),
    ADD INDEX IF NOT EXISTS `idx_is_approved` (`isApproved`),
    ADD INDEX IF NOT EXISTS `idx_leave_status` (`leaveApplicationID`, `isApproved`);

-- Leave Deduction History Indexes
ALTER TABLE `leave_deduction_history`
    ADD INDEX IF NOT EXISTS `idx_employee` (`employeeID`),
    ADD INDEX IF NOT EXISTS `idx_leave_type` (`leaveID`),
    ADD INDEX IF NOT EXISTS `idx_approved` (`isApproved`),
    ADD INDEX IF NOT EXISTS `idx_create_date` (`createDate`);

-- Previous Leave Deduction Indexes
ALTER TABLE `previous_leave_deduction`
    ADD INDEX IF NOT EXISTS `idx_employee` (`employeeID`),
    ADD INDEX IF NOT EXISTS `idx_approved` (`isApproved`);

-- Salary Increment Indexes
ALTER TABLE `yearly_salary_increment`
    ADD INDEX IF NOT EXISTS `idx_employee` (`employeeID`),
    ADD INDEX IF NOT EXISTS `idx_year` (`incrementYear`),
    ADD INDEX IF NOT EXISTS `idx_organization` (`organization_id`),
    ADD INDEX IF NOT EXISTS `idx_section` (`section_id`);

-- User List Indexes
ALTER TABLE `user_list`
    ADD INDEX IF NOT EXISTS `idx_employee_id` (`employee_id`),
    ADD INDEX IF NOT EXISTS `idx_user_group` (`user_group_id`);

-- Leave Approval Signatory Indexes
ALTER TABLE `leave_approval_signatory`
    ADD INDEX IF NOT EXISTS `idx_organization` (`organization_id`),
    ADD INDEX IF NOT EXISTS `idx_designation` (`designationID`),
    ADD INDEX IF NOT EXISTS `idx_approval_order` (`organization_id`, `approvalSL`);

-- Notification Indexes
ALTER TABLE `notification`
    ADD INDEX IF NOT EXISTS `idx_user` (`userID`),
    ADD INDEX IF NOT EXISTS `idx_datetime` (`dateTime`);

-- =====================================================
-- SECTION 3: ADD FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Employee List Foreign Keys
ALTER TABLE `employee_list`
    ADD CONSTRAINT `fk_employee_designation`
        FOREIGN KEY (`designation`)
        REFERENCES `job_title`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `employee_list`
    ADD CONSTRAINT `fk_employee_organization`
        FOREIGN KEY (`organization_id`)
        REFERENCES `organization`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `employee_list`
    ADD CONSTRAINT `fk_employee_section`
        FOREIGN KEY (`section_id`)
        REFERENCES `sections`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `employee_list`
    ADD CONSTRAINT `fk_employee_pay_scale`
        FOREIGN KEY (`pay_scale`)
        REFERENCES `grade`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `employee_list`
    ADD CONSTRAINT `fk_employee_salary_group`
        FOREIGN KEY (`salary_group_id`)
        REFERENCES `salary_group`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- Leave Applications Foreign Keys
ALTER TABLE `leave_applications`
    ADD CONSTRAINT `fk_leave_applicant`
        FOREIGN KEY (`applicantID`)
        REFERENCES `employee_list`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `leave_applications`
    ADD CONSTRAINT `fk_leave_type`
        FOREIGN KEY (`leaveType`)
        REFERENCES `leave_types`(`leaveID`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `leave_applications`
    ADD CONSTRAINT `fk_leave_organization`
        FOREIGN KEY (`organization_id`)
        REFERENCES `organization`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- Leave Approval Data Foreign Keys
ALTER TABLE `leave_data_for_approval`
    ADD CONSTRAINT `fk_approval_leave_app`
        FOREIGN KEY (`leaveApplicationID`)
        REFERENCES `leave_applications`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `leave_data_for_approval`
    ADD CONSTRAINT `fk_approval_signatory`
        FOREIGN KEY (`signatory`)
        REFERENCES `employee_list`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `leave_data_for_approval`
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
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `leave_deduction_history`
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
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `leave_approval_signatory`
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
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `group_access_permission`
    ADD CONSTRAINT `fk_permission_module`
        FOREIGN KEY (`module_id`)
        REFERENCES `modules`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `group_access_permission`
    ADD CONSTRAINT `fk_permission_submodule`
        FOREIGN KEY (`submodule_id`)
        REFERENCES `submodules`(`dataID`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Yearly Salary Increment Foreign Keys
ALTER TABLE `yearly_salary_increment`
    ADD CONSTRAINT `fk_increment_employee`
        FOREIGN KEY (`employeeID`)
        REFERENCES `employee_list`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `yearly_salary_increment`
    ADD CONSTRAINT `fk_increment_designation`
        FOREIGN KEY (`designation`)
        REFERENCES `job_title`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `yearly_salary_increment`
    ADD CONSTRAINT `fk_increment_section`
        FOREIGN KEY (`section_id`)
        REFERENCES `sections`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `yearly_salary_increment`
    ADD CONSTRAINT `fk_increment_organization`
        FOREIGN KEY (`organization_id`)
        REFERENCES `organization`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `yearly_salary_increment`
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
-- SECTION 4: OPTIMIZATION SETTINGS
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

SELECT 'Database optimization completed successfully!' as Status;
