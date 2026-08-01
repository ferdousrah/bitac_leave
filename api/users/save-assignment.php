<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../config/connection.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate input
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    echo json_encode(['status' => 0, 'message' => 'ব্যবহারকারী আইডি প্রদান করুন!']);
    exit;
}

$userId = intval($_POST['user_id']);
$groupId = isset($_POST['group_id']) && !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;

// Update user's group assignment.
// user_group_id on user_list = currently active group. user_group_assignment
// holds the full set of groups the user can switch between. Keep them in sync
// for the single-group quick-assign UI: replacing the active group also
// replaces the assignment set.
if ($groupId === null) {
    $stmt = mysqli_prepare($con, "UPDATE user_list SET user_group_id = NULL WHERE dataID = ?");
    mysqli_stmt_bind_param($stmt, "i", $userId);
} else {
    $stmt = mysqli_prepare($con, "UPDATE user_list SET user_group_id = ? WHERE dataID = ?");
    mysqli_stmt_bind_param($stmt, "ii", $groupId, $userId);
}

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 0, 'message' => 'গ্রুপ বরাদ্দ করতে ব্যর্থ হয়েছে!']);
    mysqli_close($con);
    exit;
}
mysqli_stmt_close($stmt);

// Mirror to assignment table — wipe & re-insert. Single-group page,
// so we keep exactly one row (or zero if cleared).
$del = mysqli_prepare($con, "DELETE FROM user_group_assignment WHERE user_id = ?");
mysqli_stmt_bind_param($del, "i", $userId);
mysqli_stmt_execute($del);
mysqli_stmt_close($del);

if ($groupId !== null) {
    $ins = mysqli_prepare($con, "INSERT INTO user_group_assignment (user_id, group_id, is_default) VALUES (?, ?, 1)");
    mysqli_stmt_bind_param($ins, "ii", $userId, $groupId);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
}

if (function_exists('audit_log')) {
    audit_log('user_group_assigned', [
        'target_type' => 'user_group_assignment',
        'target_id'   => (int)$userId,
        'note'        => 'default_group=' . ($groupId ?? 'none'),
    ]);
}

echo json_encode(['status' => 1, 'message' => 'গ্রুপ সফলভাবে বরাদ্দ করা হয়েছে!']);
mysqli_close($con);
?>
