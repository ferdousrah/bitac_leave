<?php
/**
 * Leave Report PDF Viewer (Admin - any employee)
 * Pattern: same as leave-self-pdf.php
 * Usage: api/reports/leave-pdf.php?employeeID=X&leaveTypeInTwo=Y&year=Z
 */

ini_set('memory_limit', '256M');
set_time_limit(120);
ob_start();

require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$action         = $_GET['action']        ?? 'view';
$employeeID     = isset($_GET['employeeID'])     ? (int)$_GET['employeeID']     : 0;
$leaveTypeInTwo = trim($_GET['leaveTypeInTwo']   ?? '');
$year           = trim($_GET['year']             ?? '');

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['username'])) {
    if ($action === 'generate') {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    } else {
        http_response_code(403);
        echo 'Unauthorized';
    }
    exit;
}

if ($employeeID <= 0) {
    if ($action === 'generate') {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid employee ID']);
    } else {
        echo '<p style="font-family:sans-serif;padding:20px">Invalid employee ID</p>';
    }
    exit;
}

if ($action === 'generate') {
    generatePDFData($employeeID, $leaveTypeInTwo, $year);
} else {
    showViewer($employeeID, $leaveTypeInTwo, $year);
}

// ─────────────────────────────────────────────────────────────────────────────
function _lrDate() {
    $hour = gmdate("H") + 6;
    return date("Y-m-d", mktime($hour, gmdate("i"), gmdate("s"), gmdate("m"), gmdate("d"), gmdate("Y")));
}

