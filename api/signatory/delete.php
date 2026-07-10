<?php
require_once(__DIR__ . '/../../config/connection.php');

$dataID = $_POST['dataID'];

$tableName = $_POST['tableName'];

$deltQ = mysqli_query($con,"delete from `$tableName` where dataID='$dataID'");

if($deltQ==1)
{
 echo 1;
}
else
{
 echo 0;
}
?>