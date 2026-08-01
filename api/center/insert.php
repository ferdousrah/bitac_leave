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
if (empty($_POST['organization_name'])) {
    echo json_encode(['status' => 0, 'message' => 'প্রতিষ্ঠান/কেন্দ্রের নাম প্রয়োজন!']);
    exit;
}

// Sanitize input
$organization_name = mysqli_real_escape_string($con, trim($_POST['organization_name']));
$address = isset($_POST['address']) ? mysqli_real_escape_string($con, trim($_POST['address'])) : '';
$phone = isset($_POST['phone']) ? mysqli_real_escape_string($con, trim($_POST['phone'])) : '';

// Check if organization already exists
$checkQuery = "SELECT id FROM organization WHERE organization_name = ? AND deleted = 0";
$checkStmt = mysqli_prepare($con, $checkQuery);
mysqli_stmt_bind_param($checkStmt, "s", $organization_name);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই নামের প্রতিষ্ঠান/কেন্দ্র ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    exit;
}
mysqli_stmt_close($checkStmt);

// Insert new organization
$insertQuery = "INSERT INTO organization (organization_name, address, phone, deleted) VALUES (?, ?, ?, 0)";
$insertStmt = mysqli_prepare($con, $insertQuery);

if ($insertStmt) {
    mysqli_stmt_bind_param($insertStmt, "sss", $organization_name, $address, $phone);

    if (mysqli_stmt_execute($insertStmt)) {
        $newId = mysqli_insert_id($con);
        if (function_exists('audit_log')) {
            audit_log('center_created', [
                'target_type' => 'organization',
                'target_id'   => $newId,
                'note'        => 'name=' . mb_substr($organization_name, 0, 100),
            ]);
        }
        echo json_encode(['status' => 1, 'message' => 'প্রতিষ্ঠান/কেন্দ্র সফলভাবে যোগ করা হয়েছে!']);
    } else {
        echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি: ' . mysqli_error($con)]);
    }

    mysqli_stmt_close($insertStmt);
} else {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস প্রস্তুতি ত্রুটি!']);
}

mysqli_close($con);
?>
