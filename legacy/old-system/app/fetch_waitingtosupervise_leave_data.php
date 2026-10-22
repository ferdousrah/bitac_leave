<?php
session_start();
include('connection.php');
include('library/number_converter.php');

function dateDiffInDays($date1, $date2)
{
    // Calculating the difference in timestamps
    $diff = strtotime($date2) - strtotime($date1);

    // 1 day = 24 hours = 24 * 60 * 60 = 86400 seconds
    return abs(round($diff / 86400));
}

$username = mysqli_real_escape_string($con, $_SESSION['username']);

$getUserInfoQ = mysqli_query($con, "select * from user_list where user_id='$username'");
$getUserInfoQRW = mysqli_fetch_assoc($getUserInfoQ);

$employee_id = mysqli_real_escape_string($con, $getUserInfoQRW['employee_id']);

$request = $_REQUEST;

$columns = array(
    0 => 'dataID',
    1 => 'applicant_name',
    2 => 'section',
    3 => 'leave_type',
    4 => 'requested_leave_days',
    5 => 'proposed_leave_days',
    6 => 'proposed_leave_type',
    7 => 'action'
);

// Only these columns actually exist in the main query, so only these
// can be filtered / ordered in SQL. The rest are PHP-computed or come
// from separate queries.
$searchableColumns = array(
    'applicant_name' => 'employee_list.employee_name',
    'employee_id'    => 'employee_list.employee_id',
);

$orderableColumns = array(
    'dataID'         => 'leave_data_for_approval.dataID',
    'applicant_name' => 'employee_list.employee_name',
);

// ---- BASE QUERY ----
$sql = "select
            `leave_data_for_approval`.dataID,
            `leave_data_for_approval`.leaveApplicationID,
            `leave_data_for_approval`.signatory,
            `leave_data_for_approval`.isSupervisor,
            `leave_data_for_approval`.isSentbyAdmin,
            `leave_data_for_approval`.prevSignatory,
            `leave_data_for_approval`.isApproved,
            `leave_data_for_approval`.approvedDate,
            `leave_data_for_approval`.serial,
            `leave_data_for_approval`.approvedDays,
            `leave_applications`.applicantID,
            `leave_applications`.leaveType,
            `leave_applications`.dateFrom,
            `leave_applications`.dateTo,
            `leave_applications`.approvedDateFrom,
            `leave_applications`.approvedDateTo,
            `leave_applications`.leaveTypeInTwo,
            `leave_applications`.attachment,
            employee_list.employee_name as applicant_name,
            employee_list.employee_id as employee_id
        from `leave_data_for_approval`
        inner join `leave_applications`
            on `leave_data_for_approval`.leaveApplicationID = `leave_applications`.dataID
        inner join employee_list
            on `leave_applications`.applicantID = employee_list.id
        where `leave_data_for_approval`.signatory = '$employee_id'
            and `leave_data_for_approval`.isSupervisor = 1
            and `leave_data_for_approval`.isApproved = 0 ";

// ---- TOTAL (before filtering) ----
$totalData = mysqli_num_rows(mysqli_query($con, $sql));
$totalFiltered = $totalData;

// ---- SEARCH (global + per-column) ----
$where = array();

// Global search (the main DataTables search box)
if (!empty($request['search']['value'])) {
    $gs = mysqli_real_escape_string($con, $request['search']['value']);
    $or = array();
    foreach ($searchableColumns as $col) {
        $or[] = "$col LIKE '%$gs%'";
    }
    if (!empty($or)) {
        $where[] = "(" . implode(" OR ", $or) . ")";
    }
}

// Per-column search (only if you have individual column inputs in the frontend)
foreach ($columns as $key => $column) {
    if (!empty($request['columns'][$key]['search']['value']) && isset($searchableColumns[$column])) {
        $cv = mysqli_real_escape_string($con, $request['columns'][$key]['search']['value']);
        $where[] = $searchableColumns[$column] . " LIKE '%$cv%'";
    }
}

if (!empty($where)) {
    $sql .= " AND (" . implode(" AND ", $where) . ")";
}

// ---- FILTERED COUNT ----
$totalFiltered = mysqli_num_rows(mysqli_query($con, $sql));

