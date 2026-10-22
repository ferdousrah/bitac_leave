<?php
include('connection.php');

$employeeID = $_POST['employeeID'];

$incrementYear = $_POST['financialYear'];

$getEmployeeDetailsQ = mysqli_query($con,"select * from `employee_list` where id='$employeeID'");
$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQRW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQRW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQRW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getPayScaleDetailsQ = mysqli_query($con, "select * from grade where id='$getEmployeeDetailsQRW[pay_scale]'");
$getPayScaleDetailsQRW = mysqli_fetch_assoc($getPayScaleDetailsQ);



// new salary calculation

	$currentBasic = $getEmployeeDetailsQRW['basic_salary'];
	
	$pay_scale = $getEmployeeDetailsQRW['pay_scale'];
	
	$getSalaryIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='$incrementYear' and employeeID='$employeeID'");
    $getSalaryIncrementDataQRW = mysqli_fetch_assoc($getSalaryIncrementDataQ);

	//....

	$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getSalaryIncrementDataQRW[section_id]'");
	$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

	$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getSalaryIncrementDataQRW[organization_id]'");
	$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);


	//

	$getNewPayscaleQ = mysqli_query($con, "select * from `grade` where `id`='$getSalaryIncrementDataQRW[incrementSalaryGrade]'");
	$getNewPayscaleQRW = mysqli_fetch_assoc($getNewPayscaleQ);


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
	<tr><td width="50%"><p style="font-size: 15px;"></p></td><td width="50%"><p style="font-size: 15px;" align="right">নোট অনুমোদনের তারিখ: <?php echo $obj->engToBn(date('d/m/Y')); ?> খ্রিস্টাব্দ</p></td></tr>
</table>


<p>&nbsp;</p>

<p style="font-size: 15px;text-align: justify;">&nbsp;&nbsp;&nbsp;<?php echo $getEmployeeDetailsQRW['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?>, <?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getorgDetailsQRW['organization_name']; ?>কে  অর্থ মন্ত্রণালয়, <?php echo $getIncrementSettingsRW['notice_content']; ?> <?php echo $obj->engToBn(number_format($getNewPayscaleQRW['minimum_salary'],2)).'-'.$obj->engToBn(number_format($getNewPayscaleQRW['maximum_salary'],2)); ?>/= টাকার বেতন স্কেলে <?php echo $obj->engToBn($salaryIncrementDate); ?> তারিখে বাৎসরিক বেতন বৃদ্ধির পর তার মূলবেতন <?php echo $obj->engToBn(number_format($currentBasic,2)); ?>/= হতে <?php echo $obj->engToBn(number_format($getSalaryIncrementDataQRW['incrementSalary'],2)); ?>/= টাকায় উন্নীত করা যেতে পারে। উল্লেখ্য যে, বেতন বৃদ্ধির আলোচ্য বছরে তিনি কোন অবৈতনিক ছুটি ভোগ করেননি। তার নামে কোন বিভাগীয় মামলা/ ইনক্রিমেন্ট এর স্থগিতাদেশ/ বেতন গ্রেড অবনমিতকরণের কোন আদেশ নেই
। সদয় অনুমোদনের জন্য পেশ করা হলো ।</p>

<p>&nbsp;</p>

<p>&nbsp;</p>

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