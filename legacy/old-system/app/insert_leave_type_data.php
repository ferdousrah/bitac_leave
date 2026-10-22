<?php
include('connection.php');

$leaveTitle = $_POST['leaveTitle'];


$insertQuery = mysqli_query($con, "insert into leave_types(`leaveTitle`) values('$leaveTitle')");

if($insertQuery==1)
{
 echo 1;
}
else
{
echo 0;
}

?>