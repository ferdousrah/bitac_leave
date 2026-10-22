<?php
session_start();
include('connection.php');

$dataID = $_POST['dataID'];

$account_type = $_POST['account_type'];

$updated_by = $_SESSION['userID'];




$insertQuery = mysqli_query($con,"update account_types set `account_type`='$account_type', `updated_by`='$updated_by' where `dataID`='$dataID'") or die(mysqli_error($con));

if($insertQuery==1)
{
   echo 1;
}
else
{
	echo 0;
}


?>