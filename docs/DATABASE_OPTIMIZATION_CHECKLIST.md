# Database Optimization Implementation Checklist

## Quick Start Guide

This checklist will guide you through implementing foreign key constraints and indexes for the BITAC Leave Management System database.

---

## ⚠️ IMPORTANT: Read Before Starting

1. **BACKUP YOUR DATABASE FIRST!** This cannot be undone.
2. **Test on a development copy** before applying to production
3. **Expect downtime** during implementation (30-60 minutes)
4. **Have rollback plan ready** (database backup)

---

## Pre-Implementation Checklist

- [ ] **Create database backup**
  ```bash
  mysqldump -u root -p bitac_leave_dev > backup_before_optimization_$(date +%Y%m%d).sql
  ```

- [ ] **Verify backup is valid**
  ```bash
  # Check file size is not zero
  ls -lh backup_before_optimization_*.sql

  # Test restore on different database
  mysql -u root -p -e "CREATE DATABASE bitac_test;"
  mysql -u root -p bitac_test < backup_before_optimization_*.sql
  ```

- [ ] **Put application in maintenance mode**
  - Create `maintenance.php` with message
  - Redirect all traffic temporarily

- [ ] **Document current database size**
  ```sql
  SELECT
      table_name AS "Table",
      ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
  FROM information_schema.TABLES
  WHERE table_schema = "bitac_leave_dev"
  ORDER BY (data_length + index_length) DESC;
  ```

---

## Implementation Steps

### Step 1: Data Integrity Check (15 min)

- [ ] **Check for invalid employee references**
  ```sql
  -- Invalid organization_id
  SELECT COUNT(*) as invalid_org FROM employee_list
  WHERE organization_id NOT IN (SELECT id FROM organization);

  -- Invalid section_id
  SELECT COUNT(*) as invalid_section FROM employee_list
  WHERE section_id NOT IN (SELECT id FROM sections);

  -- Invalid designation
  SELECT COUNT(*) as invalid_designation FROM employee_list
  WHERE designation NOT IN (SELECT id FROM job_title);

  -- Invalid pay_scale
  SELECT COUNT(*) as invalid_grade FROM employee_list
  WHERE pay_scale NOT IN (SELECT id FROM grade);
  ```

- [ ] **Check for invalid leave application references**
  ```sql
  -- Invalid applicantID
  SELECT COUNT(*) as invalid_applicant FROM leave_applications
  WHERE applicantID NOT IN (SELECT id FROM employee_list);

  -- Invalid organization_id
  SELECT COUNT(*) as invalid_org FROM leave_applications
  WHERE organization_id NOT IN (SELECT id FROM organization);

  -- Invalid leaveType
  SELECT COUNT(*) as invalid_type FROM leave_applications
  WHERE leaveType NOT IN (SELECT leaveID FROM leave_types);
  ```

- [ ] **Fix orphaned records** (if any found above)
  ```sql
  -- Example: Set invalid organization to default
  UPDATE employee_list
  SET organization_id = 1
  WHERE organization_id NOT IN (SELECT id FROM organization);

  -- Example: Set invalid section to default
  UPDATE employee_list
  SET section_id = 1
  WHERE section_id NOT IN (SELECT id FROM sections);
  ```

### Step 2: Run Optimization Script (20 min)

- [ ] **Execute the optimization script**
  ```bash
  mysql -u root -p bitac_leave_dev < database_optimization.sql
  ```

- [ ] **Monitor for errors**
  - Note any "Cannot add foreign key constraint" errors
  - Check `mysql error log` for issues

- [ ] **If errors occur:**
  1. Note the failing constraint name
  2. Run the specific data integrity check for that relationship
  3. Fix the data issue
  4. Re-run that specific ALTER TABLE command

### Step 3: Verify Implementation (10 min)

- [ ] **Check all foreign keys were created**
  ```sql
  SELECT
      CONSTRAINT_NAME,
      TABLE_NAME,
      COLUMN_NAME,
      REFERENCED_TABLE_NAME,
      REFERENCED_COLUMN_NAME
  FROM
      INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE
      REFERENCED_TABLE_SCHEMA = 'bitac_leave_dev'
  ORDER BY
      TABLE_NAME;
  ```
  **Expected:** Should see 20+ foreign key constraints

