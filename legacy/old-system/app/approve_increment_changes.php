<?php
session_start();
include('connection.php');

$dataID = $_GET['dataID'];

$isApproved = $_GET['isApproved'];

$approvedBy = $_SESSION['userID'];


$getDataDetailsQ = mysqli_query($con, "select * from increment_data_update_permission where dataID='$dataID'");
$getDataDetailsQRW = mysqli_fetch_assoc($getDataDetailsQ);

if($isApproved == 1){

	$updateSalaryIncrementDataQ = mysqli_query($con, "update yearly_salary_increment set incrementAmount='$getDataDetailsQRW[salary_increment_rate_edited]', incrementSalary='$getDataDetailsQRW[salary_increment_basic_edited]' where incrementYear='$getDataDetailsQRW[incrementYear]' and employeeID='$getDataDetailsQRW[employeeID]'");

	if($updateSalaryIncrementDataQ == 1){

		$updateQ = mysqli_query($con, "update increment_data_update_permission set isApproved='$isApproved', approvedBy='$approvedBy' where dataID='$dataID'");

		if($updateQ == 1){

		echo "<script>alert('Success')</script>";
		echo "<script>window.location='approve_increment_data_changes?menuslug=approve-increment-data-changes'</script>";

		}else{
		
			echo "<script>alert('Error')</script>";
			echo "<script>window.location='approve_increment_data_changes?menuslug=approve-increment-data-changes'</script>";
		
		}
	
	
	}



}else{


	$updateQ = mysqli_query($con, "update increment_data_update_permission set isApproved='$isApproved' where dataID='$dataID'");

	if($updateQ == 1){

		echo "<script>alert('Success')</script>";
		echo "<script>window.location='approve_increment_data_changes?menuslug=approve-increment-data-changes'</script>";

	}else{
	
		echo "<script>alert('Error')</script>";
		echo "<script>window.location='approve_increment_data_changes?menuslug=approve-increment-data-changes'</script>";
	
	}



}




?>