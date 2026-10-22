<?php
include('connection.php');

$templateData = $_POST['templateData'];

$templateType = $_POST['templateType'];

$checkForDuplicateQ = mysqli_query($con, "select * from leave_templates where `templateData`='$templateData' and `templateType`='$templateType'");
$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

if($checkForDuplicateQNumRows > 0){

	echo "Data Already Exist!";

}else{

$insertQuery = mysqli_query($con, "insert into leave_templates(`templateData`, `templateType`) values('$templateData', '$templateType')");

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