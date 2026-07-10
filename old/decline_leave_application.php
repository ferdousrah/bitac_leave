<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$signature = $con -> real_escape_string($getUserDetailsQRW['signature']);

$dataID = $_POST['dataID'];

$isApproved = 2;

$leaveApplicationID = $_POST['leaveApplicationID'];

$note = $_POST['note'];

$loggedUserID = $_SESSION['employeeID'];

$officeNoticeDate = todayDate();

$updateSignature = mysqli_query($con, "update leave_data_for_approval set isApproved='$isApproved', approvedDays='0', note='$note', signature='$signature' where dataID='$dataID' and signatory='$loggedUserID'");

if($updateSignature == 1){

	$year = date('Y');

	$month = date('m');

	$insertOfficeNoticeQ = mysqli_query($con, "insert into office_notice_record(year, month, noticeType, leaveApplicationID) values('$year', '$month', 1, '$leaveApplicationID')");
		
	if($insertOfficeNoticeQ == 1){

			$officeNoticeNumberFormatted = mysqli_insert_id($con);

		}else{ $officeNoticeNumberFormatted = ""; }

	$updateSignatureForPendingApp = mysqli_query($con, "update leave_data_for_approval set isApproved='$isApproved', approvedDays='0' where leaveApplicationID='$leaveApplicationID' and isApproved=0");

	$updateLeaveJoiningApplicationQ = mysqli_query($con, "update leave_applications set status='$isApproved', cancellationReasion='$note', cancellationDate='$officeNoticeDate', declinedBy='$loggedUserID', officeNoticeNumber='$officeNoticeNumberFormatted' where dataID='$leaveApplicationID'");


	if($updateLeaveJoiningApplicationQ == 1){

		echo 1;
	
	
	}else{
	
		echo 0;
	
	}


}else{

	echo 0;

}


?>