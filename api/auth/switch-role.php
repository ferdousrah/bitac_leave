<?php
/**
 * Switch the logged-in user's active role (user_group).
 *
 * Body params (POST): group_id — the group to switch to
 *
 * Returns JSON: { status: 0|1, message: string, group_name?: string }
 *
 * Only allows switching to a group the user is actually assigned to
 * (validated against user_group_assignment), so a malicious client can't
 * elevate themselves to an unassigned role.
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'লগইন করা নেই']);
    exit;
}

$targetGroupId = (int)($_POST['group_id'] ?? 0);
if ($targetGroupId <= 0) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ গ্রুপ']);
    exit;
}

// Resolve current user
$uStmt = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
$uStmt->bind_param("s", $_SESSION['username']);
$uStmt->execute();
$userRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$userRow) {
    echo json_encode(['status' => 0, 'message' => 'ব্যবহারকারী খুঁজে পাওয়া যায়নি']);
    exit;
}
$userID = (int)$userRow['dataID'];

// Verify the user is actually assigned to the requested group + fetch name for the response
$vStmt = $con->prepare(
    "SELECT ug.group_name
     FROM user_group_assignment uga
     INNER JOIN user_group ug ON uga.group_id = ug.id
     WHERE uga.user_id = ? AND uga.group_id = ? AND ug.deleted = 0
     LIMIT 1"
);
$vStmt->bind_param("ii", $userID, $targetGroupId);
$vStmt->execute();
$valid = $vStmt->get_result()->fetch_assoc();
$vStmt->close();

if (!$valid) {
    echo json_encode(['status' => 0, 'message' => 'এই গ্রুপে আপনার অ্যাক্সেস নেই']);
    exit;
}

// Flip active group on user_list — the rest of the app already reads this column,
// so existing menu/permission logic picks up the new role on next page load.
$upd = $con->prepare("UPDATE user_list SET user_group_id = ? WHERE dataID = ?");
$upd->bind_param("ii", $targetGroupId, $userID);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    echo json_encode(['status' => 0, 'message' => 'ত্রুটি ঘটেছে']);
    exit;
}

if (function_exists('audit_log')) {
    audit_log('role_switched', [
        'target_type' => 'user',
        'target_id'   => $userID,
        'note'        => 'switched to group_id=' . $targetGroupId . ' (' . $valid['group_name'] . ')',
    ]);
}

echo json_encode([
    'status'     => 1,
    'message'    => 'রোল পরিবর্তন সম্পন্ন',
    'group_name' => $valid['group_name'],
]);
