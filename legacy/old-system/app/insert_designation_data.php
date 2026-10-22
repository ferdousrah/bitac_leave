<?php
include('connection.php');

$job_title_name = $_POST['job_title_name'];

$display_order = $_POST['display_order'];

$checkForDuplicateQ = mysqli_query($con, "select * from job_title where `job_title_name`='$job_title_name'");
$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

if($checkForDuplicateQNumRows > 0){

	echo "Already Exist!";

}else{

	$insertQuery = mysqli_query($con, "insert into job_title(`job_title_name`, `display_order`) values('$job_title_name', '$display_order')");

	if($insertQuery==1)
	{
	 echo 1;
	}
	else
	{
	echo 0;
	}
}

?>