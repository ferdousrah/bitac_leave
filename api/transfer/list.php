<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

header('Content-Type: application/json');

// Actor scope
$actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);

$isSuperAdmin  = ((int)($actor['user_group_id'] ?? 0) === 1);
$myCenterID    = (int)($actor['emp_org'] ?? 0);
$seeAllCenters = ($isSuperAdmin || $myCenterID === 4);

// DataTable params
$limit  = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$start  = isset($_POST['start'])  ? (int)$_POST['start']  : 0;
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : '';
$dateFrom   = isset($_POST['date_from'])   ? mysqli_real_escape_string($con, $_POST['date_from'])   : '';
$dateTo     = isset($_POST['date_to'])     ? mysqli_real_escape_string($con, $_POST['date_to'])     : '';
$fromCenter = isset($_POST['from_center']) ? (int)$_POST['from_center'] : 0;
$toCenter   = isset($_POST['to_center'])   ? (int)$_POST['to_center']   : 0;
$status     = isset($_POST['status'])      ? mysqli_real_escape_string($con, $_POST['status'])      : '';

$where = "h.from_organization_id IS NOT NULL"; // exclude initial postings (no transfer event)

// Scope enforcement
if (!$seeAllCenters) {
    $mc = $myCenterID;
    $where .= " AND (h.from_organization_id = $mc OR h.to_organization_id = $mc)";
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where .= " AND h.transfer_date >= '$dateFrom'";
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where .= " AND h.transfer_date <= '$dateTo'";
}
if ($fromCenter > 0) $where .= " AND h.from_organization_id = $fromCenter";
if ($toCenter   > 0) $where .= " AND h.to_organization_id = $toCenter";

if ($status === 'pending') {
    $where .= " AND h.effective_to IS NULL AND h.section_id_at_join IS NULL";
} elseif ($status === 'active') {
    $where .= " AND h.effective_to IS NULL AND h.section_id_at_join IS NOT NULL";
} elseif ($status === 'closed') {
    $where .= " AND h.effective_to IS NOT NULL";
}

if ($search !== '') {
    $where .= " AND (e.employee_name LIKE '%$search%' OR e.employee_id LIKE '%$search%' OR h.order_number LIKE '%$search%')";
}

$orderDir = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';

$sql = "
    SELECT h.dataID, h.employee_ref_id, h.from_organization_id, h.to_organization_id,
           h.transfer_date, h.actual_joining_date, h.effective_to,
           h.order_number, h.order_date, h.section_id_at_join,
           e.employee_name, e.employee_id, e.photo, e.pending_section_assignment,
           jt.job_title_name,
           ofrm.organization_name AS from_org_name,
           oto.organization_name  AS to_org_name,
           sec.section_name
    FROM employee_transfer_history h
    LEFT JOIN employee_list e   ON e.id = h.employee_ref_id
    LEFT JOIN job_title jt      ON jt.id = e.designation
    LEFT JOIN organization ofrm ON ofrm.id = h.from_organization_id
    LEFT JOIN organization oto  ON oto.id  = h.to_organization_id
    LEFT JOIN sections sec      ON sec.id  = h.section_id_at_join
    WHERE $where
    ORDER BY h.transfer_date $orderDir, h.dataID $orderDir
    LIMIT $start, $limit
";

$result = mysqli_query($con, $sql);
if (!$result) {
    echo json_encode(['draw'=>(int)($_POST['draw']??0), 'recordsTotal'=>0, 'recordsFiltered'=>0, 'data'=>[], 'error'=>mysqli_error($con)]);
    exit;
}

$cntSql = "SELECT COUNT(*) FROM employee_transfer_history h
    LEFT JOIN employee_list e ON e.id = h.employee_ref_id
    WHERE $where";
$cntRes = mysqli_query($con, $cntSql);
$total  = (int)(mysqli_fetch_row($cntRes)[0] ?? 0);

function bn_date($d) {
    if (!$d || $d === '0000-00-00') return '<span class="text-muted">—</span>';
    $parts = explode('-', $d);
    if (count($parts) !== 3) return htmlspecialchars($d);
    return banglaNumber($parts[2]) . '-' . banglaNumber($parts[1]) . '-' . banglaNumber($parts[0]);
}

$data = [];
$sl = $start + 1;
while ($row = mysqli_fetch_assoc($result)) {
    $empName  = trim($row['employee_name'] ?? '');
    $empCode  = trim((string)($row['employee_id'] ?? ''));
    $empPhoto = trim($row['photo'] ?? '');
    $empJob   = trim($row['job_title_name'] ?? '');

    $initials = mb_substr($empName, 0, 1, 'UTF-8');
    $parts = preg_split('/\s+/u', $empName);
    if (count($parts) > 1) {
        $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
    }
    if (!empty($empPhoto)) {
        $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
        $avatar = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt=""></div>';
    } else {
        $avatar = '<div class="emp-avatar">' . htmlspecialchars($initials) . '</div>';
    }
    $empCell = '<div class="emp-cell">' . $avatar
             . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName)
             . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
             . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
             . '</div></div>';

    $route = '<div class="transfer-route"><span class="from">' . htmlspecialchars($row['from_org_name'] ?? '—')
           . '</span><i class="ti tabler-arrow-right arrow"></i><span class="to">' . htmlspecialchars($row['to_org_name'] ?? '—')
           . '</span></div>';

    // Status determination
    if (!empty($row['effective_to'])) {
        $statusBadge = '<span class="badge-status-closed"><i class="ti tabler-archive me-1"></i>পরবর্তী বদলি</span>';
    } elseif (empty($row['section_id_at_join'])) {
        $statusBadge = '<span class="badge-status-pending"><i class="ti tabler-clock-pause me-1"></i>সেকশন অপেক্ষমান</span>';
    } else {
        $statusBadge = '<span class="badge-status-active"><i class="ti tabler-check me-1"></i>সক্রিয় ('
                     . htmlspecialchars($row['section_name'] ?? '') . ')</span>';
    }

    $action = '<button type="button" class="action-icon view btn-tr-detail" data-emp="' . (int)$row['employee_ref_id']
            . '" data-bs-toggle="tooltip" title="পোস্টিং ইতিহাস"><i class="ti tabler-history"></i></button>';

    $data[] = [
        'sl'                  => '<span class="serial-num">' . banglaNumber($sl) . '</span>',
        'employee_cell'       => $empCell,
        'route'               => $route,
        'order_number'        => $row['order_number'] ? htmlspecialchars($row['order_number']) : '<span class="text-muted">—</span>',
        'order_date'          => bn_date($row['order_date']),
        'transfer_date'       => bn_date($row['transfer_date']),
        'actual_joining_date' => bn_date($row['actual_joining_date']),
        'status_badge'        => $statusBadge,
        'action'              => $action,
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
