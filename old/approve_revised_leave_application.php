<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$signature = $con -> real_escape_string($getUserDetailsQRW['signature']);

$dataID = $_GET['dataID'];

$isApproved = $_GET['isApproved'];

$leaveApplicationID = $_GET['leaveApplicationID'];



$loggedUserID = $_SESSION['employeeID'];

$checkLastManSigQ = mysqli_query($con, "select * from revised_leave_data_for_approval where leaveApplicationID='$leaveApplicationID' order by serial desc limit 0,1");
$checkLastManSigQRW = mysqli_fetch_assoc($checkLastManSigQ);


$lasrSig = $checkLastManSigQRW['signatory'];



$checkforLApQ = mysqli_query($con, "select * from revised_leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID'");
$checkforLApQRW = mysqli_num_rows($checkforLApQ);



//echo "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$loggedUserID' and serial=1";

if($isApproved == 1 && $loggedUserID == $lasrSig){

	$updateSignature = mysqli_query($con, "update revised_leave_data_for_approval set isApproved=1, signature='$signature' where dataID='$dataID' and signatory='$loggedUserID'");

	if($updateSignature == 1){

		$updateIncrementDataQ = mysqli_query($con, "update leave_applications set approvedDays='$checkforLApQRW[approvedDays]', leaveTypeInTwo='$checkforLApQRW[leaveTypeInTwo]', approvedDateFrom='$checkforLApQRW[leaveFrom]', approvedDateTo='$checkforLApQRW[leaveTo]' where dataID='$leaveApplicationID'");

		if($updateIncrementDataQ == 1){
		
			echo "<script>window.location='approve_revised_leaves?menuslug=approve-revised-leaves'</script>";
		
		}else{

			echo "<script>alert('Error')</script>";
		
			echo "<script>window.location='approve_revised_leaves?menuslug=approve-revised-leaves'</script>";
		
		}
		
	
	}


}else if($isApproved == 1 && $loggedUserID != $lasrSig){


	$updateSignature = mysqli_query($con, "update revised_leave_data_for_approval set isApproved=1 where dataID='$dataID' and signatory='$loggedUserID'");

	if($updateSignature == 1){

		//$updateIncrementDataQ = mysqli_query($con, "update leave_applications set approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', leaveTypeInTwo='$leaveTypeInTwo', approvedDateFrom='$dateFrom', approvedDateTo='$dateTo' where dataID='$leaveApplicationID'");
		//echo "<script>alert('Success')</script>";
		//echo "<script>window.location='leave_approval?menuslug=leave-approval'</script>";

		echo "<script>window.location='approve_revised_leaves?menuslug=approve-revised-leaves'</script>";
	
	
	}else{
	
		echo "<script>alert('Error')</script>";
		
		echo "<script>window.location='approve_revised_leaves?menuslug=approve-revised-leaves'</script>";
	
	}


}

?>