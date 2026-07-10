<?php
include('connection.php');

$department_id = $_POST['id'];

$getAllS = mysqli_query($con, "select * from `sections` where `department_id`='$department_id'");
echo "<option value='0'>--শাখা বাছাই করুন--</option>";
while($sRow=mysqli_fetch_array($getAllS))
{
	echo "<option value='$sRow[id]'>$sRow[section_name]</option>";
}

?>