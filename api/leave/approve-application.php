<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

// Get current user
$userStmt = mysqli_prepare($con, "SELECT * FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($userStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($userStmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));

if (!$user) {
    echo json_encode(['status' => 0, 'message' => 'ব্যবহারকারীর তথ্য পাওয়া যায়নি।']);
    exit;
}

$currentEmployeeID = (int)$user['employee_id'];
$signature         = mysqli_real_escape_string($con, $user['signature'] ?? '');
$today             = date('Y-m-d');

$action             = $_POST['action']             ?? 'approve';
$dataID             = intval($_POST['dataID']             ?? 0);
$leaveApplicationID = intval($_POST['leaveApplicationID'] ?? 0);
$note               = trim($_POST['note']                 ?? '');

if (!$dataID || !$leaveApplicationID) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ অনুরোধ।']);
    exit;
}

// Verify the approval row belongs to the current user
$rowStmt = mysqli_prepare($con, "SELECT * FROM leave_data_for_approval WHERE dataID = ? AND signatory = ? LIMIT 1");
mysqli_stmt_bind_param($rowStmt, 'ii', $dataID, $currentEmployeeID);
mysqli_stmt_execute($rowStmt);
$approvalRow = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));

if (!$approvalRow) {
    echo json_encode(['status' => 0, 'message' => 'আপনার এই আবেদন অনুমোদনের অনুমতি নেই।']);
    exit;
}

