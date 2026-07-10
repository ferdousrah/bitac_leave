<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: application/json');

// Actor scope — only Super Admin or HQ users can initiate transfers, so only they need this picker
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

$isSuperAdmin = ((int)($actor['user_group_id'] ?? 0) === 1);
$myCenterID   = (int)($actor['emp_org'] ?? 0);
$canWrite     = ($isSuperAdmin || $myCenterID === 4);

if (!$canWrite) {
    echo json_encode(['items' => []]);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$qEsc = mysqli_real_escape_string($con, $q);

// Empty query → return first 50 active employees (so dropdown shows a list on open).
// Non-empty → filter by name/ID.
$searchClause = ($q === '')
    ? ''
    : " AND (e.employee_name LIKE '%$qEsc%' OR e.employee_id LIKE '%$qEsc%')";
$limit = ($q === '') ? 50 : 25;

$sql = "
    SELECT e.id, e.employee_name, e.employee_id, e.organization_id,
           o.organization_name, jt.job_title_name
    FROM employee_list e
    LEFT JOIN organization o ON o.id = e.organization_id
    LEFT JOIN job_title jt ON jt.id = e.designation
    WHERE e.employment_status = 1
      AND e.pending_section_assignment = 0
      $searchClause
    ORDER BY e.employee_name ASC
    LIMIT $limit
";

$result = mysqli_query($con, $sql);
$items = [];
if ($result) {
    while ($r = mysqli_fetch_assoc($result)) {
        $name = trim($r['employee_name'] ?? '');
        $code = trim((string)($r['employee_id'] ?? ''));
        $job  = trim($r['job_title_name'] ?? '');
        $org  = trim($r['organization_name'] ?? '');
        $label = $name . ($code ? " ($code)" : '') . ($job ? " — $job" : '') . ($org ? " @ $org" : '');
        $items[] = [
            'id'               => (int)$r['id'],
            'label'            => $label,
            'current_org_id'   => (int)$r['organization_id'],
            'current_org_name' => $org,
        ];
    }
}

echo json_encode(['items' => $items]);
mysqli_close($con);
