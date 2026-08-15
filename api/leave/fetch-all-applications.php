<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');
require_once(__DIR__ . '/../../includes/joining-effective-leave.php');

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

$sessionUserID = $_SESSION['userID'] ?? '';
$getUserInfoQRW = pq_fetch_one($con, "SELECT * FROM user_list WHERE dataID = ?", 's', $sessionUserID);
$sessionEmployeeId = $getUserInfoQRW['employee_id'] ?? '';

// Hoisted existence check for the lazily-created return-history table.
$hasReturnHistory = false;
$_rrChk = mysqli_query($con, "SHOW TABLES LIKE 'leave_return_history'");
if ($_rrChk && mysqli_num_rows($_rrChk) > 0) $hasReturnHistory = true;

// DataTables server-side parameters
$draw         = isset($_POST['draw'])   ? intval($_POST['draw'])   : 1;
$start        = isset($_POST['start'])  ? max(0, intval($_POST['start']))  : 0;
$length       = isset($_POST['length']) ? max(1, intval($_POST['length'])) : 10;
$searchValue  = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
$statusFilter = isset($_POST['statusFilter']) ? $_POST['statusFilter'] : 'all';

// Map filter chip → la.status value
$statusClause = '';
$statusTypes  = '';
$statusParams = [];
if ($statusFilter === 'pending') {
    $statusClause   = ' AND la.status = ?';
    $statusTypes    = 'i';
    $statusParams[] = 0;
} else if ($statusFilter === 'approved') {
    $statusClause   = ' AND la.status = ?';
    $statusTypes    = 'i';
    $statusParams[] = 1;
} else if ($statusFilter === 'rejected') {
    $statusClause   = ' AND la.status = ?';
    $statusTypes    = 'i';
    $statusParams[] = 2;
} else if ($statusFilter === 'returned') {
    $statusClause   = ' AND la.status = ?';
    $statusTypes    = 'i';
    $statusParams[] = 3;
}

// Base FROM/WHERE used by both counts and main query
// NOTE: an earlier version filtered out la.status = 3 (ফেরত পাঠানো
// applications). That hid every returned application from the
// applicant's history page even though the row rendering already
// handles status = 3 ("ফেরত — সম্পাদনা করুন" badge). Removed the
// exclusion so returned applications remain visible to the applicant.
$baseFrom = "
FROM leave_applications la
INNER JOIN employee_list el  ON la.applicantID = el.id
LEFT  JOIN job_title     jt  ON el.designation = jt.id
LEFT  JOIN sections      s   ON el.section_id  = s.id
LEFT  JOIN leave_types   lt  ON la.leaveType   = lt.leaveID
LEFT  JOIN leave_types   alt ON la.approvedLeaveType = alt.leaveID
LEFT  JOIN leave_joining_application lja ON lja.leaveApplicationID = la.dataID
WHERE (la.applicantID = ? OR la.submitBy = ?)
  $statusClause";

$baseTypes  = 'ss' . $statusTypes;
$baseParams = array_merge([$sessionEmployeeId, $sessionUserID], $statusParams);

// Optional search
$searchClause = '';
$searchTypes  = $baseTypes;
$searchParams = $baseParams;
if (!empty($searchValue)) {
    $searchClause   = ' AND (la.dataID LIKE ?)';
    $searchTypes   .= 's';
    $searchParams[] = '%' . $searchValue . '%';
}

// Counts
$totalStmt = mysqli_prepare($con, "SELECT COUNT(DISTINCT la.dataID) AS total $baseFrom");
mysqli_stmt_bind_param($totalStmt, $baseTypes, ...$baseParams);
mysqli_stmt_execute($totalStmt);
$totalRecords = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['total'] ?? 0);
mysqli_stmt_close($totalStmt);

$filteredStmt = mysqli_prepare($con, "SELECT COUNT(DISTINCT la.dataID) AS total $baseFrom $searchClause");
mysqli_stmt_bind_param($filteredStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($filteredStmt);
$filteredRecords = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($filteredStmt))['total'] ?? 0);
mysqli_stmt_close($filteredStmt);

// Main query: select all needed fields in one round trip
$dataSql = "
SELECT
    la.dataID                   AS dataID,
    la.applicantID              AS applicantID,
    la.leaveType                AS leaveType,
    la.approvedLeaveType        AS approvedLeaveType,
    la.status                   AS status,
    la.dateFrom                 AS dateFrom,
    la.dateTo                   AS dateTo,
    la.primaryLeaveDateFrom     AS primaryLeaveDateFrom,
    la.primaryLeaveDateTo       AS primaryLeaveDateTo,
    la.primaryApprovedLeaveType AS primaryApprovedLeaveType,
    la.primaryApprovedLeaveDays AS primaryApprovedLeaveDays,
    la.applicationType          AS applicationType,
    la.approvedDateTo           AS approvedDateTo,
    la.cancellationReasion      AS cancellationReasion,
    la.cancellationDate         AS cancellationDate,
    la.declinedBy               AS declinedBy,
    el.employee_name            AS employee_name,
    el.photo                    AS employee_photo,
    jt.job_title_name           AS job_title_name,
    s.section_name              AS section_name,
    lt.leaveTitle               AS leaveTitle,
    alt.leaveTitle              AS approvedLeaveTitle,
    lja.requestedJoiningDate    AS requestedJoiningDate,
    lja.joiningType             AS joiningType,
    lja.status                  AS lja_status,
    lja.approvedDate            AS lja_approvedDate,
    lja.approvedLeaveType       AS lja_approvedLeaveType,
    lja.extensionSegmentsJson   AS lja_extensionSegmentsJson,
    (lja.leaveApplicationID IS NOT NULL) AS has_joining,
    (SELECT COUNT(*) FROM leave_data_for_approval pending
       WHERE pending.leaveApplicationID = la.dataID
         AND pending.isSupervisor = 1
         AND pending.isApproved   = 0
         AND pending.isRead       = 0
    ) AS pending_supervisor_count,
    (SELECT COUNT(*) FROM leave_data_for_approval ap
       WHERE ap.leaveApplicationID = la.dataID
         AND ap.isApproved = 1
    ) AS approved_signatory_count
