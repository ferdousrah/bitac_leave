<?php
session_start();
include('connection.php');

$dataID = $_POST['dataID'];

$group_title = $_POST['group_title'];

$updated_by = $_SESSION['userID'];




$insertQuery = mysqli_query($con,"update accounts_group set `group_title`='$group_title', `updated_by`='$updated_by' where `dataID`='$dataID'") or die(mysqli_error($con));

if($insertQuery==1)
{
   echo 1;
}
else
{
	echo 0;
}


?>