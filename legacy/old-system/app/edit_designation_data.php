<?php
include('connection.php');

$id = $_POST['id'];

$job_title_name = $_POST['job_title_name'];

$display_order = $_POST['display_order'];



	$insertQuery = mysqli_query($con, "update job_title set `job_title_name`='$job_title_name', `display_order`='$display_order' where id='$id'");

	if($insertQuery==1)
	{
	 echo 1;
	}
	else
	{
	echo 0;
	}


?>