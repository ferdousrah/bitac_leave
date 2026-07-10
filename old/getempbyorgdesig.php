<?php
include('connection.php'); // or however you connect

if (isset($_GET['organization_id']) && isset($_GET['designation_id'])) {
    $orgID = intval($_GET['organization_id']);
    $desigID = intval($_GET['designation_id']);

    $sql = mysqli_query($con, "SELECT employee_id, employee_name FROM employee_list WHERE organization_id = '$orgID' AND designation = '$desigID' and employment_status=1");

	echo "<ul>";

	while($row = mysqli_fetch_array($sql)){

		echo "<li>".$row['employee_id'].' - '.$row['employee_name']."</li>";
	
	}

	echo "</ul>";

}
?>
