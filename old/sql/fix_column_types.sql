-- Fix Column Data Types for Foreign Keys
-- These columns need to be INT to match their referenced tables

USE bitac_leave_dev;

SET FOREIGN_KEY_CHECKS = 0;

-- Fix employee_list column types
ALTER TABLE `employee_list`
    MODIFY COLUMN `designation` INT NULL,
    MODIFY COLUMN `organization_id` INT NULL,
    MODIFY COLUMN `section_id` INT NULL,
    MODIFY COLUMN `pay_scale` INT NULL,
    MODIFY COLUMN `salary_group_id` INT NULL,
    MODIFY COLUMN `department_id` INT NULL;

-- Fix leave_applications column types
ALTER TABLE `leave_applications`
    MODIFY COLUMN `applicantID` INT NULL,
    MODIFY COLUMN `leaveType` INT NULL,
    MODIFY COLUMN `organization_id` INT NULL,
    MODIFY COLUMN `submitBy` INT NULL;

-- Fix leave_data_for_approval column types
ALTER TABLE `leave_data_for_approval`
    MODIFY COLUMN `leaveApplicationID` INT NULL,
    MODIFY COLUMN `signatory` INT NULL,
    MODIFY COLUMN `prevSignatory` INT NULL;

-- Fix leave_deduction_history column types
ALTER TABLE `leave_deduction_history`
    MODIFY COLUMN `employeeID` INT NULL,
    MODIFY COLUMN `leaveID` INT NULL;

-- Fix previous_leave_deduction column types
ALTER TABLE `previous_leave_deduction`
    MODIFY COLUMN `employeeID` INT NULL;

-- Fix leave_approval_signatory column types
ALTER TABLE `leave_approval_signatory`
    MODIFY COLUMN `organization_id` INT NULL,
    MODIFY COLUMN `designationID` INT NULL;

-- Fix user_list column types
ALTER TABLE `user_list`
    MODIFY COLUMN `user_group_id` INT NULL;

-- Fix yearly_salary_increment column types
ALTER TABLE `yearly_salary_increment`
    MODIFY COLUMN `employeeID` INT NULL,
    MODIFY COLUMN `designation` INT NULL,
    MODIFY COLUMN `section_id` INT NULL,
    MODIFY COLUMN `organization_id` INT NULL,
    MODIFY COLUMN `salary_group_id` INT NULL;

-- Fix notification column types
ALTER TABLE `notification`
    MODIFY COLUMN `userID` INT NULL;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Column types fixed successfully!' as Status;
