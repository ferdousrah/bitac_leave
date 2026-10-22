<?php
include('connection.php');

$groupID = $_POST['groupID'];

$getAllItemsQ = mysqli_query($con, "select * from row_materials where groupID='$groupID'");

while($dataRow=mysqli_fetch_array($getAllItemsQ))
{
 echo "<option value='$dataRow[itemCode]'>".$dataRow['itemCode'].' - '.$dataRow['material_name']."</option>";
}

?>