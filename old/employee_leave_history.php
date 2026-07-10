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

$employeeID = $_GET['employeeID'];

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


$getLeaveTypesQ = mysqli_query($con, "SELECT * FROM `leave_types`");


$getLeaveApplicationsQ = mysqli_query($con, "select * from leave_applications where applicantID='$employeeID' and status=1");


//.....

$resultSTR = calculateLeave($employeeID);

$resultSTRArray = explode('_', $resultSTR);

$employmentyears = floor($resultSTRArray[0] / 365); // Calculate the number of years
$employmentmonths = floor(($resultSTRArray[0] % 365) / 30); // Calculate the number of months
$employmentdays = ($resultSTRArray[0] % 365) % 30; // Calculate the remaining days



//.....

$resultSTR2 = calculateLeave($employeeID);

$resultSTRArray2 = explode('_', $resultSTR2);

// monthly leave consumption
$cyear = date('Y');

$monthlyCons2 = array(); // Array to store the monthly leave summaries

for ($month = 1; $month <= 12; $month++) {
    $monthlyCons2[$month] = monthlyLeaveSummary($employeeID, $cyear, $month);

}

$sdata2 = "[";



$sdata2 = $sdata2.number_format($monthlyCons2[1]).", ".number_format($monthlyCons2[2]).", ".number_format($monthlyCons2[3]).", ".number_format($monthlyCons2[4]).", ".number_format($monthlyCons2[5]).", ".number_format($monthlyCons2[6]).", ".number_format($monthlyCons2[7]).", ".number_format($monthlyCons2[8]).", ".number_format($monthlyCons2[9]).", ".number_format($monthlyCons2[10]).", ".number_format($monthlyCons2[11]).", ".number_format($monthlyCons2[12]);


$sdata2 = $sdata2."]";

//echo $sdata2;

?>


<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <link rel="stylesheet" type="text/css" href="app-assets/vendors/css/chartist.min.css">


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

							<h4>Leave Summary - Year <?php echo date('Y'); ?></h4>

							<div id="chart"></div>

							<p>&nbsp;</p>

							<?php
								

								

								$diff = abs(strtotime($todayDate)-strtotime($getEmployeeInfoQRW['joining_date']));

								$days = round($diff/(60*60*24));

								// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days

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

								//echo 'অর্ধ-গড় বেতনে অর্জিত ছুটি:'. $halfAvgSalLeave;

							?>

							<div class="row">
							  <div class="col-sm-2" style="padding-bottom: 10px;float: left;">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									চাকরির সময়কাল <br>
									<?php echo $obj->engToBn($employmentyears).' বছর '.$obj->engToBn($employmentmonths).' মাস '.$obj->engToBn($employmentdays).' দিন'; ?>
								</div>
							  </div>
							  <div class="col-sm-2" style="padding-bottom: 10px;float: left;">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									নৈমিত্তিক পাওনা ছুটি<br>
									<?php echo $obj->engToBn(($resultSTRArray[1])); ?>&nbsp;দিন
								</div>
							  </div>
							  <div class="col-sm-2" style="padding-bottom: 10px;float: left;">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									গড়-বেতনে পাওনা ছুটি<br>
									<?php echo $obj->engToBn($resultSTRArray[2]); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($resultSTRArray[3]); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($resultSTRArray[4]); ?>&nbsp;দিন
								</div>
							  </div>
							  <div class="col-sm-2" style="float: left;">
							  	<div style="border: 1px solid #000;text-align: center;padding: 10px;">
									অর্ধ-গড় বেতনে পাওনা ছুটি <br>
									<?php echo $obj->engToBn($resultSTRArray[5]); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($resultSTRArray[6]); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($resultSTRArray[7]); ?>&nbsp;দিন
								</div>
							  </div>



							</div>

							<p>&nbsp;</p>							


							<table border="1" width="100%">
								<tr>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ক্রমিক নং</span></th>
								 <th><span style="padding: 10px;font-size: 14px;">&nbsp;ছুটির ধরণ</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ভোগকৃত ছুটি(দিন)</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">গড় বেতনে (দিন)</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">অর্ধ-গড় বেতনে(দিন)</span> </th>
								</tr>

								<?php
								  $ltSl = 0;
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

										  $usedLeave = number_format($getTotalCasualLeaveInCurrentYearQ2RW['totalUsedLeave']);

										  // calculate fullsal
										  $getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]' and leaveTypeInTwo='1'");
										  $getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

										  $totalFullSLLeave = $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'];


										  $getTotalfullAvgSalLeaveUsedQ2 = mysqli_query($con, "select sum(approvedDays) as totalhalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and approvedLeaveType='$ltRow[leaveID]' and leaveTypeInTwo='2'");
										  $getTotalfullAvgSalLeaveUsedQ2RW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ2);

										  $totalHalfSLLeave = $getTotalfullAvgSalLeaveUsedQ2RW['totalhalfAvgSal'];
									  
									  }
								?>



								<tr>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn($ltSl); ?></td>
								 <td>&nbsp;<?php echo $ltRow['leaveTitle']; ?></td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn(number_format($usedLeave)); ?></td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn(number_format($totalFullSLLeave)); ?></td>
								 <td style="text-align: center;">&nbsp;<?php echo $obj->engToBn(number_format($totalHalfSLLeave)); ?></td>
								</tr>




								<?php } ?>


							</table>

							<p>&nbsp;</p>							

							<p>ভোগকৃত ছুটির বিবরণ</p>
							<hr>

							<table border="1" width="100%" style="border-collapse: collapse; border: 1px solid #ccc;">
								<tr>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ক্রমিক নং</span></th>
								 <th>&nbsp;&nbsp;<span style="padding: 10px;font-size: 14px;">ছুটির ধরণ</span></th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">তারিখ</span> </th>
								 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">ছুটির সময়কাল(দিন)</span></th>
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

								?>

								<tr>
								<td style="text-align: center;"><?php echo $obj->engToBn($sl); ?></td>
								<td>&nbsp;&nbsp;<?php echo $getLeaveTypeQRW['leaveTitle']; ?></td>
								<td style="text-align: center;"><?php echo banglaNumber(date_format($dateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($dateT,"d/m/Y")); ?></td>
								<td style="text-align: center;"><?php echo banglaNumber($dateDiff); ?></td>

								</tr>


								<?php $sl++; } ?>

							</table>

							<p>&nbsp;</p>



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



		<script src='https://cdn.jsdelivr.net/npm/apexcharts'></script>

<script>

	var options = {
  chart: {
    height: 280,
    type: "area"
  },
  dataLabels: {
    enabled: true
  },
  series: [
    {
      name: "Leave spent",
      data: <?php echo $sdata2; ?>
    }
  ],
  fill: {
    type: "gradient",
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.7,
      opacityTo: 0.9,
      stops: [0, 90, 100]
    }
  },
  xaxis: {
    categories: [
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "May",
      "Jun",
      "Jul",
      "Aug",
      "Sep",
      "Oct",
      "Nov",
      "Dec"
    ]
  }
};

var chart = new ApexCharts(document.querySelector("#chart"), options);

chart.render();
	
</script>