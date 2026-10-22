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
    1 => 'designation',
    2 => 'signatory',
    3 => 'approvalSL',    
	4 => 'action'
);

$sql = "select `leave_approval_signatory`.dataID, `leave_approval_signatory`.designationID, `leave_approval_signatory`.approvalSL, `leave_approval_signatory`.isMandatory, `job_title`.job_title_name as designation from leave_approval_signatory inner join `job_title` on leave_approval_signatory.designationID=`job_title`.id where `leave_approval_signatory`.organization_id=4 ";


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

$sql .= " ORDER BY approvalSL asc"." 
       LIMIT ".$request['start']." ,".$request['length']." ";



$query = mysqli_query($con, $sql);


$data = array();
$sl = $request['start'];

while ($empRow = mysqli_fetch_array($query)) {
    $sl++;
    // Fetch additional details
    $getSignatoryDetailsQ = mysqli_query($con, "select employee_name from employee_list where designation='$empRow[designationID]' and organization_id=4 and employment_status=1");
	$getSignatoryDetailsQRW = mysqli_fetch_assoc($getSignatoryDetailsQ);											

	
	$action = "<a data-toggle='tooltip' data-placement='top' data-original-title='Edit' href='edit_leave_sig_form?menuslug=leave-settings&dataID={$empRow['dataID']}' type='button' class='btn btn-raised btn-icon btn-secondary mr-1'><i class='fa fa-edit'></i></a>&nbsp;&nbsp;&nbsp;";
    
    $action .= "<button data-toggle='tooltip' data-placement='top' data-original-title='Delete' onClick='removeData({$sl},{$empRow['dataID']})' type='button' class='btn btn-raised btn-icon btn-danger mr-1'><i class='fa fa-trash-o'></i></button>";


	$isMadatory = $empRow['isMandatory'] == 0 ? 'না' : 'হ্যাঁ ';

    $nestedData = array();
    $nestedData['serial'] = $sl;
    $nestedData['designation'] = $empRow['designation'];
    $nestedData['signatory'] = $getSignatoryDetailsQRW['employee_name'];
    $nestedData['approvalSL'] = $empRow['approvalSL'];
	$nestedData['isMandatory'] = $isMadatory;
    
	$nestedData['action'] = $action;
    

    $data[] = $nestedData;
	
	
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