// ─────────────────────────────────────────────────────────────────────────────
function generatePDFData($employeeID, $leaveTypeInTwo, $year) {
    try {
        if (!class_exists('\Mpdf\Mpdf')) {
            throw new \Exception("mPDF not found.");
        }

        global $con, $obj, $casualBalance, $optionalLBalance, $casualStart, $casualEnd;

        // ── Date range filters ───────────────────────────────────────────────
        $leaveTypqSQL = '';
        if ($leaveTypeInTwo !== '') {
            $lt = mysqli_real_escape_string($con, $leaveTypeInTwo);
            $leaveTypqSQL = " AND leaveTypeInTwo='$lt'";
        }

        $yearSQL = '';
        if ($year !== '') {
            $y = mysqli_real_escape_string($con, $year);
            $casualStart = $y . '-01-01';
            $casualEnd   = $y . '-12-31';
            $yearSQL     = " AND (approvedDateFrom>='$casualStart' AND approvedDateTo<='$casualEnd')";
        }

        // ── Multi-segment aware spent helper ─────────────────────────────────
        // $segLeaveType = leave_types.leaveID matching segments.leaveType
        //   1=পূর্ণ গড়, 2=অর্ধ-গড়, 5=সংগনিরোধ, 6=প্রসূতি, 7=ঐচ্ছিক,
        //   8=নৈমিত্তিক, 9=বিনা বেতনে, 10=অসাধারণ, 19=অক্ষমতাজনিত
        $segSpent = function($empID, $segType, $useDateFilter = false, $multiplier = 1) use ($con, $casualStart, $casualEnd) {
            $empIDEsc = mysqli_real_escape_string($con, (string)$empID);
            $segType  = (int)$segType;
            $where = "la.status=1 AND la.applicantID='$empIDEsc' AND s.leaveType=$segType AND (s.kind='proposed' OR s.kind IS NULL)";
            if ($useDateFilter) $where .= " AND (s.dateFrom BETWEEN '$casualStart' AND '$casualEnd')";
            $sql = "SELECT COALESCE(SUM(s.days),0)*$multiplier AS v
                    FROM leave_application_segments s
                    INNER JOIN leave_applications la ON la.dataID = s.applicationID
                    WHERE $where";
            $r = mysqli_fetch_assoc(mysqli_query($con, $sql));
            return (float)($r['v'] ?? 0);
        };

        $todayDate = _lrDate();

        // ── Employee ─────────────────────────────────────────────────────────
        $empQ  = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$employeeID'");
        $emp   = mysqli_fetch_assoc($empQ);
        $desQ  = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($emp['designation']) . "'");
        $des   = mysqli_fetch_assoc($desQ);
        $secQ  = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($emp['section_id']) . "'");
        $sec   = mysqli_fetch_assoc($secQ);

        $birthArr   = explode('-', $emp['date_of_birth']);
        $birthDate  = $birthArr[2] . '/' . $birthArr[1] . '/' . $birthArr[0];
        $joinArr    = explode('-', $emp['joining_date']);
        $joiningDate = $joinArr[2] . '/' . $joinArr[1] . '/' . $joinArr[0];

        // ── Previous leave history ───────────────────────────────────────────
        $prevQ  = mysqli_query($con, "SELECT * FROM previous_leave_deduction WHERE employeeID='$employeeID' AND isApproved=1");
        $prev   = mysqli_fetch_assoc($prevQ);

        // ── Deduction sums ───────────────────────────────────────────────────
        $fullDedQ = mysqli_query($con, "SELECT SUM(leaveDeduction) AS v FROM leave_deduction_history WHERE employeeID='$employeeID' AND leaveID='1' AND isApproved=1"); $fullDed = mysqli_fetch_assoc($fullDedQ)['v'];
        $halfDedQ = mysqli_query($con, "SELECT SUM(leaveDeduction) AS v FROM leave_deduction_history WHERE employeeID='$employeeID' AND leaveID='2' AND isApproved=1"); $halfDed = mysqli_fetch_assoc($halfDedQ)['v'];
        $clDedQ   = mysqli_query($con, "SELECT SUM(leaveDeduction) AS v FROM leave_deduction_history WHERE employeeID='$employeeID' AND leaveID='3' AND isApproved=1 AND (createDate BETWEEN '$casualStart' AND '$casualEnd')"); $clDed = mysqli_fetch_assoc($clDedQ)['v'];
        $olDedQ   = mysqli_query($con, "SELECT SUM(leaveDeduction) AS v FROM leave_deduction_history WHERE employeeID='$employeeID' AND leaveID='5' AND isApproved=1 AND (createDate BETWEEN '$casualStart' AND '$casualEnd')"); $olDed = mysqli_fetch_assoc($olDedQ)['v'];
        $wpDedQ   = mysqli_query($con, "SELECT SUM(leaveDeduction) AS v FROM leave_deduction_history WHERE employeeID='$employeeID' AND leaveID='4' AND isApproved=1"); $wpDed = mysqli_fetch_assoc($wpDedQ)['v'];
        $exDedQ   = mysqli_query($con, "SELECT SUM(leaveDeduction) AS v FROM leave_deduction_history WHERE employeeID='$employeeID' AND leaveID='10' AND isApproved=1"); $exDed = mysqli_fetch_assoc($exDedQ)['v'];
        $unDedQ   = mysqli_query($con, "SELECT SUM(leaveDeduction) AS v FROM leave_deduction_history WHERE employeeID='$employeeID' AND leaveID='6' AND isApproved=1"); $unDed = mysqli_fetch_assoc($unDedQ)['v'];

        // ── Leave used sums (multi-segment aware) ────────────────────────────
        // segments leaveType: 1=পূর্ণ গড়, 2=অর্ধ-গড়, 7=ঐচ্ছিক, 8=নৈমিত্তিক, 9=বিনা বেতনে, 10=অসাধারণ
        // "কর্তনহীন" (legacy leaveTypeInTwo=6) = সংগনিরোধ(5) + প্রসূতি(6) + অক্ষমতাজনিত(19)
        $fullSpent = $segSpent($employeeID, 1);                               // পূর্ণ গড়
        $halfSpent = $segSpent($employeeID, 2, false, 2);                     // অর্ধ-গড় ×2 multiplier
        $clSpent   = $segSpent($employeeID, 8, true);                         // নৈমিত্তিক (year-filtered)
        $wpSpent   = $segSpent($employeeID, 9);                               // বিনা বেতনে
        $olSpent   = $segSpent($employeeID, 7, true);                         // ঐচ্ছিক (year-filtered)
        $exSpent   = $segSpent($employeeID, 10);                              // অসাধারণ
        $unSpent   = $segSpent($employeeID, 5) + $segSpent($employeeID, 6) + $segSpent($employeeID, 19); // কর্তনহীন

        // ── Employment duration — single source of truth: getEmployeeLeaveInfo ─
        $_li = getEmployeeLeaveInfo($employeeID);
        $empYears  = $_li['employment']['years'];
        $empMonths = $_li['employment']['months'];
        $empDays   = $_li['employment']['days'];

        $diff = abs(strtotime($todayDate) - strtotime($emp['joining_date']));
        $days = round($diff / 86400) - 1;

        $days -= ($wpSpent + ($prev['leaveWithoutPay'] ?? 0) + $wpDed + $exSpent + ($prev['extraOrdinaryLeave'] ?? 0) + $exDed);

        // ── Full avg salary leave ─────────────────────────────────────────────
        $fullAvg = floor($days / 11) + (fmod($days, 11) >= 6 ? 1 : 0);
        $faY = floor($fullAvg / 360); $faM = floor(($fullAvg - $faY*360)/30); $faD = round($fullAvg - $faY*360 - $faM*30);
        $totalFullUsed = ($prev['avgSalary'] ?? 0) + $fullSpent + $fullDed;
        $tfuY = floor($totalFullUsed / 360); $tfuM = floor(($totalFullUsed - $tfuY*360)/30); $tfuD = round($totalFullUsed - $tfuY*360 - $tfuM*30);
        $restFull = $fullAvg - $totalFullUsed;
        $rfY = floor($restFull / 360); $rfM = floor(($restFull - $rfY*360)/30); $rfD = round($restFull - $rfY*360 - $rfM*30);

        // ── Half avg salary leave ─────────────────────────────────────────────
        $halfAvg = floor($days / 12) + (fmod($days, 12) >= 6 ? 1 : 0);
        $haY = floor($halfAvg / 360); $haM = floor(($halfAvg - $haY*360)/30); $haD = round($halfAvg - $haY*360 - $haM*30);
        $totalHalfUsed = ($prev['halfAvgSalary'] ?? 0) + $halfSpent + $halfDed;
        $thuY = floor($totalHalfUsed / 360); $thuM = floor(($totalHalfUsed - $thuY*360)/30); $thuD = round($totalHalfUsed - $thuY*360 - $thuM*30);
        $restHalf = $halfAvg - $totalHalfUsed;
        $rhY = floor($restHalf / 360); $rhM = floor(($restHalf - $rhY*360)/30); $rhD = round($restHalf - $rhY*360 - $rhM*30);

        // ── Without pay totals ────────────────────────────────────────────────
        $totalWP = $wpSpent + ($prev['leaveWithoutPay'] ?? 0) + $wpDed;
        $wpY = floor($totalWP / 360); $wpM = floor(($totalWP - $wpY*360)/30); $wpD = round($totalWP - $wpY*360 - $wpM*30);

        // ── Undeductible leave ────────────────────────────────────────────────
        $totalUn = ($prev['undeductibleLeave'] ?? 0) + $unDed + $unSpent;
        $unY = floor($totalUn / 360); $unM = floor(($totalUn - $unY*360)/30); $unD = round($totalUn - $unY*360 - $unM*30);

        // ── CL / Optional — single source of truth: getEmployeeLeaveInfo (matches dashboard) ──
        $casualSpent  = $_li['casual']['spent'];
        $clBalance    = $_li['casual']['balance'];
        $olSpentTotal = $_li['optional']['spent'];
        $olBalance    = $_li['optional']['balance'];

        // ── Orjito totals ─────────────────────────────────────────────────────
        $totalOrjito = $fullSpent + $halfSpent;
        $toY = floor($totalOrjito/360); $toM = floor(($totalOrjito-$toY*360)/30); $toD = round($totalOrjito-$toY*360-$toM*30);
        $fSpentY = floor($fullSpent/360); $fSpentM = floor(($fullSpent-$fSpentY*360)/30); $fSpentD = round($fullSpent-$fSpentY*360-$fSpentM*30);
        $hSpentY = floor($halfSpent/360); $hSpentM = floor(($halfSpent-$hSpentY*360)/30); $hSpentD = round($halfSpent-$hSpentY*360-$hSpentM*30);

        // ── Queries for loop tables ──────────────────────────────────────────
        $leaveTypesQ = mysqli_query($con, "SELECT * FROM leave_types WHERE leaveID NOT IN (1,2,3)");
        $leaveAppsQ  = mysqli_query($con, "SELECT * FROM leave_applications WHERE applicantID='$employeeID' AND status=1 $leaveTypqSQL $yearSQL ORDER BY dataID DESC");
        $leaveDedQ   = mysqli_query($con, "SELECT * FROM leave_deduction_history WHERE isApproved=1 AND employeeID='$employeeID'");

        // ── Build HTML ───────────────────────────────────────────────────────
        $td  = 'style="border:1px solid #ccc; padding:4px 6px;"';
        $th  = 'style="border:1px solid #ccc; padding:4px 6px; background:#f0f0f0;"';
        $tc  = 'style="border:1px solid #ccc; padding:4px 6px; text-align:center;"';
        $thc = 'style="border:1px solid #ccc; padding:4px 6px; background:#f0f0f0; text-align:center;"';

        ob_start();
        ?>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: kalpurush, sans-serif; font-size: 11px; }
