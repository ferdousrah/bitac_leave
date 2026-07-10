<?php
require_once(__DIR__ . '/../../connection.php');

$sectionID = intval($_POST['sectionID'] ?? 0);
$incrementYear = intval($_POST['financialYear'] ?? date('Y'));

// Determine section string for document
if ($sectionID == 0) {
    $sectionSTR = "বিটাক";
} else if ($sectionID == 36 || $sectionID == 38 || $sectionID == 39 || $sectionID == 40 || $sectionID == 41) {
    $getSalaryGInfoQ = mysqli_query($con, "SELECT * FROM salary_group WHERE id='" . $sectionID . "'");
    $getSalaryGInfoQRW = mysqli_fetch_assoc($getSalaryGInfoQ);
    $sectionSTR = $getSalaryGInfoQRW['sub_head'] ?? 'বিটাক';
} else {
    $sectionSTR = "বিটাক, ঢাকা";
}

// Get employees based on section selection
if ($sectionID == 0) {
    $getAllEmployeeListQ = mysqli_query($con, "SELECT employee_list.employee_name, employee_list.organization_id, employee_list.section_id, employee_list.employee_id, employee_list.designation, yearly_salary_increment.incrementYear, yearly_salary_increment.employeeID, yearly_salary_increment.presentSalaryGrade, yearly_salary_increment.presentSalary, yearly_salary_increment.incrementSalaryGrade, yearly_salary_increment.incrementAmount, yearly_salary_increment.incrementSalary, yearly_salary_increment.status FROM employee_list INNER JOIN yearly_salary_increment ON employee_list.id=yearly_salary_increment.employeeID WHERE yearly_salary_increment.incrementYear='" . $incrementYear . "' AND yearly_salary_increment.status=1 ORDER BY employee_list.display_order ASC");
} else {
    $getAllEmployeeListQ = mysqli_query($con, "SELECT employee_list.employee_name, employee_list.organization_id, employee_list.section_id, employee_list.employee_id, employee_list.designation, yearly_salary_increment.incrementYear, yearly_salary_increment.employeeID, yearly_salary_increment.presentSalaryGrade, yearly_salary_increment.presentSalary, yearly_salary_increment.incrementSalaryGrade, yearly_salary_increment.incrementAmount, yearly_salary_increment.incrementSalary, yearly_salary_increment.status FROM employee_list INNER JOIN yearly_salary_increment ON employee_list.id=yearly_salary_increment.employeeID WHERE yearly_salary_increment.incrementYear='" . $incrementYear . "' AND yearly_salary_increment.status=1 AND employee_list.salary_group_id='" . $sectionID . "' ORDER BY employee_list.display_order ASC");
}

// Get increment settings
$getIncrementSettings = mysqli_query($con, "SELECT * FROM increment_settings WHERE dataID=1");
$getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

$incrementDateArray = explode('-', $getIncrementSettingsRW['salary_increment_date'] ?? '');
$salaryIncrementDate = (isset($incrementDateArray[2]) ? $incrementDateArray[2] : '') . '/' . (isset($incrementDateArray[1]) ? $incrementDateArray[1] : '') . '/' . (isset($incrementDateArray[0]) ? $incrementDateArray[0] : '');

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

// Get copy recipients
$getCopyToQ = mysqli_query($con, "SELECT * FROM salary_notice_copy WHERE refFor=0 ORDER BY serial ASC");

