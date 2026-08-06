<?php
/**
 * DataTables server-side endpoint: applications the current user has declined
 * (the "না মঞ্জুর করুন" flow).
 *
 * Scoped on leave_applications.declinedBy rather than
 * leave_data_for_approval.isApproved = 2: declining marks EVERY still-pending
 * chain row as 2, so that flag cannot tell who actually made the decision.
 * declinedBy is written with the decider's employee id by the decline branch
 * of api/leave/approve-application.php.
 *
 * Like the পুনঃ যাচাই tab this is a permanent history — nothing is filtered
 * out once declined.
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

$baseFrom = "
FROM leave_applications la
INNER JOIN employee_list el ON la.applicantID     = el.id
LEFT  JOIN job_title    jt  ON el.designation     = jt.id
LEFT  JOIN sections     s   ON el.section_id      = s.id
LEFT  JOIN organization o   ON el.organization_id = o.id
LEFT  JOIN leave_types  lt  ON la.leaveType       = lt.leaveID
WHERE la.status = 2 AND la.declinedBy = ?";

$countStmt = mysqli_prepare($con, "SELECT COUNT(*) c $baseFrom");
mysqli_stmt_bind_param($countStmt, 'i', $currentEmpID);
mysqli_stmt_execute($countStmt);
$totalRecords = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'] ?? 0);
mysqli_stmt_close($countStmt);

$dataSql = "
SELECT
    la.dataID              AS applicationID,
    la.application_no      AS applicationNo,
    la.submitDate          AS submitDate,
    la.dateFrom            AS dateFrom,
    la.dateTo              AS dateTo,
    la.cancellationReasion AS reason,
    la.cancellationDate    AS declinedAt,
    el.employee_name       AS employee_name,
    el.employee_id         AS employee_code,
    el.photo               AS employee_photo,
    jt.job_title_name      AS job_title_name,
    s.section_name         AS section_name,
    o.organization_name    AS organization_name,
    lt.leaveTitle          AS leaveTitle
$baseFrom
ORDER BY la.cancellationDate DESC, la.dataID DESC
LIMIT $start, $length";

$dataStmt = mysqli_prepare($con, $dataSql);
mysqli_stmt_bind_param($dataStmt, 'i', $currentEmpID);
mysqli_stmt_execute($dataStmt);
$dataRes = mysqli_stmt_get_result($dataStmt);

$data   = [];
$serial = $start + 1;

while ($r = mysqli_fetch_assoc($dataRes)) {
    $appNo = !empty($r['applicationNo'])
        ? $r['applicationNo']
        : ('BITAC/' . date('Y', strtotime($r['submitDate'] ?? 'now')) . '/' . (int)$r['applicationID']);

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
                   . '<div class="emp-meta">'
                   . '<div class="appno-chip"><i class="ti tabler-hash"></i> ' . htmlspecialchars($appNo) . '</div>'
                   . '<div class="emp-name">' . htmlspecialchars($empName)
                   . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                   . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                   . '</div></div>';

    // Section + center chips
    $secCenter = '';
    if ($empSec !== '') {
        $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($empSec) . '</span>';
    }
    if ($empOrg !== '') {
        if ($secCenter) $secCenter .= '<br>';
        $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($empOrg) . '</span>';
    }

    // Requested leave — multi-segment aware, same convention as the other tabs
    $days = dateDiffInDays($r['dateFrom'], $r['dateTo']) + 1;
    $aid  = (int)$r['applicationID'];
    $segs = [];
    $segRes = mysqli_query($con, "SELECT s.days, lt.leaveTitle
                                   FROM leave_application_segments s
                                   LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
                                   WHERE s.applicationID = $aid
                                     AND (s.kind = 'proposed' OR s.kind IS NULL)
                                   ORDER BY s.serial ASC, s.dataID ASC");
    if ($segRes) while ($sr = mysqli_fetch_assoc($segRes)) $segs[] = $sr;

    $requestedHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>'
                   . banglaNumber(date('d/m/Y', strtotime($r['dateFrom'])))
                   . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>'
                   . banglaNumber(date('d/m/Y', strtotime($r['dateTo']))) . '</span></div>';
    if (count($segs) > 1) {
        $segTotal = array_sum(array_column($segs, 'days'));
        $segParts = [];
        foreach ($segs as $sg) {
            $segParts[] = '<span class="seg-pill">' . banglaNumber((int)$sg['days']) . ' দিন '
                        . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা') . '</span>';
        }
        $requestedHtml .= '<div class="leave-meta"><span class="days-pill">মোট ' . banglaNumber($segTotal) . ' দিন</span></div>'
                        . '<div class="seg-list">' . implode(' ', $segParts) . '</div>';
    } else {
        $requestedHtml .= '<div class="leave-meta"><span class="days-pill">' . banglaNumber($days) . ' দিন</span>'
                        . ' <span class="leave-type-chip">' . htmlspecialchars($r['leaveTitle'] ?? '') . '</span></div>';
    }

    // When it was declined
    $whenHtml = !empty($r['declinedAt'])
        ? '<span class="days-pill" style="background:#fdecec;color:#a52a2a;border:1px solid #f5c5c1;"><i class="ti tabler-calendar-x me-1"></i>'
          . banglaNumber(date('d/m/Y', strtotime($r['declinedAt']))) . '</span>'
        : '<span class="text-muted small">—</span>';

    // Reason
    $reason = trim($r['reason'] ?? '');
    $reasonCell = $reason !== ''
        ? '<div style="max-width:280px;font-size:0.82rem;line-height:1.45;color:#4a4d63;white-space:normal;">' . nl2br(htmlspecialchars($reason)) . '</div>'
        : '<span class="text-muted small">—</span>';

    // Actions — open the application in the same-page preview modal
    $actionCell = '<a href="javascript:void(0);" data-url="../../views/leave/application-details.php?menuslug=supervised-nd-approved-application-by-user&leaveApplicationID='
                . $aid . '" data-title="আবেদনপত্র" class="action-icon icon-view app-doc-view" title="আবেদনপত্র">'
                . '<i class="ti tabler-file-text"></i></a>';

    $data[] = [
        'serial'         => '<span class="serial-num">' . $serial++ . '</span>',
        'applicant_cell' => $applicantCell,
        'section_center' => $secCenter,
        'requested'      => $requestedHtml,
        'declined_at'    => $whenHtml,
        'reason'         => $reasonCell,
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
