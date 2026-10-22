<?php
include('connection.php');

$sectionID = $_POST['sectionID'];

if($sectionID == 0){

	$sectionSTR = "বিটাক";

}else if($sectionID == 36 || $sectionID == 38 || $sectionID == 39 || $sectionID == 40 || $sectionID == 41){

$getSalaryGInfoQ = mysqli_query($con,"select * from salary_group where id='$sectionID'");
$getSalaryGInfoQRW = mysqli_fetch_assoc($getSalaryGInfoQ);

$sectionSTR = $getSalaryGInfoQRW['sub_head'];

}else{

	$sectionSTR = "বিটাক, ঢাকা";


}



$incrementYear = $_POST['financialYear'];

if($sectionID == 0){

	$getAllEmployeeListQ = mysqli_query($con, "select employee_list.employee_name, employee_list.organization_id, employee_list.section_id, employee_list.employee_id, employee_list.designation, yearly_salary_increment.incrementYear, yearly_salary_increment.employeeID, yearly_salary_increment.presentSalaryGrade, yearly_salary_increment.presentSalary, yearly_salary_increment.incrementSalaryGrade, yearly_salary_increment.incrementAmount, yearly_salary_increment.incrementSalary, yearly_salary_increment.status from employee_list inner join yearly_salary_increment on employee_list.id=yearly_salary_increment.employeeID where yearly_salary_increment.incrementYear='$incrementYear' and yearly_salary_increment.status=1 order by employee_list.display_order asc");

}else{

	$getAllEmployeeListQ = mysqli_query($con, "select employee_list.employee_name, employee_list.organization_id, employee_list.section_id, employee_list.employee_id, employee_list.designation, yearly_salary_increment.incrementYear, yearly_salary_increment.employeeID, yearly_salary_increment.presentSalaryGrade, yearly_salary_increment.presentSalary, yearly_salary_increment.incrementSalaryGrade, yearly_salary_increment.incrementAmount, yearly_salary_increment.incrementSalary, yearly_salary_increment.status from employee_list inner join yearly_salary_increment on employee_list.id=yearly_salary_increment.employeeID where yearly_salary_increment.incrementYear='$incrementYear' and yearly_salary_increment.status=1 and employee_list.salary_group_id='$sectionID' order by employee_list.display_order asc");

}


$getIncrementSettings = mysqli_query($con,"select * from increment_settings where dataID=1");
$getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

$incrementDateArray = explode('-', $getIncrementSettingsRW['salary_increment_date']);

$salaryIncrementDate = $incrementDateArray['2'].'/'.$incrementDateArray[1].'/'.$incrementDateArray[0];

$getCopyToQ = mysqli_query($con, "select * from salary_notice_copy");


// get signatory signature
	$getDataOneQ = mysqli_query($con, "select * from user_list where dataID=96");
	$getDataOneQRW = mysqli_fetch_assoc($getDataOneQ);

	$getfirstSignatoryDetailsQ = mysqli_query($con, "select * from employee_list where id='$getDataOneQRW[employee_id]'");
	$getfirstSignatoryDetailsQRW = mysqli_fetch_assoc($getfirstSignatoryDetailsQ);

	$getDesignationDetailsForFirstQ = mysqli_query($con, "select * from job_title where id='$getfirstSignatoryDetailsQRW[designation]'");
	$getDesignationDetailsForFirstQRW = mysqli_fetch_assoc($getDesignationDetailsForFirstQ);

	$getSectionDetailsForFirstQ = mysqli_query($con, "select * from sections where id='$getfirstSignatoryDetailsQRW[section_id]'");
	$getSectionDetailsForFirstQRW = mysqli_fetch_assoc($getSectionDetailsForFirstQ);

	$getorgDetailsForFirstQ = mysqli_query($con, "select * from organization where id='$getfirstSignatoryDetailsQRW[organization_id]'");
	$getorgDetailsForFirstQRW = mysqli_fetch_assoc($getorgDetailsForFirstQ);

	// end of first sig

	$getDataOneQ2 = mysqli_query($con, "select * from user_list where dataID=88");
	$getDataOneQ2RW = mysqli_fetch_assoc($getDataOneQ2);

	$getsecondSignatoryDetailsQ = mysqli_query($con, "select * from employee_list where id='$getDataOneQ2RW[employee_id]'");
	$getsecondSignatoryDetailsQRW = mysqli_fetch_assoc($getsecondSignatoryDetailsQ);

	$getDesignationDetailsForSecondQ = mysqli_query($con, "select * from job_title where id='$getsecondSignatoryDetailsQRW[designation]'");
	$getDesignationDetailsForSecondQRW = mysqli_fetch_assoc($getDesignationDetailsForSecondQ);

	$getSectionDetailsForSecondQ = mysqli_query($con, "select * from sections where id='$getsecondSignatoryDetailsQRW[section_id]'");
	$getSectionDetailsForSecondQRW = mysqli_fetch_assoc($getSectionDetailsForSecondQ);

	$getorgDetailsForSecondQ = mysqli_query($con, "select * from organization where id='$getsecondSignatoryDetailsQRW[organization_id]'");
	$getorgDetailsForSecondQRW = mysqli_fetch_assoc($getorgDetailsForSecondQ);

	// end of second



	$getDataOneQ3 = mysqli_query($con, "select * from user_list where dataID=162");
	$getDataOneQ3RW = mysqli_fetch_assoc($getDataOneQ3);


	$getthirdSignatoryDetailsQ = mysqli_query($con, "select * from employee_list where id='$getDataOneQ3RW[employee_id]'");
	$getthirdSignatoryDetailsQRW = mysqli_fetch_assoc($getthirdSignatoryDetailsQ);

	$getDesignationDetailsForThirdQ = mysqli_query($con, "select * from job_title where id='$getthirdSignatoryDetailsQRW[designation]'");
	$getDesignationDetailsForThirdQRW = mysqli_fetch_assoc($getDesignationDetailsForThirdQ);

	$getSectionDetailsForThirdQ = mysqli_query($con, "select * from sections where id='$getthirdSignatoryDetailsQRW[section_id]'");
	$getSectionDetailsForThirdQRW = mysqli_fetch_assoc($getSectionDetailsForThirdQ);

	$getorgDetailsForThirdQ = mysqli_query($con, "select * from organization where id='$getthirdSignatoryDetailsQRW[organization_id]'");
	$getorgDetailsForThirdQRW = mysqli_fetch_assoc($getorgDetailsForThirdQ);
	
	$getSalaryIncrementDateQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='$incrementYear' order by approvedDate desc limit 0,1");
	$getSalaryIncrementDateQRW = mysqli_fetch_assoc($getSalaryIncrementDateQ);

