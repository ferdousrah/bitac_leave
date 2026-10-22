<?php
error_reporting(0);
include('connection.php');
include('bddate.php');

$todayDate = todayDate();

$studentID = $_POST['studentID'];

$full_name = $_POST['full_name'];

$father_name = $_POST['father_name'];

$mother_name = $_POST['mother_name'];

$dob = $_POST['dob'];

$gender = $_POST['gender'];

$nationality = $_POST['nationality'];

$academic_qualification = $_POST['academic_qualification'];

$academic_institute = $_POST['academic_institute'];

$present_address = $_POST['present_address'];

$guardians_cell = $_POST['guardians_cell'];

$residence_tele = $_POST['residence_tele'];

$students_cell = $_POST['students_cell'];

$passport_no = $_POST['passport_no'];

$study_abroad = $_POST['study_abroad'];

$email = $_POST['email'];

$prevPhoto = $_POST['prevPhoto'];

// receipt

$getLastMoneyReceiptQ = mysqli_query($con, "select * from `money_receipt` order by `dataID` desc LIMIT 0,1");

$getLastMoneyReceiptQNumRows = mysqli_num_rows($getLastMoneyReceiptQ);


if($getLastMoneyReceiptQNumRows > 0) {

	$getLastMoneyReceiptQRW = mysqli_fetch_assoc($getLastMoneyReceiptQ);

	$newReceiptNo = $getLastMoneyReceiptQRW['dataID'] + 1;

	$newReceiptNoFormatted = str_pad($newReceiptNo, 5, '0', STR_PAD_LEFT);

}
else {

	$newReceiptNo = 1;

	$newReceiptNoFormatted = str_pad($newReceiptNo, 5, '0', STR_PAD_LEFT);

}

// end of receipt


if(isset($_FILES['photo'])){

$file_name = $_FILES['photo']['name'];
$file_size =$_FILES['photo']['size'];
$file_tmp =$_FILES['photo']['tmp_name'];
$file_type=$_FILES['photo']['type'];
$file_extArray=explode('.',$_FILES['photo']['name']);
//echo $file_extArray[1];
$file_ext = strtolower($file_extArray[1]);	  

$extensions= array("jpeg","jpg","png");
      
if(in_array($file_ext,$extensions)== false){
	$errors[]="extension not allowed, please choose a JPEG or PNG file.";
}
      
	if($file_size > 2097152){
		$errors[]='File size must be excately 2 MB';
	}
      
	if(empty($errors)==true){
		move_uploaded_file($file_tmp,"uploads/students/".$file_name);
		//echo "Success";
	}else{
		print_r($errors);
		$file_name = $prevPhoto;
	}
} else{

	$file_name = $prevPhoto;

}

$updateQuery = mysqli_query($con, "update `students` set `full_name` = '$full_name', `father_name` = '$father_name', `mother_name` = '$mother_name', `dob` = '$dob', `gender` = '$gender', `nationality` = '$nationality', `academic_qualification` = '$academic_qualification', `academic_institute` = '$academic_institute', `present_address` = '$present_address', `guardians_cell` = '$guardians_cell', `residence_tele` = '$residence_tele', `students_cell` = '$students_cell', `passport_no` = '$passport_no', `study_abroad` = '$study_abroad', `email` = '$email', `photo` = '$file_name', `last_update` = '$todayDate' where `dataID` = '$studentID'");



if($updateQuery == 1) {

 echo 1;
	
} else {

echo 0;

}









?>