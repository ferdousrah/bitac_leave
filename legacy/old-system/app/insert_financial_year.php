<?php
include("connection.php");

$year1 = $_POST['year1'];

$year2 = $_POST['year2'];

$dateFrom = $_POST['dateFrom'];

$dateTo = $_POST['dateTo'];

$financial_year = $year1."-".$year2;

$checkForDuplicateDataQ = mysqli_query($con,"select * from financial_year where financial_year='$financial_year' and dateFrom='$dateFrom' and dateTo='$dateTo'");
$checkForDuplicateDataQNumRows = mysqli_num_rows($checkForDuplicateDataQ);

if($checkForDuplicateDataQNumRows>0)
{
$insertQ = mysqli_query($con,"update financial_year set financial_year='$financial_year',dateFrom='$dateFrom',dateTo='$dateTo' where financial_year='$financial_year' and dateFrom='$dateFrom' and dateTo='$dateTo'");
}
else
{
$insertQ = mysqli_query($con,"insert into financial_year(financial_year,dateFrom,dateTo) values('$financial_year','$dateFrom','$dateTo')");
}


if($insertQ==1)
{
  echo 1;
}
else
{
  echo 0;
  
}

?>