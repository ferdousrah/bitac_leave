<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../config/connection.php');
ob_end_clean();

// ── Resolve viewer scope ──────────────────────────────────────────────
// Super Admin (user_group_id=1) can see all centers; everyone else is
// scoped to their own center via employee_list / centerAdminOrgID.
$meStmt = $con->prepare("SELECT dataID, user_group_id, employee_id, organization_id FROM user_list WHERE user_id = ? LIMIT 1");
$meStmt->bind_param("s", $_SESSION['username']);
$meStmt->execute();
$meRow = $meStmt->get_result()->fetch_assoc();
$meStmt->close();
$viewerGroupId = (int)($meRow['user_group_id'] ?? 0);
$isSuperAdminViewer = ($viewerGroupId === 1);

if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $viewerOrgID = (int)$_SESSION['centerAdminOrgID'];
} else {
    $empID = (int)($_SESSION['employeeID'] ?? 0);
    $stmt_org = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $stmt_org->bind_param("i", $empID);
    $stmt_org->execute();
    $orgRow = $stmt_org->get_result()->fetch_assoc();
    $stmt_org->close();
    $viewerOrgID = (int)($orgRow['organization_id'] ?? 0);
}

// ── DataTables params ─────────────────────────────────────────────────
$limit  = isset($_POST['length']) ? intval($_POST['length']) : 10;
$start  = isset($_POST['start'])  ? intval($_POST['start'])  : 0;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

$orderColumn    = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDirection = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

// Column index → SQL column. Aligned with the front-end columns array on
// views/users/manage.php (sl, full_name, designation, center, email, user_id, action).
// Use COALESCE so sorting matches what's actually displayed — display prefers
// live employee_list values over the user_list copies.
$columns = [
    1 => 'COALESCE(el.employee_name, ul.full_name)',
    2 => 'COALESCE(jt.job_title_name, ul.designation)',
    3 => 'o.organization_name',
    4 => 'COALESCE(el.email, ul.email)',
    5 => 'ul.user_id',
];
$orderBy = $columns[$orderColumn] ?? 'ul.full_name';

// Center filter from the new dropdown on the page (0 = no filter / all centers)
$centerFilter = (int)($_POST['centerFilter'] ?? 0);

// ── Org scope ─────────────────────────────────────────────────────────
// Non-super-admin viewers can never break out of their own center even if the
// front-end sent a different centerFilter. Super Admin honours centerFilter freely.
$effectiveOrgID = 0;
if ($isSuperAdminViewer) {
    $effectiveOrgID = ($centerFilter > 0) ? $centerFilter : 0; // 0 = all centers
} else {
    $effectiveOrgID = ($centerFilter > 0 && $centerFilter === $viewerOrgID)
        ? $centerFilter
        : $viewerOrgID;
}

$baseFrom = "FROM user_list ul
             LEFT JOIN employee_list el ON ul.employee_id = el.id
             LEFT JOIN organization o   ON el.organization_id = o.id
             WHERE (ul.isCenterAdmin IS NULL OR ul.isCenterAdmin = 0)";
if ($effectiveOrgID > 0) {
    $baseFrom .= " AND el.organization_id = " . (int)$effectiveOrgID;
}

// Total records (in scope, before text search)
$totalRecordsQuery = mysqli_query($con, "SELECT COUNT(*) AS total $baseFrom");
$totalRecords = (int)(mysqli_fetch_assoc($totalRecordsQuery)['total'] ?? 0);

// ── Search clause ─────────────────────────────────────────────────────
// Free-text search across user fields + employee fields (employee_id + name).
$searchClause = "";
$params       = [];
$types        = "";
if (!empty($search)) {
    $searchClause = " AND (ul.full_name LIKE ?
                       OR ul.designation LIKE ?
                       OR ul.email LIKE ?
                       OR ul.user_id LIKE ?
                       OR el.employee_id LIKE ?
                       OR el.employee_name LIKE ?
                       OR o.organization_name LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term, $term, $term, $term];
    $types  = "sssssss";
}

// Filtered records count
if (!empty($search)) {
    $countSql  = "SELECT COUNT(*) AS total $baseFrom" . $searchClause;
    $countStmt = mysqli_prepare($con, $countSql);
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
    mysqli_stmt_execute($countStmt);
    $filteredRecords = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
    mysqli_stmt_close($countStmt);
} else {
    $filteredRecords = $totalRecords;
}

