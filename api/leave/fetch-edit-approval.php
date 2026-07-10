<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

error_reporting(0);
ini_set('display_errors', 0);

function pq_fetch_one($con, $sql, $types = '', ...$params) {
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) return null;
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

$sessionUsername = $_SESSION['username'] ?? '';
$userRow = pq_fetch_one($con, "SELECT employee_id FROM user_list WHERE user_id = ?", 's', $sessionUsername);
$signatoryEmpId = (int)($userRow['employee_id'] ?? 0);

$request = $_REQUEST;
$draw = isset($request['draw']) ? intval($request['draw']) : 0;

if ($signatoryEmpId <= 0) {
    echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    exit;
}

// ── Canonical "current signatory" filter for edit-approval chain ──
// Show only rows where:
//   - actor is the signatory
//   - row pending
//   - parent edit-request still pending (status=0)
//   - no earlier-serial row is still pending (chain ordering)
$baseWhere = "
    WHERE ldfa.signatory   = ?
      AND ldfa.isApproved  = 0
      AND led.status       = 0
      AND NOT EXISTS (
          SELECT 1 FROM leave_edit_data_for_approval prev
          WHERE prev.editRequestID = ldfa.editRequestID
            AND prev.serial        < ldfa.serial
            AND prev.isApproved    = 0
      )";

// Count
$totalStmt = mysqli_prepare($con, "
    SELECT COUNT(*) AS total
    FROM leave_edit_data_for_approval ldfa
    INNER JOIN leave_edit_data led ON led.dataID = ldfa.editRequestID
    $baseWhere");
mysqli_stmt_bind_param($totalStmt, 'i', $signatoryEmpId);
mysqli_stmt_execute($totalStmt);
$totalData = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['total'] ?? 0);
mysqli_stmt_close($totalStmt);

