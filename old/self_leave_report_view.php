<?php
include('connection.php');
include('library/number_converter.php');
include_once('function.php');

function ShowBangladeshDate()
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



function dateDiffInDays($date1, $date2) 
  {
      // Calculating the difference in timestamps
      $diff = strtotime($date2) - strtotime($date1);
  
      // 1 day = 24 hours
      // 24 * 60 * 60 = 86400 seconds
      return abs(round($diff / 86400));
  }


if(isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo'] != ''){

	
	$leaveTypeInTwo = $_POST['leaveTypeInTwo'];

	$leaveTypqSQL = " and leaveTypeInTwo='$leaveTypeInTwo'";


}else{

	$leaveTypqSQL = "";

}

if(isset($_POST['year']) && $_POST['year'] != ''){

	$year = $_POST['year'];

	$sdatefrom = $year.'-'.'01'.'-'.'01';

	$sdateto = $year.'-'.'12'.'-'.'31';

	$yearSQL = " and (approvedDateFrom>='$sdatefrom' and approvedDateTo <= '$sdateto')";

}else{

	$yearSQL = "";

}




$employeeID = $_POST['employeeID'];

// clculate casual



// end of casual


$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeInfoQRW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeInfoQRW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$birthDateArray = explode('-', $getEmployeeInfoQRW['date_of_birth']);

$birthDate = $birthDateArray['2'].'/'.$birthDateArray[1].'/'.$birthDateArray[0];


$joiningDateArray = explode('-', $getEmployeeInfoQRW['joining_date']);

$joiningDate = $joiningDateArray['2'].'/'.$joiningDateArray[1].'/'.$joiningDateArray[0];

$todayDate = ShowBangladeshDate();


$getLeaveTypesQ = mysqli_query($con, "SELECT * FROM `leave_types` where (leaveID!=1 and leaveID!=2 and leaveID!=3)");


$getLeaveApplicationsQ = mysqli_query($con, "select * from leave_applications where applicantID='$employeeID' and status=1 $leaveTypqSQL $yearSQL");


//.....

$resultSTR = calculateLeave($employeeID);

$resultSTRArray = explode('_', $resultSTR);

$employmentyears = floor($resultSTRArray[0] / 365); // Calculate the number of years
$employmentmonths = floor(($resultSTRArray[0] % 365) / 30); // Calculate the number of months
$employmentdays = ($resultSTRArray[0] % 365) % 30; // Calculate the remaining days


$getLeaveDeductionQ = mysqli_query($con, "select * from leave_deduction_history where isApproved=1 and employeeID='$employeeID'");


?>


<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>


<p style="text-align:right"><button class="print-link no-print" onclick="jQuery('#ele1').print()">
                Print
                </button></p>


<div id="ele1" style="padding:0px; margin: 0 auto; width:98%;">

<p style="text-align:center;padding:0px;">
<span style="font-size: 20px;">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</span><br><span style="font-size: 14px;">১১৬(খ), তেজগাঁও শিল্প এলাকা, ঢাকা-১২০৮
</span>
</p>

