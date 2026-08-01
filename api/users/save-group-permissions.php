<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!isset($data['group_id']) || empty($data['group_id'])) {
    echo json_encode(['status' => 0, 'message' => 'গ্রুপ আইডি প্রদান করুন!']);
    exit;
}

if (!isset($data['permissions']) || !is_array($data['permissions'])) {
    echo json_encode(['status' => 0, 'message' => 'পারমিশন ডেটা সঠিক নয়!']);
    exit;
}

$groupId = intval($data['group_id']);
$permissions = $data['permissions'];

// Start transaction
mysqli_begin_transaction($con);

try {
    // First, delete all existing permissions for this group
    $deleteQuery = "DELETE FROM group_access_permission WHERE user_group_id = ?";
    $stmt = mysqli_prepare($con, $deleteQuery);
    mysqli_stmt_bind_param($stmt, "i", $groupId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Insert new permissions
    if (count($permissions) > 0) {
        $insertQuery = "INSERT INTO group_access_permission (user_group_id, module_id, submodule_id) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($con, $insertQuery);

        foreach ($permissions as $permission) {
            $moduleId = isset($permission['module_id']) ? intval($permission['module_id']) : null;
            $submoduleId = isset($permission['submodule_id']) && $permission['submodule_id'] !== null
                ? intval($permission['submodule_id'])
                : null;

            if ($moduleId) {
                mysqli_stmt_bind_param($stmt, "iii", $groupId, $moduleId, $submoduleId);
                mysqli_stmt_execute($stmt);
            }
        }

        mysqli_stmt_close($stmt);
    }

    // Commit transaction
    mysqli_commit($con);

    if (function_exists('audit_log')) {
        audit_log('group_permissions_saved', [
            'target_type' => 'group_access_permission',
            'target_id'   => (int)$groupId,
            'note'        => 'permission matrix updated',
        ]);
    }

    echo json_encode(['status' => 1, 'message' => 'পারমিশন সফলভাবে সংরক্ষণ করা হয়েছে!']);

} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => 'পারমিশন সংরক্ষণ করতে ব্যর্থ হয়েছে!']);
}

mysqli_close($con);
?>
