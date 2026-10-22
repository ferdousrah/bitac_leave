<?php
include('connection.php');

$menuslug = $_POST['menuslug'];

$storeID = $_POST['storeID'];

$groupID = $_POST['groupID'];

$componentID = $_POST['componentID'];

$opening_stock = $_POST['opening_stock'];

$opening_date = $_POST['opening_date'];

$checkForExistOrNotQ = mysqli_query($con, "SELECT * FROM `component_stock` where componentCode='$componentID' and storeID='$storeID'");
$checkForExistOrNotQNumRows = mysqli_num_rows($checkForExistOrNotQ);

if($checkForExistOrNotQNumRows>0)
{
$updateQ = mysqli_query($con, "update component_stock set stockQuantity=(stockQuantity + $opening_stock) where storeID='$storeID' and componentCode='$componentID'");
}
else
{
$updateQ = mysqli_query($con, "insert into component_stock(`componentCode`,`groupID`,`storeID`,`stockQuantity`) values('$componentID','$groupID','$storeID','$opening_stock')");
}


if($updateQ==1)
{
    $deltPrvData = mysqli_query($con, "delete from component_opening_stock where storeID='$storeID' and componentCode='$componentID'");

	$insertOpeningQQ = mysqli_query($con, "insert into component_opening_stock(`componentCode`,`quantity`,`storeID`,`date`) values('$componentID','$opening_stock','$storeID','$opening_date')");

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