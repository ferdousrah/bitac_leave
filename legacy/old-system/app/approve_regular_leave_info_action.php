<?php
include('connection.php');

$dataID = $_GET['dataID'];

$isApproved = $_GET['isApproved'];

$approveLeaveDataQ = mysqli_query($con, "update leave_deduction_history set isApproved='$isApproved' where dataID='$dataID'");

if($approveLeaveDataQ == 1){

echo "<script>alert('Success')</script>";
echo "<script>window.location='previous_leave_regular_info_approve?menuslug=previous-leave-regular-info-approve'</script>";

}else{

echo "<script>alert('Error')</script>";
echo "<script>window.location='previous_leave_regular_info_approve?menuslug=previous-leave-regular-info-approve'</script>";

}

?>