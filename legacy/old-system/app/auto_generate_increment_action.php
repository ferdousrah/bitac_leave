<?php
session_start();
include('connection.php');
include('bddate.php');

$todayDateTime = ShowBangladeshTime();

$incrementYear = $_POST['incrementYear'];

$employeeID = $_POST['employeeID'];

//$incrementYear = 2024;

//$employeeID = 0;


if($employeeID == 0){

$deleteQ = mysqli_query($con, "delete from yearly_salary_increment where incrementYear='$incrementYear'");

$getAllEmployeeQ = mysqli_query($con, "select * from employee_list where employment_status=1 or employment_status=2");

}else{

$deleteQ = mysqli_query($con, "delete from yearly_salary_increment where employeeID='$employeeID' and incrementYear='$incrementYear'");

$getAllEmployeeQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");

}

$getAllEmployeeQNumRows = mysqli_num_rows($getAllEmployeeQ);

//echo "Total Rows: ".$getAllEmployeeQNumRows;

$generateBy = 1;

while($empRow = mysqli_fetch_array($getAllEmployeeQ)){


	// get designation info
	$getJobtitleQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
	$getJobtitleQRW = mysqli_fetch_assoc($getJobtitleQ);


	$currentBasic = $empRow['basic_salary'];
	
	$pay_scale = $empRow['pay_scale'];
	
	$getIncremntSalaryQ = mysqli_query($con, "select * from payscale_distribution where pay_scale='$pay_scale' and basic_salary='$currentBasic'");
	$getIncremntSalaryQNumRows = mysqli_num_rows($getIncremntSalaryQ);


	$getIncremntSalaryQRW = mysqli_fetch_assoc($getIncremntSalaryQ);
	
	$getLinkedRowQ = mysqli_query($con, "select * from payscale_distribution where linkedWith='$getIncremntSalaryQRW[id]'");
	$getLinkedRowQRW = mysqli_fetch_assoc($getLinkedRowQ);
	
	$incrementSalary = $getLinkedRowQRW['basic_salary'];

	$newPayscaleID = $getLinkedRowQRW['pay_scale'];

	$getNewPayscaleQ = mysqli_query($con, "select * from `grade` where `id`='$newPayscaleID'");
	$getNewPayscaleQRW = mysqli_fetch_assoc($getNewPayscaleQ);

	$incrementAmount = $incrementSalary - $currentBasic;

	if($newPayscaleID ==''){
	
	$newPayscaleID = 0;
	
	}

	if($incrementSalary ==''){
	
	$incrementSalary = 0;

	}

	if($incrementAmount < 0){
	
	$incrementAmount = 0;
	
	}
	
	//echo "insert into yearly_salary_increment(incrementYear, employeeID, designation, section_id, organization_id, display_order, presentSalaryGrade, presentSalary, incrementSalaryGrade, incrementAmount, incrementSalary, genDateTime, salary_group_id, generateBy) values('$incrementYear', '$empRow[id]', '$empRow[designation]', '$empRow[section_id]', '$empRow[organization_id]', '$empRow[display_order]', '$pay_scale', '$currentBasic', '$newPayscaleID', '$incrementAmount', '$incrementSalary', '$todayDateTime', '$empRow[salary_group_id]', '$generateBy')"."<br>";

	$getIncrementSettings = mysqli_query($con,"select * from increment_settings where dataID=1");
	$getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

	$insertQ = mysqli_query($con, "insert into yearly_salary_increment(incrementYear, employeeID, designation, section_id, organization_id, display_order, presentSalaryGrade, presentSalary, incrementSalaryGrade, incrementAmount, incrementSalary, genDateTime, salary_group_id, generateBy, salary_increment_date, fileNo) values('$incrementYear', '$empRow[id]', '$empRow[designation]', '$empRow[section_id]', '$empRow[organization_id]', '$empRow[display_order]', '$pay_scale', '$currentBasic', '$newPayscaleID', '$incrementAmount', '$incrementSalary', '$todayDateTime', '$empRow[salary_group_id]', '$generateBy', '$getIncrementSettingsRW[salary_increment_date]', '$getIncrementSettingsRW[fileNo]')");




}

echo 1;

?>