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
    "SELECT employee_id FROM user_list WHERE user_id = ? LIMIT 1");
$un = $_SESSION['username'];
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);
$myEmpID = (int)($actor['employee_id'] ?? 0);
if ($myEmpID <= 0) {
    echo json_encode(['draw'=>0,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
    exit;
}

$limit = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$start = isset($_POST['start'])  ? (int)$_POST['start']  : 0;

// Supervisor queue: apps where I'm the isSupervisor=1 row and haven't acted yet
$where = "opa.status = 0
          AND s.isSupervisor = 1
          AND s.signatory = $myEmpID
          AND s.isApproved = 0";

$sql = "
    SELECT opa.*, s.dataID AS sigDataID,
           el.employee_name, el.employee_id AS emp_code, el.photo,
           jt.job_title_name, o.organization_name
    FROM optional_leave_pre_approval opa
    INNER JOIN optional_leave_pre_approval_signatory s ON s.preApprovalID = opa.id
    INNER JOIN employee_list el ON opa.employee_id = el.id
    LEFT JOIN job_title jt ON el.designation = jt.id
    LEFT JOIN organization o ON opa.organization_id = o.id
    WHERE $where
    ORDER BY opa.submit_date ASC
    LIMIT $start, $limit
";
$res = mysqli_query($con, $sql);

$countSql = "
    SELECT COUNT(*) FROM optional_leave_pre_approval opa
    INNER JOIN optional_leave_pre_approval_signatory s ON s.preApprovalID = opa.id
    WHERE $where
";
$total = (int)(mysqli_fetch_row(mysqli_query($con, $countSql))[0] ?? 0);

function bn_dt_sup($d) {
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

    $pid = (int)$row['id'];
    $action = '<div class="action-group">'
            . '<button type="button" class="action-icon btn-opa-view" data-pid="' . $pid . '" data-bs-toggle="tooltip" title="বিস্তারিত"><i class="ti tabler-eye"></i></button>'
            . '<button type="button" class="action-icon icon-approve btn-opa-approve" data-pid="' . $pid . '" data-bs-toggle="tooltip" title="সুপারিশ"><i class="ti tabler-check"></i></button>'
            . '<button type="button" class="action-icon icon-reject btn-opa-reject" data-pid="' . $pid . '" data-bs-toggle="tooltip" title="প্রত্যাখ্যান"><i class="ti tabler-x"></i></button>'
            . '</div>';

    $data[] = [
        'sl'          => '<span class="serial-num">' . banglaNumber($sl) . '</span>',
        'employee'    => $empCell,
        'year'        => '<span class="opa-year-pill">' . banglaNumber((int)$row['year']) . '</span>',
        'days'        => '<span class="fw-bold">' . banglaNumber((float)$row['requested_days']) . '</span>',
        'notes'       => $notesCell,
        'submit_date' => bn_dt_sup($row['submit_date']),
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