// ── Main query ────────────────────────────────────────────────────────
// Pull live values from employee_list (employee_name, email, mobileNo) +
// job_title_name. The cells below prefer these over the user_list copies so
// that updates in employee_list flow through to display without re-saving.
$sql = "SELECT ul.*,
               el.photo         AS employee_photo,
               el.employee_id   AS emp_code,
               el.employee_name AS emp_name,
               el.email         AS emp_email,
               el.mobileNo      AS emp_mobile,
               jt.job_title_name AS emp_designation,
               o.organization_name
        FROM user_list ul
        LEFT JOIN employee_list el ON ul.employee_id = el.id
        LEFT JOIN organization o   ON el.organization_id = o.id
        LEFT JOIN job_title jt     ON el.designation = jt.id
        WHERE (ul.isCenterAdmin IS NULL OR ul.isCenterAdmin = 0)"
       . ($effectiveOrgID > 0 ? " AND el.organization_id = " . (int)$effectiveOrgID : "")
       . $searchClause
       . " ORDER BY {$orderBy} {$orderDirection} LIMIT ?, ?";

$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[],'error'=>'Database query error']);
    exit;
}
if (!empty($search)) {
    $allTypes  = $types . "ii";
    $allParams = array_merge($params, [$start, $limit]);
    mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $start, $limit);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
$sl   = $start + 1;
$menuslug = isset($_GET['menuslug']) ? htmlspecialchars($_GET['menuslug']) : '';

while ($row = mysqli_fetch_assoc($result)) {
    $dataID   = (int)$row['dataID'];
    // Prefer live employee_list values; fall back to user_list copies (super
    // admin / legacy center admin users that have no employee record).
    $fullName    = trim(($row['emp_name'] ?? '') !== '' ? $row['emp_name'] : ($row['full_name'] ?? ''));
    $designation = trim(($row['emp_designation'] ?? '') !== '' ? $row['emp_designation'] : ($row['designation'] ?? ''));
    $email       = trim(($row['emp_email'] ?? '') !== '' ? $row['emp_email'] : ($row['email'] ?? ''));
    $empPhoto    = trim($row['employee_photo'] ?? '');
    $empCode     = trim($row['emp_code'] ?? '');

    // Avatar initials
    $initials = mb_substr($fullName, 0, 1, 'UTF-8');
    $parts = preg_split('/\s+/u', $fullName);
    if (count($parts) > 1) {
        $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
    }
    if (!empty($empPhoto)) {
        $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
        $avatarHtml = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
    } else {
        $avatarHtml = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
    }
    $nameCell = '<div class="emp-cell">' . $avatarHtml
              . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($fullName) . '</div>'
              . ($empCode ? '<div class="emp-sub-light"><i class="ti tabler-id me-1"></i>' . htmlspecialchars($empCode) . '</div>' : '')
              . '</div></div>';

    $designationHtml = !empty($designation)
        ? '<span class="leave-type-chip">' . htmlspecialchars($designation) . '</span>'
        : '<span class="text-muted small">—</span>';

    $centerHtml = !empty($row['organization_name'])
        ? '<span class="meta-chip center"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($row['organization_name']) . '</span>'
        : '<span class="text-muted small">—</span>';

    $emailHtml = !empty($email)
        ? '<a href="mailto:' . htmlspecialchars($email) . '" class="email-link"><i class="ti tabler-mail me-1"></i>' . htmlspecialchars($email) . '</a>'
        : '<span class="text-muted small">—</span>';

    $userIdHtml = !empty($row['user_id'])
        ? '<span class="user-code"><i class="ti tabler-at me-1"></i>' . htmlspecialchars($row['user_id']) . '</span>'
        : '<span class="text-muted small">—</span>';

    $action = '<div class="action-group">'
            . '<a class="action-icon icon-view" data-turbo="true" data-bs-toggle="tooltip" data-bs-placement="top" title="সম্পাদনা" href="../../views/users/edit.php?dataID=' . $dataID . '&menuslug=' . $menuslug . '"><i class="ti tabler-edit"></i></a>'
            . '<button type="button" class="action-icon icon-reject" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন" onclick="removeData(' . $sl . ',' . $dataID . ')"><i class="ti tabler-trash"></i></button>'
            . '</div>';

    $data[] = [
        "row_check"   => '<input type="checkbox" class="form-check-input user-row-check" value="' . $dataID . '">',
        "sl"          => '<span class="serial-num">' . $sl . '</span>',
        "full_name"   => $nameCell,
        "designation" => $designationHtml,
        "center"      => $centerHtml,
        "email"       => $emailHtml,
        "user_id"     => $userIdHtml,
        "action"      => $action,
    ];
    $sl++;
}
mysqli_stmt_close($stmt);

echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data"            => $data,
]);
