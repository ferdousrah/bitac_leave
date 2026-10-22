<?php
include("connection.php");


function ShowBangladeshDate()
{

$hour = gmdate("H");

$minute = gmdate("i");

$seconds = gmdate("s");

$day = gmdate("d");

$month = gmdate("m");

$year = gmdate("Y");

// This is the offset from the server time to Bangladesh time.

$hour = $hour + 6;

//return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

}

$sdate=date_create($_POST['receiveDate']); 

$arrivalDate = date_format($sdate,"Y-m-d");

$ref_no = $_POST['ref_no'];



$sl = 0;
foreach($_POST['productCode'] as $pcode)
{

$quantity = $_POST['quantity'][$sl];



$updateStockQ = mysqli_query($con,"update row_materials set stockQuantity=(stockQuantity + $quantity) where itemCode='$pcode' and storeID=1");

if($updateStockQ==1)
	{
$getItemInfoQ = mysqli_query($con, "select * from row_materials where itemCode='$pcode'");
$getItemInfoQRW = mysqli_fetch_assoc($getItemInfoQ);

$insertArrivalHistoryDataQ = mysqli_query($con,"insert into item_arrival_history(groupID,itemCode,material_name,quantity,arrivalDate,ref_no,storeTo) values('$getItemInfoQRW[groupID]','$pcode','$getItemInfoQRW[material_name]','$quantity','$arrivalDate','$ref_no',1)");
	}


$sl = $sl + 1;
}



echo "<script>alert('Success')</script>";

echo "<script>window.location='row_material_receiving?menuslug=row-material-receiving'</script>";



?>


