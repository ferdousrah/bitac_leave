<?php
session_start();
include('connection.php');

$full_name = $_POST['full_name'];

$mobile_no = $_POST['mobile_no'];

$email = $_POST['email'];




$insertQuery = mysqli_query($con,"INSERT INTO `instructors` (`full_name`,`mobile`,`email`) VALUES ('$full_name','$mobile_no','$email')") or die(mysqli_error($con));

if($insertQuery==1)
{
   echo 1;
}
else
{
	echo 0;
}


?>