table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
h2 { text-align: center; font-size: 15px; font-weight: normal; margin: 0 0 2px 0; }
h3 { text-align: center; font-size: 11px; font-weight: normal; margin: 0 0 8px 0; }
h4 { font-size: 12px; font-weight: normal; margin: 8px 0 4px 0; }
th { font-weight: normal; }
</style>
</head>
<body>

<h2>বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</h2>
<h3>১১৬(খ), তেজগাঁও শিল্প এলাকা, ঢাকা-১২০৮</h3>

<table>
    <tr><td <?=$td?>>নাম</td><td <?=$td?> colspan="3"><?=htmlspecialchars($emp['employee_name'])?></td></tr>
    <tr><td <?=$td?>>জাতীয় পরিচয়পত্র নং</td><td <?=$td?> colspan="3"><?=htmlspecialchars($emp['nationalID'])?></td></tr>
    <tr><td <?=$td?>>শাখা</td><td <?=$td?> colspan="3"><?=htmlspecialchars($sec['section_name'])?></td></tr>
    <tr><td <?=$td?>>পদবি</td><td <?=$td?> colspan="3"><?=htmlspecialchars($des['job_title_name'])?></td></tr>
    <tr><td <?=$td?>>আইডি</td><td <?=$td?> colspan="3"><?=$obj->engToBn($emp['employee_id'])?></td></tr>
    <tr>
        <td <?=$td?>>জন্ম তারিখ</td><td <?=$td?>><?=$obj->engToBn($birthDate)?></td>
        <td <?=$td?>>চাকরিতে যোগদানের তারিখ</td><td <?=$td?>><?=$obj->engToBn($joiningDate)?></td>
    </tr>
    <tr>
        <td <?=$td?>>ইমেইল</td><td <?=$td?>><?=htmlspecialchars($emp['email'])?></td>
        <td <?=$td?>>মোবাইল নম্বর</td><td <?=$td?>><?=$obj->engToBn($emp['mobileNo'])?></td>
    </tr>
