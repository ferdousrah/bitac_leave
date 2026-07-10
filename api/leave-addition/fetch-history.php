<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../library/number_converter.php');
ob_end_clean();

if (!isset($_SESSION['username'])) {
    echo json_encode(["draw" => 0, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Unauthorized"]);
    exit;
}

// DataTables params
$draw   = isset($_POST['draw'])   ? intval($_POST['draw'])   : 1;
$start  = isset($_POST['start'])  ? max(0, intval($_POST['start']))  : 0;
$length = isset($_POST['length']) ? max(1, intval($_POST['length'])) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
$orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 5;
$orderDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

// Frontend column order: 0=sl, 1=employee_cell, 2=leave_type, 3=addition, 4=note, 5=submit_date, 6=attachment, 7=status
$columnsMap = [
    0 => 'leave_addition_history.dataID',
    1 => 'employee_list.employee_name',
    2 => 'leave_addition_history.leaveID',
    3 => 'leave_addition_history.leaveAddition',
    4 => 'leave_addition_history.note',
    5 => 'leave_addition_history.createDate',
    6 => 'leave_addition_history.dataID',
    7 => 'leave_addition_history.isApproved',
];
$orderColumn = $columnsMap[$orderColumnIndex] ?? 'leave_addition_history.createDate';

// Resolve viewer org
$orgStmt = $con->prepare("SELECT isCenterAdmin, organization_id, employee_id FROM user_list WHERE user_id = ?");
$orgStmt->bind_param("s", $_SESSION['username']);
$orgStmt->execute();
$orgUserRow = $orgStmt->get_result()->fetch_assoc();
$orgStmt->close();
if (!empty($orgUserRow['isCenterAdmin'])) {
    $userOrgID = (int)$orgUserRow['organization_id'];
} elseif (!empty($orgUserRow['employee_id'])) {
    $empOrgStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $empOrgStmt->bind_param("i", $orgUserRow['employee_id']);
    $empOrgStmt->execute();
    $userOrgID = (int)($empOrgStmt->get_result()->fetch_assoc()['organization_id'] ?? 0);
    $empOrgStmt->close();
} else {
    $userOrgID = 0;
}

// Filter params
$centerFilter    = (int)($_POST['centerFilter']    ?? 0);
$sectionFilter   = (int)($_POST['sectionFilter']   ?? 0);
$employeeFilter  = (int)($_POST['employeeFilter']  ?? 0);
$leaveTypeFilter = (int)($_POST['leaveTypeFilter'] ?? 0);
$statusFilter    = $_POST['statusFilter'] ?? '';
$dateFromF       = trim($_POST['dateFrom'] ?? '');
$dateToF         = trim($_POST['dateTo']   ?? '');

$filterClause = '';
if ($userOrgID === 0 && $centerFilter > 0) $filterClause .= " AND employee_list.organization_id = $centerFilter";
if ($sectionFilter   > 0) $filterClause .= " AND employee_list.section_id = $sectionFilter";
if ($employeeFilter  > 0) $filterClause .= " AND employee_list.id = $employeeFilter";
if ($leaveTypeFilter > 0) $filterClause .= " AND leave_addition_history.leaveID = $leaveTypeFilter";
if ($statusFilter !== '' && in_array($statusFilter, ['0','1','2'], true)) {
    $filterClause .= " AND leave_addition_history.isApproved = $statusFilter";
}
if ($dateFromF !== '' && $dateToF !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromF) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToF)) {
    $filterClause .= " AND leave_addition_history.createDate BETWEEN '$dateFromF' AND '$dateToF'";
}

$orgWhere = $userOrgID > 0 ? "employee_list.organization_id = $userOrgID" : "1=1";

$baseFrom = "FROM leave_addition_history
             INNER JOIN employee_list ON leave_addition_history.employeeID = employee_list.id
             LEFT JOIN job_title ON employee_list.designation = job_title.id
             LEFT JOIN sections s ON employee_list.section_id = s.id
             LEFT JOIN organization o ON employee_list.organization_id = o.id
             WHERE $orgWhere
             $filterClause";

