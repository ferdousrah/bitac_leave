<?php
// Set JSON header first to prevent any output issues
header('Content-Type: application/json');

// Start output buffering to catch any unwanted output
ob_start();

require_once(__DIR__ . '/../../connection.php');

// Clear any output from connection.php
ob_end_clean();

// Validate and sanitize input
$limit = isset($_POST['length']) ? intval($_POST['length']) : 10;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

$orderColumn = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDirection = isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';

$columns = ['organization_name']; // Column names
$orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'organization_name';

// Get total records count (excluding soft deleted)
$totalRecordsQuery = mysqli_query($con, "SELECT COUNT(*) as total FROM organization WHERE deleted = 0");
$totalRecords = mysqli_fetch_assoc($totalRecordsQuery)['total'];

// Build the WHERE clause with prepared statement
$whereClause = " WHERE deleted = 0";
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause .= " AND organization_name LIKE ?";
    $searchParam = "%{$search}%";
    $params = [$searchParam];
    $types = "s";
}

// Get filtered records count
if (!empty($search)) {
    $countSql = "SELECT COUNT(*) as total FROM organization" . $whereClause;
    $countStmt = mysqli_prepare($con, $countSql);
    if ($countStmt) {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        $filteredRecords = mysqli_fetch_assoc($countResult)['total'];
        mysqli_stmt_close($countStmt);
    } else {
        $filteredRecords = $totalRecords;
    }
} else {
    $filteredRecords = $totalRecords;
}

// Prepare the main query with pagination, sorting, and search filter
$sql = "SELECT * FROM organization" . $whereClause . " ORDER BY {$orderBy} {$orderDirection} LIMIT ?, ?";

$stmt = mysqli_prepare($con, $sql);
if ($stmt) {
    // Bind parameters based on whether we have a search
    if (!empty($search)) {
        $allTypes = $types . "ii";
        $allParams = array_merge($params, [$start, $limit]);
        mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $start, $limit);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    $sl = $start + 1;

    while ($row = mysqli_fetch_assoc($result)) {
        $menuslug = isset($_GET['menuslug']) ? htmlspecialchars($_GET['menuslug']) : 'manage-center';
        $dataID = intval($row['id']);

        $centerName = htmlspecialchars($row['organization_name']);
        $nameCell = '<div class="d-flex align-items-center gap-2"><span class="center-icon-tile"><i class="ti tabler-building-bank"></i></span><span class="center-name">' . $centerName . '</span></div>';

        $action = '<div class="action-group">'
                . '<a data-turbo="true" data-bs-toggle="tooltip" data-bs-placement="top" title="সম্পাদনা" href="edit.php?dataID=' . $dataID . '&menuslug=' . $menuslug . '" class="action-icon icon-view"><i class="ti tabler-edit"></i></a>'
                . '<button type="button" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন" onclick="removeData(' . $sl . ',' . $dataID . ')" class="action-icon icon-reject"><i class="ti tabler-trash"></i></button>'
                . '</div>';

        $data[] = [
            "sl" => '<span class="serial-num">' . $sl . '</span>',
            "organization_name" => $nameCell,
            "action" => $action,
        ];
        $sl++;
    }

    mysqli_stmt_close($stmt);

    // Send response in JSON format
    $response = [
        "draw" => $draw,
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => intval($filteredRecords),
        "data" => $data
    ];

    echo json_encode($response);
} else {
    // Error response
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Database query error"
    ]);
}
?>
