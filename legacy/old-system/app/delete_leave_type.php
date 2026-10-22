<?php
include('connection.php');

$leaveID = $_POST['dataID'];

$deleteQuery = mysqli_query($con, "delete from leave_types where leaveID='$leaveID'");

if($deleteQuery == 1){

	echo 1;

}else{

	echo 0;

}


?>