<?php
session_start();
require_once(__DIR__ . '/../../connection.php');

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate input
if (empty($_POST['dataID'])) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ তথ্য!']);
    exit;
}

$dataID = intval($_POST['dataID']);

// Soft delete - set deleted = 1
$deleteQuery = "UPDATE organization SET deleted = 1 WHERE id = ?";
$deleteStmt = mysqli_prepare($con, $deleteQuery);

if ($deleteStmt) {
    mysqli_stmt_bind_param($deleteStmt, "i", $dataID);

    if (mysqli_stmt_execute($deleteStmt)) {
        if (mysqli_stmt_affected_rows($deleteStmt) > 0) {
            if (function_exists('audit_log')) {
                audit_log('center_deleted', ['target_type' => 'organization', 'target_id' => $dataID]);
            }
            echo json_encode(['status' => 1, 'message' => 'প্রতিষ্ঠান/কেন্দ্র সফলভাবে মুছে ফেলা হয়েছে!']);
        } else {
            echo json_encode(['status' => 0, 'message' => 'প্রতিষ্ঠান/কেন্দ্র খুঁজে পাওয়া যায়নি!']);
        }
    } else {
        echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি: ' . mysqli_error($con)]);
    }

    mysqli_stmt_close($deleteStmt);
} else {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস প্রস্তুতি ত্রুটি!']);
}

mysqli_close($con);
?>
