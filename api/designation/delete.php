<?php
// Start session first
session_start();

// Set JSON header
header('Content-Type: application/json');

// Start output buffering
ob_start();
require_once(__DIR__ . '/../../connection.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate input
if (!isset($_POST['dataID']) || empty($_POST['dataID'])) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ অনুরোধ!']);
    exit;
}

$dataID = intval($_POST['dataID']);

// Check if 'deleted' column exists in job_title table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM job_title LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

if ($hasDeletedColumn) {
    // Soft delete - set deleted = 1
    $deleteQuery = "UPDATE job_title SET deleted = 1 WHERE id = ?";
    $deleteStmt = mysqli_prepare($con, $deleteQuery);

    if (!$deleteStmt) {
        echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
        exit;
    }

    mysqli_stmt_bind_param($deleteStmt, "i", $dataID);

    if (mysqli_stmt_execute($deleteStmt)) {
        if (mysqli_stmt_affected_rows($deleteStmt) > 0) {
            if (function_exists('audit_log')) {
                audit_log('designation_deleted', ['target_type' => 'job_title', 'target_id' => $dataID]);
            }
            echo json_encode(['status' => 1, 'message' => 'পদবী সফলভাবে মুছে ফেলা হয়েছে!']);
        } else {
            echo json_encode(['status' => 0, 'message' => 'পদবী খুঁজে পাওয়া যায়নি!']);
        }
    } else {
        echo json_encode(['status' => 0, 'message' => 'পদবী মুছে ফেলতে ব্যর্থ হয়েছে!']);
    }

    mysqli_stmt_close($deleteStmt);
} else {
    // Hard delete - actually delete the record
    $deleteQuery = "DELETE FROM job_title WHERE id = ?";
    $deleteStmt = mysqli_prepare($con, $deleteQuery);

    if (!$deleteStmt) {
        echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
        exit;
    }

    mysqli_stmt_bind_param($deleteStmt, "i", $dataID);

    if (mysqli_stmt_execute($deleteStmt)) {
        if (mysqli_stmt_affected_rows($deleteStmt) > 0) {
            if (function_exists('audit_log')) {
                audit_log('designation_deleted', ['target_type' => 'job_title', 'target_id' => $dataID]);
            }
            echo json_encode(['status' => 1, 'message' => 'পদবী সফলভাবে মুছে ফেলা হয়েছে!']);
        } else {
            echo json_encode(['status' => 0, 'message' => 'পদবী খুঁজে পাওয়া যায়নি!']);
        }
    } else {
        echo json_encode(['status' => 0, 'message' => 'পদবী মুছে ফেলতে ব্যর্থ হয়েছে!']);
    }

    mysqli_stmt_close($deleteStmt);
}

mysqli_close($con);
?>
