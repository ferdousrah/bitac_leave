<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
include(__DIR__ . '/../../bddate.php');
require_once __DIR__ . '/../../includes/signatory_route_helper.php';

$createdBy = $_SESSION['userID'];

try {
    // Start a transaction
    mysqli_begin_transaction($con, MYSQLI_TRANS_START_READ_WRITE);

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$submitDate = todayDate();

$submitTime = logTime();

$todayDate = todayDate();

$employeeID = $_POST['employeeID'];

$onbehalf = 0;



if($employeeID == 0){

	if(isset($_POST['employeeIDOnbehalf']) && $_POST['employeeIDOnbehalf'] !=''){

		
		$employeeID = $_POST['employeeIDOnbehalf'];

		$onbehalf = $getUserDetailsQRW['employee_id'];

		$getOnbehalfUserDetailsQ = mysqli_query($con, "select * from `user_list` where employee_id='$employeeID'");
		$getOnbehalfUserDetailsQRW = mysqli_fetch_assoc($getOnbehalfUserDetailsQ);

		$signature = $con -> real_escape_string($getOnbehalfUserDetailsQRW['signature']);
	
	}else{
	
		echo 0;
		exit();
	
	}

}else{

	$signature = $con -> real_escape_string($getUserDetailsQRW['signature']);

}

$supervisorID = $_POST['supervisorID'];
$organization_id = $_POST['organization_id'];

// ═══════════════════════════════════════════════════════════
// Multi-segment leave application — collect & validate segments
// ═══════════════════════════════════════════════════════════
$segTypes = $_POST['segment_leaveType'] ?? [];
$segFroms = $_POST['segment_dateFrom']  ?? [];
$segTos   = $_POST['segment_dateTo']    ?? [];

// Backward-compat: if old single-field POST is sent, wrap as 1 segment
if (empty($segTypes) && !empty($_POST['leaveType'])) {
    $segTypes = [$_POST['leaveType']];
    $segFroms = [$_POST['leaveFrom']  ?? ''];
    $segTos   = [$_POST['leaveTo']    ?? ''];
}

if (empty($segTypes)) {
    exit("<div class='alert alert-danger'><strong>ত্রুটি:</strong> অন্তত একটা ছুটির ধরন যোগ করুন।</div>");
}

// Build segments[] with parsed dates
$segments = [];
foreach ($segTypes as $i => $rawType) {
    $rawFrom = $segFroms[$i] ?? '';
    $rawTo   = $segTos[$i]   ?? '';
    if (empty($rawType) || empty($rawFrom) || empty($rawTo)) continue;

    // Parse dd/mm/yyyy → YYYY-MM-DD
    $fParts = explode('/', $rawFrom);
    $tParts = explode('/', $rawTo);
    if (count($fParts) !== 3 || count($tParts) !== 3) {
        exit("<div class='alert alert-danger'><strong>ত্রুটি:</strong> তারিখ ফরম্যাট ঠিক নেই (ধরন " . ($i + 1) . ")।</div>");
    }
    $df = $fParts[2] . '-' . $fParts[1] . '-' . $fParts[0];
    $dt = $tParts[2] . '-' . $tParts[1] . '-' . $tParts[0];
    $dfTs = strtotime($df);
    $dtTs = strtotime($dt);
    if (!$dfTs || !$dtTs || $dtTs < $dfTs) {
        exit("<div class='alert alert-danger'><strong>ত্রুটি:</strong> অবৈধ তারিখ পরিসর (ধরন " . ($i + 1) . ")।</div>");
    }
    $days = (int)(($dtTs - $dfTs) / 86400) + 1;
    $segments[] = ['leaveType' => intval($rawType), 'dateFrom' => $df, 'dateTo' => $dt, 'days' => $days];
}

if (count($segments) === 0) {
    exit("<div class='alert alert-danger'><strong>ত্রুটি:</strong> বৈধ কোনো ছুটির segment পাওয়া যায়নি।</div>");
}

// ═══════════════════════════════════════════════════════════
// BSR per-segment + combined validations
// leaveID: 1 = Full Avg Pay, 8 = Casual, 22 = Cancellation
// ═══════════════════════════════════════════════════════════

// Combined: CL cannot mix with other types in same application
$hasCL    = false;
$hasNonCL = false;
foreach ($segments as $s) {
    if ($s['leaveType'] === 8)  $hasCL = true;
    elseif ($s['leaveType'] !== 22) $hasNonCL = true;
}
if ($hasCL && $hasNonCL) {
    exit("<div class='alert alert-danger'><strong>নিয়ম লঙ্ঘন (সরকারি চাকরি বিধিমালা):</strong> নৈমিত্তিক ছুটি অন্য ধরনের ছুটির সাথে এক আবেদনে মিশানো যাবে না। নৈমিত্তিকের জন্য আলাদা আবেদন করুন।</div>");
}

// Optional leave (leaveID=7) — requires pre-approval for the segment's year.
// Days requested this application must fit within (approved pre-approval credit − already-spent optional).
$optionalDaysRequested = 0;
$optionalYearsRequested = [];
foreach ($segments as $s) {
    if ($s['leaveType'] === 7) {
        $optionalDaysRequested += $s['days'];
        $optionalYearsRequested[] = (int)date('Y', strtotime($s['dateFrom']));
    }
}
if ($optionalDaysRequested > 0) {
    $preTblChk = mysqli_query($con, "SHOW TABLES LIKE 'optional_leave_pre_approval'");
    if ($preTblChk && mysqli_num_rows($preTblChk) > 0) {
        // Compute approved credit per year involved
        $yearsList = implode(',', array_unique(array_map('intval', $optionalYearsRequested)));
        $credQ = mysqli_query($con,
            "SELECT COALESCE(SUM(requested_days),0) AS credit
             FROM optional_leave_pre_approval
             WHERE employee_id = '$employeeID' AND year IN ($yearsList) AND status = 1");
        $credRow = mysqli_fetch_assoc($credQ);
        $approvedCredit = (float)($credRow['credit'] ?? 0);

        // Compute already-spent optional (both from leave_applications and manual deductions)
        $spentAppsQ = mysqli_query($con,
            "SELECT COALESCE(SUM(DATEDIFF(dateTo, dateFrom)+1),0) AS spent
             FROM leave_applications
             WHERE applicantID = '$employeeID' AND leaveType = 7 AND status = 1
               AND YEAR(dateFrom) IN ($yearsList)");
        $spentApps = (float)(mysqli_fetch_assoc($spentAppsQ)['spent'] ?? 0);
        $spentDedQ = mysqli_query($con,
            "SELECT COALESCE(SUM(leaveDeduction),0) AS s
             FROM leave_deduction_history
             WHERE employeeID = '$employeeID' AND leaveID = 5 AND isApproved = 1
               AND YEAR(createDate) IN ($yearsList)");
        $spentDed = (float)(mysqli_fetch_assoc($spentDedQ)['s'] ?? 0);
        $availableOptional = $approvedCredit - ($spentApps + $spentDed);

        if ($availableOptional < $optionalDaysRequested) {
            exit("<div class='alert alert-danger'><strong>ঐচ্ছিক ছুটি:</strong> এই বছরের অনুমোদিত পূর্বানুমোদন balance ($approvedCredit দিন) অপর্যাপ্ত। ইতিমধ্যে ব্যয়িত: " . ($spentApps + $spentDed) . " দিন। এই আবেদনে চাইছেন: $optionalDaysRequested দিন। প্রয়োজনে পূর্বানুমোদন আবেদন জমা দিন।</div>");
        }
    } else {
        exit("<div class='alert alert-danger'><strong>ঐচ্ছিক ছুটি:</strong> পূর্বানুমোদন সিস্টেম initialize হয়নি। administrator-কে জানান।</div>");
    }
}

// Per-segment caps
foreach ($segments as $i => $s) {
    if ($s['leaveType'] === 1 && $s['days'] > 120) {
        exit("<div class='alert alert-danger'><strong>নিয়ম লঙ্ঘন (সরকারি চাকরি বিধিমালা — অনুচ্ছেদ ১২-ক):</strong> পূর্ণ গড় বেতনে একটানা সর্বোচ্চ ১২০ দিন (৪ মাস) — ধরন " . ($i + 1) . " এ {$s['days']} দিন চাইছেন।</div>");
    }
    if ($s['leaveType'] === 8 && $s['days'] > 10) {
        exit("<div class='alert alert-danger'><strong>নিয়ম লঙ্ঘন (সরকারি চাকরি বিধিমালা):</strong> নৈমিত্তিক ছুটি একটানা সর্বোচ্চ ১০ দিন — ধরন " . ($i + 1) . " এ {$s['days']} দিন চাইছেন।</div>");
    }
}

// Date overlap among segments
for ($a = 0; $a < count($segments); $a++) {
    for ($b = $a + 1; $b < count($segments); $b++) {
        $A = $segments[$a]; $B = $segments[$b];
        if ($A['dateFrom'] <= $B['dateTo'] && $B['dateFrom'] <= $A['dateTo']) {
            exit("<div class='alert alert-danger'><strong>ত্রুটি:</strong> ধরন " . ($a + 1) . " ও ধরন " . ($b + 1) . " এর তারিখ overlap করছে।</div>");
        }
    }
}

// সরকারি চাকরি বিধিমালা — যে ছুটির ধরণে সংযুক্তি বাধ্যতামূলক
$MANDATORY_ATTACHMENT = [
    2  => ['name' => 'অর্ধ-গড় বেতনে',     'doc' => 'Medical certificate'],
    5  => ['name' => 'সংগনিরোধ',          'doc' => 'Quarantine আদেশ/সার্টিফিকেট'],
    6  => ['name' => 'প্রসূতি',            'doc' => 'Medical certificate'],
    19 => ['name' => 'অক্ষমতাজনিত',       'doc' => 'Medical Board সার্টিফিকেট'],
];
$mandatoryHits = [];
foreach ($segments as $s) {
    if (isset($MANDATORY_ATTACHMENT[$s['leaveType']])) {
        $hit = $MANDATORY_ATTACHMENT[$s['leaveType']];
        $mandatoryHits[$s['leaveType']] = $hit['name'] . ' → ' . $hit['doc'];
    }
}
$hasFileUpload = isset($_FILES['leaveFile']['tmp_name']) && is_uploaded_file($_FILES['leaveFile']['tmp_name']);
if (!empty($mandatoryHits) && !$hasFileUpload) {
    $listHtml = implode('<br>', array_values($mandatoryHits));
    exit("<div class='alert alert-danger'><strong>সংযুক্তি বাধ্যতামূলক (সরকারি চাকরি বিধিমালা):</strong><br>{$listHtml}<br><br>আবেদনের সাথে উপযুক্ত ডকুমেন্ট সংযুক্ত করুন।</div>");
}

// ── Aggregate values for parent leave_applications row (backward-compat) ──
$dateFromTs = min(array_map(fn($s) => strtotime($s['dateFrom']), $segments));
$dateToTs   = max(array_map(fn($s) => strtotime($s['dateTo']),   $segments));
$dateFrom   = date('Y-m-d', $dateFromTs);
$dateTo     = date('Y-m-d', $dateToTs);
$leaveType  = $segments[0]['leaveType'];                 // primary type = first segment
$requestedDays = array_sum(array_column($segments, 'days'));

// Duplicate check (same applicant + same overall start date + still active)
$getDuplicateEntryQ = mysqli_query($con, "select dataID from leave_applications where applicantID='$employeeID' and dateFrom='$dateFrom' and (status=0 or status=1)");
if ($getDuplicateEntryQ && mysqli_num_rows($getDuplicateEntryQ) > 0) {
    exit("<div class='alert alert-danger'><strong>ত্রুটি:</strong> এই তারিখে ইতিমধ্যে একটি আবেদন আছে।</div>");
}

// Adjacency check for CL with other PENDING/APPROVED applications
$leaveIDInt = intval($leaveType);
if (count($segments) === 1 && $leaveIDInt !== 22) {
    $dayBefore     = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
    $dayAfter      = date('Y-m-d', strtotime($dateTo   . ' +1 day'));
    $employeeIdEsc = mysqli_real_escape_string($con, $employeeID);
    $dayBeforeEsc  = mysqli_real_escape_string($con, $dayBefore);
    $dayAfterEsc   = mysqli_real_escape_string($con, $dayAfter);

    if ($leaveIDInt === 8) {
        $adjRes = mysqli_query($con, "SELECT dataID FROM leave_applications
                   WHERE applicantID = '$employeeIdEsc' AND status IN (0, 1)
                     AND leaveType NOT IN (8, 22)
                     AND (dateTo = '$dayBeforeEsc' OR dateFrom = '$dayAfterEsc') LIMIT 1");
        if ($adjRes && mysqli_num_rows($adjRes) > 0) {
            exit("<div class='alert alert-danger'><strong>নিয়ম লঙ্ঘন (সরকারি চাকরি বিধিমালা):</strong> নৈমিত্তিক ছুটি অন্য ধরনের ছুটির সাথে পাশাপাশি (consecutive) নেওয়া যাবে না।</div>");
        }
    } elseif ($hasNonCL) {
        $adjRes = mysqli_query($con, "SELECT dataID FROM leave_applications
                   WHERE applicantID = '$employeeIdEsc' AND status IN (0, 1)
                     AND leaveType = 8
                     AND (dateTo = '$dayBeforeEsc' OR dateFrom = '$dayAfterEsc') LIMIT 1");
        if ($adjRes && mysqli_num_rows($adjRes) > 0) {
            exit("<div class='alert alert-danger'><strong>নিয়ম লঙ্ঘন (সরকারি চাকরি বিধিমালা):</strong> এই ছুটি আপনার পার্শ্ববর্তী নৈমিত্তিক ছুটির সাথে সংযুক্ত হবে।</div>");
        }
    }
}

$subject = $_POST['subject'];

$leaveApplication = $_POST['leaveApplication'];

$applicationTo = $_POST['to'];

$applicationType = $_POST['applicationType'];

if (isset($_POST['isinformedValue']) && $_POST['isinformedValue'] != '') {

	$isinformedValue = $_POST['isinformedValue'];

}else{

	$isinformedValue = 0;

}

if(!file_exists($_FILES['leaveFile']['tmp_name']) || !is_uploaded_file($_FILES['leaveFile']['tmp_name'])) {
   // echo 'No upload'; 
   $leaveFile = '';
}
else {

$leaveFile = $_FILES['leaveFile']['name'];
$file_size =$_FILES['leaveFile']['size'];
$file_tmp =$_FILES['leaveFile']['tmp_name'];
$file_type=$_FILES['leaveFile']['type'];
$file_extArray=explode('.',$_FILES['leaveFile']['name']);
//echo $file_extArray[1];
$file_ext = strtolower($file_extArray[1]);	  

$extensions= array("jpeg","jpg","png","pdf");
      
if(in_array($file_ext,$extensions)== false){
	$errors[]="extension not allowed, please choose a JPEG or PNG file.";
}
      
	if($file_size > 2097152){
		$errors[]='File size must be excately 2 MB';
	}
      
	if(empty($errors)==true){
		// Use absolute path so the file always lands in bitac_leave/uploads/
		// (relative "uploads/" resolved to api/leave/uploads/ which doesn't exist → silent fail)
		$_uploadDir = __DIR__ . '/../../uploads/';
		if (!is_dir($_uploadDir)) { @mkdir($_uploadDir, 0755, true); }
		if (!move_uploaded_file($file_tmp, $_uploadDir . $leaveFile)) {
			// Don't persist a filename in DB if the file wasn't actually saved
			$leaveFile = '';
		}
	}else{
		print_r($errors);
		$leaveFile = '';
	}
}



	// calculate leave balance

	$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
	$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);


	$getPrevLeaveHistory = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$employeeID' and isApproved=1");
								$getPrevLeaveHistoryRW = mysqli_fetch_assoc($getPrevLeaveHistory);

								// calculate leave deduction

								$calculateFullAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalFullLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='1' and isApproved=1");
								$calculateFullAvgQRW = mysqli_fetch_assoc($calculateFullAvgQ);

								$calculateHalfAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalLHalfDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='2' and isApproved=1");
								$calculateHalfAvgQRW = mysqli_fetch_assoc($calculateHalfAvgQ);

								$calculateCLAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalCLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='3' and isApproved=1 and (createDate between '$casualStart' and '$casualEnd')");
								$calculateCLAvgQRW = mysqli_fetch_assoc($calculateCLAvgQ);

								// optional leave

								$optionalLHistoryQ = mysqli_query($con, "select sum(leaveDeduction) as totalOLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='5' and isApproved=1 and (createDate between '$casualStart' and '$casualEnd')");
								$optionalLHistoryQRW = mysqli_fetch_assoc($optionalLHistoryQ); 

								// end of optional leave

								$calculateWPAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalWPDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='4' and isApproved=1");
								$calculateWPAvgQRW = mysqli_fetch_assoc($calculateWPAvgQ);


								// extraordinary leave, new added

								$calculateExOrLManualQ = mysqli_query($con, "select sum(leaveDeduction) as totalExODeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='10' and isApproved=1");
								$calculateExOrLManualQRW = mysqli_fetch_assoc($calculateExOrLManualQ);								

								//
								

								$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLWithoutPay from leave_applications where status=1 and applicantID='$employeeID' and (leaveTypeInTwo='4')");
								$getTotalLWioutPayLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLWioutPayLeaveInCurrentYearQ);

								// extraordinary leave, new added

								$getTotalLExtraOrdinaryLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalExorLeave from leave_applications where status=1 and applicantID='$employeeID' and (leaveTypeInTwo='10')");
								$getTotalLExtraOrdinaryLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLExtraOrdinaryLeaveInCurrentYearQ);	
								
								//.............


								$getTotalLCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLCasualSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3' and (`approvedDateFrom`>='$casualStart' and `approvedDateTo`<='$casualEnd')");
								$getTotalLCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLCasualLeaveInCurrentYearQ);

								// optional leave

								$getTotalOptionalLeaveQ = mysqli_query($con, "select sum(approvedDays) as totalOLSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='5' and (`approvedDateFrom`>='$casualStart' and `approvedDateTo`<='$casualEnd')");
								$getTotalOptionalLeaveQRW = mysqli_fetch_assoc($getTotalOptionalLeaveQ); 


								// end of optional leave


								// total without pay to display

								$totalWithoutPay = ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction']);

								
								$totalWithoutPayyears = floor($totalWithoutPay / 360);
								$totalWithoutPaymonths = floor(($totalWithoutPay - ($totalWithoutPayyears * 360))/30);
								$totalWithoutPaydays = round($totalWithoutPay - ($totalWithoutPayyears * 360) - ($totalWithoutPaymonths * 30));

								//.............................




								// total extra ordinary leave to display 


								$totalExtraOrdinaryLeave = ($getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] + $getPrevLeaveHistoryRW['extraOrdinaryLeave'] + $calculateExOrLManualQRW['totalExODeduction']);

								
								$totalExtraOrdinaryLeaveYears = floor($totalExtraOrdinaryLeave / 360);
								$totalExtraOrdinaryLeaveMonths = floor(($totalExtraOrdinaryLeave - ($totalExtraOrdinaryLeaveYears * 360))/30);
								$totalExtraOrdinaryLeaveDays = round($totalExtraOrdinaryLeave - ($totalExtraOrdinaryLeaveYears * 360) - ($totalExtraOrdinaryLeaveMonths * 30));



								//.........

								

								$diff = abs(strtotime($todayDate)-strtotime($getEmployeeInfoQRW['joining_date']));

								$days = round($diff/(60*60*24));

								$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction'] + $getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] + $getPrevLeaveHistoryRW['extraOrdinaryLeave'] + $calculateExOrLManualQRW['totalExODeduction']); // updated

								// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days

								$fullAvgSalLeave = floor($days/11);

								$fullAvgSalLeaveRemainder = fmod($days, 11);

								if($fullAvgSalLeaveRemainder >= 6){

									$fullAvgSalLeave = $fullAvgSalLeave + 1;
								
								}

								// in year month day

								$fullAvgSalLeaveyears = floor($fullAvgSalLeave / 360);
								$fullAvgSalLeavemonths = floor(($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360))/30);
								$fullAvgSalLeavedays = round($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360) - ($fullAvgSalLeavemonths * 30));

								// get voghkrito chuti
								$getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
								$getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

								$totalAvgSalVugkrito = $getPrevLeaveHistoryRW['avgSalary'] + $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'] + $calculateFullAvgQRW['totalFullLDeduction'];

								$fullAvgVugkritoSalLeaveyears = floor($totalAvgSalVugkrito / 360);
								$fullAvgVugkritoSalLeavemonths = floor(($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360))/30);
								$fullAvgVugkritoSalLeavedays = round($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360) - ($fullAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

								$restAvgSalLeave = $fullAvgSalLeave - $totalAvgSalVugkrito;

								// rest in year month day

								$fullAvgRestSalLeaveyears = floor($restAvgSalLeave / 360);
								$fullAvgRestSalLeavemonths = floor(($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 360))/30);
								$fullAvgRestSalLeavedays = round($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 360) - ($fullAvgRestSalLeavemonths * 30));

								//..................


								$halfAvgSalLeave = floor($days/12);

								$halfAvgSalLeaveRemainder = fmod($days, 12);

								if($halfAvgSalLeaveRemainder >= 6){

									$halfAvgSalLeave = $halfAvgSalLeave + 1;
								
								}

								//echo 'অর্ধ-গড় বেতনে অর্জিত ছুটি:'. $halfAvgSalLeave;

								// in year month day

								$halfAvgSalLeaveyears = floor($halfAvgSalLeave / 360);
								$halfAvgSalLeavemonths = floor(($halfAvgSalLeave - ($halfAvgSalLeaveyears * 360))/30);
								$halfAvgSalLeavedays = round($halfAvgSalLeave - ($halfAvgSalLeaveyears * 360) - ($halfAvgSalLeavemonths * 30));

								// get voghkrito chuti
								$getTotalhalfAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays)*2 as totalHalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
								$getTotalhalfAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalhalfAvgSalLeaveUsedQ);

								$totalHalfAvgSalVugkrito = $getPrevLeaveHistoryRW['halfAvgSalary'] + $getTotalhalfAvgSalLeaveUsedQRW['totalHalfAvgSal'] + $calculateHalfAvgQRW['totalLHalfDeduction'];

								$halfAvgVugkritoSalLeaveyears = floor($totalHalfAvgSalVugkrito / 360);
								$halfAvgVugkritoSalLeavemonths = floor(($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360))/30);
								$halfAvgVugkritoSalLeavedays = round($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360) - ($halfAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

								$restHalfAvgSalLeave = $halfAvgSalLeave - $totalHalfAvgSalVugkrito;

								// rest in year month day

								$halfAvgRestSalLeaveyears = floor($restHalfAvgSalLeave / 360);
								$halfAvgRestSalLeavemonths = floor(($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360))/30);
								$halfAvgRestSalLeavedays = round($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360) - ($halfAvgRestSalLeavemonths * 30));

								// get total orjito

								$getTotalIorjitoFullLeaveQ = mysqli_query($con, "select sum(approvedDays) as totalOrjitofull from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
								$getTotalIorjitoFullLeaveQRW = mysqli_fetch_assoc($getTotalIorjitoFullLeaveQ);

								$getTotalIorjitoHalfLeaveQ = mysqli_query($con, "select sum(approvedDays)*2 as totalOrjitohalf from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
								$getTotalIorjitoHalfLeaveQRW = mysqli_fetch_assoc($getTotalIorjitoHalfLeaveQ);

								$totalOrjito = $getTotalIorjitoFullLeaveQRW['totalOrjitofull'] + $getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'];

								$totalIorjitobugkritoyears = floor($totalOrjito / 360);
								$totalIorjitobugkritomonths = floor(($totalOrjito - ($totalIorjitobugkritoyears * 360))/30);
								$totalIorjitobugkritodays = round($totalOrjito - ($totalIorjitobugkritoyears * 360) - ($totalIorjitobugkritomonths * 30));

								// orjito ghor betone

								$orjitoGhorbetoneYear = floor($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] / 360);
								$orjitoGhorbetonemonths = floor(($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] - ($orjitoGhorbetoneYear * 360))/30);
								$orjitoGhorbetonedays = round($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] - ($orjitoGhorbetoneYear * 360) - ($orjitoGhorbetonemonths * 30));


								$orjitoOrdhoGhorbetoneYear = floor($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] / 360);
								$orjitoOrdhoGhorbetoneMonths = floor(($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] - ($orjitoOrdhoGhorbetoneYear * 360))/30);
								$orjitoOrdhoGhorbetoneDays = round($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] - ($orjitoOrdhoGhorbetoneYear * 360) - ($orjitoOrdhoGhorbetoneMonths * 30));


								
								$casualCurrentBalance = $casualBalance - ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $calculateCLAvgQRW['totalCLDeduction']);

								$casualSpent = ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $calculateCLAvgQRW['totalCLDeduction']);


								// optional leave

								$optionalLeaveCurrentBalance = $optionalLBalance - ($getTotalOptionalLeaveQRW['totalOLSpent'] + $optionalLHistoryQRW['totalOLDeduction']);

								$optionalLeaveSpent = ($getTotalOptionalLeaveQRW['totalOLSpent'] + $optionalLHistoryQRW['totalOLDeduction']);

								// end of optional leave

	


	// end of leave calculation

	


	$insertLeaveApplicationQ = mysqli_query($con, "insert into leave_applications(applicationType, isinformed, leaveType, dateFrom, dateTo, leaveApplication, attachment, applicantID, organization_id, submitBy, submitDate, submitTime, signature, applicationTo, subject, onbehalf, fullSalaryYear, fullSalaryMonth, fullSalaryDay, halfSalaryYear, halfSalaryMonth, halfSalaryDay, casual, optionalLBalance) values('$applicationType', '$isinformedValue', '$leaveType', '$dateFrom', '$dateTo', '$leaveApplication', '$leaveFile', '$employeeID', '$organization_id', '$createdBy', '$submitDate', '$submitTime', '$signature', '$applicationTo', '$subject', '$onbehalf', '$fullAvgRestSalLeaveyears', '$fullAvgRestSalLeavemonths', '$fullAvgRestSalLeavedays', '$halfAvgRestSalLeaveyears', '$halfAvgRestSalLeavemonths', '$halfAvgRestSalLeavedays', '$casualCurrentBalance', '$optionalLeaveCurrentBalance')") or die(mysqli_error($con));


	if($insertLeaveApplicationQ == 1){

		$leaveApplicationID = mysqli_insert_id($con);

		// Generate readable application number: BITAC/{year}/{dataID}
		$applicationNo = function_exists('generateApplicationNo')
			? generateApplicationNo($leaveApplicationID, $submitDate)
			: ('BITAC/' . date('Y', strtotime($submitDate ?: 'now')) . '/' . $leaveApplicationID);

		// Snapshot applicant's org info into leave_applications + set application_no
		mysqli_query($con, "UPDATE leave_applications SET "
			. "department_id='" . (int)$getEmployeeInfoQRW['department_id'] . "', "
			. "section_id='" . (int)$getEmployeeInfoQRW['section_id'] . "', "
			. "designation_id='" . (int)$getEmployeeInfoQRW['designation'] . "', "
			. "pay_scale='" . mysqli_real_escape_string($con, $getEmployeeInfoQRW['pay_scale'] ?? '') . "', "
			. "application_no='" . mysqli_real_escape_string($con, $applicationNo) . "' "
			. "WHERE dataID='$leaveApplicationID'");

		// ── Insert each segment (both 'requested' frozen + 'proposed' mutable) + history ──
		$segStmt = mysqli_prepare($con,
			"INSERT INTO leave_application_segments
			 (applicationID, kind, leaveType, dateFrom, dateTo, days, serial, createdBy, createdAt)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
		$histStmt = mysqli_prepare($con,
			"INSERT INTO leave_segment_history
			 (applicationID, segmentID, action, signatoryLevel, changedBy, changedByName, newData, note, changedAt)
			 VALUES (?, ?, 'created', 0, ?, ?, ?, 'Applicant submission', NOW())");
		$applicantName = $getOnbehalfUserDetailsQRW['full_name'] ?? $getUserDetailsQRW['full_name'] ?? '';
		$serial = 1;
		foreach ($segments as $s) {
			if (!$segStmt) break;
			// Insert 'requested' (frozen original)
			$kindReq = 'requested';
			mysqli_stmt_bind_param($segStmt, 'isissiii',
				$leaveApplicationID, $kindReq, $s['leaveType'], $s['dateFrom'], $s['dateTo'], $s['days'], $serial, $createdBy);
			mysqli_stmt_execute($segStmt);
			$reqSegID = mysqli_insert_id($con);

			// Insert 'proposed' (initial copy, signatories will edit)
			$kindProp = 'proposed';
			mysqli_stmt_bind_param($segStmt, 'isissiii',
				$leaveApplicationID, $kindProp, $s['leaveType'], $s['dateFrom'], $s['dateTo'], $s['days'], $serial, $createdBy);
			mysqli_stmt_execute($segStmt);

			if ($histStmt) {
				$newJson = json_encode($s, JSON_UNESCAPED_UNICODE);
				mysqli_stmt_bind_param($histStmt, 'iiiss',
					$leaveApplicationID, $reqSegID, $createdBy, $applicantName, $newJson);
				mysqli_stmt_execute($histStmt);
			}
			$serial++;
		}
		if ($segStmt)  mysqli_stmt_close($segStmt);
		if ($histStmt) mysqli_stmt_close($histStmt);


		$prevSignatory = 0;

		$getSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` where organization_id='$organization_id' and isMandatory=1 order by approvalSL asc");	

		// insert first approval

		// Fetch supervisor's org snapshot
		$_supQ    = mysqli_query($con, "SELECT organization_id, department_id, section_id, designation, pay_scale FROM employee_list WHERE id='$supervisorID' LIMIT 1");
		$_supSnap = $_supQ ? mysqli_fetch_assoc($_supQ) : [];
		$_sup_org   = (int)($_supSnap['organization_id'] ?? 0);
		$_sup_dept  = (int)($_supSnap['department_id'] ?? 0);
		$_sup_sec   = (int)($_supSnap['section_id'] ?? 0);
		$_sup_desig = (int)($_supSnap['designation'] ?? 0);
		$_sup_pay   = mysqli_real_escape_string($con, $_supSnap['pay_scale'] ?? '');
		$insertForApprovalQbySupervisor = mysqli_query($con, "insert into leave_data_for_approval(leaveApplicationID, signatory, isSupervisor, prevSignatory, isApproved, serial, organization_id, department_id, section_id, designation_id, pay_scale) values('$leaveApplicationID', '$supervisorID','1', NULL, '0', '1', '$_sup_org', '$_sup_dept', '$_sup_sec', '$_sup_desig', '$_sup_pay')");

		if($insertForApprovalQbySupervisor == 1){

			$prevSignatory = $supervisorID;

			// insert notification
			$getApplicantDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
			$getApplicantDetailsQRW = mysqli_fetch_assoc($getApplicantDetailsQ);

			$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getApplicantDetailsQRW[designation]'");
			$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

			$getSupervisorDetailsQ = mysqli_query($con, "select * from user_list where employee_id='$supervisorID'");
			$getSupervisorDetailsQNumRows = mysqli_num_rows($getSupervisorDetailsQ);

			if($getSupervisorDetailsQNumRows > 0){

				$getSupervisorDetailsQRW = mysqli_fetch_assoc($getSupervisorDetailsQ);

				$message = $getApplicantDetailsQRW['employee_name'].", ".$getDesignationDetailsQRW['job_title_name']." ছুটির জন্যে আবেদন করেছেন ।";

				$type= "<span class='badge badge-primary'>ছুটির সুপারিশ চেয়ে আবেদন</span>";

				$escapedType = mysqli_real_escape_string($con, $type);

				$link = "leave_application_details.php?menuslug=allowed-leave-applications&leaveApplicationID=".$leaveApplicationID;

				$dateTime = ShowBangladeshTime();

				$insertNotiQuery = mysqli_query($con, "insert into notification(userID, message, notificationType, link, dateTime, isImportant) values('$getSupervisorDetailsQRW[dataID]', '$message', '$escapedType', '$link', '$dateTime', 1)");
			
			} // end of notification
		
		}

		// Build signatory chain using routing rules (grade + leave type based)
		// buildSignatoryChain() uses employeeID directly from leave_approval_signatory
		$chain = buildSignatoryChain($con, (int)$employeeID, (int)$leaveType);

		if (!empty($chain)) {
			// Route-based chain: each entry has employeeID already resolved
			$serialNum = 2;
			foreach ($chain as $sigEntry) {
				$sigEmpId = (int)$sigEntry['employeeID'];
				if (!$sigEmpId) { $serialNum++; continue; }
				// Fetch this signatory's org snapshot
					$_sigQ    = mysqli_query($con, "SELECT organization_id, department_id, section_id, designation, pay_scale FROM employee_list WHERE id='$sigEmpId' LIMIT 1");
				$_sigSnap = $_sigQ ? mysqli_fetch_assoc($_sigQ) : [];
				$_sig_org   = (int)($_sigSnap['organization_id'] ?? 0);
				$_sig_dept  = (int)($_sigSnap['department_id'] ?? 0);
				$_sig_sec   = (int)($_sigSnap['section_id'] ?? 0);
				$_sig_desig = (int)($_sigSnap['designation'] ?? 0);
				$_sig_pay   = mysqli_real_escape_string($con, $_sigSnap['pay_scale'] ?? '');
				mysqli_query($con, "INSERT INTO leave_data_for_approval
					(leaveApplicationID, signatory, prevSignatory, isApproved, serial, organization_id, department_id, section_id, designation_id, pay_scale)
					VALUES('$leaveApplicationID', '$sigEmpId', '$prevSignatory', '0', '$serialNum', '$_sig_org', '$_sig_dept', '$_sig_sec', '$_sig_desig', '$_sig_pay')");
				$prevSignatory = $sigEmpId;
				$serialNum++;
			}
		} else {
			// Fallback: no routing rules configured — use designation-based method
			$getSignatoryFallbackQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` WHERE organization_id='$organization_id' AND isMandatory=1 ORDER BY approvalSL ASC");
			while ($sigRow = mysqli_fetch_array($getSignatoryFallbackQ)) {
				$designatedSigQ = mysqli_query($con, "SELECT * FROM employee_list WHERE organization_id = '$sigRow[organization_id]' AND designation = '$sigRow[designationID]' AND employment_status=1 AND pending_section_assignment=0");
				$designatedSigQRW = mysqli_fetch_assoc($designatedSigQ);
				$_fb_org   = (int)($designatedSigQRW['organization_id'] ?? 0);
			$_fb_dept  = (int)($designatedSigQRW['department_id'] ?? 0);
			$_fb_sec   = (int)($designatedSigQRW['section_id'] ?? 0);
			$_fb_desig = (int)($designatedSigQRW['designation'] ?? 0);
			$_fb_pay   = mysqli_real_escape_string($con, $designatedSigQRW['pay_scale'] ?? '');
			mysqli_query($con, "INSERT INTO leave_data_for_approval(leaveApplicationID, signatory, prevSignatory, isApproved, serial, organization_id, department_id, section_id, designation_id, pay_scale) VALUES('$leaveApplicationID', '$designatedSigQRW[id]', '$prevSignatory', '0', '$sigRow[approvalSL]', '$_fb_org', '$_fb_dept', '$_fb_sec', '$_fb_desig', '$_fb_pay')");
				$prevSignatory = $designatedSigQRW['id'];
			}

			if ($applicationTo == 2) {
				$getLastSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` WHERE organization_id='$organization_id' AND isMandatory=1 ORDER BY approvalSL DESC LIMIT 0,1");
				$getLastSignatoryQRW = mysqli_fetch_assoc($getLastSignatoryQ);
				$getLastSigQ = mysqli_query($con, "SELECT * FROM employee_list WHERE organization_id='$getLastSignatoryQRW[organization_id]' AND designation='$getLastSignatoryQRW[designationID]'");
				$getLastSigQRW = mysqli_fetch_assoc($getLastSigQ);
				$newsigsl = $getLastSignatoryQRW['approvalSL'] + 1;
				$prevSignatorylast = $getLastSigQRW['id'];
				$getDGQ = mysqli_query($con, "SELECT * FROM employee_list WHERE designation=111 AND employment_status=1 AND pending_section_assignment=0");
				$getDGQRW = mysqli_fetch_assoc($getDGQ);
				$_dg_org   = (int)($getDGQRW['organization_id'] ?? 0);
				$_dg_dept  = (int)($getDGQRW['department_id'] ?? 0);
				$_dg_sec   = (int)($getDGQRW['section_id'] ?? 0);
				$_dg_desig = (int)($getDGQRW['designation'] ?? 0);
				$_dg_pay   = mysqli_real_escape_string($con, $getDGQRW['pay_scale'] ?? '');
				mysqli_query($con, "INSERT INTO leave_data_for_approval(leaveApplicationID, signatory, prevSignatory, isApproved, serial, isDG, organization_id, department_id, section_id, designation_id, pay_scale) VALUES('$leaveApplicationID', '$getDGQRW[id]', '$prevSignatorylast', '0', '$newsigsl', 1, '$_dg_org', '$_dg_dept', '$_dg_sec', '$_dg_desig', '$_dg_pay')");
			}
		}

		echo "<div class='alert alert-success'><strong>Success!</strong> আপনার ছুটির আবেদনটি অনুমোদনের জন্য যথাযথ কর্তৃপক্ষের কাছে প্রেরণ করা হয়েছে ।</div>";

		// Commit transaction
        mysqli_commit($con);

        if (function_exists('audit_log')) {
            audit_log('leave_application_submitted', [
                'target_type' => 'leave_application',
                'target_id'   => (int)$leaveApplicationID,
                'note'        => 'applicantID=' . ($employeeID ?? '') . '; onbehalf=' . ($onbehalf ?? 0),
            ]);
        }


	}else{

	echo 0;
	
	}


} catch (\Throwable $e) {
    // Rollback on failure (catches both Exception and PHP 8 Error/TypeError)
    mysqli_rollback($con);
    echo "<div class='alert alert-danger'><strong>Error:</strong> " . $e->getMessage() . "</div>";
}



?>