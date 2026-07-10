<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../bddate.php');
require_once(__DIR__ . '/../../includes/signatory_route_helper.php');
ob_end_clean();

function out($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    out(0, 'আপনি লগইন করেননি!');
}

$actorUserId = (int)$_SESSION['userID'];

$leaveApplicationID   = (int)($_POST['leaveApplicationID'] ?? 0);
$joiningType          = (int)($_POST['joiningType']        ?? 0);
$joiningDateRaw       = trim($_POST['joiningDate']         ?? '');
$reason               = trim($_POST['reason']              ?? '');
$supervisorID         = (int)($_POST['supervisorID']       ?? 0);
$extensionSegmentsRaw = trim($_POST['extensionSegments']   ?? '');

// Backward-compat: Type 3 previously accepted a single `extensionLeaveType`.
// Prefer the new multi-segment `extensionSegments` JSON when present.
$extensionLeaveType   = (int)($_POST['extensionLeaveType'] ?? 0);
$extensionSegments    = [];
if ($extensionSegmentsRaw !== '') {
    $decoded = json_decode($extensionSegmentsRaw, true);
    if (is_array($decoded)) $extensionSegments = $decoded;
}

if ($leaveApplicationID <= 0)                 out(0, 'অবৈধ আবেদন আইডি');
if (!in_array($joiningType, [1, 2, 3], true)) out(0, 'অবৈধ যোগদানের প্রকার');
if ($supervisorID <= 0)                       out(0, 'সুপারভাইজার নির্বাচন করুন');
if ($joiningType === 3) {
    if ($reason === '') out(0, 'বিলম্বের কারণ আবশ্যক');
    // Must have segments — either multi-segment JSON or legacy single type
    if (empty($extensionSegments) && $extensionLeaveType <= 0) {
        out(0, 'বর্ধিত অংশের ছুটির ধরন নির্বাচন করুন');
    }
}

// Load leave application
$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($appStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);
if (!$leaveApp)                          out(0, 'আবেদন খুঁজে পাওয়া যায়নি');
if ((int)$leaveApp['status'] !== 1)      out(0, 'শুধু অনুমোদিত ছুটির জন্য যোগদান পত্র জমা দেওয়া যাবে');

$applicantID = (int)$leaveApp['applicantID'];
$appOrgID    = (int)$leaveApp['organization_id'];