// ---- ORDER (whitelisted, no direct injection) ----
$orderColIndex = isset($request['order'][0]['column']) ? (int) $request['order'][0]['column'] : 0;
$orderColKey   = isset($columns[$orderColIndex]) ? $columns[$orderColIndex] : 'dataID';
$orderColumn   = isset($orderableColumns[$orderColKey]) ? $orderableColumns[$orderColKey] : 'leave_data_for_approval.dataID';
$orderDir      = (isset($request['order'][0]['dir']) && strtolower($request['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';

$sql .= " ORDER BY $orderColumn $orderDir ";

// ---- LIMIT ----
$start  = isset($request['start'])  ? (int) $request['start']  : 0;
$length = isset($request['length']) ? (int) $request['length'] : 10;
$sql .= " LIMIT $start, $length ";

$query = mysqli_query($con, $sql);

$data = array();
$sl = $start;

while ($empRow = mysqli_fetch_array($query)) {

    // Fetch additional details
    $leaveAppID = mysqli_real_escape_string($con, $empRow['leaveApplicationID']);

    $getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$leaveAppID'");
    $getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

    $applicantID = mysqli_real_escape_string($con, $getLeaveApplicationDetailsQRW['applicantID']);
    $getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$applicantID'");
    $getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

    $designation = mysqli_real_escape_string($con, $getEmployeeDetailsQW['designation']);
    $getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$designation'");
    $getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

    $sectionId = mysqli_real_escape_string($con, $getEmployeeDetailsQW['section_id']);
    $getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$sectionId'");
    $getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

    $orgId = mysqli_real_escape_string($con, $getEmployeeDetailsQW['organization_id']);
    $getorgDetailsQ = mysqli_query($con, "select * from organization where id='$orgId'");
    $getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

    $leaveTypeId = mysqli_real_escape_string($con, $getLeaveApplicationDetailsQRW['leaveType']);
    $getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$leaveTypeId'");
    $getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

    // ---- Previous signatory check ----
    if ($empRow['prevSignatory'] == 0) {
        $proceed = 1;
    } else {
        $prevserial = $empRow['serial'] - 1;
        $prevSig    = mysqli_real_escape_string($con, $empRow['prevSignatory']);

        $checkPrevSognatorySignedQ = mysqli_query($con, "select * from `leave_data_for_approval` where leaveApplicationID='$leaveAppID' and signatory='$prevSig' and isApproved=1 and serial='$prevserial'");
        $checkPrevSognatorySignedQNumRows = mysqli_num_rows($checkPrevSognatorySignedQ);

        $proceed = ($checkPrevSognatorySignedQNumRows > 0) ? 1 : 0;
    }

    if ($proceed == 1) {

        $sl = $sl + 1;

        $dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;

        $dateF = date_create($getLeaveApplicationDetailsQRW['dateFrom']);
        $dateT = date_create($getLeaveApplicationDetailsQRW['dateTo']);

        // proposed
        if ($getLeaveApplicationDetailsQRW['approvedDateFrom'] != '' && $getLeaveApplicationDetailsQRW['approvedDateTo'] != '') {
            $adateF = date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
            $adateT = date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);
            $adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;
        } else {
            $adateDiff = "";
        }

        // proposed leave days
        if ($getLeaveApplicationDetailsQRW['approvedDateFrom'] != '' && $getLeaveApplicationDetailsQRW['approvedDateTo'] != '') {
            $proposed_leave_days = banglaNumber(date_format($adateF, "d/m/Y")) . ' হইতে ' . banglaNumber(date_format($adateT, "d/m/Y")) . ', ' . banglaNumber($adateDiff) . ' দিন';
        } else {
            $proposed_leave_days = "";
        }

        // proposed leave type
        switch ($getLeaveApplicationDetailsQRW['leaveTypeInTwo']) {
            case 1:  $proposed_leave_type = "গড় বেতন "; break;
            case 2:  $proposed_leave_type = "অর্ধ-গড় বেতন "; break;
            case 3:  $proposed_leave_type = "নৈমিত্তিক (Casual Leave)"; break;
            case 4:  $proposed_leave_type = "বিনা বেতনে ছুটি"; break;
            case 5:  $proposed_leave_type = "ঐচ্ছিক ছুটি"; break;
            case 6:  $proposed_leave_type = "সংগনিরোধ ছুটি"; break;
            case 7:  $proposed_leave_type = "প্রসূতি ছুটি"; break;
            case 8:  $proposed_leave_type = "অক্ষমতাজনিত বিশেষ ছুটি"; break;
            case 9:  $proposed_leave_type = "অধ্যয়ন ছুটি"; break;
            case 10: $proposed_leave_type = "অসাধারণ ছুটি"; break;
            default: $proposed_leave_type = ""; break;
        }

        // ---- Action buttons ----
        $action  = "<a target='_blank' href='leave_application_details.php?menuslug=leave-approval&leaveApplicationID={$empRow['leaveApplicationID']}'><img src='app-assets/form.png' height='32' /></a>&nbsp;&nbsp;&nbsp;";
        if ($getLeaveApplicationDetailsQRW['attachment'] != '') {
            $action .= "<a target='_blank' href='uploads/{$getLeaveApplicationDetailsQRW['attachment']}'><img src='app-assets/clip.png' height='32' /></a>&nbsp;&nbsp;&nbsp;";
        }
        $action .= "<a href='approve_leave_application.php?menuslug=leave-approval&dataID={$empRow['dataID']}&leaveApplicationID={$empRow['leaveApplicationID']}&isApproved=1'><img height='32' src='app-assets/check-mark.png' /></a>";

        $nestedData = array();
        $nestedData['serial']               = $sl;
        $nestedData['applicant_name']        = $empRow['applicant_name'] . ', ' . $getDesignationDetailsQRW['job_title_name'] . '(' . $empRow['employee_id'] . ')';
        $nestedData['section']               = $getSectionDetailsQRW['section_name'] . ", " . $getorgDetailsQRW['organization_name'];
        $nestedData['leave_type']            = $getLeaveTypeQRW['leaveTitle'];
        $nestedData['requested_leave_days']  = banglaNumber(date_format($dateF, "d/m/Y")) . ' হইতে ' . banglaNumber(date_format($dateT, "d/m/Y")) . ', ' . banglaNumber($dateDiff) . 'দিন';
        $nestedData['proposed_leave_days']   = $proposed_leave_days;
        $nestedData['proposed_leave_type']   = $proposed_leave_type;
        $nestedData['action']                = $action;

        $data[] = $nestedData;

        $proceed = 0;
    }
}

$json_data = array(
    "draw"            => intval($request['draw']),
    "recordsTotal"    => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data"            => $data
);

echo json_encode($json_data);

?>