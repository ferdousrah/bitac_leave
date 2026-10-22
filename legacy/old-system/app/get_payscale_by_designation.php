<?php
include('connection.php');
//include('library/number_converter.php');
$desig_id = $_POST['id'];

$getAllS = mysqli_query($con, "select * from `job_title` where `id`='$desig_id'");
$getAllSRW = mysqli_fetch_assoc($getAllS);

$scaleID = $getAllSRW['grade'];

echo "select * from grade where id='$scaleID'";

$getScalesQ = mysqli_query($con, "select * from grade where id='$scaleID'");


while($sRow=mysqli_fetch_array($getScalesQ))
{
	echo "<option value='$sRow[id]'>".$obj->engToBn($sRow['minimum_salary'])." - ".$obj->engToBn($sRow['maximum_salary'])." ($sRow[grade_title])</option>";
}

?>