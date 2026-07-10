<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once(__DIR__ . '/../../config/connection.php');

// Resolve organization_id for current user
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

// Privileged actors (Super Admin OR HQ org=4) bypass cross-center org checks,
// matching the gate on views/employees/manage.php and the fetch APIs.
$actorGroupStmt = $con->prepare("SELECT user_group_id FROM user_list WHERE dataID = ?");
$_uidForCheck = intval($_SESSION['userID'] ?? 0);
$actorGroupStmt->bind_param("i", $_uidForCheck);
$actorGroupStmt->execute();
$actorGroupRow = $actorGroupStmt->get_result()->fetch_assoc();
$actorGroupStmt->close();
$_actorIsSuperAdmin = ((int)($actorGroupRow['user_group_id'] ?? 0) === 1);
$_actorIsHQ         = ($orgID === 4);
$_seeAllCenters     = ($_actorIsSuperAdmin || $_actorIsHQ);

$dataID       = intval($_POST['dataID'] ?? 0);
$employee_id  = intval($_POST['employeeID'] ?? 0);
$user_id      = trim($_POST['user_id'] ?? '');

// dashboardType field is no longer on the edit form; preserve the existing
// value so the UPDATE doesn't zero it out.
$dashStmt = $con->prepare("SELECT dashboardType FROM user_list WHERE dataID = ?");
$dashStmt->bind_param("i", $dataID);
$dashStmt->execute();
$dashRow = $dashStmt->get_result()->fetch_assoc();
$dashStmt->close();
$dashboardType = isset($_POST['dashboardType']) && $_POST['dashboardType'] !== ''
    ? intval($_POST['dashboardType'])
    : (int)($dashRow['dashboardType'] ?? 1);

// Multi-role: array of assigned group IDs + the default (active) group.
$group_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['user_group_ids'] ?? [])))));
$default_group_id = intval($_POST['default_group_id'] ?? 0);

if (!$dataID || empty($user_id) || empty($group_ids) || $default_group_id <= 0) {
    echo 0;
    exit;
}

// Reserved roles (Super Admin, Center Admin, Regional Super/Op Admin) are
// granted only via the role-approval workflow. Reject any attempt to pass
// them through the regular user-edit form (defence against malicious POST).
$RESERVED_FORM_IDS = [1, 2, 7, 8];
foreach ($group_ids as $gid) {
    if (in_array($gid, $RESERVED_FORM_IDS, true)) { echo 0; exit; }
}
if (in_array($default_group_id, $RESERVED_FORM_IDS, true)) { echo 0; exit; }

// Pull display fields from employee_list — single source of truth.
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

