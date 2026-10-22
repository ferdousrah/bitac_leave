<?php
include('connection.php');
include('bddate.php');

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

$employeeID = $_POST['employeeID'];

$avgSalary = $_POST['avgSalary'];

$halfAvgSalary = $_POST['halfAvgSalary'];

$casual = $_POST['casual'];

if(isset($_POST['leaveWithoutPay']) && $_POST['leaveWithoutPay']!=''){

$leaveWithoutPay = $_POST['leaveWithoutPay'];

}else{

$leaveWithoutPay = 0;

}


if(isset($_POST['undeductibleLeave']) && $_POST['undeductibleLeave']!=''){

$undeductibleLeave = $_POST['undeductibleLeave'];

}else{

$undeductibleLeave = 0;

}

$submitDate = ShowBangladeshTime();

$checkForExistQ = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$employeeID'");
$checkForExistQNumRows = mysqli_num_rows($checkForExistQ);


// avg salary

if(!file_exists($_FILES['avgSalaryFile']['tmp_name']) || !is_uploaded_file($_FILES['avgSalaryFile']['tmp_name'])) {
   // echo 'No upload'; 
   $avgSalaryFile = '';
}
else {

$avgSalaryFile = $_FILES['avgSalaryFile']['name'];
$file_size =$_FILES['avgSalaryFile']['size'];
$file_tmp =$_FILES['avgSalaryFile']['tmp_name'];
$file_type=$_FILES['avgSalaryFile']['type'];
$file_extArray=explode('.',$_FILES['avgSalaryFile']['name']);
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
		move_uploaded_file($file_tmp,"uploads/".$avgSalaryFile);
		//echo "Success";
	}else{
		print_r($errors);
		$avgSalaryFile = '';
	}
}



// halfAvgSalaryFile

if(!file_exists($_FILES['halfAvgSalaryFile']['tmp_name']) || !is_uploaded_file($_FILES['halfAvgSalaryFile']['tmp_name'])) {
   // echo 'No upload'; 
   $halfAvgSalaryFile = '';
}
else {

$halfAvgSalaryFile = $_FILES['halfAvgSalaryFile']['name'];
$file_size =$_FILES['halfAvgSalaryFile']['size'];
$file_tmp =$_FILES['halfAvgSalaryFile']['tmp_name'];
$file_type=$_FILES['halfAvgSalaryFile']['type'];
$file_extArray=explode('.',$_FILES['halfAvgSalaryFile']['name']);
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
		move_uploaded_file($file_tmp,"uploads/".$halfAvgSalaryFile);
		//echo "Success";
	}else{
		print_r($errors);
		$halfAvgSalaryFile = '';
	}
}



// casualFile

if(!file_exists($_FILES['casualFile']['tmp_name']) || !is_uploaded_file($_FILES['casualFile']['tmp_name'])) {
   // echo 'No upload'; 
   $casualFile = '';
}
else {

$casualFile = $_FILES['casualFile']['name'];
$file_size =$_FILES['casualFile']['size'];
$file_tmp =$_FILES['casualFile']['tmp_name'];
$file_type=$_FILES['casualFile']['type'];
$file_extArray=explode('.',$_FILES['casualFile']['name']);
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
		move_uploaded_file($file_tmp,"uploads/".$casualFile);
		//echo "Success";
	}else{
		print_r($errors);
		$casualFile = '';
	}
}


// leaveWithoutPayFile

if(!file_exists($_FILES['leaveWithoutPayFile']['tmp_name']) || !is_uploaded_file($_FILES['leaveWithoutPayFile']['tmp_name'])) {
   // echo 'No upload'; 
   $leaveWithoutPayFile = '';
}
else {

$leaveWithoutPayFile = $_FILES['leaveWithoutPayFile']['name'];
$file_size =$_FILES['leaveWithoutPayFile']['size'];
$file_tmp =$_FILES['leaveWithoutPayFile']['tmp_name'];
$file_type=$_FILES['leaveWithoutPayFile']['type'];
$file_extArray=explode('.',$_FILES['leaveWithoutPayFile']['name']);
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
		move_uploaded_file($file_tmp,"uploads/".$leaveWithoutPayFile);
		//echo "Success";
	}else{
		print_r($errors);
		$leaveWithoutPayFile = '';
	}
}



// undeductibleLeaveFile

if(!file_exists($_FILES['undeductibleLeaveFile']['tmp_name']) || !is_uploaded_file($_FILES['undeductibleLeaveFile']['tmp_name'])) {
   // echo 'No upload'; 
   $undeductibleLeaveFile = '';
}
else {

$undeductibleLeaveFile = $_FILES['undeductibleLeaveFile']['name'];
$file_size =$_FILES['undeductibleLeaveFile']['size'];
$file_tmp =$_FILES['undeductibleLeaveFile']['tmp_name'];
$file_type=$_FILES['undeductibleLeaveFile']['type'];
$file_extArray=explode('.',$_FILES['undeductibleLeaveFile']['name']);
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
		move_uploaded_file($file_tmp,"uploads/".$undeductibleLeaveFile);
		//echo "Success";
	}else{
		print_r($errors);
		$undeductibleLeaveFile = '';
	}
}



if($checkForExistQNumRows > 0){

	$updateQuery = mysqli_query($con, "update previous_leave_deduction set avgSalary='$avgSalary', halfAvgSalary='$halfAvgSalary', casual='$casual', leaveWithoutPay='$leaveWithoutPay', isApproved=0, avgSalaryFile='$avgSalaryFile', halfAvgSalaryFile='$halfAvgSalaryFile', casualFile='$casualFile', leaveWithoutPayFile='$leaveWithoutPayFile', lastUpdate='$submitDate', undeductibleLeave='$undeductibleLeave', undeductibleLeaveFile='$undeductibleLeaveFile' where employeeID='$employeeID'");

	if($updateQuery == 1){
	
		echo 1;
	
	}else{
	
		echo 0;
	
	}


}else{


	$insertQuery = mysqli_query($con, "insert into previous_leave_deduction(employeeID, avgSalary, halfAvgSalary, casual, leaveWithoutPay, avgSalaryFile, halfAvgSalaryFile, casualFile, leaveWithoutPayFile, lastUpdate, undeductibleLeave, undeductibleLeaveFile) values('$employeeID', '$avgSalary', '$halfAvgSalary', '$casual', '$leaveWithoutPay', '$avgSalaryFile', '$halfAvgSalaryFile', '$casualFile', '$leaveWithoutPayFile', '$submitDate', '$undeductibleLeave', '$undeductibleLeaveFile')");

	if($insertQuery == 1){
	
		echo 1;
	
	}else{
	
		echo 0;
	
	}

}


?>