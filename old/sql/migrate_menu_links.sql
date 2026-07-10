-- ============================================================================
-- BITAC Leave Management System - Menu Links Migration
-- This script updates all module and submodule page_link values
-- to point to the new modular structure (views/modulename/)
-- ============================================================================

-- Backup note: This script modifies the modules and submodules tables
-- Consider backing up these tables before running this script

-- ============================================================================
-- EMPLOYEE MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/employees/manage.php' WHERE page_link = 'manage_employees';

-- ============================================================================
-- USER MANAGEMENT MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/users/manage.php' WHERE page_link = 'manage_user';
UPDATE submodules SET page_link = 'views/users/manage-groups.php' WHERE page_link = 'manage_user_group';

-- ============================================================================
-- SIGNATORY/ADMIN PANEL MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/signatory/manage.php' WHERE page_link = 'manage_leave_signatory';

-- ============================================================================
-- LEAVE MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/leave/application-form.php' WHERE page_link = 'leave_application_form';
UPDATE submodules SET page_link = 'views/leave/all-applications.php' WHERE page_link = 'all_leave_application';
UPDATE submodules SET page_link = 'views/leave/approval.php' WHERE page_link = 'leave_approval';
UPDATE submodules SET page_link = 'views/leave/allowed-applications.php' WHERE page_link = 'allowed_leave_applications';
UPDATE submodules SET page_link = 'views/leave/joining-approval.php' WHERE page_link = 'leave_joining_approval';
UPDATE submodules SET page_link = 'views/leave/my-applications.php' WHERE page_link = 'supervised_nd_approved_application_by_user';
UPDATE submodules SET page_link = 'views/employees/previous-leave.php' WHERE page_link = 'previous_leave_info_approve';
UPDATE submodules SET page_link = 'views/employees/regular-leave.php' WHERE page_link = 'previous_leave_regular_info_approve';
UPDATE submodules SET page_link = 'views/leave/edit-approval.php' WHERE page_link = 'leave_edit_approval';

UPDATE submodules SET page_link = 'views/leave/manage-approved-leaves.php' WHERE page_link = 'manage_approved_leaves';

UPDATE submodules SET page_link = 'views/leave-certificate/yearly.php' WHERE page_link = 'yearly_leave_certificate_form';

-- ============================================================================
-- REPORTS MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/reports/leave-self.php' WHERE page_link = 'leave_report_self';
UPDATE submodules SET page_link = 'views/reports/increment.php' WHERE page_link = 'increment_report';
UPDATE submodules SET page_link = 'views/reports/leave.php' WHERE page_link = 'leave_report';
UPDATE submodules SET page_link = 'views/reports/notice.php' WHERE page_link = 'office_notice';

-- ============================================================================
-- PROFILE MODULE LINK
-- ============================================================================
UPDATE submodules SET page_link = 'views/profile/my-account.php' WHERE page_link = 'my_profile.php';
UPDATE submodules SET page_link = 'views/profile/my-account.php' WHERE page_link = 'my_profile';

-- ============================================================================
-- CENTER MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/center/manage.php' WHERE page_link = 'manage_center';

-- ============================================================================
-- DESIGNATION MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/designation/manage.php' WHERE page_link = 'manage_designations';

-- ============================================================================
-- SECTION MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/section/manage.php' WHERE page_link = 'manage_sections';

-- ============================================================================
-- SALARY INCREMENT MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/salary-increment/settings.php' WHERE page_link = 'salary_increment_settings';
UPDATE submodules SET page_link = 'views/salary-increment/approve-changes.php' WHERE page_link = 'approve_increment_data_changes';
UPDATE submodules SET page_link = 'views/salary-increment/my-increments.php' WHERE page_link = 'my_salary_increment';
UPDATE submodules SET page_link = 'views/salary-increment/auto-generate.php' WHERE page_link = 'auto_generate_salary_increment_form';
UPDATE submodules SET page_link = 'views/salary-increment/manage.php' WHERE page_link = 'manage_salary_increment';
UPDATE submodules SET page_link = 'views/salary-increment/approve.php' WHERE page_link = 'approve_increment';
UPDATE submodules SET page_link = 'views/salary-increment/file-permission.php' WHERE page_link = 'increment_file_permission';
UPDATE submodules SET page_link = 'views/salary-increment/file-permission-all.php' WHERE page_link = 'increment_file_permission_all';
UPDATE submodules SET page_link = 'views/salary-increment/office-notice.php' WHERE page_link = 'salary_increment_office_notice';
UPDATE submodules SET page_link = 'views/salary-increment/office-notice-all.php' WHERE page_link = 'salary_increment_office_notice_all';

-- ============================================================================
-- LEAVE TYPE MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/leave-type/manage.php' WHERE page_link = 'leave_types';

-- ============================================================================
-- LEAVE CERTIFICATE MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/leave-certificate/generate.php' WHERE page_link = 'generate_leave_certificate_form';

-- ============================================================================
-- LEAVE TEMPLATE MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/leave-template/manage.php' WHERE page_link = 'manage_leave_template';

-- ============================================================================
-- LEAVE DEDUCTION MODULE LINKS
-- ============================================================================
UPDATE submodules SET page_link = 'views/leave-deduction/office-order.php' WHERE page_link = 'leave_deduction_on_office_order';
UPDATE submodules SET page_link = 'views/leave-deduction/history.php' WHERE page_link = 'approved_leave_deduction_data';

-- ============================================================================
-- VERIFICATION QUERIES
-- Run these after migration to verify the updates
-- ============================================================================

-- Check all updated links
-- SELECT 'Submodules' as Type, submodule_name as Name, page_link as Link
-- FROM submodules
-- WHERE deleted = 0 AND page_link LIKE 'views/%'
-- ORDER BY page_link;

-- Check for any remaining old-style links
-- SELECT 'Old Format' as Type, submodule_name as Name, page_link as Link
-- FROM submodules
-- WHERE deleted = 0 AND page_link NOT LIKE 'views/%' AND page_link != '#'
-- ORDER BY page_link;

-- ============================================================================
-- END OF MIGRATION SCRIPT
-- ============================================================================
