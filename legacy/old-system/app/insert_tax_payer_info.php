<?php
include('connection.php');
include('bddate.php');

$taxPayerName = $_POST['name'];

$eTIN = $_POST['etin'];


$etin_issue_date = $_POST['etin_issue_date'];

$address = $_POST['address'];

$email = $_POST['email'];

$phone = $_POST['phone'];

$todayDate = todayDate();


$insertQ = mysqli_query($con,"insert into tax_payer_info(taxPayerName, eTIN, etin_issue_date, address, email, phone) values('$taxPayerName', '$eTIN', '$etin_issue_date', '$address', '$email', '$phone')");

if($insertQ==1)
{

	$getLastIDQ = mysqli_query($con,"select * from tax_payer_info order by dataID desc LIMIT 0,1");
	$getLastIDQRW = mysqli_fetch_assoc($getLastIDQ);

// insert tax collection data

	$sl = 0;
	foreach($_POST['financial_year'] as $fyear)
		{
          
		  $income = $_POST['income'][$sl];

		  $wealth = $_POST['wealth'][$sl];

		  $taxDemand = $_POST['tax_paid'][$sl];

		  $insertQ = mysqli_query($con,"insert into tax_demand(dataID, financial_year, income, wealth, taxDemand, entryDate) values('$getLastIDQRW[dataID]', '$fyear', '$income', '$wealth', '$taxDemand', '$todayDate')");


		  $sl = $sl + 1;
		}


}

echo "<script>alert('Success')</script>";
echo "<script>window.location='new_tax_payer_info'</script>";

?>