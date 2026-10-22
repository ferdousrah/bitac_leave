<?php
include('connection.php');

$menuslug = $_POST['menuslug'];

$storeID = $_POST['storeID'];

$itemCode = $_POST['itemID'];

$opening_stock = $_POST['opening_stock'];

$opening_date = $_POST['opening_date'];

$checkForExistOrNotQ = mysqli_query($con, "select * from row_materials where itemCode='$itemCode' and storeID='$storeID'");
$checkForExistOrNotQNumRows = mysqli_num_rows($checkForExistOrNotQ);

if($checkForExistOrNotQNumRows>0)
{

}
else
{

$getItemInfoQ = mysqli_query($con, "select * from row_materials where itemCode='$itemCode'");
$getItemInfoQRW = mysqli_fetch_assoc($getItemInfoQ);

$insertItemInfoQ = mysqli_query($con, "insert into row_materials(material_name,groupID,itemCode,stockQuantity,uom,storeID) values('$getItemInfoQRW[material_name]','$getItemInfoQRW[groupID]','$getItemInfoQRW[itemCode]','0','$getItemInfoQRW[uom]','$storeID')");
}




$updateStockQuery = mysqli_query($con, "update row_materials set stockQuantity='$opening_stock' where storeID='$storeID' and itemCode='$itemCode'");

if($updateStockQuery==1)
{
    $deltPrvData = mysqli_query($con, "delete from material_opening_stock where storeID='$storeID' and itemCode='$itemCode'");

	$insertOpeningQQ = mysqli_query($con, "insert into material_opening_stock(`itemCode`,`quantity`,`storeID`,`date`) values('$itemCode','$opening_stock','$storeID','$opening_date')");

	if($insertOpeningQQ==1)
	{
	  echo 1;
	}
	else
	{
		echo 0;
	}

}
else
{
 echo 0;
}

?>