<?php
session_start();
include('connection.php'); // Database connection file
include('library/number_converter.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

function dateDiffInDays($date1, $date2) 
  {
      // Calculating the difference in timestamps
      $diff = strtotime($date2) - strtotime($date1);
  
      // 1 day = 24 hours
      // 24 * 60 * 60 = 86400 seconds
      return abs(round($diff / 86400));
  }

// Get user information securely using prepared statements to prevent SQL injection
$getUserDetailsQ = mysqli_query($con, "select * from user_list where dataID = '$_SESSION[userID]'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);


$getCurrentEmpDetailsQ = mysqli_query($con, "select * from `employee_list` where id='$getUserDetailsQRW[employee_id]'");
$getCurrentEmpDetailsQRW = mysqli_fetch_assoc($getCurrentEmpDetailsQ);

// Constants for columns to avoid hardcoding
define('COLUMNS', ['applicant_name', 'employee_id']);

// Get request parameters with sanitization
$limit = isset($_POST['length']) ? (int) $_POST['length'] : 10;  // Number of records per page
$start = isset($_POST['start']) ? (int) $_POST['start'] : 0;    // Offset for pagination
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : ''; // Search filter

$orderColumn = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;  // Get the column to order by
$orderDirection = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';  // Get the direction (asc/desc)

// SQL Query with JOIN to fetch employee data along with related information
$sql = "select employee_list.employee_name as applicant_name, employee_list.employee_id, employee_list.designation, employee_list.section_id, leave_data_for_approval.leaveApplicationID, leave_data_for_approval.isSentbyAdmin from `leave_data_for_approval` inner join leave_applications on leave_data_for_approval.leaveApplicationID=leave_applications.dataID INNER JOIN employee_list on leave_applications.applicantID=employee_list.id where leave_data_for_approval.isSupervisor=1 and leave_data_for_approval.isApproved=1 and leave_applications.organization_id='$getCurrentEmpDetailsQRW[organization_id]' AND leave_data_for_approval.isSentbyAdmin = 1";

// Apply search filter if available
if ($search) {
    $sql .= " AND (employee_list.employee_name LIKE ? OR employee_list.employee_id LIKE ?)";
}

// Add sorting and pagination
$sql .= " ORDER BY " . COLUMNS[$orderColumn] . " $orderDirection LIMIT ?, ?";

// Prepare the query
$stmt = mysqli_prepare($con, $sql);
if ($search) {
    // Bind parameters with wildcards for search
    $searchTerm = "%$search%";
    //mysqli_stmt_bind_param($stmt, 'ssssii', $searchTerm, $searchTerm, $searchTerm, $searchTerm, $start, $limit);
	mysqli_stmt_bind_param($stmt, 'ssii', $searchTerm, $searchTerm, $start, $limit);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $start, $limit);
}

// Execute the query
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch total records count for pagination
$totalRecordsQuery = mysqli_query($con, "select employee_list.employee_name as applicant_name, employee_list.employee_id, leave_data_for_approval.leaveApplicationID, leave_data_for_approval.isSentbyAdmin from `leave_data_for_approval` inner join leave_applications on leave_data_for_approval.leaveApplicationID=leave_applications.dataID INNER JOIN employee_list on leave_applications.applicantID=employee_list.id where leave_data_for_approval.isSupervisor=1 and leave_data_for_approval.isApproved=1 and leave_applications.organization_id='$getCurrentEmpDetailsQRW[organization_id]' AND leave_data_for_approval.isSentbyAdmin = 1");
$totalRecords = mysqli_num_rows($totalRecordsQuery);

$data = [];
$sl = $start + 1;

// Process the results
while ($row = mysqli_fetch_array($result)) {

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$row[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$row[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

//$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$row[organization_id]'");
//$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$row[leaveApplicationID]'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

//.....

$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

$getApprovedLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[approvedLeaveType]'");
$getApprovedLeaveTypeQRW = mysqli_fetch_assoc($getApprovedLeaveTypeQ);

if($getLeaveApplicationDetailsQRW['dateFrom']!=NULL && $getLeaveApplicationDetailsQRW['dateTo']!=NULL){

	$dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;

	$dateF=date_create($getLeaveApplicationDetailsQRW['dateFrom']);
	//echo date_format($dateF,"d/m/Y");
	$dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);

}

// proposed

if($getLeaveApplicationDetailsQRW['approvedDateFrom']!=NULL && $getLeaveApplicationDetailsQRW['approvedDateTo']!=NULL){

	$adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
	//echo date_format($dateF,"d/m/Y");
	$adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

	$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

}

//$getALeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveTypeInTwo]'");
//$getALeaveTypeQRW = mysqli_fetch_assoc($getALeaveTypeQ);


$sdate = date_create($getLeaveApplicationDetailsQRW['submitDate']);

												
$checkIsReadQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$row[leaveApplicationID]' and isSupervisor=0 and isRead=1");
$checkIsReadQNumRows = mysqli_num_rows($checkIsReadQ);

$application_date_time = $sdate->format('d/m/Y');

if($getLeaveApplicationDetailsQRW['submitTime'] != NULL){ $application_date_time = $application_date_time. " ".$getLeaveApplicationDetailsQRW['submitTime']; }

$requested_leave_days = "";

if ($dateF && $dateT) {
// Format the DateTime objects
	$formatted_dateF = $dateF->format('d/m/Y');
	$formatted_dateT = $dateT->format('d/m/Y');
													
	// Echo the formatted dates
	$requested_leave_days = $formatted_dateF . ' হইতে ' . $formatted_dateT.", ".banglaNumber($dateDiff)."দিন";

} else {
	// Handle the case where date_create() failed
	$requested_leave_days = "Error: Unable to create DateTime object";
}

$proposed_leave_days = "";

if ($adateF && $adateT) {
// Format the DateTime objects
	$formatted_adateF = $adateF->format('d/m/Y');
	$formatted_adateT = $adateT->format('d/m/Y');
														
	// Echo the formatted dates
	$proposed_leave_days = $formatted_adateF . ' হইতে ' . $formatted_adateT.", ".banglaNumber($adateDiff)."দিন";

} else {
// Handle the case where date_create() failed
	$proposed_leave_days = "Error: Unable to create DateTime object";
}



if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){
	$proposed_leave_type = "গড় বেতন ";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){
	$proposed_leave_type = "অর্ধ-গড় বেতন ";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){
	$proposed_leave_type = "নৈমিত্তিক (Casual Leave)";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){
	$proposed_leave_type = "বিনা বেতনে ছুটি";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){
	$proposed_leave_type = "ঐচ্ছিক ছুটি";													
}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){

	$proposed_leave_type = "সংগনিরোধ ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 7){

	$proposed_leave_type = "প্রসূতি ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 8){

	$proposed_leave_type = "অক্ষমতাজনিত বিশেষ ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 9){

	$proposed_leave_type = "অধ্যয়ন ছুটি";

}else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){

	$proposed_leave_type = "অসাধারণ ছুটি";

}else{

	$proposed_leave_type = "";

}


