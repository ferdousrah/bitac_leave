<?php
include('connection.php');

$notificationID = $_GET['notificationID'];

$updateNotificationStatusQ = mysqli_query($con, "update notification set isRead=1 where notificationID='$notificationID'");

if($updateNotificationStatusQ == 1){

	$getNotificationDetailsQ = mysqli_query($con, "select * from notification where notificationID='$notificationID'");
	$getNotificationDetailsQRW = mysqli_fetch_assoc($getNotificationDetailsQ);

	echo "<script>window.location='".$getNotificationDetailsQRW['link']."'</script>";

}else{

	echo "<script>window.location='dashboard?menuslug=dashboard'</script>";


}
?>