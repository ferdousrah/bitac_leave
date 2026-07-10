<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

header('Content-Type: application/json');

// Org gate
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

$limit = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$start = isset($_POST['start'])  ? (int)$_POST['start']  : 0;

$where = "e.employment_status = 1 AND e.pending_section_assignment = 1";
if (!$seeAllCenters) {
    $where .= " AND e.organization_id = " . $myCenterID;
}

$sql = "
    SELECT e.id, e.employee_name, e.employee_id, e.photo, e.organization_id,
           jt.job_title_name, ocur.organization_name AS current_org,
           h.from_organization_id, h.transfer_date, h.order_number, h.order_date,
           h.attachment,
           ofrm.organization_name AS from_org_name
    FROM employee_list e
    LEFT JOIN job_title jt    ON jt.id = e.designation
    LEFT JOIN organization ocur ON ocur.id = e.organization_id
    LEFT JOIN (
        SELECT h1.*
        FROM employee_transfer_history h1
        WHERE h1.effective_to IS NULL
    ) h ON h.employee_ref_id = e.id
    LEFT JOIN organization ofrm ON ofrm.id = h.from_organization_id
    WHERE $where
    ORDER BY h.transfer_date DESC, e.id DESC
    LIMIT $start, $limit
";

$result = mysqli_query($con, $sql);
if (!$result) {
    echo json_encode(['draw'=>(int)($_POST['draw']??0), 'recordsTotal'=>0, 'recordsFiltered'=>0, 'data'=>[], 'error'=>mysqli_error($con)]);
    exit;
}

$cntRes = mysqli_query($con, "SELECT COUNT(*) FROM employee_list e WHERE $where");
$total = (int)(mysqli_fetch_row($cntRes)[0] ?? 0);

function bn_date($d) {
    if (!$d || $d === '0000-00-00') return '<span class="text-muted">—</span>';
    $parts = explode('-', $d);
    if (count($parts) !== 3) return htmlspecialchars($d);
    return banglaNumber($parts[2]) . '-' . banglaNumber($parts[1]) . '-' . banglaNumber($parts[0]);
}

$data = [];
$sl = $start + 1;
while ($row = mysqli_fetch_assoc($result)) {
    $empId    = (int)$row['id'];
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
        $avatar = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
    } else {
        $avatar = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
    }
    $empCell = '<div class="emp-cell">' . $avatar
             . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName)
             . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
             . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
             . '</div></div>';

    $fromCtr = trim($row['from_org_name'] ?? '');
    $fromCell = $fromCtr
        ? '<span class="meta-chip center"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($fromCtr) . '</span>'
        : '<span class="text-muted small">—</span>';

    $orderCell = $row['order_number']
        ? htmlspecialchars($row['order_number']) . ($row['order_date'] ? ' <span class="text-muted small">(' . bn_date($row['order_date']) . ')</span>' : '')
        : '<span class="text-muted">—</span>';

    // Attachment cell — clickable link to view the order copy (opens in new tab)
    $attachmentCell = !empty($row['attachment'])
        ? '<a href="' . BASE_URL . '/uploads/' . htmlspecialchars($row['attachment']) . '" target="_blank" rel="noopener" '
          . 'class="btn btn-sm btn-label-primary" data-bs-toggle="tooltip" title="আদেশের কপি দেখুন">'
          . '<i class="ti tabler-paperclip"></i></a>'
        : '<span class="text-muted">—</span>';

    // Action: assign-section button (data-attrs feed the modal)
    $assignBtn = '<button type="button" class="btn btn-sm btn-primary btn-assign-section"'
               . ' data-emp="' . $empId . '"'
               . ' data-name="' . htmlspecialchars($empName, ENT_QUOTES) . '"'
               . ' data-code="' . htmlspecialchars($empCode, ENT_QUOTES) . '"'
               . ' data-from="' . htmlspecialchars($fromCtr, ENT_QUOTES) . '"'
               . ' data-transfer-date="' . htmlspecialchars($row['transfer_date'] ?? '', ENT_QUOTES) . '"'
               . ' data-org="' . (int)$row['organization_id'] . '">'
               . '<i class="ti tabler-building me-1"></i>সেকশন বরাদ্দ</button>';

    $data[] = [
        'sl'            => '<span class="serial-num">' . banglaNumber($sl) . '</span>',
        'employee_cell' => $empCell,
        'from_center'   => $fromCell,
        'transfer_date' => bn_date($row['transfer_date']),
        'order_number'  => $orderCell,
        'attachment'    => $attachmentCell,
        'action'        => $assignBtn,
    ];
    $sl++;
}

echo json_encode([
    'draw' => (int)($_POST['draw'] ?? 0),
    'recordsTotal' => $total,
    'recordsFiltered' => $total,
    'data' => $data,
]);

mysqli_close($con);
