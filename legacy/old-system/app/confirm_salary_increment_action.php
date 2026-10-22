<?php
include('connection.php');

$sql = mysqli_query($con, "SELECT * FROM `yearly_salary_increment` WHERE `incrementYear`=2025 and status=1");

while($datRow = mysqli_fetch_array($sql)){

	echo "update employee_list set basic_salary='$datRow[incrementSalary]' where id='$datRow[employeeID]'"."<br>";

	$updateQ = mysqli_query($con, "update employee_list set basic_salary='$datRow[incrementSalary]' where id='$datRow[employeeID]'");



}

?>