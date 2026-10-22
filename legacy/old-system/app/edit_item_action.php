<?php
include('connection.php');

$dataID = $_POST['dataID'];

$groupID = $_POST['groupID'];

$material_name = $_POST['material_name'];

$itemCode = $_POST['itemCode'];

$uom = $_POST['uom'];

$opening_stock = $_POST['opening_stock'];

$opening_date = $_POST['opening_date'];

$insertQuery = mysqli_query($con,"update `row_materials` set `material_name`='$material_name',`groupID`='$groupID',`itemCode`='$itemCode',`uom`='$uom',`stockQuantity`='$opening_stock' where `dataID`='$dataID'") or die(mysqli_error($con));

if($insertQuery==1)
{

    if($opening_stock>0)
	{

      $insertOpeningQQ = mysqli_query($con, "insert into material_opening_stock(`itemCode`,`quantity`,`storeID`,`date`) values('$itemCode','$opening_stock','1','$opening_date')");
   
    }



	echo 1;
}
else
{
	echo 0;
}


?>