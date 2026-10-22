<?php
session_start();
include('connection.php');

$dataID = $_POST['dataID'];

$courseCode = $_POST['courseCode'];

$courseTitle = $_POST['courseTitle'];

$no_of_classes = $_POST['no_of_classes'];

$total_hour = $_POST['total_hour'];

$price = $_POST['price'];


$updated_by = $_SESSION['userID'];




$insertQuery = mysqli_query($con,"update `courses` set `courseCode`='$courseCode', `courseTitle`='$courseTitle', `no_of_classes`='$no_of_classes', `total_hour`='$total_hour', `price`='$price', `updated_by`='$updated_by' where `dataID`='$dataID'") or die(mysqli_error($con));

if($insertQuery==1)
{
   echo 1;
}
else
{
	echo 0;
}


?>