// ── DECLINE ─────────────────────────────────────────────────────────────────
if ($action === 'decline') {
    $decStmt = mysqli_prepare($con,
        "UPDATE leave_data_for_approval
         SET isApproved=2, approvedDays=0, note=?, signature=?, approvedDate=?
         WHERE dataID=? AND signatory=?");
    mysqli_stmt_bind_param($decStmt, 'sssii', $note, $signature, $today, $dataID, $currentEmployeeID);
    mysqli_stmt_execute($decStmt);

    // Mark all other pending rows for this application as declined
    $decAllStmt = mysqli_prepare($con,
        "UPDATE leave_data_for_approval SET isApproved=2, approvedDays=0
         WHERE leaveApplicationID=? AND isApproved=0");
    mysqli_stmt_bind_param($decAllStmt, 'i', $leaveApplicationID);
    mysqli_stmt_execute($decAllStmt);

    // Generate office notice record
    $year = date('Y'); $month = date('m');
    $noticeStmt = mysqli_prepare($con,
        "INSERT INTO office_notice_record(year, month, noticeType, leaveApplicationID) VALUES(?,?,1,?)");
    mysqli_stmt_bind_param($noticeStmt, 'ssi', $year, $month, $leaveApplicationID);
    mysqli_stmt_execute($noticeStmt);
    $noticeNumber = mysqli_insert_id($con);

    $appUpdStmt = mysqli_prepare($con,
        "UPDATE leave_applications
         SET status=2, cancellationReasion=?, cancellationDate=?, declinedBy=?, officeNoticeNumber=?
         WHERE dataID=?");
    mysqli_stmt_bind_param($appUpdStmt, 'ssiis', $note, $today, $currentEmployeeID, $noticeNumber, $leaveApplicationID);
    mysqli_stmt_execute($appUpdStmt);

    // Notify applicant of the rejection
    try {
        $aQ = mysqli_prepare($con,
            "SELECT el.employee_name, ul.dataID
             FROM leave_applications la
             INNER JOIN employee_list el ON la.applicantID = el.id
             LEFT JOIN user_list ul ON ul.employee_id = el.id
             WHERE la.dataID = ? LIMIT 1");
        mysqli_stmt_bind_param($aQ, 'i', $leaveApplicationID);
        mysqli_stmt_execute($aQ);
        $aRow = mysqli_fetch_assoc(mysqli_stmt_get_result($aQ)) ?: [];
        mysqli_stmt_close($aQ);
        $applicantUserID = (int)($aRow['dataID'] ?? 0);
        $noteShort = mb_substr((string)$note, 0, 120);
        send_notification([$applicantUserID],
            "আপনার ছুটির আবেদন না মঞ্জুর করা হয়েছে। কারণ: $noteShort",
            ['type' => 'leave_rejected',
             'link' => 'views/leave/all-applications.php?menuslug=all-leave-application',
             'isImportant' => 1]);
    } catch (\Throwable $e) { /* silent */ }

    if (function_exists('audit_log')) {
        audit_log('leave_rejected', [
            'target_type' => 'leave_application',
            'target_id'   => (int)$leaveApplicationID,
            'note'        => 'reason=' . mb_substr((string)$note, 0, 200),
        ]);
    }
    echo json_encode(['status' => 1, 'message' => 'আবেদনটি না মঞ্জুর করা হয়েছে।']);
    exit;
}

// ── APPROVE ──────────────────────────────────────────────────────────────────
$approvedDays      = intval($_POST['approvedDays']      ?? 0);
$approvedLeaveType = intval($_POST['approvedLeaveType'] ?? 0);
$leaveTypeInTwo    = intval($_POST['leaveTypeInTwo']    ?? 0);
$isSupervisor      = (int)$approvalRow['isSupervisor'];

// Dates come in as yyyy-mm-dd (HTML5 date input)
$leaveFrom = $_POST['leaveFrom'] ?? '';
$leaveTo   = $_POST['leaveTo']   ?? '';

if (!$leaveFrom || !$leaveTo) {
    echo json_encode(['status' => 0, 'message' => 'তারিখ সঠিকভাবে পূরণ করুন।']);
    exit;
}

// Auto-calculate days if not provided
if (!$approvedDays) {
    $approvedDays = abs((int)((strtotime($leaveTo) - strtotime($leaveFrom)) / 86400)) + 1;
}

// Update leave_applications proposed data
if ($isSupervisor) {
    $updApp = mysqli_prepare($con,
        "UPDATE leave_applications SET approvedDateFrom=?, approvedDateTo=?, approvedLeaveType=? WHERE dataID=?");
    mysqli_stmt_bind_param($updApp, 'ssii', $leaveFrom, $leaveTo, $approvedLeaveType, $leaveApplicationID);
} else {
    $updApp = mysqli_prepare($con,
        "UPDATE leave_applications SET approvedDateFrom=?, approvedDateTo=?, approvedLeaveType=?, leaveTypeInTwo=? WHERE dataID=?");
    mysqli_stmt_bind_param($updApp, 'ssiii', $leaveFrom, $leaveTo, $approvedLeaveType, $leaveTypeInTwo, $leaveApplicationID);
}
mysqli_stmt_execute($updApp);

// Check if this is the last (final) signatory
$lastStmt = mysqli_prepare($con,
    "SELECT signatory FROM leave_data_for_approval
     WHERE leaveApplicationID=? AND isSupervisor=0 AND isSentbyAdmin=1
     ORDER BY serial DESC LIMIT 1");
mysqli_stmt_bind_param($lastStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($lastStmt);
$lastRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lastStmt));
$lastSignatory   = (int)($lastRow['signatory'] ?? 0);
$isLastSignatory = (!$isSupervisor && $currentEmployeeID === $lastSignatory);

// Mark this row as approved
$approveStmt = mysqli_prepare($con,
    "UPDATE leave_data_for_approval
     SET isApproved=1, approvedDays=?, note=?, signature=?, approvedDate=?
     WHERE dataID=? AND signatory=?");
mysqli_stmt_bind_param($approveStmt, 'isssii', $approvedDays, $note, $signature, $today, $dataID, $currentEmployeeID);
mysqli_stmt_execute($approveStmt);

if ($isLastSignatory) {
    // Final approval: generate office notice, update application status
    $year = date('Y'); $month = date('m');
    $noticeStmt = mysqli_prepare($con,
        "INSERT INTO office_notice_record(year, month, noticeType, leaveApplicationID) VALUES(?,?,1,?)");
    mysqli_stmt_bind_param($noticeStmt, 'ssi', $year, $month, $leaveApplicationID);
    mysqli_stmt_execute($noticeStmt);
    $noticeNumber = mysqli_insert_id($con);

    $joiningDate = date('Y-m-d', strtotime($leaveTo . ' +1 day'));

    $finalStmt = mysqli_prepare($con,
        "UPDATE leave_applications
         SET status=1, approvedDays=?, approvedDateFrom=?, approvedDateTo=?,
             officeNoticeDate=?, officeNoticeNumber=?,
             primaryLeaveDateFrom=?, primaryLeaveDateTo=?, primaryApprovedLeaveDays=?,
             joiningDateAfterLeave=?, leaveTypeInTwo=?, primaryApprovedLeaveType=?
         WHERE dataID=?");
    mysqli_stmt_bind_param($finalStmt, 'isssssssissi',
        $approvedDays, $leaveFrom, $leaveTo,
        $today, $noticeNumber,
        $leaveFrom, $leaveTo, $approvedDays,
        $joiningDate, $leaveTypeInTwo, $leaveTypeInTwo,
        $leaveApplicationID);
    mysqli_stmt_execute($finalStmt);

    // Notify copy-to employees
    $copyStmt = mysqli_prepare($con,
        "SELECT employeeID FROM leave_notice_copy WHERE applicationID=? ORDER BY serial ASC");
    mysqli_stmt_bind_param($copyStmt, 'i', $leaveApplicationID);
    mysqli_stmt_execute($copyStmt);
    $copyRes = mysqli_stmt_get_result($copyStmt);
    $notimsg  = 'ছুটি সংক্রান্ত অফিস আদেশ , অনুলিপি';
    $notilink = 'leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $leaveApplicationID;
    $dt = date('Y-m-d H:i:s');

    while ($cRow = mysqli_fetch_assoc($copyRes)) {
        $uStmt = mysqli_prepare($con, "SELECT dataID FROM user_list WHERE employee_id=? LIMIT 1");
        mysqli_stmt_bind_param($uStmt, 'i', $cRow['employeeID']);
        mysqli_stmt_execute($uStmt);
        $uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
        if ($uRow) {
            $nStmt = mysqli_prepare($con,
                "INSERT INTO notification(userID, message, link, dateTime) VALUES(?,?,?,?)");
            mysqli_stmt_bind_param($nStmt, 'isss', $uRow['dataID'], $notimsg, $notilink, $dt);
            mysqli_stmt_execute($nStmt);
        }
    }

    // Notify applicant
    $appStmt = mysqli_prepare($con, "SELECT applicantID FROM leave_applications WHERE dataID=? LIMIT 1");
    mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
    mysqli_stmt_execute($appStmt);
    $appRow = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
    if ($appRow) {
        $uStmt2 = mysqli_prepare($con, "SELECT dataID FROM user_list WHERE employee_id=? LIMIT 1");
        mysqli_stmt_bind_param($uStmt2, 'i', $appRow['applicantID']);
        mysqli_stmt_execute($uStmt2);
        $uRow2 = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt2));
        if ($uRow2) {
            $nStmt2 = mysqli_prepare($con,
                "INSERT INTO notification(userID, message, link, dateTime) VALUES(?,?,?,?)");
            mysqli_stmt_bind_param($nStmt2, 'isss', $uRow2['dataID'], $notimsg, $notilink, $dt);
            mysqli_stmt_execute($nStmt2);
        }
    }

    if (function_exists('audit_log')) {
        audit_log('leave_approved', [
            'target_type' => 'leave_application',
            'target_id'   => (int)$leaveApplicationID,
            'note'        => 'final; dates ' . $leaveFrom . ' → ' . $leaveTo,
        ]);
    }
    echo json_encode(['status' => 1, 'message' => 'ছুটির আবেদন চূড়ান্তভাবে অনুমোদিত হয়েছে।']);
} else {
    $msg = $isSupervisor ? 'সুপারিশ সম্পন্ন হয়েছে।' : 'অনুমোদন সম্পন্ন হয়েছে।';
    if (function_exists('audit_log')) {
        audit_log($isSupervisor ? 'leave_recommended' : 'leave_chain_approved', [
            'target_type' => 'leave_application',
            'target_id'   => (int)$leaveApplicationID,
            'note'        => 'chain-step; dates ' . $leaveFrom . ' → ' . $leaveTo,
        ]);
    }
    echo json_encode(['status' => 1, 'message' => $msg]);
}
