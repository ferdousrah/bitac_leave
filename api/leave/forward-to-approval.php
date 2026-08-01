<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

// Get current user (admin)
$userStmt = mysqli_prepare($con, "SELECT * FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($userStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($userStmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));

if (!$user) {
    echo json_encode(['status' => 0, 'message' => 'ব্যবহারকারীর তথ্য পাওয়া যায়নি।']);
    exit;
}

$adminUserID        = (int)$user['dataID'];
$leaveApplicationID = intval($_POST['leaveApplicationID'] ?? 0);

if (!$leaveApplicationID) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ অনুরোধ।']);
    exit;
}

// Dates come in as yyyy-mm-dd (HTML5 date input)
$leaveFrom         = $_POST['leaveFrom']         ?? '';
$leaveTo           = $_POST['leaveTo']           ?? '';
$approvedLeaveType = intval($_POST['approvedLeaveType'] ?? 0);
$leaveTypeInTwo    = intval($_POST['leaveTypeInTwo']    ?? 0);
$note              = trim($_POST['note']                ?? '');
$today             = date('Y-m-d');

if (!$leaveFrom || !$leaveTo) {
    echo json_encode(['status' => 0, 'message' => 'তারিখ সঠিকভাবে পূরণ করুন।']);
    exit;
}

// admin initiator details
$adminInitiatorEmployeeID        = (int)$user['employee_id'];

$getInitiatorDetailsQ = mysqli_prepare($con, "SELECT * FROM employee_list WHERE id=?");
mysqli_stmt_bind_param($getInitiatorDetailsQ, 'i', $adminInitiatorEmployeeID);
mysqli_stmt_execute($getInitiatorDetailsQ);
$adminInitiatorDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($getInitiatorDetailsQ));

