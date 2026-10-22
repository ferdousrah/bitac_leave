<?php
include('connection.php');

$section_name = $_POST['section_name'];

$display_order = $_POST['display_order'];

$checkForDuplicateQ = mysqli_query($con, "select * from sections where `section_name`='$section_name'");
$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

if($checkForDuplicateQNumRows > 0){

	echo "Already Exist!";

}else{

	$insertQuery = mysqli_query($con, "insert into sections(`section_name`, `display_order`) values('$section_name', '$display_order')");

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