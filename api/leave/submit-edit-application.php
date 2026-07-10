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
$leaveApplicationID = (int)($_POST['leaveApplicationID'] ?? 0);
$adminNote = trim($_POST['adminNote'] ?? '');

if ($leaveApplicationID <= 0)  out(0, 'অবৈধ আবেদন আইডি');
if ($adminNote === '')         out(0, 'সংশোধনের কারণ আবশ্যক');

$segLeaveTypes = $_POST['segment_leaveType'] ?? [];
$segFrom       = $_POST['segment_dateFrom']  ?? [];
$segTo         = $_POST['segment_dateTo']    ?? [];
$segDays       = $_POST['segment_days']      ?? [];

if (!is_array($segLeaveTypes) || count($segLeaveTypes) === 0) {
    out(0, 'কমপক্ষে একটি ছুটির ধরন যোগ করুন');
}

// ── Load + validate the original leave ──
$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($appStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);

if (!$leaveApp)                          out(0, 'আবেদন খুঁজে পাওয়া যায়নি');
if ((int)$leaveApp['status'] !== 1)      out(0, 'শুধু অনুমোদিত ছুটি সংশোধন করা যাবে');

$applicantID  = (int)$leaveApp['applicantID'];
$appOrgID     = (int)$leaveApp['organization_id'];

// ── Block if pending edit already exists ──
$pendStmt = mysqli_prepare($con, "SELECT dataID FROM leave_edit_data WHERE leaveApplicationID = ? AND status = 0 LIMIT 1");
mysqli_stmt_bind_param($pendStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($pendStmt);
$pendRow = mysqli_fetch_assoc(mysqli_stmt_get_result($pendStmt));
mysqli_stmt_close($pendStmt);
if ($pendRow) {
    out(0, 'এই আবেদনের জন্য একটি সংশোধন প্রস্তাব ইতিমধ্যে অনুমোদনের অপেক্ষায় রয়েছে (#' . (int)$pendRow['dataID'] . ')');
}

// ── Authorization: actor must be in the same org or HQ super admin ──
$actorStmt = mysqli_prepare($con,
    "SELECT ul.dataID, ul.user_group_id, ul.isCenterAdmin, ul.organization_id AS ul_org, ul.employee_id,
            el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.dataID = ? LIMIT 1");
mysqli_stmt_bind_param($actorStmt, 'i', $actorUserId);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt));
mysqli_stmt_close($actorStmt);

if (!$actor) out(0, 'ব্যবহারকারী তথ্য পাওয়া যায়নি');

$actorOrgID = (int)($actor['ul_org'] ?: $actor['emp_org']);
$isSuperAdmin = ((int)$actor['user_group_id'] === 1);
$isHQ = ($actorOrgID === 4);
if (!$isSuperAdmin && !$isHQ && $actorOrgID !== $appOrgID) {
    out(0, 'এই আবেদন সংশোধনের অনুমতি নেই');
}

// ── Validate + normalize segments ──
$normSegs = [];
$primaryLeaveType = 0;
for ($i = 0; $i < count($segLeaveTypes); $i++) {
    $lt = (int)$segLeaveTypes[$i];
    $df = trim($segFrom[$i] ?? '');
    $dt = trim($segTo[$i]   ?? '');
    if ($lt <= 0 || $df === '' || $dt === '') continue;

    // dd/mm/yyyy → Y-m-d
    $fromTs = strtotime(str_replace('/', '-', $df));
    $toTs   = strtotime(str_replace('/', '-', $dt));
    if (!$fromTs || !$toTs) {
        // Try parsing dd/mm/yyyy explicitly
        $fp = explode('/', $df); $tp = explode('/', $dt);
        if (count($fp) === 3 && count($tp) === 3) {
            $fromIso = sprintf('%04d-%02d-%02d', (int)$fp[2], (int)$fp[1], (int)$fp[0]);
            $toIso   = sprintf('%04d-%02d-%02d', (int)$tp[2], (int)$tp[1], (int)$tp[0]);
            $fromTs = strtotime($fromIso);
            $toTs   = strtotime($toIso);
        }
    } else {
        $fromIso = date('Y-m-d', $fromTs);
        $toIso   = date('Y-m-d', $toTs);
    }
    if (!$fromTs || !$toTs || $toTs < $fromTs) out(0, 'অবৈধ তারিখ পরিসর (ধরন ' . ($i + 1) . ')');

    $days = (int)$segDays[$i];
    if ($days <= 0) $days = (int)(($toTs - $fromTs) / 86400) + 1;
    if ($days <= 0) out(0, 'অবৈধ দিন সংখ্যা (ধরন ' . ($i + 1) . ')');

    $normSegs[] = [
        'leaveType' => $lt,
        'dateFrom'  => $fromIso,
        'dateTo'    => $toIso,
        'days'      => $days,
        'serial'    => count($normSegs) + 1,
    ];
    if ($primaryLeaveType === 0) $primaryLeaveType = $lt;
}

if (empty($normSegs)) out(0, 'কোনো বৈধ ছুটির অংশ পাওয়া যায়নি');

// ── Build signatory chain (from applicant + primary leave type) ──
$chain = buildSignatoryChain($con, $applicantID, $primaryLeaveType);
if (empty($chain)) {
    out(0, 'এই ছুটির জন্য কোনো অনুমোদন চেইন কনফিগার করা নেই — অ্যাডমিন কে জানান');
}

