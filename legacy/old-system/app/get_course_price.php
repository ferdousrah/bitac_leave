<?php
include('connection.php');

$courseID = $_POST['courseID'];

$getCourseQ = mysqli_query($con, "select * from `courses` where `dataID`='$courseID'");
$getCourseQRW = mysqli_fetch_assoc($getCourseQ);

echo $getCourseQRW['price'];

?>