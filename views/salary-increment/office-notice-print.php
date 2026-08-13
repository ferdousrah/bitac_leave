<?php
require_once(__DIR__ . '/../../connection.php');

$employeeID = intval($_POST['employeeID'] ?? 0);
$incrementYear = intval($_POST['financialYear'] ?? date('Y'));

// Get employee details
$getEmployeeDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . $employeeID . "'");
$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($getEmployeeDetailsQRW['designation'] ?? 0) . "'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($getEmployeeDetailsQRW['section_id'] ?? 0) . "'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getPayScaleDetailsQ = mysqli_query($con, "SELECT * FROM grade WHERE id='" . intval($getEmployeeDetailsQRW['pay_scale'] ?? 0) . "'");
$getPayScaleDetailsQRW = mysqli_fetch_assoc($getPayScaleDetailsQ);

// Get salary increment data
$pay_scale = $getEmployeeDetailsQRW['pay_scale'] ?? '';

$getSalaryIncrementDataQ = mysqli_query($con, "SELECT * FROM yearly_salary_increment WHERE incrementYear='" . $incrementYear . "' AND employeeID='" . $employeeID . "'");
$getSalaryIncrementDataQRW = mysqli_fetch_assoc($getSalaryIncrementDataQ);

$currentBasic = floatval($getSalaryIncrementDataQRW['presentSalary'] ?? 0);

$getSectionDetailsQ2 = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($getSalaryIncrementDataQRW['section_id'] ?? 0) . "'");
$getSectionDetailsQRW2 = mysqli_fetch_assoc($getSectionDetailsQ2);

$getorgDetailsQ = mysqli_query($con, "SELECT * FROM organization WHERE id='" . intval($getSalaryIncrementDataQRW['organization_id'] ?? 0) . "'");
$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

// Get new pay scale
$getNewPayscaleQ = mysqli_query($con, "SELECT * FROM grade WHERE id='" . intval($getSalaryIncrementDataQRW['incrementSalaryGrade'] ?? 0) . "'");
$getNewPayscaleQRW = mysqli_fetch_assoc($getNewPayscaleQ);

// Get increment settings
$getIncrementSettings = mysqli_query($con, "SELECT * FROM increment_settings WHERE dataID=1");
$getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

$incrementDateArray = explode('-', $getIncrementSettingsRW['salary_increment_date'] ?? '');
$salaryIncrementDate = (isset($incrementDateArray[2]) ? $incrementDateArray[2] : '') . '/' . (isset($incrementDateArray[1]) ? $incrementDateArray[1] : '') . '/' . (isset($incrementDateArray[0]) ? $incrementDateArray[0] : '');

// Get copy recipients for this employee
$getCopyToQ = mysqli_query($con, "SELECT * FROM salary_notice_copy WHERE refFor='" . $employeeID . "' ORDER BY serial ASC");

// Get signatory 1
$getDataOneQ = mysqli_query($con, "SELECT * FROM user_list WHERE dataID=96");
$getDataOneQRW = mysqli_fetch_assoc($getDataOneQ);

$getfirstSignatoryDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . intval($getDataOneQRW['employee_id'] ?? 0) . "'");
$getfirstSignatoryDetailsQRW = mysqli_fetch_assoc($getfirstSignatoryDetailsQ);

$getDesignationDetailsForFirstQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($getfirstSignatoryDetailsQRW['designation'] ?? 0) . "'");
$getDesignationDetailsForFirstQRW = mysqli_fetch_assoc($getDesignationDetailsForFirstQ);

$getSectionDetailsForFirstQ = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($getfirstSignatoryDetailsQRW['section_id'] ?? 0) . "'");
$getSectionDetailsForFirstQRW = mysqli_fetch_assoc($getSectionDetailsForFirstQ);

$getorgDetailsForFirstQ = mysqli_query($con, "SELECT * FROM organization WHERE id='" . intval($getfirstSignatoryDetailsQRW['organization_id'] ?? 0) . "'");
$getorgDetailsForFirstQRW = mysqli_fetch_assoc($getorgDetailsForFirstQ);

// Get signatory 2
$getDataOneQ2 = mysqli_query($con, "SELECT * FROM user_list WHERE dataID=88");
$getDataOneQ2RW = mysqli_fetch_assoc($getDataOneQ2);

$getsecondSignatoryDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . intval($getDataOneQ2RW['employee_id'] ?? 0) . "'");
$getsecondSignatoryDetailsQRW = mysqli_fetch_assoc($getsecondSignatoryDetailsQ);

$getDesignationDetailsForSecondQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($getsecondSignatoryDetailsQRW['designation'] ?? 0) . "'");
$getDesignationDetailsForSecondQRW = mysqli_fetch_assoc($getDesignationDetailsForSecondQ);

$getSectionDetailsForSecondQ = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($getsecondSignatoryDetailsQRW['section_id'] ?? 0) . "'");
$getSectionDetailsForSecondQRW = mysqli_fetch_assoc($getSectionDetailsForSecondQ);

$getorgDetailsForSecondQ = mysqli_query($con, "SELECT * FROM organization WHERE id='" . intval($getsecondSignatoryDetailsQRW['organization_id'] ?? 0) . "'");
$getorgDetailsForSecondQRW = mysqli_fetch_assoc($getorgDetailsForSecondQ);

// Get signatory 3
$getDataOneQ3 = mysqli_query($con, "SELECT * FROM user_list WHERE dataID=91");
$getDataOneQ3RW = mysqli_fetch_assoc($getDataOneQ3);

$getthirdSignatoryDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . intval($getDataOneQ3RW['employee_id'] ?? 0) . "'");
$getthirdSignatoryDetailsQRW = mysqli_fetch_assoc($getthirdSignatoryDetailsQ);

$getDesignationDetailsForThirdQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($getthirdSignatoryDetailsQRW['designation'] ?? 0) . "'");
$getDesignationDetailsForThirdQRW = mysqli_fetch_assoc($getDesignationDetailsForThirdQ);

$getSectionDetailsForThirdQ = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($getthirdSignatoryDetailsQRW['section_id'] ?? 0) . "'");
$getSectionDetailsForThirdQRW = mysqli_fetch_assoc($getSectionDetailsForThirdQ);

$getorgDetailsForThirdQ = mysqli_query($con, "SELECT * FROM organization WHERE id='" . intval($getthirdSignatoryDetailsQRW['organization_id'] ?? 0) . "'");
$getorgDetailsForThirdQRW = mysqli_fetch_assoc($getorgDetailsForThirdQ);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>অফিস আদেশ</title>
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; }
        .no-print { }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<p style="text-align:right">
    <button class="print-link no-print" onclick="jQuery('#ele1').print()">Print</button>
</p>

