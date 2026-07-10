<?php
session_start();
include('connection.php');

// Get session user info
$getUserInfoQ = mysqli_query($con, "select * from user_list where dataID='$_SESSION[userID]'");
$getUserInfoQRW = mysqli_fetch_assoc($getUserInfoQ);

// DataTables server-side parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// Base query
$baseQuery = "FROM leave_deduction_history
              WHERE employeeID='$getUserInfoQRW[employee_id]'
              AND isApproved=1";

// Search query
$searchQuery = "";
if(!empty($searchValue)) {
    $searchQuery = " AND (
        note LIKE '%$searchValue%'
        OR createDate LIKE '%$searchValue%'
    )";
}

// Count total records
$totalRecordsQuery = "SELECT COUNT(*) as total $baseQuery";
$totalRecordsResult = mysqli_query($con, $totalRecordsQuery);
$totalRecords = mysqli_fetch_assoc($totalRecordsResult)['total'];

// Count filtered records
$filteredRecordsQuery = "SELECT COUNT(*) as total $baseQuery $searchQuery";
$filteredRecordsResult = mysqli_query($con, $filteredRecordsQuery);
$filteredRecords = mysqli_fetch_assoc($filteredRecordsResult)['total'];

// Fetch data
$dataQuery = "SELECT * $baseQuery $searchQuery ORDER BY createDate DESC LIMIT $start, $length";
$dataResult = mysqli_query($con, $dataQuery);

$data = array();
$serial = $start + 1;

while($dataRow = mysqli_fetch_assoc($dataResult)) {
    // Determine leave type
    $approvedLeaveType = '';
    if($dataRow['leaveID'] == 1) {
        $approvedLeaveType = "গড় বেতন ";
    } else if($dataRow['leaveID'] == 2) {
        $approvedLeaveType = "অর্ধ-গড় বেতন ";
    } else if($dataRow['leaveID'] == 3) {
        $approvedLeaveType = "নৈমিত্তিক (Casual Leave)";
    } else if($dataRow['leaveID'] == 4) {
        $approvedLeaveType = "অসাধারণ(বিনা বেতনে ছুটি)";
    } else if($dataRow['leaveID'] == 5) {
        $approvedLeaveType = "ঐচ্ছিক ছুটি";
    }

    // Attachment button
    $attachment = '';
    if($dataRow['attachment'] != '') {
        $attachment = '<a href="uploads/'.htmlspecialchars($dataRow['attachment']).'" target="_blank" class="btn btn-sm btn-info">
                <i class="ti tabler-eye me-1"></i>View
            </a>';
    }

    $data[] = array(
        'serial' => $serial++,
        'leave_type' => $approvedLeaveType,
        'deduction_days' => htmlspecialchars($dataRow['leaveDeduction']),
        'note' => htmlspecialchars($dataRow['note']),
        'submit_date' => htmlspecialchars($dataRow['createDate']),
        'attachment' => $attachment
    );
}

// Response
$response = array(
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
);

echo json_encode($response);
?>
