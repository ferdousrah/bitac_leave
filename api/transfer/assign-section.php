<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(__DIR__ . '/../../library/employee_helpers.php');
ob_end_clean();

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

$empId        = (int)($_POST['dataID']              ?? 0);
$sectionID    = (int)($_POST['section_id']          ?? 0);
$joiningDate  = trim($_POST['actual_joining_date']  ?? '');

if ($empId <= 0)       { echo json_encode(['status'=>0,'message'=>'অবৈধ কর্মচারী']); exit; }
if ($sectionID <= 0)   { echo json_encode(['status'=>0,'message'=>'সেকশন নির্বাচন করুন']); exit; }
if ($joiningDate === '') { echo json_encode(['status'=>0,'message'=>'যোগদান তারিখ আবশ্যক']); exit; }

// Authorization — receiving center admin OR SuperAdmin OR HQ
// (Center admin must be from the employee's current center)
$actorQ = mysqli_query($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.dataID = " . (int)$_SESSION['userID'] . " LIMIT 1");
$actor = $actorQ ? mysqli_fetch_assoc($actorQ) : null;
$actorGroup    = (int)($actor['user_group_id'] ?? 0);
$actorCenterID = (int)($actor['emp_org'] ?? 0);
$isSuperAdmin  = ($actorGroup === 1);
$isHQ          = ($actorCenterID === 4);

// Check employee's current org
$empQ = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = $empId LIMIT 1");
$empRow = $empQ ? mysqli_fetch_assoc($empQ) : null;
if (!$empRow) { echo json_encode(['status'=>0,'message'=>'কর্মচারী পাওয়া যায়নি']); exit; }
$empOrgID = (int)$empRow['organization_id'];

$canAssign = $isSuperAdmin || $isHQ || ($actorCenterID === $empOrgID);
if (!$canAssign) {
    echo json_encode(['status'=>0,'message'=>'সেকশন বরাদ্দ করার অনুমতি নেই']);
    exit;
}

$result = bitac_assign_section_after_transfer($con, $empId, $sectionID, $joiningDate, (int)$_SESSION['userID']);
echo json_encode($result);
