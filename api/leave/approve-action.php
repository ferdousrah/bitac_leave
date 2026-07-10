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


if(isset($_POST['leaveTypeInTwo'])){

$leaveTypeInTwo = $_POST['leaveTypeInTwo'];

}else{

$leaveTypeInTwo = 0;

}
$loggedUserID = $_SESSION['employeeID'];

$leaveType = $_POST['approvedLeaveType'];

// Audit: intent-level log. isApproved=1 → approve, 2 → reject/decline.
// Logged early so we capture the attempt even if downstream branches fail.
if (function_exists('audit_log')) {
    $auditAction = ((int)$isApproved === 1) ? 'leave_approved'
                  : (((int)$isApproved === 2) ? 'leave_rejected' : 'leave_decision');
    audit_log($auditAction, [
        'target_type' => 'leave_application',
        'target_id'   => (int)$leaveApplicationID,
        'note'        => 'approval row dataID=' . (int)$dataID . '; days=' . ($approvedDays ?? '') . (isset($_POST['forwardTo']) && $_POST['forwardTo'] !== '' ? '; forwarded_to=' . $_POST['forwardTo'] : ''),
    ]);
}

// forwardTo

if(isset($_POST['forwardTo']) && $_POST['forwardTo'] != ''){

	$forwardTo = $_POST['forwardTo'];

	//$deleteMohsinQ = mysqli_query($con, "delete from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory=816 and serial=3");

	if($createdBy == 91){

		$updateSignatoryQ = mysqli_query($con, "update leave_data_for_approval set signatory='$forwardTo' where leaveApplicationID='$leaveApplicationID' and serial=4");

	}else if($createdBy == 88){

		$updateSignatoryQ = mysqli_query($con, "update leave_data_for_approval set signatory='$forwardTo' where leaveApplicationID='$leaveApplicationID' and serial=3");

	}

}


$checkLastManSigQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and isSupervisor=0 and isSentbyAdmin=1 order by serial desc limit 0,1");
$checkLastManSigQRW = mysqli_fetch_assoc($checkLastManSigQ);


// date

$dateF = $_POST['leaveFrom'];

$dateFArray = explode('/', $dateF);

$dateFrom = $dateFArray[2].'-'.$dateFArray[1].'-'.$dateFArray[0];


$dateT = $_POST['leaveTo'];

$dateTArray = explode('/', $dateT);

$dateTo = $dateTArray[2].'-'.$dateTArray[1].'-'.$dateTArray[0];


// Use full DATETIME so events on the same day still sort chronologically in
// the previous-comments thread. approvedDate column is varchar(40); strtotime
// + date('d/m/Y', …) used by display code parses DATETIME just as well as DATE.
$officeNoticeDate = date('Y-m-d H:i:s');


//......

$updateApplicationQ = mysqli_query($con, "update leave_applications set approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', approvedLeaveType='$leaveType', leaveTypeInTwo='$leaveTypeInTwo' where dataID='$leaveApplicationID'");

if(isset($_POST['forwardTo']) && $_POST['forwardTo'] != ''){

	$forwardTo = $_POST['forwardTo'];

	$getPrevSigserialQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID'");
	$getPrevSigserialQRW = mysqli_fetch_assoc($getPrevSigserialQ);

	$newSerial = $getPrevSigserialQRW['serial'] + 1;

	$insertForApprovalQbySupervisor = mysqli_query($con, "insert into leave_data_for_approval(leaveApplicationID, signatory, isSupervisor, prevSignatory, isApproved, serial, isSentbyAdmin, isForwarded, approvedDate) values('$leaveApplicationID', '$forwardTo','0', '$loggedUserID', '0', '$newSerial', 1, 1, '$officeNoticeDate')");

	$lasrSig = $forwardTo;

}else{

	$lasrSig = $checkLastManSigQRW['signatory'];

}




$checkforSupervisorQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID'");
$checkforSupervisorQNumRows = mysqli_num_rows($checkforSupervisorQ);
$checkforSupervisorQRW = mysqli_fetch_assoc($checkforSupervisorQ);


//echo "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID' and serial=1"; , '$officeNoticeDate'

if($isApproved == 1 && $loggedUserID == $lasrSig){

	$updateSignature = mysqli_query($con, "update leave_data_for_approval set isApproved=1, approvedDays='$approvedDays', note='$note', signature='$signature', approvedDate='$officeNoticeDate' where dataID='$dataID' and signatory='$loggedUserID'");

	if($updateSignature == 1){

		$year = date('Y');

		$month = date('m');

		$insertOfficeNoticeQ = mysqli_query($con, "insert into office_notice_record(year, month, noticeType, leaveApplicationID) values('$year', '$month', 1, '$leaveApplicationID')");
		
		if($insertOfficeNoticeQ == 1){

			$officeNoticeNumberFormatted = mysqli_insert_id($con);

		}else{ $officeNoticeNumberFormatted = ""; }

		$joiningDateAfterLeave = date('Y-m-d', strtotime($dateTo. ' + 1 days'));

		$updateIncrementDataQ = mysqli_query($con, "update leave_applications set status=1, approvedDays='$approvedDays', approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', officeNoticeDate='$officeNoticeDate', officeNoticeNumber='$officeNoticeNumberFormatted', primaryLeaveDateFrom='$dateFrom', primaryLeaveDateTo='$dateTo', primaryApprovedLeaveDays='$approvedDays', joiningDateAfterLeave='$joiningDateAfterLeave', leaveTypeInTwo='$leaveTypeInTwo', primaryApprovedLeaveType='$leaveTypeInTwo' where dataID='$leaveApplicationID'");

		// get copy to employees
		$getCopyToEmpQ = mysqli_query($con, "select * from leave_notice_copy where applicationID='$leaveApplicationID' order by serial asc");

		while($cRow = mysqli_fetch_array($getCopyToEmpQ)){

			$getUserIDQ = mysqli_query($con, "select * from user_list where employee_id='$cRow[employeeID]'");
			$getUserIDQNumRows = mysqli_num_rows($getUserIDQ);

			if($getUserIDQNumRows > 0){

				$getUserIDQRW = mysqli_fetch_assoc($getUserIDQ);

				$notimsg = "ছুটি সংক্রান্ত অফিস আদেশ , অনুলিপি";
				$notilink = "leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=".$leaveApplicationID;

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
			$notilink = "leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=".$leaveApplicationID;

			$dateTime = ShowBangladeshTime();

			$insertNotificationQ = mysqli_query($con, "insert into notification(userID, message, link, dateTime) values('$getApplicantUserDetailsQRW[dataID]', '$notimsg', '$notilink', '$dateTime')");
		
		
		
		}


		echo 1;
	
	
	}


}else if($isApproved == 1 && $loggedUserID != $lasrSig){


	$updateSignature = mysqli_query($con, "update leave_data_for_approval set isApproved=1, note='$note', approvedDays='$approvedDays', signature='$signature', approvedDate='$officeNoticeDate' where dataID='$dataID' and signatory='$loggedUserID'");

	if($updateSignature == 1){

		$updateIncrementDataQ = mysqli_query($con, "update leave_applications set approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', leaveTypeInTwo='$leaveTypeInTwo' where dataID='$leaveApplicationID'");
		//echo "<script>alert('Success')</script>";
		//echo "<script>window.location='leave_approval?menuslug=leave-approval'</script>";

		echo 1;
	
	
	}


}

?>