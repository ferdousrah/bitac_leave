<?php
/**
 * Delete a user.
 *
 * Body (POST): dataID — user_list.dataID to remove.
 *
 * Returns plain "1" on success, "0" on failure (matches the AJAX expectation
 * on views/users/manage.php).
 *
 * Cleans up dependent rows: user_group_assignment (no FK cascade configured),
 * leave_notice_copy etc. are left to admin's discretion — only the direct
 * user-account artefacts are removed here.
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: text/plain; charset=utf-8');

if (empty($_SESSION['username'])) { echo 0; exit; }

$dataID = (int)($_POST['dataID'] ?? 0);
if ($dataID <= 0) { echo 0; exit; }

// Resolve current admin: Super Admin (group_id=1) can delete users from any
// org; everyone else is scoped to their own center.
$meStmt = $con->prepare("SELECT dataID, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$meStmt->bind_param("s", $_SESSION['username']);
$meStmt->execute();
$meRow = $meStmt->get_result()->fetch_assoc();
$meStmt->close();
$isSuperAdmin = $meRow && (int)$meRow['user_group_id'] === 1;

if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = (int)$_SESSION['centerAdminOrgID'];
} else {
    $empID = (int)($_SESSION['employeeID'] ?? 0);
    $stmt_org = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $stmt_org->bind_param("i", $empID);
    $stmt_org->execute();
    $orgRow = $stmt_org->get_result()->fetch_assoc();
    $stmt_org->close();
    $orgID = (int)($orgRow['organization_id'] ?? 0);
}

// Verify the target user exists. For non-super-admin viewers we additionally
// require the target to be in the admin's own org. Super Admin has no org gate.
if ($isSuperAdmin) {
    $check = $con->prepare("SELECT dataID FROM user_list WHERE dataID = ? LIMIT 1");
    $check->bind_param("i", $dataID);
} else {
    $check = $con->prepare(
        "SELECT ul.dataID
         FROM user_list ul
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         WHERE ul.dataID = ?
           AND (el.organization_id = ? OR ul.organization_id = ?)
         LIMIT 1"
    );
    $check->bind_param("iii", $dataID, $orgID, $orgID);
}
$check->execute();
$found = $check->get_result()->fetch_assoc();
$check->close();

if (!$found) { echo 0; exit; }

// Don't allow deleting yourself
$selfStmt = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
$selfStmt->bind_param("s", $_SESSION['username']);
$selfStmt->execute();
$self = $selfStmt->get_result()->fetch_assoc();
$selfStmt->close();
if ($self && (int)$self['dataID'] === $dataID) { echo 0; exit; }

mysqli_begin_transaction($con);
try {
    // Clean up role assignments (no FK cascade exists)
    $del1 = $con->prepare("DELETE FROM user_group_assignment WHERE user_id = ?");
    $del1->bind_param("i", $dataID);
    $del1->execute();
    $del1->close();

    // Finally remove the user
    $del2 = $con->prepare("DELETE FROM user_list WHERE dataID = ?");
    $del2->bind_param("i", $dataID);
    $del2->execute();
    $affected = mysqli_stmt_affected_rows($del2);
    $del2->close();

    if ($affected < 1) {
        mysqli_rollback($con);
        echo 0;
        exit;
    }

    mysqli_commit($con);

    if (function_exists('audit_log')) {
        audit_log('user_deleted', [
            'target_type'     => 'user',
            'target_id'       => $dataID,
            'organization_id' => $orgID,
        ]);
    }

    echo 1;
} catch (Throwable $e) {
    mysqli_rollback($con);
    echo 0;
}