// Get increment data for file number and date
$getIncrementSettDataQ = mysqli_query($con, "SELECT DISTINCT salary_increment_date, fileNo FROM yearly_salary_increment WHERE incrementYear='" . $incrementYear . "'");
$getIncrementSettDataQRW = mysqli_fetch_assoc($getIncrementSettDataQ);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>অফিস আদেশ (সকল)</title>
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
            <td width="50%"><p style="font-size: 15px;">নং- <?php echo htmlspecialchars($getIncrementSettDataQRW['fileNo'] ?? ''); ?></p></td>
            <td width="50%"><p style="font-size: 15px;" align="right">তারিখঃ <?php echo $obj->engToBn($getIncrementSettDataQRW['salary_increment_date'] ?? ''); ?> খ্রিস্টাব্দ</p></td>
        </tr>
    </table>

    <p style="font-size: 15px;text-align: justify;">
        &nbsp;&nbsp;&nbsp;<?php echo $sectionSTR; ?> এর নিম্নোক্ত কর্মচারীগণকে অর্থ মন্ত্রণালয়, অর্থ বিভাগের বাস্তবায়ন অনুবিভাগের ১৫-১২-২০১৫ খ্রিষ্ট: তারিখের এস.আর.ও.নং-৩৭০-আইন/২০১৫ । Service(Recognition and Conditions) Act. 1975(Act No. XXXII of 1975) এর Section-5-এ প্রদত্ত আদেশের অনুচ্ছেদ ১০ অনুসারে <?php echo $obj->engToBn(convertDateTotrad($getIncrementSettDataQRW['salary_increment_date'] ?? '')); ?> সাল থেকে নিম্নোক্ত ছক মোতাবেক বেতন বৃদ্ধি করা হলো ।
    </p>

    <br>

    <table width="100%" border="1" style="border-collapse: collapse; border: 1px solid #000;font-size: 12px;" cellpadding="8" cellspacing="0">
        <tr>
            <th width="30" style="border-bottom: 1px solid #000;border-right: 1px solid #000;">ক্র: নং</th>
            <th width="220" style="border-bottom: 1px solid #000;border-right: 1px solid #000;">নাম ও পদবী</th>
            <th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">শাখা ও কেন্দ্র</th>
            <th style="border-bottom: 1px solid #000;border-right: 1px solid #000;" width="40">আইডি</th>
            <th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">বর্তমান বেতন স্কেল</th>
            <th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">বর্তমান মূল বেতন</th>
            <th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">বেতন বৃদ্ধির হার</th>
            <th style="border-bottom: 1px solid #000;">বেতন বৃদ্ধির পর মূল বেতন</th>
        </tr>

        <?php
        $sl = 1;
        while ($empRow = mysqli_fetch_array($getAllEmployeeListQ)) {
            $getDesignationDetailsQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($empRow['designation']) . "'");
            $getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

            $getPresentSGradeQ = mysqli_query($con, "SELECT * FROM grade WHERE id='" . intval($empRow['presentSalaryGrade']) . "'");
            $getPresentSGradeQRW = mysqli_fetch_assoc($getPresentSGradeQ);

            $getSectionDetailsQ = mysqli_query($con, "SELECT * FROM sections WHERE id='" . intval($empRow['section_id']) . "'");
            $getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

            $getorgDetailsQ = mysqli_query($con, "SELECT * FROM organization WHERE id='" . intval($empRow['organization_id']) . "'");
            $getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);
        ?>
        <tr>
            <td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn($sl); ?></td>
            <td style="border-right: 1px solid #000;">
                <span style="padding: 0px;"><?php echo htmlspecialchars($empRow['employee_name']); ?><br><?php echo htmlspecialchars($getDesignationDetailsQRW['job_title_name'] ?? ''); ?></span>
            </td>
            <td style="border-right: 1px solid #000;text-align: center;">
                <?php echo htmlspecialchars($getSectionDetailsQRW['section_name'] ?? ''); ?><br>
                <?php echo htmlspecialchars($getorgDetailsQRW['organization_name'] ?? ''); ?>
            </td>
            <td style="text-align: center;border-right: 1px solid #000;"><?php echo htmlspecialchars($empRow['employee_id']); ?></td>
            <td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn($getPresentSGradeQRW['minimum_salary'] ?? 0) . '-' . $obj->engToBn($getPresentSGradeQRW['maximum_salary'] ?? 0); ?></td>
            <td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn(number_format($empRow['presentSalary'], 2)); ?></td>
            <td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn(number_format($empRow['incrementAmount'])); ?></td>
            <td style="text-align: center;"><?php echo $obj->engToBn(number_format($empRow['incrementSalary'])); ?></td>
        </tr>
        <?php $sl++; } ?>
    </table>

    <br>

    <p>০২। কর্তৃপক্ষের অনুমোদনক্রমে এ আদেশ জারী করা হলো ।</p>

    <p>&nbsp;</p>

    <table width="100%" border="0">
        <tr>
            <td width="40%"><p style="font-size: 15px;margin-left: 40px;">&nbsp;</p></td>
            <td width="20%"><p style="font-size: 15px;">&nbsp;</p></td>
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
    $copySL = 1;
    while ($copyRow = mysqli_fetch_array($getCopyToQ)) {
        $getCopyToDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . intval($copyRow['employeeID']) . "'");
        $getCopyToDetailsQRW = mysqli_fetch_assoc($getCopyToDetailsQ);

        $getDesigDetailsQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($getCopyToDetailsQRW['designation'] ?? 0) . "'");
        $getDesigDetailsQRW = mysqli_fetch_assoc($getDesigDetailsQ);

        $getorgDetailsForThirdQ = mysqli_query($con, "SELECT * FROM organization WHERE id='" . intval($getCopyToDetailsQRW['organization_id'] ?? 0) . "'");
        $getorgDetailsForThirdQRW = mysqli_fetch_assoc($getorgDetailsForThirdQ);
    ?>
    <p style="font-size: 15px;"><?php echo $obj->engToBn($copySL); ?>। <?php echo htmlspecialchars($getCopyToDetailsQRW['employee_name'] ?? ''); ?>, <?php echo htmlspecialchars($getDesigDetailsQRW['job_title_name'] ?? ''); ?>, <?php echo htmlspecialchars($getorgDetailsForSecondQRW['organization_name'] ?? ''); ?></p>
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
