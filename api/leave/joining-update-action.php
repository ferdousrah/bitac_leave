<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../bddate.php');
require_once(__DIR__ . '/../../includes/approval-chain-preview.php');
ob_end_clean();

function out($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// `status` is an integer, 0 or 1. Never pass a `status` key through out()'s
// $extra — array_merge lets it overwrite the real one, and the sole caller
// tests `resp.status === 1`, so a successful forward reported itself as an error.

if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    out(0, 'আপনি লগইন করেননি!');
}

$actorUserId = (int)$_SESSION['userID'];

$joiningID          = (int)($_POST['joiningID']          ?? 0);
$leaveApplicationID = (int)($_POST['leaveApplicationID'] ?? 0);
$joiningType        = (int)($_POST['joiningType']        ?? 0);
$joiningDate        = trim($_POST['joiningDate']         ?? '');
$extLeaveType       = (int)($_POST['extensionLeaveType'] ?? 0);
$adminNote          = trim($_POST['adminNote']           ?? '');

if ($joiningID <= 0 && $leaveApplicationID <= 0) out(0, 'অবৈধ আইডি');
if (!in_array($joiningType, [2, 3], true))       out(0, 'অবৈধ যোগদানের প্রকার (শুধু Type 2/3 এই page-এ)');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $joiningDate)) out(0, 'অবৈধ তারিখ ফরম্যাট');
if ($adminNote === '')                            out(0, 'অ্যাডমিন মন্তব্য আবশ্যক');
if ($joiningType === 3 && $extLeaveType <= 0)    out(0, 'Type 3: বর্ধিত অংশের ছুটির ধরন আবশ্যক');

// Load joining
$ljaStmt = mysqli_prepare($con, "SELECT * FROM leave_joining_application WHERE dataID = ? OR leaveApplicationID = ? ORDER BY dataID DESC LIMIT 1");
mysqli_stmt_bind_param($ljaStmt, 'ii', $joiningID, $leaveApplicationID);
mysqli_stmt_execute($ljaStmt);
$lja = mysqli_fetch_assoc(mysqli_stmt_get_result($ljaStmt));
mysqli_stmt_close($ljaStmt);
if (!$lja)                              out(0, 'যোগদান পত্র খুঁজে পাওয়া যায়নি');
if ((int)$lja['status'] !== 0)          out(0, 'এই যোগদান পত্র ইতিমধ্যে নিষ্পত্তি হয়েছে');
if ((int)$lja['joiningType'] !== $joiningType) out(0, 'যোগদানের প্রকার match করছে না');

$joiningID = (int)$lja['dataID'];
$leaveAppID = (int)$lja['leaveApplicationID'];

// Verify supervisor has approved
$supStmt = mysqli_prepare($con,
    "SELECT isApproved FROM leave_joining_data_for_approval
     WHERE leaveApplicationID = ? AND isSupervisor = 1 LIMIT 1");
mysqli_stmt_bind_param($supStmt, 'i', $leaveAppID);
mysqli_stmt_execute($supStmt);
$supRow = mysqli_fetch_assoc(mysqli_stmt_get_result($supStmt));
mysqli_stmt_close($supStmt);
if (!$supRow || (int)$supRow['isApproved'] !== 1) out(0, 'সুপারভাইজার এখনো সুপারিশ করেননি');

// Load leave application
$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveAppID);
mysqli_stmt_execute($appStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);
if (!$leaveApp) out(0, 'মূল ছুটির আবেদন পাওয়া যায়নি');

$appOrgID         = (int)$leaveApp['organization_id'];
$approvedDateFrom = $leaveApp['approvedDateFrom'] ?: $leaveApp['dateFrom'];
$approvedDateTo   = $leaveApp['approvedDateTo']   ?: $leaveApp['dateTo'];

// Org gate
$actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, ul.employee_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.dataID = ? LIMIT 1");
mysqli_stmt_bind_param($actorStmt, 'i', $actorUserId);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt));
mysqli_stmt_close($actorStmt);
$actorOrgID    = (int)($actor['emp_org']       ?? 0);
$isSuperAdmin  = ((int)($actor['user_group_id'] ?? 0) === 1);
if (!$isSuperAdmin && $actorOrgID !== $appOrgID) {
    out(0, 'এই কেন্দ্রের যোগদান পত্র forward করার অনুমতি নেই');
}

// Validate joiningDate range per joiningType (convention: joiningDate = last leave day, inclusive)
$joinTs  = strtotime($joiningDate);
$aFromTs = strtotime($approvedDateFrom);
$aToTs   = strtotime($approvedDateTo);
if (!$joinTs || !$aFromTs || !$aToTs) out(0, 'তারিখ পার্স করা যায়নি');

if ($joiningType === 2) {
    if ($joinTs >= $aToTs)  out(0, 'Type 2: তারিখ অনুমোদিত শেষ তারিখের আগে হতে হবে');
    if ($joinTs <  $aFromTs) out(0, 'Type 2: তারিখ ছুটির শুরুর আগে হতে পারবে না');
} else if ($joiningType === 3) {
    if ($joinTs <= $aToTs)  out(0, 'Type 3: তারিখ অনুমোদিত শেষ তারিখের পরে হতে হবে');
}

$now = function_exists('ShowBangladeshTime') ? ShowBangladeshTime() : date('Y-m-d H:i:s');

mysqli_autocommit($con, false);

