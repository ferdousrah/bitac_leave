<?php
/**
 * Menu Links Migration Script
 * Updates database page_link values to point to new modular structure
 */

require_once('config/connection.php');

echo "=================================\n";
echo "MENU LINKS MIGRATION\n";
echo "=================================\n\n";

// Start transaction for safety
mysqli_begin_transaction($con);

try {
    // Array of migrations [old_path => new_path]
    $migrations = [
        // Employee Module
        'manage_employees' => 'views/employees/manage.php',

        // User Management Module
        'manage_user' => 'views/users/manage.php',
        'manage_user_group' => 'views/users/manage-groups.php',

        // Signatory/Admin Panel Module
        'manage_leave_signatory' => 'views/signatory/manage.php',

        // Leave Module
        'leave_application_form' => 'views/leave/application-form.php',
        'all_leave_application' => 'views/leave/all-applications.php',
        'leave_approval' => 'views/leave/approval.php',
        'allowed_leave_applications' => 'views/leave/allowed-applications.php',
        'leave_joining_approval' => 'views/leave/joining-approval.php',
        'supervised_nd_approved_application_by_user' => 'views/leave/my-applications.php',
        // NOTE: These pages stay in root (not refactored):
        // - previous_leave_info_approve.php
        // - previous_leave_regular_info_approve.php
        'leave_edit_approval' => 'views/leave/edit-approval.php',

        // Reports Module
        'leave_report_self' => 'views/reports/leave-self.php',
        'increment_report' => 'views/reports/increment.php',
        'leave_report' => 'views/reports/leave.php',
        'office_notice' => 'views/reports/notice.php',
    ];

    $updated_count = 0;
    $skipped_count = 0;

    foreach ($migrations as $old_path => $new_path) {
        // Check if the old path exists
        $check_query = "SELECT COUNT(*) as count FROM submodules WHERE page_link = ? AND deleted = 0";
        $stmt = mysqli_prepare($con, $check_query);
        mysqli_stmt_bind_param($stmt, "s", $old_path);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row['count'] > 0) {
            // Update the path
            $update_query = "UPDATE submodules SET page_link = ? WHERE page_link = ? AND deleted = 0";
            $stmt = mysqli_prepare($con, $update_query);
            mysqli_stmt_bind_param($stmt, "ss", $new_path, $old_path);

            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                echo "✓ Updated: {$old_path} → {$new_path} ({$affected} record(s))\n";
                $updated_count += $affected;
            } else {
                throw new Exception("Failed to update {$old_path}");
            }
        } else {
            echo "⊘ Skipped: {$old_path} (not found in database)\n";
            $skipped_count++;
        }
    }

    // Commit the transaction
    mysqli_commit($con);

    echo "\n=================================\n";
    echo "MIGRATION COMPLETED SUCCESSFULLY\n";
    echo "=================================\n";
    echo "Total updated: {$updated_count} records\n";
    echo "Total skipped: {$skipped_count} records\n\n";

    // Show verification query results
    echo "=== Verification: Updated Links ===\n";
    $verify_query = "SELECT submodule_name, page_link FROM submodules WHERE deleted = 0 AND page_link LIKE 'views/%' ORDER BY page_link";
    $result = mysqli_query($con, $verify_query);
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  • " . str_pad($row['submodule_name'], 40) . " → " . $row['page_link'] . "\n";
    }

    echo "\n=== Remaining Old-Style Links (if any) ===\n";
    $old_query = "SELECT submodule_name, page_link FROM submodules WHERE deleted = 0 AND page_link NOT LIKE 'views/%' AND page_link != '#' ORDER BY page_link";
    $result = mysqli_query($con, $old_query);
    $has_old = false;
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  ⚠ " . str_pad($row['submodule_name'], 40) . " → " . $row['page_link'] . "\n";
        $has_old = true;
    }
    if (!$has_old) {
        echo "  ✓ No old-style links remaining!\n";
    }

} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($con);
    echo "\n❌ ERROR: Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
    exit(1);
}

mysqli_close($con);
?>
