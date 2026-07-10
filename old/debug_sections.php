<?php
include('connection.php');

echo "<h3>Debug Information for Sections Table</h3>";

// Check if deleted column exists
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM sections LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

echo "<p><strong>Has 'deleted' column:</strong> " . ($hasDeletedColumn ? "YES" : "NO") . "</p>";

// Show table structure
echo "<h4>Table Structure:</h4>";
$result = mysqli_query($con, "DESCRIBE sections");
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

// Count total records
$countQuery = "SELECT COUNT(*) as total FROM sections";
$countResult = mysqli_query($con, $countQuery);
$count = mysqli_fetch_assoc($countResult)['total'];
echo "<p><strong>Total records in sections table:</strong> " . $count . "</p>";

// Show all data
echo "<h4>All Data in Sections Table:</h4>";
$data = mysqli_query($con, "SELECT * FROM sections LIMIT 20");
if (mysqli_num_rows($data) > 0) {
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
            echo "<td>" . htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data found in sections table.</p>";
}

// Test the fetch query
echo "<h4>Test Fetch Query:</h4>";
$whereClause = "";
if ($hasDeletedColumn) {
    $whereClause = " WHERE deleted = 0";
}
$testQuery = "SELECT * FROM sections" . $whereClause . " LIMIT 10";
echo "<p><strong>Query:</strong> " . htmlspecialchars($testQuery) . "</p>";
$testResult = mysqli_query($con, $testQuery);
echo "<p><strong>Records found:</strong> " . mysqli_num_rows($testResult) . "</p>";

// Simulate AJAX request
echo "<h4>Simulated AJAX Response:</h4>";
$_POST['length'] = 10;
$_POST['start'] = 0;
$_POST['draw'] = 1;

ob_start();
include('fetch_section_data.php');
$response = ob_get_clean();

echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Decode and show formatted
$decoded = json_decode($response, true);
if ($decoded) {
    echo "<h5>Formatted Response:</h5>";
    echo "<pre>" . print_r($decoded, true) . "</pre>";
} else {
    echo "<p style='color: red;'>JSON decode error: " . json_last_error_msg() . "</p>";
}
?>
