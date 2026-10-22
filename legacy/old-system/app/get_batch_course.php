<?php
include('connection.php');

$courseID = $_POST['courseID'];

$getAllBatchByCourseQ = mysqli_query($con, "select * from `batch_list` where `courseID`='$courseID'");

echo "<option value=''></option>";

while($dataRow = mysqli_fetch_array($getAllBatchByCourseQ)) {

echo "<option value='$dataRow[dataID]'>$dataRow[batch_title]</option>";

}

?>