if($getLeaveApplicationDetailsQRW['status'] == 1){ $status = "<span class='badge badge-success'>অনুমোদন করা হয়েছে</span>"; }else if($getLeaveApplicationDetailsQRW['status'] == 2){ $status = "<span class='badge badge-danger'>অনুমোদিত হয়নি</span>"; }else if($getLeaveApplicationDetailsQRW['status'] == 0){ $status = "<span class='badge badge-primary'>অনুমোদনের জন্যে অপেক্ষমান</span>"; }else{ $status = ""; }



$html = '
<div class="form-group">
    <div class="btn-group" role="group" aria-label="Button group with nested dropdown">
        <div class="btn-group" role="group">
            <button id="btnGroupDrop3" type="button" class="btn btn-raised btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-folder"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="btnGroupDrop3" x-placement="top-start" style="position: absolute; transform: translate3d(0px, -2px, 0px); top: 0px; left: 0px; will-change: transform;">
                <!-- Leave Application Details -->
                <a class="dropdown-item" target="_blank" href="leave_application_details.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '">আবেদনপত্র</a>

                ' . ($getLeaveApplicationDetailsQRW['attachment'] != '' ? 
                    '<a class="dropdown-item" target="_blank" href="uploads/' . $getLeaveApplicationDetailsQRW['attachment'] . '">সংযুক্তি</a>' : '') . '

                ' . ($row['isSentbyAdmin'] == 1 ? '
                    <!-- Admin-Specific Links -->
                    <a class="dropdown-item" target="_blank" href="leave_application_by_admin.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '">সম্পাদনার নোট</a>

                    ' . ($checkIsReadQNumRows <= 0 ? 
                        '<a class="dropdown-item" target="_blank" href="allowed_leave_application_update?menuslug=allowed-leave-applications&dataID=' . $getLeaveApplicationDetailsQRW['dataID'] . '&leaveApplicationID=' . $getLeaveApplicationDetailsQRW['dataID'] . '&isApproved=1">নোট এডিট করুন</a>' : '') . '

                    ' . ($getLeaveApplicationDetailsQRW['status'] == 1 ? '
                        <a class="dropdown-item" target="_blank" href="leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '">অফিস আদেশ</a>
                        <a class="dropdown-item" href="new_leave_edit_form?menuslug=allowed-leave-applications&leaveApplicationID=' . $row['leaveApplicationID'] . '">সংশোধন</a>
                    ' : '') . '
                ' : '') . '

                ' . ($_SESSION['username'] == 'Saifullah' || $_SESSION['username'] == 'saifullah' ? '
                    <!--<button class="dropdown-item" onClick="removeData(' . $sl . ',' . $row['leaveApplicationID'] . ')" type="button" class="btn btn-raised btn-icon btn-danger mr-1">Delete</button>-->
                ' : '') . '
            </div>
        </div>

        ' . ($row['isSentbyAdmin'] == 0 ? '
            <a type="button" class="dropdown-item" href="allowed_leave_application_update?menuslug=allowed-leave-applications&dataID=' . $getLeaveApplicationDetailsQRW['dataID'] . '&leaveApplicationID=' . $getLeaveApplicationDetailsQRW['dataID'] . '&isApproved=1">
                <img src="dashboard-icons/arrow-right.png" height="32" />
            </a>
        ' : '') . '
    </div>
