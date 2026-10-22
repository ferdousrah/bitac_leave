<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$signature = $con -> real_escape_string($getUserDetailsQRW['signature']);

$dataID = $_POST['dataID'];

$isApproved = $_POST['isApproved'];

$leaveApplicationID = $_POST['leaveApplicationID'];

$approvedDays = $_POST['approvedDays'];

$isValid = $_POST['isValid'];



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


if($isValid == 1){

	$updateSignature = mysqli_query($con, "update leave_joining_data_for_approval set isApproved=1, approvedDays='$approvedDays', signature='$signature', approvedDate='$officeNoticeDate' where leaveApplicationID='$leaveApplicationID'");

	if($updateSignature == 1){

		$updateIncrementDataQ = mysqli_query($con, "update leave_joining_application set requestedJoiningDate='$dateTo', approvedLeaveType='$leaveTypeInTwo', status=1 where leaveApplicationID='$leaveApplicationID'");
		//echo "<script>alert('Success')</script>";
		//echo "<script>window.location='leave_approval?menuslug=leave-approval'</script>";

		echo 1;
	
	
	}


}else{


$note = $_POST['note'];




} // end of else






?>