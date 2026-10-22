<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$submitDate = todayDate();



$employeeID = $_POST['employeeID'];

$leaveType = $_POST['leaveType'];

$leaveDeduct = $_POST['leaveDeduct'];

$note = $_POST['note'];



if(!file_exists($_FILES['officeAdesh']['tmp_name']) || !is_uploaded_file($_FILES['officeAdesh']['tmp_name'])) {
   // echo 'No upload'; 
   $officeAdesh = '';
}
else {

$officeAdesh = $_FILES['officeAdesh']['name'];
$file_size =$_FILES['officeAdesh']['size'];
$file_tmp =$_FILES['officeAdesh']['tmp_name'];
$file_type=$_FILES['officeAdesh']['type'];
$file_extArray=explode('.',$_FILES['officeAdesh']['name']);
//echo $file_extArray[1];
$file_ext = strtolower($file_extArray[1]);	  

$extensions= array("jpeg","jpg","png","pdf","PDF");
      
if(in_array($file_ext,$extensions)== false){
	$errors[]="extension not allowed, please choose a JPEG or PNG file.";
}
      
	if($file_size > 2097152){
		$errors[]='File size must be excately 2 MB';
	}
      
	if(empty($errors)==true){
		move_uploaded_file($file_tmp,"uploads/".$officeAdesh);
		//echo "Success";
	}else{
		print_r($errors);
		$officeAdesh = '';
	}
}

if($officeAdesh == ''){

print_r($errors);
exit();

}


$insertQuery = mysqli_query($con, "insert into leave_deduction_history(employeeID, leaveID, leaveDeduction, note, attachment, createDate, createdBy) values('$employeeID', '$leaveType', '$leaveDeduct', '$note', '$officeAdesh', '$submitDate', '$createdBy')");

if($insertQuery == 1){

	echo 1;

}else {

	echo 0;

}



?>