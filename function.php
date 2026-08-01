<?php
if (defined('FUNCTION_PHP_LOADED')) { return; }
define('FUNCTION_PHP_LOADED', true);

function todayDateFunc()
{

$hour = gmdate("H");

$minute = gmdate("i");

$seconds = gmdate("s");

$day = gmdate("d");

$month = gmdate("m");

$year = gmdate("Y");

// This is the offset from the server time to Bangladesh time.

$hour = $hour + 6;

//return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

}

// Alias used by many API files (insert-application.php, approve-action.php, etc.).
// Guarded so legacy files that locally redefine `todayDate()` don't conflict.
if (!function_exists('todayDate')) {
    function todayDate() { return todayDateFunc(); }
}

// Bangladesh timezone helpers — originally defined in bddate.php which moved to old/.
// Several API files include the old path with plain `include()` (not include_once),
// which now silently fails. Re-declare here, guarded.
if (!function_exists('logTime')) {
    function logTime() {
        $hour = gmdate("H") + 6;
        return date("H:i:s", mktime($hour, gmdate("i"), gmdate("s"), gmdate("m"), gmdate("d"), gmdate("Y")));
    }
}
if (!function_exists('ShowBangladeshTime')) {
    function ShowBangladeshTime() {
        $hour = gmdate("H") + 6;
        return date("Y-m-d H:i:s", mktime($hour, gmdate("i"), gmdate("s"), gmdate("m"), gmdate("d"), gmdate("Y")));
    }
}
if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        $keys = ['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED','HTTP_FORWARDED_FOR','HTTP_FORWARDED','REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) return $_SERVER[$k];
        }
        return 'UNKNOWN';
    }
}


function calculateLeave($employeeID){
    global $con;

$casualBalance = 20;

$casualStart = date('Y').'-'.'01'.'-'.'01';

$casualEnd = date('Y').'-'.'12'.'-'.'31';

// prev hostory

$getPrevLeaveHistory = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$employeeID'");
$getPrevLeaveHistoryRW = mysqli_fetch_assoc($getPrevLeaveHistory);

//....

$str = "";

$getTotalCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalCasual from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3' and (approvedDateFrom >='$casualStart' and approvedDateTo<='$casualEnd')");
$getTotalCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ);


$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLWithoutPay from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='4'");
$getTotalLWioutPayLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLWioutPayLeaveInCurrentYearQ);


$getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
$getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);


$getTotalhalfAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalhalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
$getTotalhalfAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalhalfAvgSalLeaveUsedQ);


$calculateWPAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalWPDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='4' and isApproved=1");
$calculateWPAvgQRW = mysqli_fetch_assoc($calculateWPAvgQ);


// extraordinary leave, new added


$getTotalLExtraOrdinaryLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalExorLeave from leave_applications where status=1 and applicantID='$employeeID' and (leaveTypeInTwo='10')");
$getTotalLExtraOrdinaryLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLExtraOrdinaryLeaveInCurrentYearQ);	
								
//.............

// extraordinary leave, new added

$calculateExOrLManualQ = mysqli_query($con, "select sum(leaveDeduction) as totalExODeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='10' and isApproved=1");
$calculateExOrLManualQRW = mysqli_fetch_assoc($calculateExOrLManualQ);								

//

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