// Block if existing joining application (pending or approved)
$exStmt = mysqli_prepare($con, "SELECT dataID, status FROM leave_joining_application WHERE leaveApplicationID = ? ORDER BY dataID DESC LIMIT 1");
mysqli_stmt_bind_param($exStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($exStmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($exStmt));
mysqli_stmt_close($exStmt);
if ($existing) {
    $s = (int)$existing['status'];
    if ($s === 0) out(0, 'এই ছুটির জন্য একটি যোগদান পত্র ইতিমধ্যে অপেক্ষমান');
    if ($s === 1) out(0, 'এই ছুটির জন্য যোগদান পত্র ইতিমধ্যে অনুমোদিত');
}

// Determine joining date
$approvedDateFrom = $leaveApp['approvedDateFrom'] ?: $leaveApp['dateFrom'];
$approvedDateTo   = $leaveApp['approvedDateTo']   ?: $leaveApp['dateTo'];
if (!$approvedDateFrom || !$approvedDateTo) out(0, 'অনুমোদিত তারিখ পাওয়া যায়নি');

if ($joiningType === 1) {
    // Type 1: joining date = approvedDateTo (ignore user input)
    $joiningDateIso = $approvedDateTo;
} else {
    // Type 2/3: parse user-provided ISO date (form converts dd/mm/yyyy → yyyy-mm-dd before submit)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $joiningDateRaw)) out(0, 'অবৈধ তারিখ ফরম্যাট');
    $joiningDateIso = $joiningDateRaw;
    $joinTs = strtotime($joiningDateIso);
    $approvedToTs = strtotime($approvedDateTo);
    $approvedFromTs = strtotime($approvedDateFrom);
    // Convention: joiningDate = last leave day (inclusive)
    if ($joiningType === 2) {
        // Type 2: joining must be earlier than original approved end (else it's Type 1) and within leave start
        if ($joinTs >= $approvedToTs) out(0, 'অগ্রিম যোগদানের জন্য তারিখ অনুমোদিত শেষ তারিখের আগে হতে হবে');
        if ($joinTs < $approvedFromTs) out(0, 'যোগদানের তারিখ ছুটির শুরুর আগে হতে পারবে না');
    } else if ($joiningType === 3) {
        // Type 3: joining must be after original approved end (extension)
        if ($joinTs <= $approvedToTs) out(0, 'বর্ধিত যোগদানের জন্য তারিখ অনুমোদিত শেষ তারিখের পরে হতে হবে');
    }
}

// Type 3 extension segments validation — sum days = extension total, contiguous, valid leave types
$extensionSegmentsJson = null;
if ($joiningType === 3) {
    $extStartIso = date('Y-m-d', strtotime($approvedDateTo . ' +1 day'));
    $extEndIso   = $joiningDateIso;
    $extTotalDays = (int)floor((strtotime($extEndIso) - strtotime($extStartIso)) / 86400) + 1;

    if (!empty($extensionSegments)) {
        // Multi-segment path
        $sumDays = 0;
        $cursor = $extStartIso;
        foreach ($extensionSegments as $i => $seg) {
            $lt = (int)($seg['leaveType'] ?? 0);
            $df = (string)($seg['dateFrom'] ?? '');
            $dt = (string)($seg['dateTo']   ?? '');
            $dd = (int)($seg['days'] ?? 0);
            if ($lt <= 0) out(0, 'সারি ' . ($i + 1) . ': ছুটির ধরন নির্বাচন করুন');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $df) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
                out(0, 'সারি ' . ($i + 1) . ': অবৈধ তারিখ');
            }
            if ($dd <= 0) out(0, 'সারি ' . ($i + 1) . ': দিন সংখ্যা সঠিক নয়');
            $ddCalc = (int)floor((strtotime($dt) - strtotime($df)) / 86400) + 1;
            if ($ddCalc !== $dd) out(0, 'সারি ' . ($i + 1) . ': দিন সংখ্যা ও তারিখের গড়মিল');
            if ($df !== $cursor) out(0, 'সারি ' . ($i + 1) . ': তারিখ চেইন বিচ্ছিন্ন');
            $cursor = date('Y-m-d', strtotime($dt . ' +1 day'));
            $sumDays += $dd;
        }
        if ($sumDays !== $extTotalDays) {
            out(0, 'সারিগুলোর মোট দিন (' . $sumDays . ') বর্ধিত মোট দিনের (' . $extTotalDays . ') সমান হতে হবে');
        }
        $extensionSegmentsJson = json_encode($extensionSegments, JSON_UNESCAPED_UNICODE);
        // Backward-compat: first segment's leaveType goes into approvedLeaveType
        $extensionLeaveType = (int)$extensionSegments[0]['leaveType'];
    } else {
        // Legacy single-type path — synthesize a single-segment JSON so the
        // approval backend uniformly handles both shapes.
        $syntheticSeg = [[
            'leaveType' => $extensionLeaveType,
            'dateFrom'  => $extStartIso,
            'dateTo'    => $extEndIso,
            'days'      => $extTotalDays,
        ]];
        $extensionSegmentsJson = json_encode($syntheticSeg, JSON_UNESCAPED_UNICODE);
    }
}

// Verify supervisor is a real employee in the applicant's org
$supChkStmt = mysqli_prepare($con, "SELECT id FROM employee_list WHERE id = ? AND organization_id = ? AND employment_status = 1 AND pending_section_assignment = 0");
mysqli_stmt_bind_param($supChkStmt, 'ii', $supervisorID, $appOrgID);
mysqli_stmt_execute($supChkStmt);
$supChk = mysqli_fetch_assoc(mysqli_stmt_get_result($supChkStmt));
mysqli_stmt_close($supChkStmt);
if (!$supChk) out(0, 'সুপারভাইজার এই কেন্দ্রের কর্মী নন');

// Build signatory chain (use the leave's original leaveType)
$chainLeaveType = (int)$leaveApp['leaveType'];
$chain = buildSignatoryChain($con, $applicantID, $chainLeaveType);
if (empty($chain)) {
    out(0, 'এই ছুটির জন্য কোনো অনুমোদন চেইন কনফিগার করা নেই — অ্যাডমিন কে জানান');
}