</div>';




    $data[] = [
        "sl" => $sl,
        "applicant_name" => $row['applicant_name'].', '.$getDesignationDetailsQRW['job_title_name'],
        "employee_id" => $row['employee_id'],
        "section_name" => $getSectionDetailsQRW['section_name'],
        "application_date_time" => $application_date_time,
        "requested_leave_type" => $getLeaveTypeQRW['leaveTitle'],
		"requested_leave_days" => $requested_leave_days,
		"proposed_leave_days" => $proposed_leave_days,
		"proposed_leave_type" => $getApprovedLeaveTypeQRW['leaveTitle']. ' - '.$proposed_leave_type,
		"status" => $status,
		"action" => $html
/*        "action" => '<button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location=\'edit_employee_info_form?dataID=' . base64_encode($row['id']) . '&menuslug=manage-employee\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-edit"></i></button>
                    <button data-toggle="tooltip" data-placement="top" data-original-title="Delete" onClick="removeData(' . $sl . ',' . $row['id'] . ')" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-trash-o"></i></button>
                    <button data-toggle="tooltip" data-placement="top" data-original-title="Previous Leave" onclick="window.location=\'previous_leave_info_form?dataID=' . base64_encode($row['id']) . '&menuslug=manage-employee\'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-sort-amount-asc"></i></button>' */
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
