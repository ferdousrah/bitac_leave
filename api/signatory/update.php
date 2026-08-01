<?php
require_once(__DIR__ . '/../../config/connection.php');

$dataID = $_POST['dataID'];

$organization_id = $_POST['organization_id'];

$designationID = $_POST['designationID'];

$isMandatory = $_POST['isMandatory'];

$updateQuery = mysqli_query($con, "update leave_approval_signatory set `organization_id`='$organization_id', `designationID`='$designationID', `isMandatory`='$isMandatory' where dataID='$dataID'");

if($updateQuery == 1)
{
	if (function_exists('audit_log')) {
		audit_log('signatory_updated_legacy', [
			'target_type'     => 'leave_approval_signatory',
			'target_id'       => (int)$dataID,
			'organization_id' => (int)$organization_id,
			'note'            => "designation=$designationID; mandatory=$isMandatory",
		]);
	}
	echo 1;
}
else
{
	echo 0;
}

?>