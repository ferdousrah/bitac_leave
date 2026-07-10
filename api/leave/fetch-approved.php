<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

error_reporting(0);
ini_set('display_errors', 0);

function dateDiffInDays($date1, $date2) {
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

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

$sessionUsername = $_SESSION['username'] ?? '';
$getUserInfoQRW = pq_fetch_one($con, "SELECT * FROM user_list WHERE user_id = ?", 's', $sessionUsername);
$signatoryEmpId = $getUserInfoQRW['employee_id'] ?? '';

$request = $_REQUEST;

// Frontend column order: 0=row, 1=applicant, 2=section/center, 3=requested, 4=proposed, 5=action
$columnMap = array(
    0 => array('name' => 'dataID',         'sql' => 'lda.dataID'),
    1 => array('name' => 'applicant_name', 'sql' => 'el.employee_name'),
    2 => array('name' => 'section',        'sql' => 's.section_name'),
    3 => array('name' => 'requested',      'sql' => 'la.dateFrom'),
    4 => array('name' => 'proposed',       'sql' => 'la.approvedDateFrom'),
    5 => array('name' => 'action',         'sql' => 'lda.dataID'),
);

// Filters
$centerFilter    = (int)($_REQUEST['centerFilter']    ?? 0);
$sectionFilter   = (int)($_REQUEST['sectionFilter']   ?? 0);
$employeeFilter  = (int)($_REQUEST['employeeFilter']  ?? 0);
$leaveTypeFilter = (int)($_REQUEST['leaveTypeFilter'] ?? 0);
$dFrom           = trim($_REQUEST['dateFrom'] ?? '');
$dTo             = trim($_REQUEST['dateTo']   ?? '');

$filterClause = '';
$filterTypes  = '';
$filterParams = [];
if ($centerFilter > 0)    { $filterClause .= ' AND el.organization_id = ?'; $filterTypes .= 'i'; $filterParams[] = $centerFilter; }
if ($sectionFilter > 0)   { $filterClause .= ' AND el.section_id = ?';      $filterTypes .= 'i'; $filterParams[] = $sectionFilter; }
if ($employeeFilter > 0)  { $filterClause .= ' AND el.id = ?';              $filterTypes .= 'i'; $filterParams[] = $employeeFilter; }
if ($leaveTypeFilter > 0) { $filterClause .= ' AND la.leaveType = ?';       $filterTypes .= 'i'; $filterParams[] = $leaveTypeFilter; }
if ($dFrom !== '' && $dTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dTo)) {
    $filterClause .= ' AND la.dateFrom BETWEEN ? AND ?';
    $filterTypes  .= 'ss';
    $filterParams[] = $dFrom;
    $filterParams[] = $dTo;
}

$baseSelect = "SELECT
    lda.dataID              AS lda_dataID,
    lda.leaveApplicationID  AS leaveApplicationID,
    lda.prevSignatory       AS prevSignatory,
    lda.serial              AS serial,
    la.applicantID          AS applicantID,
    la.leaveType            AS leaveType,
    la.approvedLeaveType    AS approvedLeaveType,
    la.dateFrom             AS dateFrom,
    la.dateTo               AS dateTo,
    la.approvedDateFrom     AS approvedDateFrom,
    la.approvedDateTo       AS approvedDateTo,
    la.leaveTypeInTwo       AS leaveTypeInTwo,
    la.attachment           AS attachment,
    el.employee_name        AS applicant_name,
    el.employee_id          AS applicant_code,
    el.photo                AS applicant_photo,
    jt.job_title_name       AS job_title_name,
    s.section_name          AS section_name,
    o.organization_name     AS organization_name,
    lt.leaveTitle           AS leaveTitle,
    alt.leaveTitle          AS approvedLeaveTitle";

$baseFrom = "
FROM leave_data_for_approval lda
INNER JOIN leave_applications la  ON lda.leaveApplicationID = la.dataID
INNER JOIN employee_list     el   ON la.applicantID         = el.id
LEFT  JOIN job_title          jt  ON el.designation         = jt.id
LEFT  JOIN sections           s   ON el.section_id          = s.id
LEFT  JOIN organization       o   ON el.organization_id     = o.id
LEFT  JOIN leave_types        lt  ON la.leaveType           = lt.leaveID
LEFT  JOIN leave_types        alt ON la.approvedLeaveType   = alt.leaveID
WHERE lda.signatory     = ?
  AND lda.isSentbyAdmin = 1
  AND lda.isSupervisor != 1
  AND lda.isApproved    = 1
  $filterClause
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

$searchTypes  = 's' . $filterTypes;
$searchParams = array_merge([$signatoryEmpId], $filterParams);
$searchClauses = [];
foreach ($columnMap as $key => $col) {
    if (!empty($request['columns'][$key]['search']['value'])) {
        $searchClauses[] = $col['sql'] . ' LIKE ?';
        $searchTypes  .= 's';
        $searchParams[] = '%' . $request['columns'][$key]['search']['value'] . '%';
    }
}
$whereExtra = !empty($searchClauses) ? ' AND (' . implode(' AND ', $searchClauses) . ')' : '';

