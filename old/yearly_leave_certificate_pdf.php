<?php
session_start();
include('connection.php');
include('library/number_converter.php');
include_once('function.php');
require_once __DIR__ . '/vendor/autoload.php';

// Get parameters
$employeeID = isset($_GET['employeeID']) ? intval($_GET['employeeID']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($employeeID <= 0) {
    die('Invalid Employee ID');
}

// Get leave summary from database
$getLeaveSummaryQ = mysqli_query($con, "SELECT * FROM yearly_leave_summary WHERE employeeID='$employeeID' AND year='$year'");

if (mysqli_num_rows($getLeaveSummaryQ) == 0) {
    die('এই কর্মচারীর জন্য ' . $year . ' সালের ছুটি সনদ তৈরি করা হয়নি।');
}

$leaveSummary = mysqli_fetch_assoc($getLeaveSummaryQ);

// Get employee details
$getEmployeeQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$employeeID'");
$employeeInfo = mysqli_fetch_assoc($getEmployeeQ);

// Get employee designation
$getDesignationQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='{$employeeInfo['designation']}'");
$designationInfo = mysqli_fetch_assoc($getDesignationQ);

// Get employee organization
$getOrgQ = mysqli_query($con, "SELECT * FROM organization WHERE id='{$employeeInfo['organization_id']}'");
$orgInfo = mysqli_fetch_assoc($getOrgQ);

// Get signatory details
$getSignatoryQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='{$leaveSummary['signatory']}'");
$signatoryInfo = mysqli_fetch_assoc($getSignatoryQ);

// Get signatory designation
$getSignatoryDesigQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='{$signatoryInfo['designation']}'");
$signatoryDesigInfo = mysqli_fetch_assoc($getSignatoryDesigQ);

// Get signatory organization
$getSignatoryOrgQ = mysqli_query($con, "SELECT * FROM organization WHERE id='{$signatoryInfo['organization_id']}'");
$signatoryOrgInfo = mysqli_fetch_assoc($getSignatoryOrgQ);

// Get signatory signature from user_list
$getSignatoryUserQ = mysqli_query($con, "SELECT signature FROM user_list WHERE employee_id='{$leaveSummary['signatory']}'");
$signatoryUserInfo = mysqli_fetch_assoc($getSignatoryUserQ);

// Get copy-to recipients
$getCopyToQ = mysqli_query($con, "SELECT * FROM leaveSummary_copy WHERE leaveSummaryID='{$leaveSummary['leaveSummaryID']}'");

// Convert days to years, months, days
function daysToYMD($totalDays) {
    $totalDays = max(0, $totalDays); // Ensure non-negative
    $years = floor($totalDays / 360);
    $months = floor(($totalDays - ($years * 360)) / 30);
    $days = round($totalDays - ($years * 360) - ($months * 30));
    return array('years' => $years, 'months' => $months, 'days' => $days);
}

// Calculate leave in years, months, days
$fullAvgLeave = daysToYMD($leaveSummary['fullHalfSalaryInDays']);
$halfAvgLeave = daysToYMD($leaveSummary['HalfSalaryInDays']);
$withoutPayLeave = daysToYMD($leaveSummary['withoutSalaryInDays']);

// Format certificate date
$certificateDate = date('d/m/Y', strtotime($leaveSummary['date']));

// Bengali month names
$months = [
    '01' => 'জানুয়ারি', '02' => 'ফেব্রুয়ারি', '03' => 'মার্চ', '04' => 'এপ্রিল',
    '05' => 'মে', '06' => 'জুন', '07' => 'জুলাই', '08' => 'আগস্ট', 
    '09' => 'সেপ্টেম্বর', '10' => 'অক্টোবর', '11' => 'নভেম্বর', '12' => 'ডিসেম্বর'
];

// Format date in Bengali
function formatBengaliDate($dateStr, $months, $obj) {
    $dateObj = new DateTime($dateStr);
    $day = $obj->engToBn($dateObj->format('d'));
    $month = $months[$dateObj->format('m')];
    $year = $obj->engToBn($dateObj->format('Y'));
    return $day . ' ' . $month . ' ' . $year;
}

$certificateDateBengali = formatBengaliDate($leaveSummary['date'], $months, $obj);

// Build copy-to HTML
$copyToHTML = '';
if (mysqli_num_rows($getCopyToQ) > 0) {
    $copyToHTML .= '<p class="bold-text">অনুলিপি (সদয় অবগতি ও প্রয়োজনীয় কার্যার্থে):</p>';
    $copySL = 1;
    while ($copyRow = mysqli_fetch_assoc($getCopyToQ)) {
        $getCopyEmpQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='{$copyRow['copyTo']}'");
        $copyEmpInfo = mysqli_fetch_assoc($getCopyEmpQ);
        
        $getCopyDesigQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='{$copyEmpInfo['designation']}'");
        $copyDesigInfo = mysqli_fetch_assoc($getCopyDesigQ);
        
        $getCopySectionQ = mysqli_query($con, "SELECT * FROM sections WHERE id='{$copyEmpInfo['section_id']}'");
        $copySectionInfo = mysqli_fetch_assoc($getCopySectionQ);
        
        $getCopyOrgQ = mysqli_query($con, "SELECT * FROM organization WHERE id='{$copyEmpInfo['organization_id']}'");
        $copyOrgInfo = mysqli_fetch_assoc($getCopyOrgQ);
        
        $copyToHTML .= '<p class="normal-text">' . $obj->engToBn($copySL) . '। ' . $copyEmpInfo['employee_name'] . ', ' . $copyDesigInfo['job_title_name'];
        if (!empty($copySectionInfo['section_name'])) {
            $copyToHTML .= ', ' . $copySectionInfo['section_name'];
        }
        $copyToHTML .= ', ' . $copyOrgInfo['organization_name'] . '</p>';
        $copySL++;
    }
}

// Signature HTML
$signatureHTML = '';
if (!empty($signatoryUserInfo['signature'])) {
    $signatureHTML = '<img src="data:image/png;base64,' . base64_encode($signatoryUserInfo['signature']) . '" height="60" /><br>';
    $signatureHTML .= '<span style="font-size: 11px;">' . $obj->engToBn(date('d.m.Y', strtotime($leaveSummary['date']))) . '</span><br>';
}

// Build PDF HTML
$html = '
<style>
    body {
        font-family: "Kalpurush", "SolaimanLipi", "Nikosh", sans-serif;
        font-size: 14px;
        line-height: 1.6;
    }
    .header-table {
        width: 100%;
    }
    .center-text {
        text-align: center;
    }
    .justify-text {
        text-align: justify;
    }
    .title-text {
        font-family: "Kalpurush", "SolaimanLipi", "Nikosh", sans-serif;
        font-size: 18px;
        text-align: center;
        text-decoration: underline;
        color: blue;
    }
    .normal-text {
        font-family: "Kalpurush", "SolaimanLipi", "Nikosh", sans-serif;
        font-size: 15px;
    }
    .bold-text {
        font-family: "Kalpurush", "SolaimanLipi", "Nikosh", sans-serif;
        font-size: 15px;
        font-weight: bold;
    }
    table td {
        font-family: "Kalpurush", "SolaimanLipi", "Nikosh", sans-serif;
        vertical-align: top;
    }
</style>

<table class="header-table">
    <tr>
        <td width="100" valign="middle"><img src="uploads/bdgov.png" width="80" /></td>
        <td class="center-text">
            <span style="font-size: 20px;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</span><br>
            <span style="font-size: 14px;">শিল্প মন্ত্রণালয়</span><br>
            <span style="font-size: 14px;">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</span><br>
            <span style="font-size: 14px;">১১৬/খ, তেজগাঁও শিল্প এলাকা, ঢাকা-১২০৮।</span>
        </td>
        <td width="100" valign="middle" align="right"><img src="uploads/bitac-logo-inner.png" width="80" /></td>
    </tr>
</table>

<br><br>

<table width="100%">
    <tr>
        <td width="50%"><p class="normal-text">স্মারক নং- ' . $leaveSummary['memorial_number'] . '</p></td>
        <td width="50%" align="right"><p class="normal-text">তারিখঃ ' . $obj->engToBn($certificateDate) . ' খ্রিস্টাব্দ</p></td>
    </tr>
</table>

<br><br>

<p class="title-text">বার্ষিক ছুটি সনদ - ' . $obj->engToBn($year) . '</p>

<br><br>

<p class="normal-text justify-text">&nbsp;&nbsp;&nbsp;এতদ্দ্বারা আপনি ' . $employeeInfo['employee_name'] . ', ' . $designationInfo['job_title_name'] . ', ' . $orgInfo['organization_name'] . ' কে জানানো যাচ্ছে যে, ' . $certificateDateBengali . ' তারিখ পর্যন্ত আপনার ছুটির বিবরণ নিম্নরূপঃ</p>

<br>

<table width="100%" align="center" border="0">
    <tr>
        <td width="20%">&nbsp;</td>
        <td width="60%">
            <p class="normal-text">ক) পূর্ণ গড় বেতনে জমা ছুটি : ' . $obj->engToBn($fullAvgLeave['years']) . ' বছর, ' . $obj->engToBn($fullAvgLeave['months']) . ' মাস, ' . $obj->engToBn($fullAvgLeave['days']) . ' দিন</p>
            <p class="normal-text">খ) অর্ধ গড় বেতনে জমা ছুটি : ' . $obj->engToBn($halfAvgLeave['years']) . ' বছর, ' . $obj->engToBn($halfAvgLeave['months']) . ' মাস, ' . $obj->engToBn($halfAvgLeave['days']) . ' দিন</p>
            <p class="normal-text">গ) অসাধারণ (বিনা বেতনে) ছুটি : ' . $obj->engToBn($withoutPayLeave['years']) . ' বছর, ' . $obj->engToBn($withoutPayLeave['months']) . ' মাস, ' . $obj->engToBn($withoutPayLeave['days']) . ' দিন</p>
        </td>
        <td width="20%">&nbsp;</td>
    </tr>
