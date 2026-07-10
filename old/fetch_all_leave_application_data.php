<?php
session_start();
include('connection.php');
include('library/number_converter.php');

// Get session user info
$getUserInfoQ = mysqli_query($con, "select * from user_list where dataID='$_SESSION[userID]'");
$getUserInfoQRW = mysqli_fetch_assoc($getUserInfoQ);

// DataTables server-side parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// Base query
$baseQuery = "FROM leave_applications
              WHERE status!=3
              AND (applicantID='$getUserInfoQRW[employee_id]' OR submitBy='$_SESSION[userID]')";

// Search query
$searchQuery = "";
if(!empty($searchValue)) {
    $searchQuery = " AND (
        dataID LIKE '%$searchValue%'
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
$dataQuery = "SELECT * $baseQuery $searchQuery ORDER BY dataID DESC LIMIT $start, $length";
$dataResult = mysqli_query($con, $dataQuery);

$data = array();
$serial = $start + 1;

while($lRow = mysqli_fetch_assoc($dataResult)) {
    // Get employee details
    $getEmpDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$lRow[applicantID]'");
    $getEmpDetailsQRW = mysqli_fetch_assoc($getEmpDetailsQ);

    $getDesignationDetailsQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='$getEmpDetailsQRW[designation]'");
    $getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

    $getSectionDetailsQ = mysqli_query($con, "SELECT * FROM sections WHERE id='$getEmpDetailsQRW[section_id]'");
    $getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

    $getLeaveTypeQ = mysqli_query($con, "SELECT * FROM leave_types WHERE leaveID='$lRow[leaveType]'");
    $getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

    $getAppLeaveTypeQ = mysqli_query($con, "SELECT * FROM leave_types WHERE leaveID='$lRow[approvedLeaveType]'");
    $getAppLeaveTypeQRW = mysqli_fetch_assoc($getAppLeaveTypeQ);

    $getApplicationTypeQ = mysqli_query($con, "SELECT * FROM leave_joining_application WHERE leaveApplicationID='$lRow[dataID]'");
    $getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);
    $getLeaveJoiningApplicationQNumRows = mysqli_num_rows($getApplicationTypeQ);

    // Employee info
    $employee_info = htmlspecialchars($getEmpDetailsQRW['employee_name']).', '.htmlspecialchars($getDesignationDetailsQRW['job_title_name']).', '.htmlspecialchars($getSectionDetailsQRW['section_name']);

    // Requested leave
    $leaveApplicationDateF = date_create($lRow['dateFrom']);
    $leaveApplicationDateT = date_create($lRow['dateTo']);
    $totalReqDays = dateDiffInDays($lRow['dateFrom'], $lRow['dateTo']) + 1;
    $requested_leave = banglaNumber(date_format($leaveApplicationDateF,"d/m/Y")).' হইতে '.banglaNumber(date_format($leaveApplicationDateT,"d/m/Y")).', '.banglaNumber($totalReqDays).' দিন '.htmlspecialchars($getLeaveTypeQRW['leaveTitle']);

    // Approved leave
    $approved_leave = '';
    if($lRow['status'] == 1 && $getAppLeaveTypeQRW) {
        $adateF = date_create($lRow['primaryLeaveDateFrom']);
        $adateT = date_create($lRow['primaryLeaveDateTo']);
        $adateDiff = dateDiffInDays($lRow['primaryLeaveDateFrom'], $lRow['primaryLeaveDateTo']) + 1;

        $leaveTypeText = '';
        if($lRow['primaryApprovedLeaveType'] == 1) $leaveTypeText = "গড় বেতন";
        else if($lRow['primaryApprovedLeaveType'] == 2) $leaveTypeText = "অর্ধ-গড় বেতন";
        else if($lRow['primaryApprovedLeaveType'] == 3) $leaveTypeText = "নৈমিত্তিক (Casual Leave)";
        else if($lRow['primaryApprovedLeaveType'] == 4) $leaveTypeText = "বিনা বেতনে ছুটি";
        else if($lRow['primaryApprovedLeaveType'] == 5) $leaveTypeText = "ঐচ্ছিক ছুটি";
        else if($lRow['primaryApprovedLeaveType'] == 6) $leaveTypeText = "কর্তনহীন ছুটি";
        else if($lRow['primaryApprovedLeaveType'] == 10) $leaveTypeText = "অসাধারণ ছুটি";

        $approved_leave = htmlspecialchars($getAppLeaveTypeQRW['leaveTitle']).'-'.banglaNumber(date_format($adateF,"d/m/Y")).' হইতে '.banglaNumber(date_format($adateT,"d/m/Y")).', '.banglaNumber($adateDiff).' দিন '.$leaveTypeText;
    }

    // Spent leave
    $spent_leave = '';
    if($getLeaveJoiningApplicationQNumRows > 0) {
        $leaveSpentDateFrom = date_create($lRow['primaryLeaveDateFrom']);
        $leaveSpentDateTo = date_create($getApplicationTypeQRW['requestedJoiningDate']);
        $leaveSpent = dateDiffInDays($lRow['primaryLeaveDateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;

        $leaveTypeText = '';
        if($lRow['primaryApprovedLeaveType'] == 1) $leaveTypeText = "গড় বেতন";
        else if($lRow['primaryApprovedLeaveType'] == 2) $leaveTypeText = "অর্ধ-গড় বেতন";
        else if($lRow['primaryApprovedLeaveType'] == 3) $leaveTypeText = "নৈমিত্তিক (Casual Leave)";
        else if($lRow['primaryApprovedLeaveType'] == 4) $leaveTypeText = "বিনা বেতনে ছুটি";
        else if($lRow['primaryApprovedLeaveType'] == 5) $leaveTypeText = "ঐচ্ছিক ছুটি";
        else if($lRow['primaryApprovedLeaveType'] == 6) $leaveTypeText = "কর্তনহীন ছুটি";
        else if($lRow['primaryApprovedLeaveType'] == 10) $leaveTypeText = "অসাধারণ ছুটি";

        $spent_leave = banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")).' হইতে '.banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")).', '.banglaNumber($leaveSpent).' দিন '.$leaveTypeText;
    }

    // Joining type
    $joining_type = '';
    if($getLeaveJoiningApplicationQNumRows > 0) {
        if($getApplicationTypeQRW['joiningType'] == 1) $joining_type = "সঠিক সময়ে যোগদান";
        else if($getApplicationTypeQRW['joiningType'] == 2) $joining_type = "অগ্রিম যোগদান";
        else if($getApplicationTypeQRW['joiningType'] == 3) $joining_type = "বর্ধিত ছুটির আবেদন";
    }

    // Corrected leave
    $corrected_leave = '';
    if($getLeaveJoiningApplicationQNumRows > 0 && $getApplicationTypeQRW['approvedDate'] != '') {
        $correctionJoiningDate = date_create($lRow['approvedDateTo']);
        $correctedLeaveSpent = dateDiffInDays($lRow['primaryLeaveDateFrom'], $lRow['approvedDateTo']) + 1;

        $leaveTypeText = '';
        if($getApplicationTypeQRW['approvedLeaveType'] == 1) $leaveTypeText = "গড় বেতন";
        else if($getApplicationTypeQRW['approvedLeaveType'] == 2) $leaveTypeText = "অর্ধ-গড় বেতন";
        else if($getApplicationTypeQRW['approvedLeaveType'] == 3) $leaveTypeText = "নৈমিত্তিক (Casual Leave)";
        else if($getApplicationTypeQRW['approvedLeaveType'] == 4) $leaveTypeText = "বিনা বেতনে ছুটি";
        else if($getApplicationTypeQRW['approvedLeaveType'] == 5) $leaveTypeText = "ঐচ্ছিক ছুটি";
        else if($getApplicationTypeQRW['approvedLeaveType'] == 6) $leaveTypeText = "কর্তনহীন ছুটি";
        else if($getApplicationTypeQRW['approvedLeaveType'] == 10) $leaveTypeText = "অসাধারণ ছুটি";

        $corrected_leave = banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")).' হইতে '.banglaNumber(date_format($correctionJoiningDate,"d/m/Y")).', '.banglaNumber($correctedLeaveSpent).' দিন '.$leaveTypeText;
    }

    // Status
    $status = '';
    if($lRow['status'] == 1 && $getLeaveJoiningApplicationQNumRows > 0) {
        if($getApplicationTypeQRW['status'] == 0) {
            $status = '<span class="badge bg-primary">যোগদানপত্র অনুমোদনের জন্য অপেক্ষমান</span>';
        } else if($getApplicationTypeQRW['status'] == 2) {
            $status = '<span class="badge bg-danger">অনুমোদিত হয়নি</span>';
        } else if($getApplicationTypeQRW['status'] == 1) {
            $status = '<span class="badge bg-success">যোগদান পত্র অনুমোদন করা হয়েছে ।</span>';
        }
    } else if($lRow['status'] == 1 && $getLeaveJoiningApplicationQNumRows <= 0) {
        if($lRow['applicationType'] == 1) {
            $status = '<div class="btn-group">
                <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti tabler-user-check me-1"></i>কর্মক্ষেত্রে যোগদান
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="join_at_work_intime?applicationID='.$lRow['dataID'].'&menuslug=all-leave-application">
                        <i class="ti tabler-clock me-2"></i>সঠিক সময়ে যোগদান
                    </a></li>
                    <li><a class="dropdown-item" href="join_at_work_advance_joining?applicationID='.$lRow['dataID'].'&menuslug=all-leave-application">
                        <i class="ti tabler-calendar-minus me-2"></i>ছুটি পূর্ণ ভোগ না করে অগ্রিম যোগদান
                    </a></li>
                    <li><a class="dropdown-item" href="join_at_work_after_joining_date?applicationID='.$lRow['dataID'].'&menuslug=all-leave-application">
                        <i class="ti tabler-calendar-plus me-2"></i>বর্ধিত ছুটি মঞ্জুর ও কর্মস্থলে যোগদানের অনুমতি
                    </a></li>
                </ul>
            </div>';
        } else if($lRow['applicationType'] == 2) {
            $status = '<span class="badge bg-success">যোগদান পত্র ও ছুটি<br><br> অনুমোদন করা হয়েছে ।</span>';
        }
    } else if($lRow['status'] == 2 && $getLeaveJoiningApplicationQNumRows <= 0) {
        $status = '<span class="badge bg-danger">অনুমোদিত হয়নি</span>';
    }

    // Actions
    $checkIsSupervisorApproveQ = mysqli_query($con, "SELECT count(*) as totalApBySRow FROM leave_data_for_approval WHERE leaveApplicationID='$lRow[dataID]' AND isSupervisor=1 AND isApproved=0 AND isRead=0");
    $checkIsSupervisorApproveQRW = mysqli_fetch_assoc($checkIsSupervisorApproveQ);

    $actions = '<div class="btn-group">
        <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="ti tabler-folder"></i>
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="leave_application_details.php?menuslug=all-leave-application&leaveApplicationID='.$lRow['dataID'].'" target="_blank">
                <i class="ti tabler-file-text me-2"></i>আবেদনপত্র
            </a></li>';

    if($checkIsSupervisorApproveQRW['totalApBySRow'] == 1) {
        $actions .= '<li><a class="dropdown-item" href="edit_my_application?applicationID='.base64_encode($lRow['dataID']).'&menuslug=all-leave-application">
                <i class="ti tabler-edit me-2"></i>এডিট করুন
            </a></li>
            <li><a class="dropdown-item" href="javascript:void(0);" onClick="cancelApplication('.$lRow['dataID'].', \''.$lRow['dataID'].'\')">
                <i class="ti tabler-trash me-2"></i>ডিলিট
            </a></li>';
    }

    if($lRow['status'] == 1) {
        $actions .= '<li><a class="dropdown-item" href="leave_office_notice.php?menuslug=all-leave-application&leaveApplicationID='.$lRow['dataID'].'" target="_blank">
                <i class="ti tabler-file-description me-2"></i>অফিস আদেশ
            </a></li>';
    }

    if($getLeaveJoiningApplicationQNumRows > 0) {
        if($getApplicationTypeQRW['joiningType'] == 1) {
            $actions .= '<li><a class="dropdown-item" target="_blank" href="leave_joining_application_details?menuslug=all-leave-application&leaveApplicationID='.$lRow['dataID'].'">
                    <i class="ti tabler-file-check me-2"></i>যোগদান পত্র
                </a></li>';
        } else if($getApplicationTypeQRW['joiningType'] == 2) {
            $actions .= '<li><a class="dropdown-item" target="_blank" href="leave_joining_application_details_typetwo?menuslug=all-leave-application&leaveApplicationID='.$lRow['dataID'].'">
                    <i class="ti tabler-file-check me-2"></i>যোগদান পত্র
                </a></li>';
        } else if($getApplicationTypeQRW['joiningType'] == 3) {
            $actions .= '<li><a class="dropdown-item" target="_blank" href="leave_joining_application_details_typethree?menuslug=all-leave-application&leaveApplicationID='.$lRow['dataID'].'">
                    <i class="ti tabler-file-check me-2"></i>যোগদান পত্র
                </a></li>';
        }

        if($getApplicationTypeQRW['status'] == 1) {
            $actions .= '<li><a class="dropdown-item" href="updated_leave_office_notice?menuslug=all-leave-application&leaveApplicationID='.$lRow['dataID'].'" target="_blank">
                    <i class="ti tabler-file-invoice me-2"></i>সংশোধিত অফিস আদেশ
                </a></li>';
        }
    }

    $actions .= '</ul></div>';

    $data[] = array(
        'serial' => $serial++,
        'employee_info' => $employee_info,
        'requested_leave' => $requested_leave,
        'approved_leave' => $approved_leave,
        'spent_leave' => $spent_leave,
        'joining_type' => $joining_type,
        'corrected_leave' => $corrected_leave,
        'status' => $status,
        'actions' => $actions
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
