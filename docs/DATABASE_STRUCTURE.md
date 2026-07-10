# BITAC Leave Management System - Database Structure & Optimization Guide

**Database Name:** `bitac_leave_dev`
**Total Tables:** 26
**Last Updated:** January 2026

---

## Table of Contents

1. [Overview](#overview)
2. [Database Schema Diagram](#database-schema-diagram)
3. [Core Tables](#core-tables)
4. [Foreign Key Relationships](#foreign-key-relationships)
5. [Indexes & Performance](#indexes--performance)
6. [Implementation Guide](#implementation-guide)
7. [Maintenance Recommendations](#maintenance-recommendations)

---

## Overview

The BITAC Leave Management System is a comprehensive employee leave tracking system with multi-level approval workflows, salary increment tracking, and role-based access control.

### Key Features
- Employee management with organizational hierarchy
- Complex leave type classifications (7 types)
- Multi-level approval workflows
- Salary increment tracking
- Historical leave balance management
- User role-based access control
- Full audit trail capabilities

---

## Database Schema Diagram

### Entity Relationship Overview

```
┌─────────────────┐
│  organization   │◄─────────┐
└─────────────────┘          │
         ▲                   │
         │                   │
         │              ┌────┴─────┐
         │              │ sections │
         │              └────┬─────┘
         │                   ▲
         │                   │
┌────────┴────────┐    ┌─────┴──────┐      ┌──────────────┐
│   job_title     │◄───│employee_list│─────►│ salary_group │
└─────────────────┘    └─────┬──────┘      └──────────────┘
                             │
                             ▼
                    ┌────────────────┐
                    │ leave_applications│
                    └────────┬─────────┘
                             │
                    ┌────────┴──────────┐
                    │                   │
         ┌──────────▼────────┐  ┌──────▼──────────────┐
         │ leave_data_for_   │  │ leave_deduction_    │
         │    approval       │  │     history         │
         └───────────────────┘  └─────────────────────┘
```

---

## Core Tables

### 1. Employee Management

#### `employee_list`
**Purpose:** Central employee master data

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| id | INT PK | Primary key (AUTO_INCREMENT) | - |
| employee_name | VARCHAR | Full name | - |
| employee_id | VARCHAR UNIQUE | Unique employee identifier | - |
| designation | INT | Job title/position | → job_title.id |
| organization_id | INT | Organization/center | → organization.id |
| section_id | INT | Department/section | → sections.id |
| pay_scale | INT | Pay scale grade | → grade.id |
| salary_group_id | INT | Report category | → salary_group.id |
| basic_salary | DECIMAL | Current basic salary | - |
| employee_type | INT | 1=Permanent, 2=Contract | - |
| employment_status | INT | 1=Active, 0=Inactive | - |
| joining_date | DATE | Date of joining | - |
| date_of_birth | DATE | Birth date | - |
| email | VARCHAR | Email address | - |
| mobileNo | VARCHAR | Mobile number | - |
| nationalID | VARCHAR | National ID/NID | - |
| memorialNo | VARCHAR | Memorial/order number | - |
| photo | VARCHAR | Photo filename | - |
| signature | BLOB | Digital signature | - |
| display_order | INT | Display sorting order | - |
| created_by | INT | Created by user | → user_list.dataID |
| created_at | TIMESTAMP | Creation timestamp | - |
| updated_at | TIMESTAMP | Last update timestamp | - |

**Indexes:**
- PRIMARY KEY on `id`
- UNIQUE INDEX on `employee_id`
- INDEX on `organization_id`, `section_id`, `designation`, `employment_status`

---

#### `organization`
**Purpose:** Organization/center/office master

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Primary key |
| organization_name | VARCHAR | Organization name |
| address | TEXT | Address |
| phone | VARCHAR | Contact phone |
| display_order | INT | Display order |
| deleted | INT | 0=Active, 1=Deleted |

---

#### `sections`
**Purpose:** Department/section master

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Primary key |
| section_name | VARCHAR | Section name |
| display_order | INT | Display order |
| deleted | INT | 0=Active, 1=Deleted |

---

#### `job_title`
**Purpose:** Designation/position master

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Primary key |
| job_title_name | VARCHAR | Designation name |
| display_order | INT | Display order |
| deleted | INT | 0=Active, 1=Deleted |

---

#### `grade`
**Purpose:** Pay scale/grade master

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Primary key |
| grade_title | VARCHAR | Grade name |
| minimum_salary | DECIMAL | Minimum salary |
| maximum_salary | DECIMAL | Maximum salary |
| deleted | INT | 0=Active, 1=Deleted |

---

#### `salary_group`
**Purpose:** Salary reporting categories

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Primary key |
| head | VARCHAR | Main category |
| sub_head | VARCHAR | Subcategory |

---

### 2. Leave Management

#### `leave_applications`
**Purpose:** Main leave request records

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| dataID | INT PK | Primary key (AUTO_INCREMENT) | - |
| applicantID | INT | Employee who applied | → employee_list.id |
| leaveType | INT | Requested leave type | → leave_types.leaveID |
| approvedLeaveType | INT | Approved leave type | → leave_types.leaveID |
| organization_id | INT | Organization | → organization.id |
| dateFrom | DATE | Leave start date | - |
| dateTo | DATE | Leave end date | - |
| approvedDateFrom | DATE | Approved start date | - |
| approvedDateTo | DATE | Approved end date | - |
| approvedDays | INT | Approved number of days | - |
| leaveApplication | TEXT | Leave reason/justification | - |
| subject | TEXT | Leave subject | - |
| attachment | VARCHAR | Supporting document filename | - |
| status | INT | 0=Pending, 1=Approved, 3=Deleted | - |
| leaveTypeInTwo | INT | Leave classification (1-10) | - |
| submitBy | INT | User who submitted | → user_list.dataID |
| submitDate | DATE | Submission date | - |
| submitTime | TIME | Submission time | - |
| approvedDate | DATE | Approval date | - |
| onbehalf | VARCHAR | If applied on behalf of someone | - |
| isinformed | INT | Whether employee is informed | - |
| applicationType | INT | Application type | - |
| applicationTo | INT | Application destination level | - |
| signature | BLOB | Digital signature | - |
| created_at | TIMESTAMP | Creation timestamp | - |
| updated_at | TIMESTAMP | Last update timestamp | - |

**Leave Balance Tracking Columns:**
- `fullSalaryYear`, `fullSalaryMonth`, `fullSalaryDay` - Full avg salary leave balance
- `halfSalaryYear`, `halfSalaryMonth`, `halfSalaryDay` - Half avg salary leave balance
- `casual` - Casual leave balance
- `optionalLBalance` - Optional leave balance

**Indexes:**
- PRIMARY KEY on `dataID`
- INDEX on `applicantID`, `status`, `leaveType`, `organization_id`
- COMPOSITE INDEX on (`applicantID`, `status`)
- INDEX on (`dateFrom`, `dateTo`)

---

#### `leave_types`
**Purpose:** Leave type master

| leaveID | Leave Type | Bengali Name |
|---------|------------|--------------|
| 1 | Full Average Salary | গড় বেতন |
| 2 | Half Average Salary | অর্ধ-গড় বেতন |
| 3 | Casual Leave | নৈমিত্তিক |
| 4 | Leave Without Pay | বিনা বেতনে ছুটি |
| 5 | Optional Leave | ঐচ্ছিক ছুটি |
| 6 | Undeductible Leave | কর্তনহীন ছুটি |
| 10 | Extraordinary Leave | অসাধারণ ছুটি |

---

#### `leave_data_for_approval`
**Purpose:** Approval workflow tracking

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| leaveApplicationID | INT | Leave application reference | → leave_applications.dataID |
| signatory | INT | Approver employee | → employee_list.id |
| prevSignatory | INT | Previous approver | → employee_list.id |
| isApproved | INT | 0=Pending, 1=Approved, 2=Rejected | - |
| isRead | INT | Whether notification read | - |
| isSupervisor | INT | Is supervisor approval | - |
| isDG | INT | Is Director General approval | - |
| serial | INT | Approval sequence number | - |

**Indexes:**
- INDEX on `leaveApplicationID`, `signatory`, `isApproved`
- COMPOSITE INDEX on (`leaveApplicationID`, `isApproved`)

---

#### `leave_approval_signatory`
**Purpose:** Approval workflow configuration by organization and designation

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| dataID | INT PK | Primary key | - |
| organization_id | INT | Organization | → organization.id |
| designationID | INT | Designation/position | → job_title.id |
| approvalSL | INT | Serial in approval chain | - |
| isMandatory | INT | Is mandatory approver | - |

**Indexes:**
- INDEX on `organization_id`, `designationID`
- COMPOSITE INDEX on (`organization_id`, `approvalSL`)

---

#### `leave_deduction_history`
**Purpose:** Historical leave deductions tracking

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| employeeID | INT | Employee | → employee_list.id |
| leaveID | INT | Leave type | → leave_types.leaveID |
| leaveDeduction | INT | Days deducted | - |
| isApproved | INT | 0=Pending, 1=Approved | - |
| createDate | DATE | Record creation date | - |

---

#### `previous_leave_deduction`
**Purpose:** Leave balances from previous years

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| employeeID | INT | Employee | → employee_list.id |
| avgSalary | INT | Full avg salary leave days | - |
| halfAvgSalary | INT | Half avg salary leave days | - |
| casual | INT | Casual leave days | - |
| leaveWithoutPay | INT | Leave without pay days | - |
| undeductibleLeave | INT | Undeductible leave days | - |
| extraOrdinaryLeave | INT | Extraordinary leave days | - |
| avgSalaryFile | VARCHAR | Supporting document | - |
| halfAvgSalaryFile | VARCHAR | Supporting document | - |
| casualFile | VARCHAR | Supporting document | - |
| isApproved | INT | 0=Pending, 1=Approved | - |
| lastUpdate | DATETIME | Last update timestamp | - |

---

### 3. User & Authorization Management

#### `user_list`
**Purpose:** System users and login credentials

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| dataID | INT PK | Primary key | - |
| full_name | VARCHAR | Full name | - |
| employee_id | VARCHAR | Reference to employee | → employee_list.employee_id |
| user_id | VARCHAR UNIQUE | Login username | - |
| password | VARCHAR | Hashed password | - |
| user_type | INT | User role type | - |
| user_group_id | INT | User group | → user_group.id |
| email | VARCHAR | Email address | - |
| mobile | VARCHAR | Mobile number | - |
| designation | INT | Designation | - |
| signature | BLOB | Digital signature | - |
| created_at | TIMESTAMP | Creation timestamp | - |
| updated_at | TIMESTAMP | Last update timestamp | - |

---

#### `user_group`
**Purpose:** User group definitions for role-based access

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Primary key |
| group_name | VARCHAR | Group name |
| display_order | INT | Display order |
| deleted | INT | 0=Active, 1=Deleted |

---

#### `group_access_permission`
**Purpose:** Permission matrix for user groups

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| id | INT PK | Primary key | - |
| user_group_id | INT | User group | → user_group.id |
| module_id | INT | Module | → modules.dataID |
| submodule_id | INT | Submodule | → submodules.dataID |
| created_at | TIMESTAMP | Creation timestamp | - |

**Constraints:**
- UNIQUE KEY on (`user_group_id`, `module_id`, `submodule_id`)

---

### 4. Salary Management

#### `yearly_salary_increment`
**Purpose:** Annual salary increment tracking

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| incrementYear | INT | Increment year | - |
| employeeID | INT | Employee | → employee_list.id |
| designation | INT | Designation | → job_title.id |
| section_id | INT | Section | → sections.id |
| organization_id | INT | Organization | → organization.id |
| salary_group_id | INT | Salary group | → salary_group.id |
| presentSalaryGrade | INT | Current grade | → grade.id |
| presentSalary | DECIMAL | Current salary | - |
| incrementSalaryGrade | INT | New grade | → grade.id |
| incrementAmount | DECIMAL | Increment amount | - |
| incrementSalary | DECIMAL | New salary | - |
| salary_increment_date | DATE | Increment date | - |
| fileNo | VARCHAR | File number | - |
| genDateTime | DATETIME | Generation timestamp | - |
| generateBy | INT | Generated by user | → user_list.dataID |

---

### 5. System Tables

#### `notification`
**Purpose:** User notifications

| Column | Type | Description | Foreign Key |
|--------|------|-------------|-------------|
| userID | INT | User | → user_list.dataID |
| message | TEXT | Notification message | - |
| notificationType | VARCHAR | Type/HTML content | - |
| link | VARCHAR | URL link | - |
| dateTime | DATETIME | Notification timestamp | - |
| isImportant | INT | Importance flag | - |

---

## Foreign Key Relationships

### Visual Relationship Map

```
employee_list
├── FK: designation → job_title.id
├── FK: organization_id → organization.id
├── FK: section_id → sections.id
├── FK: pay_scale → grade.id
├── FK: salary_group_id → salary_group.id
└── FK: created_by/updated_by → user_list.dataID

leave_applications
├── FK: applicantID → employee_list.id
├── FK: leaveType → leave_types.leaveID
├── FK: approvedLeaveType → leave_types.leaveID
├── FK: organization_id → organization.id
└── FK: submitBy → user_list.dataID

leave_data_for_approval
├── FK: leaveApplicationID → leave_applications.dataID (CASCADE)
├── FK: signatory → employee_list.id
└── FK: prevSignatory → employee_list.id (SET NULL)

yearly_salary_increment
├── FK: employeeID → employee_list.id (CASCADE)
├── FK: designation → job_title.id
├── FK: section_id → sections.id
├── FK: organization_id → organization.id
└── FK: salary_group_id → salary_group.id

user_list
└── FK: user_group_id → user_group.id

group_access_permission
├── FK: user_group_id → user_group.id (CASCADE)
├── FK: module_id → modules.dataID (CASCADE)
└── FK: submodule_id → submodules.dataID (CASCADE)
```

### Foreign Key Actions

| Relationship | ON DELETE | ON UPDATE | Reason |
|--------------|-----------|-----------|--------|
| employee_list → organization | RESTRICT | CASCADE | Cannot delete org with active employees |
| employee_list → sections | RESTRICT | CASCADE | Cannot delete section with employees |
| leave_applications → employee_list | RESTRICT | CASCADE | Preserve leave history |
| leave_data_for_approval → leave_applications | CASCADE | CASCADE | Delete approvals with leave |
| leave_deduction_history → employee_list | CASCADE | CASCADE | Employee-specific data |
| user_list → user_group | RESTRICT | CASCADE | Cannot delete group with users |
| group_access_permission → user_group | CASCADE | CASCADE | Delete permissions with group |

---

## Indexes & Performance

### Primary Indexes (Created)

1. **employee_list**
   - `idx_organization` on `organization_id`
   - `idx_section` on `section_id`
   - `idx_designation` on `designation`
   - `idx_employment_status` on `employment_status`
   - `idx_employee_id_unique` UNIQUE on `employee_id`
   - `idx_pay_scale` on `pay_scale`
   - `idx_salary_group` on `salary_group_id`

2. **leave_applications**
   - `idx_applicant` on `applicantID`
   - `idx_status` on `status`
   - `idx_leave_type` on `leaveType`
   - `idx_organization` on `organization_id`
   - `idx_date_range` on (`dateFrom`, `dateTo`)
   - `idx_applicant_status` on (`applicantID`, `status`) - COMPOSITE
   - `idx_submit_date` on `submitDate`

3. **leave_data_for_approval**
   - `idx_leave_app` on `leaveApplicationID`
   - `idx_signatory` on `signatory`
   - `idx_is_approved` on `isApproved`
   - `idx_leave_status` on (`leaveApplicationID`, `isApproved`) - COMPOSITE

### Query Performance Tips

1. **Searching employees by organization:**
   ```sql
   SELECT * FROM employee_list
   WHERE organization_id = ? AND employment_status = 1;
   -- Uses: idx_organization
   ```

2. **Finding pending leave applications:**
   ```sql
   SELECT * FROM leave_applications
   WHERE status = 0 AND applicantID = ?;
   -- Uses: idx_applicant_status (composite)
   ```

3. **Approval workflow queries:**
   ```sql
   SELECT * FROM leave_data_for_approval
   WHERE signatory = ? AND isApproved = 0;
   -- Uses: idx_signatory and idx_is_approved
   ```

---

## Implementation Guide

### Step 1: Backup Database

```bash
# Create backup before any changes
mysqldump -u root -p bitac_leave_dev > backup_$(date +%Y%m%d).sql
```

### Step 2: Check for Data Integrity Issues

Run the data integrity checks in `database_optimization.sql` (Section 5) to identify orphaned records.

**Common Issues to Fix:**

1. **Invalid organization_id:**
   ```sql
   UPDATE employee_list
   SET organization_id = 1
   WHERE organization_id NOT IN (SELECT id FROM organization);
   ```

2. **Invalid section_id:**
   ```sql
   UPDATE employee_list
   SET section_id = 1
   WHERE section_id NOT IN (SELECT id FROM sections);
   ```

3. **Invalid designation:**
   ```sql
   UPDATE employee_list
   SET designation = 1
   WHERE designation NOT IN (SELECT id FROM job_title);
   ```

### Step 3: Run Optimization Script

```bash
mysql -u root -p bitac_leave_dev < database_optimization.sql
```

**Watch for errors:**
- Foreign key constraint violations indicate data integrity issues
- Note the failing constraints and fix data manually
- Re-run the script after fixes

### Step 4: Test Application

After running the optimization:

1. **Test Employee CRUD Operations:**
   - Create new employee
   - Edit employee details
   - Delete employee (should fail if they have leave applications - soft delete instead)

2. **Test Leave Application Flow:**
   - Submit new leave application
   - Approve/reject leave
   - Check leave balance calculations

3. **Test User Management:**
   - Create/edit users
   - Assign user groups
   - Test permissions

### Step 5: Monitor Performance

Use `EXPLAIN` to analyze query performance:

```sql
EXPLAIN SELECT * FROM employee_list
WHERE organization_id = 5 AND employment_status = 1;
```

Look for:
- `type: ref` or better (good)
- `type: ALL` (table scan - bad)
- `key: idx_organization` (index being used)

### Step 6: Regular Maintenance

Schedule these tasks:

```sql
-- Weekly: Optimize tables
OPTIMIZE TABLE employee_list;
OPTIMIZE TABLE leave_applications;
OPTIMIZE TABLE leave_data_for_approval;

-- Monthly: Analyze tables for query optimization
ANALYZE TABLE employee_list;
ANALYZE TABLE leave_applications;
```

---

## Maintenance Recommendations

### 1. Regular Database Maintenance

**Daily:**
- Monitor slow query log
- Check error logs

**Weekly:**
- Run OPTIMIZE TABLE on frequently updated tables
- Review index usage statistics

**Monthly:**
- Backup database
- Archive old leave applications (> 3 years)
- Analyze query performance and adjust indexes

### 2. Data Archiving Strategy

Archive old records to improve performance:

```sql
-- Archive leave applications older than 3 years
CREATE TABLE leave_applications_archive LIKE leave_applications;

INSERT INTO leave_applications_archive
SELECT * FROM leave_applications
WHERE YEAR(submitDate) < YEAR(CURDATE()) - 3;

-- After verification, delete from main table
DELETE FROM leave_applications
WHERE YEAR(submitDate) < YEAR(CURDATE()) - 3;
```

### 3. Index Maintenance

Monitor index usage:

```sql
SELECT
    TABLE_NAME,
    INDEX_NAME,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    CARDINALITY
FROM
    INFORMATION_SCHEMA.STATISTICS
WHERE
    TABLE_SCHEMA = 'bitac_leave_dev'
ORDER BY
    TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

Remove unused indexes:
- Check query logs for actual usage
- Drop indexes that are never used

### 4. Performance Tuning

**MySQL Configuration (my.ini):**

```ini
[mysqld]
# InnoDB buffer pool (set to 70-80% of available RAM)
innodb_buffer_pool_size = 2G

# Query cache (if MySQL < 8.0)
query_cache_size = 128M
query_cache_type = 1

# Connection pool
max_connections = 200

# Slow query log
slow_query_log = 1
long_query_time = 2
```

### 5. Backup Strategy

**Full Backup (Daily):**
```bash
#!/bin/bash
DATE=$(date +%Y%m%d)
mysqldump -u root -p bitac_leave_dev \
  --single-transaction \
  --routines \
  --triggers \
  > /backup/bitac_full_$DATE.sql

# Compress
gzip /backup/bitac_full_$DATE.sql

# Keep only last 30 days
find /backup -name "bitac_full_*.sql.gz" -mtime +30 -delete
```

**Incremental Backup:**
- Enable binary logs for point-in-time recovery
- Backup binary logs hourly

---

## Troubleshooting

### Issue: Foreign Key Constraint Violation

**Error:**
```
Cannot add foreign key constraint
```

**Solution:**
1. Check data types match between parent and child columns
2. Ensure referenced column has an index
3. Fix orphaned records first:
   ```sql
   SELECT * FROM employee_list
   WHERE organization_id NOT IN (SELECT id FROM organization);
   ```

### Issue: Slow Queries

**Solution:**
1. Use EXPLAIN to analyze query
2. Add missing indexes
3. Rewrite query to use indexes
4. Consider denormalization for frequently joined data

### Issue: Deadlocks

**Solution:**
1. Always access tables in the same order
2. Keep transactions short
3. Use appropriate isolation levels
4. Add indexes to reduce lock duration

---

## References

- MySQL Foreign Key Documentation: https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html
- MySQL Index Optimization: https://dev.mysql.com/doc/refman/8.0/en/optimization-indexes.html
- InnoDB Best Practices: https://dev.mysql.com/doc/refman/8.0/en/innodb-best-practices.html

---

**Document Version:** 1.0
**Last Updated:** January 2026
**Maintained By:** BITAC IT Team
