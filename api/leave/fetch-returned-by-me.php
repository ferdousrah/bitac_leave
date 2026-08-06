<?php
/**
 * DataTables server-side endpoint: applications the current user has returned
 * (via the "ফেরত পাঠান" flow). Sourced from `leave_return_history`.
 *
 * Purpose: tracking view — a supervisor/signatory can see everything they
 * sent back for re-verification, along with the current status of each
 * application (still pending re-submission, resubmitted, approved etc.).
 */

session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

header('Content-Type: application/json');

function dateDiffInDays($d1, $d2) {
    return abs(round((strtotime($d2) - strtotime($d1)) / 86400));
}

if (empty($_SESSION['username'])) {
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}

// Resolve current employee id
$uStmt = mysqli_prepare($con, "SELECT employee_id FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
mysqli_stmt_close($uStmt);
$currentEmpID = (int)($uRow['employee_id'] ?? 0);

if ($currentEmpID === 0) {
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}

$draw   = isset($_POST['draw'])   ? intval($_POST['draw'])   : 1;
$start  = isset($_POST['start'])  ? max(0, intval($_POST['start']))  : 0;
$length = isset($_POST['length']) ? max(1, intval($_POST['length'])) : 10;

// Base FROM/WHERE — leave_return_history joined with the application it belongs
// to. This tab is a permanent HISTORY of everything the current user has sent
// back: entries are never filtered out when the applicant resubmits or the
// application finishes its chain. The বর্তমান অবস্থা column already reports
// where each one ended up.
$baseFrom = "
FROM leave_return_history lrh
INNER JOIN leave_applications la ON lrh.leaveApplicationID = la.dataID
INNER JOIN employee_list el      ON la.applicantID          = el.id
LEFT  JOIN job_title       jt    ON el.designation          = jt.id
LEFT  JOIN sections        s     ON el.section_id           = s.id
LEFT  JOIN organization    o     ON el.organization_id      = o.id
LEFT  JOIN leave_types     lt    ON la.leaveType            = lt.leaveID
WHERE lrh.returnedBy = ?";

// Count
$countStmt = mysqli_prepare($con, "SELECT COUNT(*) c $baseFrom");
mysqli_stmt_bind_param($countStmt, 'i', $currentEmpID);
mysqli_stmt_execute($countStmt);
$totalRecords = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'] ?? 0);
mysqli_stmt_close($countStmt);

$dataSql = "
SELECT
    lrh.dataID           AS returnID,
    lrh.leaveApplicationID AS applicationID,
    lrh.returnType       AS returnType,
    lrh.returnedToName   AS returnedToName,
    lrh.note             AS returnNote,
    lrh.createdAt        AS returnedAt,
    la.status            AS appStatus,
    la.dateFrom          AS dateFrom,
    la.dateTo            AS dateTo,
    el.employee_name     AS employee_name,
    el.employee_id       AS employee_code,
    el.photo             AS employee_photo,
    jt.job_title_name    AS job_title_name,
    s.section_name       AS section_name,
    o.organization_name  AS organization_name,
    lt.leaveTitle        AS leaveTitle
$baseFrom
ORDER BY lrh.createdAt DESC, lrh.dataID DESC
LIMIT $start, $length";

$dataStmt = mysqli_prepare($con, $dataSql);
mysqli_stmt_bind_param($dataStmt, 'i', $currentEmpID);
mysqli_stmt_execute($dataStmt);
$dataRes = mysqli_stmt_get_result($dataStmt);

$data   = [];
$serial = $start + 1;

// Human-friendly labels for the returnType enum
$typeLabels = [
    'to_applicant'          => 'আবেদনকারী',
    'to_previous_signatory' => 'পূর্ববর্তী সিদ্ধান্তকারী',
    'to_admin'              => 'প্রশাসনিক কর্মকর্তা',
];

while ($r = mysqli_fetch_assoc($dataRes)) {
    // Applicant cell (avatar + name + designation + section/center)
    $empName  = trim($r['employee_name']  ?? '');
    $empJob   = trim($r['job_title_name'] ?? '');
    $empSec   = trim($r['section_name']   ?? '');
    $empOrg   = trim($r['organization_name'] ?? '');
    $empPhoto = trim($r['employee_photo'] ?? '');
    $empCode  = trim($r['employee_code']  ?? '');

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
                   . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName)
                   . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                   . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                   . '</div></div>';

    // Section/center chips
    $secCenter = '';
    if ($empSec !== '') {
        $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($empSec) . '</span>';
    }
    if ($empOrg !== '') {
        if ($secCenter) $secCenter .= '<br>';
        $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($empOrg) . '</span>';
    }

    // Requested leave cell — multi-segment aware (same convention as the
    // signatory queues; a returned application often IS a multi-segment
    // request, so the tracker view needs to reflect the split too).
    $days = dateDiffInDays($r['dateFrom'], $r['dateTo']) + 1;
    $__aid = (int)$r['applicationID'];
    $__reqSegs = [];
    $__segRes = mysqli_query($con, "SELECT s.days, lt.leaveTitle
                                     FROM leave_application_segments s
                                     LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
                                     WHERE s.applicationID = $__aid
                                       AND (s.kind = 'proposed' OR s.kind IS NULL)
                                     ORDER BY s.serial ASC, s.dataID ASC");
    if ($__segRes) while ($__sr = mysqli_fetch_assoc($__segRes)) $__reqSegs[] = $__sr;

    $requestedHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>'
                   . banglaNumber(date('d/m/Y', strtotime($r['dateFrom'])))
                   . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>'
                   . banglaNumber(date('d/m/Y', strtotime($r['dateTo']))) . '</span></div>';
    if (count($__reqSegs) > 1) {
        $__reqTotal = array_sum(array_column($__reqSegs, 'days'));
        $__parts = [];
        foreach ($__reqSegs as $__sg) {
            $__parts[] = '<span class="seg-pill">' . banglaNumber((int)$__sg['days']) . ' দিন '
                       . htmlspecialchars($__sg['leaveTitle'] ?? 'অজানা') . '</span>';
        }
        $requestedHtml .= '<div class="leave-meta"><span class="days-pill">মোট ' . banglaNumber($__reqTotal) . ' দিন</span></div>'
                        . '<div class="seg-list">' . implode(' ', $__parts) . '</div>';
    } else {
        $requestedHtml .= '<div class="leave-meta"><span class="days-pill">' . banglaNumber($days) . ' দিন</span>'
                        . ' <span class="leave-type-chip">' . htmlspecialchars($r['leaveTitle'] ?? '') . '</span></div>';
    }

    // Return details cell
    $returnedTo = $typeLabels[$r['returnType']] ?? ($r['returnType'] ?? '');
    $returnedToName = trim($r['returnedToName'] ?? '');
    $returnedAt = $r['returnedAt'] ? banglaNumber(date('d/m/Y g:i A', strtotime($r['returnedAt']))) : '—';
    $returnCell = '<div style="line-height:1.4;"><i class="ti tabler-corner-up-left me-1" style="color:#b8651a;"></i><strong>'
                . htmlspecialchars($returnedTo) . '</strong>'
                . ($returnedToName !== '' && $returnedToName !== $returnedTo ? ' <span class="text-muted small">(' . htmlspecialchars($returnedToName) . ')</span>' : '')
                . '</div>'
                . '<div class="text-muted small mt-1"><i class="ti tabler-clock me-1"></i>' . $returnedAt . '</div>';

    // Reason cell
    $noteHtml = trim($r['returnNote'] ?? '');
    $noteCell = $noteHtml !== ''
        ? '<div style="max-width:280px;font-size:0.82rem;line-height:1.45;color:#4a4d63;white-space:normal;">' . nl2br(htmlspecialchars($noteHtml)) . '</div>'
        : '<span class="text-muted small">—</span>';

    // Current status cell (based on la.status)
    $status = (int)$r['appStatus'];
    switch ($status) {
        case 0:
            $statusCell = '<span class="status-pill status-pending"><i class="ti tabler-hourglass me-1"></i>প্রক্রিয়াধীন</span>';
            break;
        case 1:
            $statusCell = '<span class="status-pill status-approved"><i class="ti tabler-check me-1"></i>অনুমোদিত</span>';
            break;
        case 2:
            $statusCell = '<span class="status-pill status-rejected"><i class="ti tabler-x me-1"></i>অনুমোদিত হয়নি</span>';
            break;
        case 3:
            $statusCell = '<span class="status-pill" style="background:#fff3e1;color:#b8651a;"><i class="ti tabler-corner-up-left me-1"></i>পুনঃ যাচাই অপেক্ষমান</span>';
            break;
        default:
            $statusCell = '<span class="status-pill status-pending">—</span>';
    }

    // Actions cell — opens the application in the same-page preview modal
    $actionCell = '<a href="javascript:void(0);" data-url="application-details.php?menuslug=leave-approval&leaveApplicationID='
                . (int)$r['applicationID'] . '" class="action-icon icon-view app-doc-view" title="বিস্তারিত দেখুন">'
                . '<i class="ti tabler-eye"></i></a>';

    $data[] = [
        'serial'         => '<span class="serial-num">' . $serial++ . '</span>',
        'applicant_cell' => $applicantCell,
        'section_center' => $secCenter,
        'requested'      => $requestedHtml,
        'returned_to'    => $returnCell,
        'note'           => $noteCell,
        'status'         => $statusCell,
        'action'         => $actionCell,
    ];
}
mysqli_stmt_close($dataStmt);

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $totalRecords,
    'data'            => $data,
]);
