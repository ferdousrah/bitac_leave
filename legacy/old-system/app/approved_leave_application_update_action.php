<?php
session_start();
include('connection.php');
include('bddate.php');
include('library/number_converter.php');

function dateDiffInDays($date1, $date2) 
  {
      // Calculating the difference in timestamps
      $diff = strtotime($date2) - strtotime($date1);
  
      // 1 day = 24 hours
      // 24 * 60 * 60 = 86400 seconds
      return abs(round($diff / 86400));
  }

$createdBy = $_SESSION['userID'];

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where dataID='$createdBy'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$submitDate = todayDate();

$submitDate = date_create($submitDate);

$leaveType = $_POST['leaveType'];


// date

$dateF = $_POST['leaveFrom'];

$dateFArray = explode('/', $dateF);

$dateFrom = $dateFArray[2].'-'.$dateFArray[1].'-'.$dateFArray[0];


$dateT = $_POST['leaveTo'];

$dateTArray = explode('/', $dateT);

$dateTo = $dateTArray[2].'-'.$dateTArray[1].'-'.$dateTArray[0];


//......



$leaveApplicationID = $_POST['leaveApplicationID'];

$leaveTypeInTwo = $_POST['leaveTypeInTwo'];

$approvedLeaveType = $_POST['approvedLeaveType'];

$note = $_POST['note'];

$adminNoteDate = todayDate();

$updateApplicationQ = mysqli_query($con, "update leave_applications set approvedDateFrom='$dateFrom', approvedDateTo='$dateTo', approvedLeaveType='$approvedLeaveType', leaveTypeInTwo='$leaveTypeInTwo', adminNote='$note', adminInitiator='$createdBy', adminNoteDate='$adminNoteDate' where dataID='$leaveApplicationID'");

$dateDiff = dateDiffInDays($dateFrom, $dateTo) + 1;

$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$leaveApplicationID'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getLeaveApplicationDetailsQRW[applicantID]'");
$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$updateApprovalDataQ = mysqli_query($con, "update leave_data_for_approval set isSentbyAdmin=1 where leaveApplicationID='$leaveApplicationID'");


// copy to

$clearCopyToDataQ = mysqli_query($con, "delete from leave_notice_copy where applicationID='$leaveApplicationID'");

$sl = 0;
foreach($_POST['copyTo'] as $copyTo){

	$serial = $_POST['serial'][$sl];

	$insertCopyToQ = mysqli_query($con, "insert into leave_notice_copy(employeeID, applicationID, serial) values('$copyTo', '$leaveApplicationID', '$serial')");


	$sl++;

}

echo 1;

?>


