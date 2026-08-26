<?php
// Start session first
session_start();

// Set JSON header
header('Content-Type: application/json');

// Start output buffering
ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../library/number_converter.php');
require_once(__DIR__ . '/../../function.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Actor scope — Super Admin + HQ (org=4) all centers; others restricted to own center
$_actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'];
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actor = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);
$_isSuperAdmin   = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID     = (int)($_actor['emp_org'] ?? 0);
$_seeAllCenters  = ($_isSuperAdmin || $_myCenterID === 4);

// Helper: verify a posted employee ID falls within the actor's scope
$_scopeCheck = function($empID) use ($con, $_seeAllCenters, $_myCenterID) {
    $empID = (int)$empID;
    if ($empID <= 0) return false;
    if ($_seeAllCenters) return true;
    $s = mysqli_prepare($con, "SELECT 1 FROM employee_list WHERE id = ? AND organization_id = ? LIMIT 1");
    mysqli_stmt_bind_param($s, 'ii', $empID, $_myCenterID);
    mysqli_stmt_execute($s);
    $ok = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    mysqli_stmt_close($s);
    return $ok;
};

// Get POST data
$incrementYear = isset($_POST['incrementYear']) ? mysqli_real_escape_string($con, $_POST['incrementYear']) : date('Y');
$certificateDate = isset($_POST['certificateDate']) ? mysqli_real_escape_string($con, $_POST['certificateDate']) : '';
$noticeDate = isset($_POST['noticeDate']) ? mysqli_real_escape_string($con, $_POST['noticeDate']) : '';
$employeeID = isset($_POST['employeeID']) ? mysqli_real_escape_string($con, $_POST['employeeID']) : '';
$signatoryID = isset($_POST['signatoryID']) ? mysqli_real_escape_string($con, $_POST['signatoryID']) : '';
// The অনুলিপি table posts parallel arrays, one entry per row: kind (emp|label),
// the employee id or the fixed label, and the অনুক্রম the row prints at.
$copyKindArr   = isset($_POST['copyKind'])   ? (array)$_POST['copyKind']   : array();
$copyEmpArr    = isset($_POST['copyEmp'])    ? (array)$_POST['copyEmp']    : array();
$copyLabelArr  = isset($_POST['copyLabel'])  ? (array)$_POST['copyLabel']  : array();
$copySerialArr = isset($_POST['copySerial']) ? (array)$_POST['copySerial'] : array();

$copyRows = array();
foreach ($copyKindArr as $__i => $__kind) {
    $__serial = (int)($copySerialArr[$__i] ?? ($__i + 1));
    if ($__kind === 'label') {
        $__label = trim((string)($copyLabelArr[$__i] ?? ''));
        if ($__label === '') continue;
        $copyRows[] = ['kind' => 'label', 'employeeID' => 0, 'label' => $__label, 'serial' => $__serial];
    } else {
        $__emp = (int)($copyEmpArr[$__i] ?? 0);
        if ($__emp <= 0) continue;   // a row left unpicked is simply skipped
        $copyRows[] = ['kind' => 'emp', 'employeeID' => $__emp, 'label' => '', 'serial' => $__serial];
    }
}
usort($copyRows, function ($a, $b) { return $a['serial'] <=> $b['serial']; });

// Employee ids still have to pass the centre check below.
$copyToArray = array();
foreach ($copyRows as $__r) if ($__r['kind'] === 'emp') $copyToArray[] = $__r['employeeID'];

// Validate required fields
if (empty($certificateDate)) {
    echo json_encode(['status' => 0, 'message' => 'সার্টিফিকেট তারিখ প্রদান করুন!']);
    exit;
}

if (empty($noticeDate)) {
    echo json_encode(['status' => 0, 'message' => 'নোটিশ তারিখ প্রদান করুন!']);
    exit;
}

if ($employeeID === '') {
    echo json_encode(['status' => 0, 'message' => 'কর্মচারী নির্বাচন করুন!']);
    exit;
}

if (empty($signatoryID)) {
    echo json_encode(['status' => 0, 'message' => 'স্বাক্ষরকারী কর্মকর্তা নির্বাচন করুন!']);
    exit;
}

