<?php
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: application/json');

$centerId  = intval($_POST['center_id'] ?? 0);
$employeeID = intval($_POST['employeeID'] ?? 0);
$recordId  = intval($_POST['record_id'] ?? 0); // 0 = new insert

if (!$centerId || !$employeeID) {
    echo json_encode(['status' => 0, 'message' => 'অনুগ্রহ করে কেন্দ্র ও সিগনেটরি নির্বাচন করুন।']);
    exit;
}

// Auto-create organization_id column if missing
$checkCol = mysqli_query($con, "SHOW COLUMNS FROM leave_edit_approval_signatory LIKE 'organization_id'");
if (mysqli_num_rows($checkCol) === 0) {
    mysqli_query($con, "ALTER TABLE leave_edit_approval_signatory ADD COLUMN organization_id INT(11) DEFAULT 0 AFTER dataID");
}

// Verify employee exists and is active
$empCheck = mysqli_prepare($con, "SELECT id FROM employee_list WHERE id = ? AND employment_status = 1 AND pending_section_assignment = 0");
mysqli_stmt_bind_param($empCheck, 'i', $employeeID);
mysqli_stmt_execute($empCheck);
mysqli_stmt_store_result($empCheck);
if (mysqli_stmt_num_rows($empCheck) === 0) {
    echo json_encode(['status' => 0, 'message' => 'নির্বাচিত কর্মকর্তা/কর্মচারীর তথ্য পাওয়া যায়নি।']);
    exit;
}

if ($recordId > 0) {
    // UPDATE existing record
    $stmt = mysqli_prepare($con, "UPDATE leave_edit_approval_signatory SET employeeID = ? WHERE dataID = ? AND organization_id = ?");
    mysqli_stmt_bind_param($stmt, 'iii', $employeeID, $recordId, $centerId);
} else {
    // Check if a record already exists for this center (race condition guard)
    $existCheck = mysqli_prepare($con, "SELECT dataID FROM leave_edit_approval_signatory WHERE organization_id = ? LIMIT 1");
    mysqli_stmt_bind_param($existCheck, 'i', $centerId);
    mysqli_stmt_execute($existCheck);
    mysqli_stmt_store_result($existCheck);

    if (mysqli_stmt_num_rows($existCheck) > 0) {
        // Update instead
        mysqli_stmt_bind_result($existCheck, $existingId);
        mysqli_stmt_fetch($existCheck);
        $stmt = mysqli_prepare($con, "UPDATE leave_edit_approval_signatory SET employeeID = ? WHERE dataID = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $employeeID, $existingId);
    } else {
        // INSERT new
        $stmt = mysqli_prepare($con, "INSERT INTO leave_edit_approval_signatory (organization_id, employeeID, approvalSL, designationID) VALUES (?, ?, 1, 0)");
        mysqli_stmt_bind_param($stmt, 'ii', $centerId, $employeeID);
    }
}

if (mysqli_stmt_execute($stmt)) {
    if (function_exists('audit_log')) {
        audit_log('signatory_other_task_saved', [
            'target_type'     => 'leave_edit_approval_signatory',
            'target_id'       => (int)$employeeID,
            'organization_id' => (int)$centerId,
        ]);
    }
    echo json_encode(['status' => 1, 'message' => 'সিগনেটরি সফলভাবে সংরক্ষণ করা হয়েছে।']);
} else {
    echo json_encode(['status' => 0, 'message' => 'সংরক্ষণে সমস্যা হয়েছে: ' . mysqli_error($con)]);
}
