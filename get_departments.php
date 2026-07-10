<?php
include('connection.php');

$organization_id = $_POST['id'];

$getAllS = mysqli_query($con, "select * from `departments` where `organization_id`='$organization_id'");
echo "<option value='0'>--বিভাগ বাছাই করুন--</option>";
while($sRow=mysqli_fetch_array($getAllS))
{
	echo "<option value='$sRow[id]'>$sRow[department_name]</option>";
}

?>