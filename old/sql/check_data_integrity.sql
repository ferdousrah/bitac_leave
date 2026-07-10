-- Data Integrity Check for BITAC Leave Management System
-- Run this before applying foreign key constraints

USE bitac_leave_dev;

-- Check 1: Employees with invalid organization_id
SELECT 'Invalid organization_id in employee_list' as Issue, COUNT(*) as Count
FROM employee_list
WHERE organization_id NOT IN (SELECT id FROM organization WHERE id IS NOT NULL);

-- Check 2: Employees with invalid section_id
SELECT 'Invalid section_id in employee_list' as Issue, COUNT(*) as Count
FROM employee_list
WHERE section_id NOT IN (SELECT id FROM sections WHERE id IS NOT NULL);

-- Check 3: Employees with invalid designation
SELECT 'Invalid designation in employee_list' as Issue, COUNT(*) as Count
FROM employee_list
WHERE designation NOT IN (SELECT id FROM job_title WHERE id IS NOT NULL);

-- Check 4: Employees with invalid pay_scale
SELECT 'Invalid pay_scale in employee_list' as Issue, COUNT(*) as Count
FROM employee_list
WHERE pay_scale NOT IN (SELECT id FROM grade WHERE id IS NOT NULL);

-- Check 5: Employees with invalid salary_group_id
SELECT 'Invalid salary_group_id in employee_list' as Issue, COUNT(*) as Count
FROM employee_list
WHERE salary_group_id NOT IN (SELECT id FROM salary_group WHERE id IS NOT NULL);

-- Check 6: Leave applications with invalid applicantID
SELECT 'Invalid applicantID in leave_applications' as Issue, COUNT(*) as Count
FROM leave_applications
WHERE applicantID NOT IN (SELECT id FROM employee_list WHERE id IS NOT NULL);

-- Check 7: Leave applications with invalid organization_id
SELECT 'Invalid organization_id in leave_applications' as Issue, COUNT(*) as Count
FROM leave_applications
WHERE organization_id NOT IN (SELECT id FROM organization WHERE id IS NOT NULL);

-- Check 8: Leave applications with invalid leaveType
SELECT 'Invalid leaveType in leave_applications' as Issue, COUNT(*) as Count
FROM leave_applications
WHERE leaveType NOT IN (SELECT leaveID FROM leave_types WHERE leaveID IS NOT NULL);

-- Check 9: User list with invalid user_group_id
SELECT 'Invalid user_group_id in user_list' as Issue, COUNT(*) as Count
FROM user_list
WHERE user_group_id IS NOT NULL
  AND user_group_id NOT IN (SELECT id FROM user_group WHERE id IS NOT NULL);

-- Check 10: Leave approval data with invalid leaveApplicationID
SELECT 'Invalid leaveApplicationID in leave_data_for_approval' as Issue, COUNT(*) as Count
FROM leave_data_for_approval
WHERE leaveApplicationID NOT IN (SELECT dataID FROM leave_applications WHERE dataID IS NOT NULL);

-- Check 11: Leave approval data with invalid signatory
SELECT 'Invalid signatory in leave_data_for_approval' as Issue, COUNT(*) as Count
FROM leave_data_for_approval
WHERE signatory NOT IN (SELECT id FROM employee_list WHERE id IS NOT NULL);
