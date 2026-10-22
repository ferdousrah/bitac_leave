<?php
include('connection.php');

$incrementYear = date('Y');

$employeeID = $_POST['employeeID'];

$getEmployeeDetailsQ = mysqli_query($con,"select * from `employee_list` where id='$employeeID'");
$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);


$getPayScaleDetailsQ = mysqli_query($con, "select * from grade where id='$getEmployeeDetailsQRW[pay_scale]'");
$getPayScaleDetailsQRW = mysqli_fetch_assoc($getPayScaleDetailsQ);


$getSalaryIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='$incrementYear' and employeeID='$employeeID'");
$getSalaryIncrementDataQRW = mysqli_fetch_assoc($getSalaryIncrementDataQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getSalaryIncrementDataQRW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getSalaryIncrementDataQRW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getSalaryIncrementDataQRW[organization_id]'");
$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

$getIncrementSettings = mysqli_query($con,"select * from increment_settings where dataID=1");
	$getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

	$incrementDateArray = explode('-', $getIncrementSettingsRW['salary_increment_date']);

	$salaryIncrementDate = $incrementDateArray['2'].'/'.$incrementDateArray[1].'/'.$incrementDateArray[0];

// new salary calculation

/*
	$currentBasic = $getEmployeeDetailsQRW['basic_salary'];
	
	$pay_scale = $getEmployeeDetailsQRW['pay_scale'];
	
	$getIncremntSalaryQ = mysqli_query($con, "select * from payscale_distribution where pay_scale='$pay_scale' and basic_salary='$currentBasic'");
	$getIncremntSalaryQNumRows = mysqli_num_rows($getIncremntSalaryQ);


	$getIncremntSalaryQRW = mysqli_fetch_assoc($getIncremntSalaryQ);
	
	$getLinkedRowQ = mysqli_query($con, "select * from payscale_distribution where linkedWith='$getIncremntSalaryQRW[id]'");
	$getLinkedRowQRW = mysqli_fetch_assoc($getLinkedRowQ);
	
	$incrementSalary = $getLinkedRowQRW['basic_salary'];

	$newPayscaleID = $getLinkedRowQRW['pay_scale'];

	$salaryDifference = $incrementSalary - $currentBasic;

	$getNewPayscaleQ = mysqli_query($con, "select * from `grade` where `id`='$newPayscaleID'");
	$getNewPayscaleQRW = mysqli_fetch_assoc($getNewPayscaleQ);


	

	
*/


	$getApplicationToDetailsQ = mysqli_query($con, "select * from employee_list where id='$getIncrementSettingsRW[applicationTo]'");
	$getApplicationToDetailsQRW = mysqli_fetch_assoc($getApplicationToDetailsQ);

	$getDesignationDetailsQ2 = mysqli_query($con, "select * from job_title where id='$getApplicationToDetailsQRW[designation]'");
	$getDesignationDetailsQRW2 = mysqli_fetch_assoc($getDesignationDetailsQ2);

	$getSignatoryQ = mysqli_query($con, "select * from increment_data_for_approval where dataRef='$getSalaryIncrementDataQRW[dataID]' order by serial asc");




?>


<p style="text-align:right"><button class="print-link no-print" onclick="jQuery('#ele1').print()">
                Print
                </button></p>


<div id="ele1" style="padding:0px; margin:0; width:100%;">

								<p style="text-align:center;font-size:18px;padding:0px;">
								বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)<br>তেজগাঁও শিল্প এলাকা,<br>ঢাকা-১২০৮<br>বেতন বৃদ্ধির ফরম
								</p>
							                               

                                <table width="100%" align="center" border="0" style="border: 0px solid #4d4f55; border-collapse:collapse;font-size:16px;">
                                    <tbody>

										<tr height="40">
											<td width="200"><span style="font-size: 15px;">১। নাম </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size: 15px;"><?php echo $getEmployeeDetailsQRW['employee_name']; ?></span></td>
																				
										</tr>

										<tr height="40">
											<td><span style="font-size: 15px;">২। পদবী </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size: 15px;"><?php echo $getDesignationDetailsQRW['job_title_name']; ?></span></td>
																				
										</tr>

										<tr height="40">
											<td><span style="font-size: 15px;">৩। শাখা/শপ/বিভাগ </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size:15px;"><?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getorgDetailsQRW['organization_name']; ?></span></td>
																				
										</tr>

										<tr height="40">
											<td><span style="font-size: 15px;">৪। বেতন স্কেল </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size:15px;"><?php echo $getPayScaleDetailsQRW['grade_title']; ?> (<?php echo $obj->engToBn(number_format($getPayScaleDetailsQRW['minimum_salary'])); ?> - <?php echo $obj->engToBn(number_format($getPayScaleDetailsQRW['maximum_salary'])); ?>)</span></td>
																				
										</tr>

										<tr height="40">
											<td><span style="font-size: 15px;">৫। বেতন বৃদ্ধির তারিখ </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size: 15px;"><?php echo $obj->engToBn($salaryIncrementDate); ?></span></td>
																				
										</tr>

										<tr height="40">
											<td><span style="font-size: 15px;">৬। বর্তমান মূল বেতন </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size: 15px;"><?php echo $obj->engToBn(number_format($getEmployeeDetailsQRW['basic_salary'])); ?></span></td>
																				
										</tr>

										<tr height="40">
											<td><span style="font-size: 15px;">৭। বেতন বৃদ্ধির হার </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size:15px;"><?php echo $obj->engToBn(number_format($getSalaryIncrementDataQRW['incrementAmount'])); ?></span></td>
																				
										</tr>

										<tr height="40">
											<td><span style="font-size: 15px;">৮। বেতন বৃদ্ধির পর মূল বেতন </span></td>

											<td width="10"><span style="font-size: 15px;">:</span></td>

											<td style="border-bottom: 1px solid #4d4f55;"><span style="margin-bottom: 5px;font-size:15px;"><?php echo $obj->engToBn(number_format($getSalaryIncrementDataQRW['incrementSalary'])); ?></span></td>
																				
										</tr>
                                    
                                    </tbody>
                                </table>

								<p>&nbsp;</p>


								<table width="100%" align="center" border="0" style="border: 0px solid #4d4f55; border-collapse:collapse;font-size:16px;">
                                    <tbody>

										<tr height="40">
											<td width="50%" style="border-bottom: 0px solid #4d4f55;">

											<span style="margin-bottom: 5px;font-size:15px;">প্রতি : <?php echo $getEmployeeDetailsQRW['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?><br><?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getorgDetailsQRW['organization_name']; ?></span>
											
											</td>	
											
											<!--

											<td style="border-bottom: 0px solid #4d4f55;"><span style="margin-bottom: 5px;font-size:15px;">দস্তখত  <br><?php echo $getDesignationDetailsQRW2['job_title_name']; ?><br><hr style="margin:0px;padding:0px;width:200px;margin-bottom: 0px;padding-bottom:0px;height: 1px;"><br style="margin: 2px 0;line-height:2px;content: '';display: block;"> পদবী </span></td>
											-->									
										</tr>

										
									</tbody>
								</table>






								 <div class="row">
								 <?php while($aRow = mysqli_fetch_array($getSignatoryQ)){ 
								
									 $getUserDetailsQ = mysqli_query($con, "select * from user_list where dataID='$aRow[signatory]'");
									 $getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

									 $getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getUserDetailsQRW[employee_id]'");
									 $getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

									 $getDesignationDetailsQ2 = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQRW[designation]'");
									 $getDesignationDetailsQRW2 = mysqli_fetch_assoc($getDesignationDetailsQ2);

									 $getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQRW[section_id]'");
											$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

											$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQRW[organization_id]'");
											$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);


									 
								?>
								  <div class="col-sm-3" style="width: 200px;float: left;vertical-align: bottom;padding: 40px;">
									<?php if($aRow['isApproved'] == 1 && $getUserDetailsQRW['signature']!=''){ ?>

										<div style='max-height: 100px;min-height: 60px;border: 0px solid #ccc;text-align: center;'><img src="data:image/jpg;charset=utf8;base64,<?php echo base64_encode($getUserDetailsQRW['signature']); ?>" height="60" /></div>

										<?php }else{ echo "<div style='max-height: 100px;min-height: 100px;border: 0px solid #ccc;'>&nbsp;</div>"; } ?><br>

									<?php echo $getEmployeeDetailsQRW['employee_name']; ?><br>

									<span style="font-size: 12px;"><?php echo $getDesignationDetailsQRW2['job_title_name']; ?><br><?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getorgDetailsQRW['organization_name']; ?></span>

								  </div>
								  <?php } ?>
								</div> 



								<!--


								<table width="100%" border="0" style="border: 0px solid #4d4f55; border-collapse:collapse;font-size:16px;">
                                    <tbody>

										<tr height="40">
											<td width="50%" style="border-bottom: 0px solid #4d4f55;text-align: left;font-size:15px;">

											<table border="0">
												
												<tr>
												 <td><span style="font-size: 15px;">বিভাগীয়/শাখা প্রধানের সুপারিশ </span></td>
												 </tr>
												 <tr>
												 <td><hr style="width: 200px;float: left;margin:0px;padding:0px;"></td>
												 </tr>
												 <tr>
												 <td>
													
													<span style="text-align: left;font-size: 15px;">
											সুপারিশ করা গেল/গেল না
											</span>

												 </td>
												</tr>

											</table>

											
											
											</td>											

											<td style="border-bottom: 0px solid #4d4f55;font-size:15px;"><span style="margin-bottom: 5px;">

												

												<table border="0">
												
												
												 <tr>
												 <td><hr style="width: 200px;float: left;margin:0px;padding:0px;"></td>
												 </tr>
												 <tr>
												 <td>
													
													<span style="text-align: left;font-size: 15px;">
													সুপারিশকারী কর্মকর্তার স্বাক্ষর
													<br>পদবী
													</span>

												 </td>
												</tr>

												</table>
											
											
											
											</td>
																				
										</tr>

									</tbody>
								</table>

								<p>&nbsp;</p>
								<p>&nbsp;</p>


								<table width="100%" border="0" style="border: 0px solid #4d4f55; border-collapse:collapse;font-size:16px;">
                                    <tbody>

										<tr height="40">
											<td width="50%" style="border-bottom: 0px solid #4d4f55;text-align: left;font-size:15px;">

											&nbsp;

											
											
											</td>											

											<td style="border-bottom: 0px solid #4d4f55;font-size:15px;"><span style="margin-bottom: 5px;">

												

												<table border="0">
												
												
												 <tr>
												 <td><hr style="width: 220px;float: left;margin:0px;padding:0px;"></td>
												 </tr>
												 <tr>
												 <td>
													
													<span style="text-align: left;font-size: 15px;">
													অনুমোদনকারী কর্তৃপক্ষের স্বাক্ষর
													<br>পদবী
													</span>

												 </td>
												</tr>

												</table>
											
											
											
											</td>
																				
										</tr>

									</tbody>
								</table>

                           -->


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