</table>

<h4>আপনার মোট চাকরিকাল (<?=$obj->engToBn($empYears)?> বছর <?=$obj->engToBn($empMonths)?> মাস <?=$obj->engToBn($empDays)?> দিন)</h4>

<table>
    <tr>
        <th <?=$th?>>ছুটির ধরণ</th>
        <th <?=$thc?>>মোট জমা ছুটি</th>
        <th <?=$thc?>>মোট ভোগকৃত ছুটি</th>
        <th <?=$thc?>>অবশিষ্ট পাওনা ছুটি</th>
    </tr>
    <tr>
        <td <?=$td?>>ক) গড় বেতনে</td>
        <td <?=$tc?>><?=$obj->engToBn($faY)?> বছর <?=$obj->engToBn($faM)?> মাস <?=$obj->engToBn($faD)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($tfuY)?> বছর <?=$obj->engToBn($tfuM)?> মাস <?=$obj->engToBn($tfuD)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($rfY)?> বছর <?=$obj->engToBn($rfM)?> মাস <?=$obj->engToBn($rfD)?> দিন</td>
    </tr>
    <tr>
        <td <?=$td?>>খ) অর্ধ-গড় বেতনে</td>
        <td <?=$tc?>><?=$obj->engToBn($haY)?> বছর <?=$obj->engToBn($haM)?> মাস <?=$obj->engToBn($haD)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($thuY)?> বছর <?=$obj->engToBn($thuM)?> মাস <?=$obj->engToBn($thuD)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($rhY)?> বছর <?=$obj->engToBn($rhM)?> মাস <?=$obj->engToBn($rhD)?> দিন</td>
    </tr>
    <tr>
        <td <?=$td?>>গ) নৈমিত্তিক</td>
        <td <?=$tc?>><?=$obj->engToBn($casualBalance)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn(number_format($casualSpent))?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($clBalance)?> দিন</td>
    </tr>
    <tr>
        <td <?=$td?>>ঘ) অসাধারণ (বিনা বেতনে ছুটি)</td>
        <td <?=$tc?> colspan="3"><?=$obj->engToBn($wpY)?> বছর <?=$obj->engToBn($wpM)?> মাস <?=$obj->engToBn($wpD)?> দিন</td>
    </tr>
    <tr>
        <td <?=$td?>>ঙ) ঐচ্ছিক ছুটি</td>
        <td <?=$tc?>><?=$obj->engToBn($optionalLBalance)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn(number_format($olSpentTotal))?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($olBalance)?> দিন</td>
    </tr>
    <tr>
        <td <?=$td?>>চ) কর্তনহীন ছুটি</td>
        <td <?=$tc?> colspan="3"><?=$obj->engToBn($unY)?> বছর <?=$obj->engToBn($unM)?> মাস <?=$obj->engToBn($unD)?> দিন</td>
    </tr>
