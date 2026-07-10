<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$username = $_SESSION['username'];

$leaveApplicationID = $_POST['leaveApplicationID'];

$ptype = $_POST['ptype'];

$employeeID = $_SESSION['employeeID'];

$dataID = $_POST['dataID'];


if($_SESSION['username'] == 'Saifullah' || $_SESSION['username'] == 'saifullah'){


	


	$uodateLeaveEditDataApproval = mysqli_query($con, "update leave_edit_data_for_approval set isApproved='$ptype' where leaveApplicationID='$leaveApplicationID' and signatory='$employeeID'");

	if($uodateLeaveEditDataApproval){
	
		echo 1;

	}else{
	
		echo 0;
	
	}


}else if($_SESSION['username'] == '1697' || $_SESSION['username'] == '1661'){

	$uodateLeaveEditDataApproval = mysqli_query($con, "update leave_edit_data_for_approval set isApproved='$ptype' where leaveApplicationID='$leaveApplicationID' and signatory='$employeeID'");

	if($uodateLeaveEditDataApproval){
	
		$updateLeaveEditDataQ = mysqli_query($con, "update leave_edit_data set isApproved='$ptype' where leaveApplicationID='$leaveApplicationID' and dataID='$dataID'");

		if($updateLeaveEditDataQ && $ptype==1){

			$getLeaveEditDataQ = mysqli_query($con, "select * from leave_edit_data where leaveApplicationID='$leaveApplicationID' and dataID='$dataID'");
			$getLeaveEditDataQRW = mysqli_fetch_assoc($getLeaveEditDataQ);
		
			$updateLeaveDataQ = mysqli_query($con, "update leave_applications set approvedDays='$getLeaveEditDataQRW[revisedLeaveDay]', leaveTypeInTwo='$getLeaveEditDataQRW[deductFrom]', approvedLeaveType='$getLeaveEditDataQRW[leaveType]', approvedDateFrom='$getLeaveEditDataQRW[revisedLeaveFrom]', approvedDateTo='$getLeaveEditDataQRW[revisedLeaveTo]' where dataID='$leaveApplicationID'");

			if($updateLeaveDataQ){
	
					echo 1;

				}else{
				
					echo 0;
				
				}
		
		}
	
	}

}


?>