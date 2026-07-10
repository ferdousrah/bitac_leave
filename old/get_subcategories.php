<?php
include("connection.php");

$categoryID = $_POST['categoryID'];

$getAllSubCatQ = mysqli_query($con, "select distinct itemCode from row_materials where groupID='$categoryID'");

echo "<option value='0'>-Select Material-</option>";

while($subRow=mysqli_fetch_array($getAllSubCatQ))
{
  $getItemDetailsQ = mysqli_query($con, "select * from row_materials where `itemCode`='$subRow[itemCode]'");
  $getItemDetailsQRW = mysqli_fetch_assoc($getItemDetailsQ);
  echo "<option value='$subRow[itemCode]'>".$subRow['itemCode']." - ".htmlentities($getItemDetailsQRW['material_name'])."</option>";
}


?>