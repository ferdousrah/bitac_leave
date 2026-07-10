<?php
include('header.php');
include_once('function.php');

$dateStart = date('Y-m-01');

$enddate = date('Y-m-t');

$todayDate = ShowBangladeshDate();




// get today joining from leave






//$casualLeaveBalance = $casualBalance - $getTotalCasualTakenQNumRows;


$getTotalEmpQ = mysqli_query($con, "select count(*) as totalEmp from employee_list where employment_status=1");
$getTotalEmpQRW = mysqli_fetch_assoc($getTotalEmpQ);


$getTodayOnLeaveEMPQ = mysqli_query($con, "select count(*) as todayOnLeave from leave_applications where status=1 and ('$todayDate' between `dateFrom` and `dateTo`)");
$getTodayOnLeaveEMPQRW = mysqli_fetch_assoc($getTodayOnLeaveEMPQ);



//.....

//$resultSTR = calculateLeave($getUserInfoQRW['employee_id']);

//$resultSTRArray = explode('_', $resultSTR);

//echo $resultSTRArray[0];



$employeeID = $getUserInfoQRW['employee_id'];

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);



								
								$getPrevLeaveHistory = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$employeeID' and isApproved=1");
								$getPrevLeaveHistoryRW = mysqli_fetch_assoc($getPrevLeaveHistory);


								// calculate leave deduction

								$calculateFullAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalFullLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='1' and isApproved=1");
								$calculateFullAvgQRW = mysqli_fetch_assoc($calculateFullAvgQ);

								$calculateHalfAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalLHalfDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='2' and isApproved=1");
								$calculateHalfAvgQRW = mysqli_fetch_assoc($calculateHalfAvgQ);

								$calculateCLAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalCLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='3' and isApproved=1 and (createDate between '$casualStart' and '$casualEnd')");
								$calculateCLAvgQRW = mysqli_fetch_assoc($calculateCLAvgQ);

								// optional leave

								$optionalLHistoryQ = mysqli_query($con, "select sum(leaveDeduction) as totalOLDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='5' and isApproved=1 and (createDate between '$casualStart' and '$casualEnd')");
								$optionalLHistoryQRW = mysqli_fetch_assoc($optionalLHistoryQ); 

								// end of optional leave

								$calculateWPAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalWPDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='4' and isApproved=1");
								$calculateWPAvgQRW = mysqli_fetch_assoc($calculateWPAvgQ);

								// extraordinary leave, new added

								$calculateExOrLManualQ = mysqli_query($con, "select sum(leaveDeduction) as totalExODeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='10' and isApproved=1");
								$calculateExOrLManualQRW = mysqli_fetch_assoc($calculateExOrLManualQ);								

								//
								

								$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLWithoutPay from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='4'");
								$getTotalLWioutPayLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLWioutPayLeaveInCurrentYearQ);

								
								// extraordinary leave, new added


								$getTotalLExtraOrdinaryLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalExorLeave from leave_applications where status=1 and applicantID='$employeeID' and (leaveTypeInTwo='10')");
								$getTotalLExtraOrdinaryLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLExtraOrdinaryLeaveInCurrentYearQ);	
								
								//.............


								$getTotalLCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLCasualSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3' and (`approvedDateFrom`>='$casualStart' and `approvedDateTo`<='$casualEnd')");
								$getTotalLCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLCasualLeaveInCurrentYearQ);


								// optional leave

								$getTotalOptionalLeaveQ = mysqli_query($con, "select sum(approvedDays) as totalOLSpent from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='5' and (`approvedDateFrom`>='$casualStart' and `approvedDateTo`<='$casualEnd')");
								$getTotalOptionalLeaveQRW = mysqli_fetch_assoc($getTotalOptionalLeaveQ); 


								// end of optional leave


								// total without pay to display

								$totalWithoutPay = ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction']);

								
								$totalWithoutPayyears = floor($totalWithoutPay / 360);
								$totalWithoutPaymonths = floor(($totalWithoutPay - ($totalWithoutPayyears * 360))/30);
								$totalWithoutPaydays = round($totalWithoutPay - ($totalWithoutPayyears * 360) - ($totalWithoutPaymonths * 30));

								//.............................


								// total extra ordinary leave to display 


								$totalExtraOrdinaryLeave = ($getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] + $getPrevLeaveHistoryRW['extraOrdinaryLeave'] + $calculateExOrLManualQRW['totalExODeduction']);

								
								$totalExtraOrdinaryLeaveYears = floor($totalExtraOrdinaryLeave / 360);
								$totalExtraOrdinaryLeaveMonths = floor(($totalExtraOrdinaryLeave - ($totalExtraOrdinaryLeaveYears * 360))/30);
								$totalExtraOrdinaryLeaveDays = round($totalExtraOrdinaryLeave - ($totalExtraOrdinaryLeaveYears * 360) - ($totalExtraOrdinaryLeaveMonths * 30));



								//.........


								

								$diff = abs(strtotime($todayDate)-strtotime($getEmployeeInfoQRW['joining_date']));

								$days = round($diff/(60*60*24));

								//$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction']);

								$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction'] + $getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] + $getPrevLeaveHistoryRW['extraOrdinaryLeave'] + $calculateExOrLManualQRW['totalExODeduction']); // updated

								//echo "Service : ".$days;

								// job duration

								

								//......

								$days = $days - 1;

								$employmentyears = floor($days / 365);
								$employmentmonths = floor(($days - ($employmentyears * 365))/30.416667);
								$employmentdays = round($days - ($employmentyears * 365) - ($employmentmonths * 30.416667));

								// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days

								$fullAvgSalLeave = floor($days/11);

								$fullAvgSalLeaveRemainder = fmod($days, 11);

								if($fullAvgSalLeaveRemainder >= 6){

									$fullAvgSalLeave = $fullAvgSalLeave + 1;
								
								}

								// in year month day

								//echo "Full Avg Sal Spent ".$fullAvgSalLeave;

								$fullAvgSalLeaveyears = floor($fullAvgSalLeave / 360);
								$fullAvgSalLeavemonths = floor(($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360))/30);
								$fullAvgSalLeavedays = round($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360) - ($fullAvgSalLeavemonths * 30));

								//echo $fullAvgSalLeaveyears.'/'.$fullAvgSalLeavemonths.'/'.$fullAvgSalLeavedays;



								// get voghkrito chuti
								$getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
								$getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

								$totalAvgSalVugkrito = $getPrevLeaveHistoryRW['avgSalary'] + $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'] + $calculateFullAvgQRW['totalFullLDeduction'];

								

								$fullAvgVugkritoSalLeaveyears = floor($totalAvgSalVugkrito / 360);
								$fullAvgVugkritoSalLeavemonths = floor(($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360))/30);
								$fullAvgVugkritoSalLeavedays = round($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360) - ($fullAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

								//echo "Full Avg Sal Spent ".$fullAvgSalLeave;

								$restAvgSalLeave = $fullAvgSalLeave - $totalAvgSalVugkrito;

								// rest in year month day

								$fullAvgRestSalLeaveyears = floor($restAvgSalLeave / 360);
								$fullAvgRestSalLeavemonths = floor(($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 360))/30);
								$fullAvgRestSalLeavedays = round($restAvgSalLeave - ($fullAvgRestSalLeaveyears * 360) - ($fullAvgRestSalLeavemonths * 30));

								//..................


								$halfAvgSalLeave = floor($days/12);

								$halfAvgSalLeaveRemainder = fmod($days, 12);

								if($halfAvgSalLeaveRemainder >= 6){

									$halfAvgSalLeave = $halfAvgSalLeave + 1;
								
								}

								//echo 'অর্ধ-গড় বেতনে অর্জিত ছুটি:'. $halfAvgSalLeave;

								// in year month day

								$halfAvgSalLeaveyears = floor($halfAvgSalLeave / 360);
								$halfAvgSalLeavemonths = floor(($halfAvgSalLeave - ($halfAvgSalLeaveyears * 360))/30);
								$halfAvgSalLeavedays = round($halfAvgSalLeave - ($halfAvgSalLeaveyears * 360) - ($halfAvgSalLeavemonths * 30));

								// get voghkrito chuti
								$getTotalhalfAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays)*2 as totalHalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
								$getTotalhalfAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalhalfAvgSalLeaveUsedQ);

								$totalHalfAvgSalVugkrito = $getPrevLeaveHistoryRW['halfAvgSalary'] + $getTotalhalfAvgSalLeaveUsedQRW['totalHalfAvgSal'] + $calculateHalfAvgQRW['totalLHalfDeduction'];

								//echo "Total: ".$getTotalhalfAvgSalLeaveUsedQRW['totalHalfAvgSal'];

								$halfAvgVugkritoSalLeaveyears = floor($totalHalfAvgSalVugkrito / 360);
								$halfAvgVugkritoSalLeavemonths = floor(($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360))/30);
								$halfAvgVugkritoSalLeavedays = round($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360) - ($halfAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

								$restHalfAvgSalLeave = $halfAvgSalLeave - $totalHalfAvgSalVugkrito;

								// rest in year month day

								$halfAvgRestSalLeaveyears = floor($restHalfAvgSalLeave / 360);
								$halfAvgRestSalLeavemonths = floor(($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360))/30);
								$halfAvgRestSalLeavedays = round($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360) - ($halfAvgRestSalLeavemonths * 30));


								$casualCurrentBalance = $casualBalance - ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $calculateCLAvgQRW['totalCLDeduction']);

								$casualSpent = ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $calculateCLAvgQRW['totalCLDeduction']);

								// optional leave

								$optionalLeaveCurrentBalance = $optionalLBalance - ($getTotalOptionalLeaveQRW['totalOLSpent'] + $optionalLHistoryQRW['totalOLDeduction']);

								$optionalLeaveSpent = ($getTotalOptionalLeaveQRW['totalOLSpent'] + $optionalLHistoryQRW['totalOLDeduction']);

								// end of optional leave



							?>