</table>

<h4>ছুটির সংক্ষিপ্ত বিবরণী</h4>

<table>
    <tr>
        <th <?=$thc?>>ক্রমিক নং</th>
        <th <?=$th?>>ছুটির ধরণ</th>
        <th <?=$thc?>>ভোগকৃত ছুটি</th>
        <th <?=$thc?>>গড় বেতনে</th>
        <th <?=$thc?>>অর্ধ-গড় বেতনে</th>
    </tr>
    <tr>
        <td <?=$tc?>><?=$obj->engToBn(1)?></td>
        <td <?=$td?>>অর্জিত ছুটি</td>
        <td <?=$tc?>><?=$obj->engToBn($toY)?> বছর <?=$obj->engToBn($toM)?> মাস <?=$obj->engToBn($toD)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($fSpentY)?> বছর <?=$obj->engToBn($fSpentM)?> মাস <?=$obj->engToBn($fSpentD)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($hSpentY)?> বছর <?=$obj->engToBn($hSpentM)?> মাস <?=$obj->engToBn($hSpentD)?> দিন</td>
    </tr>
    <?php
    $ltSl = 1;
    while ($ltRow = mysqli_fetch_assoc($leaveTypesQ)) {
        $ltSl++;
        // Multi-segment aware: count days from segments matching this leaveType
        if ($ltRow['leaveID'] == 8) {           // নৈমিত্তিক
            $used = $segSpent($employeeID, 8, true);
            $fsl = 0; $hsl = 0;
        } elseif ($ltRow['leaveID'] == 3 || $ltRow['leaveID'] == 9) { // বিনা বেতনে (legacy 3 or modern 9)
            $used = $segSpent($employeeID, 9, true);
            $fsl = 0; $hsl = 0;
        } else {
            // For other types, count segments matching this leaveID across full + half pay buckets
            // (legacy logic split by leaveTypeInTwo; with segments, just count by segments.leaveType directly)
            $usedDirect = $segSpent($employeeID, (int)$ltRow['leaveID']);
            $fsl = (int)$ltRow['leaveID'] === 1 ? $usedDirect : 0;
            $hsl = (int)$ltRow['leaveID'] === 2 ? $usedDirect * 2 : 0;
            $used = $usedDirect + ($hsl ? $hsl - $usedDirect : 0); // half-pay double-count
        }
        $uy = floor($used/360); $um = floor(($used-$uy*360)/30); $ud = round($used-$uy*360-$um*30);
        $fy = floor($fsl/360);  $fm = floor(($fsl-$fy*360)/30);  $fd = round($fsl-$fy*360-$fm*30);
        $hy = floor($hsl/360);  $hm = floor(($hsl-$hy*360)/30);  $hd = round($hsl-$hy*360-$hm*30);
    ?>
    <tr>
        <td <?=$tc?>><?=$obj->engToBn($ltSl)?></td>
        <td <?=$td?>><?=htmlspecialchars($ltRow['leaveTitle'])?></td>
        <td <?=$tc?>><?=$obj->engToBn($uy)?> বছর <?=$obj->engToBn($um)?> মাস <?=$obj->engToBn($ud)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($fy)?> বছর <?=$obj->engToBn($fm)?> মাস <?=$obj->engToBn($fd)?> দিন</td>
        <td <?=$tc?>><?=$obj->engToBn($hy)?> বছর <?=$obj->engToBn($hm)?> মাস <?=$obj->engToBn($hd)?> দিন</td>
    </tr>
    <?php } ?>
