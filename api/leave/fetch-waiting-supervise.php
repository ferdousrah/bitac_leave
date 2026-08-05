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

// Return-history is a lazily-created table (see return-application.php).
// Hoist the existence check once so per-row lookups can skip it.
$hasReturnHistory = false;
$_rrChk = mysqli_query($con, "SHOW TABLES LIKE 'leave_return_history'");
if ($_rrChk && mysqli_num_rows($_rrChk) > 0) $hasReturnHistory = true;

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
// Exclude status=3 (পুনঃ যাচাই — sent back to applicant). return-application.php
// resets isApproved=0 on the supervisor's row so the applicant edit-and-resubmit
// path can restore it, but until the applicant actually resubmits (which flips
// status back to 0), the app is sitting on the applicant's desk — not the
// supervisor's. Without this filter, returned apps re-appear in the supervisor
// queue immediately.
$baseFrom = " FROM `leave_data_for_approval`
              INNER JOIN `leave_applications` ON `leave_data_for_approval`.leaveApplicationID=`leave_applications`.dataID
              INNER JOIN employee_list ON `leave_applications`.applicantID=employee_list.id
              WHERE `leave_data_for_approval`.signatory = ?
                AND `leave_data_for_approval`.isSupervisor = 1
                AND `leave_data_for_approval`.isApproved = 0
                AND `leave_applications`.status <> 3"
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
// Default DESC (newest first) so direct calls without an explicit
// order param don't push the latest application to the bottom.
$orderDir = (isset($request['order'][0]['dir']) && strtolower($request['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';
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

        // The old $leaveTypeInTwoMap here mislabelled leave types because its
        // hardcoded keys never matched the real leave_types.leaveID values.
        // At this "waiting to supervise" stage the applicant hasn't been
        // approved yet, so the proposed leave equals the requested leave type.
        $proposed_leave_type = $getLeaveTypeQRW['leaveTitle'] ?? '';

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

        // Return-context chip. Text/colour reflect the LATEST return type
        // so a fresh applicant resubmit doesn't look identical to a senior
        // signatory demanding re-verification. Types:
        //   * to_applicant           → applicant fixed and resubmitted
        //   * to_previous_signatory  → a higher signatory sent it back to me
        //   * to_admin               → admin re-forwarded after a bounce
        $_resubmitChip = '';
        if ($hasReturnHistory) {
            $_rr = pq_fetch_one($con,
                "SELECT returnType, returnedByName
                 FROM leave_return_history
                 WHERE leaveApplicationID = ?
                 ORDER BY dataID DESC LIMIT 1",
                'i', $leaveAppID);
            if ($_rr) {
                $_rt = $_rr['returnType'] ?? '';
                $_by = trim($_rr['returnedByName'] ?? '');
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

        // ── Section + Center as stacked chips ──────────────────────
        $secCenter = '';
        if (!empty($getSectionDetailsQRW['section_name'])) {
            $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($getSectionDetailsQRW['section_name']) . '</span>';
        }
        if (!empty($getorgDetailsQRW['organization_name'])) {
            if ($secCenter) $secCenter .= '<br>';
            $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($getorgDetailsQRW['organization_name']) . '</span>';
        }

        // ── Multi-segment breakdown ────────────────────────────────
        // Same source of truth as fetch-forwarded-pending: split segments
        // by kind ('requested' / 'proposed'). Build a small seg-list block
        // that gets appended to the leave-meta div when the application
        // spans more than one segment, so the reviewer sees "৩ দিন গড়
        // বেতন + ২ দিন নৈমিত্তিক" at a glance instead of only the total.
        $__appIDforSeg = (int)$empRow['leaveApplicationID'];
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

        // ── Requested leave (date-range + days-pill + leave-type-chip) ──
        $requestedHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date_format($dateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($dateT, "d/m/Y")) . '</span></div>';
        if (count($__reqSegs) > 1) {
            $__reqTotal = array_sum(array_column($__reqSegs, 'days'));
            $requestedHtml .= '<div class="leave-meta"><span class="days-pill">মোট ' . banglaNumber($__reqTotal) . ' দিন</span></div>'
                            . $__segChips($__reqSegs);
        } else {
            $requestedHtml .= '<div class="leave-meta"><span class="days-pill">' . banglaNumber($dateDiff) . ' দিন</span>'
                            . ' <span class="leave-type-chip">' . htmlspecialchars($getLeaveTypeQRW['leaveTitle'] ?? '') . '</span></div>';
        }

        // ── Proposed leave (only if approvedDate range present) ────
        $proposedHtml = '';
        if ($hasApproved) {
            $proposedHtml = '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date_format($adateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($adateT, "d/m/Y")) . '</span></div>';
            if (count($__propSegs) > 1) {
                $__propTotal = array_sum(array_column($__propSegs, 'days'));
                $proposedHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">মোট ' . banglaNumber($__propTotal) . ' দিন</span></div>'
                              . $__segChips($__propSegs);
            } else {
                $proposedHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber($adateDiff) . ' দিন</span>'
                              . ($proposed_leave_type ? ' <span class="leave-type-chip">' . htmlspecialchars($proposed_leave_type) . '</span>' : '')
                              . '</div>';
            }
        } else {
            $proposedHtml = '<span class="text-muted small">—</span>';
        }

        // ── Action group ──────────────────────────────────────────
        $action = '<div class="action-group">';
        $action .= "<a href='javascript:void(0);' data-url='application-details.php?menuslug=leave-approval&leaveApplicationID={$empRow['leaveApplicationID']}' class='action-icon icon-view app-doc-view' title='বিস্তারিত দেখুন'><i class='ti tabler-eye'></i></a>";
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
