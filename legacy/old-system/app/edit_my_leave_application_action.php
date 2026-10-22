<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$submitDate = todayDate();

$submitTime = logTime();

$todayDate = todayDate();

$employeeID = $_POST['employeeID'];

$onbehalf = 0;



if($employeeID == 0){

	if(isset($_POST['employeeIDOnbehalf']) && $_POST['employeeIDOnbehalf'] !=''){

		
		$employeeID = $_POST['employeeIDOnbehalf'];

		$onbehalf = $getUserDetailsQRW['employee_id'];

		$getOnbehalfUserDetailsQ = mysqli_query($con, "select * from `user_list` where employee_id='$employeeID'");
		$getOnbehalfUserDetailsQRW = mysqli_fetch_assoc($getOnbehalfUserDetailsQ);

		$signature = $con -> real_escape_string($getOnbehalfUserDetailsQRW['signature']);
	
	}else{
	
		echo 0;
		exit();
	
	}

}else{

	$signature = $con -> real_escape_string($getUserDetailsQRW['signature']);

}


$dataID = $_POST['dataID'];


$supervisorID = $_POST['supervisorID'];

$leaveType = $_POST['leaveType'];

$dateF = $_POST['leaveFrom'];

$dateFArray = explode('/', $dateF);

$dateFrom = $dateFArray[2].'-'.$dateFArray[1].'-'.$dateFArray[0];





$dateT = $_POST['leaveTo'];

$dateTArray = explode('/', $dateT);

$dateTo = $dateTArray[2].'-'.$dateTArray[1].'-'.$dateTArray[0];

$subject = $_POST['subject'];

$leaveApplication = $_POST['leaveApplication'];

$applicationTo = $_POST['to'];

$applicationType = $_POST['applicationType'];

if (isset($_POST['isinformedValue']) && $_POST['isinformedValue'] != '') {

	$isinformedValue = $_POST['isinformedValue'];

}else{

	$isinformedValue = 0;

}

