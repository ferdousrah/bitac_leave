<?php
error_reporting(0);
include('connection.php');
include('bddate.php');

$todayDate = todayDate();

$studentID = $_POST['studentID'];

$courseID = $_POST['courseID'];

$paid_receipt_no = $_POST['paid_receipt_no'];

$paid = $_POST['paid'];

$dues = $_POST['dues'];

$due_date = $_POST['due_date'];


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


$getStudentDetailsQ = mysqli_query($con, "select * from `students` where dataID='$studentID'");
$getStudentDetailsQRW = mysqli_fetch_assoc($getStudentDetailsQ);


$getCourseDetailsQ = mysqli_query($con, "select * from courses where dataID='$courseID'");
$getCourseDetailsQRW = mysqli_fetch_assoc($getCourseDetailsQ);


$insertReceiptQ = mysqli_query($con, "insert into `money_receipt`(`receipt_no`, `studentID`, `date`, `received_from`, `received_amount`, `received_in`, `on_account_of`, `next_due_date`) values('$newReceiptNoFormatted', '$studentID', '$todayDate', '$getStudentDetailsQRW[full_name]', '$paid', '', '$getCourseDetailsQRW[courseTitle]', '$due_date')");


if($insertReceiptQ == 1){

echo 1;

}else{

echo 0;

}




?>