// Validate the user being edited belongs to admin's org
// (Super Admin / HQ bypass the org constraint — they manage all centers)
if ($_seeAllCenters) {
    $stmt_check = $con->prepare("SELECT dataID FROM user_list WHERE dataID = ?");
    $stmt_check->bind_param("i", $dataID);
} else {
    $stmt_check = $con->prepare("SELECT ul.dataID FROM user_list ul
        INNER JOIN employee_list el ON ul.employee_id = el.id
        WHERE ul.dataID = ? AND el.organization_id = ?");
    $stmt_check->bind_param("ii", $dataID, $orgID);
}
$stmt_check->execute();
$ownerCheck = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if (!$ownerCheck) {
    echo 0;
    exit;
}

// Validate the new employee assignment belongs to admin's org
// (Super Admin / HQ can pick employees from any center)
if ($employee_id > 0) {
    if ($_seeAllCenters) {
        $stmt_emp = $con->prepare("SELECT id FROM employee_list WHERE id = ?");
        $stmt_emp->bind_param("i", $employee_id);
    } else {
        $stmt_emp = $con->prepare("SELECT id FROM employee_list WHERE id = ? AND organization_id = ?");
        $stmt_emp->bind_param("ii", $employee_id, $orgID);
    }
    $stmt_emp->execute();
    $empCheck = $stmt_emp->get_result()->fetch_assoc();
    $stmt_emp->close();
    if (!$empCheck) {
        echo 0;
        exit;
    }
}

// Handle signature upload
if (!empty($_FILES['signature']['tmp_name']) && is_uploaded_file($_FILES['signature']['tmp_name'])) {
    $file_ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
    if (in_array($file_ext, ['jpeg', 'jpg', 'png']) && $_FILES['signature']['size'] <= 2097152) {
        $imgContent = file_get_contents($_FILES['signature']['tmp_name']);
        $stmt_sig = $con->prepare("UPDATE user_list SET signature = ? WHERE dataID = ?");
        $stmt_sig->bind_param("si", $imgContent, $dataID);
        $stmt_sig->execute();
        $stmt_sig->close();
    }
}

// ── Reserved roles ────────────────────────────────────────────────────
// 1 = Super Admin, 2 = Center Admin (legacy), 7 = Regional Super Admin,
// 8 = Regional Op. Admin. These aren't on the manual user form — they're
// granted via the role-approval workflow with attachment + approver sign-off.
// On save we MUST NOT touch them, otherwise admin edits would silently
// strip Regional Super/Op Admin assignments.
$RESERVED_ROLES = [1, 2, 7, 8];

// Fetch the user's currently-active role to decide whether to overwrite it.
$curStmt = $con->prepare("SELECT user_group_id FROM user_list WHERE dataID = ?");
$curStmt->bind_param("i", $dataID);
$curStmt->execute();
$curRow = $curStmt->get_result()->fetch_assoc();
$curStmt->close();
$currentActive = (int)($curRow['user_group_id'] ?? 0);
// If the user is currently active in a reserved role, keep it. Otherwise the
// form's default_group_id wins.
$nextActive = in_array($currentActive, $RESERVED_ROLES, true)
    ? $currentActive
    : $default_group_id;

if (!empty($_POST['password'])) {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $con->prepare("UPDATE user_list SET user_id=?, password=?, full_name=?, designation=?, mobile=?, email=?, employee_id=?, dashboardType=?, user_group_id=? WHERE dataID=?");
    $stmt->bind_param("ssssssiiii", $user_id, $password, $full_name, $designation, $mobile, $email, $employee_id, $dashboardType, $nextActive, $dataID);
} else {
    $stmt = $con->prepare("UPDATE user_list SET user_id=?, full_name=?, designation=?, mobile=?, email=?, employee_id=?, dashboardType=?, user_group_id=? WHERE dataID=?");
    $stmt->bind_param("sssssiiii", $user_id, $full_name, $designation, $mobile, $email, $employee_id, $dashboardType, $nextActive, $dataID);
}

if (!$stmt->execute()) {
    $stmt->close();
    echo 0;
    exit;
}
$stmt->close();

// Delete only NON-reserved assignment rows so reserved ones survive the
// form-driven replace. Then insert the form-submitted assignment set.
$reservedPlaceholders = implode(',', array_fill(0, count($RESERVED_ROLES), '?'));
$delTypes = str_repeat('i', count($RESERVED_ROLES) + 1);
$delParams = array_merge([$dataID], $RESERVED_ROLES);
$delStmt = $con->prepare(
    "DELETE FROM user_group_assignment
     WHERE user_id = ? AND group_id NOT IN ($reservedPlaceholders)"
);
$delStmt->bind_param($delTypes, ...$delParams);
$delStmt->execute();
$delStmt->close();

// is_default=1 only when the new assignment is also the active role on user_list.
// If a reserved role is active, is_default stays 0 on all form-driven inserts.
$insStmt = $con->prepare("INSERT INTO user_group_assignment (user_id, group_id, is_default) VALUES (?, ?, ?)");
foreach ($group_ids as $gid) {
    $isDefault = ($gid === $nextActive) ? 1 : 0;
    $insStmt->bind_param("iii", $dataID, $gid, $isDefault);
    $insStmt->execute();
}
$insStmt->close();

// Audit
if (function_exists('audit_log')) {
    audit_log('user_updated', [
        'target_type'     => 'user',
        'target_id'       => $dataID,
        'organization_id' => $orgID,
        'note'            => 'username=' . $user_id . '; groups=' . implode(',', $group_ids) . '; active=' . $nextActive,
    ]);
}

echo 1;
?>
