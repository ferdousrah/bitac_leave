<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
    exit;
}

$actorStmt = mysqli_prepare($con,
    "SELECT employee_id, isCenterAdmin, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$un = $_SESSION['username'];
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);

$myEmpID       = (int)($actor['employee_id']    ?? 0);
$isCenterAdmin = (int)($actor['isCenterAdmin']  ?? 0);
$myGroupID     = (int)($actor['user_group_id']  ?? 0);

// Broaden: any user whose group has been granted this submodule can operate it.
if (!$isCenterAdmin && $myGroupID > 0) {
    $_permStmt = mysqli_prepare($con,
        "SELECT 1 FROM group_access_permission gap
         INNER JOIN submodules sm ON gap.submodule_id = sm.dataID
         WHERE gap.user_group_id = ? AND sm.slug = 'optional-pre-approval-forward-queue'
         LIMIT 1");
    mysqli_stmt_bind_param($_permStmt, 'i', $myGroupID);
    mysqli_stmt_execute($_permStmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($_permStmt))) {
        $isCenterAdmin = 1;
    }
    mysqli_stmt_close($_permStmt);
}

if ($myEmpID <= 0 || !$isCenterAdmin) {
    echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
    exit;
}

// Actor's org (must match applicant's org)
$myOrgQ = mysqli_prepare($con, "SELECT organization_id FROM employee_list WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($myOrgQ, 'i', $myEmpID);
mysqli_stmt_execute($myOrgQ);
$myOrgRow = mysqli_fetch_assoc(mysqli_stmt_get_result($myOrgQ)) ?: [];
mysqli_stmt_close($myOrgQ);
$myOrg = (int)($myOrgRow['organization_id'] ?? 0);
if ($myOrg <= 0) {
    echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
    exit;
}

$limit = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$start = isset($_POST['start'])  ? (int)$_POST['start']  : 0;

// Forward queue: apps where supervisor has recommended, admin hasn't forwarded, and applicant's org = my org
$where = "opa.status = 0
          AND opa.organization_id = $myOrg
          AND EXISTS (
                SELECT 1 FROM optional_leave_pre_approval_signatory sSup
                WHERE sSup.preApprovalID = opa.id
                  AND sSup.isSupervisor = 1
                  AND sSup.isApproved = 1
          )
          AND NOT EXISTS (
                SELECT 1 FROM optional_leave_pre_approval_signatory sChain
                WHERE sChain.preApprovalID = opa.id
                  AND sChain.isSupervisor = 0
                  AND sChain.isSentbyAdmin = 1
          )";

$sql = "
    SELECT opa.*,
           el.employee_name, el.employee_id AS emp_code, el.photo,
           jt.job_title_name, o.organization_name,
           supEl.employee_name AS supervisor_name
    FROM optional_leave_pre_approval opa
    INNER JOIN employee_list el ON opa.employee_id = el.id
    LEFT JOIN job_title jt ON el.designation = jt.id
    LEFT JOIN organization o ON opa.organization_id = o.id
    LEFT JOIN optional_leave_pre_approval_signatory sSup
        ON sSup.preApprovalID = opa.id AND sSup.isSupervisor = 1
    LEFT JOIN employee_list supEl ON sSup.signatory = supEl.id
    WHERE $where
    ORDER BY opa.submit_date ASC
    LIMIT $start, $limit
";
$res = mysqli_query($con, $sql);

$countSql = "SELECT COUNT(*) FROM optional_leave_pre_approval opa WHERE $where";
$total = (int)(mysqli_fetch_row(mysqli_query($con, $countSql))[0] ?? 0);

function bn_dt_fwd($d) {
    if (!$d) return '<span class="text-muted">—</span>';
    $p = date_parse($d);
    if (!$p['year']) return htmlspecialchars($d);
    return banglaNumber(sprintf('%02d', $p['day'])) . '-' . banglaNumber(sprintf('%02d', $p['month'])) . '-' . banglaNumber($p['year']);
}

$data = [];
$sl = $start + 1;
while ($row = mysqli_fetch_assoc($res)) {
    $empName = trim($row['employee_name'] ?? '');
    $empCode = trim((string)($row['emp_code'] ?? ''));
    $empJob  = trim($row['job_title_name'] ?? '');
    $initials = mb_substr($empName, 0, 1, 'UTF-8');
    $parts = preg_split('/\s+/u', $empName);
    if (count($parts) > 1) $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
    $avatar = !empty($row['photo'])
        ? '<div class="emp-avatar"><img src="' . BASE_URL . '/uploads/' . htmlspecialchars($row['photo']) . '" alt=""></div>'
        : '<div class="emp-avatar">' . htmlspecialchars($initials) . '</div>';
    $empCell = '<div class="emp-cell">' . $avatar
             . '<div><div class="fw-semibold">' . htmlspecialchars($empName) . ($empCode ? ' <span class="text-muted small">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
             . ($empJob ? '<div class="text-muted small">' . htmlspecialchars($empJob) . '</div>' : '')
             . '</div></div>';

    $notes = trim($row['festival_notes'] ?? '');
    $notesCell = $notes === '' ? '<span class="text-muted small">—</span>' : '<div style="max-width:260px;">' . htmlspecialchars($notes) . '</div>';

    $supName = trim($row['supervisor_name'] ?? '');
    $supCell = $supName === ''
        ? '<span class="text-muted small">—</span>'
        : '<div class="small"><i class="ti tabler-user-check text-success me-1"></i>' . htmlspecialchars($supName) . '<br><span class="text-muted">সুপারিশ প্রাপ্ত</span></div>';

    $pid = (int)$row['id'];
    $action = '<div class="action-group">'
            . '<button type="button" class="action-icon btn-opa-view" data-pid="' . $pid . '" data-bs-toggle="tooltip" title="বিস্তারিত"><i class="ti tabler-eye"></i></button>'
            . '<button type="button" class="action-icon icon-forward btn-opa-forward" data-pid="' . $pid . '" data-requested="' . htmlspecialchars($row['requested_days']) . '" data-bs-toggle="tooltip" title="অনুমোদনের জন্য পাঠান"><i class="ti tabler-send"></i></button>'
            . '</div>';

    $data[] = [
        'sl'          => '<span class="serial-num">' . banglaNumber($sl) . '</span>',
        'employee'    => $empCell,
        'year'        => '<span class="opa-year-pill">' . banglaNumber((int)$row['year']) . '</span>',
        'days'        => '<span class="fw-bold">' . banglaNumber((float)$row['requested_days']) . '</span>',
        'notes'       => $notesCell,
        'supervisor'  => $supCell,
        'submit_date' => bn_dt_fwd($row['submit_date']),
        'action'      => $action,
    ];
    $sl++;
}

echo json_encode([
    'draw'            => (int)($_POST['draw'] ?? 0),
    'recordsTotal'    => $total,
    'recordsFiltered' => $total,
    'data'            => $data,
]);
mysqli_close($con);