$baseFrom
$searchClause
ORDER BY la.dataID DESC
LIMIT $start, $length";

$dataStmt = mysqli_prepare($con, $dataSql);
mysqli_stmt_bind_param($dataStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($dataStmt);
$dataResult = mysqli_stmt_get_result($dataStmt);

$data = array();
$serial = $start + 1;

while ($row = mysqli_fetch_assoc($dataResult)) {
    $hasJoining = !empty($row['has_joining']);

    // Employee info — avatar + stacked name/designation/section
    $empName = trim($row['employee_name'] ?? '');
    $empJob  = trim($row['job_title_name'] ?? '');
    $empSec  = trim($row['section_name'] ?? '');
    $empPhoto = trim($row['employee_photo'] ?? '');
    // Initials for fallback (first 2 chars of name, supports Bangla)
    $initials = mb_substr($empName, 0, 1, 'UTF-8');
    if (mb_strlen($empName, 'UTF-8') > 1) {
        $parts = preg_split('/\s+/u', $empName);
        if (count($parts) > 1) {
            $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
        }
    }
    $avatarHtml = '';
    if (!empty($empPhoto)) {
        $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
        $avatarHtml = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="' . htmlspecialchars($empName) . '" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
    } else {
        $avatarHtml = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
    }
    // Detect whether this application has ever been sent back for পুনঃ যাচাই.
    // Drives two decorations further down: the row chip (only for resubmitted
    // rows, status != 3) and the "ফেরতকৃত আবেদন" label on the actions
    // dropdown (for every row that carries return history).
    $_wasReturned = false;
    if ($hasReturnHistory) {
        $_lid = (int)$row['dataID'];
        $_rrCntQ = mysqli_query($con, "SELECT COUNT(*) c FROM leave_return_history WHERE leaveApplicationID = $_lid");
        if ($_rrCntQ && (int)(mysqli_fetch_assoc($_rrCntQ)['c'] ?? 0) > 0) {
            $_wasReturned = true;
        }
    }

    // Chip: only for resubmitted rows (status != 3). Status=3 rows already
    // get the amber pill + the full return-reason callout below.
    $_resubmitChip = '';
    if ($_wasReturned && (int)$row['status'] !== 3) {
        $_resubmitChip = '<div class="mt-1"><span style="display:inline-block;background:#fff3e1;color:#b8651a;font-size:0.68rem;padding:2px 8px;border-radius:999px;border:1px solid #f0d9a8;line-height:1.3;"><i class="ti tabler-refresh me-1"></i>পুনঃ যাচাইয়ের পর জমা</span></div>';
    }

    $employee_info = '<div class="emp-cell">' . $avatarHtml
                   . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName) . '</div>'
                   . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                   . ($empSec ? '<div class="emp-sub-light">' . htmlspecialchars($empSec) . '</div>' : '')
                   . $_resubmitChip
                   . '</div></div>';

    // Requested leave
    $leaveApplicationDateF = date_create($row['dateFrom']);
    $leaveApplicationDateT = date_create($row['dateTo']);
    $totalReqDays          = dateDiffInDays($row['dateFrom'], $row['dateTo']) + 1;

    // Check for multi-segment — if exists, build per-segment breakdown.
    // This column is চাহিত ছুটি, so it must read the frozen 'requested' rows;
    // the 'proposed' copies track what the desks have since edited and would
    // contradict the applicant's own date range and total. Legacy rows predate
    // the kind column, so fall back to those when no requested rows exist.
    $_segStmt = mysqli_prepare($con,
        "SELECT s.kind, s.days, s.dateFrom, s.dateTo, lt.leaveTitle
         FROM leave_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.applicationID = ?
         ORDER BY s.serial ASC, s.dataID ASC");
    mysqli_stmt_bind_param($_segStmt, 'i', $row['dataID']);
    mysqli_stmt_execute($_segStmt);
    $_segRes = mysqli_stmt_get_result($_segStmt);
    $_reqSegs  = [];
    $_propSegs = [];
    $_snapSegs = [];
    $_oldSegs  = [];
    while ($_sr = mysqli_fetch_assoc($_segRes)) {
        if ($_sr['kind'] === 'requested')     $_reqSegs[]  = $_sr;
        elseif ($_sr['kind'] === 'proposed')  $_propSegs[] = $_sr;
        elseif ($_sr['kind'] === 'approved')  $_snapSegs[] = $_sr;
        elseif ($_sr['kind'] === null || $_sr['kind'] === '') $_oldSegs[] = $_sr;
    }
    mysqli_stmt_close($_segStmt);
    $_segs = $_reqSegs ?: $_oldSegs;
    // The desks' current proposal — still what the spent column projects from.
    $_apprSegs = $_propSegs ?: $_oldSegs;
    // Frozen at final approval. Applications approved before the snapshot
    // existed have none, so fall back to the live rows.
    $_primarySegs = $_snapSegs ?: $_apprSegs;

    // Renders the shared "মোট N দিন" pill + per-segment chips, date-stamping the
    // chips when the segments have gaps so the range above can't mislead.
    $segBreakdown = function(array $segs, $pillClass) {
        $gapped = !joining_segments_contiguous($segs);
        $parts  = [];
        foreach ($segs as $sg) {
            $parts[] = '<span class="seg-pill">'
                     . ($gapped ? joining_segment_dates($sg) . ' · ' : '')
                     . banglaNumber((int)$sg['days']) . ' দিন '
                     . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা') . '</span>';
        }
        $total = array_sum(array_map(function($sg) { return (int)$sg['days']; }, $segs));
        return '<div class="leave-meta"><span class="days-pill ' . $pillClass . '">মোট '
             . banglaNumber($total) . ' দিন</span></div>'
             . '<div class="seg-list">' . implode(' ', $parts) . '</div>';
    };

    $reqDateRange = '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date_format($leaveApplicationDateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($leaveApplicationDateT, "d/m/Y")) . '</span></div>';
    if (count($_segs) > 1) {
        // Sum the segments rather than spanning the date range, so a gap
        // between segments can't leave the total disagreeing with the chips.
        $requested_leave = $reqDateRange . $segBreakdown($_segs, '');
    } else {
        $requested_leave = $reqDateRange
                         . '<div class="leave-meta"><span class="days-pill">' . banglaNumber($totalReqDays) . ' দিন</span>'
                         . ' <span class="leave-type-chip">' . htmlspecialchars($row['leaveTitle'] ?? '') . '</span></div>';
    }

    // Approved leave
    $approved_leave = '';
    if ($row['status'] == 1 && !empty($row['approvedLeaveTitle'])) {
        $adateF    = date_create($row['primaryLeaveDateFrom']);
        $adateT    = date_create($row['primaryLeaveDateTo']);
        // Sum the frozen segments; the span is only a fallback for rows that
        // predate segments entirely.
        $adateDiff = $_primarySegs
            ? array_sum(array_column($_primarySegs, 'days'))
            : dateDiffInDays($row['primaryLeaveDateFrom'], $row['primaryLeaveDateTo']) + 1;

        $approved_leave = '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date_format($adateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($adateT, "d/m/Y")) . '</span></div>';
        if (count($_primarySegs) > 1) {
            $approved_leave .= $segBreakdown($_primarySegs, 'days-pill-success');
        } else {
            // The type must come off the frozen segment, not la.approvedLeaveType —
            // a joining desk overwrites that column, which would make this column
            // report a type that was never granted at approval time.
            $_primaryTitle = $_primarySegs
                ? ($_primarySegs[0]['leaveTitle'] ?? '')
                : ($row['approvedLeaveTitle'] ?? '');
            $approved_leave .= '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber($adateDiff) . ' দিন</span>'
                             . ' <span class="leave-type-chip">' . htmlspecialchars($_primaryTitle) . '</span></div>';
        }
    }

    // প্রস্তাবিত — what the joining desks now propose, shown only when it differs
    // from the frozen approval; otherwise it would just repeat the column beside it.
    $proposed_leave = '';
    if ($row['status'] == 1 && $_apprSegs) {
        $_changed = false;
        if ($_snapSegs) {
            // Snapshot available — compare segment for segment.
            $_changed = (count($_snapSegs) !== count($_apprSegs));
            if (!$_changed) {
                foreach ($_apprSegs as $_i => $_ps) {
                    if (($_ps['leaveTitle'] ?? '') !== ($_snapSegs[$_i]['leaveTitle'] ?? '')
                        || (int)$_ps['days'] !== (int)$_snapSegs[$_i]['days']) { $_changed = true; break; }
                }
            }
        } elseif (!empty($row['primaryApprovedLeaveType'])) {
            // No snapshot, but the frozen primary type and day count still say
            // what was granted. Compared loosely — the same leave may legitimately
            // be split across several segments.
            $_pTitle = joining_leave_titles($con)[(int)$row['primaryApprovedLeaveType']] ?? '';
            $_pDays  = (int)($row['primaryApprovedLeaveDays'] ?: 0);
            if ($_pDays <= 0 && !empty($row['primaryLeaveDateFrom']) && !empty($row['primaryLeaveDateTo'])) {
                $_pDays = dateDiffInDays($row['primaryLeaveDateFrom'], $row['primaryLeaveDateTo']) + 1;
            }
            $_sum    = array_sum(array_column($_apprSegs, 'days'));
            if ($_sum !== $_pDays) {
                $_changed = true;
            } else {
                foreach ($_apprSegs as $_ps) {
                    if (($_ps['leaveTitle'] ?? '') !== $_pTitle) { $_changed = true; break; }
                }
            }
        }
        if ($_changed) $proposed_leave = $segBreakdown($_apprSegs, 'days-pill-warning');
    }

    // Spent leave
    $spent_leave = '';
    $leaveSpentDateFrom = null;
    if ($hasJoining) {
        // Project the approved segments through the joining rules instead of
        // spanning the dates — a gap between segments isn't leave, and an
        // early joining cuts the last segment short.
        // Once the joining is finalised the desks have already written the
        // extension into the live segments, so projecting it a second time would
        // count the extension twice. Only a joining still in flight needs the
        // projection.
        if ((int)($row['lja_status'] ?? 0) === 1) {
            $_spentSegs = $_apprSegs;
        } else {
            $_spentSegs = joining_effective_segments($_apprSegs, $row['joiningType'], $row['requestedJoiningDate'], [
                'extensionSegmentsJson' => $row['lja_extensionSegmentsJson'] ?? null,
                'approvedDateTo'        => $row['approvedDateTo'] ?? '',
                'extLeaveType'          => $row['lja_approvedLeaveType'] ?? 0,
                'leaveTitles'           => joining_leave_titles($con),
            ]);
        }
        $_spentSpan = joining_segments_span($_spentSegs);
        if ($_spentSpan['days'] > 0) {
            $leaveSpentDateFrom = date_create($_spentSpan['from']);
            $leaveSpentDateTo   = date_create($_spentSpan['to']);
            $leaveSpent         = $_spentSpan['days'];
        } else {
            $leaveSpentDateFrom = date_create($row['primaryLeaveDateFrom']);
            $leaveSpentDateTo   = date_create($row['requestedJoiningDate']);
            $leaveSpent         = dateDiffInDays($row['primaryLeaveDateFrom'], $row['requestedJoiningDate']) + 1;
        }

        $spent_leave = '<div class="date-range"><i class="ti tabler-clock-check"></i><span>' . banglaNumber(date_format($leaveSpentDateFrom, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($leaveSpentDateTo, "d/m/Y")) . '</span></div>';
        if (count($_spentSegs) > 1) {
            $spent_leave .= $segBreakdown($_spentSegs, 'days-pill-info');
        } else {
            // Same rule as the column beside it — the name comes from the segment
            // that was actually spent, via leave_types, never a hand-written map.
            $_spentTitle = $_spentSegs ? ($_spentSegs[0]['leaveTitle'] ?? '') : '';
            $spent_leave .= '<div class="leave-meta"><span class="days-pill days-pill-info">' . banglaNumber($leaveSpent) . ' দিন</span>'
                          . ($_spentTitle ? ' <span class="leave-type-chip">' . htmlspecialchars($_spentTitle) . '</span>' : '')
                          . '</div>';
        }
    }

    // Joining type
    $joining_type = '';
    if ($hasJoining) {
        $jtLabel = '';
        $jtIcon = '';
        $jtClass = '';
        if ($row['joiningType'] == 1)      { $jtLabel = "সঠিক সময়ে যোগদান";       $jtIcon = 'ti-clock'; $jtClass = 'jt-ontime'; }
        else if ($row['joiningType'] == 2) { $jtLabel = "অগ্রিম যোগদান";           $jtIcon = 'ti-calendar-minus'; $jtClass = 'jt-early'; }
        else if ($row['joiningType'] == 3) { $jtLabel = "বর্ধিত ছুটির আবেদন";       $jtIcon = 'ti-calendar-plus'; $jtClass = 'jt-extend'; }
        if ($jtLabel) {
            $joining_type = '<span class="jt-chip ' . $jtClass . '"><i class="ti tabler-' . substr($jtIcon, 3) . ' me-1"></i>' . $jtLabel . '</span>';
        }
    }

    // Corrected leave
    $corrected_leave = '';
    if ($hasJoining && !empty($row['lja_approvedDate'])) {
        $correctionJoiningDate = date_create($row['approvedDateTo']);
        // Segment sum, not the span — a gap between segments is not leave.
        $correctedLeaveSpent   = $_spentSegs
            ? array_sum(array_column($_spentSegs, 'days'))
            : dateDiffInDays($row['primaryLeaveDateFrom'], $row['approvedDateTo']) + 1;

        // Names come from leave_types. The old hand-written id → name list here
        // only covered ids 1-6 and 10, so anything outside it (e.g. 21, বিনাবেতনে)
        // silently rendered no type at all.
        $_corrTitles = [];
        foreach ($_spentSegs as $_cs) {
            $_t = trim((string)($_cs['leaveTitle'] ?? ''));
            if ($_t !== '' && !in_array($_t, $_corrTitles, true)) $_corrTitles[] = $_t;
        }
        if (!$_corrTitles && !empty($row['lja_approvedLeaveType'])) {
            $_t = joining_leave_titles($con)[(int)$row['lja_approvedLeaveType']] ?? '';
            if ($_t !== '') $_corrTitles[] = $_t;
        }
        $corrected_leave = '<div class="date-range"><i class="ti tabler-edit"></i><span>' . banglaNumber(date_format($leaveSpentDateFrom, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($correctionJoiningDate, "d/m/Y")) . '</span></div>';
        if (count($_spentSegs) > 1) {
            // Break the correction down the same way every other leave column
            // does — the total alone hides an extension granted on a different
            // leave type from the original approval.
            $corrected_leave .= $segBreakdown($_spentSegs, 'days-pill-warning');
        } else {
            $corrected_leave .= '<div class="leave-meta"><span class="days-pill days-pill-warning">' . banglaNumber($correctedLeaveSpent) . ' দিন</span>';
            foreach ($_corrTitles as $_t) {
                $corrected_leave .= ' <span class="leave-type-chip">' . htmlspecialchars($_t) . '</span>';
            }
            $corrected_leave .= '</div>';
        }
    }

    // Status
    $status = '';
    if ($row['status'] == 1 && $hasJoining) {
        if ($row['lja_status'] == 0)      $status = '<span class="status-pill status-pending"><i class="ti tabler-clock me-1"></i>যোগদানপত্র অপেক্ষমান</span>';
        else if ($row['lja_status'] == 2) $status = '<span class="status-pill status-rejected"><i class="ti tabler-x me-1"></i>যোগদানপত্র অনুমোদিত হয়নি</span>';
        else if ($row['lja_status'] == 1) $status = '<span class="status-pill status-approved"><i class="ti tabler-check me-1"></i>যোগদানপত্র অনুমোদিত</span>';
        else if ($row['lja_status'] == 3) $status = '<span class="status-pill" style="background:#fff8e6;color:#8b6f47;"><i class="ti tabler-corner-up-left me-1"></i>যোগদান পত্র ফেরত</span>';

        // Tell the applicant where the joining letter is sitting. Walking the
        // whole chain rather than filtering to "who may act now" matters: after
        // the supervisor recommends, a Type 2/3 letter waits at the centre admin
        // for forwarding, and every chain row is still isSentbyAdmin = 0 — that
        // filter matches nothing, so the cell used to just go blank.
        if ((int)$row['lja_status'] === 0) {
            $_lid = (int)$row['dataID'];
            $_chain = [];
            $_cq = mysqli_query($con,
                "SELECT ldfa.serial, ldfa.isSupervisor, ldfa.isSentbyAdmin, ldfa.isApproved,
                        el.employee_name, jt.job_title_name
                 FROM leave_joining_data_for_approval ldfa
                 LEFT JOIN employee_list el ON ldfa.signatory = el.id
                 LEFT JOIN job_title jt     ON el.designation  = jt.id
                 WHERE ldfa.leaveApplicationID = $_lid
                 ORDER BY ldfa.serial ASC");
            if ($_cq) while ($_cr = mysqli_fetch_assoc($_cq)) $_chain[] = $_cr;

            $_total = count($_chain);
            $_next  = null;
            foreach ($_chain as $_cr) {
                if ((int)$_cr['isApproved'] === 0) { $_next = $_cr; break; }
            }

            if ($_next) {
                $_name  = trim($_next['employee_name']  ?? '');
                $_title = trim($_next['job_title_name'] ?? '');
                $_ser   = (int)($_next['serial'] ?? 0);
                $_isSup = ((int)$_next['isSupervisor']  === 1);
                $_fwded = ((int)$_next['isSentbyAdmin'] === 1);
                $_line  = 'font-size:0.74rem;line-height:1.35;color:#5d3f1c;';
                $_prog  = ($_ser > 0 && $_total > 0)
                    ? ' <span style="background:#6c5ce7;color:#fff;padding:1px 6px;border-radius:0.3rem;font-size:0.65rem;margin-left:4px;">' . banglaNumber($_ser) . '/' . banglaNumber($_total) . '</span>'
                    : '';

                $_who = function ($name, $title) {
                    return '<strong>' . htmlspecialchars($name) . '</strong>'
                         . ($title !== '' ? ' <span style="color:#8a90a6;">— ' . htmlspecialchars($title) . '</span>' : '');
                };

                if ($_isSup || $_fwded) {
                    if ($_name !== '') {
                        $status .= '<div class="mt-1 small" style="' . $_line . '">'
                                .  '<i class="ti tabler-user-check me-1" style="color:#b8651a;"></i>'
                                .  $_who($_name, $_title)
                                .  ' <span class="text-muted">(' . ($_isSup ? 'সুপারিশের' : 'অনুমোদনের') . ' অপেক্ষায়)</span>'
                                .  $_prog
                                .  '</div>';
                    }
                } else {
                    // Recommended, but the centre admin hasn't forwarded it yet.
                    $status .= '<div class="mt-1 small" style="' . $_line . '">'
                            .  '<i class="ti tabler-building-bank me-1" style="color:#b8651a;"></i>'
                            .  '<strong>কেন্দ্র প্রশাসন</strong> <span class="text-muted">(প্রেরণের অপেক্ষায়)</span>'
                            .  '</div>';
                    if ($_name !== '') {
                        $status .= '<div class="small" style="' . $_line . 'color:#8a90a6;">'
                                .  '<i class="ti tabler-arrow-narrow-right me-1"></i>পরবর্তী: ' . $_who($_name, $_title)
                                .  $_prog
                                .  '</div>';
                    }
                }
            }
        }
    } else if ($row['status'] == 1 && !$hasJoining) {
        if ($row['applicationType'] == 1) {
            $status = '<div class="btn-group">
                <button type="button" class="btn btn-success btn-sm dropdown-toggle rounded-pill" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti tabler-user-check me-1"></i>কর্মক্ষেত্রে যোগদান
                </button>
                <ul class="dropdown-menu shadow-sm">
                    <li><a class="dropdown-item" href="../../views/leave/joining-application.php?leaveApplicationID=' . $row['dataID'] . '&type=1&menuslug=all-leave-application">
                        <i class="ti tabler-clock me-2"></i>সঠিক সময়ে যোগদান
                    </a></li>
                    <li><a class="dropdown-item" href="../../views/leave/joining-application.php?leaveApplicationID=' . $row['dataID'] . '&type=2&menuslug=all-leave-application">
                        <i class="ti tabler-calendar-minus me-2"></i>ছুটি পূর্ণ ভোগ না করে অগ্রিম যোগদান
                    </a></li>
                    <li><a class="dropdown-item" href="../../views/leave/joining-application.php?leaveApplicationID=' . $row['dataID'] . '&type=3&menuslug=all-leave-application">
                        <i class="ti tabler-calendar-plus me-2"></i>বর্ধিত ছুটি মঞ্জুর ও কর্মস্থলে যোগদানের অনুমতি
                    </a></li>
                </ul>
            </div>';
        } else if ($row['applicationType'] == 2) {
            $status = '<span class="status-pill status-approved"><i class="ti tabler-check me-1"></i>যোগদান ও ছুটি অনুমোদিত</span>';
        }
    } else if ($row['status'] == 2 && !$hasJoining) {
        $status = '<span class="status-pill status-rejected"><i class="ti tabler-x me-1"></i>অনুমোদিত হয়নি</span>';

        // Show who declined it, when, and why — the applicant otherwise only
        // saw the bare "অনুমোদিত হয়নি" pill with no explanation. The reason is
        // written to leave_applications.cancellationReasion by the decline
        // branch of api/leave/approve-application.php; fall back to the note
        // on the declining chain row for older records that predate it.
        $_reason = trim((string)($row['cancellationReasion'] ?? ''));
        $_by     = '';
        $_byTitle = '';
        $_declinedBy = (int)($row['declinedBy'] ?? 0);
        if ($_declinedBy > 0) {
            $_dq = mysqli_query($con,
                "SELECT el.employee_name, jt.job_title_name
                 FROM employee_list el
                 LEFT JOIN job_title jt ON el.designation = jt.id
                 WHERE el.id = $_declinedBy LIMIT 1");
            if ($_dq && $_dr = mysqli_fetch_assoc($_dq)) {
                $_by      = trim($_dr['employee_name'] ?? '');
                $_byTitle = trim($_dr['job_title_name'] ?? '');
            }
        }
        if ($_reason === '') {
            $_lid2 = (int)$row['dataID'];
            $_nq = mysqli_query($con,
                "SELECT note FROM leave_data_for_approval
                 WHERE leaveApplicationID = $_lid2 AND isApproved = 2 AND note <> ''
                 ORDER BY approvedDate DESC, dataID DESC LIMIT 1");
            if ($_nq && $_nr = mysqli_fetch_assoc($_nq)) {
                $_reason = trim($_nr['note'] ?? '');
            }
        }
        $_when = !empty($row['cancellationDate'])
            ? banglaNumber(date('d/m/Y', strtotime($row['cancellationDate'])))
            : '';

        if ($_by !== '' || $_reason !== '') {
            $status .= '<div class="mt-2 p-2" style="background:#fdecec;border:1px solid #f5c5c1;border-radius:0.4rem;font-size:0.75rem;line-height:1.5;color:#7a2020;max-width:320px;">'
                     . '<div class="fw-semibold mb-1" style="color:#a52a2a;"><i class="ti tabler-user-x me-1"></i>না মঞ্জুর করেছেন'
                     . ($_by !== '' ? ': ' . htmlspecialchars($_by) : '')
                     . ($_byTitle !== '' ? ' <span style="color:#8a90a6;">(' . htmlspecialchars($_byTitle) . ')</span>' : '')
                     . ($_when !== '' ? ' <span class="text-muted">— ' . $_when . '</span>' : '')
                     . '</div>'
                     . ($_reason !== '' ? '<div><i class="ti tabler-message me-1"></i><strong>কারণ:</strong> ' . nl2br(htmlspecialchars($_reason)) . '</div>' : '')
                     . '</div>';
        }
    } else if ($row['status'] == 3) {
        $status = '<span class="status-pill" style="background:#fff3e1;color:#b8651a;"><i class="ti tabler-corner-up-left me-1"></i>পুনঃ যাচাই — সম্পাদনা করুন</span>';

        // Show the latest return reason + who returned it, so the applicant
        // knows what needs to change before resubmitting.
        $_lid = (int)$row['dataID'];
        $_rrCheck = mysqli_query($con, "SHOW TABLES LIKE 'leave_return_history'");
        if ($_rrCheck && mysqli_num_rows($_rrCheck) > 0) {
            $_rrQ = mysqli_query($con,
                "SELECT returnedByName, returnedByTitle, note, createdAt
                 FROM leave_return_history
                 WHERE leaveApplicationID = $_lid
                 ORDER BY dataID DESC LIMIT 1");
            if ($_rrQ && $_rr = mysqli_fetch_assoc($_rrQ)) {
                $_by    = trim($_rr['returnedByName']  ?? '');
                $_title = trim($_rr['returnedByTitle'] ?? '');
                $_note  = trim($_rr['note']            ?? '');
                $_when  = !empty($_rr['createdAt']) ? banglaNumber(date('d/m/Y', strtotime($_rr['createdAt']))) : '';
                if ($_by !== '' || $_note !== '') {
                    $status .= '<div class="mt-2 p-2" style="background:#fff8e6;border:1px solid #f0d9a8;border-radius:0.4rem;font-size:0.75rem;line-height:1.5;color:#5d3f1c;max-width:320px;">'
                             . '<div class="fw-semibold mb-1" style="color:#8b5a1a;"><i class="ti tabler-user-x me-1"></i>ফেরত পাঠিয়েছেন'
                             . ($_by !== '' ? ': ' . htmlspecialchars($_by) : '')
                             . ($_title !== '' ? ' <span style="color:#8a90a6;">(' . htmlspecialchars($_title) . ')</span>' : '')
                             . ($_when !== '' ? ' <span class="text-muted">— ' . $_when . '</span>' : '')
                             . '</div>'
                             . ($_note !== '' ? '<div><i class="ti tabler-message me-1"></i><strong>কারণ:</strong> ' . nl2br(htmlspecialchars($_note)) . '</div>' : '')
                             . '</div>';
                }
            }
        }
    } else if ($row['status'] == 0) {
        $status = '<span class="status-pill status-pending"><i class="ti tabler-hourglass me-1"></i>অপেক্ষমান</span>';
    }

    // Edit-request lifecycle badges (পেন্ডিং / সংশোধিত / প্রত্যাখ্যাত / ফেরত)
    // Inline lookup (avoids cross-file helper dependency)
    $_lid = (int)$row['dataID'];
    $_q = mysqli_query($con, "SELECT dataID, status FROM leave_edit_data WHERE leaveApplicationID=$_lid ORDER BY dataID DESC");
    $_pendingShown = false; $_finalizedShown = false; $_rejectedShown = false; $_returnedShown = false;
    if ($_q) while ($_r = mysqli_fetch_assoc($_q)) {
        if ((int)$_r['status'] === 0 && !$_pendingShown) {
            $_eid = (int)$_r['dataID'];
            $status .= '<div class="mt-1"><span class="status-pill" style="background:#fff3e1;color:#8b5a1a;border:1px dashed #d4a056;"><i class="ti tabler-pencil me-1"></i>সংশোধন অপেক্ষমান</span></div>';

            // Find current signatory + chain progress
            $_tcq = mysqli_query($con, "SELECT COUNT(*) c FROM leave_edit_data_for_approval WHERE editRequestID=$_eid");
            $_tc  = ($_tcq && $_tr = mysqli_fetch_assoc($_tcq)) ? (int)$_tr['c'] : 0;

            $_csq = mysqli_query($con,
                "SELECT ldfa.serial, el.employee_name, jt.job_title_name
                 FROM leave_edit_data_for_approval ldfa
                 LEFT JOIN employee_list el ON ldfa.signatory = el.id
                 LEFT JOIN job_title jt     ON el.designation  = jt.id
                 WHERE ldfa.editRequestID = $_eid
                   AND ldfa.isApproved = 0
                   AND NOT EXISTS (
                       SELECT 1 FROM leave_edit_data_for_approval prev
                       WHERE prev.editRequestID = ldfa.editRequestID
                         AND prev.serial < ldfa.serial
                         AND prev.isApproved = 0
                   )
                 ORDER BY ldfa.serial ASC LIMIT 1");
            if ($_csq && $_cs = mysqli_fetch_assoc($_csq)) {
                $_sn = trim($_cs['employee_name']  ?? '');
                $_st = trim($_cs['job_title_name'] ?? '');
                $_sr = (int)($_cs['serial'] ?? 0);
                if ($_sn !== '') {
                    $_prog = ($_sr > 0 && $_tc > 0)
                        ? ' <span style="background:#6c5ce7;color:#fff;padding:1px 6px;border-radius:0.3rem;font-size:0.65rem;margin-left:4px;">' . banglaNumber($_sr) . '/' . banglaNumber($_tc) . '</span>'
                        : '';
                    $status .= '<div class="mt-1 small" style="font-size:0.74rem;line-height:1.3;color:#5d3f1c;">'
                            .  '<i class="ti tabler-user-check me-1" style="color:#b8651a;"></i>'
                            .  '<strong>' . htmlspecialchars($_sn) . '</strong>'
                            .  ($_st !== '' ? ' <span style="color:#8a90a6;">— ' . htmlspecialchars($_st) . '</span>' : '')
                            .  $_prog
                            .  '</div>';
                }
            }
            $_pendingShown = true;
        } elseif ((int)$_r['status'] === 1 && !$_finalizedShown) {
            $status .= '<div class="mt-1"><span class="status-pill" style="background:#e6f7ee;color:#1a7e44;"><i class="ti tabler-pencil-check me-1"></i>সংশোধিত</span></div>';
            $_finalizedShown = true;
        } elseif ((int)$_r['status'] === 2 && !$_rejectedShown) {
            $status .= '<div class="mt-1"><span class="status-pill" style="background:#fff1f0;color:#a52a2a;"><i class="ti tabler-pencil-x me-1"></i>সংশোধন প্রত্যাখ্যাত</span></div>';
            $_rejectedShown = true;
        } elseif ((int)$_r['status'] === 3 && !$_returnedShown) {
            $status .= '<div class="mt-1"><span class="status-pill" style="background:#fff8e6;color:#8b6f47;"><i class="ti tabler-corner-up-left me-1"></i>সংশোধন ফেরত</span></div>';
            $_returnedShown = true;
        }
    }

    // Actions — label the application-details link differently for
    // returned rows so the applicant knows this is the send-back copy.
    // Label the details link "ফেরতকৃত আবেদন" for any row that has ever
    // been returned (not just the ones currently in status=3), so once
    // the applicant resubmits they can still see this is the send-back
    // copy — matches the "পুনঃ যাচাইয়ের পর জমা" chip on the row.
    $_appDocLabel = $_wasReturned ? 'ফেরতকৃত আবেদন' : 'আবেদনপত্র';
    $_appDocIcon  = $_wasReturned ? 'tabler-file-alert' : 'tabler-file-text';
    $actions = '<div class="btn-group">
        <button type="button" class="btn btn-icon btn-outline-primary btn-sm rounded-circle action-btn" data-bs-toggle="dropdown" aria-expanded="false" title="কার্যাবলী">
            <i class="ti tabler-dots-vertical"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item app-doc-view" href="javascript:void(0);"
                   data-url="../../views/leave/application-details.php?menuslug=all-leave-application&leaveApplicationID=' . $row['dataID'] . '"
                   data-title="' . $_appDocLabel . '">
                <i class="ti ' . $_appDocIcon . ' me-2"></i>' . $_appDocLabel . '
            </a></li>';

    // Edit/Delete only when:
    //  - status = 0 (still pending)
    //  - no signatory has approved yet
    //  - supervisor hasn't read it yet (current behavior)
    $canEditDelete = (intval($row['status']) === 0)
                  && (intval($row['approved_signatory_count']) === 0)
                  && (intval($row['pending_supervisor_count']) === 1);
    if ($canEditDelete) {
        $actions .= '<li><a class="dropdown-item" data-turbo="false" href="../../views/leave/application-form.php?editID=' . (int)$row['dataID'] . '&menuslug=all-leave-application">
                <i class="ti tabler-edit me-2"></i>এডিট করুন
            </a></li>
            <li><a class="dropdown-item" href="javascript:void(0);" onClick="cancelApplication(' . $row['dataID'] . ', \'' . $row['dataID'] . '\')">
                <i class="ti tabler-trash me-2"></i>ডিলিট
            </a></li>';
    }

    // Returned by a signatory (status=3): applicant can re-edit and resubmit.
    if (intval($row['status']) === 3) {
        $actions .= '<li><a class="dropdown-item text-warning" data-turbo="false" href="../../views/leave/application-form.php?editID=' . (int)$row['dataID'] . '&menuslug=all-leave-application">
                <i class="ti tabler-pencil me-2"></i>সম্পাদনা ও পুনরায় জমা
            </a></li>';
    }

    if ($row['status'] == 1) {
        $actions .= '<li><a class="dropdown-item" href="../../api/reports/leave-notice.php?menuslug=all-leave-application&leaveApplicationID=' . $row['dataID'] . '" target="_blank">
                <i class="ti tabler-file-description me-2"></i>অফিস আদেশ
            </a></li>';
    }

    if ($hasJoining) {
        $actions .= '<li><a class="dropdown-item" target="_blank" href="../../views/leave/documents/' . joining_letter_file($row['joiningType']) . '?menuslug=all-leave-application&leaveApplicationID=' . $row['dataID'] . '">
                <i class="ti tabler-file-check me-2"></i>যোগদান পত্র
            </a></li>';

        if ($row['lja_status'] == 1) {
            $actions .= '<li><a class="dropdown-item" href="../../views/leave/documents/corrected-office-notice.php?menuslug=all-leave-application&leaveApplicationID=' . $row['dataID'] . '" target="_blank">
                    <i class="ti tabler-file-invoice me-2"></i>সংশোধিত অফিস আদেশ
                </a></li>';
        }
    }

    $actions .= '</ul></div>';

    $data[] = array(
        'serial'          => '<span class="serial-num">' . $serial++ . '</span>',
        'employee_info'   => $employee_info,
        'requested_leave' => $requested_leave,
        'approved_leave'  => $approved_leave,
        'proposed_leave'  => $proposed_leave ?: '<span class="text-muted small">—</span>',
        'spent_leave'     => $spent_leave,
        'joining_type'    => $joining_type,
        'corrected_leave' => $corrected_leave,
        'status'          => $status,
        'actions'         => $actions,
    );
}
mysqli_stmt_close($dataStmt);

echo json_encode(array(
    "draw"            => $draw,
    "recordsTotal"    => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data"            => $data,
));
