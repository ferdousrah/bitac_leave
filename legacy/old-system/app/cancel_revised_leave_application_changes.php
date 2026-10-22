<?php
include('connection.php');

$leaveApplicationID = $_GET['leaveApplicationID'];

$updateQ = mysqli_query($con, "update revised_leave_data_for_approval set isApproved=2 where leaveApplicationID='$leaveApplicationID'");

if($updateQ == 1){

		echo "<script>window.location='approve_revised_leaves?menuslug=approve-revised-leaves'</script>";

}else{

		echo "<script>alert('Error')</script>";
		
		echo "<script>window.location='approve_revised_leaves?menuslug=approve-revised-leaves'</script>";

}


?>