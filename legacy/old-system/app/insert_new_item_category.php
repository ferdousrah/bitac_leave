<?php
include("connection.php");

$category_name = $_POST['category_name'];

$insertQ = mysqli_query($con, "insert into `item_category`(`category_name`) values('$category_name')");

$getAllCatQ = mysqli_query($con, "select * from `item_category`");

echo "<option value=''></option>";

while($dataRow=mysqli_fetch_array($getAllCatQ))
{

	echo "<option value='".$dataRow['dataID']."'>".$dataRow['category_name']."</option>";

}



?>