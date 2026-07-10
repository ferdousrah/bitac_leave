<?php
// Start session first
session_start();

// Set JSON header first to prevent any output issues
header('Content-Type: application/json');

// Start output buffering to catch any unwanted output
ob_start();
require_once(__DIR__ . '/../../config/connection.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
    exit;
}

// Check if 'deleted' column exists in user_group table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM user_group LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

// Validate and sanitize input
$limit = isset($_POST['length']) ? intval($_POST['length']) : 10;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
$orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDirection = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

// Define columns that can be ordered
$columns = ['id', 'group_name'];
$orderBy = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'id';

// Build WHERE clause - only add deleted filter if column exists
$whereClause = "";
if ($hasDeletedColumn) {
    $whereClause = " WHERE deleted = 0";
}
$params = [];
$types = "";

if (!empty($search)) {
    if ($whereClause === "") {
        $whereClause = " WHERE group_name LIKE ?";
    } else {
        $whereClause .= " AND group_name LIKE ?";
    }
    $params[] = "%{$search}%";
    $types .= "s";
}

// Get total records (without filtering)
$totalQuery = "SELECT COUNT(*) as total FROM user_group";
if ($hasDeletedColumn) {
    $totalQuery .= " WHERE deleted = 0";
}
$totalResult = mysqli_query($con, $totalQuery);
$totalRecords = mysqli_fetch_assoc($totalResult)['total'];

// Get filtered records count
$filteredQuery = "SELECT COUNT(*) as total FROM user_group" . $whereClause;
if (!empty($params)) {
    $filteredStmt = mysqli_prepare($con, $filteredQuery);
    mysqli_stmt_bind_param($filteredStmt, $types, ...$params);
    mysqli_stmt_execute($filteredStmt);
    $filteredResult = mysqli_stmt_get_result($filteredStmt);
    $filteredRecords = mysqli_fetch_assoc($filteredResult)['total'];
    mysqli_stmt_close($filteredStmt);
} else {
    $filteredResult = mysqli_query($con, $filteredQuery);
    $filteredRecords = mysqli_fetch_assoc($filteredResult)['total'];
}

// Get actual data
$sql = "SELECT * FROM user_group" . $whereClause . " ORDER BY {$orderBy} {$orderDirection} LIMIT ?, ?";

// Add pagination parameters
$allParams = $params;
$allParams[] = $start;
$allParams[] = $limit;
$allTypes = $types . "ii";

$stmt = mysqli_prepare($con, $sql);
if (!empty($allParams)) {
    mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
$sl = $start + 1;

while ($row = mysqli_fetch_assoc($result)) {
    $menuslug = isset($_GET['menuslug']) ? htmlspecialchars($_GET['menuslug']) : 'manage-user-group';

    $groupName = htmlspecialchars($row['group_name']);
    $nameCell = '<div class="d-flex align-items-center gap-2"><span class="group-icon-tile"><i class="ti tabler-users-group"></i></span><span class="center-name">' . $groupName . '</span></div>';

    $action = '<div class="action-group">'
            . '<a data-turbo="true" data-bs-toggle="tooltip" data-bs-placement="top" title="পারমিশন" href="../../views/users/group-access.php?group_id=' . (int)$row['id'] . '&menuslug=' . $menuslug . '" class="action-icon icon-approve"><i class="ti tabler-key"></i></a>'
            . '<a data-turbo="true" data-bs-toggle="tooltip" data-bs-placement="top" title="সম্পাদনা" href="../../views/users/edit-group.php?dataID=' . (int)$row['id'] . '&menuslug=' . $menuslug . '" class="action-icon icon-view"><i class="ti tabler-edit"></i></a>'
            . '<button type="button" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন" onclick="removeData(' . (int)$row['id'] . ',' . (int)$row['id'] . ')" class="action-icon icon-reject"><i class="ti tabler-trash"></i></button>'
            . '</div>';

    $data[] = [
        'sl' => '<span class="serial-num">' . $sl++ . '</span>',
        'group_name' => $nameCell,
        'action' => $action,
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($con);

// Return JSON response
echo json_encode([
    'draw' => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
]);
?>
