<?php
include('connection.php');
include('bddate.php');

$tax_payer_id = $_POST['tax_payer_id'];


$todayDate = todayDate();



// insert tax collection data

	$sl = 0;
	foreach($_POST['financial_year'] as $fyear)
		{
          
		  $income = $_POST['income'][$sl];

		  $wealth = $_POST['wealth'][$sl];

		  $tax_paid = $_POST['tax_paid'][$sl];

		  $insertQ = mysqli_query($con,"insert into tax_collection_data(dataID, financial_year, income, wealth, tax_paid, entryDate) values('$tax_payer_id', '$fyear', '$income', '$wealth', '$tax_paid', '$todayDate')");


		  $sl = $sl + 1;
		}

echo "<script>alert('Success')</script>";
echo "<script>window.location='tax_collection'</script>";

?>