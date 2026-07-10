<?php
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: application/json');

$centerId    = intval($_POST['center_id']   ?? 0);
$recordId    = intval($_POST['record_id']   ?? 0); // 0 = new
$employeeID  = intval($_POST['employeeID']  ?? 0);
$isMandatory = intval($_POST['isMandatory'] ?? 1);

if (!$centerId || !$employeeID) {
    echo json_encode(['status' => 0, 'message' => 'কর্মকর্তা/কর্মচারী নির্বাচন করুন।']);
    exit;
}

if ($recordId > 0) {
    // UPDATE
    $stmt = mysqli_prepare($con, "UPDATE leave_approval_signatory SET employeeID=?, isMandatory=? WHERE dataID=? AND organization_id=?");
    mysqli_stmt_bind_param($stmt, 'iiii', $employeeID, $isMandatory, $recordId, $centerId);
    $msg = 'সিগনেটরি আপডেট করা হয়েছে।';
} else {
    // Check for duplicate employee in same center
    $dupCheck = mysqli_prepare($con, "SELECT dataID FROM leave_approval_signatory WHERE organization_id=? AND employeeID=?");
    mysqli_stmt_bind_param($dupCheck, 'ii', $centerId, $employeeID);
    mysqli_stmt_execute($dupCheck);
    mysqli_stmt_store_result($dupCheck);
    if (mysqli_stmt_num_rows($dupCheck) > 0) {
        echo json_encode(['status' => 0, 'message' => 'এই কর্মকর্তা/কর্মচারী এই কেন্দ্রে ইতোমধ্যে সিগনেটরি হিসেবে আছেন।']);
        exit;
    }

    // Get next approvalSL for this center
    $slRes = mysqli_query($con, "SELECT COALESCE(MAX(approvalSL), 1) + 1 AS next_sl FROM leave_approval_signatory WHERE organization_id='$centerId'");
    $slRow = mysqli_fetch_assoc($slRes);
    $nextSL = $slRow['next_sl'];

    $stmt = mysqli_prepare($con, "INSERT INTO leave_approval_signatory (organization_id, employeeID, approvalSL, isMandatory) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'iiii', $centerId, $employeeID, $nextSL, $isMandatory);
    $msg = 'নতুন সিগনেটরি যোগ করা হয়েছে।';
}

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 1, 'message' => $msg]);
} else {
    echo json_encode(['status' => 0, 'message' => 'সংরক্ষণে সমস্যা: ' . mysqli_error($con)]);
}
