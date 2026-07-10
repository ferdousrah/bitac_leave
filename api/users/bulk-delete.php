<?php
/**
 * Bulk-delete users from the manage page.
 *
 * Body (POST): dataIDs[] — array of user_list.dataID values
 *
 * Mirrors the single-row delete.php logic for each ID:
 *   - Super Admin (group_id=1) can delete users from any org
 *   - Others scoped to their own org
 *   - Never delete yourself
 *   - Clean up user_group_assignment then user_list
 *
 * Returns JSON:
 *   { status: 0|1, deleted: N, skipped: N, errors: [..] }
 *
 * Each row is processed in its own transaction so a single failure doesn't
 * abort the whole batch.
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'লগইন করা নেই', 'deleted' => 0, 'skipped' => 0, 'errors' => []]);
    exit;
}

$ids = (array)($_POST['dataIDs'] ?? []);
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
if (empty($ids)) {
    echo json_encode(['status' => 0, 'message' => 'কোনো ব্যবহারকারী নির্বাচিত নয়', 'deleted' => 0, 'skipped' => 0, 'errors' => []]);
    exit;
}

// Resolve current admin
$meStmt = $con->prepare("SELECT dataID, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$meStmt->bind_param("s", $_SESSION['username']);
$meStmt->execute();
$me = $meStmt->get_result()->fetch_assoc();
$meStmt->close();
$myUserId = (int)($me['dataID'] ?? 0);
$isSuperAdmin = $me && (int)$me['user_group_id'] === 1;

if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = (int)$_SESSION['centerAdminOrgID'];
} else {
    $empID = (int)($_SESSION['employeeID'] ?? 0);
    $os = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $os->bind_param("i", $empID);
    $os->execute();
    $orgRow = $os->get_result()->fetch_assoc();
    $os->close();
    $orgID = (int)($orgRow['organization_id'] ?? 0);
}

$deleted = 0;
$skipped = 0;
$errors  = [];

foreach ($ids as $dataID) {
    // Never delete yourself
    if ($dataID === $myUserId) {
        $skipped++;
        $errors[] = "#$dataID — নিজেকে মুছা যাবে না";
        continue;
    }

    // Ownership check (Super Admin bypasses)
    if ($isSuperAdmin) {
        $chk = $con->prepare("SELECT dataID FROM user_list WHERE dataID = ? LIMIT 1");
        $chk->bind_param("i", $dataID);
    } else {
        $chk = $con->prepare(
            "SELECT ul.dataID
             FROM user_list ul
             LEFT JOIN employee_list el ON ul.employee_id = el.id
             WHERE ul.dataID = ?
               AND (el.organization_id = ? OR ul.organization_id = ?)
             LIMIT 1"
        );
        $chk->bind_param("iii", $dataID, $orgID, $orgID);
    }
    $chk->execute();
    $owns = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$owns) {
        $skipped++;
        $errors[] = "#$dataID — access নেই বা পাওয়া যায়নি";
        continue;
    }

    // Delete in its own transaction
    mysqli_begin_transaction($con);
    try {
        $d1 = $con->prepare("DELETE FROM user_group_assignment WHERE user_id = ?");
        $d1->bind_param("i", $dataID);
        $d1->execute();
        $d1->close();

        $d2 = $con->prepare("DELETE FROM user_list WHERE dataID = ?");
        $d2->bind_param("i", $dataID);
        $d2->execute();
        $aff = mysqli_stmt_affected_rows($d2);
        $d2->close();

        if ($aff < 1) {
            mysqli_rollback($con);
            $skipped++;
            $errors[] = "#$dataID — ইতিমধ্যে মুছা";
            continue;
        }

        mysqli_commit($con);
        $deleted++;

        if (function_exists('audit_log')) {
            audit_log('user_deleted', [
                'target_type' => 'user',
                'target_id'   => $dataID,
                'note'        => 'bulk delete batch (' . count($ids) . ' items)',
            ]);
        }
    } catch (Throwable $e) {
        mysqli_rollback($con);
        $skipped++;
        $errors[] = "#$dataID — " . $e->getMessage();
    }
}

echo json_encode([
    'status'  => ($deleted > 0 && $skipped === 0) ? 1 : 0,
    'deleted' => $deleted,
    'skipped' => $skipped,
    'errors'  => array_slice($errors, 0, 5), // cap so the alert stays readable
]);
