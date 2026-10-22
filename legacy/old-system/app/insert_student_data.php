<?php
error_reporting(0);
include('connection.php');
include('bddate.php');

$todayDate = todayDate();

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

$comission = $_POST['comission'];

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

if(!file_exists($_FILES['photo']['tmp_name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
   // echo 'No upload';
   $file_name = "";
}
else {

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
		$file_name = "";
	}
}


$insertStudentInfoQ = mysqli_query($con, "insert into `students`(`full_name`, `father_name`, `mother_name`, `dob`, `gender`, `nationality`, `academic_qualification`, `academic_institute`, `present_address`, `guardians_cell`, `residence_tele`, `students_cell`, `passport_no`, `study_abroad`, `email`, `photo`, `create_date`) values('$full_name', '$father_name', '$mother_name', '$dob', '$gender', '$nationality', '$academic_qualification', '$academic_institute', '$present_address', '$guardians_cell', '$residence_tele', '$students_cell', '$passport_no', '$study_abroad', '$email', '$file_name', '$todayDate')");


if($insertStudentInfoQ == 1) {

	$getLastStudentIdQ = mysqli_query($con, "select * from `students` where 1=1 order by `dataID` desc LIMIT 0,1");
	$getLastStudentIdQRW = mysqli_fetch_assoc($getLastStudentIdQ);

	$lastStudentID = $getLastStudentIdQRW['dataID'];

	$courseID = $_POST['courseID'];

	$batchID = $_POST['batchID'];

	$advance_paid = $_POST['paid'];

	$due_date = $_POST['due_date'];

	$getCoursePriceQ = mysqli_query($con, "select * from `courses` where `dataID`='$courseID'");
	$getCoursePriceQRW = mysqli_fetch_assoc($getCoursePriceQ);

	// insert student course details
	$insertIntoCourseByStudentQ = mysqli_query($con, "insert into `courses_by_students`(`studentID`, `courseID`, `batchID`, `price`, `discount`, `comission`, `advance_paid`, `totalPaid`, `date`) values('$lastStudentID', '$courseID', '$batchID' ,'$getCoursePriceQRW[price]' ,'$getCoursePriceQRW[discount]', '$comission' ,'$advance_paid' , '$advance_paid' , '$todayDate')");

	// insert transaction receipt
	$insertReceiptQ = mysqli_query($con, "insert into `money_receipt`(`receipt_no`, `studentID`, `date`, `received_from`, `received_amount`, `received_in`, `on_account_of`, `next_due_date`) values('$newReceiptNoFormatted', '$lastStudentID', '$todayDate', '$full_name', '$advance_paid', '', '$getCoursePriceQRW[courseTitle]', '$due_date')");

	foreach($_POST['heard'] as $h) {

		if(isset($_POST['others_txt']) && $_POST['others_txt']!='') {

			$otherText = $_POST['others_txt'];
		
		} else {

			$otherText = '';
		
		}

		$insertColFromDataQ = mysqli_query($con, "insert into `student_collection_from`(`studentID`, `from`, `other_text`) values('$lastStudentID', '$h', '$otherText')");
	
	}

	echo $newReceiptNoFormatted;

} else {

echo 0;

}









?>