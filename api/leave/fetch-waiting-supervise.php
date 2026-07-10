<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

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

// Column-index → DB column for ORDER BY
// Frontend order: 0=row_check, 1=serial(dataID), 2=applicant, 3=section/center, 4=requested(dateFrom), 5=proposed(approvedDateFrom), 6=action
$columns = array(
    0 => '`leave_data_for_approval`.dataID',
    1 => '`leave_data_for_approval`.dataID',
    2 => 'employee_list.employee_name',
    3 => 'employee_list.section_id',
    4 => '`leave_applications`.dateFrom',
    5 => '`leave_applications`.approvedDateFrom',
    6 => '`leave_data_for_approval`.dataID',
);

$centerFilter    = (int)($_REQUEST['centerFilter']    ?? 0);
$sectionFilter   = (int)($_REQUEST['sectionFilter']   ?? 0);
$employeeFilter  = (int)($_REQUEST['employeeFilter']  ?? 0);
$leaveTypeFilter = (int)($_REQUEST['leaveTypeFilter'] ?? 0);
$dateFrom        = trim($_REQUEST['dateFrom'] ?? '');
$dateTo          = trim($_REQUEST['dateTo']   ?? '');

$filterClause = '';
$filterTypes  = '';
$filterParams = [];

if ($centerFilter > 0) {
    $filterClause .= ' AND employee_list.organization_id = ?';
    $filterTypes  .= 'i';
    $filterParams[] = $centerFilter;
}
if ($sectionFilter > 0) {
    $filterClause .= ' AND employee_list.section_id = ?';
    $filterTypes  .= 'i';
    $filterParams[] = $sectionFilter;
}
if ($employeeFilter > 0) {
    $filterClause .= ' AND employee_list.id = ?';
    $filterTypes  .= 'i';
    $filterParams[] = $employeeFilter;
}
if ($leaveTypeFilter > 0) {
    $filterClause .= ' AND `leave_applications`.leaveType = ?';
    $filterTypes  .= 'i';
    $filterParams[] = $leaveTypeFilter;
}
if ($dateFrom !== '' && $dateTo !== '') {
    $filterClause .= ' AND `leave_applications`.dateFrom BETWEEN ? AND ?';
    $filterTypes  .= 'ss';
    $filterParams[] = $dateFrom;
    $filterParams[] = $dateTo;
}

$baseSelect = "SELECT `leave_data_for_approval`.dataID, `leave_data_for_approval`.leaveApplicationID, `leave_data_for_approval`.signatory, `leave_data_for_approval`.isSupervisor, `leave_data_for_approval`.isSentbyAdmin, `leave_data_for_approval`.prevSignatory, `leave_data_for_approval`.isApproved, `leave_data_for_approval`.approvedDate, `leave_data_for_approval`.serial, `leave_data_for_approval`.approvedDays, `leave_applications`.applicantID, `leave_applications`.leaveType, `leave_applications`.dateFrom, `leave_applications`.dateTo, `leave_applications`.approvedDateFrom, `leave_applications`.approvedDateTo, `leave_applications`.leaveTypeInTwo, `leave_applications`.attachment, employee_list.employee_name as applicant_name, employee_list.photo as applicant_photo";
$baseFrom = " FROM `leave_data_for_approval`
              INNER JOIN `leave_applications` ON `leave_data_for_approval`.leaveApplicationID=`leave_applications`.dataID
              INNER JOIN employee_list ON `leave_applications`.applicantID=employee_list.id
              WHERE `leave_data_for_approval`.signatory = ?
                AND `leave_data_for_approval`.isSupervisor = 1
                AND `leave_data_for_approval`.isApproved = 0"
           . $filterClause;

// Build search clauses
$searchTypes  = 's' . $filterTypes;
$searchParams = array_merge([$signatoryEmpId], $filterParams);

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

// Total (with all panel filters but no per-column search)
$totalSql   = "SELECT COUNT(*) AS total" . $baseFrom;
$totalStmt  = mysqli_prepare($con, $totalSql);
$totalTypes = 's' . $filterTypes;
$totalParams = array_merge([$signatoryEmpId], $filterParams);
mysqli_stmt_bind_param($totalStmt, $totalTypes, ...$totalParams);
mysqli_stmt_execute($totalStmt);
$totalData = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['total'] ?? 0);
mysqli_stmt_close($totalStmt);