?>




<p style="text-align:right"><button class="print-link no-print" onclick="jQuery('#ele1').print()">
                Print
                </button></p>


<div id="ele1" style="padding:0px; margin:0; width:100%;">

								<p style="text-align:center;font-size:18px;padding:0px;font-weight: bold;">
								বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)<br>১১৬/খ, তেজগাঁও শিল্প এলাকা<br>ঢাকা-১২০৮ ।<br><br><span style="text-decoration: underline;font-size:16px;">ইনক্রিমেন্ট নোটে অনুমোদন</span>
								</p>



								<p>&nbsp;</p>

<table width="100%">
	<tr><td width="50%"><p style="font-size: 15px;"></p></td><td width="50%"><p style="font-size: 15px;" align="right">নোট অনুমোদনের তারিখ:  <?php if($getSalaryIncrementDateQRW['approvedDate'] != ''){ echo $obj->engToBn(convertDateTotrad($getSalaryIncrementDateQRW['approvedDate'])); } ?> খ্রিস্টাব্দ</p></td></tr>
</table>




<p style="font-size: 15px;text-align: justify;">&nbsp;&nbsp;&nbsp;  <?php echo $sectionSTR; ?> এর নিম্নোক্ত কর্মচারীগণকে   অর্থ মন্ত্রণালয়, অর্থ বিভাগের বাস্তবায়ন অনুবিভাগের ১৫-১২-২০১৫ খ্রিষ্ট: তারিখের এস.আর.ও.নং-৩৭০-আইন/২০১৫ । Service(Recognition and Conditions) Act. 1975(Act No. XXXII of 1975) এর Section-5-এ প্রদত্ত আদেশের অনুচ্ছেদ ১০ অনুসারে <?php echo convertDateTotrad($obj->engToBn($getIncrementSettingsRW['salary_increment_date'])); ?> সাল থেকে নিম্নোক্ত ছক মোতাবেক বেতন বৃদ্ধি করা যেতে পারে । উল্লেখ্য যে, নিম্নোক্ত কর্মচারীগণ বেতন বৃদ্ধির আলোচ্য বছরে  কোন অবৈতনিক ছুটি ভোগ করেননি। তাদের নামে কোন বিভাগীয় মামলা/ ইনক্রিমেন্ট এর স্থগিতাদেশ/ বেতন গ্রেড অবনমিতকরণের কোন আদেশ নেই । সদয় অনুমোদনের জন্য পেশ করা হলো ।</p>

<br>

<table width="100%" border="1" style="border-collapse: collapse; border: 1px solid #000;font-size: 12px;" cellpadding="8" cellspacing="0">

<tr><th width="40" style="border-bottom: 1px solid #000;border-right: 1px solid #000;">ক্রমিক</th><th width="200" style="border-bottom: 1px solid #000;border-right: 1px solid #000;">নাম ও পদবী</th><th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">শাখা ও কেন্দ্র</th><th style="border-bottom: 1px solid #000;border-right: 1px solid #000;" width="40">আইডি</th><th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">বর্তমান বেতন স্কেল</th><th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">বর্তমান মূল বেতন </th><th style="border-bottom: 1px solid #000;border-right: 1px solid #000;">বেতন বৃদ্ধির হার</th><th style="border-bottom: 1px solid #000;">বেতন বৃদ্ধির পর মূল বেতন</th></tr>