if(!file_exists($_FILES['leaveFile']['tmp_name']) || !is_uploaded_file($_FILES['leaveFile']['tmp_name'])) {
   // echo 'No upload'; 
   $leaveFile = $_POST['prevAttachment'];
}
else {

$leaveFile = $_FILES['leaveFile']['name'];
$file_size =$_FILES['leaveFile']['size'];
$file_tmp =$_FILES['leaveFile']['tmp_name'];
$file_type=$_FILES['leaveFile']['type'];
$file_extArray=explode('.',$_FILES['leaveFile']['name']);
//echo $file_extArray[1];
$file_ext = strtolower($file_extArray[1]);	  

$extensions= array("jpeg","jpg","png","pdf");
      
if(in_array($file_ext,$extensions)== false){
	$errors[]="extension not allowed, please choose a JPEG or PNG file.";
}
      
	if($file_size > 2097152){
		$errors[]='File size must be excately 2 MB';
	}
      
	if(empty($errors)==true){
		move_uploaded_file($file_tmp,"uploads/".$leaveFile);
		//echo "Success";
	}else{
		print_r($errors);
		$leaveFile = $_POST['prevAttachment'];
	}
}



	// calculate leave balance

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
								

								$getTotalLWioutPayLeaveInCurrentYearQ = mysqli_query($con, "select sum(approvedDays) as totalLWithoutPay from leave_applications where status=1 and applicantID='$employeeID' and (leaveTypeInTwo='4')");
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

								$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay'] + $calculateWPAvgQRW['totalWPDeduction'] + $getTotalLExtraOrdinaryLeaveInCurrentYearQRW['totalExorLeave'] + $getPrevLeaveHistoryRW['extraOrdinaryLeave'] + $calculateExOrLManualQRW['totalExODeduction']); // updated

								// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days

								$fullAvgSalLeave = floor($days/11);

								$fullAvgSalLeaveRemainder = fmod($days, 11);

								if($fullAvgSalLeaveRemainder >= 6){

									$fullAvgSalLeave = $fullAvgSalLeave + 1;
								
								}

								// in year month day

								$fullAvgSalLeaveyears = floor($fullAvgSalLeave / 360);
								$fullAvgSalLeavemonths = floor(($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360))/30);
								$fullAvgSalLeavedays = round($fullAvgSalLeave - ($fullAvgSalLeaveyears * 360) - ($fullAvgSalLeavemonths * 30));

								// get voghkrito chuti
								$getTotalfullAvgSalLeaveUsedQ = mysqli_query($con, "select sum(approvedDays) as totalAvgSal from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
								$getTotalfullAvgSalLeaveUsedQRW = mysqli_fetch_assoc($getTotalfullAvgSalLeaveUsedQ);

								$totalAvgSalVugkrito = $getPrevLeaveHistoryRW['avgSalary'] + $getTotalfullAvgSalLeaveUsedQRW['totalAvgSal'] + $calculateFullAvgQRW['totalFullLDeduction'];

								$fullAvgVugkritoSalLeaveyears = floor($totalAvgSalVugkrito / 360);
								$fullAvgVugkritoSalLeavemonths = floor(($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360))/30);
								$fullAvgVugkritoSalLeavedays = round($totalAvgSalVugkrito - ($fullAvgVugkritoSalLeaveyears * 360) - ($fullAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

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

								$halfAvgVugkritoSalLeaveyears = floor($totalHalfAvgSalVugkrito / 360);
								$halfAvgVugkritoSalLeavemonths = floor(($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360))/30);
								$halfAvgVugkritoSalLeavedays = round($totalHalfAvgSalVugkrito - ($halfAvgVugkritoSalLeaveyears * 360) - ($halfAvgVugkritoSalLeavemonths * 30));

								// get rest avg salary leave

								$restHalfAvgSalLeave = $halfAvgSalLeave - $totalHalfAvgSalVugkrito;

								// rest in year month day

								$halfAvgRestSalLeaveyears = floor($restHalfAvgSalLeave / 360);
								$halfAvgRestSalLeavemonths = floor(($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360))/30);
								$halfAvgRestSalLeavedays = round($restHalfAvgSalLeave - ($halfAvgRestSalLeaveyears * 360) - ($halfAvgRestSalLeavemonths * 30));

								// get total orjito

								$getTotalIorjitoFullLeaveQ = mysqli_query($con, "select sum(approvedDays) as totalOrjitofull from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='1'");
								$getTotalIorjitoFullLeaveQRW = mysqli_fetch_assoc($getTotalIorjitoFullLeaveQ);

								$getTotalIorjitoHalfLeaveQ = mysqli_query($con, "select sum(approvedDays)*2 as totalOrjitohalf from leave_applications where status=1 and applicantID='$employeeID' and leaveTypeInTwo='2'");
								$getTotalIorjitoHalfLeaveQRW = mysqli_fetch_assoc($getTotalIorjitoHalfLeaveQ);

								$totalOrjito = $getTotalIorjitoFullLeaveQRW['totalOrjitofull'] + $getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'];

								$totalIorjitobugkritoyears = floor($totalOrjito / 360);
								$totalIorjitobugkritomonths = floor(($totalOrjito - ($totalIorjitobugkritoyears * 360))/30);
								$totalIorjitobugkritodays = round($totalOrjito - ($totalIorjitobugkritoyears * 360) - ($totalIorjitobugkritomonths * 30));

								// orjito ghor betone

								$orjitoGhorbetoneYear = floor($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] / 360);
								$orjitoGhorbetonemonths = floor(($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] - ($orjitoGhorbetoneYear * 360))/30);
								$orjitoGhorbetonedays = round($getTotalIorjitoFullLeaveQRW['totalOrjitofull'] - ($orjitoGhorbetoneYear * 360) - ($orjitoGhorbetonemonths * 30));


								$orjitoOrdhoGhorbetoneYear = floor($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] / 360);
								$orjitoOrdhoGhorbetoneMonths = floor(($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] - ($orjitoOrdhoGhorbetoneYear * 360))/30);
								$orjitoOrdhoGhorbetoneDays = round($getTotalIorjitoHalfLeaveQRW['totalOrjitohalf'] - ($orjitoOrdhoGhorbetoneYear * 360) - ($orjitoOrdhoGhorbetoneMonths * 30));


								
								$casualCurrentBalance = $casualBalance - ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $calculateCLAvgQRW['totalCLDeduction']);

								$casualSpent = ($getTotalLCasualLeaveInCurrentYearQRW['totalLCasualSpent'] + $calculateCLAvgQRW['totalCLDeduction']);


								// optional leave

								$optionalLeaveCurrentBalance = $optionalLBalance - ($getTotalOptionalLeaveQRW['totalOLSpent'] + $optionalLHistoryQRW['totalOLDeduction']);

								$optionalLeaveSpent = ($getTotalOptionalLeaveQRW['totalOLSpent'] + $optionalLHistoryQRW['totalOLDeduction']);

								// end of optional leave

	


	// end of leave calculation

	


	$insertLeaveApplicationQ = mysqli_query($con, "update leave_applications set applicationType='$applicationType', isinformed='$isinformedValue', leaveType='$leaveType', dateFrom='$dateFrom', dateTo='$dateTo', leaveApplication='$leaveApplication', attachment='$leaveFile', applicantID='$employeeID', updatedBy='$createdBy', lastUpdate='$submitDate', signature='$signature', applicationTo='$applicationTo', subject='$subject', onbehalf='$onbehalf', fullSalaryYear='$fullAvgRestSalLeaveyears', fullSalaryMonth='$fullAvgRestSalLeavemonths', fullSalaryDay='$fullAvgRestSalLeavedays', halfSalaryYear='$halfAvgRestSalLeaveyears', halfSalaryMonth='$halfAvgRestSalLeavemonths', halfSalaryDay='$halfAvgRestSalLeavedays', casual='$casualCurrentBalance', optionalLBalance='$optionalLeaveCurrentBalance' where dataID='$dataID'") or die(mysqli_error($con));


	if($insertLeaveApplicationQ == 1){

		$leaveApplicationID = $dataID;

		// remove prev signatory data
		$removePrevSigQ = mysqli_query($con, "delete from leave_data_for_approval where leaveApplicationID='$dataID'");


		if($removePrevSigQ == 1){


			$prevSignatory = 0;

		$getSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` order by approvalSL asc");	

		// insert first approval

		$insertForApprovalQbySupervisor = mysqli_query($con, "insert into leave_data_for_approval(leaveApplicationID, signatory, isSupervisor, prevSignatory, isApproved, serial) values('$leaveApplicationID', '$supervisorID','1', '$prevSignatory', '0', '1')");

		if($insertForApprovalQbySupervisor == 1){

			$prevSignatory = $supervisorID;

			// insert notification
			$getApplicantDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
			$getApplicantDetailsQRW = mysqli_fetch_assoc($getApplicantDetailsQ);

			$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getApplicantDetailsQRW[designation]'");
			$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

			$getSupervisorDetailsQ = mysqli_query($con, "select * from user_list where employee_id='$supervisorID'");
			$getSupervisorDetailsQNumRows = mysqli_num_rows($getSupervisorDetailsQ);

			if($getSupervisorDetailsQNumRows > 0){

				$getSupervisorDetailsQRW = mysqli_fetch_assoc($getSupervisorDetailsQ);

				$message = $getApplicantDetailsQRW['employee_name'].", ".$getDesignationDetailsQRW['job_title_name']." ছুটির জন্যে আবেদন করেছেন ।";

				$type= "<span class='badge badge-primary'>ছুটির সুপারিশ চেয়ে আবেদন</span>";

				$escapedType = mysqli_real_escape_string($con, $type);

				$link = "leave_application_details.php?menuslug=allowed-leave-applications&leaveApplicationID=".$leaveApplicationID;

				$dateTime = ShowBangladeshTime();

				$insertNotiQuery = mysqli_query($con, "insert into notification(userID, message, notificationType, link, dateTime, isImportant) values('$getSupervisorDetailsQRW[dataID]', '$message', '$escapedType', '$link', '$dateTime', 1)");
			
			} // end of notification
		
		}

		while($sigRow = mysqli_fetch_array($getSignatoryQ)){

			$insertForApprovalQ = mysqli_query($con, "insert into leave_data_for_approval(leaveApplicationID, signatory, prevSignatory, isApproved, serial) values('$leaveApplicationID', '$sigRow[employeeID]', '$prevSignatory', '0', '$sigRow[approvalSL]')");

			$prevSignatory = $sigRow['employeeID'];

		}

		if($applicationTo == 2){

			$getLastSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` order by approvalSL desc limit 0,1");
			$getLastSignatoryQRW = mysqli_fetch_assoc($getLastSignatoryQ);

			$newsigsl = $getLastSignatoryQRW['approvalSL'] + 1;

			$prevSignatorylast = $getLastSignatoryQRW['employeeID'];

			$getDGQ = mysqli_query($con, "select * from employee_list where designation=111 and employment_status=1");
			$getDGQRW = mysqli_fetch_assoc($getDGQ);


			$insertForApprovalQ = mysqli_query($con, "insert into leave_data_for_approval(leaveApplicationID, signatory, prevSignatory, isApproved, serial, isDG) values('$leaveApplicationID', '$getDGQRW[id]', '$prevSignatorylast', '0', '$newsigsl', 1)");

			


		
		}



		}

		echo "<div class='alert alert-success'><strong>Success!</strong> আপনার সংশোধিত ছুটির আবেদনটি অনুমোদনের জন্য যথাযথ কর্তৃপক্ষের কাছে প্রেরণ করা হয়েছে ।</div>";


	}else{
	
	echo 0;
	
	}


?>