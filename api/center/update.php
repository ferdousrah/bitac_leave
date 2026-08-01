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
if (empty($_POST['dataID']) || empty($_POST['organization_name'])) {
    echo json_encode(['status' => 0, 'message' => 'সকল তথ্য প্রয়োজন!']);
    exit;
}

// Sanitize input
$dataID = intval($_POST['dataID']);
$organization_name = mysqli_real_escape_string($con, trim($_POST['organization_name']));
$address = isset($_POST['address']) ? mysqli_real_escape_string($con, trim($_POST['address'])) : '';
$phone = isset($_POST['phone']) ? mysqli_real_escape_string($con, trim($_POST['phone'])) : '';

// Check if organization exists with the same name (excluding current record)
$checkQuery = "SELECT id FROM organization WHERE organization_name = ? AND id != ? AND deleted = 0";
$checkStmt = mysqli_prepare($con, $checkQuery);
mysqli_stmt_bind_param($checkStmt, "si", $organization_name, $dataID);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই নামের প্রতিষ্ঠান/কেন্দ্র ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    exit;
}
mysqli_stmt_close($checkStmt);

// Update organization
$updateQuery = "UPDATE organization SET organization_name = ?, address = ?, phone = ? WHERE id = ? AND deleted = 0";
$updateStmt = mysqli_prepare($con, $updateQuery);

if ($updateStmt) {
    mysqli_stmt_bind_param($updateStmt, "sssi", $organization_name, $address, $phone, $dataID);

    if (mysqli_stmt_execute($updateStmt)) {
        if (mysqli_stmt_affected_rows($updateStmt) > 0) {
            if (function_exists('audit_log')) {
                audit_log('center_updated', [
                    'target_type' => 'organization',
                    'target_id'   => $dataID,
                    'note'        => 'name=' . mb_substr($organization_name, 0, 100),
                ]);
            }
            echo json_encode(['status' => 1, 'message' => 'প্রতিষ্ঠান/কেন্দ্র সফলভাবে আপডেট করা হয়েছে!']);
        } else {
            echo json_encode(['status' => 0, 'message' => 'কোন পরিবর্তন করা হয়নি বা প্রতিষ্ঠান/কেন্দ্র খুঁজে পাওয়া যায়নি!']);
        }
    } else {
        echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি: ' . mysqli_error($con)]);
    }

    mysqli_stmt_close($updateStmt);
} else {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস প্রস্তুতি ত্রুটি!']);
}

mysqli_close($con);
?>
