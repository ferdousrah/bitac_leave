<?php
session_start();
include('connection.php');

$account_type = $_POST['account_type'];

$createdBy = $_SESSION['userID'];


$checkForDuplicateQ = mysqli_query($con, "select * from `account_types` where `account_type`='$account_type'");
$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

if($checkForDuplicateQNumRows > 0) {

echo 1;
exit;
} else{
$insertQuery = mysqli_query($con,"INSERT INTO `account_types` (`account_type`,`created_by`) VALUES ('$account_type','$createdBy')") or die(mysqli_error($con));
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