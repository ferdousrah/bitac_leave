<?php
session_start();
include('connection.php');

$account_name = $_POST['account_name'];

$account_number = $_POST['account_number'];

$account_type = $_POST['account_type'];

$account_group = $_POST['account_group'];

$createdBy = $_SESSION['userID'];


$checkForDuplicateQ = mysqli_query($con, "select * from `accounts_list` where `account_number`='$account_number'");
$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

if($checkForDuplicateQNumRows > 0) {

echo 1;
exit;
} else{
$insertQuery = mysqli_query($con,"INSERT INTO `accounts_list` (`account_name`, `account_number`, `account_type`, `account_group`, `created_by`) VALUES ('$account_name', '$account_number', '$account_type', '$account_group','$createdBy')") or die(mysqli_error($con));
}


if($insertQuery==1)
{
   echo 1;
}
else
{
	echo 0;
}


?>