<?php
session_start();
header('Content-Type: application/json');

ob_start();
include('connection.php');
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

// Update user's group assignment
if ($groupId === null) {
    // Remove group assignment (set to NULL)
    $updateQuery = "UPDATE user_list SET user_group_id = NULL WHERE dataID = ?";
    $stmt = mysqli_prepare($con, $updateQuery);
    mysqli_stmt_bind_param($stmt, "i", $userId);
} else {
    // Assign group to user
    $updateQuery = "UPDATE user_list SET user_group_id = ? WHERE dataID = ?";
    $stmt = mysqli_prepare($con, $updateQuery);
    mysqli_stmt_bind_param($stmt, "ii", $groupId, $userId);
}

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['status' => 1, 'message' => 'গ্রুপ সফলভাবে বরাদ্দ করা হয়েছে!']);
    } else {
        echo json_encode(['status' => 1, 'message' => 'কোনো পরিবর্তন হয়নি!']);
    }
} else {
    echo json_encode(['status' => 0, 'message' => 'গ্রুপ বরাদ্দ করতে ব্যর্থ হয়েছে!']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>
