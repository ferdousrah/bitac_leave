<?php
/**
 * Refactoring Helper Script
 * This script helps identify which files need to be moved and provides a migration plan
 */

$rootPath = __DIR__;

// Define module mappings
$modules = [
    'employees' => [
        'views' => [
            'manage_employees_vuexy.php' => 'views/employees/manage.php',
            'new_employee_form.php' => 'views/employees/new.php',
            'edit_employee_info_form.php' => 'views/employees/edit.php',
            'employee_details.php' => 'views/employees/details.php',
            'employee_leave_history.php' => 'views/employees/leave-history.php',
        ],
        'api' => [
            'fetch_active_employee_data.php' => 'api/employees/fetch-active.php',
            'fetch_active_employees_data.php' => 'api/employees/fetch-all-active.php',
            'fetch_inactive_employees_data.php' => 'api/employees/fetch-inactive.php',
            'insert_employee_data.php' => 'api/employees/insert.php',
            'edit_employee_data.php' => 'api/employees/update.php',
            'delete_emp_data.php' => 'api/employees/delete.php',
            'get_absent_employees.php' => 'api/employees/get-absent.php',
            'get_employees.php' => 'api/employees/get.php',
        ]
    ],
    'leave' => [
        'views' => [
            'leave_approval.php' => 'views/leave/approval.php',
            'leave_joining_approval.php' => 'views/leave/joining-approval.php',
            'leave_edit_approval.php' => 'views/leave/edit-approval.php',
            'leave_application_form.php' => 'views/leave/application-form.php',
            'leave_application_details.php' => 'views/leave/application-details.php',
            'leave_joining_application_details.php' => 'views/leave/joining-details.php',
            'all_leave_application.php' => 'views/leave/all-applications.php',
            'allowed_leave_applications.php' => 'views/leave/allowed-applications.php',
            'supervised_nd_approved_application_by_user.php' => 'views/leave/my-applications.php',
        ],
        'api' => [
            'fetch_supervised_leave_data.php' => 'api/leave/fetch-supervised.php',
            'fetch_forwardbyadmin_pending_data.php' => 'api/leave/fetch-forwarded-pending.php',
            'fetch_forwardedbyadmin_data.php' => 'api/leave/fetch-forwarded.php',
            'fetch_leave_data_for_admin.php' => 'api/leave/fetch-admin.php',
            'fetch_waitingtoapprove_leave_data.php' => 'api/leave/fetch-waiting-approve.php',
            'fetch_waitingtosupervise_leave_data.php' => 'api/leave/fetch-waiting-supervise.php',
            'fetch_approved_leave_data.php' => 'api/leave/fetch-approved.php',
            'approve_leave_application_action.php' => 'api/leave/approve-action.php',
            'approve_leave_joining_application_action.php' => 'api/leave/approve-joining-action.php',
            'leave_edit_approval_action.php' => 'api/leave/edit-approval-action.php',
            'insert_leave_application.php' => 'api/leave/insert-application.php',
            'edit_my_leave_application_action.php' => 'api/leave/edit-my-application.php',
            'cancelLeaveApplicationBySelf.php' => 'api/leave/cancel-application.php',
            'decline_leave_application.php' => 'api/leave/decline-application.php',
        ]
    ],
    'reports' => [
        'views' => [
            'leave_report.php' => 'views/reports/leave.php',
            'leave_report_self.php' => 'views/reports/leave-self.php',
            'increment_report.php' => 'views/reports/increment.php',
            'yearly_leave_certificate_form.php' => 'views/reports/yearly-certificate.php',
        ],
        'api' => [
            'leave_report_view.php' => 'api/reports/leave-view.php',
            'self_leave_report_view.php' => 'api/reports/leave-self-view.php',
            'increment_report_view.php' => 'api/reports/increment-view.php',
            'yearly_leave_certificate_print_view.php' => 'api/reports/yearly-certificate-pdf.php',
        ]
    ],
    'signatory' => [
        'views' => [
            'manage_leave_signatory.php' => 'views/signatory/manage.php',
            'edit_leave_sig_form.php' => 'views/signatory/edit.php',
            'new_signatory_form.php' => 'views/signatory/new.php',
        ],
        'api' => [
            'fetch_bitac_center_approval_setting_data.php' => 'api/signatory/fetch-center.php',
            'fetch_bitac_dhaka_approval_setting_data.php' => 'api/signatory/fetch-dhaka.php',
            'edit_signatory_data.php' => 'api/signatory/update.php',
            'insert_signatory_data.php' => 'api/signatory/insert.php',
            'delete_signatory.php' => 'api/signatory/delete.php',
            'update_leave_approval_order.php' => 'api/signatory/update-order.php',
        ]
    ],
    'profile' => [
        'views' => [
            'my_profile.php' => 'views/profile/my-account.php',
        ],
        'api' => [
            'update_my_user_data.php' => 'api/profile/update.php',
        ]
    ],
    'office' => [
        'views' => [
            'office_notice.php' => 'views/office/notice.php',
        ]
    ],
];

// Count total files
$totalFiles = 0;
foreach ($modules as $module => $types) {
    foreach ($types as $type => $files) {
        $totalFiles += count($files);
    }
}

echo "========================================\n";
echo "REFACTORING PLAN\n";
echo "========================================\n\n";
echo "Total files to migrate: $totalFiles\n\n";

foreach ($modules as $moduleName => $types) {
    echo "\n" . strtoupper($moduleName) . " MODULE:\n";
    echo str_repeat("-", 40) . "\n";

    foreach ($types as $typeName => $files) {
        echo "\n  " . ucfirst($typeName) . " Files:\n";
        foreach ($files as $oldPath => $newPath) {
            $exists = file_exists($rootPath . '/' . $oldPath) ? '✓' : '✗';
            echo "    $exists $oldPath\n";
            echo "       → $newPath\n";
        }
    }
}

echo "\n\n========================================\n";
echo "NEXT STEPS:\n";
echo "========================================\n";
echo "1. Review the plan above\n";
echo "2. Run migration module by module\n";
echo "3. Update paths in each migrated file\n";
echo "4. Test each module after migration\n";
echo "5. Update all inter-module links\n\n";
?>
