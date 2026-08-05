<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Helper: Calculate date difference in days
function dateDiffInDays($date1, $date2) {
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

// Helper: Leave type text lookup — sourced from leave_types table so the
// label always matches the DB. The previous hardcoded map had keys that
// did NOT correspond to real leave_types.leaveID values (e.g. it said
// 3=Casual, 8=Disability while the DB has 8=Casual, 19=Disability),
// producing wrong labels in the waiting-approve list.
function getLeaveTypeText($type) {
    global $con;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $r = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types");
        while ($row = mysqli_fetch_assoc($r)) {
            $cache[(int)$row['leaveID']] = $row['leaveTitle'];
        }
    }
    return $cache[(int)$type] ?? '';
}

// Sanitize and fetch user
$username = mysqli_real_escape_string($con, $_SESSION['username']);
$getUserInfoQ = mysqli_query($con, "SELECT * FROM user_list WHERE user_id='$username'");
$getUserInfo = mysqli_fetch_assoc($getUserInfoQ);
$employee_id = mysqli_real_escape_string($con, $getUserInfo['employee_id']);

// Hoisted existence check for the lazily-created return-history table.
$hasReturnHistory = false;
$_rrChk = mysqli_query($con, "SHOW TABLES LIKE 'leave_return_history'");
if ($_rrChk && mysqli_num_rows($_rrChk) > 0) $hasReturnHistory = true;

$request = $_REQUEST;

// Frontend column order: 0=row_check, 1=serial(dataID), 2=applicant, 3=section/center, 4=requested(dateFrom), 5=proposed(approvedDateFrom), 6=action
$columns = [
    0 => 'leave_data_for_approval.dataID',
    1 => 'leave_data_for_approval.dataID',
    2 => 'employee_list.employee_name',
    3 => 'employee_list.section_id',
    4 => 'leave_applications.dateFrom',
    5 => 'leave_applications.approvedDateFrom',
    6 => 'leave_data_for_approval.dataID',
];


$centerFilter    = (int)($_REQUEST['centerFilter']    ?? 0);
$sectionFilter   = (int)($_REQUEST['sectionFilter']   ?? 0);
$employeeFilter  = (int)($_REQUEST['employeeFilter']  ?? 0);
$leaveTypeFilter = (int)($_REQUEST['leaveTypeFilter'] ?? 0);
$dateFrom        = trim($_REQUEST['dateFrom'] ?? '');
$dateTo          = trim($_REQUEST['dateTo']   ?? '');

$filterSql = '';
if ($centerFilter > 0)    $filterSql .= " AND employee_list.organization_id = $centerFilter";
if ($sectionFilter > 0)   $filterSql .= " AND employee_list.section_id = $sectionFilter";
if ($employeeFilter > 0)  $filterSql .= " AND employee_list.id = $employeeFilter";
if ($leaveTypeFilter > 0) $filterSql .= " AND leave_applications.leaveType = $leaveTypeFilter";
if ($dateFrom !== '' && $dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $filterSql .= " AND leave_applications.dateFrom BETWEEN '$dateFrom' AND '$dateTo'";
}

// Base query
// Exclude status=3 (পুনঃ যাচাই — sent back to applicant/admin). Chain rows
// keep isApproved=0 across returns, so without this filter a returned app
// would reappear in the signatory queue before it's actually resubmitted.
$sql_base = "FROM leave_data_for_approval
    INNER JOIN leave_applications ON leave_data_for_approval.leaveApplicationID = leave_applications.dataID
    INNER JOIN employee_list ON leave_applications.applicantID = employee_list.id
    WHERE leave_data_for_approval.signatory = '$employee_id'
    AND leave_data_for_approval.isSentbyAdmin = 1
    AND leave_data_for_approval.isSupervisor != 1
    AND leave_data_for_approval.isApproved = 0
    AND leave_applications.status <> 3$filterSql";

// Count total records
$totalData = mysqli_num_rows(mysqli_query($con, "SELECT leave_data_for_approval.dataID $sql_base"));
$totalFiltered = $totalData;

// Filtering by column search
$search_sql = "";
foreach ($columns as $key => $column) {
    if (!empty($request['columns'][$key]['search']['value'])) {
        $search_value = mysqli_real_escape_string($con, $request['columns'][$key]['search']['value']);
        $search_sql .= " AND $column LIKE '%$search_value%'";
    }
}

// Count filtered records
$totalFiltered = mysqli_num_rows(mysqli_query($con, "SELECT leave_data_for_approval.dataID $sql_base $search_sql"));

