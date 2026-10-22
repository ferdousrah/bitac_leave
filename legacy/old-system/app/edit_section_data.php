<?php
include('connection.php');

$id = $_POST['id'];

$section_name = $_POST['section_name'];

$display_order = $_POST['display_order'];



	$insertQuery = mysqli_query($con, "update sections set `section_name`='$section_name', `display_order`='$display_order' where id='$id'");

	if($insertQuery==1)
	{
	 echo 1;
	}
	else
	{
	echo 0;
	}


?>