// Total records
$totalResult = mysqli_query($con, "SELECT COUNT(*) AS total $baseFrom");
$totalRecords = (int)(mysqli_fetch_assoc($totalResult)['total'] ?? 0);

// Search
$searchSql = '';
if (!empty($searchValue)) {
    $sv = mysqli_real_escape_string($con, $searchValue);
    $searchSql = " AND (
        employee_list.employee_name LIKE '%$sv%' OR
        employee_list.employee_id LIKE '%$sv%' OR
        leave_addition_history.note LIKE '%$sv%' OR
        leave_addition_history.createDate LIKE '%$sv%'
    )";
    $filteredResult = mysqli_query($con, "SELECT COUNT(*) AS total $baseFrom $searchSql");
    $filteredRecords = (int)(mysqli_fetch_assoc($filteredResult)['total'] ?? 0);
} else {
    $filteredRecords = $totalRecords;
}

$selectFields = "leave_addition_history.dataID,
                 leave_addition_history.leaveID,
                 leave_addition_history.leaveAddition,
                 leave_addition_history.note,
                 leave_addition_history.createDate,
                 leave_addition_history.attachment,
                 leave_addition_history.isApproved,
                 leave_addition_history.approvedDate,
                 leave_addition_history.rejectedDate,
                 leave_addition_history.rejectionReason,
                 employee_list.employee_name,
                 employee_list.employee_id AS emp_code,
                 employee_list.photo,
                 job_title.job_title_name,
                 s.section_name,
                 o.organization_name,
                 (SELECT CONCAT_WS('|', COALESCE(ael.employee_name, aul.full_name, aul.user_id), aul.user_id)
                  FROM user_list aul LEFT JOIN employee_list ael ON aul.employee_id = ael.id
                  WHERE aul.dataID = leave_addition_history.approvedBy LIMIT 1) AS approved_by_label,
                 (SELECT CONCAT_WS('|', COALESCE(rel.employee_name, rul.full_name, rul.user_id), rul.user_id)
                  FROM user_list rul LEFT JOIN employee_list rel ON rul.employee_id = rel.id
                  WHERE rul.dataID = leave_addition_history.rejectedBy LIMIT 1) AS rejected_by_label";

$dataQuery = "SELECT $selectFields $baseFrom $searchSql ORDER BY $orderColumn $orderDir LIMIT $start, $length";
$result = mysqli_query($con, $dataQuery);

if (!$result) {
    echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Query error"]);
    exit;
}

$leaveTypeMap = [
    1  => ['গড় বেতন',                  'leave-type-primary'],
    2  => ['অর্ধ-গড় বেতন',             'leave-type-info'],
    3  => ['নৈমিত্তিক (Casual)',        'leave-type-success'],
    4  => ['অসাধারণ (বিনা বেতনে)',     'leave-type-warning'],
    5  => ['ঐচ্ছিক ছুটি',                'leave-type-purple'],
    6  => ['কর্তনহীন ছুটি',              'leave-type-default'],
    10 => ['অসাধারণ ছুটি',              'leave-type-warning'],
];

$data = [];
$sl = $start;