// Server-side scope enforcement — pretty much defence-in-depth against tampered POST
if ((int)$employeeID !== 0 && !$_scopeCheck($employeeID)) {
    echo json_encode(['status' => 0, 'message' => 'এই কর্মচারীর জন্য সার্টিফিকেট তৈরির অনুমতি নেই']);
    exit;
}
if (!$_scopeCheck($signatoryID)) {
    echo json_encode(['status' => 0, 'message' => 'এই স্বাক্ষরকারী কর্মকর্তা নির্বাচনের অনুমতি নেই']);
    exit;
}
foreach ((array)$copyToArray as $_ctID) {
    if ($_ctID === '' || (int)$_ctID === 0) continue; // empty rows allowed
    if (!$_scopeCheck($_ctID)) {
        echo json_encode(['status' => 0, 'message' => 'অনুলিপি তালিকায় অন্য কেন্দ্রের কর্মকর্তা যোগ করা যাবে না']);
        exit;
    }
}

// Convert certificateDate from dd/mm/yyyy to Y-m-d
$dateObj = DateTime::createFromFormat('d/m/Y', $certificateDate);
if ($dateObj === false) {
    echo json_encode(['status' => 0, 'message' => 'সার্টিফিকেট তারিখ সঠিক নয়!']);
    exit;
}
$certificateDateFormatted = $dateObj->format('Y-m-d');

// Convert noticeDate from dd/mm/yyyy to Y-m-d
$dateObjtwo = DateTime::createFromFormat('d/m/Y', $noticeDate);
if ($dateObjtwo === false) {
    echo json_encode(['status' => 0, 'message' => 'নোটিশ তারিখ সঠিক নয়!']);
    exit;
}
$noticeDateFormatted = $dateObjtwo->format('Y-m-d');

// Get signatory employee details
$getSignatoryQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$signatoryID'");
$signatoryDetails = mysqli_fetch_assoc($getSignatoryQ);

if (!$signatoryDetails) {
    echo json_encode(['status' => 0, 'message' => 'স্বাক্ষরকারী কর্মকর্তা খুঁজে পাওয়া যায়নি!']);
    exit;
}

$signatory = $signatoryID;
$signatoryDesignation = $signatoryDetails['designation'];
$signatoryCenterID = $signatoryDetails['organization_id'];
$creationDate = date('Y-m-d H:i:s');

// Casual leave year range
$casualStart = $incrementYear . '-01-01';
$casualEnd = $incrementYear . '-12-31';

