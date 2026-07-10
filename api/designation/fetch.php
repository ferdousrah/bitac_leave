<?php
// Start session first
session_start();

// Set JSON header first to prevent any output issues
header('Content-Type: application/json');

// Start output buffering to catch any unwanted output
ob_start();
require_once(__DIR__ . '/../../connection.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
    exit;
}

// Check if 'deleted' column exists in job_title table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM job_title LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

// Validate and sanitize input
$limit = isset($_POST['length']) ? intval($_POST['length']) : 10;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
$orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDirection = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

// Define columns that can be ordered
$columns = ['id', 'job_title_name'];
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
        $whereClause = " WHERE job_title_name LIKE ?";
    } else {
        $whereClause .= " AND job_title_name LIKE ?";
    }
    $params[] = "%{$search}%";
    $types .= "s";
}

// Get total records (without filtering)
$totalQuery = "SELECT COUNT(*) as total FROM job_title";
if ($hasDeletedColumn) {
    $totalQuery .= " WHERE deleted = 0";
}
$totalResult = mysqli_query($con, $totalQuery);
$totalRecords = mysqli_fetch_assoc($totalResult)['total'];

// Get filtered records count
$filteredQuery = "SELECT COUNT(*) as total FROM job_title" . $whereClause;
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
$sql = "SELECT * FROM job_title" . $whereClause . " ORDER BY {$orderBy} {$orderDirection} LIMIT ?, ?";

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
    $menuslug = isset($_GET['menuslug']) ? htmlspecialchars($_GET['menuslug']) : 'manage-designations';

    $jobName = htmlspecialchars($row['job_title_name']);
    $nameCell = '<div class="d-flex align-items-center gap-2"><span class="designation-icon-tile"><i class="ti tabler-id-badge-2"></i></span><span class="center-name">' . $jobName . '</span></div>';

    $action = '<div class="action-group">'
            . '<a data-turbo="true" data-bs-toggle="tooltip" data-bs-placement="top" title="সম্পাদনা" href="edit.php?dataID=' . (int)$row['id'] . '&menuslug=' . $menuslug . '" class="action-icon icon-view"><i class="ti tabler-edit"></i></a>'
            . '<button type="button" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন" onclick="removeData(' . (int)$row['id'] . ',' . (int)$row['id'] . ')" class="action-icon icon-reject"><i class="ti tabler-trash"></i></button>'
            . '</div>';

    $data[] = [
        'sl' => '<span class="serial-num">' . $sl++ . '</span>',
        'job_title_name' => $nameCell,
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