while ($row = mysqli_fetch_assoc($result)) {
    $sl++;

    // Avatar + name cell
    $empName  = trim($row['employee_name'] ?? '');
    $empJob   = trim($row['job_title_name'] ?? '');
    $empSec   = trim($row['section_name'] ?? '');
    $empOrg   = trim($row['organization_name'] ?? '');
    $empPhoto = trim($row['photo'] ?? '');
    $empCode  = trim($row['emp_code'] ?? '');
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
    $empSubLight = trim($empSec . ($empSec && $empOrg ? ' • ' : '') . $empOrg);
    $employeeCell = '<div class="emp-cell">' . $avatarHtml
                  . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName) . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                  . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                  . ($empSubLight ? '<div class="emp-sub-light">' . htmlspecialchars($empSubLight) . '</div>' : '')
                  . '</div></div>';

    // Leave type tag
    $ltPair = $leaveTypeMap[$row['leaveID']] ?? ['—', 'leave-type-default'];
    $leaveTypeHtml = $ltPair[0] !== '—'
        ? '<span class="leave-type-tag ' . $ltPair[1] . '">' . htmlspecialchars($ltPair[0]) . '</span>'
        : '<span class="text-muted small">—</span>';

    // Addition days (success — positive)
    $addHtml = '<span class="days-pill days-pill-success">+' . banglaNumber((int)$row['leaveAddition']) . ' দিন</span>';

    // Note
    $noteHtml = !empty(trim($row['note'] ?? ''))
        ? '<div class="note-cell"><i class="ti tabler-message-2 text-muted me-1"></i>' . htmlspecialchars($row['note']) . '</div>'
        : '<span class="text-muted small">—</span>';

    // Submit date
    $dateHtml = '<span class="text-muted small">—</span>';
    if (!empty($row['createDate']) && $row['createDate'] !== '0000-00-00') {
        $sd = date_create($row['createDate']);
        if ($sd) {
            $dateHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date_format($sd, "d/m/Y")) . '</span></div>';
        }
    }

    // Attachment
    $attHtml = !empty($row['attachment'])
        ? '<a href="../../uploads/' . htmlspecialchars($row['attachment']) . '" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="ti tabler-eye me-1"></i>দেখুন</a>'
        : '<span class="text-muted small">—</span>';

    // Status pill + audit footer (who/when, plus reject reason)
    $statusMap = [
        0 => '<span class="status-pill status-pending"><i class="ti tabler-hourglass me-1"></i>অপেক্ষমান</span>',
        1 => '<span class="status-pill status-approved"><i class="ti tabler-check me-1"></i>অনুমোদিত</span>',
        2 => '<span class="status-pill status-rejected"><i class="ti tabler-x me-1"></i>বাতিল</span>',
    ];
    $statusHtml = $statusMap[$row['isApproved']] ?? '<span class="text-muted small">—</span>';

    $auditFooter = '';
    if ((int)$row['isApproved'] === 1) {
        $byParts = explode('|', $row['approved_by_label'] ?? '');
        $byName  = trim($byParts[0] ?? '') ?: '—';
        $when    = !empty($row['approvedDate']) ? date('d/m/Y H:i', strtotime($row['approvedDate'])) : '';
        $auditFooter = '<div class="audit-footnote text-muted mt-1" style="font-size:0.7rem;">'
                     . '<i class="ti tabler-user-check me-1"></i>' . htmlspecialchars($byName)
                     . ($when ? ' · ' . $when : '')
                     . '</div>';
    } elseif ((int)$row['isApproved'] === 2) {
        $byParts = explode('|', $row['rejected_by_label'] ?? '');
        $byName  = trim($byParts[0] ?? '') ?: '—';
        $when    = !empty($row['rejectedDate']) ? date('d/m/Y H:i', strtotime($row['rejectedDate'])) : '';
        $reason  = trim($row['rejectionReason'] ?? '');
        $auditFooter = '<div class="audit-footnote text-muted mt-1" style="font-size:0.7rem;">'
                     . '<i class="ti tabler-user-x me-1"></i>' . htmlspecialchars($byName)
                     . ($when ? ' · ' . $when : '')
                     . '</div>';
        if ($reason !== '') {
            $auditFooter .= '<div class="reject-reason mt-1" style="font-size:0.75rem;color:#991b1b;background:#fee2e2;border-radius:4px;padding:3px 6px;max-width:260px;">'
                         . '<i class="ti tabler-message-2-x me-1"></i>' . nl2br(htmlspecialchars($reason))
                         . '</div>';
        }
    }
    $statusHtml .= $auditFooter;

    $data[] = [
        "sl"            => '<span class="serial-num">' . $sl . '</span>',
        "employee_cell" => $employeeCell,
        "leave_type"    => $leaveTypeHtml,
        "addition"      => $addHtml,
        "note"          => $noteHtml,
        "submit_date"   => $dateHtml,
        "attachment"    => $attHtml,
        "status"        => $statusHtml,
    ];
}

echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data"            => $data
]);
mysqli_close($con);
