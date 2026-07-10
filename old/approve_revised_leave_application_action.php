<?php
include('connection.php');

$leaveApplicationID = $_POST['leaveApplicationID'];

// date

$dateF = $_POST['leaveFrom'];

$dateFArray = explode('/', $dateF);

$dateFrom = $dateFArray[2].'-'.$dateFArray[1].'-'.$dateFArray[0];


$dateT = $_POST['leaveTo'];

$dateTArray = explode('/', $dateT);

$dateTo = $dateTArray[2].'-'.$dateTArray[1].'-'.$dateTArray[0];

//

$approvedDays = $_POST['approvedDays'];

$leaveTypeInTwo = $_POST['leaveTypeInTwo'];

$note = $_POST['note'];



if(!file_exists($_FILES['fileattachment']['tmp_name']) || !is_uploaded_file($_FILES['fileattachment']['tmp_name'])) {
   // echo 'No upload'; 
   $file_name = '';
}
else {

$file_name = $_FILES['fileattachment']['name'];
$file_size =$_FILES['fileattachment']['size'];
$file_tmp =$_FILES['fileattachment']['tmp_name'];
$file_type=$_FILES['fileattachment']['type'];
$file_extArray=explode('.',$_FILES['fileattachment']['name']);
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
		move_uploaded_file($file_tmp,"uploads/".$file_name);
		//echo "Success";
	}else{
		print_r($errors);
		$file_name = '';
	}
}


$prevSignatory = 0;

$getSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_edit_approval_signatory` order by approvalSL asc");

while($sigRow = mysqli_fetch_array($getSignatoryQ)){

			$insertForApprovalQ = mysqli_query($con, "insert into revised_leave_data_for_approval(leaveApplicationID, leaveFrom, leaveTo, approvedDays, leaveTypeInTwo, signatory, prevSignatory, isApproved, serial, adminNote, attachment) values('$leaveApplicationID', '$dateFrom', '$dateTo', '$approvedDays', '$leaveTypeInTwo', '$sigRow[employeeID]', '$prevSignatory', '0', '$sigRow[approvalSL]', '$note', '$file_name')");

			$prevSignatory = $sigRow['employeeID'];

		}

echo 1;


?>