</table>

<br>

<p class="normal-text">০২। কর্তৃপক্ষের অনুমোদনক্রমে এ বার্ষিক ছুটি সনদ প্রদান করা হলো।</p>

<br><br><br>

<table width="100%" border="0">
    <tr>
        <td width="40%" valign="top">
            <p class="normal-text">
                ' . $employeeInfo['employee_name'] . '<br>
                ' . $designationInfo['job_title_name'] . '<br>
                ' . $orgInfo['organization_name'] . '
            </p>
        </td>
        <td width="20%">&nbsp;</td>
        <td width="40%" align="right" valign="top">
            <p class="normal-text">
                ' . $signatureHTML . '
                ' . str_replace("জনাব ", "", $signatoryInfo['employee_name']) . '<br>
                ' . $signatoryDesigInfo['job_title_name'] . '<br>
                ' . $signatoryOrgInfo['organization_name'] . '
            </p>
        </td>
    </tr>
</table>

<br><br>

' . $copyToHTML . '
';

// Generate PDF using mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 20,
    'margin_right' => 20,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'default_font' => 'kalpurush'
]);

$mpdf->autoScriptToLang = true;
$mpdf->autoLangToFont = true;

$mpdf->WriteHTML($html);

// Output PDF
$mpdf->Output('Leave_Certificate_' . $employeeInfo['employee_id'] . '_' . $year . '.pdf', 'I');

mysqli_close($con);
?>