// Order + pagination
$orderDir = (isset($request['order'][0]['dir']) && strtolower($request['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';
$start    = isset($request['start'])  ? max(0, intval($request['start']))  : 0;
$length   = isset($request['length']) ? max(1, intval($request['length'])) : 10;

$mainStmt = mysqli_prepare($con, "
    SELECT ldfa.dataID AS approvalRowID, ldfa.editRequestID, ldfa.serial,
           led.leaveApplicationID, led.applicantID, led.adminNote, led.attachment,
           led.submitDate, led.submitTime, led.dataID AS editID
    FROM leave_edit_data_for_approval ldfa
    INNER JOIN leave_edit_data led ON led.dataID = ldfa.editRequestID
    $baseWhere
    ORDER BY led.submitDate $orderDir, ldfa.dataID $orderDir
    LIMIT ?, ?");
mysqli_stmt_bind_param($mainStmt, 'iii', $signatoryEmpId, $start, $length);
mysqli_stmt_execute($mainStmt);
$res = mysqli_stmt_get_result($mainStmt);

function seg_summary_html($con, $editRequestID, $kind, $deltaColor = '') {
    $q = mysqli_query($con,
        "SELECT s.*, lt.leaveTitle FROM leave_edit_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.editRequestID = " . (int)$editRequestID . " AND s.kind = '" . mysqli_real_escape_string($con, $kind) . "'
         ORDER BY s.serial ASC, s.dataID ASC");
    if (!$q || mysqli_num_rows($q) === 0) return '<span class="text-muted small">—</span>';
    $rows = [];
    $totalDays = 0;
    while ($r = mysqli_fetch_assoc($q)) {
        $totalDays += (int)$r['days'];
        $rows[] = $r;
    }
    $html = '';
    foreach ($rows as $r) {
        $html .= '<div class="date-range mb-1">'
              .  '<span class="leave-type-chip">' . htmlspecialchars($r['leaveTitle'] ?? '—') . '</span>'
              .  ' <span>' . banglaNumber(date('d/m/Y', strtotime($r['dateFrom']))) . '</span>'
              .  '<i class="ti tabler-arrow-narrow-right text-muted mx-1"></i>'
              .  '<span>' . banglaNumber(date('d/m/Y', strtotime($r['dateTo']))) . '</span>'
              .  ' <span class="days-pill ' . ($kind === 'proposed' ? 'days-pill-info' : 'days-pill-success') . '">' . banglaNumber((int)$r['days']) . ' দিন</span>'
              .  '</div>';
    }
    if (count($rows) > 1) {
        $html .= '<div class="leave-meta mt-1"><strong>মোট: ' . banglaNumber($totalDays) . ' দিন</strong></div>';
    }
    return $html;
}

function approved_leave_html($con, $leaveApplicationID) {
    // Try segments first (kind=proposed)
    $segQ = mysqli_query($con,
        "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.applicationID = " . (int)$leaveApplicationID . " AND s.kind = 'proposed'
         ORDER BY s.serial ASC, s.dataID ASC");
    $rows = [];
    if ($segQ) while ($r = mysqli_fetch_assoc($segQ)) $rows[] = $r;

    if (empty($rows)) {
        $segQ2 = mysqli_query($con,
            "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
             LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
             WHERE s.applicationID = " . (int)$leaveApplicationID . " AND s.kind = 'requested'
             ORDER BY s.serial ASC, s.dataID ASC");
        if ($segQ2) while ($r = mysqli_fetch_assoc($segQ2)) $rows[] = $r;
    }

    if (empty($rows)) {
        // Legacy fallback to leave_applications fields
        $app = mysqli_query($con,
            "SELECT la.approvedDateFrom, la.approvedDateTo, la.approvedDays, lt.leaveTitle
             FROM leave_applications la
             LEFT JOIN leave_types lt ON la.approvedLeaveType = lt.leaveID
             WHERE la.dataID = " . (int)$leaveApplicationID);
        if ($app && $row = mysqli_fetch_assoc($app)) {
            return '<div class="date-range mb-1">'
                 . '<span class="leave-type-chip">' . htmlspecialchars($row['leaveTitle'] ?? '—') . '</span>'
                 . ' <span>' . banglaNumber(date('d/m/Y', strtotime($row['approvedDateFrom']))) . '</span>'
                 . '<i class="ti tabler-arrow-narrow-right text-muted mx-1"></i>'
                 . '<span>' . banglaNumber(date('d/m/Y', strtotime($row['approvedDateTo']))) . '</span>'
                 . ' <span class="days-pill days-pill-success">' . banglaNumber((int)$row['approvedDays']) . ' দিন</span>'
                 . '</div>';
        }
        return '<span class="text-muted small">—</span>';
    }

    $html = '';
    $total = 0;
    foreach ($rows as $r) {
        $total += (int)$r['days'];
        $html .= '<div class="date-range mb-1">'
              .  '<span class="leave-type-chip">' . htmlspecialchars($r['leaveTitle'] ?? '—') . '</span>'
              .  ' <span>' . banglaNumber(date('d/m/Y', strtotime($r['dateFrom']))) . '</span>'
              .  '<i class="ti tabler-arrow-narrow-right text-muted mx-1"></i>'
              .  '<span>' . banglaNumber(date('d/m/Y', strtotime($r['dateTo']))) . '</span>'
              .  ' <span class="days-pill days-pill-success">' . banglaNumber((int)$r['days']) . ' দিন</span>'
              .  '</div>';
    }
    if (count($rows) > 1) {
        $html .= '<div class="leave-meta mt-1"><strong>মোট: ' . banglaNumber($total) . ' দিন</strong></div>';
    }
    return $html;
}

$data = [];
$sl = $start;
while ($r = mysqli_fetch_assoc($res)) {
    $sl++;
    $appID  = (int)$r['leaveApplicationID'];
    $editID = (int)$r['editID'];

    // Applicant
    $emp = pq_fetch_one($con,
        "SELECT el.employee_id, el.employee_name, el.photo, jt.job_title_name
         FROM employee_list el
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE el.id = ?", 'i', (int)$r['applicantID']);
    $empName  = trim($emp['employee_name'] ?? '');
    $empCode  = trim($emp['employee_id']   ?? '');
    $empJob   = trim($emp['job_title_name'] ?? '');
    $empPhoto = trim($emp['photo']          ?? '');
    $parts = preg_split('/\s+/u', $empName);
    $initials = mb_substr($parts[0] ?? '', 0, 1, 'UTF-8');
    if (count($parts) > 1) $initials .= mb_substr(end($parts), 0, 1, 'UTF-8');

    if (!empty($empPhoto)) {
        $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
        $avatar = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
    } else {
        $avatar = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
    }
    $submitWhen = trim(($r['submitDate'] ?? '') . ' ' . ($r['submitTime'] ?? ''));
    $employeeCell = '<div class="emp-cell">' . $avatar
                  . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName)
                  . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '')
                  . '</div>'
                  . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                  . ($submitWhen ? '<div class="emp-sub-light"><i class="ti tabler-clock me-1"></i>' . htmlspecialchars($submitWhen) . '</div>' : '')
                  . '</div></div>';

    // Approved vs proposed
    $approvedLeaveHtml = approved_leave_html($con, $appID);
    $proposedLeaveHtml = seg_summary_html($con, (int)$r['editRequestID'], 'proposed');

    // Original application link
    $approvedLeaveHtml .= '<div class="mt-2"><a target="_blank" href="../../views/leave/application-details.php?menuslug=leave-edit-approval&leaveApplicationID=' . $appID . '" class="action-icon icon-view" data-bs-toggle="tooltip" title="মূল আবেদন"><i class="ti tabler-file-text"></i></a>'
                       . ' <a target="_blank" href="../../api/reports/leave-notice.php?menuslug=leave-edit-approval&leaveApplicationID=' . $appID . '" class="action-icon icon-view" data-bs-toggle="tooltip" title="পূর্বের অফিস আদেশ"><i class="ti tabler-file-description"></i></a></div>';

    // Admin note + attachment link
    $note = trim($r['adminNote'] ?? '');
    $noteHtml = $note !== ''
        ? '<div class="small" style="max-width:240px; line-height:1.4;">' . nl2br(htmlspecialchars(mb_substr($note, 0, 180))) . (mb_strlen($note) > 180 ? '…' : '') . '</div>'
        : '<span class="text-muted small">—</span>';
    if (!empty($r['attachment'])) {
        $noteHtml .= '<div class="mt-2"><a href="../../uploads/' . htmlspecialchars($r['attachment']) . '" target="_blank" class="action-icon icon-attach" data-bs-toggle="tooltip" title="সংযুক্তি"><i class="ti tabler-paperclip"></i></a></div>';
    }

    // Action — open the approval detail page (built in Phase 4)
    $action = '<div class="action-group">'
            . '<a href="../../views/leave/approve-edit-application.php?menuslug=leave-edit-approval&editID=' . $editID . '" class="action-icon icon-view" data-bs-toggle="tooltip" title="বিস্তারিত ও সিদ্ধান্ত"><i class="ti tabler-eye"></i></a>'
            . '</div>';

    $data[] = [
        "serial"         => '<span class="serial-num">' . $sl . '</span>',
        "employee_cell"  => $employeeCell,
        "approved_leave" => $approvedLeaveHtml,
        "proposed_leave" => $proposedLeaveHtml,
        "admin_note"     => $noteHtml,
        "action"         => $action,
    ];
}
mysqli_stmt_close($mainStmt);

echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $totalData,
    "recordsFiltered" => $totalData,
    "data"            => $data,
]);
