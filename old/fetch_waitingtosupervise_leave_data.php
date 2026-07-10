<?php
session_start();
include('connection.php');
include('library/number_converter.php');

function dateDiffInDays($date1, $date2) 
  {
      // Calculating the difference in timestamps
      $diff = strtotime($date2) - strtotime($date1);
  
      // 1 day = 24 hours
      // 24 * 60 * 60 = 86400 seconds
      return abs(round($diff / 86400));
  }

$getUserInfoQ = mysqli_query($con, "select * from user_list where user_id='$_SESSION[username]'");
$getUserInfoQRW = mysqli_fetch_assoc($getUserInfoQ);

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

$sql = "select `leave_data_for_approval`.dataID, `leave_data_for_approval`.leaveApplicationID, `leave_data_for_approval`.signatory, `leave_data_for_approval`.isSupervisor, `leave_data_for_approval`.isSentbyAdmin, `leave_data_for_approval`.prevSignatory, `leave_data_for_approval`.isApproved, `leave_data_for_approval`.approvedDate, `leave_data_for_approval`.serial, `leave_data_for_approval`.approvedDays, `leave_applications`.applicantID, `leave_applications`.leaveType, `leave_applications`.dateFrom, `leave_applications`.dateTo, `leave_applications`.approvedDateFrom, `leave_applications`.approvedDateTo, `leave_applications`.leaveTypeInTwo, `leave_applications`.attachment, employee_list.employee_name as applicant_name from `leave_data_for_approval` inner join `leave_applications` on `leave_data_for_approval`.leaveApplicationID=`leave_applications`.dataID inner join employee_list on `leave_applications`.applicantID=employee_list.id where `leave_data_for_approval`.signatory='$getUserInfoQRW[employee_id]' and `leave_data_for_approval`.isSupervisor=1 and `leave_data_for_approval`.isApproved=0 ";


$totalData = mysqli_num_rows(mysqli_query($con, $sql));
$totalFiltered = $totalData;


// Individual column search
$search_columns = array();
foreach ($columns as $key => $column) {
    if (!empty($request['columns'][$key]['search']['value'])) {
        $search_value = $request['columns'][$key]['search']['value'];
        $search_columns[] = "$column LIKE '%$search_value%'";
    }
}

if (!empty($search_columns)) {
    $sql .= " AND (" . implode(" AND ", $search_columns) . ")";
}

$query = mysqli_query($con, $sql);
$totalFiltered = mysqli_num_rows($query);

$sql .= " ORDER BY ". $columns[$request['order'][0]['column']]." ".$request['order'][0]['dir']." 
       LIMIT ".$request['start']." ,".$request['length']." ";



$query = mysqli_query($con, $sql);


$data = array();
$sl = $request['start'];
while ($empRow = mysqli_fetch_array($query)) {
    //$sl++;
    // Fetch additional details
    $getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$empRow[leaveApplicationID]'");
								$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

											$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getLeaveApplicationDetailsQRW[applicantID]'");
											$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

											$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
											$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

											$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
											$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

											$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQW[organization_id]'");
											$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

											$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
											$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

											
											if($empRow['prevSignatory'] == 0){

												$proceed = 1;
											
											}else{


												$prevserial = $empRow['serial'] - 1;
												


												$checkPrevSognatorySignedQ = mysqli_query($con, "select * from `leave_data_for_approval` where leaveApplicationID='$empRow[leaveApplicationID]' and signatory='$empRow[prevSignatory]' and isApproved=1 and serial='$prevserial'");

												//echo "select * from `leave_data_for_approval` where leaveApplicationID='$empRow[leaveApplicationID]' and signatory='$empRow[prevSignatory]' and isApproved=1 and serial='$prevserial'"."<br>";

												$checkPrevSognatorySignedQNumRows = mysqli_num_rows($checkPrevSognatorySignedQ);

												if($checkPrevSognatorySignedQNumRows > 0){


													$proceed = 1;												
												
												}else{
												
													$proceed = 0;
												
												}
											
											}


											if($proceed == 1){

												$sl = $sl + 1;


												$dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;


												$dateF=date_create($getLeaveApplicationDetailsQRW['dateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);

												// proposed

												if($getLeaveApplicationDetailsQRW['approvedDateFrom'] != '' && $getLeaveApplicationDetailsQRW['approvedDateTo'] != ''){

												$adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

												$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

												}else{
												
												$adateDiff = "";
												
												}

	// proposed leave days

	if($getLeaveApplicationDetailsQRW['approvedDateFrom'] != '' && $getLeaveApplicationDetailsQRW['approvedDateTo'] != ''){ 
		$proposed_leave_days = banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")).', '.banglaNumber($adateDiff).' দিন';
	}else{

		$proposed_leave_days = "";

	}


	// proposed leave type

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

	
	$action = "<a target='_blank' href='leave_application_details.php?menuslug=leave-approval&leaveApplicationID={$empRow['leaveApplicationID']}'><img src='app-assets/form.png' height='32' /></a>&nbsp;&nbsp;&nbsp;";
    if ($getLeaveApplicationDetailsQRW['attachment'] != '') {
        $action .= "<a target='_blank' href='uploads/{$getLeaveApplicationDetailsQRW['attachment']}'><img src='app-assets/clip.png' height='32' /></a>&nbsp;&nbsp;&nbsp;";
    }
    $action .= "<a href='approve_leave_application.php?menuslug=leave-approval&dataID={$empRow['dataID']}&leaveApplicationID={$empRow['leaveApplicationID']}&isApproved=1'><img height='32' src='app-assets/check-mark.png' /></a>";


    $nestedData = array();
    $nestedData['serial'] = $sl;
    $nestedData['applicant_name'] = $empRow['applicant_name'].', '.$getDesignationDetailsQRW['job_title_name'];
    $nestedData['section'] = $getSectionDetailsQRW['section_name'].", ".$getorgDetailsQRW['organization_name'];
    $nestedData['leave_type'] = $getLeaveTypeQRW['leaveTitle'];
    $nestedData['requested_leave_days'] = banglaNumber(date_format($dateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($dateT,"d/m/Y")).', '.banglaNumber($dateDiff).'দিন';
    $nestedData['proposed_leave_days'] = $proposed_leave_days;
    $nestedData['proposed_leave_type'] = $proposed_leave_type;
	$nestedData['action'] = $action;
    

    $data[] = $nestedData;
	
	$proceed = 0;
}
}

//$totalData = $sl;

//$totalFiltered = $sl;

$json_data = array(
    "draw" => intval($request['draw']),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
);

echo json_encode($json_data);

?>