// Filtered
$countStmt = mysqli_prepare($con, "SELECT COUNT(*) AS total" . $baseFrom . $whereExtra);
mysqli_stmt_bind_param($countStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($countStmt);
$totalFiltered = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
mysqli_stmt_close($countStmt);

// ORDER + LIMIT
$orderColIdx = isset($request['order'][0]['column']) ? intval($request['order'][0]['column']) : 0;
$orderCol = $columns[$orderColIdx] ?? $columns[0];
$orderDir = (isset($request['order'][0]['dir']) && strtolower($request['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';
$start = isset($request['start']) ? max(0, intval($request['start'])) : 0;
$length = isset($request['length']) ? max(1, intval($request['length'])) : 10;

// $orderCol already includes proper backticks
$mainSql = $baseSelect . $baseFrom . $whereExtra . " ORDER BY $orderCol $orderDir LIMIT $start, $length";
$mainStmt = mysqli_prepare($con, $mainSql);
mysqli_stmt_bind_param($mainStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($mainStmt);
$query = mysqli_stmt_get_result($mainStmt);

$data = array();
$sl = $start;
while ($empRow = mysqli_fetch_array($query)) {
    $leaveAppID = $empRow['leaveApplicationID'];
    $getLeaveApplicationDetailsQRW = pq_fetch_one($con, "SELECT * FROM leave_applications WHERE dataID = ?", 's', $leaveAppID);
    $applicantID = $getLeaveApplicationDetailsQRW['applicantID'] ?? '';

    $getEmployeeDetailsQW = pq_fetch_one($con, "SELECT * FROM employee_list WHERE id = ?", 's', $applicantID);
    $desigId = $getEmployeeDetailsQW['designation'] ?? '';
    $sectionId = $getEmployeeDetailsQW['section_id'] ?? '';
    $orgId = $getEmployeeDetailsQW['organization_id'] ?? '';

    $getDesignationDetailsQRW = pq_fetch_one($con, "SELECT * FROM job_title WHERE id = ?", 's', $desigId);
    $getSectionDetailsQRW = pq_fetch_one($con, "SELECT * FROM sections WHERE id = ?", 's', $sectionId);
    $getorgDetailsQRW = pq_fetch_one($con, "SELECT * FROM organization WHERE id = ?", 's', $orgId);

    $leaveTypeVal = $getLeaveApplicationDetailsQRW['leaveType'] ?? '';
    $getLeaveTypeQRW = pq_fetch_one($con, "SELECT * FROM leave_types WHERE leaveID = ?", 's', $leaveTypeVal);

    if ($empRow['prevSignatory'] == 0) {
        $proceed = 1;
    } else {
        $prevserial = $empRow['serial'] - 1;
        $prevSig = $empRow['prevSignatory'];
        $prevRow = pq_fetch_one(
            $con,
            "SELECT COUNT(*) AS cnt FROM `leave_data_for_approval` WHERE leaveApplicationID = ? AND signatory = ? AND isApproved = 1 AND serial = ?",
            'ssi',
            $leaveAppID,
            $prevSig,
            $prevserial
        );
        $proceed = (intval($prevRow['cnt'] ?? 0) > 0) ? 1 : 0;
    }

    if ($proceed == 1) {
        $sl = $sl + 1;

        // ── Date / day calculations ────────────────────────────────
        $dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;
        $dateF = date_create($getLeaveApplicationDetailsQRW['dateFrom']);
        $dateT = date_create($getLeaveApplicationDetailsQRW['dateTo']);

        $hasApproved = ($getLeaveApplicationDetailsQRW['approvedDateFrom'] != '' && $getLeaveApplicationDetailsQRW['approvedDateTo'] != '');
        $adateF = $adateT = null;
        $adateDiff = '';
        if ($hasApproved) {
            $adateF = date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
            $adateT = date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);
            $adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;
        }

        $leaveTypeInTwoMap = [
            1 => 'গড় বেতন', 2 => 'অর্ধ-গড় বেতন', 3 => 'নৈমিত্তিক (Casual Leave)',
            4 => 'বিনা বেতনে ছুটি', 5 => 'ঐচ্ছিক ছুটি', 6 => 'সংগনিরোধ ছুটি',
            7 => 'প্রসূতি ছুটি', 8 => 'অক্ষমতাজনিত বিশেষ ছুটি', 9 => 'অধ্যয়ন ছুটি',
            10 => 'অসাধারণ ছুটি',
        ];
        $proposed_leave_type = $leaveTypeInTwoMap[$getLeaveApplicationDetailsQRW['leaveTypeInTwo']] ?? '';

        // ── Applicant cell (avatar + name + designation) ───────────
        $empName = trim($empRow['applicant_name'] ?? '');
        $empJob  = trim($getDesignationDetailsQRW['job_title_name'] ?? '');
        $empPhoto = trim($empRow['applicant_photo'] ?? '');
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
        $empCode = '';
        $_codeR = pq_fetch_one($con, "SELECT employee_id FROM employee_list WHERE id = ?", 's', $applicantID);
        if ($_codeR) $empCode = trim($_codeR['employee_id'] ?? '');
        $applicantCell = '<div class="emp-cell">' . $avatarHtml
                       . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName) . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                       . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                       . '</div></div>';

        // ── Section + Center as stacked chips ──────────────────────
        $secCenter = '';
        if (!empty($getSectionDetailsQRW['section_name'])) {
            $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($getSectionDetailsQRW['section_name']) . '</span>';
        }
        if (!empty($getorgDetailsQRW['organization_name'])) {
            if ($secCenter) $secCenter .= '<br>';
            $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($getorgDetailsQRW['organization_name']) . '</span>';
        }

        // ── Requested leave (date-range + days-pill + leave-type-chip) ──
        $requestedHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date_format($dateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($dateT, "d/m/Y")) . '</span></div>'
                       . '<div class="leave-meta"><span class="days-pill">' . banglaNumber($dateDiff) . ' দিন</span>'
                       . ' <span class="leave-type-chip">' . htmlspecialchars($getLeaveTypeQRW['leaveTitle'] ?? '') . '</span></div>';

        // ── Proposed leave (only if approvedDate range present) ────
        $proposedHtml = '';
        if ($hasApproved) {
            $proposedHtml = '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date_format($adateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($adateT, "d/m/Y")) . '</span></div>'
                          . '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber($adateDiff) . ' দিন</span>'
                          . ($proposed_leave_type ? ' <span class="leave-type-chip">' . htmlspecialchars($proposed_leave_type) . '</span>' : '')
                          . '</div>';
        } else {
            $proposedHtml = '<span class="text-muted small">—</span>';
        }

        // ── Action group ──────────────────────────────────────────
        $action = '<div class="action-group">';
        $action .= "<a target='_blank' href='application-details.php?menuslug=leave-approval&leaveApplicationID={$empRow['leaveApplicationID']}' class='action-icon icon-view' title='বিস্তারিত দেখুন'><i class='ti tabler-eye'></i></a>";
        if ($getLeaveApplicationDetailsQRW['attachment'] != '') {
            $action .= "<a target='_blank' href='uploads/{$getLeaveApplicationDetailsQRW['attachment']}' class='action-icon icon-attach' title='সংযুক্তি দেখুন'><i class='ti tabler-paperclip'></i></a>";
        }
        $action .= "<a href='approve-application.php?menuslug=leave-approval&dataID={$empRow['dataID']}&leaveApplicationID={$empRow['leaveApplicationID']}' class='action-icon icon-approve' title='সুপারিশ করুন'><i class='ti tabler-check'></i></a>";
        $action .= '</div>';

        $nestedData = array();
        $nestedData['row_check']      = '<input type="checkbox" class="form-check-input row-check" value="' . (int)$empRow['dataID'] . '">';
        $nestedData['serial']         = '<span class="serial-num">' . $sl . '</span>';
        $nestedData['applicant_cell'] = $applicantCell;
        $nestedData['section_center'] = $secCenter;
        $nestedData['requested']      = $requestedHtml;
        $nestedData['proposed']       = $proposedHtml;
        $nestedData['action']         = $action;

        $data[] = $nestedData;
        $proceed = 0;
    }
}
mysqli_stmt_close($mainStmt);

$json_data = array(
    "draw" => isset($request['draw']) ? intval($request['draw']) : 0,
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
);

echo json_encode($json_data);
