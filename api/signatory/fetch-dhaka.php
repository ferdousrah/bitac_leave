<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

function pq_fetch_one($con, $sql, $types = '', ...$params) {
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) return null;
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

$request = $_REQUEST;
$ORG_ID = 5; // Dhaka

$columns = array(
    0 => 'dataID',
    1 => 'designation',
    2 => 'signatory',
    3 => 'approvalSL',
    4 => 'action'
);

$baseSelect = "SELECT `leave_approval_signatory`.dataID, `leave_approval_signatory`.designationID, `leave_approval_signatory`.approvalSL, `leave_approval_signatory`.isMandatory, `job_title`.job_title_name AS designation";
$baseFrom = " FROM leave_approval_signatory INNER JOIN `job_title` ON leave_approval_signatory.designationID=`job_title`.id WHERE `leave_approval_signatory`.organization_id = ?";

$searchTypes = 'i';
$searchParams = [$ORG_ID];
$searchClauses = [];
foreach ($columns as $key => $column) {
    if (!empty($request['columns'][$key]['search']['value'])) {
        $search_value = $request['columns'][$key]['search']['value'];
        $searchClauses[] = "`$column` LIKE ?";
        $searchTypes .= 's';
        $searchParams[] = '%' . $search_value . '%';
    }
}
$whereExtra = !empty($searchClauses) ? ' AND (' . implode(' AND ', $searchClauses) . ')' : '';

// Total
$totalRow = pq_fetch_one($con, "SELECT COUNT(*) AS total" . $baseFrom, 'i', $ORG_ID);
$totalData = intval($totalRow['total'] ?? 0);

// Filtered
$countStmt = mysqli_prepare($con, "SELECT COUNT(*) AS total" . $baseFrom . $whereExtra);
mysqli_stmt_bind_param($countStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($countStmt);
$totalFiltered = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
mysqli_stmt_close($countStmt);

$start = isset($request['start']) ? max(0, intval($request['start'])) : 0;
$length = isset($request['length']) ? max(1, intval($request['length'])) : 10;

$mainSql = $baseSelect . $baseFrom . $whereExtra . " ORDER BY approvalSL ASC LIMIT $start, $length";
$mainStmt = mysqli_prepare($con, $mainSql);
mysqli_stmt_bind_param($mainStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($mainStmt);
$query = mysqli_stmt_get_result($mainStmt);

$data = array();
$sl = $start;

while ($empRow = mysqli_fetch_array($query)) {
    $sl++;
    $desigId = $empRow['designationID'];
    $getSignatoryDetailsQRW = pq_fetch_one(
        $con,
        "SELECT employee_name FROM employee_list WHERE designation = ? AND organization_id = ? AND employment_status = 1 AND pending_section_assignment = 0",
        'si',
        $desigId,
        $ORG_ID
    );

    $dataID = intval($empRow['dataID']);
    $action = "<a data-toggle='tooltip' data-placement='top' data-original-title='Edit' href='../../views/signatory/edit.php?menuslug=leave-settings&dataID={$dataID}' type='button' class='btn btn-raised btn-icon btn-secondary mr-1'><i class='fa fa-edit'></i></a>&nbsp;&nbsp;&nbsp;";
    $action .= "<button data-toggle='tooltip' data-placement='top' data-original-title='Delete' onClick='removeData({$sl},{$dataID})' type='button' class='btn btn-raised btn-icon btn-danger mr-1'><i class='fa fa-trash-o'></i></button>";

    $isMadatory = $empRow['isMandatory'] == 0 ? 'না' : 'হ্যাঁ ';

    $nestedData = array();
    $nestedData['serial'] = $sl;
    $nestedData['designation'] = $empRow['designation'];
    $nestedData['signatory'] = $getSignatoryDetailsQRW['employee_name'] ?? '';
    $nestedData['approvalSL'] = $empRow['approvalSL'];
    $nestedData['isMandatory'] = $isMadatory;
    $nestedData['action'] = $action;

    $data[] = $nestedData;
}
mysqli_stmt_close($mainStmt);

$json_data = array(
    "draw" => isset($request['draw']) ? intval($request['draw']) : 0,
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
);

echo json_encode($json_data);