// ── Handle optional attachment ──
$attachment = null;
if (isset($_FILES['attachment']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $maxSize = 2 * 1024 * 1024;
    $file = $_FILES['attachment'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) out(0, 'অনুমোদিত ফরম্যাট: JPG, JPEG, PNG, PDF');
    if ($file['size'] > $maxSize)  out(0, 'ফাইলের আকার সর্বোচ্চ ২ MB');

    $uniqueName = 'leave_edit_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $uniqueName)) {
        out(0, 'ফাইল আপলোড ব্যর্থ');
    }
    $attachment = $uniqueName;
}

// ── Transaction ──
mysqli_autocommit($con, false);

try {
    $submitDate = todayDate();
    $submitTime = function_exists('ShowBangladeshTime') ? ShowBangladeshTime() : date('Y-m-d H:i:s');
    $adminInitiator = $actorUserId;

    // 1. Parent edit-request row
    $insStmt = mysqli_prepare($con,
        "INSERT INTO leave_edit_data
         (leaveApplicationID, applicantID, organization_id, adminInitiator, adminNote, attachment,
          status, submitBy, submitDate, submitTime)
         VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)");
    mysqli_stmt_bind_param($insStmt, 'iiiisssss',
        $leaveApplicationID, $applicantID, $appOrgID, $adminInitiator, $adminNote, $attachment,
        $actorUserId, $submitDate, $submitTime);
    if (!mysqli_stmt_execute($insStmt)) {
        throw new Exception('Failed to insert edit-request: ' . mysqli_error($con));
    }
    $editRequestID = mysqli_insert_id($con);
    mysqli_stmt_close($insStmt);

    // 2. Insert segments — both 'requested' (frozen original) and 'proposed' (chain-editable copy)
    $segStmt = mysqli_prepare($con,
        "INSERT INTO leave_edit_application_segments
         (editRequestID, kind, leaveType, dateFrom, dateTo, days, serial, createdBy, createdAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($normSegs as $sg) {
        $kindReq = 'requested';
        mysqli_stmt_bind_param($segStmt, 'isissiii',
            $editRequestID, $kindReq, $sg['leaveType'], $sg['dateFrom'], $sg['dateTo'], $sg['days'], $sg['serial'], $actorUserId);
        if (!mysqli_stmt_execute($segStmt)) throw new Exception('Failed to insert requested segment');

        $kindProp = 'proposed';
        mysqli_stmt_bind_param($segStmt, 'isissiii',
            $editRequestID, $kindProp, $sg['leaveType'], $sg['dateFrom'], $sg['dateTo'], $sg['days'], $sg['serial'], $actorUserId);
        if (!mysqli_stmt_execute($segStmt)) throw new Exception('Failed to insert proposed segment');
    }
    mysqli_stmt_close($segStmt);

    // 3. Build approval chain — admin initiator = isSentbyAdmin flag on chain rows
    $chainStmt = mysqli_prepare($con,
        "INSERT INTO leave_edit_data_for_approval
         (editRequestID, signatory, isSentbyAdmin, prevSignatory, isApproved, serial,
          organization_id, department_id, section_id, designation_id, pay_scale)
         VALUES (?, ?, 1, ?, 0, ?, ?, ?, ?, ?, ?)");
    $prevSig = null;
    $serial = 1;
    foreach ($chain as $entry) {
        $sigEmpID = (int)$entry['employeeID'];
        if ($sigEmpID <= 0) continue;

        // Snapshot signatory org details
        $snapStmt = mysqli_prepare($con,
            "SELECT organization_id, department_id, section_id, designation, pay_scale
             FROM employee_list WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($snapStmt, 'i', $sigEmpID);
        mysqli_stmt_execute($snapStmt);
        $snap = mysqli_fetch_assoc(mysqli_stmt_get_result($snapStmt));
        mysqli_stmt_close($snapStmt);

        $sigOrg   = (int)($snap['organization_id'] ?? 0);
        $sigDept  = (int)($snap['department_id'] ?? 0);
        $sigSec   = (int)($snap['section_id'] ?? 0);
        $sigDesig = (int)($snap['designation'] ?? 0);
        $sigPay   = $snap['pay_scale'] ?? '';

        mysqli_stmt_bind_param($chainStmt, 'iiiiiiiis',
            $editRequestID, $sigEmpID, $prevSig, $serial,
            $sigOrg, $sigDept, $sigSec, $sigDesig, $sigPay);
        if (!mysqli_stmt_execute($chainStmt)) {
            throw new Exception('Failed to insert chain row: ' . mysqli_error($con));
        }
        $prevSig = $sigEmpID;
        $serial++;
    }
    mysqli_stmt_close($chainStmt);

    mysqli_commit($con);
    mysqli_autocommit($con, true);

    if (function_exists('audit_log')) {
        audit_log('leave_edit_submitted', [
            'target_type'     => 'leave_edit',
            'target_id'       => $editRequestID,
            'organization_id' => $appOrgID,
            'note'            => 'leaveApplicationID=' . $leaveApplicationID
                                . '; segments=' . count($normSegs)
                                . '; chain=' . ($serial - 1),
        ]);
    }

    out(1, 'সংশোধন প্রস্তাব সফলভাবে প্রেরিত হয়েছে', ['editRequestID' => $editRequestID]);

} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    if ($attachment) {
        $p = __DIR__ . '/../../uploads/' . $attachment;
        if (file_exists($p)) @unlink($p);
    }
    out(0, 'প্রেরণ ব্যর্থ: ' . $e->getMessage());
}
