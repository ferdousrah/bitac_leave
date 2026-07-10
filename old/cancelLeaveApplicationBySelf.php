<?php
include('connection.php');

$applicationID = $_POST['applicationID'];

$deleteApprovalDataQ = mysqli_query($con, "delete from leave_data_for_approval where leaveApplicationID='$applicationID'");

if($deleteApprovalDataQ == 1){

	$updateApplicationStatusQ = mysqli_query($con, "delete from leave_applications where dataID='$applicationID'");
	

	if($updateApplicationStatusQ == 1){

		echo 1;
	
	}else{
	
		echo 0;
	
	}


}else{

	echo 0;

}



?>