<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(__DIR__ . '/../../includes/optional_pre_approval_helper.php');
ob_end_clean();

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি']);
    exit;
}

$actorUserID = (int)$_SESSION['userID'];
$year        = (int)($_POST['year']           ?? 0);
$days        = (float)($_POST['requested_days'] ?? 0);

// festival_notes may come as an array (multi-select) or a legacy string
$rawNotes = $_POST['festival_notes'] ?? '';
if (is_array($rawNotes)) {
    $rawNotes = array_filter(array_map('trim', $rawNotes), function($v) { return $v !== ''; });
    $notes = implode(', ', $rawNotes);
} else {
    $notes = trim($rawNotes);
}

// Derive employee_id from the logged-in user
$_meStmt = mysqli_prepare($con, "SELECT employee_id FROM user_list WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($_meStmt, 'i', $actorUserID);
mysqli_stmt_execute($_meStmt);
$_me = mysqli_fetch_assoc(mysqli_stmt_get_result($_meStmt)) ?: [];
mysqli_stmt_close($_meStmt);
$employeeID = (int)($_me['employee_id'] ?? 0);

if ($employeeID <= 0) { echo json_encode(['status'=>0,'message'=>'আপনার account-এর সাথে কর্মচারী রেকর্ড লিংক নেই']); exit; }
if ($year < 2020 || $year > 2100) { echo json_encode(['status'=>0,'message'=>'বছর সঠিক নয়']); exit; }
if ($days <= 0 || $days > 3) { echo json_encode(['status'=>0,'message'=>'দিন ০.৫ থেকে ৩-এর মধ্যে হতে হবে']); exit; }

$empQ = mysqli_prepare($con,
    "SELECT id, organization_id, section_id, designation, supervisor, employment_status, pending_section_assignment
     FROM employee_list WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($empQ, 'i', $employeeID);
mysqli_stmt_execute($empQ);
$emp = mysqli_fetch_assoc(mysqli_stmt_get_result($empQ));
mysqli_stmt_close($empQ);
if (!$emp) { echo json_encode(['status'=>0,'message'=>'কর্মচারী পাওয়া যায়নি']); exit; }
if ((int)$emp['employment_status'] !== 1 || (int)$emp['pending_section_assignment'] === 1) {
    echo json_encode(['status'=>0,'message'=>'এই কর্মচারী active নন বা section অপেক্ষমান']);
    exit;
}

$empOrg = (int)$emp['organization_id'];
$empSec = (int)$emp['section_id'];
$empDes = (int)$emp['designation'];

// Applicant picks supervisor from a Select2 of active employees in their org.
// Fallback: employee_list.supervisor (legacy default).
$supervisorID = (int)($_POST['supervisorID'] ?? 0);
if ($supervisorID <= 0) {
    $supervisorID = (int)$emp['supervisor'];
}
if ($supervisorID <= 0) {
    echo json_encode(['status'=>0,'message'=>'সুপারভাইজার নির্বাচন করুন']);
    exit;
}
if ($supervisorID === $employeeID) {
    echo json_encode(['status'=>0,'message'=>'নিজেকে সুপারভাইজার হিসেবে নির্বাচন করা যাবে না']);
    exit;
}

// Validate: supervisor must be an active employee in the applicant's org
$supChkQ = mysqli_prepare($con,
    "SELECT id FROM employee_list
     WHERE id = ? AND employment_status = 1 AND organization_id = ?
     LIMIT 1");
mysqli_stmt_bind_param($supChkQ, 'ii', $supervisorID, $empOrg);
mysqli_stmt_execute($supChkQ);
$supChk = mysqli_fetch_assoc(mysqli_stmt_get_result($supChkQ));
mysqli_stmt_close($supChkQ);
if (!$supChk) {
    echo json_encode(['status'=>0,'message'=>'নির্বাচিত সুপারভাইজার এই কেন্দ্রের সক্রিয় কর্মচারী নন']);
    exit;
}

// Uniqueness — one pre-approval per employee per year
$dupChk = mysqli_prepare($con,
    "SELECT id, status FROM optional_leave_pre_approval WHERE employee_id = ? AND year = ? LIMIT 1");
mysqli_stmt_bind_param($dupChk, 'ii', $employeeID, $year);
mysqli_stmt_execute($dupChk);
$dup = mysqli_fetch_assoc(mysqli_stmt_get_result($dupChk));
mysqli_stmt_close($dupChk);
if ($dup) {
    $statusText = ['অপেক্ষমান', 'অনুমোদিত', 'প্রত্যাখ্যাত'][$dup['status']] ?? 'unknown';
    echo json_encode(['status'=>0, 'message'=>"এই বছরের ($year) জন্য ইতিমধ্যে একটি আবেদন আছে ($statusText) — একই বছরে একাধিকবার আবেদন করা যাবে না"]);
    exit;
}

// Attachment upload
$attachment = '';
if (isset($_FILES['attachment']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
    $allowed = ['jpg','jpeg','png','pdf'];
    $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) { echo json_encode(['status'=>0,'message'=>'JPG/PNG/PDF অনুমোদিত']); exit; }
    if ($_FILES['attachment']['size'] > 2 * 1024 * 1024) { echo json_encode(['status'=>0,'message'=>'সর্বোচ্চ ২ MB']); exit; }
    $unique = 'opa_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $dir = __DIR__ . '/../../uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $unique)) {
        $attachment = $unique;
    }
}

mysqli_autocommit($con, false);
try {
    // 1. Insert parent row
    $ins = mysqli_prepare($con,
        "INSERT INTO optional_leave_pre_approval
         (employee_id, year, requested_days, festival_notes, attachment, status,
          submit_date, created_by, organization_id, section_id, designation_id)
         VALUES (?, ?, ?, ?, ?, 0, NOW(), ?, ?, ?, ?)");
    mysqli_stmt_bind_param($ins, 'iidssiiii',
        $employeeID, $year, $days, $notes, $attachment,
        $actorUserID, $empOrg, $empSec, $empDes);
    if (!mysqli_stmt_execute($ins)) throw new Exception('pre_approval insert failed: ' . mysqli_error($con));
    $preApprovalID = mysqli_insert_id($con);
    mysqli_stmt_close($ins);

    // 2. Insert supervisor row (serial=1, isSentbyAdmin=0)
    $supQ = mysqli_prepare($con,
        "SELECT organization_id, department_id, section_id, designation, pay_scale
         FROM employee_list WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($supQ, 'i', $supervisorID);
    mysqli_stmt_execute($supQ);
    $supSnap = mysqli_fetch_assoc(mysqli_stmt_get_result($supQ)) ?: [];
    mysqli_stmt_close($supQ);

    $sOrg   = (int)($supSnap['organization_id'] ?? 0);
    $sDept  = (int)($supSnap['department_id']   ?? 0);
    $sSec   = (int)($supSnap['section_id']      ?? 0);
    $sDesig = (int)($supSnap['designation']     ?? 0);
    $sPay   = (string)($supSnap['pay_scale']    ?? '');

    $supIns = mysqli_prepare($con,
        "INSERT INTO optional_leave_pre_approval_signatory
         (preApprovalID, signatory, prevSignatory, isSupervisor, isSentbyAdmin, serial,
          organization_id, department_id, section_id, designation_id, pay_scale)
         VALUES (?, ?, NULL, 1, 0, 1, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($supIns, 'iiiiiis',
        $preApprovalID, $supervisorID,
        $sOrg, $sDept, $sSec, $sDesig, $sPay);
    if (!mysqli_stmt_execute($supIns)) throw new Exception('supervisor signatory failed: ' . mysqli_error($con));
    mysqli_stmt_close($supIns);

    // 3. Build & insert the rest of the chain (from grade-based routing rules).
    //    All rows have isSentbyAdmin=0 — chain is gated until admin forwards.
    //    leaveType = NULL for optional pre-approval → matches "leave_type_id IS NULL" rules.
    insertOptionalPreApprovalChain($con, $preApprovalID, $employeeID, $supervisorID, null);

    mysqli_commit($con);
    mysqli_autocommit($con, true);

    if (function_exists('audit_log')) {
        audit_log('optional_pre_approval_submitted', [
            'target_type'     => 'optional_pre_approval',
            'target_id'       => (int)$preApprovalID,
            'organization_id' => $empOrg,
            'note'            => "employee=$employeeID; year=$year; days=$days",
        ]);
    }

    echo json_encode(['status'=>1, 'message'=>'পূর্বানুমোদন আবেদন সফলভাবে জমা হয়েছে', 'id'=>(int)$preApprovalID]);
} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    if ($attachment && file_exists(__DIR__ . '/../../uploads/' . $attachment)) {
        unlink(__DIR__ . '/../../uploads/' . $attachment);
    }
    echo json_encode(['status'=>0, 'message'=>'সংরক্ষণ ব্যর্থ: ' . $e->getMessage()]);
}