- [ ] **Check all indexes were created**
  ```sql
  SELECT
      TABLE_NAME,
      INDEX_NAME,
      GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMNS
  FROM
      INFORMATION_SCHEMA.STATISTICS
  WHERE
      TABLE_SCHEMA = 'bitac_leave_dev'
  GROUP BY
      TABLE_NAME, INDEX_NAME
  ORDER BY
      TABLE_NAME;
  ```
  **Expected:** Should see indexes on all foreign key columns

- [ ] **Test database integrity**
  ```sql
  -- Try to delete an employee with leave applications (should fail)
  DELETE FROM employee_list WHERE id = (
      SELECT applicantID FROM leave_applications LIMIT 1
  );
  -- Expected: Error 1451 - Cannot delete or update a parent row

  -- Try to insert leave with invalid employee (should fail)
  INSERT INTO leave_applications (applicantID, leaveType, dateFrom, dateTo)
  VALUES (999999, 1, '2026-01-01', '2026-01-02');
  -- Expected: Error 1452 - Cannot add or update a child row
  ```

### Step 4: Application Testing (15 min)

- [ ] **Test Employee Management**
  - [ ] Create new employee
  - [ ] Edit existing employee
  - [ ] Try to delete employee without leave applications
  - [ ] Try to delete employee WITH leave applications (should soft delete)

- [ ] **Test Leave Application Flow**
  - [ ] Submit new leave application
  - [ ] View pending leaves
  - [ ] Approve a leave
  - [ ] Reject a leave
  - [ ] Check leave balance calculation

- [ ] **Test User Management**
  - [ ] Login as different users
  - [ ] Create new user
  - [ ] Assign user to group
  - [ ] Test permissions

- [ ] **Test Reports**
  - [ ] Generate leave report
  - [ ] Generate salary increment report
  - [ ] Check DataTables loading

### Step 5: Performance Verification (10 min)

- [ ] **Run query performance tests**
  ```sql
  -- Test 1: Employee list query
  EXPLAIN SELECT e.*, jt.job_title_name, s.section_name, o.organization_name
  FROM employee_list e
  LEFT JOIN job_title jt ON jt.id = e.designation
  LEFT JOIN sections s ON s.id = e.section_id
  LEFT JOIN organization o ON o.id = e.organization_id
  WHERE e.employment_status = 1;
  -- Check: Should use indexes, not full table scan

  -- Test 2: Leave application query
  EXPLAIN SELECT * FROM leave_applications
  WHERE applicantID = 100 AND status = 0;
  -- Check: Should use idx_applicant_status composite index

  -- Test 3: Approval query
  EXPLAIN SELECT la.*, e.employee_name
  FROM leave_applications la
  JOIN leave_data_for_approval lda ON lda.leaveApplicationID = la.dataID
  WHERE lda.signatory = 50 AND lda.isApproved = 0;
  -- Check: Should use indexes on both tables
  ```

- [ ] **Check query execution time**
  ```sql
  -- Enable profiling
  SET profiling = 1;

  -- Run a complex query
  SELECT e.employee_name, COUNT(la.dataID) as leave_count
  FROM employee_list e
  LEFT JOIN leave_applications la ON la.applicantID = e.id
  WHERE e.organization_id = 5
  GROUP BY e.id;

  -- Show execution time
  SHOW PROFILES;
  ```

- [ ] **Optimize tables**
  ```sql
  OPTIMIZE TABLE employee_list;
  OPTIMIZE TABLE leave_applications;
  OPTIMIZE TABLE leave_data_for_approval;
  ```

---

## Post-Implementation Tasks

- [ ] **Remove maintenance mode**
  - Remove redirect
  - Test user access

- [ ] **Monitor application logs** for first 24 hours
  - Check PHP error logs
  - Check MySQL error logs
  - Monitor slow query log

- [ ] **Document changes**
  - [ ] Update system documentation
  - [ ] Record database version
  - [ ] Note any issues encountered

- [ ] **Schedule regular maintenance**
  - [ ] Weekly: OPTIMIZE TABLE
  - [ ] Monthly: Check index usage
  - [ ] Quarterly: Review slow query log

---

## Rollback Plan (If Needed)

If something goes wrong, follow these steps:

