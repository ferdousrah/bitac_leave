<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once(__DIR__ . '/../../config/connection.php');

// Detect Super Admin (group_id=1) — can create users for any center's employee
$_actorStmt = $con->prepare("SELECT user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
$_actorStmt->bind_param('s', $_un);
$_actorStmt->execute();
$_actorRow = $_actorStmt->get_result()->fetch_assoc() ?: [];
$_actorStmt->close();
$_isSuperAdmin = ((int)($_actorRow['user_group_id'] ?? 0) === 1);

// Resolve organization_id for current user (for own-center gate)
if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = intval($_SESSION['centerAdminOrgID']);
} else {
    $empID = intval($_SESSION['employeeID'] ?? 0);
    $stmt_org = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $stmt_org->bind_param("i", $empID);
    $stmt_org->execute();
    $orgRow = $stmt_org->get_result()->fetch_assoc();
    $stmt_org->close();
    $orgID = intval($orgRow['organization_id'] ?? 0);
}

$employee_id  = intval($_POST['employeeID'] ?? 0);
$user_id      = trim($_POST['user_id'] ?? '');
$password_raw = $_POST['password'] ?? '';

// Multi-role: array of assigned group IDs + the default (active) group.
$group_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['user_group_ids'] ?? [])))));
$default_group_id = intval($_POST['default_group_id'] ?? 0);

if (empty($employee_id) || empty($user_id) || empty($password_raw) || empty($group_ids) || $default_group_id <= 0) {
    echo 0;
    exit;
}

// Pull display fields from employee_list — single source of truth. We still
// copy them into user_list at save time to keep existing read sites working,
// but a stale-data nightly sync (or read-time JOIN) can refresh later.
$emp_info_stmt = $con->prepare(
    "SELECT el.employee_name, el.email, el.mobileNo, jt.job_title_name
     FROM employee_list el
     LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE el.id = ? LIMIT 1"
);
$emp_info_stmt->bind_param("i", $employee_id);
$emp_info_stmt->execute();
$emp_info = $emp_info_stmt->get_result()->fetch_assoc();
$emp_info_stmt->close();
if (!$emp_info) {
    echo 0;
    exit;
}
$full_name   = $emp_info['employee_name'] ?? '';
$designation = $emp_info['job_title_name'] ?? '';
$email       = $emp_info['email']         ?? '';
$mobile      = $emp_info['mobileNo']      ?? '';

// Reserved roles (Super Admin, Center Admin, Regional Super/Op Admin) are
// granted via the role-approval workflow — never directly via this form.
// Reject the request if any reserved IDs slipped through.
$RESERVED_ROLES = [1, 2, 7, 8];
foreach ($group_ids as $gid) {
    if (in_array($gid, $RESERVED_ROLES, true)) {
        echo 0;
        exit;
    }
}

// Default must be one of the assigned groups
if (!in_array($default_group_id, $group_ids, true)) {
    echo 0;
    exit;
}

// Validate group IDs exist and aren't deleted
$placeholders = implode(',', array_fill(0, count($group_ids), '?'));
$stmt_g = $con->prepare("SELECT id FROM user_group WHERE deleted = 0 AND id IN ($placeholders)");
$stmt_g->bind_param(str_repeat('i', count($group_ids)), ...$group_ids);
$stmt_g->execute();
$validIds = array_column($stmt_g->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
$stmt_g->close();
if (count($validIds) !== count($group_ids)) {
    echo 0;
    exit;
}

// Validate employee. Super Admin can pick any center's employee; others restricted to own org.
if ($_isSuperAdmin) {
    $stmt_emp = $con->prepare("SELECT id, organization_id FROM employee_list WHERE id = ? AND employment_status = 1");
    $stmt_emp->bind_param("i", $employee_id);
} else {
    $stmt_emp = $con->prepare("SELECT id, organization_id FROM employee_list WHERE id = ? AND organization_id = ? AND employment_status = 1");
    $stmt_emp->bind_param("ii", $employee_id, $orgID);
}
$stmt_emp->execute();
$empCheck = $stmt_emp->get_result()->fetch_assoc();
$stmt_emp->close();

if (!$empCheck) {
    echo 0;
    exit;
}

// Use the employee's actual organization for the new user record (not the actor's)
$userOrgID = (int)$empCheck['organization_id'];

// Check for duplicate username
$stmt_dup = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ?");
$stmt_dup->bind_param("s", $user_id);
$stmt_dup->execute();
$dupRow = $stmt_dup->get_result()->fetch_assoc();
$stmt_dup->close();

if ($dupRow) {
    echo "User Already Exist!";
    exit;
}

$password = password_hash($password_raw, PASSWORD_DEFAULT);

// user_group_id on user_list = active (default) group — keeps existing
// permission-lookup code working unchanged. user_group_assignment holds the full set.
$stmt = $con->prepare("INSERT INTO user_list (full_name, employee_id, designation, email, mobile, user_id, password, user_type, user_group_id)
                       VALUES (?, ?, ?, ?, ?, ?, ?, '2', ?)");
$stmt->bind_param("sisssssi", $full_name, $employee_id, $designation, $email, $mobile, $user_id, $password, $default_group_id);

if (!$stmt->execute()) {
    $stmt->close();
    echo 0;
    exit;
}
$newID = $stmt->insert_id;
$stmt->close();

// Insert all assignments
$assignStmt = $con->prepare("INSERT INTO user_group_assignment (user_id, group_id, is_default) VALUES (?, ?, ?)");
foreach ($group_ids as $gid) {
    $isDefault = ($gid === $default_group_id) ? 1 : 0;
    $assignStmt->bind_param("iii", $newID, $gid, $isDefault);
    $assignStmt->execute();
}
$assignStmt->close();

// Audit
if (function_exists('audit_log')) {
    audit_log('user_created', [
        'target_type'    => 'user',
        'target_id'      => $newID,
        'organization_id'=> $userOrgID,
        'note'           => 'username=' . $user_id . '; groups=' . implode(',', $group_ids) . '; default=' . $default_group_id
                            . ($_isSuperAdmin && $userOrgID !== $orgID ? '; cross_center=true' : ''),
    ]);
}

echo $newID;
?>
