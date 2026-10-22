<?php
session_start();
include('connection.php');
include('bddate.php');

$createdBy = $_SESSION['userID'];

$createDate = todayDate();

$ref_no = $_POST['ref_no'];

$voucher_date = $_POST['voucher_date'];

$transaction_mode = $_POST['transaction_mode'];

$sl = 0;
foreach($_POST['account'] as $account) {

	$getAccountDetailsQ = mysqli_query($con, "select * from accounts_list where dataID='$account'");
	$getAccountDetailsQRW = mysqli_fetch_assoc($getAccountDetailsQ);

//$voucher_date = $_POST['voucher_date'][$sl];

	$acGroupID = $getAccountDetailsQRW['account_group'];

//$account = $_POST['account'][$sl];

$description = $_POST['description'][$sl];

$debit = $_POST['debit'][$sl];

$credit = $_POST['credit'][$sl];

$insertVoucherQ = mysqli_query($con, "insert into `voucher_list`(`entry_no`, `voucher_no`, `voucher_date`, `groupID`, `accountNo`, `description`, `debit`, `credit`, `created_by`, `create_date`) values('$ref_no', '$ref_no', '$voucher_date', '$acGroupID', '$account', '$description', '$debit', '$credit', '$createdBy', '$createDate')");

$sl = $sl + 1;
}

echo 1;

?>