try {
    // 1. Update joining application with admin's edits
    $updStmt = mysqli_prepare($con,
        "UPDATE leave_joining_application
         SET requestedJoiningDate = ?, approvedLeaveType = ?, adminNote = ?,
             adminInitiator = ?, adminNoteDate = ?, lastUpdate = ?
         WHERE dataID = ?");
    mysqli_stmt_bind_param($updStmt, 'sisissi',
        $joiningDate, $extLeaveType, $adminNote, $actorUserId, $now, $now, $joiningID);
    if (!mysqli_stmt_execute($updStmt)) {
        throw new Exception('যোগদান আপডেট করতে ব্যর্থ: ' . mysqli_error($con));
    }
    mysqli_stmt_close($updStmt);

$segTypeChanges = [];
    // The desk may have corrected the leave type on the already-approved
    // segments (e.g. নৈমিত্তিক that should have been গড় বেতন). Only the type
    // changes — dates and day counts stay as approved.
    if (!empty($_POST['segLeaveType']) && is_array($_POST['segLeaveType'])) {
        $validLT = [];
        $ltq = mysqli_query($con, "SELECT leaveID FROM leave_types");
        if ($ltq) while ($lr = mysqli_fetch_assoc($ltq)) $validLT[(int)$lr['leaveID']] = true;

        $segUpd = mysqli_prepare($con,
            "UPDATE leave_application_segments
             SET leaveType = ?
             WHERE dataID = ? AND applicationID = ? AND leaveType <> ?");
        foreach ($_POST['segLeaveType'] as $segID => $newLT) {
            $segID = (int)$segID;
            $newLT = (int)$newLT;
            if ($segID <= 0 || !isset($validLT[$newLT])) continue;
            mysqli_stmt_bind_param($segUpd, 'iiii', $newLT, $segID, $leaveAppID, $newLT);
            if (!mysqli_stmt_execute($segUpd)) throw new Exception('ছুটির ধরন হালনাগাদ ব্যর্থ');
            if (mysqli_stmt_affected_rows($segUpd) > 0) $segTypeChanges[] = $segID . '=>' . $newLT;
        }
        mysqli_stmt_close($segUpd);
    }

    // The নোট উপস্থাপনকারী may have re-ordered or replaced the pending desks.
    // Applied before the forward flag below so the new rows pick it up too.
    $chainEdit = ['changed' => false];
    if (!empty($_POST['chainSignatory']) && is_array($_POST['chainSignatory'])) {
        $chainEdit = applyChainEdit($con, 'leave_joining_data_for_approval', $leaveAppID,
            $_POST['chainSignatory'], (int)$lja['applicantID']);
    }

    // 2. Forward chain — set isSentbyAdmin=1 on all rows for this application
    //    (Legacy convention sets it on supervisor row too; the inbox query treats
    //    isSentbyAdmin=1 on supervisor row as "admin has forwarded" sentinel.)
    $fwdStmt = mysqli_prepare($con,
        "UPDATE leave_joining_data_for_approval
         SET isSentbyAdmin = 1
         WHERE leaveApplicationID = ?");
    mysqli_stmt_bind_param($fwdStmt, 'i', $leaveAppID);
    if (!mysqli_stmt_execute($fwdStmt)) {
        throw new Exception('চেইন forward করতে ব্যর্থ: ' . mysqli_error($con));
    }
    mysqli_stmt_close($fwdStmt);

    // 3. Notify the first chain signatory (first row with isSupervisor=0, lowest serial)
    try {
        $firstSigStmt = mysqli_prepare($con,
            "SELECT signatory FROM leave_joining_data_for_approval
             WHERE leaveApplicationID = ? AND isSupervisor = 0
             ORDER BY serial ASC LIMIT 1");
        mysqli_stmt_bind_param($firstSigStmt, 'i', $leaveAppID);
        mysqli_stmt_execute($firstSigStmt);
        $firstSig = mysqli_fetch_assoc(mysqli_stmt_get_result($firstSigStmt));
        mysqli_stmt_close($firstSigStmt);

        if ($firstSig) {
            $applicantQ = mysqli_query($con, "SELECT employee_name FROM employee_list WHERE id = " . (int)$lja['applicantID']);
            $apName = ($applicantQ && $apRow = mysqli_fetch_assoc($applicantQ)) ? $apRow['employee_name'] : 'কর্মচারী';

            send_notification([user_id_for_employee((int)$firstSig['signatory'])],
                "$apName কর্মস্থলে যোগদানের আবেদন আপনার অনুমোদনের অপেক্ষায়",
                ['type' => 'joining_pending',
                 'link' => "views/leave/approve-joining-application.php?menuslug=leave-joining-approval&joiningID=" . $joiningID,
                 'isImportant' => 1]);
        }
    } catch (\Throwable $e) { /* silent */ }

    mysqli_commit($con);
    mysqli_autocommit($con, true);

    if (function_exists('audit_log')) {
        audit_log('joining_admin_forwarded', [
            'target_type'     => 'leave_joining',
            'target_id'       => $joiningID,
            'organization_id' => $appOrgID ?: null,
            'note'            => ($segTypeChanges ? 'segLeaveType ' . implode(',', $segTypeChanges) . '; ' : '') . 'type=' . $joiningType
                               . '; joiningDate=' . $joiningDate
                               . ($joiningType === 3 ? '; extLT=' . $extLeaveType : '')
                               . (!empty($chainEdit['changed'])
                                   ? '; chain reordered ' . implode(',', $chainEdit['before']) . ' → ' . implode(',', $chainEdit['after'])
                                   : ''),
        ]);
    }

    out(1, 'যোগদান পত্র স্বাক্ষরকারী চেইনে forwarded হয়েছে');

} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    out(0, 'ব্যর্থ: ' . $e->getMessage());
}
