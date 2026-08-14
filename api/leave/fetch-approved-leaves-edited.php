<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../library/number_converter.php');
require_once(__DIR__ . '/../../includes/joining-effective-leave.php');

// Disable error display for clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

function dateDiffInDays($date1, $date2) {
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

// Resolve organization_id for the current user
if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = intval($_SESSION['centerAdminOrgID']);
} else {
    $empID = intval($_SESSION['employeeID'] ?? 0);
    $stmt_org = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $stmt_org->bind_param("i", $empID);
    $stmt_org->execute();
    $orgRow = $stmt_org->get_result()->fetch_assoc();
    $stmt_org->close();
    $orgID = intval($orgRow['organization_id'] ?? 0);
}

// Filter params
$sectionFilter   = (int)($_POST['sectionFilter']   ?? 0);
$employeeFilter  = (int)($_POST['employeeFilter']  ?? 0);
$joiningTypeFilter = (int)($_POST['joiningTypeFilter'] ?? 0);

$filterClause = '';
if ($sectionFilter   > 0) $filterClause .= " AND el.section_id = $sectionFilter";
if ($employeeFilter  > 0) $filterClause .= " AND el.id = $employeeFilter";
if ($joiningTypeFilter > 0) $filterClause .= " AND lja.joiningType = $joiningTypeFilter";

// Get request parameters
$limit = isset($_POST['length']) ? (int) $_POST['length'] : 10;
$start = isset($_POST['start']) ? (int) $_POST['start'] : 0;
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : '';

// Base query - Edited leaves (isSentbyAdmin = 1)
$baseQuery = "FROM leave_joining_data_for_approval lj
    LEFT JOIN leave_joining_application lja ON lj.leaveApplicationID = lja.leaveApplicationID
    LEFT JOIN leave_applications la ON lj.leaveApplicationID = la.dataID
    LEFT JOIN employee_list el ON la.applicantID = el.id
    LEFT JOIN job_title jt ON el.designation = jt.id
    LEFT JOIN sections s ON el.section_id = s.id
    LEFT JOIN organization o ON el.organization_id = o.id
    WHERE lj.isSupervisor = 1 AND lj.isApproved = 1
    AND lj.isSentbyAdmin = 1
    AND lja.joiningType != 1
    AND la.organization_id = $orgID
    $filterClause";

// Search filter
$searchQuery = "";
if ($search) {
    $searchQuery = " AND (el.employee_name LIKE '%$search%' OR jt.job_title_name LIKE '%$search%' OR s.section_name LIKE '%$search%')";
}

// Count total records
$totalRecordsQuery = "SELECT COUNT(*) as total $baseQuery $searchQuery";
$totalRecordsResult = mysqli_query($con, $totalRecordsQuery);
$totalRecords = mysqli_fetch_assoc($totalRecordsResult)['total'] ?? 0;

// Fetch data
$dataQuery = "SELECT lj.*, lja.*, la.*, lja.status AS joining_status, lja.approvedLeaveType AS joining_ext_leave_type, el.employee_name, el.employee_id AS emp_code, el.photo, el.designation, el.section_id, el.organization_id,
    jt.job_title_name, s.section_name, o.organization_name
    $baseQuery $searchQuery
    ORDER BY lj.dataID DESC
    LIMIT $start, $limit";
$dataResult = mysqli_query($con, $dataQuery);

$data = array();
$sl = $start + 1;

