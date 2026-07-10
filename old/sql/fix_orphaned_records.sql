-- Fix Orphaned Records in BITAC Leave Management System
-- This script fixes data integrity issues before adding foreign keys

USE bitac_leave_dev;

-- First, let's see what the valid default IDs are
SELECT 'Valid organization IDs:' as Info;
SELECT id, organization_name FROM organization WHERE deleted = 0 ORDER BY id LIMIT 5;

SELECT 'Valid section IDs:' as Info;
SELECT id, section_name FROM sections WHERE deleted = 0 ORDER BY id LIMIT 5;

SELECT 'Valid job_title IDs:' as Info;
SELECT id, job_title_name FROM job_title WHERE deleted = 0 ORDER BY id LIMIT 5;

SELECT 'Valid grade IDs:' as Info;
SELECT id, grade_title FROM grade WHERE deleted = 0 ORDER BY id LIMIT 5;

SELECT 'Valid salary_group IDs:' as Info;
SELECT id, head, sub_head FROM salary_group ORDER BY id LIMIT 5;

-- Show affected records before fixing
SELECT 'Employees with invalid organization_id:' as Info;
SELECT id, employee_name, organization_id
FROM employee_list
WHERE organization_id NOT IN (SELECT id FROM organization WHERE id IS NOT NULL)
LIMIT 10;

SELECT 'Employees with invalid section_id:' as Info;
SELECT id, employee_name, section_id
FROM employee_list
WHERE section_id NOT IN (SELECT id FROM sections WHERE id IS NOT NULL)
LIMIT 10;

SELECT 'Employees with invalid designation:' as Info;
SELECT id, employee_name, designation
FROM employee_list
WHERE designation NOT IN (SELECT id FROM job_title WHERE id IS NOT NULL)
LIMIT 10;

SELECT 'Employees with invalid pay_scale:' as Info;
SELECT id, employee_name, pay_scale
FROM employee_list
WHERE pay_scale NOT IN (SELECT id FROM grade WHERE id IS NOT NULL)
LIMIT 10;

SELECT 'Employees with invalid salary_group_id:' as Info;
SELECT id, employee_name, salary_group_id
FROM employee_list
WHERE salary_group_id NOT IN (SELECT id FROM salary_group WHERE id IS NOT NULL)
LIMIT 10;

-- FIX 1: Set invalid organization_id to the first valid organization
UPDATE employee_list
SET organization_id = (SELECT MIN(id) FROM organization WHERE deleted = 0)
WHERE organization_id NOT IN (SELECT id FROM organization WHERE id IS NOT NULL);

SELECT 'Fixed invalid organization_id records' as Status, ROW_COUNT() as RowsAffected;

-- FIX 2: Set invalid section_id to the first valid section
UPDATE employee_list
SET section_id = (SELECT MIN(id) FROM sections WHERE deleted = 0)
WHERE section_id NOT IN (SELECT id FROM sections WHERE id IS NOT NULL);

SELECT 'Fixed invalid section_id records' as Status, ROW_COUNT() as RowsAffected;

-- FIX 3: Set invalid designation to the first valid job title
UPDATE employee_list
SET designation = (SELECT MIN(id) FROM job_title WHERE deleted = 0)
WHERE designation NOT IN (SELECT id FROM job_title WHERE id IS NOT NULL);

SELECT 'Fixed invalid designation records' as Status, ROW_COUNT() as RowsAffected;

-- FIX 4: Set invalid pay_scale to the first valid grade
UPDATE employee_list
SET pay_scale = (SELECT MIN(id) FROM grade WHERE deleted = 0)
WHERE pay_scale NOT IN (SELECT id FROM grade WHERE id IS NOT NULL);

SELECT 'Fixed invalid pay_scale records' as Status, ROW_COUNT() as RowsAffected;

-- FIX 5: Set invalid salary_group_id to the first valid salary group
UPDATE employee_list
SET salary_group_id = (SELECT MIN(id) FROM salary_group)
WHERE salary_group_id NOT IN (SELECT id FROM salary_group WHERE id IS NOT NULL);

SELECT 'Fixed invalid salary_group_id records' as Status, ROW_COUNT() as RowsAffected;

-- Verify all issues are fixed
SELECT 'VERIFICATION - Remaining Issues:' as Status;

SELECT 'Invalid organization_id' as Issue, COUNT(*) as Count
FROM employee_list
WHERE organization_id NOT IN (SELECT id FROM organization WHERE id IS NOT NULL)
UNION ALL
SELECT 'Invalid section_id', COUNT(*)
FROM employee_list
WHERE section_id NOT IN (SELECT id FROM sections WHERE id IS NOT NULL)
UNION ALL
SELECT 'Invalid designation', COUNT(*)
FROM employee_list
WHERE designation NOT IN (SELECT id FROM job_title WHERE id IS NOT NULL)
UNION ALL
SELECT 'Invalid pay_scale', COUNT(*)
FROM employee_list
WHERE pay_scale NOT IN (SELECT id FROM grade WHERE id IS NOT NULL)
UNION ALL
SELECT 'Invalid salary_group_id', COUNT(*)
FROM employee_list
WHERE salary_group_id NOT IN (SELECT id FROM salary_group WHERE id IS NOT NULL);

SELECT 'All orphaned records have been fixed!' as Status;
