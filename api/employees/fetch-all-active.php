<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Org gate — Super Admin sees all centers; others restricted to own center
$_actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actor = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);
$_isSuperAdmin  = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID    = (int)($_actor['emp_org'] ?? 0);
$_seeAllCenters = ($_isSuperAdmin || $_myCenterID === 4); // HQ = id 4

// Constants for columns to avoid hardcoding
define('COLUMNS', ['employee_name', 'employee_id', 'section_name', 'organization_name']);

// Get request parameters with sanitization
$limit = isset($_POST['length']) ? (int) $_POST['length'] : 10;  // Number of records per page
$start = isset($_POST['start']) ? (int) $_POST['start'] : 0;    // Offset for pagination
$search = isset($_POST['search']['value']) ? mysqli_real_escape_string($con, $_POST['search']['value']) : ''; // Global search filter
$centerId = isset($_POST['center_id']) ? (int) $_POST['center_id'] : 0; // Center/Organization filter

// Restricted users (not Super Admin, not HQ): force centerId to own center regardless of client input
if (!$_seeAllCenters) {
    $centerId = $_myCenterID;
}

// Get column-specific search values
$searchName = isset($_POST['columns'][2]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][2]['search']['value']) : '';
$searchID = isset($_POST['columns'][3]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][3]['search']['value']) : '';
$searchCenter = isset($_POST['columns'][4]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][4]['search']['value']) : '';
$searchSection = isset($_POST['columns'][5]['search']['value']) ? mysqli_real_escape_string($con, $_POST['columns'][5]['search']['value']) : '';

$orderColumn = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;  // Get the column to order by
$orderDirection = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';  // Get the direction (asc/desc)

// SQL Query with JOIN to fetch employee data along with related information
$sql = "
    SELECT e.*, jt.job_title_name, s.section_name, o.organization_name
    FROM employee_list e
    LEFT JOIN job_title jt ON jt.id = e.designation
    LEFT JOIN sections s ON s.id = e.section_id
    LEFT JOIN organization o ON o.id = e.organization_id
    WHERE e.employment_status = 1 AND e.pending_section_assignment = 0
";

// Apply center filter if provided
if ($centerId > 0) {
    $sql .= " AND e.organization_id = " . $centerId;
}

// Apply global search filter if available (for main search box)
if ($search) {
    $sql .= " AND (e.employee_name LIKE '%" . $search . "%' OR e.employee_id LIKE '%" . $search . "%' OR s.section_name LIKE '%" . $search . "%' OR o.organization_name LIKE '%" . $search . "%')";
}

// Apply column-specific search filters
$columnSearchConditions = [];
if ($searchName) {
    $columnSearchConditions[] = "e.employee_name LIKE '%" . $searchName . "%'";
}
if ($searchID) {
    $columnSearchConditions[] = "e.employee_id LIKE '%" . $searchID . "%'";
}
if ($searchCenter) {
    $columnSearchConditions[] = "o.organization_name LIKE '%" . $searchCenter . "%'";
}
if ($searchSection) {
    $columnSearchConditions[] = "s.section_name LIKE '%" . $searchSection . "%'";
}

if (!empty($columnSearchConditions)) {
    $sql .= " AND (" . implode(" AND ", $columnSearchConditions) . ")";
}

// Add sorting and pagination
$sql .= " ORDER BY e.display_order ASC LIMIT " . $start . ", " . $limit;

// Execute the query directly (no prepared statement needed since we escaped the values)
$result = mysqli_query($con, $sql);

// Check for query error
if (!$result) {
    echo json_encode([
        "draw" => isset($_POST['draw']) ? $_POST['draw'] : 0,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => mysqli_error($con)
    ]);
    exit;
}

// Fetch total records count for pagination (with same filters)
$totalRecordsSql = "SELECT COUNT(*) FROM employee_list e
    LEFT JOIN job_title jt ON jt.id = e.designation
    LEFT JOIN sections s ON s.id = e.section_id
    LEFT JOIN organization o ON o.id = e.organization_id
    WHERE e.employment_status = 1 AND e.pending_section_assignment = 0";

if ($centerId > 0) {
    $totalRecordsSql .= " AND e.organization_id = " . $centerId;
}

// Apply the same search filters to count query
if ($search) {
    $totalRecordsSql .= " AND (e.employee_name LIKE '%" . $search . "%' OR e.employee_id LIKE '%" . $search . "%' OR s.section_name LIKE '%" . $search . "%' OR o.organization_name LIKE '%" . $search . "%')";
}

if (!empty($columnSearchConditions)) {
    $totalRecordsSql .= " AND (" . implode(" AND ", $columnSearchConditions) . ")";
}

$totalRecordsQuery = mysqli_query($con, $totalRecordsSql);
$totalRecords = mysqli_fetch_row($totalRecordsQuery)[0];

$data = [];
$sl = $start + 1;

// Process the results
while ($row = mysqli_fetch_array($result)) {
    // Ensure ID is always an integer
    $empId = (int)($row['id'] ?? 0);

    // Avatar + name + (id) + designation — combined into a single "কর্মচারী" cell
    $empName  = trim($row['employee_name'] ?? '');
    $empJob   = trim($row['job_title_name'] ?? '');
    $empPhoto = trim($row['photo'] ?? '');
    $empCode  = trim((string)($row['employee_id'] ?? ''));
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
    $detailUrl = 'details?menuslug=manage-employee&employeeID=' . base64_encode($empId);
    $employeeCell = '<div class="emp-cell">' . $avatarHtml
                  . '<div class="emp-meta"><div class="emp-name"><a href="' . $detailUrl . '" class="text-heading">' . htmlspecialchars($empName) . '</a>' . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                  . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                  . '</div></div>';

    $orgName = trim($row['organization_name'] ?? '');
    $secName = trim($row['section_name'] ?? '');
    $orgChip = $orgName ? '<span class="meta-chip center"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($orgName) . '</span>' : '<span class="text-muted small">—</span>';
    $secChip = $secName ? '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($secName) . '</span>' : '<span class="text-muted small">—</span>';

    $action = '<div class="action-group">'
            . '<a href="../../views/employees/edit.php?dataID=' . base64_encode($empId) . '&menuslug=manage-employee" class="action-icon icon-view" data-bs-toggle="tooltip" data-bs-placement="top" title="সম্পাদনা"><i class="ti tabler-edit"></i></a>'
            . '<a href="../../views/employees/previous-leave-form.php?dataID=' . base64_encode($empId) . '&menuslug=manage-employee" class="action-icon icon-attach" data-bs-toggle="tooltip" data-bs-placement="top" title="পূর্ববর্তী ছুটি"><i class="ti tabler-history"></i></a>'
            . '<button onclick="removeData(' . $sl . ',' . $empId . ')" class="action-icon icon-reject" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন"><i class="ti tabler-trash"></i></button>'
            . '</div>';

    $data[] = [
        "sl"                => '<span class="serial-num">' . $sl . '</span>',
        "employee_cell"     => $employeeCell,
        "employee_name"     => $empName,           // for column search
        "employee_id"       => bn2enNumber((string)($row['employee_id'] ?? '')),  // for column search
        "organization_name" => $orgChip,
        "organization_name_raw" => $orgName,        // for column search
        "section_name"      => $secChip,
        "section_name_raw"  => $secName,            // for column search
        "action"            => $action,
    ];
    $sl++;
}

// Send response in JSON format
$response = [
    "draw" => $_POST['draw'],
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data" => $data
];

// Output the response
echo json_encode($response);

// Close connection
mysqli_close($con);
?>
