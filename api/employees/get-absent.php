<?php
require_once(__DIR__ . '/../../config/connection.php');
include_once('function.php');

function todayDate()
{

$hour = gmdate("H");

$minute = gmdate("i");

$seconds = gmdate("s");

$day = gmdate("d");

$month = gmdate("m");

$year = gmdate("Y");

// This is the offset from the server time to Bangladesh time.

$hour = $hour + 6;

//return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));

}


// Set pagination and filtering parameters
$limit = $_POST['length'];
$start = $_POST['start'];
$search = $_POST['search']['value']; // Search term

$todayDate = todayDate();

// Prepare the SQL query
$sql = "SELECT * FROM `leave_applications` 
        LEFT JOIN employee_list ON employee_list.id = leave_applications.applicantID
        LEFT JOIN job_title ON job_title.id = employee_list.designation
        LEFT JOIN sections ON sections.id = employee_list.section_id
        WHERE leave_applications.joiningDateAfterLeave < '$todayDate'
        AND leave_applications.status = 1
        AND leave_applications.dataID NOT IN (SELECT leaveApplicationID FROM leave_joining_application)";

// Apply search filter
if (!empty($search)) {
    $sql .= " AND (employee_list.employee_name LIKE '%$search%' OR employee_list.employee_id LIKE '%$search%')";
}

// Apply pagination
$sql .= " LIMIT $start, $limit";

// Execute the query
$result = mysqli_query($con, $sql);

// Get total records (for pagination)
$totalRecordsQuery = "SELECT COUNT(*) as total FROM `leave_applications` 
        LEFT JOIN employee_list ON employee_list.id = leave_applications.applicantID
        LEFT JOIN job_title ON job_title.id = employee_list.designation
        LEFT JOIN sections ON sections.id = employee_list.section_id
        WHERE leave_applications.joiningDateAfterLeave < '$todayDate'
        AND leave_applications.status = 1
        AND leave_applications.dataID NOT IN (SELECT leaveApplicationID FROM leave_joining_application)";
$totalRecordsResult = mysqli_query($con, $totalRecordsQuery);
$totalRecords = mysqli_fetch_assoc($totalRecordsResult)['total'];

// Prepare the data for DataTables
$data = [];
$sll = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $dateFrom = date_create($row['dateFrom']);
    $joiningDate = date_create($row['joiningDateAfterLeave']);
    
    $data[] = [
        'serial' => $sll++,  // Increment serial number
        'employee_name' => $row['employee_name'],
        'employee_id' => $obj->engToBn($row['employee_id']),
        'job_title' => $row['job_title_name'],
        'section_name' => $row['section_name'],
        'joining_date' => $obj->engToBn(date_format($joiningDate, "d/m/Y"))
    ];
}

// Return the response for DataTables
$response = [
    'draw' => $_POST['draw'],
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalRecords,  // You could apply filtering here as well
    'data' => $data
];

echo json_encode($response);

?>
