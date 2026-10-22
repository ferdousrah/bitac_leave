<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$submitDate = todayDate();

$incrementYear = date('Y');

//echo "Total Data: ".count($_POST['listedemp']);

foreach($_POST['listedemp'] as $empID){

	$getDataDetailsQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='$incrementYear' and employeeID='$empID'");
	$getDataDetailsQRW = mysqli_fetch_assoc($getDataDetailsQ);

	$prevSignatory = 0;

	$checkForDuplicateQ = mysqli_query($con, "select * from increment_data_for_approval where incrementYear='$incrementYear' and employeeID='$getDataDetailsQRW[employeeID]'");
	$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

	if($checkForDuplicateQNumRows > 0){
	
		$deleteDataQ = mysqli_query($con, "delete from increment_data_for_approval where incrementYear='$incrementYear' and employeeID='$getDataDetailsQRW[employeeID]'");
	
	}


	$getSignatoryQ = mysqli_query($con, "SELECT * FROM `salary_increment_approvals` order by approvalSL asc");	

	while($sigRow = mysqli_fetch_array($getSignatoryQ)){	

	$insertForApprovalQ = mysqli_query($con, "insert into increment_data_for_approval(dataRef, incrementYear, employeeID, signatory, prevSignatory, isApproved, serial) values('$getDataDetailsQRW[dataID]', '$incrementYear', '$getDataDetailsQRW[employeeID]', '$sigRow[employeeID]', '$prevSignatory', '0', '$sigRow[approvalSL]')");

	//echo "insert into increment_data_for_approval(dataRef, incrementYear, employeeID, signatory, prevSignatory, isApproved, serial) values('$getDataDetailsQRW[dataID]', '$incrementYear', '$getDataDetailsQRW[employeeID]', '$sigRow[employeeID]', '$prevSignatory', '0', '$sigRow[approvalSL]')";

	$prevSignatory = $sigRow['employeeID'];

	}

}


?>