<style>
        .countdown {
            font-family: Arial, sans-serif;
            text-align: center;
            font-size: 1.5em;
            color: #333;
        }
        .countdown span {
            display: inline-block;
            margin: 0 10px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
        }
        .prl-date {
            font-size: 1.2em;
            margin-top: 20px;
            color: #555;
        }
    </style>



<div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper">



		<!-- dashboard summary -->



			<div class="row match-height">	

				


				<div class="col-xl-2 col-lg-12 col-12">
					<div class="card" style="background-color: #2d3e50;color:#fff">
						
						<div class="card-body" style="padding-top: 20px;">

							<p style="text-align: center;"><img src="dashboard-icons/halfleave.png" width="64" /></p>

							<p style="text-align: center;text-transform: uppercase;margin-bottom: 0rem;font-size: 14px;">গড়-বেতনে ছুটি</p>

							<p style="text-align: center;text-transform: uppercase;font-size: 13px;color: yellow;"><?php echo $obj->engToBn($fullAvgRestSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($fullAvgRestSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($fullAvgRestSalLeavedays); ?>&nbsp;দিন</p>

							

						</div>
					</div>
				</div>


				<div class="col-xl-2 col-lg-12 col-12">
					<div class="card" style="background-color: #6dbcdb; color: #fff;">
						
						<div class="card-body" style="padding-top: 20px;">

							<p style="text-align: center;"><img src="dashboard-icons/sunbed.png" width="64" /></p>

							<p style="text-align: center;text-transform: uppercase;margin-bottom: 0rem;font-size: 14px;">অর্ধ-গড় বেতনে ছুটি</p>

							<p style="text-align: center;text-transform: uppercase;font-size: 13px;color: yellow;"><?php echo $obj->engToBn($halfAvgRestSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($halfAvgRestSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($halfAvgRestSalLeavedays); ?>&nbsp;দিন</p>

							

						</div>
					</div>
				</div>


				<div class="col-xl-2 col-lg-12 col-12">
					<div class="card" style="background-color: #8BACAA; color: #fff;">
						
						<div class="card-body" style="padding-top: 20px;">

							<p style="text-align: center;"><img src="dashboard-icons/sunbed.png" width="64" /></p>

							<p style="text-align: center;text-transform: uppercase;margin-bottom: 0rem;font-size: 14px;">নৈমিত্তিক ছুটি </p>

							<p style="text-align: center;text-transform: uppercase;font-size: 13px;color: yellow;"><?php echo $obj->engToBn(($casualCurrentBalance)); ?>&nbsp;দিন</p>

							

						</div>
					</div>
				</div>


				<div class="col-xl-2 col-lg-12 col-12">
					<div class="card" style="background-color: #5C5470; color: #fff;">
						
						<div class="card-body" style="padding-top: 20px;">

							<p style="text-align: center;"><img src="dashboard-icons/no-money.png" width="64" /></p>

							<p style="text-align: center;text-transform: uppercase;margin-bottom: 0rem;font-size: 14px;">বিনা বেতনে ভোগকৃত ছুটি</p>

							<p style="text-align: center;text-transform: uppercase;font-size: 13px;color: yellow;"><?php echo $obj->engToBn($totalWithoutPayyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalWithoutPaymonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalWithoutPaydays); ?>&nbsp;দিন</p>

							

						</div>
					</div>
				</div>


				<div class="col-xl-2 col-lg-12 col-12">
					<div class="card" style="background-color: #5C5470; color: #fff;">
						
						<div class="card-body" style="padding-top: 20px;">

							<p style="text-align: center;"><img src="dashboard-icons/no-money.png" width="64" /></p>

							<p style="text-align: center;text-transform: uppercase;margin-bottom: 0rem;font-size: 14px;">অসাধারণ  ভোগকৃত ছুটি</p>

							<p style="text-align: center;text-transform: uppercase;font-size: 13px;color: yellow;"><?php echo $obj->engToBn($totalExtraOrdinaryLeaveYears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalExtraOrdinaryLeaveMonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalExtraOrdinaryLeaveDays); ?>&nbsp;দিন</p>

							

						</div>
					</div>
				</div>



				<div class="col-xl-2 col-lg-12 col-12">
					<div class="card" style="background-color: #8BACAA; color: #fff;">
						
						<div class="card-body" style="padding-top: 20px;">

							<p style="text-align: center;"><img src="dashboard-icons/sunbed.png" width="64" /></p>

							<p style="text-align: center;text-transform: uppercase;margin-bottom: 0rem;font-size: 14px;">ঐচ্ছিক ছুটি </p>

							<p style="text-align: center;text-transform: uppercase;font-size: 13px;color: yellow;"><?php echo $obj->engToBn(($optionalLeaveCurrentBalance)); ?>&nbsp;দিন</p>

							

						</div>
					</div>
				</div>
				
				

				


			</div>






							<div class="row match-height">
								<div class="col-xl-10 col-lg-12">
									<div class="card" style="margin-bottom: 0px;padding-bottom: 0px;">
										<div class="card-header" style="background-color: #002060;">
											<h4 class="card-title" style="color: #ffc000;text-align: center;">আপনার মোট চাকরিকাল <br><span style="color: #dbeef4; font-size: 12px;">(<?php echo $obj->engToBn($employmentyears).' বছর '.$obj->engToBn($employmentmonths).' মাস '.$obj->engToBn($employmentdays).' দিন'; ?>)</span></h4>
										</div>
										<div class="card-body">


										<div class="table-container" style="height: auto;margin-bottom: 0px;padding-bottom: 0px;">
											<table class="table table-bordered">
											<tr height="40" style="background-color: #daeef3;">
											 
											 <th><span style="padding: 10px;font-size: 14px;">&nbsp;ছুটির ধরণ</span></th>
											 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">মোট জমা ছুটি</span></th>
											 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">মোট ভোগকৃত ছুটি</span></th>
											 <th style="text-align: center;"><span style="padding: 10px;font-size: 14px;">অবশিষ্ট পাওনা ছুটি</span> </th>

											</tr>


											<tr height="30">
											 
											 <td style="background-color: #daeef3;"><span style="padding: 10px;font-size: 14px;">&nbsp;ক) গড় বেতনে</span></td>
											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($fullAvgSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($fullAvgSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($fullAvgSalLeavedays); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($fullAvgVugkritoSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($fullAvgVugkritoSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($fullAvgVugkritoSalLeavedays); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($fullAvgRestSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($fullAvgRestSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($fullAvgRestSalLeavedays); ?>&nbsp;দিন</span> </td>

											</tr>


											<tr height="30">
											 
											 <td style="background-color: #daeef3;"><span style="padding: 10px;font-size: 14px;">&nbsp;খ) অর্ধ-গড় বেতনে</span></td>
											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($halfAvgSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($halfAvgSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($halfAvgSalLeavedays); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($halfAvgVugkritoSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($halfAvgVugkritoSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($halfAvgVugkritoSalLeavedays); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($halfAvgRestSalLeaveyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($halfAvgRestSalLeavemonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($halfAvgRestSalLeavedays); ?>&nbsp;দিন</span> </td>

											</tr>


											<tr height="30">
											 
											 <td style="background-color: #daeef3;"><span style="padding: 10px;font-size: 14px;">&nbsp;গ) নৈমিত্তিক</span></td>
											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($casualBalance); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn(number_format($casualSpent)); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;">
											 <?php 

											 

											 echo $obj->engToBn($casualCurrentBalance);
											 
											 ?>&nbsp;দিন</span> </td>

											</tr>


											<tr height="30">
											 
											 <td style="background-color: #daeef3;"><span style="padding: 10px;font-size: 14px;">&nbsp;ঘ) বিনা বেতনে ছুটি</span></td>

											 

											 <td colspan="3" style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($totalWithoutPayyears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalWithoutPaymonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalWithoutPaydays); ?>&nbsp;দিন</span></td>

											 
											</tr>


											<tr height="30">
											 
											 <td style="background-color: #daeef3;"><span style="padding: 10px;font-size: 14px;">&nbsp;ঙ) ঐচ্ছিক ছুটি</span></td>

											 

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($optionalLBalance); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn(number_format($optionalLeaveSpent)); ?>&nbsp;দিন</span></td>

											 <td style="text-align: center;"><span style="padding: 10px;font-size: 14px;">
											 <?php 

											 

											 echo $obj->engToBn($optionalLeaveCurrentBalance);
											 
											 ?>&nbsp;দিন</span> </td>

											</tr>


											<tr height="30">
											 
											 <td style="background-color: #daeef3;"><span style="padding: 10px;font-size: 14px;">&nbsp;চ) অসাধারণ ছুটি</span></td>

											 

											 <td colspan="3" style="text-align: center;"><span style="padding: 10px;font-size: 14px;"><?php echo $obj->engToBn($totalExtraOrdinaryLeaveYears); ?>&nbsp;বছর&nbsp;<?php echo $obj->engToBn($totalExtraOrdinaryLeaveMonths); ?>&nbsp;মাস&nbsp;<?php echo $obj->engToBn($totalExtraOrdinaryLeaveDays); ?>&nbsp;দিন</span></td>

											</tr>

											


										</table>

										</div>

									</div>
								</div>
								
								
							</div>

							<div class="col-xl-2 col-lg-12">

									<div class="card" style="margin-bottom: 0px;padding-bottom: 0px;">
										<div class="card-header" style="background-color: #002060;">
											<h4 class="card-title" style="color: #ffc000;text-align: center;">অবসর গ্রহণের তারিখ<br><span style="color: #dbeef4; font-size: 12px;" class="prl-date" id="prlDateDisplay">PRL Date: --/--/--</span></h4>
										</div>
										<div class="card-body">

											<div class="countdown">
												<span id="years">0</span> বছর
												<span id="months">0</span> মাস
												<span id="days">0</span> দিন
											</div>
    
										</div>
									</div>
									
								</div>

						


					</div>





			<?php if($getUserInfoQRW['dashboardType'] == 2){ ?>

			<div class="row">
				<div class="col-xl-10 col-lg-10 col-10">


					<div class="card" style="height: 849px;">
						
						<div class="card-header">
							<h4 class="card-title">ছুটি হইতে কর্মস্থলে যোগদান করেননি</h4>
						</div>

					

						<div class="card-body">


							<table id="absent_employees" class="table table-striped table-bordered scroll-vertical" style="width: 100%;" width="100%">
								<thead>
									<tr>
										<th>ক্রমিক</th>
										<th>কর্মকর্তা/কর্মচারীর নাম</th>
										<th>আইডি</th>
										<th>পদবী</th>
										<th>শাখা</th>
										<th>যোগদানের তারিখ</th>
									</tr>
								</thead>
								<tbody>
									<!-- Data will be inserted here dynamically by DataTables -->
								</tbody>
							</table>



						</div>

					</div>


				</div>
			</div>


			<p>&nbsp;</p>


<?php } ?>






		<!-- end of summary -->





          </div>
        </div>

        
		
</div>







<?php
include('footer.php');

?>

<script>

$(document).ready(function() {
    // Destroy the DataTable if it's already initialized
    if ($.fn.dataTable.isDataTable('#absent_employees')) {
        $('#absent_employees').DataTable().destroy();
    }

    // Reinitialize the DataTable
    $('#absent_employees').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "get_absent_employees.php",
            "type": "POST"
        },
        "columns": [
            { "data": "serial" },
            { "data": "employee_name" },
            { "data": "employee_id" },
            { "data": "job_title" },
            { "data": "section_name" },
            { "data": "joining_date" }
        ]
    });
});




        function fetchCountdown() {
            fetch('prlcountdown.php')
                .then(response => response.json())
                .then(data => updateCountdown(data))
                .catch(error => console.error('Error fetching countdown:', error));
        }

        function updateCountdown(data) {
            document.getElementById('years').textContent = data.years;
            document.getElementById('months').textContent = data.months;
            document.getElementById('days').textContent = data.days;
            document.getElementById('prlDateDisplay').textContent = "(" + data.prl_date + ")";
        }

        // Initial load
        fetchCountdown();

        // Update countdown every day
        setInterval(fetchCountdown, 24 * 60 * 60 * 1000); // 24 hours in milliseconds
    </script>