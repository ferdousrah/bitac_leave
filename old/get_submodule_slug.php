<?php
include('connection.php');

$submoduleID = $_POST['submoduleID'];

$getSubModuleQ = mysqli_query($con, "select * from submodules where dataID='$submoduleID'");
$getSubModuleQRW = mysqli_fetch_assoc($getSubModuleQ);

echo $getSubModuleQRW['slug'];

?>