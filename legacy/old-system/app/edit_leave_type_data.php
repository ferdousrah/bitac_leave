<?php
include('connection.php');

$leaveID = $_POST['leaveID'];

$leaveTitle = $_POST['leaveTitle'];


$insertQuery = mysqli_query($con, "update leave_types set `leaveTitle`='$leaveTitle' where leaveID='$leaveID'");

if($insertQuery==1)
{
 echo 1;
}
else
{
echo 0;
}

?>