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

// Validate input
if (!isset($_POST['group_id']) || empty($_POST['group_id'])) {
    echo json_encode(['status' => 0, 'message' => 'গ্রুপ আইডি প্রদান করুন!']);
    exit;
}

$groupId = intval($_POST['group_id']);

// Fetch all modules with their submodules
$modulesQuery = "
    SELECT
        m.dataID,
        m.module_name,
        m.page_link,
        m.icon,
        m.display_order
    FROM modules m
    WHERE m.deleted = 0
    ORDER BY m.display_order ASC
";

$modulesResult = mysqli_query($con, $modulesQuery);

if (!$modulesResult) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

$modules = [];
while ($module = mysqli_fetch_assoc($modulesResult)) {
    $moduleId = $module['dataID'];

    // Fetch submodules for this module
    $submodulesQuery = "
        SELECT
            dataID,
            submodule_name,
            page_link,
            display_order
        FROM submodules
        WHERE module_id = ? AND deleted = 0
        ORDER BY display_order ASC
    ";

    $stmt = mysqli_prepare($con, $submodulesQuery);
    mysqli_stmt_bind_param($stmt, "i", $moduleId);
    mysqli_stmt_execute($stmt);
    $submodulesResult = mysqli_stmt_get_result($stmt);

    $submodules = [];
    while ($submodule = mysqli_fetch_assoc($submodulesResult)) {
        $submodules[] = $submodule;
    }
    mysqli_stmt_close($stmt);

    $module['submodules'] = $submodules;
    $modules[] = $module;
}

// Fetch existing permissions for this group
$permissionsQuery = "
    SELECT
        module_id,
        submodule_id
    FROM group_access_permission
    WHERE user_group_id = ?
";

$stmt = mysqli_prepare($con, $permissionsQuery);
mysqli_stmt_bind_param($stmt, "i", $groupId);
mysqli_stmt_execute($stmt);
$permissionsResult = mysqli_stmt_get_result($stmt);

$permissions = [];
while ($permission = mysqli_fetch_assoc($permissionsResult)) {
    $permissions[] = $permission;
}
mysqli_stmt_close($stmt);

mysqli_close($con);

echo json_encode([
    'status' => 1,
    'modules' => $modules,
    'permissions' => $permissions
]);
?>