$totalSql    = "SELECT COUNT(*) AS total" . $baseFrom;
$totalStmt   = mysqli_prepare($con, $totalSql);
$totalTypes  = 's' . $filterTypes;
$totalParams = array_merge([$signatoryEmpId], $filterParams);
mysqli_stmt_bind_param($totalStmt, $totalTypes, ...$totalParams);
mysqli_stmt_execute($totalStmt);
$totalData = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['total'] ?? 0);
mysqli_stmt_close($totalStmt);

$countStmt = mysqli_prepare($con, "SELECT COUNT(*) AS total" . $baseFrom . $whereExtra);
mysqli_stmt_bind_param($countStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($countStmt);
$totalFiltered = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
mysqli_stmt_close($countStmt);

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
$sl = $start;

$leaveTypeInTwoMap = [
    1 => 'গড় বেতন', 2 => 'অর্ধ-গড় বেতন', 3 => 'নৈমিত্তিক (Casual Leave)',
    4 => 'বিনা বেতনে ছুটি', 5 => 'ঐচ্ছিক ছুটি', 6 => 'সংগনিরোধ ছুটি',
    7 => 'প্রসূতি ছুটি', 8 => 'অক্ষমতাজনিত বিশেষ ছুটি', 9 => 'অধ্যয়ন ছুটি', 10 => 'অসাধারণ ছুটি',
];

while ($row = mysqli_fetch_assoc($query)) {
    $sl++;

    $dateDiff = dateDiffInDays($row['dateFrom'], $row['dateTo']) + 1;
    $dateF = date_create($row['dateFrom']);
    $dateT = date_create($row['dateTo']);

    $hasApproved = ($row['approvedDateFrom'] != '' && $row['approvedDateTo'] != '');
    $adateF = $adateT = null; $adateDiff = 0;
    if ($hasApproved) {
        $adateF = date_create($row['approvedDateFrom']);
        $adateT = date_create($row['approvedDateTo']);
        $adateDiff = dateDiffInDays($row['approvedDateFrom'], $row['approvedDateTo']) + 1;
    }

    $proposed_leave_type = $leaveTypeInTwoMap[$row['leaveTypeInTwo']] ?? '';

    // Applicant cell
    $empName  = trim($row['applicant_name'] ?? '');
    $empJob   = trim($row['job_title_name'] ?? '');
    $empPhoto = trim($row['applicant_photo'] ?? '');
    $empCode  = trim($row['applicant_code'] ?? '');
    $initials = mb_substr($empName, 0, 1, 'UTF-8');
    $parts = preg_split('/\s+/u', $empName);
    if (count($parts) > 1) {
        $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
    }
    if (!empty($empPhoto)) {
        $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
        $avatarHtml = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
    } else {
        $avatarHtml = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
    }
    $applicantCell = '<div class="emp-cell">' . $avatarHtml
                   . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName) . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                   . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                   . '</div></div>';

    // Section + center
    $secCenter = '';
    if (!empty($row['section_name'])) {
        $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($row['section_name']) . '</span>';
    }
    if (!empty($row['organization_name'])) {
        if ($secCenter) $secCenter .= '<br>';
        $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($row['organization_name']) . '</span>';
    }

    // Requested
    $requestedHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date_format($dateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($dateT, "d/m/Y")) . '</span></div>'
                   . '<div class="leave-meta"><span class="days-pill">' . banglaNumber($dateDiff) . ' দিন</span>'
                   . ' <span class="leave-type-chip">' . htmlspecialchars($row['leaveTitle'] ?? '') . '</span></div>';

    // Proposed
    if ($hasApproved) {
        $proposedHtml = '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date_format($adateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($adateT, "d/m/Y")) . '</span></div>'
                      . '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber($adateDiff) . ' দিন</span>'
                      . ($proposed_leave_type ? ' <span class="leave-type-chip">' . htmlspecialchars($proposed_leave_type) . '</span>' : '')
                      . '</div>';
    } else {
        $proposedHtml = '<span class="text-muted small">—</span>';
    }

    // Action group
    $action = '<div class="action-group">';
    $action .= '<a class="action-icon icon-view" target="_blank" href="../../views/leave/application-details.php?menuslug=supervised-nd-approved-application-by-user&leaveApplicationID=' . $row['leaveApplicationID'] . '" title="আবেদনপত্র"><i class="ti tabler-file-text"></i></a>';
    if ($row['attachment'] != '') {
        $action .= '<a class="action-icon icon-attach" target="_blank" href="uploads/' . htmlspecialchars($row['attachment']) . '" title="সংযুক্তি"><i class="ti tabler-paperclip"></i></a>';
    }
    $action .= '</div>';

    $data[] = array(
        'serial'         => '<span class="serial-num">' . $sl . '</span>',
        'applicant_cell' => $applicantCell,
        'section_center' => $secCenter,
        'requested'      => $requestedHtml,
        'proposed'       => $proposedHtml,
        'action'         => $action,
    );
}
mysqli_stmt_close($mainStmt);

echo json_encode(array(
    "draw"            => isset($request['draw']) ? intval($request['draw']) : 0,
    "recordsTotal"    => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data"            => $data,
));
