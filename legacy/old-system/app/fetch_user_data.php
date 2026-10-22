<?php
include('connection.php'); // Your database connection file

$limit = $_POST['length']; // Number of records per page
$start = $_POST['start'];  // Offset for pagination
$search = $_POST['search']['value']; // Search filter

$orderColumn = $_POST['order'][0]['column']; // Get the column to order by
$orderDirection = $_POST['order'][0]['dir']; // Get the direction (asc/desc)

$columns = ['full_name', 'designation', 'email', 'user_id']; // Column names

// Prepare the query with pagination, sorting, and search filter
$sql = "SELECT * FROM user_list";
if ($search) {
    $sql .= " WHERE full_name LIKE '%$search%' OR designation LIKE '%$search%' OR email LIKE '%$search%' OR user_id LIKE '%$search%'";
}
$sql .= " ORDER BY {$columns[$orderColumn]} $orderDirection LIMIT $start, $limit";

$result = mysqli_query($con, $sql);
$totalRecordsQuery = mysqli_query($con, "SELECT COUNT(*) FROM user_list");
$totalRecords = mysqli_fetch_row($totalRecordsQuery)[0];

$data = [];
$sl = $start + 1;

while ($row = mysqli_fetch_array($result)) {
    $data[] = [
        "sl" => $sl,
        "full_name" => $row['full_name'],
        "designation" => $row['designation'],
        "email" => $row['email'],
        "user_id" => $row['user_id'],
        "action" => '<button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location=\'edit_user_form?dataID=' . $row['dataID'] . '&menuslug=' . $_GET['menuslug'] . '\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-edit"></i></button> 
             <button data-toggle="tooltip" data-placement="top" data-original-title="Delete" onClick="removeData(' . $sl . ',' . $row['dataID'] . ')" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-trash-o"></i></button>
             <button data-toggle="tooltip" data-placement="top" data-original-title="User Access" onclick="window.location=\'fetch_user_access_record?menuslug=' . $_GET['menuslug'] . '&rowid=' . $row['dataID'] . '\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-bars"></i></button>'

    ];
    $sl++;
}

// Send response in JSON format
$response = [
    "draw" => $_POST['draw'],
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data" => $data
];

echo json_encode($response);
?>
