<?php
session_start();
include('connection.php');
include('bddate.php');

$todayDate = todayDate();

foreach($_POST['listedemp'] as $dataID){

	$getDataDetailsQ = mysqli_query($con, "select * from `increment_data_for_approval` where dataID='$dataID'");
	$getDataDetailsQRW = mysqli_fetch_assoc($getDataDetailsQ);

	$dataRef = $getDataDetailsQRW['dataRef'];

	$loggedUserID = $_SESSION['userID'];

	$isApproved = 1;

	$checkLastManSigQ = mysqli_query($con, "select * from salary_increment_approvals order by approvalSL desc limit 0,1");
	$checkLastManSigQRW = mysqli_fetch_assoc($checkLastManSigQ);

	$lasrSig = $checkLastManSigQRW['employeeID'];


		if($isApproved == 1 && $loggedUserID == $lasrSig){

		$updateSignature = mysqli_query($con, "update increment_data_for_approval set isApproved=1 where dataID='$dataID' and signatory='$loggedUserID'");

		if($updateSignature == 1){

			$updateIncrementDataQ = mysqli_query($con, "update yearly_salary_increment set status=1, approvedDate='$todayDate'  where dataID='$dataRef'");


			if($updateIncrementDataQ == 1){

			$getYearlySalaryIncDataQ = mysqli_query($con, "select * from yearly_salary_increment where dataID='$dataRef'");
			$getYearlySalaryIncDataQRW = mysqli_fetch_assoc($getYearlySalaryIncDataQ);

			//$updateSalaryQ = mysqli_query($con, "update employee_list set basic_salary='$getYearlySalaryIncDataQRW[incrementSalary]' where id='$getYearlySalaryIncDataQRW[employeeID]'");

			$updateSalaryQ = mysqli_query($con, "update employee_list set pay_scale='$getYearlySalaryIncDataQRW[incrementSalaryGrade]', basic_salary='$getYearlySalaryIncDataQRW[incrementSalary]' where id='$getYearlySalaryIncDataQRW[employeeID]'");
		
		
			}


			//echo "<script>alert('Success')</script>";
			//echo "<script>window.location='approve_increment?menuslug=approve-increment'</script>";
		
		
		}


	}else if($isApproved == 1 && $loggedUserID != $lasrSig){

		$updateSignature = mysqli_query($con, "update increment_data_for_approval set isApproved=1 where dataID='$dataID' and signatory='$loggedUserID'");

		if($updateSignature == 1){


			//echo "<script>alert('Success')</script>";
			//echo "<script>window.location='approve_increment?menuslug=approve-increment'</script>";
		
		
		}


	}else if($isApproved == 2){

		$updateSignature = mysqli_query($con, "update increment_data_for_approval set isApproved=2 where dataID='$dataID' and signatory='$loggedUserID'");

		if($updateSignature == 1){

			//echo "<script>alert('Success')</script>";
			//echo "<script>window.location='approve_increment?menuslug=approve-increment'</script>";

		}


	}



	

} // end of foreach

?>