// Handle attachment
$attachment = null;
if (isset($_FILES['attachment']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $maxSize = 2 * 1024 * 1024;
    $file = $_FILES['attachment'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) out(0, 'অনুমোদিত ফরম্যাট: JPG, JPEG, PNG, PDF');
    if ($file['size'] > $maxSize)  out(0, 'ফাইলের আকার সর্বোচ্চ ২ MB');

    $uniqueName = 'joining_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $uniqueName)) {
        out(0, 'ফাইল আপলোড ব্যর্থ');
    }
    $attachment = $uniqueName;
}

// Resolve applicant's signature blob (from user_list if exists)
$appSig = null;
$sigQ = mysqli_prepare($con, "SELECT signature FROM user_list WHERE employee_id = ? LIMIT 1");
mysqli_stmt_bind_param($sigQ, 'i', $applicantID);
mysqli_stmt_execute($sigQ);
$sigRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sigQ));
mysqli_stmt_close($sigQ);
$appSig = $sigRow['signature'] ?? null;

mysqli_autocommit($con, false);

try {
    $submitDate = todayDate();
    $submitTime = function_exists('ShowBangladeshTime') ? ShowBangladeshTime() : date('Y-m-d H:i:s');

    // 1. Insert parent leave_joining_application row
    $insStmt = mysqli_prepare($con,
        "INSERT INTO leave_joining_application
         (leaveApplicationID, joiningType, reason, submitDate, submitTime, submitBy,
          requestedJoiningDate, applicantID, organization_id, attachment,
          approvedLeaveType, extensionSegmentsJson, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    mysqli_stmt_bind_param($insStmt, 'iisssisiisis',
        $leaveApplicationID, $joiningType, $reason, $submitDate, $submitTime, $actorUserId,
        $joiningDateIso, $applicantID, $appOrgID, $attachment,
        $extensionLeaveType, $extensionSegmentsJson);
    if (!mysqli_stmt_execute($insStmt)) {
        throw new Exception('Failed to insert joining application: ' . mysqli_error($con));
    }
    $joiningID = mysqli_insert_id($con);
    mysqli_stmt_close($insStmt);

    // 2. Save applicant signature blob (if any)
    if ($appSig !== null) {
        $sigUpd = mysqli_prepare($con, "UPDATE leave_joining_application SET applicantSignature = ? WHERE dataID = ?");
        $null = null;
        mysqli_stmt_bind_param($sigUpd, 'bi', $null, $joiningID);
        mysqli_stmt_send_long_data($sigUpd, 0, $appSig);
        mysqli_stmt_execute($sigUpd);
        mysqli_stmt_close($sigUpd);
    }

    // 3. Insert supervisor row (serial=1, isSupervisor=1)
    $supSnapStmt = mysqli_prepare($con,
        "SELECT organization_id, department_id, section_id, designation, pay_scale
         FROM employee_list WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($supSnapStmt, 'i', $supervisorID);
    mysqli_stmt_execute($supSnapStmt);
    $supSnap = mysqli_fetch_assoc(mysqli_stmt_get_result($supSnapStmt));
    mysqli_stmt_close($supSnapStmt);

    $supRowStmt = mysqli_prepare($con,
        "INSERT INTO leave_joining_data_for_approval
         (leaveApplicationID, signatory, isSupervisor, isSentbyAdmin, prevSignatory, isApproved, serial,
          organization_id, department_id, section_id, designation_id, pay_scale)
         VALUES (?, ?, 1, 0, 0, 0, 1, ?, ?, ?, ?, ?)");
    $sOrg   = (int)($supSnap['organization_id'] ?? 0);
    $sDept  = (int)($supSnap['department_id'] ?? 0);
    $sSec   = (int)($supSnap['section_id'] ?? 0);
    $sDesig = (int)($supSnap['designation'] ?? 0);
    $sPay   = $supSnap['pay_scale'] ?? '';
    mysqli_stmt_bind_param($supRowStmt, 'iiiiiis',
        $leaveApplicationID, $supervisorID, $sOrg, $sDept, $sSec, $sDesig, $sPay);
    if (!mysqli_stmt_execute($supRowStmt)) {
        throw new Exception('Failed to insert supervisor row: ' . mysqli_error($con));
    }
    mysqli_stmt_close($supRowStmt);

    // 4. Insert routed chain rows (serial = 2..N), each gated by isSentbyAdmin=0 until admin forwards
    $chainStmt = mysqli_prepare($con,
        "INSERT INTO leave_joining_data_for_approval
         (leaveApplicationID, signatory, isSupervisor, isSentbyAdmin, prevSignatory, isApproved, serial,
          organization_id, department_id, section_id, designation_id, pay_scale)
         VALUES (?, ?, 0, 0, ?, 0, ?, ?, ?, ?, ?, ?)");
    $prevSig = $supervisorID;
    $serial = 2;
    foreach ($chain as $entry) {
        $sigEmpID = (int)$entry['employeeID'];
        if ($sigEmpID <= 0) { $serial++; continue; }
        if ($sigEmpID === $supervisorID) { continue; } // skip dup if supervisor also in chain

        $snapStmt = mysqli_prepare($con,
            "SELECT organization_id, department_id, section_id, designation, pay_scale
             FROM employee_list WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($snapStmt, 'i', $sigEmpID);
        mysqli_stmt_execute($snapStmt);
        $snap = mysqli_fetch_assoc(mysqli_stmt_get_result($snapStmt));
        mysqli_stmt_close($snapStmt);
        $sigOrg   = (int)($snap['organization_id'] ?? 0);
        $sigDept  = (int)($snap['department_id']   ?? 0);
        $sigSec   = (int)($snap['section_id']      ?? 0);
        $sigDesig = (int)($snap['designation']     ?? 0);
        $sigPay   = $snap['pay_scale']             ?? '';

        mysqli_stmt_bind_param($chainStmt, 'iiiiiiiis',
            $leaveApplicationID, $sigEmpID, $prevSig, $serial,
            $sigOrg, $sigDept, $sigSec, $sigDesig, $sigPay);
        if (!mysqli_stmt_execute($chainStmt)) {
            throw new Exception('Failed to insert chain row: ' . mysqli_error($con));
        }
        $prevSig = $sigEmpID;
        $serial++;
    }
    mysqli_stmt_close($chainStmt);

    // 5. Notification to supervisor
    $supUserQ = mysqli_prepare($con, "SELECT dataID FROM user_list WHERE employee_id = ? LIMIT 1");
    mysqli_stmt_bind_param($supUserQ, 'i', $supervisorID);
    mysqli_stmt_execute($supUserQ);
    $supUserRow = mysqli_fetch_assoc(mysqli_stmt_get_result($supUserQ));
    mysqli_stmt_close($supUserQ);
    if ($supUserRow) {
        $applicantNameQ = mysqli_prepare($con, "SELECT employee_name FROM employee_list WHERE id = ?");
        mysqli_stmt_bind_param($applicantNameQ, 'i', $applicantID);
        mysqli_stmt_execute($applicantNameQ);
        $apName = mysqli_fetch_assoc(mysqli_stmt_get_result($applicantNameQ))['employee_name'] ?? '';
        mysqli_stmt_close($applicantNameQ);

        $msg = $apName . ' কর্মস্থলে যোগদানের আবেদন করেছেন।';
        $type = "<span class='badge badge-primary'>কর্মস্থলে যোগদান পত্র</span>";
        $link = "views/leave/approve-joining-application.php?menuslug=leave-joining-approval&joiningID=" . $joiningID;
        $dt = function_exists('ShowBangladeshTime') ? ShowBangladeshTime() : date('Y-m-d H:i:s');

        $noteStmt = mysqli_prepare($con,
            "INSERT INTO notification (userID, message, notificationType, link, dateTime, isImportant)
             VALUES (?, ?, ?, ?, ?, 1)");
        $supUserID = (int)$supUserRow['dataID'];
        mysqli_stmt_bind_param($noteStmt, 'issss', $supUserID, $msg, $type, $link, $dt);
        @mysqli_stmt_execute($noteStmt);
        mysqli_stmt_close($noteStmt);
    }

    mysqli_commit($con);
    mysqli_autocommit($con, true);

    if (function_exists('audit_log')) {
        audit_log('joining_submitted', [
            'target_type'     => 'leave_joining',
            'target_id'       => $joiningID,
            'organization_id' => $appOrgID,
            'note'            => 'leaveApplicationID=' . $leaveApplicationID
                               . '; type=' . $joiningType
                               . '; joiningDate=' . $joiningDateIso
                               . '; chain=' . ($serial - 2),
        ]);
    }

    out(1, 'যোগদান পত্র সফলভাবে প্রেরিত হয়েছে', ['joiningID' => $joiningID]);

} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    if ($attachment) {
        $p = __DIR__ . '/../../uploads/' . $attachment;
        if (file_exists($p)) @unlink($p);
    }
    out(0, 'প্রেরণ ব্যর্থ: ' . $e->getMessage());
}