if($getEmployeeDetailsQRW['joining_date'] != ''){

$joiningDateArray = explode('-', $getEmployeeDetailsQRW['joining_date']);

$joiningDate = $joiningDateArray['2'].'/'.$joiningDateArray[1].'/'.$joiningDateArray[0];

$todayDate = todayDateFunc();


$datediff = abs(strtotime($todayDate)-strtotime($getEmployeeDetailsQRW['joining_date']));

$employmentyears = floor($datediff / (365*60*60*24));

$employmentmonths = floor(($datediff - $employmentyears * 365*60*60*24) / (30*60*60*24));

$employmentdays = floor(($datediff - $employmentyears * 365*60*60*24 - $employmentmonths*30*60*60*24)/ (60*60*24));



$diff = abs(strtotime($todayDate)-strtotime($getEmployeeDetailsQRW['joining_date']));

								$days = round($diff/(60*60*24));

								// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days
$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay']);

$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction'] + $getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] + $getPrevLeaveHistoryRW['extraOrdinaryLeave'] + $calculateExOrLManualQRW['totalExODeduction']); // updated

//echo "কার্যকাল".$days."<br>";

$str = $str.$days;



								$fullAvgSalLeave = floor($days/11);

								$fullAvgSalLeaveRemainder = fmod($days, 11);

								if($fullAvgSalLeaveRemainder >= 6){

									$fullAvgSalLeave = $fullAvgSalLeave + 1;
								
								}


								$halfAvgSalLeave = floor($days/12);

								$halfAvgSalLeaveRemainder = fmod($days, 12);

								if($halfAvgSalLeaveRemainder >= 6){

									$halfAvgSalLeave = $halfAvgSalLeave + 1;
								
								}
	// deduct from prev
	
	$fullAvgSalLeave = $fullAvgSalLeave - $getPrevLeaveHistoryRW['avgSalary'];

	$halfAvgSalLeave = $halfAvgSalLeave - $getPrevLeaveHistoryRW['halfAvgSalary'];

	$fullAvgSalLeaveyears = floor($fullAvgSalLeave / 365);
    $fullAvgSalLeavemonths = floor(($fullAvgSalLeave - ($fullAvgSalLeaveyears * 365))/30.5);
    $fullAvgSalLeavedays = round($fullAvgSalLeave - ($fullAvgSalLeaveyears * 365) - ($fullAvgSalLeavemonths * 30.5));


	$halfAvgSalLeaveyears = floor($halfAvgSalLeave / 365);
    $halfAvgSalLeavemonths = floor(($halfAvgSalLeave - ($halfAvgSalLeaveyears * 365))/30.5);
    $halfAvgSalLeavedays = round($halfAvgSalLeave - ($halfAvgSalLeaveyears * 365) - ($halfAvgSalLeavemonths * 30.5));


}else{

$fullAvgSalLeave = 0;

$halfAvgSalLeave = 0;

}

$fullavgInDays = $fullAvgSalLeave - $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'];


$calcyears = floor($fullavgInDays / 365);

$calcmonths = floor(($fullavgInDays % 365) / 30);

$calcdays = ($fullavgInDays % 365) % 30;

//echo "Years: ".$calcyears;

$halfavgInDays = ($halfAvgSalLeave - $getTotalhalfAvgSalLeaveUsedQRW['totalhalfAvgSal']);

$calcyears2 = floor($halfavgInDays / 365);

$calcmonths2 = floor(($halfavgInDays % 365) / 30);

$calcdays2 = ($halfavgInDays % 365) % 30;

//echo "নৈমিত্তিক ছুটি ".($casualBalance - $getTotalCasualLeaveInCurrentYearQRW['totalCasual'])."<br>";

$str = $str."_".($casualBalance - $getTotalCasualLeaveInCurrentYearQRW['totalCasual']);

//echo "গড়-বেতনে ছুটি ".$calcyears.'/'.$calcmonths.'/'.$calcdays."<br>";

$str = $str."_".$calcyears."_".$calcmonths."_".$calcdays;

//echo "অর্ধ-গড় বেতনে ছুটি".$calcyears2.'/'.$calcmonths2.'/'.$calcdays2."<br>";

$str = $str."_".$calcyears2."_".$calcmonths2."_".$calcdays2;

return $str;


}


//echo calculateLeave(872);

function monthlyLeaveSummary($employeeID, $year, $month){
    global $con;

	 $firstDay = date('Y-m-01', strtotime("$year-$month-01")); // First day of the month

	 $lastDay = date('Y-m-t', strtotime("$year-$month-01")); // Last day of the month


	 $getTotalLeaveDaysQ = mysqli_query($con, "select sum(approvedDays) as totalLConsumed from leave_applications where applicantID='$employeeID' and status=1 and approvedDateFrom>='$firstDay' and approvedDateTo<='$lastDay'");
	 $getTotalLeaveDaysQRW = mysqli_fetch_assoc($getTotalLeaveDaysQ);

	 return $getTotalLeaveDaysQRW['totalLConsumed'];


}

