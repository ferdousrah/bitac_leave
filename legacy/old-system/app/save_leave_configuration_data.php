<?php
include('connection.php');

$prevDataClearQ = mysqli_query($con, "delete from `leave_approval_signatory`");

	$sl = 0;
	foreach($_POST['signatory'] as $signatory){

		$serial = $_POST['serial'][$sl];

		$insertSignatoryQ = mysqli_query($con, "insert into `leave_approval_signatory`(`approvalSL`,`employeeID`) values('$serial', '$signatory')");
	
	
		$sl++;
	}


	// edit
	$prevDataClearQ = mysqli_query($con, "delete from `leave_edit_approval_signatory`");

	$sl = 0;
	foreach($_POST['editsignatory'] as $esignatory){

		$serial = $_POST['serial'][$sl];

		$insertSignatoryQ = mysqli_query($con, "insert into `leave_edit_approval_signatory`(`approvalSL`,`employeeID`) values('$serial', '$esignatory')");
	
	
		$sl++;
	}

echo 1;

?>