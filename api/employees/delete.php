<?php
session_start();

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Check if user is logged in
if (!isset($_SESSION['userID']) || empty($_SESSION['userID'])) {
    error_log("Delete failed: Session not set. Session data: " . print_r($_SESSION, true));
    echo 0;
    exit;
}

require_once(__DIR__ . '/../../config/connection.php');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Delete failed: Not a POST request");
    echo 0;
    exit;
}

// Get and validate dataID - cast to integer to prevent SQL injection
$dataID = isset($_POST['dataID']) ? (int)$_POST['dataID'] : 0;

// Debug: Log all POST data
error_log("Delete POST data: " . print_r($_POST, true));

if ($dataID <= 0) {
    error_log("Delete failed: Invalid dataID = " . $dataID . " | Raw POST: " . file_get_contents('php://input'));
    echo 0;
    exit;
}

// Check for related records before deleting
$checkRelated = mysqli_query($con, "SELECT COUNT(*) as cnt FROM leave_applications WHERE applicantID = '$dataID'");
$relatedCount = mysqli_fetch_assoc($checkRelated)['cnt'] ?? 0;

if ($relatedCount > 0) {
    error_log("Employee has $relatedCount related leave applications - performing soft delete");
    // Instead of hard delete, do soft delete (set employment_status to 0)
    $updateStmt = $con->prepare("UPDATE `employee_list` SET `employment_status` = 0 WHERE `id` = ? AND `employment_status` != 0");
    if ($updateStmt) {
        $updateStmt->bind_param("i", $dataID);
        $updateStmt->execute();
        if ($updateStmt->affected_rows > 0) {
            error_log("Soft delete successful for ID: $dataID");
            if (function_exists('audit_log')) {
                audit_log('employee_deleted', [
                    'target_type' => 'employee_list',
                    'target_id'   => (int)$dataID,
                    'note'        => 'mode=soft; related_leave=' . $relatedCount,
                ]);
            }
            echo 1;
        } else {
            // Check if already inactive
            $checkStatus = mysqli_query($con, "SELECT employment_status FROM employee_list WHERE id = '$dataID'");
            $statusRow = mysqli_fetch_assoc($checkStatus);
            if ($statusRow && $statusRow['employment_status'] == 0) {
                error_log("Employee $dataID is already inactive");
                echo 1; // Already inactive, consider it success
            } else {
                error_log("Soft delete failed for ID $dataID: " . $con->error);
                echo 0;
            }
        }
        $updateStmt->close();
    } else {
        error_log("Soft delete prepare failed: " . $con->error);
        echo 0;
    }
    mysqli_close($con);
    exit;
}

// Try hard delete first
$stmt = $con->prepare("DELETE FROM `employee_list` WHERE `id` = ?");

if ($stmt) {
    $stmt->bind_param("i", $dataID);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            error_log("Hard delete successful for ID: $dataID");
            if (function_exists('audit_log')) {
                audit_log('employee_deleted', [
                    'target_type' => 'employee_list',
                    'target_id'   => (int)$dataID,
                    'note'        => 'mode=hard',
                ]);
            }
            echo 1;
        } else {
            error_log("Delete failed: No rows affected. Employee ID may not exist: " . $dataID);
            echo 0;
        }
    } else {
        // If hard delete fails (likely foreign key constraint), try soft delete
        error_log("Hard delete failed (FK constraint?): " . $stmt->error . " - Trying soft delete");
        $stmt->close();

        // Attempt soft delete
        $softStmt = $con->prepare("UPDATE `employee_list` SET `employment_status` = 0 WHERE `id` = ?");
        if ($softStmt) {
            $softStmt->bind_param("i", $dataID);
            $softStmt->execute();
            if ($softStmt->affected_rows > 0) {
                error_log("Soft delete successful for ID: $dataID");
                if (function_exists('audit_log')) {
                    audit_log('employee_deleted', [
                        'target_type' => 'employee_list',
                        'target_id'   => (int)$dataID,
                        'note'        => 'mode=soft; fallback_after_fk_fail',
                    ]);
                }
                echo 1;
            } else {
                error_log("Soft delete also failed for ID: $dataID");
                echo 0;
            }
            $softStmt->close();
        } else {
            echo 0;
        }
        mysqli_close($con);
        exit;
    }

    $stmt->close();
} else {
    error_log("Delete failed: Prepare error - " . $con->error);
    echo 0;
}

mysqli_close($con);
?>