// Ensure admin-note history table exists (stores every forward note,
// so re-forwards after a return don't overwrite earlier notes in the thread).
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS leave_admin_note_history (
        dataID INT AUTO_INCREMENT PRIMARY KEY,
        leaveApplicationID INT NOT NULL,
        adminInitiator INT NOT NULL,
        adminInitiatorName VARCHAR(255),
        adminInitiatorTitle VARCHAR(255),
        note TEXT NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app (leaveApplicationID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Record this forward's note in history (only if non-empty)
if (trim($note) !== '') {
    // Resolve initiator's title for display
    $initTitle = '';
    if (!empty($adminInitiatorDetails['designation'])) {
        $titleStmt = mysqli_prepare($con, "SELECT job_title_name FROM job_title WHERE id = ?");
        mysqli_stmt_bind_param($titleStmt, 'i', $adminInitiatorDetails['designation']);
        mysqli_stmt_execute($titleStmt);
        $titleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($titleStmt));
        mysqli_stmt_close($titleStmt);
        $initTitle = $titleRow['job_title_name'] ?? '';
    }
    $initName = $adminInitiatorDetails['employee_name'] ?? '';

    $histStmt = mysqli_prepare($con,
        "INSERT INTO leave_admin_note_history
         (leaveApplicationID, adminInitiator, adminInitiatorName, adminInitiatorTitle, note)
         VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($histStmt, 'iisss',
        $leaveApplicationID, $adminUserID, $initName, $initTitle, $note);
    mysqli_stmt_execute($histStmt);
    mysqli_stmt_close($histStmt);
}

// Update leave_applications with proposed data and latest admin note.
// status=0 reset ensures a previously returned-to-admin app (status=3) re-enters
// the approval chain as pending when re-forwarded.
$updStmt = mysqli_prepare($con,
    "UPDATE leave_applications
     SET approvedDateFrom=?, approvedDateTo=?, approvedLeaveType=?, leaveTypeInTwo=?,
         adminNote=?, adminInitiator=?, adminNoteDate=?, adminInitiatorOrganization=?, adminInitiatorSection=?, adminInitiatorDesignation=?,
         status=0
     WHERE dataID=?");
mysqli_stmt_bind_param($updStmt, 'ssiisssiiii',
    $leaveFrom, $leaveTo, $approvedLeaveType, $leaveTypeInTwo,
    $note, $adminUserID, $today,
    $adminInitiatorDetails['organization_id'], $adminInitiatorDetails['section_id'], $adminInitiatorDetails['designation'],
    $leaveApplicationID);
mysqli_stmt_execute($updStmt);

// Mark all approval rows as sent by admin
$fwdStmt = mysqli_prepare($con,
    "UPDATE leave_data_for_approval SET isSentbyAdmin=1 WHERE leaveApplicationID=?");
mysqli_stmt_bind_param($fwdStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($fwdStmt);

// Update copy-to list: delete old entries, insert new
$delStmt = mysqli_prepare($con, "DELETE FROM leave_notice_copy WHERE applicationID=?");
mysqli_stmt_bind_param($delStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($delStmt);

$copyToArr = (array)($_POST['copyTo'] ?? []);
$serialArr = (array)($_POST['serial'] ?? []);

foreach ($copyToArr as $i => $empID) {
    $empID  = intval($empID);
    $serial = intval($serialArr[$i] ?? ($i + 1));
    if ($empID > 0) {

    $getEmpDetailsQ = mysqli_prepare($con, "SELECT * FROM employee_list WHERE id=?");
    mysqli_stmt_bind_param($getEmpDetailsQ, 'i', $empID);
    mysqli_stmt_execute($getEmpDetailsQ);
    $empDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($getEmpDetailsQ));

        $insStmt = mysqli_prepare($con,
            "INSERT INTO leave_notice_copy (employeeID, organization_id, section_id, designation_id, applicationID, serial) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($insStmt, 'iiiiii', $empID, $empDetails['organization_id'], $empDetails['section_id'], $empDetails['designation'], $leaveApplicationID, $serial);
        mysqli_stmt_execute($insStmt);
    }
}

if (function_exists('audit_log')) {
    audit_log('leave_forwarded_to_approval', [
        'target_type' => 'leave_application',
        'target_id'   => (int)$leaveApplicationID,
        'note'        => 'forwarded by admin (dates ' . $leaveFrom . ' → ' . $leaveTo . ')',
    ]);
}

// Notify first non-supervisor signatory that their queue has a new item
try {
    $nextQ = mysqli_prepare($con,
        "SELECT signatory FROM leave_data_for_approval
         WHERE leaveApplicationID = ? AND isSupervisor = 0 AND isApproved = 0
         ORDER BY serial ASC LIMIT 1");
    mysqli_stmt_bind_param($nextQ, 'i', $leaveApplicationID);
    mysqli_stmt_execute($nextQ);
    $nextRow = mysqli_fetch_assoc(mysqli_stmt_get_result($nextQ)) ?: [];
    mysqli_stmt_close($nextQ);
    $nextEmpID = (int)($nextRow['signatory'] ?? 0);

    if ($nextEmpID > 0) {
        $applName = '';
        $anq = mysqli_prepare($con,
            "SELECT el.employee_name FROM leave_applications la
             INNER JOIN employee_list el ON la.applicantID = el.id
             WHERE la.dataID = ? LIMIT 1");
        mysqli_stmt_bind_param($anq, 'i', $leaveApplicationID);
        mysqli_stmt_execute($anq);
        $applName = mysqli_fetch_assoc(mysqli_stmt_get_result($anq))['employee_name'] ?? 'কর্মচারী';
        mysqli_stmt_close($anq);

        send_notification([user_id_for_employee($nextEmpID)],
            "$applName-এর ছুটির আবেদন আপনার অনুমোদনের অপেক্ষায়",
            ['type' => 'leave_pending',
             'link' => 'views/leave/approval.php?menuslug=leave-approval']);
    }
} catch (\Throwable $e) { /* silent */ }

echo json_encode(['status' => 1, 'message' => 'আবেদনটি অনুমোদনের জন্য পাঠানো হয়েছে।']);
