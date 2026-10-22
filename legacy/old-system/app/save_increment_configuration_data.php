<?php
include('connection.php');

$salary_increment_date = $_POST['salary_increment_date'];

$applicationTo = $_POST['applicationTo'];

$fileNo = $_POST['fileNo'];

$copyTo = 0;

$notice_content = $_POST['notice_content'];

$updateQ = mysqli_query($con, "update increment_settings set salary_increment_date='$salary_increment_date', applicationTo='$applicationTo', fileNo='$fileNo', copyTo='$copyTo', notice_content='$notice_content' where dataID=1");

if($updateQ == 1){

	$prevDataClearQ = mysqli_query($con, "delete from `salary_increment_approvals`");

	$sl = 0;
	foreach($_POST['signatory'] as $signatory){

		$serial = $_POST['serial'][$sl];

		$insertSignatoryQ = mysqli_query($con, "insert into `salary_increment_approvals`(`employeeID`, `approvalSL`) values('$signatory', '$serial')");
	
	
		$sl++;
	}

	// insert copy to

	$prevCopyDataClearQ = mysqli_query($con, "truncate table `salary_notice_copy`");

	foreach($_POST['copyTo'] as $copyTo){

		$insertCopyToQ = mysqli_query($con, "insert into `salary_notice_copy`(employeeID) values('$copyTo')");
	
	
	}

	echo 1;

}else{ echo 0; }


?>