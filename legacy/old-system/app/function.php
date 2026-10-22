<?php

function todayDateFunc()
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


function calculateLeave($employeeID){

//include('connection.php');

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'techno';
$pass = (string)getenv('DB_PASSWORD');

$con = mysqli_connect($host, $user, $pass, getenv('DB_NAME') ?: 'bitac_db');

// Check connection
if (mysqli_connect_errno())
  {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  }
  else
  {
     //echo 'connected';
  }
mysqli_set_charset($con,"utf8");

$casualBalance = 20;

$casualStart = date('Y').'-'.'01'.'-'.'01';

$casualEnd = date('Y').'-'.'12'.'-'.'31';

// prev hostory

$getPrevLeaveHistory = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$employeeID'");
$getPrevLeaveHistoryRW = mysqli_fetch_assoc($getPrevLeaveHistory);

//....

$str = "";

$getTotalCasualLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalCasual from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='3' and (approvedDateFrom >='$casualStart' and approvedDateTo<='$casualEnd')");
$getTotalCasualLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalCasualLeaveInCurrentYearQ);


$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLWithoutPay from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='4'");
$getTotalLWioutPayLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLWioutPayLeaveInCurrentYearQ);


$getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
$getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);


$getTotalhalfAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalhalfAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
$getTotalhalfAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalhalfAvgSalLeaveUsedQ);


$calculateWPAvgQ = mysqli_query($con, "select sum(leaveDeduction) as totalWPDeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='4' and isApproved=1");
$calculateWPAvgQRW = mysqli_fetch_assoc($calculateWPAvgQ);


// extraordinary leave, new added


$getTotalLExtraOrdinaryLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalExorLeave from leave_applications where status=1 and applicantID='$employeeID' and (leaveTypeInTwo='10')");
$getTotalLExtraOrdinaryLeaveInCurrentYearQRW = mysqli_fetch_assoc($getTotalLExtraOrdinaryLeaveInCurrentYearQ);	
								
//.............

// extraordinary leave, new added

$calculateExOrLManualQ = mysqli_query($con, "select sum(leaveDeduction) as totalExODeduction from leave_deduction_history where employeeID='$employeeID' and leaveID='10' and isApproved=1");
$calculateExOrLManualQRW = mysqli_fetch_assoc($calculateExOrLManualQ);								

//

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

if($getEmployeeDetailsQRW['joining_date'] != ''){

$joiningDateArray = explode('-', $getEmployeeDetailsQRW['joining_date']);

$joiningDate = $joiningDateArray['2'].'/'.$joiningDateArray[1].'/'.$joiningDateArray[0];

$todayDate = todayDateFunc();


$datediff = abs(strtotime($todayDate)-strtotime($getEmployeeDetailsQRW['joining_date']));

$employmentyears = floor($datediff / (365*60*60*24));

$employmentmonths = floor(($datediff - $employmentyears * 365*60*60*24) / (30*60*60*24));

$employmentdays = floor(($datediff - $employmentyears * 365*60*60*24 - $employmentmonths*30*60*60*24)/ (60*60*24));



$diff = abs(strtotime($todayDate)-strtotime($getEmployeeDetailsQRW['joining_date']));

								$days = round($diff/(60*60*24));

								// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days
$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay']);

$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction'] + $getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] + $getPrevLeaveHistoryRW['extraOrdinaryLeave'] + $calculateExOrLManualQRW['totalExODeduction']); // updated

//echo "কার্যকাল".$days."<br>";

$str = $str.$days;



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
	// deduct from prev
	
	$fullAvgSalLeave = $fullAvgSalLeave - $getPrevLeaveHistoryRW['avgSalary'];

	$halfAvgSalLeave = $halfAvgSalLeave - $getPrevLeaveHistoryRW['halfAvgSalary'];

	$fullAvgSalLeaveyears = floor($fullAvgSalLeave / 365);
    $fullAvgSalLeavemonths = floor(($fullAvgSalLeave - ($fullAvgSalLeaveyears * 365))/30.5);
    $fullAvgSalLeavedays = round($fullAvgSalLeave - ($fullAvgSalLeaveyears * 365) - ($fullAvgSalLeavemonths * 30.5));


	$halfAvgSalLeaveyears = floor($halfAvgSalLeave / 365);
    $halfAvgSalLeavemonths = floor(($halfAvgSalLeave - ($halfAvgSalLeaveyears * 365))/30.5);
    $halfAvgSalLeavedays = round($halfAvgSalLeave - ($halfAvgSalLeaveyears * 365) - ($halfAvgSalLeavemonths * 30.5));


}else{

$fullAvgSalLeave = 0;

$halfAvgSalLeave = 0;

}

$fullavgInDays = $fullAvgSalLeave - $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'];


$calcyears = floor($fullavgInDays / 365);

$calcmonths = floor(($fullavgInDays % 365) / 30);

$calcdays = ($fullavgInDays % 365) % 30;

//echo "Years: ".$calcyears;

$halfavgInDays = ($halfAvgSalLeave - $getTotalhalfAvgSalLeaveUsedQRW['totalhalfAvgSal']);

$calcyears2 = floor($halfavgInDays / 365);

$calcmonths2 = floor(($halfavgInDays % 365) / 30);

$calcdays2 = ($halfavgInDays % 365) % 30;

//echo "নৈমিত্তিক ছুটি ".($casualBalance - $getTotalCasualLeaveInCurrentYearQRW['totalCasual'])."<br>";

$str = $str."_".($casualBalance - $getTotalCasualLeaveInCurrentYearQRW['totalCasual']);

//echo "গড়-বেতনে ছুটি ".$calcyears.'/'.$calcmonths.'/'.$calcdays."<br>";

$str = $str."_".$calcyears."_".$calcmonths."_".$calcdays;

//echo "অর্ধ-গড় বেতনে ছুটি".$calcyears2.'/'.$calcmonths2.'/'.$calcdays2."<br>";

$str = $str."_".$calcyears2."_".$calcmonths2."_".$calcdays2;

return $str;


}


//echo calculateLeave(872);

function monthlyLeaveSummary($employeeID, $year, $month){

	 $host = getenv('DB_HOST') ?: 'localhost';
	 $user = getenv('DB_USER') ?: 'techno';
	 $pass = (string)getenv('DB_PASSWORD');

	 $con = mysqli_connect($host, $user, $pass, getenv('DB_NAME') ?: 'bitac_db');

	 // Check connection
	 if (mysqli_connect_errno())
	  {
	  echo "Failed to connect to MySQL: " . mysqli_connect_error();
	  }
	  else
	  {
		 //echo 'connected';
	  }
	 mysqli_set_charset($con,"utf8");

	 $firstDay = date('Y-m-01', strtotime("$year-$month-01")); // First day of the month

	 $lastDay = date('Y-m-t', strtotime("$year-$month-01")); // Last day of the month


	 $getTotalLeaveDaysQ = mysqli_query($con, "select sum(approvedDays) as totalLConsumed from leave_applications where applicantID='$employeeID' and status=1 and approvedDateFrom>='$firstDay' and approvedDateTo<='$lastDay'");
	 $getTotalLeaveDaysQRW = mysqli_fetch_assoc($getTotalLeaveDaysQ);

	 return $getTotalLeaveDaysQRW['totalLConsumed'];


}

//echo monthlyLeaveSummary(1, 2023, 07);

?>
