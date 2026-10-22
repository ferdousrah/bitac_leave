<?php
include('connection.php');

$getIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='2023' and status=1");


while($dataRow = mysqli_fetch_array($getIncrementDataQ)){


echo "update employee_list set pay_scale='$dataRow[incrementSalaryGrade]', basic_salary='$dataRow[incrementSalary]' where id='$dataRow[employeeID]'"."<br>";


$updateEmpQ = mysqli_query($con, "update employee_list set pay_scale='$dataRow[incrementSalaryGrade]', basic_salary='$dataRow[incrementSalary]' where id='$dataRow[employeeID]'");


}



?>