</table>

<h4>নোটিশ বোর্ড</h4>

<table>
    <tr>
        <th <?=$thc?>>ক্রমিক নং</th>
        <th <?=$th?>>ছুটির বিষয়</th>
        <th <?=$th?>>অনুমোদিত ছুটি</th>
        <th <?=$thc?>>তারিখ</th>
        <th <?=$thc?>>দিন</th>
    </tr>
    <?php
    // Pull the labels straight from leave_types so we always match the DB.
    // The old hardcoded $lTypeLabels used ids (1..10) that did NOT map to
    // real leave_types.leaveID values (which include 8, 18, 19, 21, 22 etc.),
    // so many rows were mislabelled in the generated PDF.
    $lTypeLabels = [];
    $_labQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types");
    while ($_labR = mysqli_fetch_assoc($_labQ)) {
        $lTypeLabels[(int)$_labR['leaveID']] = $_labR['leaveTitle'];
    }
    $sl = 1;
    while ($lr = mysqli_fetch_assoc($leaveAppsQ)) {
        $ltQ  = mysqli_query($con, "SELECT * FROM leave_types WHERE leaveID='" . intval($lr['approvedLeaveType']) . "'");
        $ltR  = mysqli_fetch_assoc($ltQ);
        $dateF = date_create($lr['approvedDateFrom']);
        $dateT = date_create($lr['approvedDateTo']);
        $approvedLType = $lTypeLabels[(int)$lr['leaveTypeInTwo']] ?? '';
    ?>
    <tr>
        <td <?=$tc?>><?=$obj->engToBn($sl)?></td>
        <td <?=$td?>><?=htmlspecialchars($lr['subject'])?></td>
        <td <?=$td?>><?=htmlspecialchars($ltR['leaveTitle'] ?? '') ?> - <?=$approvedLType?></td>
        <td <?=$tc?>><?=banglaNumber(date_format($dateF,"d/m/Y"))?> হইতে <?=banglaNumber(date_format($dateT,"d/m/Y"))?></td>
        <td <?=$tc?>><?=banglaNumber($lr['approvedDays'])?></td>
    </tr>
    <?php $sl++; } ?>
</table>

<h4>ছুটি কর্তন (অফিস আদেশ অনুযায়ী)</h4>

<table>
    <tr>
        <th <?=$thc?>>ক্রমিক নং</th>
        <th <?=$th?>>ছুটির ধরণ</th>
        <th <?=$thc?>>ছুটি কর্তন (দিন)</th>
        <th <?=$thc?>>মন্তব্য</th>
    </tr>
    <?php
    // Same DB-backed lookup for deductions (see note above the first map).
    $leaveLabels = $lTypeLabels;
    $n = 1;
    while ($ld = mysqli_fetch_assoc($leaveDedQ)) { ?>
    <tr>
        <td <?=$tc?>><?=$obj->engToBn($n)?></td>
        <td <?=$td?>><?=$leaveLabels[$ld['leaveID']] ?? ''?></td>
        <td <?=$tc?>><?=$obj->engToBn($ld['leaveDeduction'])?></td>
        <td <?=$tc?>><?=htmlspecialchars($ld['note'])?></td>
    </tr>
    <?php $n++; } ?>
</table>

