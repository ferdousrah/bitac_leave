<?php
include('connection.php');

$studentID = $_POST['studentID'];

$getCoursesQ = mysqli_query($con, "select * from courses_by_students where studentID='$studentID'");

echo "<option value=''></option>";

while($cRow = mysqli_fetch_array($getCoursesQ)){

	$getCourseDetailsQ = mysqli_query($con, "select * from courses where dataID='$cRow[courseID]'");
	$getCourseDetailsQRW = mysqli_fetch_assoc($getCourseDetailsQ);

	echo "<option value='$cRow[courseID]'>$getCourseDetailsQRW[courseTitle]</option>";

}


?>