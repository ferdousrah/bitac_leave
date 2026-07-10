<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');
header('Content-Type: application/json');

// Employee self-service scope — resolve actor's own employee_id
$actorStmt = mysqli_prepare($con,
    "SELECT ul.employee_id FROM user_list ul WHERE ul.user_id = ? LIMIT 1");
$un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);
$myEmpID = (int)($actor['employee_id'] ?? 0);
if ($myEmpID <= 0) {
    echo json_encode(['draw'=>(int)($_POST['draw']??0),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
    exit;
}

$limit = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$start = isset($_POST['start'])  ? (int)$_POST['start']  : 0;
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : '';
$fYear  = isset($_POST['year']) ? (int)$_POST['year'] : 0;
$fStat  = isset($_POST['status']) && $_POST['status'] !== '' ? (int)$_POST['status'] : -1;

// Self-only: scope to actor's own applications
$where = 'opa.employee_id = ' . $myEmpID;
if ($fYear > 0)      $where .= ' AND opa.year = ' . $fYear;
if ($fStat >= 0)     $where .= ' AND opa.status = ' . $fStat;
if ($search !== '')  $where .= " AND opa.festival_notes LIKE '%$search%'";

$sql = "
    SELECT opa.*, el.employee_name, el.employee_id AS emp_code, el.photo,
           jt.job_title_name, o.organization_name
    FROM optional_leave_pre_approval opa
    INNER JOIN employee_list el ON opa.employee_id = el.id
    LEFT JOIN job_title jt ON el.designation = jt.id
    LEFT JOIN organization o ON el.organization_id = o.id
    WHERE $where
    ORDER BY opa.submit_date DESC, opa.id DESC
    LIMIT $start, $limit
";
$res = mysqli_query($con, $sql);
$total = (int)(mysqli_fetch_row(mysqli_query($con,
    "SELECT COUNT(*) FROM optional_leave_pre_approval opa
     INNER JOIN employee_list el ON opa.employee_id = el.id
     WHERE $where"))[0] ?? 0);

function bn_date_short($d) {
    if (!$d || $d === '0000-00-00 00:00:00' || $d === '0000-00-00') return '<span class="text-muted">—</span>';
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

    $status = (int)$row['status'];
    if ($status === 1)      $statBadge = '<span class="opa-status-approved">অনুমোদিত</span>';
    elseif ($status === 2)  $statBadge = '<span class="opa-status-rejected">প্রত্যাখ্যাত</span>';
    else                    $statBadge = '<span class="opa-status-pending">অপেক্ষমান</span>';

    $notes = trim($row['festival_notes'] ?? '');
    $notesCell = $notes === '' ? '<span class="text-muted small">—</span>' : '<div style="max-width:250px;">' . htmlspecialchars($notes) . '</div>';

    $action = '<button type="button" class="btn btn-sm btn-label-primary btn-opa-detail" data-pid="' . (int)$row['id'] . '" data-bs-toggle="tooltip" title="বিস্তারিত"><i class="ti tabler-eye"></i></button>';

    $data[] = [
        'sl'          => '<span class="serial-num">' . banglaNumber($sl) . '</span>',
        'year'        => '<span class="opa-year-pill">' . banglaNumber((int)$row['year']) . '</span>',
        'days'        => '<span class="fw-bold">' . banglaNumber((float)$row['requested_days']) . '</span>',
        'notes'       => $notesCell,
        'submit_date' => bn_date_short($row['submit_date']),
        'status'      => $statBadge,
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
