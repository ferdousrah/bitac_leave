<?php
include('connection.php'); // Database connection file
include('library/number_converter.php');

// Constants for columns to avoid hardcoding
define('COLUMNS', ['employee_name', 'employee_id', 'section_name', 'organization_name']);

// Get request parameters with sanitization
$limit = isset($_POST['length']) ? (int) $_POST['length'] : 10;  // Number of records per page
$start = isset($_POST['start']) ? (int) $_POST['start'] : 0;    // Offset for pagination
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : ''; // Search filter

$orderColumn = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;  // Get the column to order by
$orderDirection = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';  // Get the direction (asc/desc)

// SQL Query with JOIN to fetch employee data along with related information
$sql = "
    SELECT e.*, jt.job_title_name, s.section_name, o.organization_name 
    FROM employee_list e
    LEFT JOIN job_title jt ON jt.id = e.designation
    LEFT JOIN sections s ON s.id = e.section_id
    LEFT JOIN organization o ON o.id = e.organization_id
    WHERE e.employment_status = 1
";

// Apply search filter if available
if ($search) {
    $sql .= " AND (e.employee_name LIKE ? OR e.employee_id LIKE ? OR s.section_name LIKE ? OR o.organization_name LIKE ?)";
}

// Add sorting and pagination
//$sql .= " ORDER BY " . COLUMNS[$orderColumn] . " $orderDirection LIMIT ?, ?";

$sql .= " ORDER BY e.display_order asc " . " LIMIT ?, ?";

// Prepare the query
$stmt = mysqli_prepare($con, $sql);
if ($search) {
    // Bind parameters with wildcards for search
    $searchTerm = "%$search%";
    mysqli_stmt_bind_param($stmt, 'ssssii', $searchTerm, $searchTerm, $searchTerm, $searchTerm, $start, $limit);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $start, $limit);
}

// Execute the query
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch total records count for pagination
$totalRecordsQuery = mysqli_query($con, "SELECT COUNT(*) FROM employee_list WHERE employment_status = 1");
$totalRecords = mysqli_fetch_row($totalRecordsQuery)[0];

$data = [];
$sl = $start + 1;

// Process the results
while ($row = mysqli_fetch_array($result)) {
    $data[] = [
        "sl" => $sl,
        "photo" => '<img src="uploads/' . ($row['photo'] ? $row['photo'] : 'default-avatar.png') . '" style="border-radius: 50%;" height="60" />',
        "employee_name" => '<a href="employee_details?menuslug=manage-employee&employeeID=' . base64_encode($row['id']) . '">' . $row['employee_name'] . '</a><br>' . $row['job_title_name'],
        "employee_id" => bn2enNumber($row['employee_id']),
        "section_name" => $row['section_name'],
        "organization_name" => $row['organization_name'],
        "action" => '<button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location=\'edit_employee_info_form?dataID=' . base64_encode($row['id']) . '&menuslug=manage-employee\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-edit"></i></button>
                    <button data-toggle="tooltip" data-placement="top" data-original-title="Delete" onClick="removeData(' . $sl . ',' . $row['id'] . ')" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-trash-o"></i></button>
                    <button data-toggle="tooltip" data-placement="top" data-original-title="Previous Leave" onclick="window.location=\'previous_leave_info_form?dataID=' . base64_encode($row['id']) . '&menuslug=manage-employee\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-sort-amount-asc"></i></button>'
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

// Output the response
echo json_encode($response);

// Close statement and connection
mysqli_stmt_close($stmt);
mysqli_close($con);
?>