//echo monthlyLeaveSummary(1, 2023, 07);


/**
 * getEmployeeLeaveInfo($employeeID)
 *
 * Returns a comprehensive array of leave balances and service duration
 * for a given employee. Uses globals set in config/connection.php:
 *   $con, $casualBalance, $casualStart, $casualEnd, $optionalLBalance
 *
 * Return keys:
 *   employment         → ['years','months','days']
 *   fullAvgEarned      → ['total','years','months','days']
 *   fullAvgUsed        → ['total','years','months','days']
 *   fullAvgBalance     → ['total','years','months','days']
 *   halfAvgEarned      → ['total','years','months','days']
 *   halfAvgUsed        → ['total','years','months','days']
 *   halfAvgBalance     → ['total','years','months','days']
 *   withoutPay         → ['total','years','months','days']
 *   extraOrdinary      → ['total','years','months','days']
 *   undeductible       → ['total']
 *   casual             → ['balance','spent']
 *   optional           → ['balance','spent']
 *   actualDuration     → ['total','years','months','days']
 */
function getEmployeeLeaveInfo($employeeID) {
    global $con, $casualBalance, $casualStart, $casualEnd, $optionalLBalance;

    // ── helpers ──────────────────────────────────────────────────────
    // Unified Y/M/D conversion: 365-day year, 30-day month (gov standard)
    // with proper overflow handling — 30 days → 1 month, 12 months → 1 year
    $ymd = function($totalDays) {
        $totalDays = max(0, (int)round($totalDays));
        $y = (int)floor($totalDays / 365);
        $rem = $totalDays - $y * 365;
        $m = (int)floor($rem / 30);
        $d = (int)round($rem - $m * 30);
        if ($d >= 30) { $m += (int)floor($d / 30); $d = $d % 30; }
        if ($m >= 12) { $y += (int)floor($m / 12); $m = $m % 12; }
        return ['total' => $totalDays, 'years' => $y, 'months' => $m, 'days' => $d];
    };

    // ── previous leave history ────────────────────────────────────────
    $prevQ  = mysqli_query($con, "SELECT * FROM previous_leave_deduction WHERE employeeID='$employeeID' AND isApproved=1");
    $prev   = mysqli_fetch_assoc($prevQ) ?: [
        'undeductibleLeave' => 0, 'leaveWithoutPay' => 0,
        'extraOrdinaryLeave' => 0, 'avgSalary' => 0, 'halfAvgSalary' => 0,
    ];

    // ── manual deduction history ──────────────────────────────────────
    $deductQ = function($leaveID, $dateFilter = '') use ($con, $employeeID, $casualStart, $casualEnd) {
        $where = "employeeID='$employeeID' AND leaveID='$leaveID' AND isApproved=1";
        if ($dateFilter) $where .= " AND (createDate BETWEEN '$casualStart' AND '$casualEnd')";
        $r = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(leaveDeduction),0) AS v FROM leave_deduction_history WHERE $where"));
        return (int)$r['v'];
    };

    $deductFullAvg    = $deductQ('1');
    $deductHalfAvg    = $deductQ('2');
    $deductCasual     = $deductQ('3', true);
    $deductWithoutPay = $deductQ('4');
    $deductOptional   = $deductQ('5', true);
    $deductExtraOrd   = $deductQ('10');
    $deductUndeduct   = $deductQ('6');

    // ── manual addition history ───────────────────────────────────────
    // Office-order additions CREDIT the employee's balance. Internally we
    // subtract them from the "used" total so balance = earned - (used - added).
    // Date-filtered for the same leave types as deductions (casual, optional).
    $addQ = function($leaveID, $dateFilter = '') use ($con, $employeeID, $casualStart, $casualEnd) {
        $where = "employeeID='$employeeID' AND leaveID='$leaveID' AND isApproved=1";
        if ($dateFilter) $where .= " AND (createDate BETWEEN '$casualStart' AND '$casualEnd')";
        $r = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(leaveAddition),0) AS v FROM leave_addition_history WHERE $where"));
        return (int)$r['v'];
    };

    $addFullAvg    = $addQ('1');
    $addHalfAvg    = $addQ('2');
    $addCasual     = $addQ('3', true);
    $addWithoutPay = $addQ('4');
    $addOptional   = $addQ('5', true);
    $addExtraOrd   = $addQ('10');
    $addUndeduct   = $addQ('6');

    // ── leave_applications totals — multi-segment aware ──────────────
    // Counts per-segment leave types from leave_application_segments (kind='proposed')
    // for status=1 applications. This correctly handles multi-segment apps where
    // different segments deduct from different balance buckets.
    $hasSegKindCol = false;
    $colC = mysqli_query($con, "SHOW COLUMNS FROM leave_application_segments LIKE 'kind'");
    if ($colC && mysqli_num_rows($colC) > 0) $hasSegKindCol = true;
    $kindClause = $hasSegKindCol ? " AND (s.kind='proposed' OR s.kind IS NULL) " : "";

    $appSum = function($segLeaveType, $dateFilter = '', $multiplier = 1) use ($con, $employeeID, $casualStart, $casualEnd, $kindClause) {
        $employeeIDEsc = mysqli_real_escape_string($con, (string)$employeeID);
        $segLeaveType  = (int)$segLeaveType;
        $where = "la.status=1 AND la.applicantID='$employeeIDEsc' AND s.leaveType='$segLeaveType' $kindClause";
        if ($dateFilter) $where .= " AND (s.dateFrom BETWEEN '$casualStart' AND '$casualEnd')";
        $sql = "SELECT COALESCE(SUM(s.days),0)*$multiplier AS v
                FROM leave_application_segments s
                INNER JOIN leave_applications la ON la.dataID = s.applicationID
                WHERE $where";
        $r = mysqli_fetch_assoc(mysqli_query($con, $sql));
        return (float)($r['v'] ?? 0);
    };

    // Map: segment leaveType (= leave_types.leaveID) — values used per BSR leave types
    //   1 = পূর্ণ গড় বেতনে, 2 = অর্ধ-গড় বেতনে, 5 = সংগনিরোধ, 6 = প্রসূতি,
    //   7 = ঐচ্ছিক, 8 = নৈমিত্তিক, 9 = বিনা বেতনে, 10 = অসাধারণ, 19 = অক্ষমতাজনিত
    $appFullAvg    = $appSum(1);                                 // পূর্ণ গড়
    $appHalfAvg    = $appSum(2, '', 2);                          // অর্ধ-গড় (*2: half-avg days count double)
    $appHalfAvgActual = $appSum(2);                              // actual days (no multiplier) — for display
    $appCasual     = $appSum(8, true);                           // নৈমিত্তিক (with date filter)
    $appWithoutPay = $appSum(9);                                 // বিনা বেতনে
    $appExtraOrd   = $appSum(10);                                // অসাধারণ
    $appOptional   = $appSum(7, true);                           // ঐচ্ছিক (with date filter)
    $appUndeduct   = $appSum(5) + $appSum(6) + $appSum(19);      // সংগনিরোধ + প্রসূতি + অক্ষমতাজনিত (undeductible)

    // ── used totals (net of additions) ────────────────────────────────
    // Office-order additions reduce the effective "used" total per bucket,
    // so leave balance = earned - (prev + apps + deductions - additions).
    // max(0, ...) guards against historical over-credits that would make
    // a used total go negative (would otherwise inflate balance artificially).
    $totalFullAvgUsed    = max(0, ($prev['avgSalary']          ?? 0) + $appFullAvg    + $deductFullAvg    - $addFullAvg);
    $totalHalfAvgUsed    = max(0, ($prev['halfAvgSalary']      ?? 0) + $appHalfAvg    + $deductHalfAvg    - $addHalfAvg);   // doubled (for balance calc)
    $totalHalfAvgUsedActual = max(0, ($prev['halfAvgSalary']   ?? 0) + $appHalfAvgActual + $deductHalfAvg - $addHalfAvg);   // actual days (for display)
    $totalWithoutPay     = max(0, ($prev['leaveWithoutPay']    ?? 0) + $appWithoutPay + $deductWithoutPay - $addWithoutPay);
    $totalExtraOrdinary  = max(0, ($prev['extraOrdinaryLeave'] ?? 0) + $appExtraOrd   + $deductExtraOrd   - $addExtraOrd);
    $totalUndeductible   = max(0, ($prev['undeductibleLeave']  ?? 0) + $appUndeduct   + $deductUndeduct   - $addUndeduct);
    $casualSpent         = max(0, $appCasual   + $deductCasual   - $addCasual);
    $optionalSpent       = max(0, $appOptional + $deductOptional - $addOptional);

    // ── employee joining date → service duration ──────────────────────
    $empQ  = mysqli_query($con, "SELECT joining_date FROM employee_list WHERE id='$employeeID'");
    $emp   = mysqli_fetch_assoc($empQ);
    $today = date('Y-m-d');

    if (!empty($emp['joining_date'])) {
        $diff = abs(strtotime($today) - strtotime($emp['joining_date']));
        $days = round($diff / 86400) - 1;   // subtract today
    } else {
        $days = 0;
    }

    // চাকরিকাল — same gov convention (365/30 with overflow)
    $empYears  = (int)floor($days / 365);
    $rem       = $days - $empYears * 365;
    $empMonths = (int)floor($rem / 30);
    $empDays   = (int)round($rem - $empMonths * 30);
    if ($empDays >= 30)  { $empMonths += (int)floor($empDays / 30);  $empDays = $empDays % 30; }
    if ($empMonths >= 12){ $empYears  += (int)floor($empMonths / 12); $empMonths = $empMonths % 12; }

    // ── entitlement: full avg salary leave (1 per 11 qualifying days) ──
    $fullAvgEarned = floor($days / 11);
    if (fmod($days, 11) >= 6) $fullAvgEarned++;

    // ── entitlement: half avg salary leave (1 per 12 qualifying days) ──
    $halfAvgEarned = floor($days / 12);
    if (fmod($days, 12) >= 6) $halfAvgEarned++;

    // ── balances ──────────────────────────────────────────────────────
    $fullAvgBalance = $fullAvgEarned - $totalFullAvgUsed;
    $halfAvgBalance = $halfAvgEarned - $totalHalfAvgUsed;
    $casualBalance_ = $casualBalance  - $casualSpent;

    // Optional leave: credit is now driven ENTIRELY by approved pre-approvals
    // for the current calendar year. If employee has no approved pre-approval,
    // balance = 0 (BSR/pre-approval requirement). Falls back to hardcoded
    // $optionalLBalance ONLY as legacy safety if the pre-approval table is missing.
    $optionalCredit = 0;
    $preApprovalTbl = mysqli_query($con, "SHOW TABLES LIKE 'optional_leave_pre_approval'");
    if ($preApprovalTbl && mysqli_num_rows($preApprovalTbl) > 0) {
        $curYear = (int)date('Y');
        $creditQ = mysqli_query($con,
            "SELECT COALESCE(SUM(requested_days), 0) AS credit
             FROM optional_leave_pre_approval
             WHERE employee_id = '$employeeID' AND year = $curYear AND status = 1");
        $creditRow = mysqli_fetch_assoc($creditQ);
        $optionalCredit = (float)($creditRow['credit'] ?? 0);
    } else {
        $optionalCredit = (float)$optionalLBalance; // legacy fallback
    }
    $optionalBalance = $optionalCredit - $optionalSpent;

    // ── BSR §12-A split: full-pay earned leave ─────────────────────────
    // একবারে সর্বোচ্চ ১২০ দিন ভোগ করা যায়; অতিরিক্ত জমা = রিজার্ভ (অবসরে encashable)
    $FULL_AVG_MAX_AT_ONCE = 120; // days
    $fullAvgAvailable = max(0, min($fullAvgBalance, $FULL_AVG_MAX_AT_ONCE));
    $fullAvgReserve   = max(0, $fullAvgBalance - $FULL_AVG_MAX_AT_ONCE);

    // ── actual job duration (BSR compliant) ─────────────────────────────
    // Per BSR (চাকরির বিধানাবলী, পৃ. ১৪৫): মোট চাকরিকাল − ভোগকৃত সব ছুটি (actual days)
    // অর্ধ-গড় leave এখানে ACTUAL days হিসেবে বাদ যায় (×২ multiplier সরানো হয়েছে — সেটা শুধু balance accrual-এর জন্য)
    $actualDays = $days - ($totalFullAvgUsed + $totalHalfAvgUsedActual + $totalWithoutPay + $totalExtraOrdinary + $totalUndeductible);
    $actualDays = max(0, $actualDays);
    $adY = floor($actualDays / 365);
    $adM = floor(($actualDays - $adY * 365) / 30);
    $adD = (int)round($actualDays - $adY * 365 - $adM * 30);
    // Edge case: if days >= 30 (e.g. 35), shift to next month
    if ($adD >= 30) { $adM += floor($adD / 30); $adD = $adD % 30; }
    if ($adM >= 12) { $adY += floor($adM / 12); $adM = $adM % 12; }
    $actualDuration = ['total' => $actualDays, 'years' => $adY, 'months' => $adM, 'days' => $adD];

    return [
        'employment'    => ['years' => $empYears, 'months' => $empMonths, 'days' => $empDays],
        'fullAvgEarned' => $ymd($fullAvgEarned),
        'fullAvgUsed'   => $ymd($totalFullAvgUsed),
        'fullAvgBalance'=> $ymd($fullAvgBalance),
        'fullAvgAvailable' => $ymd($fullAvgAvailable),
        'fullAvgReserve'   => $ymd($fullAvgReserve),
        'halfAvgEarned' => $ymd($halfAvgEarned),
        'halfAvgUsed'   => $ymd($totalHalfAvgUsed),                  // doubled (legacy *2 multiplier)
        'halfAvgUsedActual' => $ymd($totalHalfAvgUsedActual),        // actual days taken (for display)
        'halfAvgBalance'=> $ymd($halfAvgBalance),
        'withoutPay'    => $ymd($totalWithoutPay),
        'extraOrdinary' => $ymd($totalExtraOrdinary),
        'undeductible'  => ['total' => $totalUndeductible],
        'casual'        => ['balance' => $casualBalance_, 'spent' => $casualSpent],
        'optional'      => ['balance' => $optionalBalance, 'spent' => $optionalSpent],
        'actualDuration'=> $actualDuration,
    ];
}

