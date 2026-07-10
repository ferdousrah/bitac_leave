<?php
// Start session first
session_start();

// Set JSON header
header('Content-Type: application/json');

// Start output buffering
ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../bddate.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate required inputs
if (!isset($_POST['employeeID']) || empty($_POST['employeeID'])) {
    echo json_encode(['status' => 0, 'message' => 'কর্মচারী নির্বাচন করুন!']);
    exit;
}

// Multi-row payload: leaveType[], leaveDeduct[], note[], maturity_date[] arrays.
// leaveType[] may contain the sentinel string "shranti" — those rows deduct
// from গড় বেতন (leaveID=1) AND get an extra recreation_leave_history entry.
$leaveTypes   = isset($_POST['leaveType'])    ? (array)$_POST['leaveType']    : [];
$leaveDays    = isset($_POST['leaveDeduct'])  ? (array)$_POST['leaveDeduct']  : [];
$notes        = isset($_POST['note'])         ? (array)$_POST['note']         : [];
$maturityDates= isset($_POST['maturity_date'])? (array)$_POST['maturity_date']: [];
$signatoryID  = isset($_POST['signatory_id']) ? (int)$_POST['signatory_id']   : 0;

if (empty($leaveTypes) || empty($leaveDays) || empty($notes)) {
    echo json_encode(['status' => 0, 'message' => 'ছুটির এন্ট্রি প্রদান করুন!']);
    exit;
}
if (count($leaveTypes) !== count($leaveDays) || count($leaveTypes) !== count($notes)) {
    echo json_encode(['status' => 0, 'message' => 'এন্ট্রি ডেটা অসম্পূর্ণ']);
    exit;
}

// Get form data
$createdBy = $_SESSION['userID'];
$submitDate = todayDate();
$employeeID = intval($_POST['employeeID']);

// Signatory validation — mandatory, cannot be the target employee themselves,
// must belong to the same org.
if ($signatoryID <= 0) {
    echo json_encode(['status'=>0, 'message'=>'স্বাক্ষরকারী নির্বাচন করুন']);
    exit;
}
if ($signatoryID === $employeeID) {
    echo json_encode(['status'=>0, 'message'=>'কর্মচারী নিজে স্বাক্ষরকারী হতে পারবেন না']);
    exit;
}
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

// Validate that the selected employee belongs to the user's center
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
// Normalize + validate each row up front
$rows = [];
foreach ($leaveTypes as $i => $lt) {
    $rawType = trim((string)$lt);
    $isShranti = ($rawType === 'shranti');
    $type = $isShranti ? 1 : intval($rawType); // shranti deducts from গড় বেতন (leaveID=1)
    $days = floatval($leaveDays[$i] ?? 0);
    $rn   = trim($notes[$i] ?? '');
    $maturity = trim($maturityDates[$i] ?? '');

    if ($type <= 0) { echo json_encode(['status' => 0, 'message' => 'সারি ' . ($i+1) . ': ছুটির ধরন নির্বাচন করুন']); exit; }
    if ($days <= 0) { echo json_encode(['status' => 0, 'message' => 'সারি ' . ($i+1) . ': দিন সংখ্যা সঠিক নয়']); exit; }
    if ($rn === '') { echo json_encode(['status' => 0, 'message' => 'সারি ' . ($i+1) . ': মন্তব্য প্রদান করুন']); exit; }
    if ($isShranti) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maturity)) {
            echo json_encode(['status' => 0, 'message' => 'সারি ' . ($i+1) . ': পরবর্তী শ্রান্তি বিনোদন ম্যাচিউর তারিখ প্রদান করুন']);
            exit;
        }
    }
    $rows[] = [
        'type' => $type, 'days' => $days, 'note' => $rn,
        'is_shranti' => $isShranti,
        'maturity' => $isShranti ? $maturity : null,
    ];
}

// Handle file upload
$officeAdesh = '';
$allowedExtensions = array("jpeg", "jpg", "png", "pdf");
$maxFileSize = 2097152; // 2MB

if (!isset($_FILES['officeAdesh']) || !is_uploaded_file($_FILES['officeAdesh']['tmp_name'])) {
    echo json_encode(['status' => 0, 'message' => 'অফিস আদেশ ফাইল আপলোড করুন!']);
    exit;
}

