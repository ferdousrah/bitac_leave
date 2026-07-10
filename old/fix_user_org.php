<?php
require_once(__DIR__ . '/config/connection.php');

// Update user_list.organization_id from employee_list via employee_id join
$sql = "UPDATE user_list ul
        INNER JOIN employee_list el ON ul.employee_id = el.id
        SET ul.organization_id = el.organization_id
        WHERE el.organization_id > 0";

if (mysqli_query($con, $sql)) {
    $affected = mysqli_affected_rows($con);
    echo "<p style='color:green;font-family:monospace;'>Done. $affected row(s) updated.</p>";
} else {
    echo "<p style='color:red;font-family:monospace;'>Error: " . mysqli_error($con) . "</p>";
}
?>