/**
 * Generate readable leave application number.
 * Format: BITAC/{year}/{dataID}  (e.g., BITAC/2026/15)
 *
 * @param int|string $dataID    leave_applications.dataID
 * @param string     $submitDate  Y-m-d (defaults to today if empty/invalid)
 * @return string
 */
function generateApplicationNo($dataID, $submitDate = '') {
    $year = ($submitDate && $submitDate !== '0000-00-00')
        ? date('Y', strtotime($submitDate))
        : date('Y');
    return 'BITAC/' . $year . '/' . (int)$dataID;
}

/**
 * Write a row to the universal audit_log table.
 *
 * Usage:
 *   audit_log('login_success');
 *   audit_log('user_created', [
 *       'target_type' => 'user',
 *       'target_id'   => $newID,
 *       'note'        => 'username='.$user_id,
 *   ]);
 *   audit_log('login_failed', [
 *       'actor_username' => $attemptedUsername,
 *       'note'           => 'wrong password',
 *   ]);
 *
 * Options (all optional):
 *   actor_user_id   — override session-resolved actor
 *   actor_name
 *   actor_username
 *   target_type     — domain noun: 'user', 'leave_application', 'role_proposal', etc.
 *   target_id
 *   organization_id
 *   note            — free-form context (kept short — full payloads are not logged)
 *
 * Failure handling: every error is swallowed. Audit logging must never break
 * the actual business flow — if the audit table is missing or insert fails,
 * the calling action still succeeds.
 */
