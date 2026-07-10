<?php
session_start();
include('connection.php');
include('library/number_converter.php');

// Disable error display for clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

function dateDiffInDays($date1, $date2) {
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

// Get user info
$getUserInfoQ = mysqli_query($con, "select * from user_list where user_id='$_SESSION[username]'");
$getUserInfoQRW = mysqli_fetch_assoc($getUserInfoQ);

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getUserInfoQRW[employee_id]'");
$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$request = $_REQUEST;

$columns = array(
    0 => 'dataID',
    1 => 'employee_name',
    2 => 'employee_id',
    3 => 'approvedLeave',
    4 => 'previousOfficeOrder',
    5 => 'revisedLeave',
    6 => 'revisedOfficeOrder',
    7 => 'deductFrom',
    8 => 'submitDateTime',
    9 => 'action'
);

// Build query based on organization and user
$sql = "";
if($getEmployeeDetailsQRW['organization_id'] == 4){
    if($_SESSION['username'] == 'Saifullah' || $_SESSION['username'] == 'saifullah'){
        $sql = "SELECT * FROM leave_edit_data_for_approval WHERE signatory='$getUserInfoQRW[employee_id]' AND isApproved=0";
    }else if($_SESSION['username'] == '1661'){
        $sql = "SELECT * FROM leave_edit_data_for_approval WHERE prevSignatory='872' AND signatory='$getUserInfoQRW[employee_id]' AND isApproved=0";
    }
}else if($getEmployeeDetailsQRW['organization_id'] == 5){
    if($_SESSION['username'] == '1529'){
        $sql = "SELECT * FROM leave_edit_data_for_approval WHERE signatory='$getUserInfoQRW[employee_id]' AND isApproved=0";
    }else if($_SESSION['username'] == '1697'){
        $sql = "SELECT * FROM leave_edit_data_for_approval WHERE prevSignatory='1383' AND signatory='$getUserInfoQRW[employee_id]' AND isApproved=0";
    }
}

// If no query built, return empty result
if(empty($sql)){
    $json_data = array(
        "draw" => intval($request['draw']),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => array()
    );
    echo json_encode($json_data);
    exit;
}

$totalData = mysqli_num_rows(mysqli_query($con, $sql));
$totalFiltered = $totalData;

// Global search
if (!empty($request['search']['value'])) {
    $search_value = mysqli_real_escape_string($con, $request['search']['value']);
    // We'll search after joining with other tables
}

// Ordering
$orderColumnIndex = isset($request['order'][0]['column']) ? $request['order'][0]['column'] : 0;
$orderDir = isset($request['order'][0]['dir']) ? $request['order'][0]['dir'] : 'DESC';

$sql .= " ORDER BY dataID " . $orderDir . "
          LIMIT " . $request['start'] . " , " . $request['length'] . " ";

$query = mysqli_query($con, $sql);

$data = array();
$sl = $request['start'];

while ($lRow = mysqli_fetch_array($query)) {
    $sl = $sl + 1;

    $getApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$lRow[leaveApplicationID]'");
    $getApplicationDetailsQRW = mysqli_fetch_assoc($getApplicationDetailsQ);

    $getleaveEditDataQ = mysqli_query($con, "select * from leave_edit_data where leaveApplicationID='$lRow[leaveApplicationID]'");
    $getleaveEditDataQRW = mysqli_fetch_assoc($getleaveEditDataQ);

    $getEmployeeQ = mysqli_query($con, "select * from employee_list where id='$getApplicationDetailsQRW[applicantID]'");
    $getEmployeeQW = mysqli_fetch_assoc($getEmployeeQ);

    $getDesignationQ = mysqli_query($con, "select * from job_title where id='$getEmployeeQW[designation]'");
    $getDesignationQRW = mysqli_fetch_assoc($getDesignationQ);

    $getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getApplicationDetailsQRW[approvedLeaveType]'");
    $getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

    // Approved leave
    $leaveApplicationDateF = date_create($getApplicationDetailsQRW['approvedDateFrom']);
    $leaveApplicationDateT = date_create($getApplicationDetailsQRW['approvedDateTo']);
    $totalReqDays = dateDiffInDays($getApplicationDetailsQRW['approvedDateFrom'], $getApplicationDetailsQRW['approvedDateTo']) + 1;

    $approvedLeaveHtml = banglaNumber(date_format($leaveApplicationDateF,"d/m/Y")) . ' হইতে ' . banglaNumber(date_format($leaveApplicationDateT,"d/m/Y")) . ', ' . banglaNumber($totalReqDays) . ' দিন, ' . htmlspecialchars($getLeaveTypeQRW['leaveTitle']);

    // Previous office order
    $previousOfficeOrderHtml = '<a target="_blank" href="leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $lRow['leaveApplicationID'] . '" class="btn btn-sm btn-info">
        <i class="ti tabler-file-description me-1"></i>অফিস আদেশ
    </a>';

    // Revised leave
    $getRevLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getleaveEditDataQRW[leaveType]'");
    $getRevLeaveTypeQRW = mysqli_fetch_assoc($getRevLeaveTypeQ);

    $revisedLeaveHtml = '';
    if($getleaveEditDataQRW['revisedLeaveFrom'] != '--'){
        $revisedLeaveF = date_create($getleaveEditDataQRW['revisedLeaveFrom']);
        $revisedLeaveT = date_create($getleaveEditDataQRW['revisedLeaveTo']);
        $revisedLeaveHtml = banglaNumber(date_format($revisedLeaveF,"d/m/Y")) . ' হইতে ' . banglaNumber(date_format($revisedLeaveT,"d/m/Y")) . ', ';
    }
    $totalRevDays = $getleaveEditDataQRW['revisedLeaveDay'];
    $revisedLeaveHtml .= banglaNumber($totalRevDays) . ' দিন, ' . htmlspecialchars($getRevLeaveTypeQRW['leaveTitle']);

    // Revised office order
    $revisedOfficeOrderHtml = '<a href="uploads/' . htmlspecialchars($getleaveEditDataQRW['attachment']) . '" target="_blank" class="btn btn-sm btn-secondary">
        <i class="ti tabler-file-text me-1"></i>View
    </a>';

    // Deduct from
    $deductFromType = '';
    if($getleaveEditDataQRW['deductFrom'] == 1){
        $deductFromType = "গড় বেতন";
    }else if($getleaveEditDataQRW['deductFrom'] == 2){
        $deductFromType = "অর্ধ-গড় বেতন";
    }else if($getleaveEditDataQRW['deductFrom'] == 3){
        $deductFromType = "নৈমিত্তিক (Casual Leave)";
    }else if($getleaveEditDataQRW['deductFrom'] == 4){
        $deductFromType = "বিনা বেতনে ছুটি";
    }else if($getleaveEditDataQRW['deductFrom'] == 5){
        $deductFromType = "ঐচ্ছিক ছুটি";
    }else if($getleaveEditDataQRW['deductFrom'] == 6){
        $deductFromType = "কর্তনহীন ছুটি";
    }else if($getleaveEditDataQRW['deductFrom'] == 10){
        $deductFromType = "অসাধারণ ছুটি";
    }

    // Action buttons
    $action = '<div class="btn-group" role="group">';
    $action .= '<button type="button" class="btn btn-sm btn-success" onclick="processapplication(' . $lRow['leaveApplicationID'] . ', 1, ' . $sl . ', ' . $getleaveEditDataQRW['dataID'] . ')" data-bs-toggle="tooltip" title="অনুমোদন">';
    $action .= '<i class="ti tabler-check"></i>';
    $action .= '</button>';
    $action .= '<button type="button" class="btn btn-sm btn-warning" onclick="processapplication(' . $lRow['leaveApplicationID'] . ', 2, ' . $sl . ', ' . $getleaveEditDataQRW['dataID'] . ')" data-bs-toggle="tooltip" title="প্রত্যাখ্যান">';
    $action .= '<i class="ti tabler-x"></i>';
    $action .= '</button>';
    $action .= '</div>';

    $nestedData = array();
    $nestedData['serial'] = $sl;
    $nestedData['employee_name'] = htmlspecialchars($getEmployeeQW['employee_name'] . ', ' . $getDesignationQRW['job_title_name']);
    $nestedData['employee_id'] = htmlspecialchars($getEmployeeQW['employee_id']);
    $nestedData['approved_leave'] = $approvedLeaveHtml;
    $nestedData['previous_office_order'] = $previousOfficeOrderHtml;
    $nestedData['revised_leave'] = $revisedLeaveHtml;
    $nestedData['revised_office_order'] = $revisedOfficeOrderHtml;
    $nestedData['deduct_from'] = htmlspecialchars($deductFromType);
    $nestedData['submit_datetime'] = htmlspecialchars($getleaveEditDataQRW['submitDateTime']);
    $nestedData['action'] = $action;

    $data[] = $nestedData;
}

$json_data = array(
    "draw" => intval($request['draw']),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
);

echo json_encode($json_data);
?>
