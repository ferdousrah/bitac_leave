<?php
session_start();
include('connection.php');
include('bddate.php');

$last_update_date = ShowBangladeshTime();

$updated_by = $_SESSION['userID'];

$dataID = $_POST['dataID'];

$deltQuery = mysqli_query($con,"delete from `row_materials` where `dataID`='$dataID'");

if($deltQuery==1)
{
	echo 1;
}
else
{
	echo 0;
}
?>