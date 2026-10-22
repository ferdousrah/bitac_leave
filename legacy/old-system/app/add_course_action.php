<?php
session_start();
include('connection.php');

$courseCode = $_POST['courseCode'];

$courseTitle = $_POST['courseTitle'];

$no_of_classes = $_POST['no_of_classes'];

$total_hour = $_POST['total_hour'];

$price = $_POST['price'];


$createdBy = $_SESSION['userID'];




$insertQuery = mysqli_query($con,"INSERT INTO `courses` (`courseCode`,`courseTitle`,`no_of_classes`,`total_hour`,`price`,`created_by`) VALUES ('$courseCode','$courseTitle','$no_of_classes','$total_hour','$price','$createdBy')") or die(mysqli_error($con));

if($insertQuery==1)
{
   echo 1;
}
else
{
	echo 0;
}


?>