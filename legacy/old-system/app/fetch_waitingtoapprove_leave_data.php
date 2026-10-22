<?php
session_start();
include('connection.php');
include('library/number_converter.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Helper: Calculate date difference in days
function dateDiffInDays($date1, $date2) {
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

// Helper: Leave type mapping
function getLeaveTypeText($type) {
    $types = [
        1 => "গড় বেতন",
        2 => "অর্ধ-গড় বেতন",
        3 => "নৈমিত্তিক (Casual Leave)",
        4 => "বিনা বেতনে ছুটি",
        5 => "ঐচ্ছিক ছুটি",
        6 => "সংগনিরোধ ছুটি",
        7 => "প্রসূতি ছুটি",
        8 => "অক্ষমতাজনিত বিশেষ ছুটি",
        9 => "অধ্যয়ন ছুটি",
        10 => "অসাধারণ ছুটি"
    ];
    return $types[$type] ?? '';
}

// Sanitize and fetch user
$username = mysqli_real_escape_string($con, $_SESSION['username']);
$getUserInfoQ = mysqli_query($con, "SELECT * FROM user_list WHERE user_id='$username'");
$getUserInfo = mysqli_fetch_assoc($getUserInfoQ);
$employee_id = mysqli_real_escape_string($con, $getUserInfo['employee_id']);

$request = $_REQUEST;

// Column index -> DB column (for ORDER BY). Empty string = not orderable.
$columns = [
    0 => 'leave_data_for_approval.dataID',
    1 => 'employee_list.employee_name',
    2 => 'employee_list.section_id',
    3 => 'leave_applications.leaveType',
    4 => 'leave_applications.dateFrom',
    5 => 'leave_applications.approvedDateFrom',
    6 => 'leave_applications.leaveTypeInTwo',
    7 => '' // action - not a DB field
];

// Columns that are real text fields in the base query and make sense to search.
$searchableColumns = [
    'employee_list.employee_name',
    'employee_list.employee_id',
];

// Base query.
// The previous-signatory ("proceed") rule is now part of the WHERE clause
// via EXISTS, so the row count and pagination match what actually shows up.
$sql_base = "FROM leave_data_for_approval 
    INNER JOIN leave_applications ON leave_data_for_approval.leaveApplicationID = leave_applications.dataID 
    INNER JOIN employee_list ON leave_applications.applicantID = employee_list.id 
    WHERE leave_data_for_approval.signatory = '$employee_id' 
    AND leave_data_for_approval.isSentbyAdmin = 1 
    AND leave_data_for_approval.isSupervisor != 1 
    AND leave_data_for_approval.isApproved = 0 
    AND (
        leave_data_for_approval.prevSignatory = 0 
        OR EXISTS (
            SELECT 1 FROM leave_data_for_approval prev 
            WHERE prev.leaveApplicationID = leave_data_for_approval.leaveApplicationID 
              AND prev.signatory = leave_data_for_approval.prevSignatory 
              AND prev.isApproved = 1 
              AND prev.serial = leave_data_for_approval.serial - 1 
        )
    )";

// Count total records (before search filtering, but WITH the proceed rule)
$totalData = mysqli_num_rows(mysqli_query($con, "SELECT leave_data_for_approval.dataID $sql_base"));
$totalFiltered = $totalData;

// ---- FILTERING ----
$search_sql = "";

// Global search (the main DataTables search box)
if (!empty($request['search']['value'])) {
    $gs = mysqli_real_escape_string($con, $request['search']['value']);
    $or = [];
    foreach ($searchableColumns as $col) {
        $or[] = "$col LIKE '%$gs%'";
    }
    if (!empty($or)) {
        $search_sql .= " AND (" . implode(" OR ", $or) . ")";
    }
}

// Per-column search (only if individual column inputs exist in the frontend)
foreach ($columns as $key => $column) {
    if ($column !== '' && in_array($column, $searchableColumns) && !empty($request['columns'][$key]['search']['value'])) {
        $search_value = mysqli_real_escape_string($con, $request['columns'][$key]['search']['value']);
        $search_sql .= " AND $column LIKE '%$search_value%'";
    }
}

// Count filtered records.
// When there is no search term, the filtered count == total count,
// so we skip the second heavy query entirely.
if ($search_sql !== "") {
    $totalFiltered = mysqli_num_rows(mysqli_query($con, "SELECT leave_data_for_approval.dataID $sql_base $search_sql"));
} else {
    $totalFiltered = $totalData;
}

// ---- SORTING & PAGINATION ----
$start  = intval($request['start']);
$length = intval($request['length']);

$columnIndex = isset($request['order'][0]['column']) ? intval($request['order'][0]['column']) : 0;
$sortColumn  = (isset($columns[$columnIndex]) && $columns[$columnIndex] !== '')
    ? $columns[$columnIndex]
    : 'leave_data_for_approval.dataID'; // fallback for non-orderable / invalid column
$sortDir = (isset($request['order'][0]['dir']) && $request['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';

$sql = "SELECT 
    leave_data_for_approval.dataID AS approvalDataID, 
    leave_applications.dataID AS applicationID, 
    leave_data_for_approval.*, 
    leave_applications.*, 
    employee_list.employee_name, 
    employee_list.employee_id 
$sql_base $search_sql 
ORDER BY $sortColumn $sortDir 
LIMIT $start, $length";

$query = mysqli_query($con, $sql);

$data = [];
$serial = $start;

// No more $proceed check here — every fetched row already qualifies.
while ($row = mysqli_fetch_assoc($query)) {
    $serial++;

    // Get related info
    $applicantID = mysqli_real_escape_string($con, $row['applicantID']);
    $empQ = mysqli_query($con, "SELECT e.*, j.job_title_name, s.section_name, o.organization_name 
        FROM employee_list e 
        LEFT JOIN job_title j ON e.designation = j.id 
        LEFT JOIN sections s ON e.section_id = s.id 
        LEFT JOIN organization o ON e.organization_id = o.id 
        WHERE e.id = '$applicantID'");
    $emp = mysqli_fetch_assoc($empQ);

    $leaveTypeId = mysqli_real_escape_string($con, $row['leaveType']);
    $leaveTypeQ = mysqli_query($con, "SELECT leaveTitle FROM leave_types WHERE leaveID = '$leaveTypeId'");
    $leaveType = mysqli_fetch_assoc($leaveTypeQ)['leaveTitle'] ?? '';

    // Date calculation
    $dateFrom = $row['dateFrom'];
    $dateTo = $row['dateTo'];
    $requestedDays = dateDiffInDays($dateFrom, $dateTo) + 1;
    $req_leave_days = banglaNumber(date('d/m/Y', strtotime($dateFrom))) . ' হইতে ' . banglaNumber(date('d/m/Y', strtotime($dateTo))) . ', ' . banglaNumber($requestedDays) . 'দিন';

    $proposed_leave_days = "";
    if (!empty($row['approvedDateFrom']) && !empty($row['approvedDateTo'])) {
        $proposedDays = dateDiffInDays($row['approvedDateFrom'], $row['approvedDateTo']) + 1;
        $proposed_leave_days = banglaNumber(date('d/m/Y', strtotime($row['approvedDateFrom']))) . ' হইতে ' . banglaNumber(date('d/m/Y', strtotime($row['approvedDateTo']))) . ', ' . banglaNumber($proposedDays) . ' দিন';
    }

    $proposed_leave_type = getLeaveTypeText($row['leaveTypeInTwo']);

    // Action buttons
    $action = "<a target='_blank' href='leave_application_details.php?menuslug=leave-approval&leaveApplicationID={$row['applicationID']}'><img src='app-assets/form.png' height='32' /></a>&nbsp;";
    if (!empty($row['attachment'])) {
        $action .= "<a target='_blank' href='uploads/{$row['attachment']}'><img src='app-assets/clip.png' height='32' /></a>&nbsp;";
    }
    $action .= "<a href='approve_leave_application.php?menuslug=leave-approval&dataID={$row['approvalDataID']}&leaveApplicationID={$row['applicationID']}&isApproved=1'><img height='32' src='app-assets/check-mark.png' /></a>";

    $nestedData = [];
    $nestedData['serial'] = $serial;
    $nestedData['applicant_name'] = $row['employee_name'] . " (" . banglaNumber($row['employee_id']) . "), " . $emp['job_title_name'];
    $nestedData['section'] = $emp['section_name'] . ", " . $emp['organization_name'];
    $nestedData['leave_type'] = $leaveType;
    $nestedData['requested_leave_days'] = $req_leave_days;
    $nestedData['proposed_leave_days'] = $proposed_leave_days;
    $nestedData['proposed_leave_type'] = $proposed_leave_type;
    $nestedData['action'] = $action;

    $data[] = $nestedData;
}


$json_data = [
    "draw" => intval($request['draw']),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
];

header('Content-Type: application/json');
echo json_encode($json_data);
?>