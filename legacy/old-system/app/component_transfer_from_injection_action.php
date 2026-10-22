<?php
include("connection.php");

$transferFrom = $_POST['storeID'];

$transferTo = 0;

$ref_no = $_POST['ref_no'];

$transferDate = $_POST['transferDate'];

$sl = 0;
foreach($_POST['productCode'] as $componentCode)
{

$quantity = $_POST['quantity'][$sl];

$updateStockQ = mysqli_query($con, "update component_stock set stockQuantity=(stockQuantity - $quantity) where componentCode='$componentCode' and storeID='$transferFrom'");

if($updateStockQ==1)
	{
$insertTransferdataQ = mysqli_query($con, "insert into `component_transfer_data`(`ref_no`,`transferFrom`,`transferTo`,`transferDate`,`componentCode`,`quantity`,`isReceived`) values('$ref_no','$transferFrom','$transferTo','$transferDate','$componentCode','$quantity','1')");
	}


$sl = $sl + 1;
}

echo "<script>alert('Transfer Success!')</script>";
echo "<script>window.location='injection_delivery_component?submslug=injection-delivery-component&slug=injection-delivery-component&mslug=injection'</script>";



?>