<?php
include('connection.php');

$courseID = $_POST['courseID'];

$instructorID = $_POST['instructorID'];

$batch_title = $_POST['batch_title'];

$total_seat = $_POST['total_seat'];

$venue_type = $_POST['venue_type'];

$venue_details = $_POST['venue_details'];



$insertQuery = mysqli_query($con, "insert into batch_list(`courseID`,`instructorID`,`batch_title`,`total_seat`,`venue_type`,`venue_details`) values('$courseID','$instructorID','$batch_title','$total_seat','$venue_type','$venue_details')");

if($insertQuery==1)
{

 $getLastDataQ = mysqli_query($con, "select dataID from batch_list order by dataID desc LIMIT 0,1");
 $getLastDataQRW = mysqli_fetch_assoc($getLastDataQ);

 $sl = 0;	
 foreach ($_POST['weekday'] as $day) {
 
 $start_time = $_POST['start_time'][$sl];

 $end_time = $_POST['end_time'][$sl];


 $insertClassScheduleQ = mysqli_query($con, "insert into `class_schedule`(`courseID`,`batchID`,`day`,`start_time`,`end_time`) values('$courseID','$getLastDataQRW[dataID]','$day','$start_time','$end_time')");


 $sl = $sl + 1;
 }


 echo 1;
}
else
{
echo 0;
}

?>