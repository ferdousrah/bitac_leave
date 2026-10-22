<?php
include('connection.php');

$courseID = $_POST['dataID'];

$countRowsInBatchQ = mysqli_query($con, "select count(*) as totalBatch from batch_list where courseID='$courseID'");
$countRowsInBatchQRW = mysqli_fetch_assoc($countRowsInBatchQ);

$countRowsInStudentCourseQ = mysqli_query($con, "select count(*) as totalStCourse from courses_by_students where courseID='$courseID'");
$countRowsInStudentCourseQRW = mysqli_fetch_assoc($countRowsInStudentCourseQ);

$getTotalDataFromCrsSchQ = mysqli_query($con, "select count(*) as totalCrsSch from class_schedule where courseID='$courseID'");
$getTotalDataFromCrsSchQRW = mysqli_fetch_assoc($getTotalDataFromCrsSchQ);

if($countRowsInBatchQRW['totalBatch'] <= 0 && $countRowsInStudentCourseQRW['totalStCourse'] <= 0 && $getTotalDataFromCrsSchQRW <= 0){

	$deleteQ = mysqli_query($con, "delete from courses where dataID='$courseID'");

	if($deleteQ == 1){
	
		echo 1;
	
	}


}else{

	echo "Dependent Data Found on Other Table!";


}


?>