<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'অননুমোদিত']);
    exit;
}

$employeeID = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($employeeID <= 0) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ কর্মচারী']);
    exit;
}

// Resolve the target employee's org
$empQ = mysqli_prepare($con,
    "SELECT id, organization_id, employee_name FROM employee_list
     WHERE id = ? AND employment_status = 1 AND pending_section_assignment = 0
     LIMIT 1");
mysqli_stmt_bind_param($empQ, 'i', $employeeID);
mysqli_stmt_execute($empQ);
$emp = mysqli_fetch_assoc(mysqli_stmt_get_result($empQ));
mysqli_stmt_close($empQ);
if (!$emp) {
    echo json_encode(['status' => 0, 'message' => 'কর্মচারী পাওয়া যায়নি']);
    exit;
}
$empOrg = (int)$emp['organization_id'];

// Org's designated signatory (from leave_edit_approval_signatory)
$defaultSig = null;
$defaultSigConflict = false;
$sigQ = mysqli_prepare($con,
    "SELECT s.employeeID, el.employee_name, el.employee_id AS emp_code, jt.job_title_name
     FROM leave_edit_approval_signatory s
     LEFT JOIN employee_list el ON s.employeeID = el.id
     LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE s.organization_id = ?
     LIMIT 1");
mysqli_stmt_bind_param($sigQ, 'i', $empOrg);
mysqli_stmt_execute($sigQ);
$sigRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sigQ));
mysqli_stmt_close($sigQ);
if ($sigRow) {
    $defaultSig = [
        'id'    => (int)$sigRow['employeeID'],
        'name'  => $sigRow['employee_name'],
        'code'  => $sigRow['emp_code'],
        'title' => $sigRow['job_title_name'],
    ];
    $defaultSigConflict = ((int)$sigRow['employeeID'] === $employeeID);
}

// Same-org active employees (dropdown options), excluding the target employee themselves
$empListQ = mysqli_prepare($con,
    "SELECT el.id, el.employee_name, el.employee_id AS emp_code, jt.job_title_name
     FROM employee_list el
     LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE el.organization_id = ?
       AND el.employment_status = 1
       AND el.pending_section_assignment = 0
       AND el.id <> ?
     ORDER BY el.employee_name ASC");
mysqli_stmt_bind_param($empListQ, 'ii', $empOrg, $employeeID);
mysqli_stmt_execute($empListQ);
$listRes = mysqli_stmt_get_result($empListQ);
$empList = [];
while ($r = mysqli_fetch_assoc($listRes)) {
    $empList[] = [
        'id'    => (int)$r['id'],
        'name'  => $r['employee_name'],
        'code'  => $r['emp_code'],
        'title' => $r['job_title_name'],
    ];
}
mysqli_stmt_close($empListQ);

echo json_encode([
    'status'                => 1,
    'employee_org_id'       => $empOrg,
    'default_signatory'     => $defaultSig,
    'default_sig_conflict'  => $defaultSigConflict,
    'org_employees'         => $empList,
]);

mysqli_close($con);
