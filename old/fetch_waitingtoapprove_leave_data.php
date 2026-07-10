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
        1 => "গড় বেতন",
        2 => "অর্ধ-গড় বেতন",
        3 => "নৈমিত্তিক (Casual Leave)",
        4 => "বিনা বেতনে ছুটি",
        5 => "ঐচ্ছিক ছুটি",
        6 => "সংগনিরোধ ছুটি",
        7 => "প্রসূতি ছুটি",
        8 => "অক্ষমতাজনিত বিশেষ ছুটি",
        9 => "অধ্যয়ন ছুটি",
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

// Columns for ordering
$columns = [
    0 => 'leave_data_for_approval.dataID',
    1 => 'employee_list.employee_name',
    2 => 'employee_list.section_id', // or a proper joined alias
    3 => 'leave_applications.leaveType',
    4 => 'leave_applications.dateFrom',
    5 => 'leave_applications.approvedDateFrom',
    6 => 'leave_applications.leaveTypeInTwo',
    7 => '' // action is not a DB field, skip ordering on this column
];


// Base query
$sql_base = "FROM leave_data_for_approval 
    INNER JOIN leave_applications ON leave_data_for_approval.leaveApplicationID = leave_applications.dataID 
    INNER JOIN employee_list ON leave_applications.applicantID = employee_list.id 
    WHERE leave_data_for_approval.signatory = '$employee_id' 
    AND leave_data_for_approval.isSentbyAdmin = 1 
    AND leave_data_for_approval.isSupervisor != 1 
    AND leave_data_for_approval.isApproved = 0";

// Count total records
$totalData = mysqli_num_rows(mysqli_query($con, "SELECT leave_data_for_approval.dataID $sql_base"));
$totalFiltered = $totalData;

// Filtering by column search
$search_sql = "";
foreach ($columns as $key => $column) {
    if (!empty($request['columns'][$key]['search']['value'])) {
        $search_value = mysqli_real_escape_string($con, $request['columns'][$key]['search']['value']);
        $search_sql .= " AND $column LIKE '%$search_value%'";
    }
}

// Count filtered records
$totalFiltered = mysqli_num_rows(mysqli_query($con, "SELECT leave_data_for_approval.dataID $sql_base $search_sql"));

// Sorting & Pagination
$start = intval($request['start']);
$length = intval($request['length']);
$columnIndex = intval($request['order'][0]['column']);
$sortColumn = $columns[$columnIndex];
$sortDir = $request['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

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

$totalD = 0;

while ($row = mysqli_fetch_assoc($query)) {
    $proceed = 0;

    // Check previous signatory approval
    if ($row['prevSignatory'] == 0) {
        $proceed = 1;
    } else {
        $prevserial = $row['serial'] - 1;
        $checkPrev = mysqli_query($con, "SELECT * FROM leave_data_for_approval 
            WHERE leaveApplicationID = '{$row['applicationID']}' 
            AND signatory = '{$row['prevSignatory']}' 
            AND isApproved = 1 
            AND serial = '$prevserial'");
        if (mysqli_num_rows($checkPrev) > 0) {
            $proceed = 1;
        }
    }

    if ($proceed) {
        $serial++;

		$totalD++;

        // Get related info
        $empQ = mysqli_query($con, "SELECT e.*, j.job_title_name, s.section_name, o.organization_name 
            FROM employee_list e 
            LEFT JOIN job_title j ON e.designation = j.id 
            LEFT JOIN sections s ON e.section_id = s.id 
            LEFT JOIN organization o ON e.organization_id = o.id 
            WHERE e.id = '{$row['applicantID']}'");
        $emp = mysqli_fetch_assoc($empQ);

        $leaveTypeQ = mysqli_query($con, "SELECT leaveTitle FROM leave_types WHERE leaveID = '{$row['leaveType']}'");
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
        $nestedData['serial'] = $serial."-".$row['applicationID'];
        $nestedData['applicant_name'] = $row['employee_name'] . " (" . banglaNumber($row['employee_id']) . "), " . $emp['job_title_name'];
        $nestedData['section'] = $emp['section_name'] . ", " . $emp['organization_name'];
        $nestedData['leave_type'] = $leaveType;
        $nestedData['requested_leave_days'] = $req_leave_days;
        $nestedData['proposed_leave_days'] = $proposed_leave_days;
        $nestedData['proposed_leave_type'] = $proposed_leave_type;
        $nestedData['action'] = $action;

        $data[] = $nestedData;
    }
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