$file = $_FILES['officeAdesh'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmp = $file['tmp_name'];
$fileExtArray = explode('.', $fileName);
$fileExt = strtolower(end($fileExtArray));

// Validate file extension
if (!in_array($fileExt, $allowedExtensions)) {
    echo json_encode(['status' => 0, 'message' => 'অনুমোদিত ফাইল ফরম্যাট: JPEG, JPG, PNG, PDF']);
    exit;
}

// Validate file size
if ($fileSize > $maxFileSize) {
    echo json_encode(['status' => 0, 'message' => 'ফাইলের আকার সর্বোচ্চ ২ MB হতে হবে!']);
    exit;
}

// Generate unique filename to prevent overwriting
$uniqueFileName = time() . '_' . mt_rand(1000, 9999) . '.' . $fileExt;
$uploadPath = __DIR__ . '/../../uploads/' . $uniqueFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmp, $uploadPath)) {
    echo json_encode(['status' => 0, 'message' => 'ফাইল আপলোড করতে ব্যর্থ হয়েছে!']);
    exit;
}

$officeAdesh = $uniqueFileName;

// Each office-order submission gets one batch_id — approval UI collapses all
// rows sharing this id into a single card so approvers act once, not N times.
$batchId = bin2hex(random_bytes(16));

// Multi-row insert inside a transaction — partial failure rolls back the batch
$insertQuery = "INSERT INTO leave_deduction_history
    (employeeID, leaveID, leaveDeduction, note, attachment, batch_id, override_signatory_id, isApproved, createDate, createdBy)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)";
$insertStmt = mysqli_prepare($con, $insertQuery);

if (!$insertStmt) {
    if (file_exists($uploadPath)) unlink($uploadPath);
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

// Prepared insert for recreation_leave_history — used only when a row is shranti
$recInsert = mysqli_prepare($con,
    "INSERT INTO recreation_leave_history
     (employee_id, deduction_days, deducted_on, next_maturity_date,
      leave_deduction_history_id, batch_id, attachment, note, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

mysqli_autocommit($con, false);
$insertedIDs = [];
$allOK = true;
foreach ($rows as $r) {
    $t = $r['type']; $d = $r['days']; $n = $r['note'];
    mysqli_stmt_bind_param($insertStmt, "iidsssisi", $employeeID, $t, $d, $n, $officeAdesh, $batchId, $signatoryID, $submitDate, $createdBy);
    if (!mysqli_stmt_execute($insertStmt)) { $allOK = false; break; }
    $newDedID = mysqli_insert_id($con);
    $insertedIDs[] = $newDedID;

    // Shranti sibling insert — recreation history row keyed to this deduction
    if (!empty($r['is_shranti']) && $recInsert) {
        $mat = $r['maturity'];
        mysqli_stmt_bind_param($recInsert, "idssisssi",
            $employeeID, $d, $submitDate, $mat, $newDedID, $batchId, $officeAdesh, $n, $createdBy);
        if (!mysqli_stmt_execute($recInsert)) { $allOK = false; break; }
    }
}
mysqli_stmt_close($insertStmt);
if ($recInsert) mysqli_stmt_close($recInsert);

if ($allOK) {
    mysqli_commit($con);
    mysqli_autocommit($con, true);

    if (function_exists('audit_log')) {
        foreach ($rows as $idx => $r) {
            audit_log('leave_deduction_submitted', [
                'target_type'     => 'leave_deduction',
                'target_id'       => (int)($insertedIDs[$idx] ?? 0),
                'organization_id' => $allowedOrgID > 0 ? $allowedOrgID : null,
                'note'            => 'employeeID=' . $employeeID . '; leaveID=' . $r['type'] . '; days=' . $r['days'],
            ]);
        }
    }

    $n = count($insertedIDs);
    echo json_encode(['status' => 1, 'message' => $n . ' টি ছুটি কর্তন সফলভাবে সংরক্ষণ হয়েছে']);
} else {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    if (file_exists($uploadPath)) unlink($uploadPath);
    echo json_encode(['status' => 0, 'message' => 'ছুটি কর্তন সংরক্ষণ করতে ব্যর্থ হয়েছে!']);
}

mysqli_close($con);
?>