1. **Restore from backup**
   ```bash
   # Drop current database
   mysql -u root -p -e "DROP DATABASE bitac_leave_dev;"

   # Create fresh database
   mysql -u root -p -e "CREATE DATABASE bitac_leave_dev;"

   # Restore backup
   mysql -u root -p bitac_leave_dev < backup_before_optimization_*.sql
   ```

2. **Verify restoration**
   ```sql
   -- Check table count
   SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema = 'bitac_leave_dev';

   -- Check record counts
   SELECT COUNT(*) FROM employee_list;
   SELECT COUNT(*) FROM leave_applications;
   ```

3. **Test application**
   - Login to system
   - Check employee list loads
   - Check leave applications load

---

## Expected Results

After successful implementation:

✅ **Database Integrity**
- Cannot delete employees with leave applications
- Cannot insert invalid foreign key references
- Data relationships are enforced

✅ **Performance Improvements**
- Faster employee searches (30-50% improvement)
- Faster leave application queries (40-60% improvement)
- Faster approval workflow queries (50-70% improvement)

✅ **Maintenance Benefits**
- Automatic cascading deletes where appropriate
- Easier data cleanup
- Better error messages for invalid operations

---

## Troubleshooting Guide

### Problem: Foreign Key Constraint Error During Script Execution

**Error Message:**
```
ERROR 1452: Cannot add or update a child row: a foreign key constraint fails
```

**Solution:**
1. Identify the failing constraint from error message
2. Run data integrity check for that relationship
3. Fix orphaned records
4. Re-run the ALTER TABLE command

---

### Problem: Script Takes Too Long

**Symptoms:**
- ALTER TABLE commands hang
- Database unresponsive

**Solution:**
1. Check for long-running queries: `SHOW PROCESSLIST;`
2. Kill blocking queries if safe
3. Ensure no other users are accessing the database
4. Consider running during off-peak hours

---

### Problem: Application Errors After Implementation

**Error:** "Foreign key constraint fails" in PHP logs

**Solution:**
1. Review the specific SQL query failing
2. Check if application is trying to insert invalid foreign keys
3. Update application code to validate data before insert
4. Add proper error handling in PHP

---

### Problem: Slow Query Performance

**Symptoms:**
- Queries slower than before
- High CPU usage

**Solution:**
1. Run ANALYZE TABLE on affected tables
2. Check if MySQL is using correct indexes (EXPLAIN)
3. Update table statistics: `ANALYZE TABLE table_name;`
4. Rebuild indexes: `ALTER TABLE table_name ENGINE=InnoDB;`

---

## Success Criteria Checklist

Before considering the optimization complete:

- [ ] All foreign key constraints created successfully
- [ ] All indexes created successfully
- [ ] All application tests pass
- [ ] Query performance improved or equal
- [ ] No errors in application logs
- [ ] Backup completed and verified
- [ ] Documentation updated
- [ ] Team notified of changes

---

## Contact Information

If you encounter issues during implementation:

1. **Check MySQL Error Log:**
   - Linux: `/var/log/mysql/error.log`
   - Windows: `C:\xampp\mysql\data\*.err`

2. **Review Documentation:**
   - `DATABASE_STRUCTURE.md` - Complete schema reference
   - `database_optimization.sql` - SQL script with comments

3. **Rollback if necessary** - Don't hesitate to rollback if issues arise

---

## Maintenance Schedule Template

Create a cron job (Linux) or scheduled task (Windows) for regular maintenance:

**Weekly Optimization:**
```bash
#!/bin/bash
# weekly_optimize.sh
mysql -u root -p bitac_leave_dev -e "
    OPTIMIZE TABLE employee_list;
    OPTIMIZE TABLE leave_applications;
    OPTIMIZE TABLE leave_data_for_approval;
    OPTIMIZE TABLE leave_deduction_history;
    OPTIMIZE TABLE yearly_salary_increment;
"
```

**Monthly Analysis:**
```bash
#!/bin/bash
# monthly_analyze.sh
mysql -u root -p bitac_leave_dev -e "
    ANALYZE TABLE employee_list;
    ANALYZE TABLE leave_applications;
    ANALYZE TABLE leave_data_for_approval;
"
```

---

**Good luck with the implementation!**

Remember: **Backup → Test → Implement → Monitor → Document**
