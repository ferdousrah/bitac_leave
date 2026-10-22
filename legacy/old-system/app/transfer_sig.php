<?php
include('connection.php');
/*
$query = mysqli_query($con, "SELECT leave_data_for_approval.dataID as lineID, leave_data_for_approval.leaveApplicationID, leave_data_for_approval.signatory, leave_data_for_approval.isApproved, leave_applications.dataID, leave_applications.organization_id FROM `leave_data_for_approval` INNER JOIN leave_applications ON leave_data_for_approval.leaveApplicationID=leave_applications.dataID WHERE leave_data_for_approval.`signatory`=872 and leave_data_for_approval.`isApproved`=0 and leave_applications.organization_id=5");

while($row = mysqli_fetch_array($query)){

echo "DataId: ".$row['lineID'].", leaveapplication ID: ".$row['leaveApplicationID'].", Signatory: ".$row['signatory'].", isApproved: ".$row['isApproved'].", org id: ".$row['organization_id']."<br>";

$updateQ = mysqli_query($con, "update leave_data_for_approval set signatory=1383 where signatory=872 and dataID='$row[lineID]' and isApproved=0");

}
*/

?>