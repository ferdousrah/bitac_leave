<?php
include("connection.php");

$dataID = $_POST['dataID'];

$year1 = $_POST['year1'];

$year2 = $_POST['year2'];

$dateFrom = $_POST['dateFrom'];

$dateTo = $_POST['dateTo'];

$financial_year = $year1."-".$year2;

$insertQ = mysqli_query($con,"update financial_year set financial_year='$financial_year',dateFrom='$dateFrom',dateTo='$dateTo' where dataID='$dataID'");


if($insertQ==1)
{
  echo 1;
}
else
{
  echo 0;
  
}

?>