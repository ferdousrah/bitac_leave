<?php
/**
 * DataTables server-side endpoint: applications returned to the admin desk.
 *
 * A center admin can trace every application that a signatory in their
 * chain sent back to the প্রশাসনিক desk via "ফেরত পাঠান" — this powers
 * the "পুনঃ যাচাই" tab on views/leave/allowed-applications.php.
 *
 * Scope: leave_return_history rows with returnType = 'to_admin' where
 * the applicant belongs to the admin's own organization.
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

// Resolve admin's org — mirror the same logic allowed-applications.php uses
if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = (int)$_SESSION['centerAdminOrgID'];
} else {
    $empID = (int)($_SESSION['employeeID'] ?? 0);
    if ($empID > 0) {
        $r = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = $empID LIMIT 1");
        $orgID = (int)(mysqli_fetch_assoc($r)['organization_id'] ?? 0);
    } else {
        $orgID = 0;
    }
}

// Guard against a missing lazily-created table
$_rrChk = mysqli_query($con, "SHOW TABLES LIKE 'leave_return_history'");
if (!$_rrChk || mysqli_num_rows($_rrChk) === 0 || $orgID === 0) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}

$draw   = isset($_POST['draw'])   ? intval($_POST['draw'])   : 1;
$start  = isset($_POST['start'])  ? max(0, intval($_POST['start']))  : 0;
$length = isset($_POST['length']) ? max(1, intval($_POST['length'])) : 10;

// Exclude fully-approved applications — once the admin has re-forwarded
// after a পুনঃ যাচাই and the chain has finally approved, the tracking
// row moves out of this "actionable" tab and shows up in the অনুমোদিত
// tab instead. Keeps this tab focused on things the admin still needs
// to look at.
$baseFrom = "
FROM leave_return_history lrh
INNER JOIN leave_applications la ON lrh.leaveApplicationID = la.dataID
INNER JOIN employee_list el      ON la.applicantID          = el.id
LEFT  JOIN job_title       jt    ON el.designation          = jt.id
LEFT  JOIN sections        s     ON el.section_id           = s.id
LEFT  JOIN organization    o     ON el.organization_id      = o.id
LEFT  JOIN leave_types     lt    ON la.leaveType            = lt.leaveID
WHERE lrh.returnType = 'to_admin'
  AND la.status <> 1
  AND el.organization_id = ?";

$countStmt = mysqli_prepare($con, "SELECT COUNT(*) c $baseFrom");
mysqli_stmt_bind_param($countStmt, 'i', $orgID);
mysqli_stmt_execute($countStmt);
$totalRecords = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'] ?? 0);
mysqli_stmt_close($countStmt);

$dataSql = "
SELECT
    lrh.dataID           AS returnID,
    lrh.leaveApplicationID AS applicationID,
    lrh.returnedByName   AS returnedByName,
    lrh.returnedByTitle  AS returnedByTitle,
    lrh.note             AS returnNote,
    lrh.createdAt        AS returnedAt,
    la.status            AS appStatus,
    la.dateFrom          AS dateFrom,
    la.dateTo            AS dateTo,
    la.application_no    AS applicationNo,
    la.submitDate        AS submitDate,
    la.dataID            AS appDataID,
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
mysqli_stmt_bind_param($dataStmt, 'i', $orgID);
mysqli_stmt_execute($dataStmt);
$dataRes = mysqli_stmt_get_result($dataStmt);

$data   = [];
$serial = $start + 1;

while ($r = mysqli_fetch_assoc($dataRes)) {
    $appNo = !empty($r['applicationNo'])
        ? $r['applicationNo']
        : ('BITAC/' . date('Y', strtotime($r['submitDate'] ?? 'now')) . '/' . (int)$r['appDataID']);

    // Applicant cell (avatar + name + designation)
    $empName  = trim($r['employee_name']  ?? '');
    $empJob   = trim($r['job_title_name'] ?? '');
    $empSec   = trim($r['section_name']   ?? '');
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
                   . ($empSec ? '<div class="emp-sub-light">' . htmlspecialchars($empSec) . '</div>' : '')
                   . '</div></div>';

    // Requested leave — multi-segment aware.
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

    // Returned-by cell
    $rBy    = trim($r['returnedByName']  ?? '');
    $rTitle = trim($r['returnedByTitle'] ?? '');
    $rWhen  = !empty($r['returnedAt']) ? banglaNumber(date('d/m/Y g:i A', strtotime($r['returnedAt']))) : '—';
    $returnedByCell = '<div style="line-height:1.4;"><i class="ti tabler-user-x me-1" style="color:#b8651a;"></i>'
                    . ($rBy !== '' ? htmlspecialchars($rBy) : 'সংশ্লিষ্ট সিদ্ধান্তকারী')
                    . ($rTitle !== '' ? ' <span class="text-muted small">(' . htmlspecialchars($rTitle) . ')</span>' : '')
                    . '</div>'
                    . '<div class="text-muted small mt-1"><i class="ti tabler-clock me-1"></i>' . $rWhen . '</div>';

    // Reason
    $noteHtml = trim($r['returnNote'] ?? '');
    $noteCell = $noteHtml !== ''
        ? '<div style="max-width:280px;font-size:0.82rem;line-height:1.45;color:#4a4d63;white-space:normal;">' . nl2br(htmlspecialchars($noteHtml)) . '</div>'
        : '<span class="text-muted small">—</span>';

    // Current status of the application
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
            $statusCell = '<span class="status-pill" style="background:#fff3e1;color:#b8651a;"><i class="ti tabler-corner-up-left me-1"></i>প্রশাসনিক ডেস্কে</span>';
            break;
        default:
            $statusCell = '<span class="status-pill status-pending">—</span>';
    }

    // Actions — view details
    $actionCell = '<a target="_blank" href="../../views/leave/application-details.php?menuslug=allowed-leave-applications&leaveApplicationID='
                . (int)$r['applicationID'] . '" class="action-icon icon-view" title="বিস্তারিত দেখুন">'
                . '<i class="ti tabler-eye"></i></a>';

    $data[] = [
        'serial'         => '<span class="serial-num">' . $serial++ . '</span>',
        'applicant_cell' => $applicantCell,
        'requested'      => $requestedHtml,
        'returned_by'    => $returnedByCell,
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
