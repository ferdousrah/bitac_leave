<?php
session_start();
include('connection.php');
include('bddate.php');
require_once __DIR__ . '/includes/signatory_route_helper.php';

$createdBy = $_SESSION['userID'];

try {
    // Start a transaction
    mysqli_begin_transaction($con, MYSQLI_TRANS_START_READ_WRITE);

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

$supervisorID = $_POST['supervisorID'];

$leaveType = $_POST['leaveType'];

$dateF = $_POST['leaveFrom'];

$dateFArray = explode('/', $dateF);

$dateFrom = $dateFArray[2].'-'.$dateFArray[1].'-'.$dateFArray[0];

$organization_id = $_POST['organization_id'];

// check for duplicate entry

$getDuplicateEntryQ = mysqli_query($con, "select * from leave_applications where applicantID='$employeeID' and dateFrom='$dateFrom' and (status=0 or status=1)");
$getDuplicateEntryQNumRows = mysqli_num_rows($getDuplicateEntryQ);

if($getDuplicateEntryQNumRows > 0){

exit("<div class='alert alert-danger'><strong>Duplicate entry!</strong></div>");

}



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
   $leaveFile = '';
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
		$leaveFile = '';
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

	


	$insertLeaveApplicationQ = mysqli_query($con, "insert into leave_applications(applicationType, isinformed, leaveType, dateFrom, dateTo, leaveApplication, attachment, applicantID, organization_id, submitBy, submitDate, submitTime, signature, applicationTo, subject, onbehalf, fullSalaryYear, fullSalaryMonth, fullSalaryDay, halfSalaryYear, halfSalaryMonth, halfSalaryDay, casual, optionalLBalance) values('$applicationType', '$isinformedValue', '$leaveType', '$dateFrom', '$dateTo', '$leaveApplication', '$leaveFile', '$employeeID', '$organization_id', '$createdBy', '$submitDate', '$submitTime', '$signature', '$applicationTo', '$subject', '$onbehalf', '$fullAvgRestSalLeaveyears', '$fullAvgRestSalLeavemonths', '$fullAvgRestSalLeavedays', '$halfAvgRestSalLeaveyears', '$halfAvgRestSalLeavemonths', '$halfAvgRestSalLeavedays', '$casualCurrentBalance', '$optionalLeaveCurrentBalance')") or die(mysqli_error($con));


	if($insertLeaveApplicationQ == 1){

		$leaveApplicationID = mysqli_insert_id($con);

		// Snapshot applicant's org info into leave_applications
		mysqli_query($con, "UPDATE leave_applications SET "
			. "department_id='" . (int)$getEmployeeInfoQRW['department_id'] . "', "
			. "section_id='" . (int)$getEmployeeInfoQRW['section_id'] . "', "
			. "designation_id='" . (int)$getEmployeeInfoQRW['designation'] . "', "
			. "pay_scale='" . mysqli_real_escape_string($con, $getEmployeeInfoQRW['pay_scale'] ?? '') . "' "
			. "WHERE dataID='$leaveApplicationID'");


		$prevSignatory = 0;

		$getSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` where organization_id='$organization_id' and isMandatory=1 order by approvalSL asc");	

		// insert first approval

		// Fetch supervisor's org snapshot
		$_supSnap = mysqli_fetch_assoc(mysqli_query($con, "SELECT organization_id, department_id, section_id, designation, pay_scale FROM employee_list WHERE id='$supervisorID' LIMIT 1"));
		$_sup_org   = (int)($_supSnap['organization_id'] ?? 0);
		$_sup_dept  = (int)($_supSnap['department_id'] ?? 0);
		$_sup_sec   = (int)($_supSnap['section_id'] ?? 0);
		$_sup_desig = (int)($_supSnap['designation'] ?? 0);
		$_sup_pay   = mysqli_real_escape_string($con, $_supSnap['pay_scale'] ?? '');
		$insertForApprovalQbySupervisor = mysqli_query($con, "insert into leave_data_for_approval(leaveApplicationID, signatory, isSupervisor, prevSignatory, isApproved, serial, organization_id, department_id, section_id, designation_id, pay_scale) values('$leaveApplicationID', '$supervisorID','1', '$prevSignatory', '0', '1', '$_sup_org', '$_sup_dept', '$_sup_sec', '$_sup_desig', '$_sup_pay')");

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

		// Build signatory chain using routing rules (grade + leave type based)
		// buildSignatoryChain() uses employeeID directly from leave_approval_signatory
		$chain = buildSignatoryChain($con, (int)$employeeID, (int)$leaveType);

		if (!empty($chain)) {
			// Route-based chain: each entry has employeeID already resolved
			$serialNum = 2;
			foreach ($chain as $sigEntry) {
				$sigEmpId = (int)$sigEntry['employeeID'];
				if (!$sigEmpId) { $serialNum++; continue; }
				// Fetch this signatory's org snapshot
				$_sigSnap = mysqli_fetch_assoc(mysqli_query($con, "SELECT organization_id, department_id, section_id, designation, pay_scale FROM employee_list WHERE id='$sigEmpId' LIMIT 1"));
				$_sig_org   = (int)($_sigSnap['organization_id'] ?? 0);
				$_sig_dept  = (int)($_sigSnap['department_id'] ?? 0);
				$_sig_sec   = (int)($_sigSnap['section_id'] ?? 0);
				$_sig_desig = (int)($_sigSnap['designation'] ?? 0);
				$_sig_pay   = mysqli_real_escape_string($con, $_sigSnap['pay_scale'] ?? '');
				mysqli_query($con, "INSERT INTO leave_data_for_approval
					(leaveApplicationID, signatory, prevSignatory, isApproved, serial, organization_id, department_id, section_id, designation_id, pay_scale)
					VALUES('$leaveApplicationID', '$sigEmpId', '$prevSignatory', '0', '$serialNum', '$_sig_org', '$_sig_dept', '$_sig_sec', '$_sig_desig', '$_sig_pay')");
				$prevSignatory = $sigEmpId;
				$serialNum++;
			}
		} else {
			// Fallback: no routing rules configured — use designation-based method
			$getSignatoryFallbackQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` WHERE organization_id='$organization_id' AND isMandatory=1 ORDER BY approvalSL ASC");
			while ($sigRow = mysqli_fetch_array($getSignatoryFallbackQ)) {
				$designatedSigQ = mysqli_query($con, "SELECT * FROM employee_list WHERE organization_id = '$sigRow[organization_id]' AND designation = '$sigRow[designationID]' AND employment_status=1");
				$designatedSigQRW = mysqli_fetch_assoc($designatedSigQ);
				$_fb_org   = (int)($designatedSigQRW['organization_id'] ?? 0);
			$_fb_dept  = (int)($designatedSigQRW['department_id'] ?? 0);
			$_fb_sec   = (int)($designatedSigQRW['section_id'] ?? 0);
			$_fb_desig = (int)($designatedSigQRW['designation'] ?? 0);
			$_fb_pay   = mysqli_real_escape_string($con, $designatedSigQRW['pay_scale'] ?? '');
			mysqli_query($con, "INSERT INTO leave_data_for_approval(leaveApplicationID, signatory, prevSignatory, isApproved, serial, organization_id, department_id, section_id, designation_id, pay_scale) VALUES('$leaveApplicationID', '$designatedSigQRW[id]', '$prevSignatory', '0', '$sigRow[approvalSL]', '$_fb_org', '$_fb_dept', '$_fb_sec', '$_fb_desig', '$_fb_pay')");
				$prevSignatory = $designatedSigQRW['id'];
			}

			if ($applicationTo == 2) {
				$getLastSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` WHERE organization_id='$organization_id' AND isMandatory=1 ORDER BY approvalSL DESC LIMIT 0,1");
				$getLastSignatoryQRW = mysqli_fetch_assoc($getLastSignatoryQ);
				$getLastSigQ = mysqli_query($con, "SELECT * FROM employee_list WHERE organization_id='$getLastSignatoryQRW[organization_id]' AND designation='$getLastSignatoryQRW[designationID]'");
				$getLastSigQRW = mysqli_fetch_assoc($getLastSigQ);
				$newsigsl = $getLastSignatoryQRW['approvalSL'] + 1;
				$prevSignatorylast = $getLastSigQRW['id'];
				$getDGQ = mysqli_query($con, "SELECT * FROM employee_list WHERE designation=111 AND employment_status=1");
				$getDGQRW = mysqli_fetch_assoc($getDGQ);
				$_dg_org   = (int)($getDGQRW['organization_id'] ?? 0);
				$_dg_dept  = (int)($getDGQRW['department_id'] ?? 0);
				$_dg_sec   = (int)($getDGQRW['section_id'] ?? 0);
				$_dg_desig = (int)($getDGQRW['designation'] ?? 0);
				$_dg_pay   = mysqli_real_escape_string($con, $getDGQRW['pay_scale'] ?? '');
				mysqli_query($con, "INSERT INTO leave_data_for_approval(leaveApplicationID, signatory, prevSignatory, isApproved, serial, isDG, organization_id, department_id, section_id, designation_id, pay_scale) VALUES('$leaveApplicationID', '$getDGQRW[id]', '$prevSignatorylast', '0', '$newsigsl', 1, '$_dg_org', '$_dg_dept', '$_dg_sec', '$_dg_desig', '$_dg_pay')");
			}
		}

		echo "<div class='alert alert-success'><strong>Success!</strong> আপনার ছুটির আবেদনটি অনুমোদনের জন্য যথাযথ কর্তৃপক্ষের কাছে প্রেরণ করা হয়েছে ।</div>";

		// Commit transaction
        mysqli_commit($con);


	}else{
	
	echo 0;
	
	}


} catch (Exception $e) {
    // Rollback on failure
    mysqli_rollback($con);
    echo "<div class='alert alert-danger'><strong>Error:</strong> " . $e->getMessage() . "</div>";
}



?>