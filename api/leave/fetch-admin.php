<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

function dateDiffInDays($date1, $date2)
{
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

function pq_fetch_one($con, $sql, $types = '', ...$params)
{
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

$sessionUsername = $_SESSION['username'] ?? '';
$getUserInfoQRW = pq_fetch_one($con, "SELECT * FROM user_list WHERE user_id = ?", 's', $sessionUsername);
$signatoryEmpId = $getUserInfoQRW['employee_id'] ?? '';

$request = $_REQUEST;

// Display columns -> (sql expression, sortable?)
// Only map columns that have a real DB expression. Others fall back to dataID for sort/search safety.
$columnMap = array(
    0  => array('name' => 'dataID',                 'sql' => 'lda.dataID'),
    1  => array('name' => 'employee_name',          'sql' => 'el.employee_name'),
    2  => array('name' => 'employee_id',            'sql' => 'el.employee_id'),
    3  => array('name' => 'section',                'sql' => 's.section_name'),
    4  => array('name' => 'apply_date',             'sql' => 'la.dateFrom'),
    5  => array('name' => 'leave_type',             'sql' => 'lt.leaveTitle'),
    6  => array('name' => 'requested_leave_days',   'sql' => 'la.dateFrom'),
    7  => array('name' => 'approved_leave_days',    'sql' => 'la.approvedDateFrom'),
    8  => array('name' => 'approved_leave_type',    'sql' => 'la.leaveTypeInTwo'),
    9  => array('name' => 'status',                 'sql' => 'lda.isApproved'),
    10 => array('name' => 'actions',                'sql' => 'lda.dataID'),
);

$baseSelect = "SELECT
    lda.dataID              AS lda_dataID,
    lda.leaveApplicationID  AS leaveApplicationID,
    lda.signatory           AS signatory,
    lda.isSupervisor        AS isSupervisor,
    lda.isSentbyAdmin       AS isSentbyAdmin,
    lda.prevSignatory       AS prevSignatory,
    lda.isApproved          AS isApproved,
    lda.approvedDate        AS approvedDate,
    lda.serial              AS serial,
    lda.approvedDays        AS approvedDays,
    la.applicantID          AS applicantID,
    la.leaveType            AS leaveType,
    la.dateFrom             AS dateFrom,
    la.dateTo               AS dateTo,
    la.approvedDateFrom     AS approvedDateFrom,
    la.approvedDateTo       AS approvedDateTo,
    la.leaveTypeInTwo       AS leaveTypeInTwo,
    la.attachment           AS attachment,
    el.employee_name        AS applicant_name,
    jt.job_title_name       AS job_title_name,
    s.section_name          AS section_name,
    o.organization_name     AS organization_name,
    lt.leaveTitle           AS leaveTitle";

$baseFrom = "
FROM leave_data_for_approval lda
INNER JOIN leave_applications la ON lda.leaveApplicationID = la.dataID
INNER JOIN employee_list     el  ON la.applicantID         = el.id
LEFT  JOIN job_title          jt  ON el.designation         = jt.id
LEFT  JOIN sections           s   ON el.section_id          = s.id
LEFT  JOIN organization       o   ON el.organization_id     = o.id
LEFT  JOIN leave_types        lt  ON la.leaveType           = lt.leaveID
WHERE lda.signatory = ?
  AND lda.isSupervisor = 1
  AND lda.isApproved   = 0
  AND (
        lda.prevSignatory = 0
     OR EXISTS (
            SELECT 1 FROM leave_data_for_approval prev
             WHERE prev.leaveApplicationID = lda.leaveApplicationID
               AND prev.signatory          = lda.prevSignatory
               AND prev.isApproved         = 1
               AND prev.serial             = lda.serial - 1
        )
    )";

// Build per-column search clauses using whitelisted SQL expressions
$searchTypes  = 's';
$searchParams = [$signatoryEmpId];
$searchClauses = [];
foreach ($columnMap as $key => $col) {
    if (!empty($request['columns'][$key]['search']['value'])) {
        $searchClauses[] = $col['sql'] . ' LIKE ?';
        $searchTypes  .= 's';
        $searchParams[] = '%' . $request['columns'][$key]['search']['value'] . '%';
    }
}
$whereExtra = !empty($searchClauses) ? ' AND (' . implode(' AND ', $searchClauses) . ')' : '';

// Total (no user search)
$totalRow  = pq_fetch_one($con, "SELECT COUNT(*) AS total" . $baseFrom, 's', $signatoryEmpId);
$totalData = intval($totalRow['total'] ?? 0);

// Filtered count
$countStmt = mysqli_prepare($con, "SELECT COUNT(*) AS total" . $baseFrom . $whereExtra);
mysqli_stmt_bind_param($countStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($countStmt);
$totalFiltered = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
mysqli_stmt_close($countStmt);

// ORDER BY (column whitelist via map) + LIMIT (intval)
$orderColIdx = isset($request['order'][0]['column']) ? intval($request['order'][0]['column']) : 0;
$orderExpr   = $columnMap[$orderColIdx]['sql'] ?? $columnMap[0]['sql'];
$orderDir    = (isset($request['order'][0]['dir']) && strtolower($request['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';
$start       = isset($request['start'])  ? max(0, intval($request['start']))  : 0;
$length      = isset($request['length']) ? max(1, intval($request['length'])) : 10;

$mainSql  = $baseSelect . $baseFrom . $whereExtra . " ORDER BY $orderExpr $orderDir LIMIT $start, $length";
$mainStmt = mysqli_prepare($con, $mainSql);
mysqli_stmt_bind_param($mainStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($mainStmt);
$query = mysqli_stmt_get_result($mainStmt);

$data = array();
$sl   = $start;
while ($row = mysqli_fetch_assoc($query)) {
    $sl++;

    $dateDiff = dateDiffInDays($row['dateFrom'], $row['dateTo']) + 1;
    $dateF    = date_create($row['dateFrom']);
    $dateT    = date_create($row['dateTo']);

    if ($row['approvedDateFrom'] != '' && $row['approvedDateTo'] != '') {
        $adateF    = date_create($row['approvedDateFrom']);
        $adateT    = date_create($row['approvedDateTo']);
        $adateDiff = dateDiffInDays($row['approvedDateFrom'], $row['approvedDateTo']) + 1;
        $proposed_leave_days = banglaNumber(date_format($adateF, "d/m/Y")) . ' হইতে ' . banglaNumber(date_format($adateT, "d/m/Y")) . ', ' . banglaNumber($adateDiff) . ' দিন';
    } else {
        $proposed_leave_days = "";
    }

    $proposed_leave_type = "";
    switch ($row['leaveTypeInTwo']) {
        case 1:  $proposed_leave_type = "গড় বেতন ";                         break;
        case 2:  $proposed_leave_type = "অর্ধ-গড় বেতন ";                    break;
        case 3:  $proposed_leave_type = "নৈমিত্তিক (Casual Leave)";        break;
        case 4:  $proposed_leave_type = "বিনা বেতনে ছুটি";                  break;
        case 5:  $proposed_leave_type = "ঐচ্ছিক ছুটি";                      break;
        case 6:  $proposed_leave_type = "সংগনিরোধ ছুটি";                    break;
        case 7:  $proposed_leave_type = "প্রসূতি ছুটি";                     break;
        case 8:  $proposed_leave_type = "অক্ষমতাজনিত বিশেষ ছুটি";          break;
        case 9:  $proposed_leave_type = "অধ্যয়ন ছুটি";                     break;
        case 10: $proposed_leave_type = "অসাধারণ ছুটি";                     break;
    }

    $action = "<a target='_blank' href='leave_application_details.php?menuslug=leave-approval&leaveApplicationID={$row['leaveApplicationID']}'><img src='app-assets/form.png' height='32' /></a>&nbsp;&nbsp;&nbsp;";
    if ($row['attachment'] != '') {
        $action .= "<a target='_blank' href='uploads/{$row['attachment']}'><img src='app-assets/clip.png' height='32' /></a>&nbsp;&nbsp;&nbsp;";
    }
    $action .= "<a href='approve_leave_application.php?menuslug=leave-approval&dataID={$row['lda_dataID']}&leaveApplicationID={$row['leaveApplicationID']}&isApproved=1'><img height='32' src='app-assets/check-mark.png' /></a>";

    $data[] = array(
        'serial'               => $sl,
        'applicant_name'       => $row['applicant_name'] . ', ' . ($row['job_title_name'] ?? ''),
        'section'              => ($row['section_name'] ?? '') . ', ' . ($row['organization_name'] ?? ''),
        'leave_type'           => $row['leaveTitle'] ?? '',
        'requested_leave_days' => banglaNumber(date_format($dateF, "d/m/Y")) . ' হইতে ' . banglaNumber(date_format($dateT, "d/m/Y")) . ', ' . banglaNumber($dateDiff) . 'দিন',
        'proposed_leave_days'  => $proposed_leave_days,
        'proposed_leave_type'  => $proposed_leave_type,
        'action'               => $action,
    );
}
mysqli_stmt_close($mainStmt);

echo json_encode(array(
    "draw"            => isset($request['draw']) ? intval($request['draw']) : 0,
    "recordsTotal"    => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data"            => $data,
));
