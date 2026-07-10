<?php
include('connection.php'); // Database connection file
include('library/number_converter.php');

// Constants for columns to avoid hardcoding
define('COLUMNS', ['employee_name', 'employee_id', 'section_name', 'organization_name']);

// Get request parameters with sanitization
$limit = isset($_POST['length']) ? (int) $_POST['length'] : 10;  // Number of records per page
$start = isset($_POST['start']) ? (int) $_POST['start'] : 0;    // Offset for pagination
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : ''; // Global search filter
$centerId = isset($_POST['center_id']) ? (int) $_POST['center_id'] : 0; // Center/Organization filter

// Get column-specific search values
$searchName = isset($_POST['columns'][2]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][2]['search']['value']) : '';
$searchID = isset($_POST['columns'][3]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][3]['search']['value']) : '';
$searchCenter = isset($_POST['columns'][4]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][4]['search']['value']) : '';
$searchSection = isset($_POST['columns'][5]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][5]['search']['value']) : '';

$orderColumn = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;  // Get the column to order by
$orderDirection = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';  // Get the direction (asc/desc)

// Validate orderColumn to avoid SQL injection
$orderColumn = in_array($orderColumn, range(0, count(COLUMNS) - 1)) ? COLUMNS[$orderColumn] : COLUMNS[0];

// SQL Query with JOIN to fetch employee data along with related information
$sql = "
    SELECT e.*, jt.job_title_name, s.section_name, o.organization_name
    FROM employee_list e
    LEFT JOIN job_title jt ON jt.id = e.designation
    LEFT JOIN sections s ON s.id = e.section_id
    LEFT JOIN organization o ON o.id = e.organization_id
    WHERE (e.employment_status = 0 OR e.employment_status = 2)
";

// Apply center filter if provided
if ($centerId > 0) {
    $sql .= " AND e.organization_id = " . $centerId;
}

// Apply global search filter if available (for main search box)
if ($search) {
    $sql .= " AND (e.employee_name LIKE '%" . $search . "%' OR e.employee_id LIKE '%" . $search . "%' OR s.section_name LIKE '%" . $search . "%' OR o.organization_name LIKE '%" . $search . "%')";
}

// Apply column-specific search filters
$columnSearchConditions = [];
if ($searchName) {
    $columnSearchConditions[] = "e.employee_name LIKE '%" . $searchName . "%'";
}
if ($searchID) {
    $columnSearchConditions[] = "e.employee_id LIKE '%" . $searchID . "%'";
}
if ($searchCenter) {
    $columnSearchConditions[] = "o.organization_name LIKE '%" . $searchCenter . "%'";
}
if ($searchSection) {
    $columnSearchConditions[] = "s.section_name LIKE '%" . $searchSection . "%'";
}

if (!empty($columnSearchConditions)) {
    $sql .= " AND (" . implode(" AND ", $columnSearchConditions) . ")";
}

// Add sorting and pagination
$sql .= " ORDER BY e.display_order ASC LIMIT " . $start . ", " . $limit;

// Execute the query directly (no prepared statement needed since we escaped the values)
$result = mysqli_query($con, $sql);

// Check for query error
if (!$result) {
    echo json_encode([
        "draw" => isset($_POST['draw']) ? $_POST['draw'] : 0,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => mysqli_error($con)
    ]);
    exit;
}

// Fetch total records count for pagination (with same filters)
$totalRecordsSql = "SELECT COUNT(*) FROM employee_list e
    LEFT JOIN job_title jt ON jt.id = e.designation
    LEFT JOIN sections s ON s.id = e.section_id
    LEFT JOIN organization o ON o.id = e.organization_id
    WHERE (e.employment_status = 0 OR e.employment_status = 2)";

if ($centerId > 0) {
    $totalRecordsSql .= " AND e.organization_id = " . $centerId;
}

// Apply the same search filters to count query
if ($search) {
    $totalRecordsSql .= " AND (e.employee_name LIKE '%" . $search . "%' OR e.employee_id LIKE '%" . $search . "%' OR s.section_name LIKE '%" . $search . "%' OR o.organization_name LIKE '%" . $search . "%')";
}

if (!empty($columnSearchConditions)) {
    $totalRecordsSql .= " AND (" . implode(" AND ", $columnSearchConditions) . ")";
}

$totalRecordsQuery = mysqli_query($con, $totalRecordsSql);
$totalRecords = mysqli_fetch_row($totalRecordsQuery)[0];

$data = [];
$sl = $start + 1;

// Process the results
while ($row = mysqli_fetch_array($result)) {
    // Ensure ID is always an integer
    $empId = (int)($row['id'] ?? 0);

    // Build data array - always include organization_name to match frontend columns
    $data[] = [
        "sl" => $sl,
        "photo" => '<img src="uploads/' . ($row['photo'] ? $row['photo'] : 'default-avatar.png') . '" class="rounded-circle" height="50" width="50" />',
        "employee_name" => '<a href="employee_details?menuslug=manage-employee&employeeID=' . base64_encode($empId) . '" class="text-heading fw-medium">' . $row['employee_name'] . '</a><br><small class="text-muted">' . ($row['job_title_name'] ?? '') . '</small>',
        "employee_id" => bn2enNumber($row['employee_id']),
        "organization_name" => $row['organization_name'] ?? '',
        "section_name" => $row['section_name'] ?? '',
        "action" => '<div class="d-flex gap-1">
                        <a href="edit_employee_info_form?dataID=' . base64_encode($empId) . '&menuslug=manage-employee" class="btn btn-sm btn-icon btn-outline-primary rounded-pill waves-effect" data-bs-toggle="tooltip" data-bs-placement="top" title="সম্পাদনা করুন">
                            <i class="icon-base ti tabler-edit icon-22px"></i>
                        </a>
                        <button onclick="removeData(' . $sl . ',' . $empId . ')" class="btn btn-sm btn-icon btn-outline-danger rounded-pill waves-effect" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন">
                            <i class="icon-base ti tabler-trash icon-22px"></i>
                        </button>
                        <a href="previous_leave_info_form?dataID=' . base64_encode($empId) . '&menuslug=manage-employee" class="btn btn-sm btn-icon btn-outline-secondary rounded-pill waves-effect" data-bs-toggle="tooltip" data-bs-placement="top" title="পূর্ববর্তী ছুটি">
                            <i class="icon-base ti tabler-history icon-22px"></i>
                        </a>
                    </div>'
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

// Close connection
mysqli_close($con);
?>
