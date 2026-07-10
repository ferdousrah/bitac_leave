-- Test Foreign Key Constraints
USE bitac_leave_dev;

SELECT '=== TESTING FOREIGN KEY CONSTRAINTS ===' as Test;

-- Test 1: Try to delete an organization that has employees (should fail)
SELECT 'Test 1: Trying to delete organization with employees (should fail)...' as Test;
DELETE FROM organization WHERE id = (
    SELECT organization_id FROM employee_list WHERE organization_id IS NOT NULL LIMIT 1
);
-- Expected: Error 1451 - Cannot delete or update a parent row

-- Test 2: Try to insert employee with invalid organization_id (should fail)
SELECT 'Test 2: Trying to insert employee with invalid organization_id (should fail)...' as Test;
INSERT INTO employee_list (employee_name, designation, organization_id, section_id, employee_id, memorialNo, pay_scale, basic_salary, salary_group_id)
VALUES ('Test Employee', 1, 999999, 1, 'TEST123', 'TEST123', 18, 10000, 1);
-- Expected: Error 1452 - Cannot add or update a child row

-- Test 3: Soft delete an employee with leave applications (should succeed)
SELECT 'Test 3: Soft deleting employee with leave applications...' as Test;
UPDATE employee_list
SET employment_status = 0
WHERE id IN (
    SELECT applicantID FROM leave_applications LIMIT 1
);
-- Expected: Success

SELECT 'All tests completed! Check for errors above.' as Status;
