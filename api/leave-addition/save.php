<?php
session_start();

header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../bddate.php');
ob_end_clean();

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

if (empty($_POST['employeeID'])) {
    echo json_encode(['status' => 0, 'message' => 'কর্মচারী নির্বাচন করুন!']);
    exit;
}

// Multi-row payload: leaveType[], leaveAdd[], note[] arrays. Fall back to
// single-value shape for backward compat with any legacy callers.
$leaveTypes = isset($_POST['leaveType']) ? (array)$_POST['leaveType'] : [];
$leaveDays  = isset($_POST['leaveAdd'])  ? (array)$_POST['leaveAdd']  : [];
$notes      = isset($_POST['note'])      ? (array)$_POST['note']      : [];
$signatoryID = isset($_POST['signatory_id']) ? (int)$_POST['signatory_id'] : 0;

if (empty($leaveTypes) || empty($leaveDays) || empty($notes)) {
    echo json_encode(['status' => 0, 'message' => 'ছুটির এন্ট্রি প্রদান করুন!']);
    exit;
}
if (count($leaveTypes) !== count($leaveDays) || count($leaveTypes) !== count($notes)) {
    echo json_encode(['status' => 0, 'message' => 'এন্ট্রি ডেটা অসম্পূর্ণ']);
    exit;
}

$createdBy  = $_SESSION['userID'];
$submitDate = todayDate();
$employeeID = intval($_POST['employeeID']);

// Signatory selection is mandatory and cannot be the target employee themselves.
if ($signatoryID <= 0) {
    echo json_encode(['status'=>0, 'message'=>'স্বাক্ষরকারী নির্বাচন করুন']);
    exit;
}
if ($signatoryID === $employeeID) {
    echo json_encode(['status'=>0, 'message'=>'কর্মচারী নিজে স্বাক্ষরকারী হতে পারবেন না']);
    exit;
}
// Verify signatory belongs to the same org as the target employee
$sigChk = mysqli_prepare($con,
    "SELECT s.organization_id AS sig_org, e.organization_id AS emp_org
     FROM employee_list s, employee_list e
     WHERE s.id = ? AND e.id = ? AND s.employment_status = 1 AND s.pending_section_assignment = 0
     LIMIT 1");
mysqli_stmt_bind_param($sigChk, 'ii', $signatoryID, $employeeID);
mysqli_stmt_execute($sigChk);
$sigChkRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sigChk));
mysqli_stmt_close($sigChk);
if (!$sigChkRow) {
    echo json_encode(['status'=>0, 'message'=>'স্বাক্ষরকারী active নন']);
    exit;
}
if ((int)$sigChkRow['sig_org'] !== (int)$sigChkRow['emp_org']) {
    echo json_encode(['status'=>0, 'message'=>'স্বাক্ষরকারী একই কেন্দ্রের হতে হবে']);
    exit;
}

// Resolve user's allowed org
$orgStmt = $con->prepare("SELECT isCenterAdmin, organization_id, employee_id FROM user_list WHERE user_id = ?");
$orgStmt->bind_param("s", $_SESSION['username']);
$orgStmt->execute();
$orgUserRow = $orgStmt->get_result()->fetch_assoc();
$orgStmt->close();
if (!empty($orgUserRow['isCenterAdmin'])) {
    $allowedOrgID = intval($orgUserRow['organization_id']);
} elseif (!empty($orgUserRow['employee_id'])) {
    $empOrgStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $empOrgStmt->bind_param("i", $orgUserRow['employee_id']);
    $empOrgStmt->execute();
    $empOrgRow = $empOrgStmt->get_result()->fetch_assoc();
    $empOrgStmt->close();
    $allowedOrgID = intval($empOrgRow['organization_id'] ?? 0);
} else {
    $allowedOrgID = 0; // HQ/superadmin — no restriction
}

// Validate employee belongs to user's center
if ($allowedOrgID > 0) {
    $empCheckStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ? AND employment_status = 1 AND pending_section_assignment = 0");
    $empCheckStmt->bind_param("i", $employeeID);
    $empCheckStmt->execute();
    $empCheckRow = $empCheckStmt->get_result()->fetch_assoc();
    $empCheckStmt->close();
    if (!$empCheckRow || intval($empCheckRow['organization_id']) !== $allowedOrgID) {
        echo json_encode(['status' => 0, 'message' => 'এই কর্মচারীর তথ্য পরিবর্তন করার আপনার অনুমতি নেই!']);
        exit;
    }
}

