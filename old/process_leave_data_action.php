<?php
include('connection.php');

$leaveApplicationID = $_POST['leaveApplicationID'];

$ptype = $_POST['ptype'];


if($ptype == 1){


	$getLeaveEditDetailsQ = mysqli_query($con, "select * from leave_edit_data where leaveApplicationID='$leaveApplicationID'");
	$getLeaveEditDetailsQRW = mysqli_fetch_assoc($getLeaveEditDetailsQ);


	$updateLeaveDataQ = mysqli_query($con, "update leave_applications set leaveTypeInTwo='$getLeaveEditDetailsQRW[approvedLeaveType]', approvedDateFrom='$getLeaveEditDetailsQRW[approvedFrom]', approvedDateTo='$getLeaveEditDetailsQRW[approvedTo]', approvedDays='$getLeaveEditDetailsQRW[approvedDays]', primaryLeaveDateFrom='$getLeaveEditDetailsQRW[approvedFrom]', primaryLeaveDateTo='$getLeaveEditDetailsQRW[approvedTo]', primaryApprovedLeaveDays='$getLeaveEditDetailsQRW[approvedDays]' where dataID='$leaveApplicationID'");

	if($updateLeaveDataQ == 1){
	
		echo 1;

	}else{
	
		echo 0;
	
	}



}else if($ptype == 2){


	$updateLeaveEditQ = mysqli_query($con, "update leave_edit_data set isApproved=2 where leaveApplicationID='$leaveApplicationID'");

	if($updateLeaveEditQ == 1){
	
		echo 1;

	}else{
	
		echo 0;
	
	}



}else if($ptype == 3){



	$updateLeaveEditQ = mysqli_query($con, "delete from leave_edit_data where leaveApplicationID='$leaveApplicationID'");

	if($updateLeaveEditQ == 1){
	
		echo 1;

	}else{
	
		echo 0;
	
	}



}



?>