// Function to calculate leave for an employee
// Function to generate certificate
function generateCertificate($con, $empID, $incrementYear, $certificateDateFormatted, $noticeDateFormatted, $signatory, $signatoryDesignation, $signatoryCenterID, $creationDate, $copyRows, $casualStart, $casualEnd) {

    $getEmpQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$empID'");
    $empInfo = mysqli_fetch_assoc($getEmpQ);

    if (!$empInfo) {
        return false;
    }

    $designation = $empInfo['designation'];
    $centerID = $empInfo['organization_id'];
    $memorialNo = $empInfo['memorialNo'] ?? '';

    // Same figures the dashboard shows: one implementation, no second copy to
    // drift. Computed as of the certificate's own date, so re-issuing a
    // back-dated certificate reproduces the figures it carried.
    $__info = getEmployeeLeaveInfo($empID, $certificateDateFormatted);
    $leaveData = [
        'fullAvgSalaryInDays' => (int)$__info['fullAvgBalance']['total'],
        'halfSalaryInDays'    => (int)$__info['halfAvgBalance']['total'],
        'withoutSalaryInDays' => (int)$__info['withoutPay']['total'],
    ];

    $checkExistQ = mysqli_query($con, "SELECT leaveSummaryID FROM yearly_leave_summary WHERE employeeID='$empID' AND year='$incrementYear'");

    if (mysqli_num_rows($checkExistQ) > 0) {
        $existingRow = mysqli_fetch_assoc($checkExistQ);
        $leaveSummaryID = $existingRow['leaveSummaryID'];

        $updateQ = mysqli_query($con, "UPDATE yearly_leave_summary SET
            designation = '$designation',
            centerID = '$centerID',
            memorial_number = '$memorialNo',
            date = '$certificateDateFormatted',
            noticeDate = '$noticeDateFormatted',
            fullHalfSalaryInDays = '{$leaveData['fullAvgSalaryInDays']}',
            HalfSalaryInDays = '{$leaveData['halfSalaryInDays']}',
            withoutSalaryInDays = '{$leaveData['withoutSalaryInDays']}',
            signatory = '$signatory',
            signatoryDesignation = '$signatoryDesignation',
            signatoryCenterID = '$signatoryCenterID',
            isApproved = 0,
            approvedBy = NULL,
            approvedDate = NULL,
            rejectionReason = NULL
            WHERE leaveSummaryID = '$leaveSummaryID'");

        if (!$updateQ) {
            return false;
        }

        mysqli_query($con, "DELETE FROM leaveSummary_copy WHERE leaveSummaryID='$leaveSummaryID'");

    } else {
        $insertQ = mysqli_query($con, "INSERT INTO yearly_leave_summary
            (employeeID, designation, centerID, memorial_number, date, noticeDate, year, fullHalfSalaryInDays, HalfSalaryInDays, withoutSalaryInDays, creationDate, signatory, signatoryDesignation, signatoryCenterID, isApproved)
            VALUES
            ('$empID', '$designation', '$centerID', '$memorialNo', '$certificateDateFormatted', '$noticeDateFormatted', '$incrementYear', '{$leaveData['fullAvgSalaryInDays']}', '{$leaveData['halfSalaryInDays']}', '{$leaveData['withoutSalaryInDays']}', '$creationDate', '$signatory', '$signatoryDesignation', '$signatoryCenterID', 0)");

        if (!$insertQ) {
            return false;
        }

        $leaveSummaryID = mysqli_insert_id($con);
    }

    // Insert copy-to records, in the order the form put them in. A label row
    // carries no employee; an employee row resolves designation and centre so
    // the certificate can print them without a second lookup.
    foreach ($copyRows as $__r) {
        $__serial = (int)$__r['serial'];
        if ($__r['kind'] === 'label') {
            $__lbl = mysqli_real_escape_string($con, $__r['label']);
            mysqli_query($con, "INSERT INTO leaveSummary_copy
                (leaveSummaryID, copyTo, label, designation, centerID, serial)
                VALUES ('$leaveSummaryID', 0, '$__lbl', 0, 0, $__serial)");
            continue;
        }

        $__emp = (int)$__r['employeeID'];
        $getCopyToQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$__emp'");
        $copyToInfo = mysqli_fetch_assoc($getCopyToQ);
        if (!$copyToInfo) continue;

        mysqli_query($con, "INSERT INTO leaveSummary_copy
            (leaveSummaryID, copyTo, label, designation, centerID, serial)
            VALUES ('$leaveSummaryID', '$__emp', NULL, '{$copyToInfo['designation']}', '{$copyToInfo['organization_id']}', $__serial)");
    }

    return true;
}

try {
    if ($employeeID == '0') {
        $getAllEmployeeQ = mysqli_query($con, "SELECT id FROM employee_list WHERE employment_status=1 OR employment_status=2");
        $successCount = 0;
        $totalCount = mysqli_num_rows($getAllEmployeeQ);

        while ($empRow = mysqli_fetch_array($getAllEmployeeQ)) {
            if (generateCertificate($con, $empRow['id'], $incrementYear, $certificateDateFormatted, $noticeDateFormatted, $signatory, $signatoryDesignation, $signatoryCenterID, $creationDate, $copyRows, $casualStart, $casualEnd)) {
                $successCount++;
            }
        }

        if ($successCount > 0) {
            if (function_exists('audit_log')) {
                audit_log('leave_certificate_generated_bulk', [
                    'target_type' => 'yearly_leave_summary',
                    'target_id'   => 0,
                    'note'        => "year=$incrementYear; count=$successCount/$totalCount; sig=$signatory",
                ]);
            }
            echo json_encode(['status' => 1, 'message' => "সকল কর্মচারীর ({$successCount}/{$totalCount}) সার্টিফিকেট সফলভাবে তৈরি করা হয়েছে!"]);
        } else {
            echo json_encode(['status' => 0, 'message' => 'সার্টিফিকেট তৈরি করতে ব্যর্থ হয়েছে!']);
        }
    } else {
        if (generateCertificate($con, $employeeID, $incrementYear, $certificateDateFormatted, $noticeDateFormatted, $signatory, $signatoryDesignation, $signatoryCenterID, $creationDate, $copyRows, $casualStart, $casualEnd)) {
            if (function_exists('audit_log')) {
                audit_log('leave_certificate_generated', [
                    'target_type' => 'yearly_leave_summary',
                    'target_id'   => (int)$employeeID,
                    'note'        => "year=$incrementYear; sig=$signatory",
                ]);
            }
            echo json_encode(['status' => 1, 'message' => 'সার্টিফিকেট সফলভাবে তৈরি করা হয়েছে!']);
        } else {
            echo json_encode(['status' => 0, 'message' => 'সার্টিফিকেট তৈরি করতে ব্যর্থ হয়েছে!']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['status' => 0, 'message' => 'একটি ত্রুটি হয়েছে: ' . $e->getMessage()]);
}

mysqli_close($con);
?>
