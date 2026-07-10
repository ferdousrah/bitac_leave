<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$signature = $con -> real_escape_string($getUserDetailsQRW['signature']);

$dataID = $_POST['dataID'];

$isApproved = $_POST['isApproved'];

$leaveApplicationID = $_POST['leaveApplicationID'];

$approvedDays = $_POST['approvedDays'];

$note = $_POST['note'];

//$leaveType = $_POST['leaveType'];

$leaveTypeInTwo = $_POST['leaveTypeInTwo'];

$loggedUserID = $_SESSION['employeeID'];

$checkLastManSigQ = mysqli_query($con, "select * from leave_joining_data_for_approval where leaveApplicationID='$leaveApplicationID' and isSupervisor=0 and isSentbyAdmin=1 order by serial desc limit 0,1");
$checkLastManSigQRW = mysqli_fetch_assoc($checkLastManSigQ);


// date

$dateF = $_POST['spentLeaveFrom'];

$dateFArray = explode('/', $dateF);

$dateFrom = $dateFArray[2].'-'.$dateFArray[1].'-'.$dateFArray[0];


$dateT = $_POST['spentLeaveTo'];

$dateTArray = explode('/', $dateT);

$dateTo = $dateTArray[2].'-'.$dateTArray[1].'-'.$dateTArray[0];


$officeNoticeDate = todayDate();


//......

//$updateApplicationQ = mysqli_query($con, "update leave_applications set approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', approvedLeaveType='$leaveType', leaveTypeInTwo='$leaveTypeInTwo', adminNote='$note' where dataID='$leaveApplicationID'");

if(isset($_POST['forwardTo']) && $_POST['forwardTo'] != ''){

	$forwardTo = $_POST['forwardTo'];

	$getPrevSigserialQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID'");
	$getPrevSigserialQRW = mysqli_fetch_assoc($getPrevSigserialQ);

	$newSerial = $getPrevSigserialQRW['serial'] + 1;

	$insertForApprovalQbySupervisor = mysqli_query($con, "insert into leave_data_for_approval(leaveApplicationID, signatory, isSupervisor, prevSignatory, isApproved, serial, isSentbyAdmin, isForwarded) values('$leaveApplicationID', '$forwardTo','0', '$loggedUserID', '0', '$newSerial', 1, 1)");

	$lasrSig = $forwardTo;

}else{

	$lasrSig = $checkLastManSigQRW['signatory'];

}




$checkforSupervisorQ = mysqli_query($con, "select * from leave_joining_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID'");
$checkforSupervisorQNumRows = mysqli_num_rows($checkforSupervisorQ);
$checkforSupervisorQRW = mysqli_fetch_assoc($checkforSupervisorQ);


//echo "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID' and serial=1";

if($isApproved == 1 && $loggedUserID == $lasrSig){

	$year = date('Y');

	$month = date('m');

	$insertOfficeNoticeQ = mysqli_query($con, "insert into office_notice_record(year, month, noticeType, leaveApplicationID) values('$year', '$month', 2, '$leaveApplicationID')");
		
	if($insertOfficeNoticeQ == 1){

			$officeNoticeNumberFormatted = mysqli_insert_id($con);

		}else{ $officeNoticeNumberFormatted = ""; }

	$updateSignature = mysqli_query($con, "update leave_joining_data_for_approval set isApproved=1, approvedDays='$approvedDays', note='$note', signature='$signature', approvedDate='$officeNoticeDate' where dataID='$dataID' and signatory='$loggedUserID'");

	if($updateSignature == 1){

		$updateIncrementDataQ = mysqli_query($con, "update leave_applications set approvedDays='$approvedDays', leaveTypeInTwo='$leaveTypeInTwo', approvedDateFrom='$dateFrom', approvedDateTo='$dateTo' where dataID='$leaveApplicationID'");

		$updateLeaveJoiningApplicationQ = mysqli_query($con, "update leave_joining_application set status=1, approvedDate='$officeNoticeDate', requestedJoiningDate='$dateTo', approvedLeaveType='$leaveTypeInTwo', officeNoticeNumber='$officeNoticeNumberFormatted' where leaveApplicationID='$leaveApplicationID'");

		// get copy to employees
		$getCopyToEmpQ = mysqli_query($con, "select * from leave_notice_copy where applicationID='$leaveApplicationID' order by serial asc");

		while($cRow = mysqli_fetch_array($getCopyToEmpQ)){

			$getUserIDQ = mysqli_query($con, "select * from user_list where employee_id='$cRow[employeeID]'");
			$getUserIDQNumRows = mysqli_num_rows($getUserIDQ);

			if($getUserIDQNumRows > 0){

				$getUserIDQRW = mysqli_fetch_assoc($getUserIDQ);

				$notimsg = "ছুটি সংক্রান্ত অফিস আদেশ , অনুলিপি";
				$notilink = "";

				$dateTime = ShowBangladeshTime();

				$insertNotificationQ = mysqli_query($con, "insert into notification(userID, message, link, dateTime) values('$getUserIDQRW[dataID]', '$notimsg', '$notilink', '$dateTime')");
			
			
			}
		
		
		}

		// copy to applicant
		$getApplicantQ = mysqli_query($con, "select * from leave_applications where dataID='$leaveApplicationID'");
		$getApplicantQRW = mysqli_fetch_assoc($getApplicantQ);

		$getApplicantUserDetailsQ = mysqli_query($con, "select * from user_list where employee_id='$getApplicantQRW[applicantID]'");
		$getApplicantUserDetailsQNumRows = mysqli_num_rows($getApplicantUserDetailsQ);

		if($getApplicantUserDetailsQNumRows > 0){

			$getApplicantUserDetailsQRW = mysqli_fetch_assoc($getApplicantUserDetailsQ);

			$notimsg = "ছুটি সংক্রান্ত অফিস আদেশ , অনুলিপি";
			$notilink = "";

			$dateTime = ShowBangladeshTime();

			$insertNotificationQ = mysqli_query($con, "insert into notification(userID, message, link, dateTime) values('$getApplicantUserDetailsQRW[dataID]', '$notimsg', '$notilink', '$dateTime')");
		
		
		
		}


		echo 1;
	
	
	}


}else if($isApproved == 1 && $loggedUserID != $lasrSig){


	$updateSignature = mysqli_query($con, "update leave_joining_data_for_approval set isApproved=1, note='$note', approvedDays='$approvedDays', signature='$signature', approvedDate='$officeNoticeDate' where dataID='$dataID' and signatory='$loggedUserID'");

	if($updateSignature == 1){

		$updateIncrementDataQ = mysqli_query($con, "update leave_joining_application set requestedJoiningDate='$dateTo', approvedLeaveType='$leaveTypeInTwo' where leaveApplicationID='$leaveApplicationID'");
		//echo "<script>alert('Success')</script>";
		//echo "<script>window.location='leave_approval?menuslug=leave-approval'</script>";

		echo 1;
	
	
	}


}

?>