if (!function_exists('audit_log')) {
    function audit_log($action, array $opts = []) {
        global $con;
        if (!$con) return;
        if (!is_string($action) || $action === '') return;

        try {
            // Resolve actor from session if not explicitly given
            $actorUserId = $opts['actor_user_id'] ?? null;
            $actorName   = $opts['actor_name']    ?? null;
            $actorUname  = $opts['actor_username'] ?? null;

            if (($actorUserId === null || $actorName === null) && !empty($_SESSION['username'])) {
                $uStmt = mysqli_prepare($con,
                    "SELECT ul.dataID, ul.user_id, ul.full_name, el.employee_name
                     FROM user_list ul
                     LEFT JOIN employee_list el ON ul.employee_id = el.id
                     WHERE ul.user_id = ? LIMIT 1");
                if ($uStmt) {
                    mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
                    mysqli_stmt_execute($uStmt);
                    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
                    mysqli_stmt_close($uStmt);
                    if ($r) {
                        if ($actorUserId === null) $actorUserId = (int)$r['dataID'];
                        if ($actorName === null)   $actorName   = $r['employee_name'] ?: $r['full_name'] ?: $r['user_id'];
                        if ($actorUname === null)  $actorUname  = $r['user_id'];
                    }
                }
            }
            if ($actorUname === null && !empty($_SESSION['username'])) {
                $actorUname = $_SESSION['username'];
            }

            $targetType = $opts['target_type'] ?? null;
            $targetId   = isset($opts['target_id']) ? (int)$opts['target_id'] : null;
            $orgId      = isset($opts['organization_id']) ? (int)$opts['organization_id'] : null;
            $note       = $opts['note'] ?? null;
            if (is_string($note)) $note = mb_substr($note, 0, 4000); // cap

            $ip  = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null);
            if (is_string($ip)) $ip = substr($ip, 0, 45);
            $ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if (is_string($ua)) $ua = substr($ua, 0, 500);
            $url = ($_SERVER['REQUEST_URI'] ?? null);
            if (is_string($url)) $url = substr($url, 0, 500);

            $stmt = mysqli_prepare($con,
                "INSERT INTO audit_log
                 (action, actor_user_id, actor_name, actor_username,
                  target_type, target_id, organization_id,
                  ip_address, user_agent, request_url, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) return;
            $actorUserIdSafe = ($actorUserId !== null) ? (int)$actorUserId : null;
            $targetIdSafe    = ($targetId    !== null) ? (int)$targetId    : null;
            $orgIdSafe       = ($orgId       !== null) ? (int)$orgId       : null;
            mysqli_stmt_bind_param($stmt, 'sisssiissss',
                $action,
                $actorUserIdSafe,
                $actorName,
                $actorUname,
                $targetType,
                $targetIdSafe,
                $orgIdSafe,
                $ip,
                $ua,
                $url,
                $note
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } catch (Throwable $e) {
            // Silent — audit failures must never break the caller
        }
    }
}

/**
 * Fan out an in-app notification to one or more users.
 *
 * All approval endpoints call this — never write to `notification` directly.
 * Silent-on-failure so a notification insert never breaks the underlying
 * business operation (approve/reject succeeds even if inbox write fails).
 *
 * Usage:
 *   send_notification([$applicantUserID], 'আপনার ছুটি অনুমোদিত হয়েছে', [
 *       'type'        => 'leave_approved',
 *       'link'        => '/views/leave/all-applications.php',
 *       'isImportant' => 1,
 *   ]);
 *
 * @param int|int[] $userIDs      Target user_list.dataID (or array of them)
 * @param string    $message      Bangla / plain-text message (< 400 chars ideal)
 * @param array     $opts         Optional: type (short slug), link (relative URL),
 *                                isImportant (0|1), typeBadge (raw HTML badge —
 *                                legacy `notificationType` blob for backward compat)
 * @return int  count of rows inserted
 */
if (!function_exists('send_notification')) {
    function send_notification($userIDs, $message, array $opts = []) {
        if (!isset($GLOBALS['con']) || !$GLOBALS['con']) return 0;
        $con = $GLOBALS['con'];

        // Normalize + dedupe recipients
        if (!is_array($userIDs)) $userIDs = [$userIDs];
        $userIDs = array_values(array_unique(array_filter(array_map('intval', $userIDs), function($v){ return $v > 0; })));
        if (empty($userIDs) || trim($message) === '') return 0;

        $type        = isset($opts['type'])        ? (string)$opts['type']        : '';
        $link        = isset($opts['link'])        ? (string)$opts['link']        : '';
        $isImportant = isset($opts['isImportant']) ? (int)(bool)$opts['isImportant'] : 0;
        // Legacy DB uses `notificationType` as an HTML badge blob; if caller
        // supplies a plain slug via `type`, keep it as-is. UI keyword-matches on it.
        $notifType   = isset($opts['typeBadge'])   ? (string)$opts['typeBadge']   : $type;

        $now = date('Y-m-d H:i:s');
        $count = 0;
        try {
            $stmt = mysqli_prepare($con,
                "INSERT INTO notification (userID, message, notificationType, link, dateTime, isRead, isImportant)
                 VALUES (?, ?, ?, ?, ?, 0, ?)");
            if (!$stmt) return 0;
            foreach ($userIDs as $uid) {
                mysqli_stmt_bind_param($stmt, 'issssi', $uid, $message, $notifType, $link, $now, $isImportant);
                if (mysqli_stmt_execute($stmt)) $count++;
            }
            mysqli_stmt_close($stmt);
        } catch (Throwable $e) {
            // Silent — inbox write must never break the caller's business op
        }
        return $count;
    }
}

/**
 * Resolve user_list.dataID from an employee_list.id.
 *
 * Multiple approval flows only know the employee_id but need to notify the
 * user (notification.userID = user_list.dataID). Returns 0 if the employee
 * has no user account.
 */
if (!function_exists('user_id_for_employee')) {
    function user_id_for_employee($employeeID) {
        if (!isset($GLOBALS['con']) || !$GLOBALS['con']) return 0;
        $employeeID = (int)$employeeID;
        if ($employeeID <= 0) return 0;
        try {
            $stmt = mysqli_prepare($GLOBALS['con'],
                "SELECT dataID FROM user_list WHERE employee_id = ? LIMIT 1");
            if (!$stmt) return 0;
            mysqli_stmt_bind_param($stmt, 'i', $employeeID);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
            mysqli_stmt_close($stmt);
            return (int)($row['dataID'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

/**
 * Resolve user_list.dataID[] for a list of employee_list.id values (batch).
 * Skips employees without a user account.
 */
if (!function_exists('user_ids_for_employees')) {
    function user_ids_for_employees(array $employeeIDs) {
        if (!isset($GLOBALS['con']) || !$GLOBALS['con']) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $employeeIDs), function($v){ return $v > 0; })));
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        try {
            $stmt = mysqli_prepare($GLOBALS['con'],
                "SELECT dataID FROM user_list WHERE employee_id IN ($placeholders)");
            if (!$stmt) return [];
            mysqli_stmt_bind_param($stmt, $types, ...$ids);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $out = [];
            while ($r = mysqli_fetch_assoc($res)) $out[] = (int)$r['dataID'];
            mysqli_stmt_close($stmt);
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}

?>
