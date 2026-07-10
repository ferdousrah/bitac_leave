<?php
include('connection.php');

$organization_id = $_POST['organization_id'];

$designationID = $_POST['designationID'];

$approvalSL = $_POST['approvalSL'];


// check is there any officer in that position, if no one exist hold the process
$checkOfficerQ = mysqli_query($con, "SELECT employee_id, employee_name FROM employee_list WHERE organization_id = '$organization_id' AND designation = '$designationID' and employment_status=1");
$checkOfficerQNumRows = mysqli_num_rows($checkOfficerQ);

if($checkOfficerQNumRows == 1){

$checkPersonExistQ = mysqli_query($con, "select * from leave_approval_signatory where `organization_id`='$organization_id' and `designationID`='$designationID' and `approvalSL`='$approvalSL'");
$checkPersonExistQNumRows = mysqli_num_rows($checkPersonExistQ);

if($checkPersonExistQNumRows > 0){

	echo "Signatory already exist in that approval position";

}else{

	$checkForDuplicateQ = mysqli_query($con, "select * from leave_approval_signatory where `organization_id`='$organization_id' and designationID='$designationID'");
	$checkForDuplicateQNumRows = mysqli_num_rows($checkForDuplicateQ);

	if($checkForDuplicateQNumRows > 0){

		echo "Already Exist!";

	}else{

		$insertQuery = mysqli_query($con, "insert into leave_approval_signatory(`organization_id`, `designationID`, `approvalSL`) values('$organization_id', '$designationID', '$approvalSL')");

		if($insertQuery == 1)
		{
			echo 1;
		}
		else
		{
			echo 0;
		}
	}

}
// end of if($checkOfficerQNumRows == 1)

}else{

	echo "Error! In that position there are no responsible person or more than one person";

}


?>