// Normalize + validate each row before we touch anything
$rows = [];
foreach ($leaveTypes as $i => $lt) {
    $type = intval($lt);
    $days = floatval($leaveDays[$i] ?? 0);
    $rn   = trim($notes[$i] ?? '');
    if ($type <= 0)  { echo json_encode(['status' => 0, 'message' => 'সারি ' . ($i+1) . ': ছুটির ধরন নির্বাচন করুন']); exit; }
    if ($days <= 0)  { echo json_encode(['status' => 0, 'message' => 'সারি ' . ($i+1) . ': দিন সংখ্যা সঠিক নয়']); exit; }
    if ($rn === '')  { echo json_encode(['status' => 0, 'message' => 'সারি ' . ($i+1) . ': মন্তব্য প্রদান করুন']); exit; }
    $rows[] = ['type' => $type, 'days' => $days, 'note' => $rn];
}

// Handle optional file upload
$attachment = '';
if (isset($_FILES['officeAdesh']) && is_uploaded_file($_FILES['officeAdesh']['tmp_name'])) {
    $allowedExtensions = ['jpeg', 'jpg', 'png', 'pdf'];
    $maxFileSize = 2097152; // 2MB

    $file     = $_FILES['officeAdesh'];
    $fileExt  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExtensions)) {
        echo json_encode(['status' => 0, 'message' => 'অনুমোদিত ফাইল ফরম্যাট: JPEG, JPG, PNG, PDF']);
        exit;
    }

    if ($file['size'] > $maxFileSize) {
        echo json_encode(['status' => 0, 'message' => 'ফাইলের আকার সর্বোচ্চ ২ MB হতে হবে!']);
        exit;
    }

    $uniqueFileName = time() . '_' . mt_rand(1000, 9999) . '.' . $fileExt;
    $uploadPath     = __DIR__ . '/../../uploads/' . $uniqueFileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['status' => 0, 'message' => 'ফাইল আপলোড করতে ব্যর্থ হয়েছে!']);
        exit;
    }

    $attachment = $uniqueFileName;
}

// Each office-order submission gets one batch_id — approval UI collapses all
// rows sharing this id into a single card so approvers act once, not N times.
$batchId = bin2hex(random_bytes(16));

// Insert every row inside a single transaction so partial failures roll back
// and don't leave the office order attachment orphaned to half the rows.
$insertStmt = $con->prepare(
    "INSERT INTO leave_addition_history
     (employeeID, leaveID, leaveAddition, note, attachment, batch_id, override_signatory_id, isApproved, createDate, createdBy)
     VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)"
);
if (!$insertStmt) {
    if ($attachment && file_exists(__DIR__ . '/../../uploads/' . $attachment)) {
        unlink(__DIR__ . '/../../uploads/' . $attachment);
    }
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

$con->autocommit(false);
$insertedIDs = [];
$allOK = true;
foreach ($rows as $r) {
    $t = $r['type']; $d = $r['days']; $n = $r['note'];
    $insertStmt->bind_param("iidsssisi", $employeeID, $t, $d, $n, $attachment, $batchId, $signatoryID, $submitDate, $createdBy);
    if (!$insertStmt->execute()) { $allOK = false; break; }
    $insertedIDs[] = $con->insert_id;
}
$insertStmt->close();

if ($allOK) {
    $con->commit();
    $con->autocommit(true);

    if (function_exists('audit_log')) {
        foreach ($rows as $idx => $r) {
            audit_log('leave_addition_submitted', [
                'target_type'     => 'leave_addition',
                'target_id'       => (int)($insertedIDs[$idx] ?? 0),
                'organization_id' => $allowedOrgID > 0 ? $allowedOrgID : null,
                'note'            => 'employeeID=' . $employeeID . '; leaveID=' . $r['type'] . '; days=' . $r['days'],
            ]);
        }
    }

    $n = count($insertedIDs);
    echo json_encode(['status' => 1, 'message' => $n . ' টি ছুটি যোগ সফলভাবে সংরক্ষণ হয়েছে']);
} else {
    $con->rollback();
    $con->autocommit(true);
    if ($attachment && file_exists(__DIR__ . '/../../uploads/' . $attachment)) {
        unlink(__DIR__ . '/../../uploads/' . $attachment);
    }
    echo json_encode(['status' => 0, 'message' => 'ছুটি যোগ সংরক্ষণ করতে ব্যর্থ হয়েছে!']);
}

$con->close();
?>
