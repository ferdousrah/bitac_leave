<?php
include('connection.php');

$dataID = $_POST['dataID'];


// delete joining data

$deleteJoiningApprovalDataQ = mysqli_query($con, "delete from leave_joining_data_for_approval where leaveApplicationID='$dataID'");

$deleteJoiningApplicationDataQ = mysqli_query($con, "delete from leave_joining_application where leaveApplicationID='$dataID'");

$deleteLApplicationApprovalDataQ = mysqli_query($con, "delete from leave_data_for_approval where leaveApplicationID='$dataID'");

$deleteLNoticeCopyQ = mysqli_query($con, "delete from leave_notice_copy where applicationID='$dataID'");

$deleteLApplicationDataQ = mysqli_query($con, "delete from leave_applications where dataID='$dataID'");



echo 1;



?>