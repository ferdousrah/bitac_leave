<?php
include('connection.php');

$courseID = $_POST['courseID'];

$studentID = $_POST['studentID'];

$getCourseQ = mysqli_query($con, "select * from `courses_by_students` where `studentID`='$studentID'");
$getCourseQRW = mysqli_fetch_assoc($getCourseQ);

$getTotalPaidQ = mysqli_query($con, "select sum(received_amount) as totalPaid from money_receipt where studentID='$studentID'");
$getTotalPaidQRW = mysqli_fetch_assoc($getTotalPaidQ);

echo $getCourseQRW['price'] - ($getTotalPaidQRW['totalPaid'] + $getCourseQRW['comission']);

?>