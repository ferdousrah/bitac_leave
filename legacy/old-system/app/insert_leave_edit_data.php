<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$submitDate = todayDate();

$submitTime = logTime();

$sDateTime = $submitDate." ".$submitTime;

$todayDate = todayDate();

$leaveApplicationID = $_POST['leaveApplicationID'];

$dateF = $_POST['leaveFrom'];

$dateFArray = explode('/', $dateF);

$revisedLeaveFrom = $dateFArray[2].'-'.$dateFArray[1].'-'.$dateFArray[0];


$dateT = $_POST['leaveTo'];

$dateTArray = explode('/', $dateT);

$revisedLeaveTo = $dateTArray[2].'-'.$dateTArray[1].'-'.$dateTArray[0];


$revisedLeaveDay = $_POST['approvedDays'];

$leaveType = $_POST['approvedLeaveType'];

$deductFrom = $_POST['leaveTypeInTwo'];

$applicantID = $_POST['applicantID'];

$organization_id = $_POST['organization_id'];


if(!file_exists($_FILES['leaveFile']['tmp_name']) || !is_uploaded_file($_FILES['leaveFile']['tmp_name'])) {
   // echo 'No upload'; 
   $leaveFile = '';
}
else {

$leaveFile = $_FILES['leaveFile']['name'];
$file_size =$_FILES['leaveFile']['size'];
$file_tmp =$_FILES['leaveFile']['tmp_name'];
$file_type=$_FILES['leaveFile']['type'];
$file_extArray=explode('.',$_FILES['leaveFile']['name']);
//echo $file_extArray[1];
$file_ext = strtolower($file_extArray[1]);	  

$extensions= array("jpeg","jpg","png","pdf");
      
if(in_array($file_ext,$extensions)== false){
	$errors[]="extension not allowed, please choose a JPEG or PNG file.";
}
      
	if($file_size > 2097152){
		$errors[]='File size must be excately 2 MB';
	}
      
	if(empty($errors)==true){
		move_uploaded_file($file_tmp,"uploads/".$leaveFile);
		//echo "Success";
	}else{
		print_r($errors);
		$leaveFile = '';
	}
}


$checkForExistingDataQ = mysqli_query($con, "select * from `leave_edit_data` where `leaveApplicationID`='$leaveApplicationID' and isApproved=0");
$checkForExistingDataQNumRows = mysqli_num_rows($checkForExistingDataQ);


if($checkForExistingDataQNumRows <= 0){

$insertUpdateDataQ = mysqli_query($con, "INSERT INTO `leave_edit_data` (`dataID`, `applicantID`, `leaveApplicationID`, `revisedLeaveFrom`, `revisedLeaveTo`, `revisedLeaveDay`, `leaveType`, `deductFrom`, `attachment`, `isApproved`, `submitDateTime`) VALUES (NULL, '$applicantID', '$leaveApplicationID', '$revisedLeaveFrom', '$revisedLeaveTo', '$revisedLeaveDay', '$leaveType', '$deductFrom', '$leaveFile', '0', '$sDateTime')");

if($insertUpdateDataQ == 1){

	$getApprovalSignatoryQ = mysqli_query($con, "select * from leave_approval_signatory where isMandatory=1 and organization_id='$organization_id' order by approvalSL asc");

	//$getSignatoryQ = mysqli_query($con, "select * from leave_edit_approval_signatory order by approvalSL asc");

	$prevSignatory = 0;

	while($sigRow = mysqli_fetch_array($getApprovalSignatoryQ)){

		$getSigDetailsQ = mysqli_query($con, "select * from employee_list where designation='$sigRow[designationID]' and organization_id='$organization_id' and employment_status=1");
		$getSigDetailsQRW = mysqli_fetch_assoc($getSigDetailsQ);
	
		$insertApprovalDataQ = mysqli_query($con, "insert into leave_edit_data_for_approval(leaveApplicationID, signatory, prevSignatory, serial) values('$leaveApplicationID', '$getSigDetailsQRW[id]', '$prevSignatory', '$sigRow[approvalSL]')");

		//echo "insert into leave_edit_data_for_approval(leaveApplicationID, signatory, prevSignatory, serial) values('$leaveApplicationID', '$sigRow[employeeID]', '$prevSignatory', '$sigRow[approvalSL]')";

		$prevSignatory = $getSigDetailsQRW['id'];
	
	}




	echo 1;




}else{

	echo "Error!";

}

}else{

echo "Duplicate Data!";


}

?>