// Sorting & Pagination
$start = intval($request['start']);
$length = intval($request['length']);
$columnIndex = intval($request['order'][0]['column']);
$sortColumn = $columns[$columnIndex];
// Default DESC (newest first). See fetch-waiting-supervise.php for rationale.
$sortDir = (isset($request['order'][0]['dir']) && strtolower($request['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

$sql = "SELECT
    leave_data_for_approval.dataID AS approvalDataID,
    leave_applications.dataID AS applicationID,
    leave_data_for_approval.*,
    leave_applications.*,
    employee_list.employee_name,
    employee_list.employee_id
$sql_base $search_sql
ORDER BY $sortColumn $sortDir
LIMIT $start, $length";

$query = mysqli_query($con, $sql);

$data = [];
$serial = $start;

$totalD = 0;

while ($row = mysqli_fetch_assoc($query)) {
    $proceed = 0;

    // Check previous signatory approval
    if ($row['prevSignatory'] == 0) {
        $proceed = 1;
    } else {
        $prevserial = $row['serial'] - 1;
        $checkPrev = mysqli_query($con, "SELECT * FROM leave_data_for_approval
            WHERE leaveApplicationID = '{$row['applicationID']}'
            AND signatory = '{$row['prevSignatory']}'
            AND isApproved = 1
            AND serial = '$prevserial'");
        if (mysqli_num_rows($checkPrev) > 0) {
            $proceed = 1;
        }
    }

    if ($proceed) {
        $serial++;
        $totalD++;

        // Get related info
        $empQ = mysqli_query($con, "SELECT e.*, j.job_title_name, s.section_name, o.organization_name
            FROM employee_list e
            LEFT JOIN job_title j ON e.designation = j.id
            LEFT JOIN sections s ON e.section_id = s.id
            LEFT JOIN organization o ON e.organization_id = o.id
            WHERE e.id = '{$row['applicantID']}'");
        $emp = mysqli_fetch_assoc($empQ);

        $leaveTypeQ = mysqli_query($con, "SELECT leaveTitle FROM leave_types WHERE leaveID = '{$row['leaveType']}'");
        $leaveType = mysqli_fetch_assoc($leaveTypeQ)['leaveTitle'] ?? '';

        // Dates
        $dateFrom = $row['dateFrom'];
        $dateTo   = $row['dateTo'];
        $requestedDays = dateDiffInDays($dateFrom, $dateTo) + 1;

        $hasApproved  = !empty($row['approvedDateFrom']) && !empty($row['approvedDateTo']);
        $proposedDays = $hasApproved ? dateDiffInDays($row['approvedDateFrom'], $row['approvedDateTo']) + 1 : 0;

        $proposed_leave_type = getLeaveTypeText($row['leaveTypeInTwo']);

        // Applicant cell (avatar + name + designation)
        $empName  = trim($row['employee_name'] ?? '');
        $empJob   = trim($emp['job_title_name'] ?? '');
        $empPhoto = trim($emp['photo'] ?? '');
        $empCode  = trim($row['employee_id'] ?? '');
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

        // Return-context chip. Two very different situations can put a row
        // into a signatory's অনুমোদন queue via a return:
        //   * to_applicant   → applicant fixed and resubmitted
        //   * to_previous_signatory → a higher signatory sent it BACK to me
        //     for re-verification (I need to re-review my earlier decision)
        //   * to_admin       → signatory bounced to admin, admin re-forwarded
        // The chip text/color needs to say which, otherwise it looks like a
        // plain resubmit even when a senior demanded re-verification.
        $_resubmitChip = '';
        if ($hasReturnHistory) {
            $_lid = (int)$row['applicationID'];
            $_rrq = mysqli_query($con,
                "SELECT returnType, returnedByName
                 FROM leave_return_history
                 WHERE leaveApplicationID = $_lid
                 ORDER BY dataID DESC LIMIT 1");
            if ($_rrq && $_rrRow = mysqli_fetch_assoc($_rrq)) {
                $_rt = $_rrRow['returnType'] ?? '';
                $_by = trim($_rrRow['returnedByName'] ?? '');
                if ($_rt === 'to_previous_signatory') {
                    $_chipTxt = 'উর্ধ্বতন সিদ্ধান্তকারী কর্তৃক পুনঃ যাচাইয়ের জন্য ফেরত'
                              . ($_by !== '' ? ' — ' . htmlspecialchars($_by) : '');
                    $_chipBg = '#fce8e6'; $_chipFg = '#a52a2a'; $_chipBd = '#f5c5c1';
                    $_chipIcon = 'tabler-arrow-back-up';
                } elseif ($_rt === 'to_admin') {
                    $_chipTxt = 'প্রশাসনিক ডেস্ক থেকে পুনঃ ফরওয়ার্ড';
                    $_chipBg = '#e5f0ff'; $_chipFg = '#1c4d94'; $_chipBd = '#c9dbf6';
                    $_chipIcon = 'tabler-share';
                } else {
                    // to_applicant (default)
                    $_chipTxt = 'পুনঃ যাচাইয়ের পর জমা';
                    $_chipBg = '#fff3e1'; $_chipFg = '#b8651a'; $_chipBd = '#f0d9a8';
                    $_chipIcon = 'tabler-refresh';
                }
                $_resubmitChip = '<div class="mt-1"><span style="display:inline-block;background:' . $_chipBg . ';color:' . $_chipFg . ';font-size:0.68rem;padding:2px 8px;border-radius:999px;border:1px solid ' . $_chipBd . ';line-height:1.3;"><i class="ti ' . $_chipIcon . ' me-1"></i>' . $_chipTxt . '</span></div>';
            }
        }

        $applicantCell = '<div class="emp-cell">' . $avatarHtml
                       . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName) . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                       . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                       . $_resubmitChip
                       . '</div></div>';

        // Section + center chips
        $secCenter = '';
        if (!empty($emp['section_name'])) {
            $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($emp['section_name']) . '</span>';
        }
        if (!empty($emp['organization_name'])) {
            if ($secCenter) $secCenter .= '<br>';
            $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($emp['organization_name']) . '</span>';
        }

        // Multi-segment breakdown — same convention as fetch-forwarded-pending.
        // When the application spans more than one segment, replace the
        // single-line "N দিন <type>" pill with a total-days pill plus a
        // seg-list showing each segment's days and type, so the approver
        // sees the split at a glance.
        $__appIDforSeg = (int)$row['applicationID'];
        $__segQ = mysqli_query($con, "SELECT s.*, lt.leaveTitle
                                       FROM leave_application_segments s
                                       LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
                                       WHERE s.applicationID = $__appIDforSeg
                                       ORDER BY s.kind ASC, s.serial ASC, s.dataID ASC");
        $__reqSegs = []; $__propSegs = [];
        if ($__segQ) while ($__sr = mysqli_fetch_assoc($__segQ)) {
            if (($__sr['kind'] ?? 'requested') === 'requested') $__reqSegs[] = $__sr;
            else                                                 $__propSegs[] = $__sr;
        }
        if (empty($__reqSegs)  && !empty($__propSegs)) $__reqSegs  = $__propSegs;
        if (empty($__propSegs) && !empty($__reqSegs))  $__propSegs = $__reqSegs;

        $__segChips = function(array $segs) {
            $parts = [];
            foreach ($segs as $sg) {
                $parts[] = '<span class="seg-pill">' . banglaNumber((int)$sg['days']) . ' দিন '
                         . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা') . '</span>';
            }
            return '<div class="seg-list">' . implode(' ', $parts) . '</div>';
        };

        // Requested
        $requestedHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date('d/m/Y', strtotime($dateFrom))) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date('d/m/Y', strtotime($dateTo))) . '</span></div>';
        if (count($__reqSegs) > 1) {
            $__reqTotal = array_sum(array_column($__reqSegs, 'days'));
            $requestedHtml .= '<div class="leave-meta"><span class="days-pill">মোট ' . banglaNumber($__reqTotal) . ' দিন</span></div>'
                            . $__segChips($__reqSegs);
        } else {
            $requestedHtml .= '<div class="leave-meta"><span class="days-pill">' . banglaNumber($requestedDays) . ' দিন</span>'
                            . ' <span class="leave-type-chip">' . htmlspecialchars($leaveType) . '</span></div>';
        }

        // Proposed
        if ($hasApproved) {
            $proposedHtml = '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date('d/m/Y', strtotime($row['approvedDateFrom']))) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date('d/m/Y', strtotime($row['approvedDateTo']))) . '</span></div>';
            if (count($__propSegs) > 1) {
                $__propTotal = array_sum(array_column($__propSegs, 'days'));
                $proposedHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">মোট ' . banglaNumber($__propTotal) . ' দিন</span></div>'
                              . $__segChips($__propSegs);
            } else {
                $proposedHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber($proposedDays) . ' দিন</span>'
                              . ($proposed_leave_type ? ' <span class="leave-type-chip">' . htmlspecialchars($proposed_leave_type) . '</span>' : '')
                              . '</div>';
            }
        } else {
            $proposedHtml = '<span class="text-muted small">—</span>';
        }

        // Action group
        $action = '<div class="action-group">';
        $action .= "<a target='_blank' href='application-details.php?menuslug=leave-approval&leaveApplicationID={$row['applicationID']}' class='action-icon icon-view' title='বিস্তারিত দেখুন'><i class='ti tabler-eye'></i></a>";
        if (!empty($row['attachment'])) {
            $action .= "<a target='_blank' href='uploads/{$row['attachment']}' class='action-icon icon-attach' title='সংযুক্তি দেখুন'><i class='ti tabler-paperclip'></i></a>";
        }
        $action .= "<a href='approve-application.php?menuslug=leave-approval&dataID={$row['approvalDataID']}&leaveApplicationID={$row['applicationID']}' class='action-icon icon-approve' title='অনুমোদন করুন'><i class='ti tabler-check'></i></a>";
        $action .= '</div>';

        $nestedData = [];
        $nestedData['row_check']      = '<input type="checkbox" class="form-check-input row-check" value="' . (int)$row['approvalDataID'] . '">';
        $nestedData['serial']         = '<span class="serial-num">' . $serial . '</span>';
        $nestedData['applicant_cell'] = $applicantCell;
        $nestedData['section_center'] = $secCenter;
        $nestedData['requested']      = $requestedHtml;
        $nestedData['proposed']       = $proposedHtml;
        $nestedData['action']         = $action;

        $data[] = $nestedData;
    }
}


$json_data = [
    "draw" => intval($request['draw']),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
];

header('Content-Type: application/json');
echo json_encode($json_data);
?>
