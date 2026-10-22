<?php
include('connection.php');

// Sample query (replace with your actual query)
$query = "SELECT * FROM employees WHERE status = 'active'";

// Execute the query
$result = mysqli_query($con, $query);

// Prepare data array for DataTables
$data = [];
$sl = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'sl' => $sl++,
        'photo' => $row['photo'] ?? '',
        'name_designation' => $row['name_designation'] ?? '',
        'id' => $row['id'] ?? '',
        'section' => $row['section'] ?? '',
        'center' => $row['center'] ?? '',
        'action' => '' // Add any action here if required
    ];
}

// Return JSON response
echo json_encode([
    'draw' => $_GET['draw'],
    'recordsTotal' => mysqli_num_rows($result),
    'recordsFiltered' => mysqli_num_rows($result),
    'data' => $data
]);