<div id="ele1" style="padding:0px; margin:0; width:100%;">
    <p style="text-align:center;font-size:18px;padding:0px;font-weight: bold;">
        বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)<br>
        ১১৬/খ, তেজগাঁও শিল্প এলাকা<br>
        ঢাকা-১২০৮ ।<br><br>
        <span style="text-decoration: underline;font-size:16px;">অফিস আদেশ</span>
    </p>

    <p>&nbsp;</p>

    <table width="100%">
        <tr>
            <td width="50%"><p style="font-size: 15px;">নং- <?php echo htmlspecialchars($getSalaryIncrementDataQRW['fileNo'] ?? ''); ?></p></td>
            <td width="50%"><p style="font-size: 15px;" align="right">তারিখঃ <?php if (!empty($getSalaryIncrementDataQRW['salary_increment_date'])) { echo $obj->engToBn(convertDateTotrad($getSalaryIncrementDataQRW['salary_increment_date'])); } ?> খ্রিস্টাব্দ</p></td>
        </tr>
    </table>

    <p>&nbsp;</p>

    <p style="font-size: 15px;text-align: justify;">
        &nbsp;&nbsp;&nbsp;<?php echo htmlspecialchars($getEmployeeDetailsQRW['employee_name'] ?? ''); ?>, <?php echo htmlspecialchars($getDesignationDetailsQRW['job_title_name'] ?? ''); ?>, <?php echo htmlspecialchars($getSectionDetailsQRW2['section_name'] ?? ''); ?>, <?php echo htmlspecialchars($getorgDetailsQRW['organization_name'] ?? ''); ?>কে  অর্থ মন্ত্রণালয়, <?php echo htmlspecialchars($getIncrementSettingsRW['notice_content'] ?? ''); ?> <?php echo $obj->engToBn(number_format($getNewPayscaleQRW['minimum_salary'] ?? 0, 2)) . '-' . $obj->engToBn(number_format($getNewPayscaleQRW['maximum_salary'] ?? 0, 2)); ?>/= টাকার বেতন স্কেলে <?php echo $obj->engToBn(convertDateTotrad($getSalaryIncrementDataQRW['salary_increment_date'] ?? '')); ?> তারিখে বাৎসরিক বেতন বৃদ্ধির পর তার মূলবেতন <?php echo $obj->engToBn(number_format($currentBasic, 2)); ?>/= হতে <?php echo $obj->engToBn(number_format($getSalaryIncrementDataQRW['incrementSalary'] ?? 0, 2)); ?>/= টাকায় উন্নীত হলো।
    </p>

    <p>&nbsp;</p>

    <p style="font-size: 15px;">০২। কর্তৃপক্ষের অনুমোদনক্রমে এ আদেশ জারী করা হলো। </p>
    <p>&nbsp;</p>

    <table width="100%" border="0">
        <tr>
            <td width="40%">
                <p style="font-size: 15px;">
                    <?php echo htmlspecialchars($getEmployeeDetailsQRW['employee_name'] ?? ''); ?><br>
                    <?php echo htmlspecialchars($getDesignationDetailsQRW['job_title_name'] ?? ''); ?><br>
                    <?php echo htmlspecialchars($getSectionDetailsQRW2['section_name'] ?? ''); ?>, <?php echo htmlspecialchars($getorgDetailsQRW['organization_name'] ?? ''); ?>
                </p>
            </td>
            <td width="20%">&nbsp;</td>
            <td align="right">
                <p style="font-size: 15px;">
                    <span><img src="data:image/jpg;charset=utf8;base64,<?php echo base64_encode($getDataOneQ2RW['signature'] ?? ''); ?>" height="40" /></span><br>
                    <?php echo str_replace("জনাব ", "", $getsecondSignatoryDetailsQRW['employee_name'] ?? ''); ?><br>
                    <?php echo htmlspecialchars($getDesignationDetailsForSecondQRW['job_title_name'] ?? ''); ?><br>
                    <?php echo htmlspecialchars($getSectionDetailsForFirstQRW['section_name'] ?? ''); ?>, <?php echo htmlspecialchars($getorgDetailsForSecondQRW['organization_name'] ?? ''); ?>
                </p>
            </td>
        </tr>
    </table>

    <p style="font-size: 15px;font-weight: bold;">অনুলিপি :</p>

    <?php
    // Fixed-text recipients from কনফিগারেশন → ডিফল্ট অনুলিপি (বার্ষিক বেতন বৃদ্ধি),
    // numbered ahead of the per-employee entries below.
    require_once(__DIR__ . '/../../includes/default-notice-copies.php');
    $copySL = 1;
    foreach (default_notice_labels($con, 'increment', $getorgDetailsQRW['organization_name'] ?? '') as $__lbl) {
        echo '<p style="font-size: 15px;">' . $obj->engToBn($copySL) . '। ' . htmlspecialchars($__lbl) . '</p>';
        $copySL++;
    }
    while ($copyRow = mysqli_fetch_array($getCopyToQ)) {
        $getCopyToDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . intval($copyRow['employeeID']) . "'");
        $getCopyToDetailsQRW = mysqli_fetch_assoc($getCopyToDetailsQ);

        $getDesigDetailsQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($getCopyToDetailsQRW['designation'] ?? 0) . "'");
        $getDesigDetailsQRW = mysqli_fetch_assoc($getDesigDetailsQ);

        $getSDetailsQ = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($getCopyToDetailsQRW['section_id'] ?? 0) . "'");
        $getSDetailsQRW = mysqli_fetch_assoc($getSDetailsQ);

        $getOrgDetailsDesigQ = mysqli_query($con, "SELECT * FROM organization WHERE id='" . intval($getCopyToDetailsQRW['organization_id'] ?? 0) . "'");
        $getOrgDetailsDesigQRW = mysqli_fetch_assoc($getOrgDetailsDesigQ);
    ?>
    <p style="font-size: 15px;"><?php echo $obj->engToBn($copySL); ?>। <?php echo htmlspecialchars($getCopyToDetailsQRW['employee_name'] ?? ''); ?>, <?php echo htmlspecialchars($getDesigDetailsQRW['job_title_name'] ?? ''); ?>, <?php echo htmlspecialchars($getSDetailsQRW['section_name'] ?? ''); ?>, <?php echo htmlspecialchars($getOrgDetailsDesigQRW['organization_name'] ?? ''); ?></p>
    <?php $copySL++; } ?>
</div>

<script src="../../app-assets/vendors/js/core/jquery-3.2.1.min.js" type="text/javascript"></script>
<script src="../../jQuery.print.js"></script>

<script type='text/javascript'>
jQuery(function($) {
    'use strict';
    try {
        var original = document.getElementById('canvasExample');
        if (original) {
            original.getContext('2d').fillRect(20, 20, 120, 120);
        }
    } catch (err) {
        console.warn(err);
    }
});
</script>

</body>
</html>