while ($empRow = mysqli_fetch_array($dataResult)) {

    if ($empRow['joiningType'] == 1 || $empRow['joiningType'] == 2 || $empRow['joiningType'] == 3) {

        // প্রাথমিক অনুমোদিত ছুটি
        $adateF = date_create($empRow['primaryLeaveDateFrom']);
        $adateT = date_create($empRow['primaryLeaveDateTo']);
        $adateDiff = dateDiffInDays($empRow['primaryLeaveDateFrom'], $empRow['primaryLeaveDateTo']) + 1;

        // ভোগকৃত ছুটি — project the approved segments through the joining rules
        // rather than spanning approvedDateFrom → requestedJoiningDate, which
        // counts the gaps between segments as leave.
        $__aid = intval($empRow['leaveApplicationID']);
        $__segs = [];
        // Frozen snapshot taken at final approval. Applications approved before
        // it existed have none, so fall back to the live proposed rows.
        $__apprSegs = [];
        $__apprRes = mysqli_query($con, "SELECT s.dateFrom, s.dateTo, s.days, s.serial, lt.leaveTitle
                                          FROM leave_application_segments s
                                          LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
                                          WHERE s.applicationID = $__aid AND s.kind = 'approved'
                                          ORDER BY s.serial ASC, s.dataID ASC");
        if ($__apprRes) while ($__ar = mysqli_fetch_assoc($__apprRes)) $__apprSegs[] = $__ar;

        $__segRes = mysqli_query($con, "SELECT s.dateFrom, s.dateTo, s.days, s.serial, lt.leaveTitle
                                         FROM leave_application_segments s
                                         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
                                         WHERE s.applicationID = $__aid
                                           AND (s.kind = 'proposed' OR s.kind IS NULL)
                                         ORDER BY s.serial ASC, s.dataID ASC");
        if ($__segRes) while ($__sr = mysqli_fetch_assoc($__segRes)) $__segs[] = $__sr;

        $__spentSegs = joining_effective_segments($__segs, $empRow['joiningType'], $empRow['requestedJoiningDate'], [
            'extensionSegmentsJson' => $empRow['extensionSegmentsJson'] ?? null,
            'approvedDateTo'        => $empRow['approvedDateTo'] ?? '',
            'extLeaveType'          => $empRow['joining_ext_leave_type'] ?? 0,
            'leaveTitles'           => joining_leave_titles($con),
        ]);
        $__spentSpan = joining_segments_span($__spentSegs);

        if ($__spentSpan['days'] > 0) {
            $leaveSpentDateFrom = date_create($__spentSpan['from']);
            $leaveSpentDateTo   = date_create($__spentSpan['to']);
            $leaveSpent         = $__spentSpan['days'];
        } else {
            // Legacy rows with no segments at all
            $leaveSpentDateFrom = date_create($empRow['approvedDateFrom']);
            $leaveSpentDateTo   = date_create($empRow['requestedJoiningDate']);
            $leaveSpent         = dateDiffInDays($empRow['approvedDateFrom'], $empRow['requestedJoiningDate']) + 1;
        }

        // সংশোধিত ছুটি
        $correctedLeaveHtml = '';
        if (!empty($empRow['approvedDate'])) {
            $correctionJoiningDate = date_create($empRow['approvedDateTo']);
            $correctedLeaveSpent = dateDiffInDays($empRow['approvedDateFrom'], $empRow['approvedDateTo']) + 1;

            $correctedLeaveTypeText = joining_leave_titles($con)[(int)$empRow['approvedLeaveType']] ?? '';

            $correctedLeaveHtml = banglaNumber(date_format($leaveSpentDateFrom, "d/m/Y")) . ' হইতে ' . banglaNumber(date_format($correctionJoiningDate, "d/m/Y")) . ', ' . banglaNumber($correctedLeaveSpent) . ' দিন ' . htmlspecialchars($correctedLeaveTypeText);
        }

        // Leave type name straight from leave_types. The hand-written list this
        // replaces only knew ids 1-5, while real ids run to 8, 18, 19, 21, 22 —
        // so anything outside that range rendered with no type at all.
        $__ltMap = joining_leave_titles($con);
        $leaveTypeText = $__ltMap[(int)$empRow['primaryApprovedLeaveType']] ?? '';

        // Application type
        $applicationType = '';
        if ($empRow['joiningType'] == 1) {
            $applicationType = "সঠিক সময়ে যোগদান";
        } else if ($empRow['joiningType'] == 2) {
            $applicationType = "অগ্রিম যোগদান";
        } else if ($empRow['joiningType'] == 3) {
            $applicationType = "বর্ধিত ছুটির আবেদন";
        }

        // Status pill
        // lja and la both carry a `status`, and la.* wins the wildcard collision —
        // which reported the parent leave's status instead of the joining's, so a
        // joining still awaiting its chain read as অনুমোদিত. Use the alias.
        $statusBadge = '';
        if ($empRow['joining_status'] == 1) {
            $statusBadge = '<span class="status-pill status-approved"><i class="ti tabler-check me-1"></i>অনুমোদিত</span>';
        } else if ($empRow['joining_status'] == 2) {
            $statusBadge = '<span class="status-pill status-rejected"><i class="ti tabler-x me-1"></i>অনুমোদিত হয়নি</span>';
        } else if ($empRow['joining_status'] == 3) {
            $statusBadge = '<span class="status-pill status-returned"><i class="ti tabler-arrow-back-up me-1"></i>পুনঃ যাচাই</span>';
        } else if ($empRow['joining_status'] == 0) {
            $statusBadge = '<span class="status-pill status-pending"><i class="ti tabler-hourglass me-1"></i>অপেক্ষমান</span>';
        }

        // Action buttons - determine joining letter link
        $joiningLetter = '';
        if ($empRow['joiningType'] == 1) {
            $joiningLetter = '../../views/leave/documents/joining-details.php';
        } else if ($empRow['joiningType'] == 2) {
            $joiningLetter = '../../views/leave/documents/joining-details-typetwo.php';
        } else if ($empRow['joiningType'] == 3) {
            $joiningLetter = '../../views/leave/documents/joining-details-typethree.php';
        }

        $actionHtml = '<div class="action-group">
            <a class="action-icon icon-view" target="_blank" href="../../views/leave/documents/office-notice.php?menuslug=leave-joining-approval&leaveApplicationID=' . intval($empRow['leaveApplicationID']) . '" data-bs-toggle="tooltip" title="ছুটির অফিস আদেশ">
                <i class="ti tabler-file-description"></i>
            </a>';

        if ($joiningLetter != '') {
            $actionHtml .= '<a class="action-icon icon-attach" target="_blank" href="' . $joiningLetter . '?menuslug=leave-joining-approval&leaveApplicationID=' . intval($empRow['leaveApplicationID']) . '" data-bs-toggle="tooltip" title="যোগদান পত্র">
                <i class="ti tabler-file-check"></i>
            </a>';
        }

        if ($empRow['isSentbyAdmin'] == 1) {
            $actionHtml .= '<a class="btn btn-sm btn-warning" href="../../views/leave/documents/approval-note.php?menuslug=manage-approved-leaves&leaveApplicationID=' . intval($empRow['leaveApplicationID']) . '&isApproved=1" target="_blank">
                <i class="ti tabler-edit me-1"></i>সংশোধিত নোট সম্পাদনা
            </a>';

            if ($empRow['joining_status'] == 1) {
                $actionHtml .= '<a class="btn btn-sm btn-success" href="../../views/leave/documents/corrected-office-notice.php?menuslug=manage-approved-leaves&leaveApplicationID=' . intval($empRow['leaveApplicationID']) . '" target="_blank">
                    <i class="ti tabler-file-check me-1"></i>সংশোধিত অফিস আদেশ
                </a>';
            }
        }

        $actionHtml .= '</div>';

        // Avatar-based applicant cell
        $empName_  = trim($empRow['employee_name'] ?? '');
        $empJob_   = trim($empRow['job_title_name'] ?? '');
        $empPhoto_ = trim($empRow['photo'] ?? '');
        $empCode_  = trim($empRow['emp_code'] ?? '');
        $initials_ = mb_substr($empName_, 0, 1, 'UTF-8');
        $parts_ = preg_split('/\s+/u', $empName_);
        if (count($parts_) > 1) {
            $initials_ = mb_substr($parts_[0], 0, 1, 'UTF-8') . mb_substr(end($parts_), 0, 1, 'UTF-8');
        }
        if (!empty($empPhoto_)) {
            $photoUrl_ = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto_);
            $avatarHtml_ = '<div class="emp-avatar"><img src="' . $photoUrl_ . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials_) . '</span></div>';
        } else {
            $avatarHtml_ = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials_) . '</span></div>';
        }
        $applicantCell = '<div class="emp-cell">' . $avatarHtml_
                       . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName_) . ($empCode_ ? ' <span class="emp-sub-light">(' . banglaNumber($empCode_) . ')</span>' : '') . '</div>'
                       . ($empJob_ ? '<div class="emp-sub">' . htmlspecialchars($empJob_) . '</div>' : '')
                       . '</div></div>';

        // Section + center chips
        $secCenter = '';
        if (!empty($empRow['section_name'])) {
            $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($empRow['section_name']) . '</span>';
        }
        if (!empty($empRow['organization_name'])) {
            if ($secCenter) $secCenter .= '<br>';
            $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($empRow['organization_name']) . '</span>';
        }

        // Joining type chip
        $jtClass = $empRow['joiningType'] == 1 ? 'jt-ontime' : ($empRow['joiningType'] == 2 ? 'jt-early' : 'jt-extend');
        $jtIcon  = $empRow['joiningType'] == 1 ? 'tabler-clock' : ($empRow['joiningType'] == 2 ? 'tabler-calendar-minus' : 'tabler-calendar-plus');
        $applicationTypeHtml = '<span class="jt-chip ' . $jtClass . '"><i class="ti ' . $jtIcon . ' me-1"></i>' . htmlspecialchars($applicationType) . '</span>';

        // Multi-segment breakdown for the primary (approved) leave — same
        // seg-list convention as the other approval queues.
        // Primary leave (date + days + type chip)
        // Draw a segment list the same way for both the frozen approval and the
        // desks' current proposal, so the two columns are directly comparable.
        $__segList = function (array $segs) {
            $total  = array_sum(array_column($segs, 'days'));
            $gapped = !joining_segments_contiguous($segs);
            $parts  = [];
            foreach ($segs as $sg) {
                $parts[] = '<span class="seg-pill">'
                         . ($gapped ? joining_segment_dates($sg) . ' · ' : '')
                         . banglaNumber((int)$sg['days']) . ' দিন '
                         . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা') . '</span>';
            }
            return ['total' => $total, 'html' => '<div class="seg-list">' . implode(' ', $parts) . '</div>'];
        };

        // প্রাথমিক অনুমোদিত reads the frozen snapshot; the proposed rows keep
        // changing as desks edit, and following those would erase the record of
        // what was actually granted.
        // Always the frozen approval — the snapshot when we have one, otherwise
        // the primary* columns. Falling back to the live proposed rows (as this
        // did for multi-segment leaves) made the column mean different things on
        // different rows, and quietly follow the desks' edits.
        $__primaryDays = (int)($empRow['primaryApprovedLeaveDays'] ?: $adateDiff);
        $primaryHtml = '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date_format($adateF, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($adateT, "d/m/Y")) . '</span></div>';
        if (count($__apprSegs) > 1) {
            $__pl = $__segList($__apprSegs);
            $__primaryDays = $__pl['total'];
            $primaryHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">মোট ' . banglaNumber($__primaryDays) . ' দিন</span></div>'
                          . $__pl['html'];
        } else {
            $__primaryTitleOnly = $__apprSegs ? ($__apprSegs[0]['leaveTitle'] ?? $leaveTypeText) : $leaveTypeText;
            if ($__apprSegs) $__primaryDays = (int)$__apprSegs[0]['days'];
            $primaryHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber($__primaryDays) . ' দিন</span>'
                          . ($__primaryTitleOnly ? ' <span class="leave-type-chip">' . htmlspecialchars($__primaryTitleOnly) . '</span>' : '')
                          . '</div>';
        }

        // ভোগকৃত is the same measure as সংশোধিত — a projection while the joining
        // is still moving, the final figure once it is approved. One column with
        // a state chip says that; two columns of the same number did not.
        $__isFinal   = ((int)($empRow['joining_status'] ?? 0) === 1);
        $__stateChip = $__isFinal
            ? '<span class="badge bg-label-success"><i class="ti tabler-check me-1"></i>চূড়ান্ত</span>'
            : '<span class="badge bg-label-warning"><i class="ti tabler-hourglass me-1"></i>অনুমোদনের অপেক্ষায়</span>';

        // Say what actually differs from the approval instead of making the reader
        // compare three columns of numbers.
        $__notes = [];
        if ($leaveSpent !== $__primaryDays) {
            $__delta = $leaveSpent - $__primaryDays;
            $__notes[] = ($__delta > 0)
                ? banglaNumber(abs($__delta)) . ' দিন বেশি — বর্ধিত অংশ যুক্ত'
                : banglaNumber(abs($__delta)) . ' দিন কম — আগেই যোগদান';
        }
        $__primaryTitles = $__apprSegs
            ? array_unique(array_filter(array_column($__apprSegs, 'leaveTitle')))
            : array_filter([$__ltMap[(int)$empRow['primaryApprovedLeaveType']] ?? '']);
        $__spentTitles = array_unique(array_filter(array_column($__spentSegs, 'leaveTitle')));
        $__newTitles   = array_diff($__spentTitles, $__primaryTitles);
        if ($__primaryTitles && $__newTitles) {
            $__notes[] = 'ছুটির ধরন পরিবর্তিত: ' . htmlspecialchars(implode(', ', $__primaryTitles))
                       . ' → ' . htmlspecialchars(implode(', ', $__spentTitles));
        }

        // Spent leave
        $spentHtml = '<div class="date-range"><i class="ti tabler-clock-check"></i><span>' . banglaNumber(date_format($leaveSpentDateFrom, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($leaveSpentDateTo, "d/m/Y")) . '</span></div>'
                   . '<div class="leave-meta"><span class="days-pill days-pill-info">'
                   . (count($__spentSegs) > 1 ? 'মোট ' : '') . banglaNumber($leaveSpent) . ' দিন</span>'
                   . (count($__spentSegs) > 1 || !$leaveTypeText ? '' : ' <span class="leave-type-chip">' . htmlspecialchars($leaveTypeText) . '</span>')
                   . '</div>';
        if (count($__spentSegs) > 1) {
            $__spentGapped = !joining_segments_contiguous($__spentSegs);
            $__spentParts = [];
            foreach ($__spentSegs as $__sg) {
                $__spentParts[] = '<span class="seg-pill">'
                                . ($__spentGapped ? joining_segment_dates($__sg) . ' · ' : '')
                                . banglaNumber((int)$__sg['days']) . ' দিন '
                                . htmlspecialchars($__sg['leaveTitle'] ?? 'অজানা') . '</span>';
            }
            $spentHtml .= '<div class="seg-list">' . implode(' ', $__spentParts) . '</div>';
        }
        $spentHtml .= '<div class="mt-1">' . $__stateChip . '</div>';
        foreach ($__notes as $__n) {
            $spentHtml .= '<div class="small text-muted" style="font-size:0.74rem;line-height:1.35;">'
                        . '<i class="ti tabler-arrow-narrow-right me-1"></i>' . $__n . '</div>';
        }

        // Corrected leave (already built in $correctedLeaveHtml — convert plain text to date-range pill if present)
        if (!empty($correctedLeaveHtml) && !empty($empRow['approvedDate'])) {
            $correctedHtml = '<div class="date-range"><i class="ti tabler-edit"></i><span>' . banglaNumber(date_format($leaveSpentDateFrom, "d/m/Y")) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date_format($correctionJoiningDate, "d/m/Y")) . '</span></div>'
                          . '<div class="leave-meta"><span class="days-pill days-pill-warning">' . banglaNumber($correctedLeaveSpent) . ' দিন</span>'
                          . ($correctedLeaveTypeText ? ' <span class="leave-type-chip">' . htmlspecialchars($correctedLeaveTypeText) . '</span>' : '')
                          . '</div>';
        } else {
            $correctedHtml = '<span class="text-muted small">—</span>';
        }

        $data[] = array(
            'serial' => '<span class="serial-num">' . $sl++ . '</span>',
            'applicant_name' => $applicantCell,
            'section' => $secCenter,
            'application_type' => $applicationTypeHtml,
            'primary_approved_leave' => $primaryHtml,
            'leave_spent' => $spentHtml,
            'corrected_leave' => $correctedHtml,
            'status' => $statusBadge,
            'action' => $actionHtml
        );
    }
}

// Response
$response = array(
    "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data" => $data
);

echo json_encode($response);