<table width="100%" border="1" style="border-collapse: collapse; border: 1px solid #ccc;">

								<tr>
									<td height="40"><span style="margin-left: 10px;margin-right: 10px;">নাম</span></td>
									<td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getEmployeeInfoQRW['employee_name']; ?></span></td>
									<td rowspan="5" width="150" align="center"><span style="padding-top: 10px;padding-bottom: 10px;"><img height="150" src="uploads/<?php if($getEmployeeInfoQRW['photo'] != ''){ echo $getEmployeeInfoQRW['photo']; }else{ echo 'default-avatar.png'; } ?>" /></span></td>
								</tr>

								<tr><td height="30"><span style="margin-left: 10px;margin-right: 10px;">জাতীয় পরিচয়পত্র নং</span></td><td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getEmployeeInfoQRW['nationalID']; ?></span></td></tr>


								<tr><td height="30"><span style="margin-left: 10px;margin-right: 10px;">শাখা</span></td><td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getSectionDetailsQRW['section_name']; ?></span></td></tr>


								<tr><td height="30"><span style="margin-left: 10px;margin-right: 10px;">পদবি</span></td><td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getDesignationDetailsQRW['job_title_name']; ?></span></td></tr>


								<tr>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">আইডি</span></td>
									<td colspan="3"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($getEmployeeInfoQRW['employee_id']); ?></span></td>
								</tr>

								
								<tr>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">জন্ম তারিখ</span></td>
									<td><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($birthDate); ?></span></td>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">চাকরিতে যোগদানের তারিখ</span></td>
									<td colspan="2"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($joiningDate); ?></span></td>
								</tr>


								<tr>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">ইমেইল</span></td>
									<td><span style="margin-left: 10px;margin-right: 10px;"><?php echo $getEmployeeInfoQRW['email']; ?></span></td>
									<td height="30"><span style="margin-left: 10px;margin-right: 10px;">মোবাইল নম্বর</span></td>
									<td colspan="2"><span style="margin-left: 10px;margin-right: 10px;"><?php echo $obj->engToBn($getEmployeeInfoQRW['mobileNo']); ?></span></td>
								</tr>
								


							
							</table>

							<p>&nbsp;</p>

							<?php

								
								$getPrevLeaveHistory = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$employeeID' and isApproved=1");
								$getPrevLeaveHistoryRW = mysqli_fetch_assoc($getPrevLeaveHistory);

								// calculate leave deduction

								$calculateFullAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalFullLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='1'");
								$calculateFullAvgQRW = mysqli_fetch_assoc($calculateFullAvgQ);

								$calculateHalfAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalLHalfDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='2'");
								$calculateHalfAvgQRW = mysqli_fetch_assoc($calculateHalfAvgQ);

								$calculateCLAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalCLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='3'");
								$calculateCLAvgQRW = mysqli_fetch_assoc($calculateCLAvgQ);

								// optional leave

								$optionalLHistoryQ = mysqli_query($con, "select sum(leaveDeduction) as totalOLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='5'");
								$optionalLHistoryQRW = mysqli_fetch_assoc($optionalLHistoryQ); 

								// end of optional leave

								$calculateWPAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalWPDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='4'");
								$calculateWPAvgQRW = mysqli_fetch_assoc($calculateWPAvgQ);
								

								$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLWithoutPay from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='4'");
								$getTotalLWioutPayLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLWioutPayLeaveInCurrentYearQ);


								$getTotalLCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLCasualSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3'");
								$getTotalLCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLCasualLeaveInCurrentYearQ);

								// optional leave

								$getTotalOptionalLeaveQ = mysqli_query($con, "select sum(approvedDays) as totalOLSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='5'");
								$getTotalOptionalLeaveQRW = mysqli_fetch_assoc($getTotalOptionalLeaveQ); 


								// end of optional leave


								// total without pay to display

								$totalWithoutPay = ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction']);

								
								$totalWithoutPayyears = floor($totalWithoutPay / 365);
								$totalWithoutPaymonths = floor(($totalWithoutPay - ($totalWithoutPayyears * 365))/30);
								$totalWithoutPaydays = round($totalWithoutPay - ($totalWithoutPayyears * 365) - ($totalWithoutPaymonths * 30));

								//.............................

								

								$diff = abs(strtotime($todayDate)-strtotime($getEmployeeInfoQRW['joining_date']));

								$days = round($diff/(60*60*24));

								$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction']);

								// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days

								$fullAvgSalLeave = floor($days/11);

								$fullAvgSalLeaveRemainder = fmod($days, 11);

								if($fullAvgSalLeaveRemainder >= 6){

									$fullAvgSalLeave = $fullAvgSalLeave + 1;
								
								}

								// in year month day

								$fullAvgSalLeaveyears = floor($fullAvgSalLeave / 365);
								$fullAvgSalLeavemonths = floor(($fullAvgSalLeave - ($fullAvgSalLeaveyears * 365))/30);
								$fullAvgSalLeavedays = round($fullAvgSalLeave - ($fullAvgSalLeaveyears * 365) - ($fullAvgSalLeavemonths * 30));

								// get voghkrito chuti
								$getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
								$getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

								$totalAvgSalVugkrito = $getPrevLeaveHistoryRW['avgSalary'] + $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'] + $calculateFullAvgQRW['totalFullLDeduction'];

								$fullAvgVugkritoSalLeaveyears = floor($totalAvgSalVugkrito / 365);
								$fullAvgVugkritoSalLeavemonths = floor(($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 365))/30);
								$fullAvgVugkritoSalLeavedays = round($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 365) - ($fullAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

								$restAvgSalLeave = $fullAvgSalLeave - $totalAvgSalVugkrito;

								// rest in year month day

								$fullAvgRestSalLeaveyears = floor($restAvgSalLeave / 365);
								$fullAvgRestSalLeavemonths = floor(($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 365))/30);
								$fullAvgRestSalLeavedays = round($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 365) - ($fullAvgRestSalLeavemonths * 30));

								//..................


								$halfAvgSalLeave = floor($days/12);

								$halfAvgSalLeaveRemainder = fmod($days, 12);

								if($halfAvgSalLeaveRemainder >= 6){

									$halfAvgSalLeave = $halfAvgSalLeave + 1;
								
								}

								//echo 'অর্ধ-গড় বেতনে অর্জিত ছুটি:'. $halfAvgSalLeave;

								// in year month day

								$halfAvgSalLeaveyears = floor($halfAvgSalLeave / 365);
								$halfAvgSalLeavemonths = floor(($halfAvgSalLeave - ($halfAvgSalLeaveyears * 365))/30);
								$halfAvgSalLeavedays = round($halfAvgSalLeave - ($halfAvgSalLeaveyears * 365) - ($halfAvgSalLeavemonths * 30));

								// get voghkrito chuti
								$getTotalhalfAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays)*2 as totalHalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
								$getTotalhalfAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalhalfAvgSalLeaveUsedQ);

								$totalHalfAvgSalVugkrito = $getPrevLeaveHistoryRW['halfAvgSalary'] + $getTotalhalfAvgSalLeaveUsedQRW['totalHalfAvgSal'] + $calculateHalfAvgQRW['totalLHalfDeduction'];

								$halfAvgVugkritoSalLeaveyears = floor($totalHalfAvgSalVugkrito / 365);
								$halfAvgVugkritoSalLeavemonths = floor(($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 365))/30);
								$halfAvgVugkritoSalLeavedays = round($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 365) - ($halfAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

								$restHalfAvgSalLeave = $halfAvgSalLeave - $totalHalfAvgSalVugkrito;

								// rest in year month day

								$halfAvgRestSalLeaveyears = floor($restHalfAvgSalLeave / 365);
								$halfAvgRestSalLeavemonths = floor(($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 365))/30);
								$halfAvgRestSalLeavedays = round($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 365) - ($halfAvgRestSalLeavemonths * 30));

								// get total orjito

								$getTotalIorjitoFullLeaveQ = mysqli_query($con, "select sum(approvedDays) as totalOrjitofull from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
								$getTotalIorjitoFullLeaveQRW = mysqli_fetch_assoc($getTotalIorjitoFullLeaveQ);

								$getTotalIorjitoHalfLeaveQ = mysqli_query($con, "select sum(approvedDays)*2 as totalOrjitohalf from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
								$getTotalIorjitoHalfLeaveQRW = mysqli_fetch_assoc($getTotalIorjitoHalfLeaveQ);

								$totalOrjito = $getTotalIorjitoFullLeaveQRW['totalOrjitofull'] + $getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'];

								$totalIorjitobugkritoyears = floor($totalOrjito / 365);
								$totalIorjitobugkritomonths = floor(($totalOrjito - ($totalIorjitobugkritoyears * 365))/30);
								$totalIorjitobugkritodays = round($totalOrjito - ($totalIorjitobugkritoyears * 365) - ($totalIorjitobugkritomonths * 30));

								// orjito ghor betone

								$orjitoGhorbetoneYear = floor($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] / 365);
								$orjitoGhorbetonemonths = floor(($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] - ($orjitoGhorbetoneYear * 365))/30);
								$orjitoGhorbetonedays = round($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] - ($orjitoGhorbetoneYear * 365) - ($orjitoGhorbetonemonths * 30));


								$orjitoOrdhoGhorbetoneYear = floor($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] / 365);
								$orjitoOrdhoGhorbetoneMonths = floor(($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] - ($orjitoOrdhoGhorbetoneYear * 365))/30);
								$orjitoOrdhoGhorbetoneDays = round($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] - ($orjitoOrdhoGhorbetoneYear * 365) - ($orjitoOrdhoGhorbetoneMonths * 30));


								$casualCurrentBalance = $casualBalance - ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $getPrevLeaveHistoryRW['casual'] + $calculateCLAvgQRW['totalCLDeduction']);

								$casualSpent = ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $getPrevLeaveHistoryRW['casual'] + $calculateCLAvgQRW['totalCLDeduction']);


								// optional leave

								$optionalLeaveCurrentBalance = $optionalLBalance - ($getTotalOptionalLeaveQRW['totalOLSpent'] + $getPrevLeaveHistoryRW['optionalLeave'] + $optionalLHistoryQRW['totalOLDeduction']);

								$optionalLeaveSpent = ($getTotalOptionalLeaveQRW['totalOLSpent'] + $getPrevLeaveHistoryRW['optionalLeave'] + $optionalLHistoryQRW['totalOLDeduction']);

								// end of optional leave




							?>

							<p>&nbsp;</p>

							<h4>আপনার মোট চাকরিকাল <br><span style="font-size: 12px;">(<?php echo $obj->engToBn($employmentyears).' বছর '.$obj->engToBn($employmentmonths).' মাস '.$obj->engToBn($employmentdays).' দিন'; ?>)</span></h4>


							<table border="1" width="100%">
								<tr height="40">
								 
								 <th><span style="padding: 10px;font-size: 14px;">&nbsp;ছুটির ধরণ</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">মোট জমা ছুটি</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">মোট ভোগকৃত ছুটি</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">অবশিষ্ট পাওনা ছুটি</span> </th>

								</tr>


								<tr height="30">
								 
								 <td><span style="padding: 10px;font-size: 14px;">&nbsp;ক) গড় বেতনে</span></td>
								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($fullAvgSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($fullAvgSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($fullAvgSalLeavedays); ?>&nbsp;দিন</span></td>

								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($fullAvgVugkritoSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($fullAvgVugkritoSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($fullAvgVugkritoSalLeavedays); ?>&nbsp;দিন</span></td>

								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($fullAvgRestSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($fullAvgRestSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($fullAvgRestSalLeavedays); ?>&nbsp;দিন</span> </td>

								</tr>


								<tr height="30">
								 
								 <td><span style="padding: 10px;font-size: 14px;">&nbsp;খ) অর্ধ-গড় বেতনে</span></td>
								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($halfAvgSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($halfAvgSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($halfAvgSalLeavedays); ?>&nbsp;দিন</span></td>

								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($halfAvgVugkritoSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($halfAvgVugkritoSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($halfAvgVugkritoSalLeavedays); ?>&nbsp;দিন</span></td>

								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($halfAvgRestSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($halfAvgRestSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($halfAvgRestSalLeavedays); ?>&nbsp;দিন</span> </td>

								</tr>


								<tr height="30">
								 
								 <td><span style="padding: 10px;font-size: 14px;">&nbsp;গ) নৈমিত্তিক</span></td>
								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($casualBalance); ?>&nbsp;দিন</span></td>

								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn(number_format($casualSpent)); ?>&nbsp;দিন</span></td>

								 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;">
								 <?php 

								 //$casualCurrentBalance = $casualBalance - ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $getPrevLeaveHistoryRW['casual']);

								 echo $obj->engToBn($casualCurrentBalance);
								 
								 ?>&nbsp;দিন</span> </td>

								</tr>


								<tr height="30">
										 
										 <td><span style="padding: 10px;font-size: 14px;">&nbsp;ঘ) অসাধারণ(বিনা বেতনে ছুটি)</span></td>

										 

										 <td colspan="3" style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($totalWithoutPayyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalWithoutPaymonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalWithoutPaydays); ?>&nbsp;দিন</span></td>

										 
								</tr>


								<tr height="30">
										 
										 <td><span style="padding: 10px;font-size: 14px;">&nbsp;ঙ) ঐচ্ছিক ছুটি</span></td>

										 

										 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($optionalLBalance); ?>&nbsp;দিন</span></td>

										 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn(number_format($optionalLeaveSpent)); ?>&nbsp;দিন</span></td>

										 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;">
										 <?php 

										 

										 echo $obj->engToBn($optionalLeaveCurrentBalance);
										 
										 ?>&nbsp;দিন</span> </td>

										</tr>

								


							</table>

							<p>&nbsp;</p>

							<h4>ছুটির সংক্ষিপ্ত বিবরণী</h4>

							


							<table border="1" width="100%">
								<tr height="40">
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ক্রমিক নং</span></th>
								 <th><span style="padding: 10px;font-size: 14px;">&nbsp;ছুটির ধরণ</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ভোগকৃত ছুটি</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">গড় বেতনে </span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">অর্ধ-গড় বেতনে</span> </th>
								</tr>

								<tr>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn(1); ?></td>
								 <td>&nbsp;অর্জিত ছুটি</td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($totalIorjitobugkritoyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalIorjitobugkritomonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalIorjitobugkritodays); ?>&nbsp;দিন</td>

								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($orjitoGhorbetoneYear); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($orjitoGhorbetonemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($orjitoGhorbetonedays); ?>&nbsp;দিন</td>

								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($orjitoOrdhoGhorbetoneYear); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($orjitoOrdhoGhorbetoneMonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($orjitoOrdhoGhorbetoneDays); ?>&nbsp;দিন</td>
								</tr>

								<?php
								  $ltSl = 1;
								  while($ltRow = mysqli_fetch_array($getLeaveTypesQ)){
									  $ltSl++;

									  if($ltRow['leaveID'] == 8){

										  $getTotalCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalCasual from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3' and (approvedDateFrom >='$casualStart' and approvedDateTo<='$casualEnd')");
										  $getTotalCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ);

										  $usedLeave = number_format($getTotalCasualLeaveInCurrentYearQRW['totalCasual']);

										  $totalFullSLLeave = 0;

										  $totalHalfSLLeave = 0;


									  
									  }else if($ltRow['leaveID'] == 3){

										  $getTotalCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalCasual from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='4' and (approvedDateFrom >='$casualStart' and approvedDateTo<='$casualEnd')");
										  $getTotalCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ);

										  $usedLeave = number_format($getTotalCasualLeaveInCurrentYearQRW['totalCasual']);

										  $totalFullSLLeave = 0;

										  $totalHalfSLLeave = 0;


									  
									  }
									  else{
									  
									  	  $getTotalCasualLeaveInCurrentYearQ2 = mysqli_query($con, "select sum(approvedDays) as totalUsedLeave from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]'");
										  $getTotalCasualLeaveInCurrentYearQ2RW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ2);

										  //$usedLeave = number_format($getTotalCasualLeaveInCurrentYearQ2RW['totalUsedLeave']);

										  // calculate fullsal
										  $getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]' and leaveTypeInTwo='1'");
										  $getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

										  $totalFullSLLeave = $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'];


										  $getTotalfullAvgSalLeaveUsedQ2 = mysqli_query($con, "select sum(approvedDays)*2 as totalhalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]' and leaveTypeInTwo='2'");
										  $getTotalfullAvgSalLeaveUsedQ2RW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ2);

										  $totalHalfSLLeave = $getTotalfullAvgSalLeaveUsedQ2RW['totalhalfAvgSal'];

										  $usedLeave = $totalFullSLLeave + $totalHalfSLLeave;
									  
									  }

									// bugkrito
									$usedLeaveVugkritoYear = floor($usedLeave / 365);
									$usedLeaveVugkritoMonths = floor(($usedLeave - ($usedLeaveVugkritoYear * 365))/30);
									$usedLeaveVugkritoDays = round($usedLeave - ($usedLeaveVugkritoYear * 365) - ($usedLeaveVugkritoMonths * 30));

									// bugkrito
									$totalFullSLLeaveYear = floor($totalFullSLLeave / 365);
									$totalFullSLLeaveMonths = floor(($totalFullSLLeave - ($totalFullSLLeaveYear * 365))/30);
									$totalFullSLLeaveDays = round($totalFullSLLeave - ($totalFullSLLeaveYear * 365) - ($totalFullSLLeaveMonths * 30));


									// bugkrito
									$totalHalfSLLeaveYear = floor($totalHalfSLLeave / 365);
									$totalHalfSLLeaveMonths = floor(($totalHalfSLLeave - ($totalHalfSLLeaveYear * 365))/30);
									$totalHalfSLLeaveDays = round($totalHalfSLLeave - ($totalHalfSLLeaveYear * 365) - ($totalHalfSLLeaveMonths * 30));



								?>



								<tr>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($ltSl); ?></td>
								 <td>&nbsp;<?php echo $ltRow['leaveTitle']; ?></td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($usedLeaveVugkritoYear); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($usedLeaveVugkritoMonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($usedLeaveVugkritoDays); ?>&nbsp;দিন</td>

								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($totalFullSLLeaveYear); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalFullSLLeaveMonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalFullSLLeaveDays); ?>&nbsp;দিন</td>

								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($totalHalfSLLeaveYear); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalHalfSLLeaveMonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalHalfSLLeaveDays); ?>&nbsp;দিন</td>
								</tr>




								<?php } ?>


							</table>

							<p>&nbsp;</p>

							
	
							
							<h4>নোটিশ বোর্ড </h4>

							<table border="1" width="100%" style="border-collapse: collapse; border: 1px solid #ccc;">
								<tr height="40">
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ক্রমিক নং</span></th>
								 <th>&nbsp;&nbsp;<span style="padding: 10px;font-size: 14px;">ছুটির বিষয়</span></th>
								 <th>&nbsp;&nbsp;<span style="padding: 10px;font-size: 14px;">অনুমোদিত ছুটি</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">তারিখ</span> </th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">দিন</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">অফিস আদেশ</span></th>
								</tr>

								<?php 
								  $sl = 1;
								  while($lrow = mysqli_fetch_array($getLeaveApplicationsQ)){

									  $getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$lrow[approvedLeaveType]'");
									  $getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

									  $dateDiff = dateDiffInDays($lrow['approvedDateFrom'], $lrow['approvedDateTo']) + 1;


												$dateF=date_create($lrow['approvedDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$dateT=date_create($lrow['approvedDateTo']);



									if($lrow['leaveTypeInTwo'] == 1){
								
									$approvedLType = "গড় বেতন";
								
									}else if($lrow['leaveTypeInTwo'] == 2){
									
										$approvedLType = "অর্ধ-গড় বেতন";

										$dateDiff = $dateDiff*2;
									
									}else if($lrow['leaveTypeInTwo'] == 3){
									
										$approvedLType = "নৈমিত্তিক (Casual Leave)";
									
									}else if($lrow['leaveTypeInTwo'] == 4){
									
										$approvedLType = "অসাধারণ(বিনা বেতনে ছুটি)";
									
									}else if($lrow['leaveTypeInTwo'] == 5){
									
										$approvedLType = "ঐচ্ছিক (Optional Leave)";
									
									}

								?>

								<tr height="30">
								<td style="text-align: center;"><?php echo $obj->engToBn($sl); ?></td>
								<td>&nbsp;&nbsp;<?php echo $lrow['subject']; ?></td>
								<td>&nbsp;&nbsp;<?php echo $approvedLType; ?></td>
								<td style="text-align: center;"><?php echo banglaNumber(date_format($dateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($dateT,"d/m/Y")); ?></td>
								<td style="text-align: center;"><?php echo banglaNumber($lrow['approvedDays']); ?></td>

								<td><a href="leave_office_notice?menuslug=leave-report&leaveApplicationID=<?php echo $lrow['dataID']; ?>" target="_blank">View</a></td>

								</tr>


								<?php $sl++; } ?>

							</table>

							<p>&nbsp;</p>

							<h4>Leave Deduction on Office Order</h4>
							

							<table border="1" width="100%" style="border-collapse: collapse; border: 1px solid #ccc;">

								<tr height="40">
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ক্রমিক নং</span></th>
								 
								 <th>&nbsp;&nbsp;<span style="padding: 10px;font-size: 14px;">ছুটির ধরণ</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ছুটি কর্তন(দিন)</span> </th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">মন্তব্য</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">অফিস আদেশ</span></th>
								</tr>

								<?php $n = 1; while($ldRow = mysqli_fetch_assoc($getLeaveDeductionQ)){ ?>

								<tr height="30">

									
									<td style="text-align: center;"><?php echo $obj->engToBn($n); ?></td>
									
									<td>&nbsp;&nbsp;
									<?php 
										
									if($ldRow['leaveID'] == 1){

										echo "গড় বেতন ";
									
									}else if($ldRow['leaveID'] == 2){

										echo "অর্ধ-গড় বেতন ";

									}else if($ldRow['leaveID'] == 3){

										echo "নৈমিত্তিক (Casual Leave";

									}else if($ldRow['leaveID'] == 4){

										echo "অসাধারণ(বিনা বেতনে ছুটি)";

									}else if($ldRow['leaveID'] == 5){

										echo "ঐচ্ছিক (Optional Leave)";

									}
									
									
									?>

									
									</td>


									<td style="text-align: center;">									
									

									<?php 
									
										echo $obj->engToBn($ldRow['leaveDeduction']);
									
									?>
									
									</td>

									<td style="text-align: center;">						
									
									<?php 
									
										echo $ldRow['note'];
									
									?>
									
									
									</td>

									<td style="text-align: center;">
										<a href="uploads/<?php echo $ldRow['attachment']; ?>" target="_blank">Download</a>
									</td>

								</tr>

								<?php $n++; } ?>

							</table>



</div> <!-- end of print div -->





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