<?php 
$sl = 1;
while($empRow = mysqli_fetch_array($getAllEmployeeListQ)){

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getPresentSGradeQ = mysqli_query($con, "select * from grade where id='$empRow[presentSalaryGrade]'");
$getPresentSGradeQRW = mysqli_fetch_assoc($getPresentSGradeQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$empRow[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$empRow[organization_id]'");
$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);



?>

<tr><td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn($sl); ?></td><td style="border-right: 1px solid #000;"><span style="padding: 0px;"><?php echo $empRow['employee_name']; ?><br><?php echo $getDesignationDetailsQRW['job_title_name']; ?></span></td><td style="border-right: 1px solid #000;text-align: center;"><?php echo $getSectionDetailsQRW['section_name']; ?><br><?php echo $getorgDetailsQRW['organization_name']; ?>

</td><td style="text-align: center;border-right: 1px solid #000;"><?php echo $empRow['employee_id']; ?></td>
<td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn($getPresentSGradeQRW['minimum_salary']).'-'.$obj->engToBn($getPresentSGradeQRW['maximum_salary']); ?></td>
<td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn(number_format($empRow['presentSalary'])); ?></td><td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn(number_format($empRow['incrementAmount'])); ?></td><td style="text-align: center;"><?php echo $obj->engToBn(number_format($empRow['incrementSalary'])); ?></td></tr>


<?php $sl++; } ?>

</table>

<br><br><br>

<table width="100%" border="0">
<tr><td width="40%"><p style="font-size: 15px;"><span><img src="data:image/jpg;charset=utf8;base64,<?php echo base64_encode($getDataOneQRW['signature']); ?>" height="40" /></span><br>(<?php echo str_replace("জনাব ", "", $getfirstSignatoryDetailsQRW['employee_name']); ?>)<br><?php echo $getDesignationDetailsForFirstQRW['job_title_name']; ?><br><?php echo $getSectionDetailsForFirstQRW['section_name']; ?>, <?php echo $getorgDetailsForFirstQRW['organization_name']; ?>	</p></td><td width="20%"><p style="font-size: 15px;"><span><img src="data:image/jpg;charset=utf8;base64,<?php echo base64_encode($getDataOneQ2RW['signature']); ?>" height="40" /></span><br>(<?php echo str_replace("জনাব ", "", $getsecondSignatoryDetailsQRW['employee_name']); ?>)<br><?php echo $getDesignationDetailsForSecondQRW['job_title_name']; ?><br><?php echo $getSectionDetailsForFirstQRW['section_name']; ?>, <?php echo $getorgDetailsForSecondQRW['organization_name']; ?></p></td><td align="right"><p style="font-size: 15px;">&nbsp;</p></td></tr>
</table>
							                               
<p>০২। নোটটি অনুমোদন করা হলো ।</p>

<p>&nbsp;</p>

<table width="100%" border="0">
<tr><td width="40%"><p style="font-size: 15px;margin-left: 40px;"><span><img src="data:image/jpg;charset=utf8;base64,<?php echo base64_encode($getDataOneQ3RW['signature']); ?>" height="40" /></span><br>(<?php echo str_replace("জনাব ", "", $getthirdSignatoryDetailsQRW['employee_name']); ?>)<br><?php echo $getDesignationDetailsForThirdQRW['job_title_name']; ?><br><?php echo $getSectionDetailsForThirdQRW['section_name']; ?>, <?php echo $getorgDetailsForThirdQRW['organization_name']; ?></p></td><td width="20%"><p style="font-size: 15px;">&nbsp;</p></td><td align="right"><p style="font-size: 15px;">&nbsp;</p></td></tr>
</table>


								


                           


				 </div>



				 <script src="./app-assets/vendors/js/core/jquery-3.2.1.min.js" type="text/javascript"></script>
<script src="./jQuery.print.js"></script>


<script type='text/javascript'>
        //<![CDATA[
        jQuery(function($) { 'use strict';
            try {
                var original = document.getElementById('canvasExample');
                original.getContext('2d').fillRect(20, 20, 120, 120);
            } catch (err) {
                console.warn(err)
            }
            $("#ele2").find('.print-link').on('click', function() {
                //Print ele2 with default options
                $.print("#ele2");
            });
            $("#ele4").find('button').on('click', function() {
                //Print ele4 with custom options
                $("#ele4").print({
                    //Use Global styles
                    globalStyles : false,
                    //Add link with attrbute media=print
                    mediaPrint : false,
                    //Custom stylesheet
                    stylesheet : "http://fonts.googleapis.com/css?family=Inconsolata",
                    //Print in a hidden iframe
                    iframe : false,
                    //Don't print this
                    noPrintSelector : ".avoid-this",
                    //Add this at top
                    prepend : "Hello World!!!<br/>",
                    //Add this on bottom
                    append : "<span><br/>Buh Bye!</span>",
                    //Log to console when printing is done via a deffered callback
                    deferred: $.Deferred().done(function() { console.log('Printing done', arguments); })
                });
            });
        });
        //]]>
        </script>