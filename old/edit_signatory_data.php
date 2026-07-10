<?php
include('connection.php');

$dataID = $_POST['dataID'];

$organization_id = $_POST['organization_id'];

$designationID = $_POST['designationID'];

$isMandatory = $_POST['isMandatory'];

$updateQuery = mysqli_query($con, "update leave_approval_signatory set `organization_id`='$organization_id', `designationID`='$designationID', `isMandatory`='$isMandatory' where dataID='$dataID'");

if($updateQuery == 1)
{
	echo 1;
}
else
{
	echo 0;
}

?>