</body>
</html>
        <?php
        $html = ob_get_clean();

        $mpdf = new \Mpdf\Mpdf([
            'mode'             => 'utf-8',
            'default_font'     => 'kalpurush',
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
            'margin_left'      => 12,
            'margin_right'     => 12,
            'margin_top'       => 15,
            'margin_bottom'    => 15,
            'format'           => 'A4',
        ]);
        $mpdf->SetTitle('লিভ রিপোর্ট - ' . $emp['employee_name']);
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');
        $base64     = base64_encode($pdfContent);

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'pdfData'  => $base64,
            'filename' => 'leave_report_' . $emp['employee_id'] . '_' . date('Ymd') . '.pdf',
        ]);

    } catch (\Throwable $e) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
function showViewer($employeeID, $leaveTypeInTwo, $year) {
    while (ob_get_level() > 0) ob_end_clean();
    $generateUrl = '?action=generate&employeeID=' . urlencode($employeeID)
                 . '&leaveTypeInTwo=' . urlencode($leaveTypeInTwo)
                 . '&year=' . urlencode($year);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লিভ রিপোর্ট</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; overflow: hidden; }
        .toolbar { background: #fff; padding: 12px 20px; border-bottom: 1px solid #e0e0e0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .toolbar-title { font-weight: 600; font-size: 15px; color: #2d3748; margin-right: 10px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .btn-primary  { background: #667eea; color: white; }
        .btn-success  { background: #48bb78; color: white; }
        .btn-info     { background: #4299e1; color: white; }
        .btn-warning  { background: #ed8936; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .status { margin-left: auto; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; }
        .status.loading { background: #e2e8f0; color: #4a5568; }
        .status.ready   { background: #c6f6d5; color: #22543d; }
        .status.error   { background: #fed7d7; color: #742a2a; }
        .pdf-viewer { height: calc(100vh - 60px); background: #525252; display: flex; flex-direction: column; align-items: center; overflow-y: auto; padding: 20px; }
        #pdfCanvas { max-width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); margin-bottom: 20px; }
        .page-controls { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: white; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); display: none; align-items: center; gap: 15px; }
        .page-controls.active { display: flex; }
        .page-btn { background: #667eea; color: white; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
        .page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .page-info { font-weight: 500; color: #2d3748; }
        .loading-screen, .error-screen { display: flex; justify-content: center; align-items: center; height: calc(100vh - 60px); flex-direction: column; gap: 20px; }
        .spinner { width: 50px; height: 50px; border: 4px solid #e2e8f0; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
        .error-icon    { font-size: 64px; }
        .error-message { font-size: 16px; color: #e53e3e; max-width: 600px; text-align: center; padding: 20px; }
        .error-details { background: #f7fafc; padding: 15px; border-radius: 4px; margin-top: 10px; font-size: 12px; font-family: monospace; text-align: left; max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>

<div class="toolbar">
    <span class="toolbar-title">লিভ রিপোর্ট</span>
    <button class="btn btn-primary"  onclick="loadPDF()"     id="btnReload">🔄 Reload</button>
    <button class="btn btn-success"  onclick="downloadPDF()" id="btnDownload" disabled>💾 Download</button>
    <button class="btn btn-info"     onclick="printPDF()"    id="btnPrint"    disabled>🖨️ Print</button>
    <button class="btn btn-warning"  onclick="zoomIn()">🔍+ Zoom</button>
    <button class="btn btn-warning"  onclick="zoomOut()">🔍- Zoom</button>
    <div class="status loading" id="status">Loading...</div>
</div>

<div id="loadingScreen" class="loading-screen">
    <div class="spinner"></div>
    <div>Generating PDF...</div>
</div>

<div id="errorScreen" class="error-screen" style="display:none">
    <div class="error-icon">⚠️</div>
    <div class="error-message" id="errorMessage"></div>
    <div class="error-details"  id="errorDetails"  style="display:none"></div>
    <button class="btn btn-primary" onclick="loadPDF()">Try Again</button>
</div>

<div id="pdfViewer" class="pdf-viewer" style="display:none">
    <canvas id="pdfCanvas"></canvas>
</div>

<div class="page-controls" id="pageControls">
    <button class="page-btn" onclick="prevPage()" id="btnPrev">‹</button>
    <span class="page-info">Page <span id="pageNum">1</span> of <span id="pageCount">1</span></span>
    <button class="page-btn" onclick="nextPage()" id="btnNext">›</button>
</div>

<script>
    const generateUrl = '<?= $generateUrl ?>';
    let pdfDoc = null, pageNum = 1, pageRendering = false, pageNumPending = null, scale = 1.5;
    let pdfDataBlob = null, downloadFilename = 'leave_report.pdf';

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    window.addEventListener('DOMContentLoaded', () => loadPDF());

    function setStatus(text, type = 'loading') { const el = document.getElementById('status'); el.textContent = text; el.className = 'status ' + type; }
    function disableButtons(d) { document.getElementById('btnDownload').disabled = d; document.getElementById('btnPrint').disabled = d; }

    function showLoading() {
        document.getElementById('loadingScreen').style.display = 'flex';
        document.getElementById('errorScreen').style.display   = 'none';
        document.getElementById('pdfViewer').style.display     = 'none';
        document.getElementById('pageControls').classList.remove('active');
        disableButtons(true);
    }
    function showError(msg, details) {
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('errorScreen').style.display   = 'flex';
        document.getElementById('pdfViewer').style.display     = 'none';
        document.getElementById('errorMessage').textContent    = msg;
        if (details) { document.getElementById('errorDetails').textContent = details; document.getElementById('errorDetails').style.display = 'block'; }
        setStatus('Error', 'error'); disableButtons(true);
    }
    function showPDF() {
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('errorScreen').style.display   = 'none';
        document.getElementById('pdfViewer').style.display     = 'flex';
        document.getElementById('pageControls').classList.add('active');
        disableButtons(false);
    }

    function loadPDF() {
        showLoading(); setStatus('Generating...', 'loading');
        fetch(generateUrl).then(r => r.json()).then(data => {
            if (data.success) {
                if (data.filename) downloadFilename = data.filename;
                setStatus('✓ Ready', 'ready');
                const binary = atob(data.pdfData);
                const bytes  = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
                pdfDataBlob = new Blob([bytes], { type: 'application/pdf' });
                loadPDFDocument(bytes);
            } else { showError(data.error || 'PDF generation failed', data.trace || null); }
        }).catch(e => showError('Failed to load: ' + e.message));
    }

    function loadPDFDocument(pdfData) {
        pdfjsLib.getDocument(pdfData).promise.then(pdf => {
            pdfDoc = pdf;
            document.getElementById('pageCount').textContent = pdf.numPages;
            renderPage(pageNum); showPDF();
        }).catch(e => showError('PDF render error: ' + e.message));
    }

    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(page => {
            const canvas = document.getElementById('pdfCanvas'), ctx = canvas.getContext('2d');
            const vp = page.getViewport({ scale: scale });
            canvas.height = vp.height; canvas.width = vp.width;
            page.render({ canvasContext: ctx, viewport: vp }).promise.then(() => {
                pageRendering = false;
                if (pageNumPending !== null) { renderPage(pageNumPending); pageNumPending = null; }
            });
        });
        document.getElementById('pageNum').textContent  = num;
        document.getElementById('btnPrev').disabled = num <= 1;
        document.getElementById('btnNext').disabled = num >= (pdfDoc ? pdfDoc.numPages : 1);
    }

    function queueRenderPage(num) { pageRendering ? (pageNumPending = num) : renderPage(num); }
    function prevPage() { if (pageNum > 1) { pageNum--; queueRenderPage(pageNum); } }
    function nextPage() { if (pdfDoc && pageNum < pdfDoc.numPages) { pageNum++; queueRenderPage(pageNum); } }
    function zoomIn()  { scale += 0.25; if (pdfDoc) renderPage(pageNum); }
    function zoomOut() { if (scale > 0.5) { scale -= 0.25; if (pdfDoc) renderPage(pageNum); } }

    function downloadPDF() {
        if (!pdfDataBlob) return;
        const url = URL.createObjectURL(pdfDataBlob), a = document.createElement('a');
        a.href = url; a.download = downloadFilename; a.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    function printPDF() {
        if (!pdfDataBlob) return;
        const url = URL.createObjectURL(pdfDataBlob);
        window.open(url, '_blank');
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
</script>
</body>
</html>
<?php
}
