<?php
session_start();
include('connection.php');

$accountID = $_POST['accountID'];

$account_name = $_POST['account_name'];

$account_number = $_POST['account_number'];

$account_type = $_POST['account_type'];

$account_group = $_POST['account_group'];

$createdBy = $_SESSION['userID'];


$insertQuery = mysqli_query($con, "update `accounts_list` set `account_name`='$account_name', `account_number`='$account_number', `account_type`='$account_type', `account_group`='$account_group' where dataID='$accountID'") or die(mysqli_error($con));


if($insertQuery==1)
{
   echo 1;
}
else
{
	echo 0;
}


?>