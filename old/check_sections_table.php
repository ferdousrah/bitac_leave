<?php
include('connection.php');

// Check table structure
$result = mysqli_query($con, "DESCRIBE sections");

echo "<h3>Sections Table Structure:</h3>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if deleted column exists
$check = mysqli_query($con, "SHOW COLUMNS FROM sections LIKE 'deleted'");
$hasDeleted = mysqli_num_rows($check) > 0;

echo "<h3>Has 'deleted' column: " . ($hasDeleted ? "YES" : "NO") . "</h3>";

// Show sample data
$data = mysqli_query($con, "SELECT * FROM sections LIMIT 5");
echo "<h3>Sample Data:</h3>";
echo "<table border='1'>";
$first = true;
while ($row = mysqli_fetch_assoc($data)) {
    if ($first) {
        echo "<tr>";
        foreach (array_keys($row) as $col) {
            echo "<th>" . $col . "</th>";
        }
        echo "</tr>";
        $first = false;
    }
    echo "<tr>";
    foreach ($row as $val) {
        echo "<td>" . $val . "</td>";
    }
    echo "</tr>";
}
echo "</table>";
?>
