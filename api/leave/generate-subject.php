<?php
require_once(__DIR__ . '/../../config/connection.php');

$reqLeaveDays = $_POST['reqLeaveDays'];

$leaveType = $_POST['leaveType'];

$applicationType = $_POST['applicationType'];

if($applicationType == 1){

$getLeaveDetailsQ = mysqli_query($con, "select * from leave_types where leaveID='$leaveType'");
$getLeaveDetailsQRW = mysqli_fetch_assoc($getLeaveDetailsQ);

$str = $obj->engToBn($reqLeaveDays)." "."দিনের ".$getLeaveDetailsQRW['leaveTitle']." "."ছুটির আবেদন পত্র ।";

}else if($applicationType == 2){

$getLeaveDetailsQ = mysqli_query($con, "select * from leave_types where leaveID='$leaveType'");
$getLeaveDetailsQRW = mysqli_fetch_assoc($getLeaveDetailsQ);

$str = "কর্মস্থলে যোগদান ও ".$obj->engToBn($reqLeaveDays)." "."দিন অফিসে অনুপস্থিতকালের জন্য ".$obj->engToBn($reqLeaveDays)." দিনের ".$getLeaveDetailsQRW['leaveTitle']." "."ছুটির আবেদন পত্র ।";

}

echo $str;


?>