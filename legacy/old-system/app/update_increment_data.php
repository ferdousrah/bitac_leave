<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$submitDate = todayDate();

$incrementYear = date('Y');

$employeeID = $_POST['employeeID'];

$incrementSalaryGrade = $_POST['incrementSalaryGrade'];

$salary_increment_rate_current = $_POST['salary_increment_rate_current'];

$salary_increment_basic_current = $_POST['salary_increment_basic_current'];

$incrementAmount = $_POST['incrementAmount'];

$incrementSalary = $_POST['incrementSalary'];

$note = $_POST['note'];


if(!file_exists($_FILES['office_notice']['tmp_name']) || !is_uploaded_file($_FILES['office_notice']['tmp_name'])) {
   // echo 'No upload'; 
   $file_name = $_POST['prevFile'];
}
else {

$file_name = $_FILES['office_notice']['name'];
$file_size =$_FILES['office_notice']['size'];
$file_tmp =$_FILES['office_notice']['tmp_name'];
$file_type=$_FILES['office_notice']['type'];
$file_extArray=explode('.',$_FILES['office_notice']['name']);
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
		$file_name = $_POST['prevFile'];
	}
}



$checkForPreviousDataQ = mysqli_query($con, "select * from increment_data_update_permission where incrementYear='$incrementYear' and employeeID='$employeeID'");
$checkForPreviousDataQNumRows = mysqli_num_rows($checkForPreviousDataQ);

if($checkForPreviousDataQNumRows > 0){


	$updateDataQ = mysqli_query($con, "update increment_data_update_permission set salary_increment_rate_current='$salary_increment_rate_current', salary_increment_rate_edited='$incrementAmount', salary_increment_basic_current='$salary_increment_basic_current', salary_increment_basic_edited='$incrementSalary', submitBy='$createdBy', submitDate='$submitDate', note='$note', office_notice='$file_name' where incrementYear='$incrementYear' and employeeID='$employeeID'");

	if($updateDataQ == 1){
	
	echo 1;

	}else{

		echo 0;

	}


}else{

	
	$insertRequestQ = mysqli_query($con, "insert into increment_data_update_permission(incrementYear, employeeID, salary_increment_rate_current, salary_increment_rate_edited, salary_increment_basic_current, salary_increment_basic_edited, submitBy, submitDate, note, office_notice) values('$incrementYear', '$employeeID', '$salary_increment_rate_current', '$incrementAmount', '$salary_increment_basic_current', '$incrementSalary', '$createdBy', '$submitDate', '$note', '$file_name')");

	//$updateIncrementDataQ = mysqli_query($con, "update yearly_salary_increment set incrementSalaryGrade='$incrementSalaryGrade', incrementAmount='$incrementAmount', incrementSalary='$incrementSalary' where employeeID='$employeeID' and incrementYear='$incrementYear'");

	if($insertRequestQ == 1){

			// get users who have approval

		$getusersforapprovalQ = mysqli_query($con, "select * from access_permission where module_id='37'");

		$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
		$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

		$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQRW[designation]'");
		$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);


		$message = "বেতন বৃদ্ধির তথ্য সংশোধিত, ".$getEmployeeDetailsQRW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name'];

		$link = "approve_increment_data_changes?menuslug=approve-increment-data-changes";

		$dateTime  = ShowBangladeshTime();

		while($uRow = mysqli_fetch_array($getusersforapprovalQ)){

			$insertNotificationQ = mysqli_query($con, "insert into notification(userID, message, link, dateTime) values('$uRow[user_id]', '$message', '$link', '$dateTime')");
		
		
		}

		// end of notification

		echo 1;

	}else{

		echo 0;

	}

}

?>