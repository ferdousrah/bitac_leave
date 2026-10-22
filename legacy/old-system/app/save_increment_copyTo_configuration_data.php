<?php
include('connection.php');

$employeeID = $_POST['employeeID'];


// insert copy to

	$prevCopyDataClearQ = mysqli_query($con, "delete from `salary_notice_copy` where refFor='$employeeID'");
	$sl = 0;
	foreach($_POST['copyTo'] as $copyTo){

		$serial = $_POST['serial'][$sl];

		$insertCopyToQ = mysqli_query($con, "insert into `salary_notice_copy`(employeeID, refFor, serial) values('$copyTo', '$employeeID', '$serial')");
	
	$sl++;
	
	}

echo 1;


?>