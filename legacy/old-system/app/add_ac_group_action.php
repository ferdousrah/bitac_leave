<?php
session_start();
include('connection.php');

$group_title = $_POST['group_title'];

$createdBy = $_SESSION['userID'];


$checkForDuplicateQ = mysqli_query($con, "select * from `accounts_group` where `group_title`='$group_title'");
$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

if($checkForDuplicateQNumRows > 0) {

echo 1;
exit;
} else{
$insertQuery = mysqli_query($con,"INSERT INTO `accounts_group` (`group_title`,`created_by`) VALUES ('$group_title','$createdBy')") or die(mysqli_error($con));
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