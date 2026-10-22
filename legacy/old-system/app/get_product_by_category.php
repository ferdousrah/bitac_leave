<?php
include('connection.php');

$groupID = $_POST['groupID'];

$getAllItemsQ = mysqli_query($con, "select * from component_list where groupID='$groupID'");

while($dataRow=mysqli_fetch_array($getAllItemsQ))
{
 echo "<option value='$dataRow[componentCode]'>".$dataRow['componentCode'].' - '.$dataRow['componentTitle']."</option>";
}

?>