<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

function pq_fetch_one($con, $sql, $types = '', ...$params) {
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) return null;
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

$sessionUserID = $_SESSION['userID'] ?? '';
$getUserInfoQRW = pq_fetch_one($con, "SELECT * FROM user_list WHERE dataID = ?", 's', $sessionUserID);
$employeeId = $getUserInfoQRW['employee_id'] ?? '';

// DataTables server-side parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? max(0, intval($_POST['start'])) : 0;
$length = isset($_POST['length']) ? max(1, intval($_POST['length'])) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

$baseWhere = "FROM leave_deduction_history WHERE employeeID = ? AND isApproved = 1";
$baseTypes = 's';
$baseParams = [$employeeId];

$searchClause = '';
$searchTypes = $baseTypes;
$searchParams = $baseParams;
if (!empty($searchValue)) {
    $searchClause = " AND (note LIKE ? OR createDate LIKE ?)";
    $like = '%' . $searchValue . '%';
    $searchTypes .= 'ss';
    $searchParams[] = $like;
    $searchParams[] = $like;
}

// Total
$totalStmt = mysqli_prepare($con, "SELECT COUNT(*) AS total $baseWhere");
mysqli_stmt_bind_param($totalStmt, $baseTypes, ...$baseParams);
mysqli_stmt_execute($totalStmt);
$totalRecords = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['total'] ?? 0);
mysqli_stmt_close($totalStmt);

// Filtered
$filterStmt = mysqli_prepare($con, "SELECT COUNT(*) AS total $baseWhere $searchClause");
mysqli_stmt_bind_param($filterStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($filterStmt);
$filteredRecords = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($filterStmt))['total'] ?? 0);
mysqli_stmt_close($filterStmt);

// Data
$dataStmt = mysqli_prepare($con, "SELECT * $baseWhere $searchClause ORDER BY createDate DESC LIMIT $start, $length");
mysqli_stmt_bind_param($dataStmt, $searchTypes, ...$searchParams);
mysqli_stmt_execute($dataStmt);
$dataResult = mysqli_stmt_get_result($dataStmt);

$data = array();
$serial = $start + 1;

while ($dataRow = mysqli_fetch_assoc($dataResult)) {
    $leaveLabel = '';
    $leaveColor = 'leave-type-default';
    if ($dataRow['leaveID'] == 1)      { $leaveLabel = "গড় বেতন";              $leaveColor = 'leave-type-primary'; }
    else if ($dataRow['leaveID'] == 2) { $leaveLabel = "অর্ধ-গড় বেতন";          $leaveColor = 'leave-type-info'; }
    else if ($dataRow['leaveID'] == 3) { $leaveLabel = "নৈমিত্তিক (Casual)";    $leaveColor = 'leave-type-success'; }
    else if ($dataRow['leaveID'] == 4) { $leaveLabel = "অসাধারণ (বিনা বেতনে)";  $leaveColor = 'leave-type-warning'; }
    else if ($dataRow['leaveID'] == 5) { $leaveLabel = "ঐচ্ছিক ছুটি";            $leaveColor = 'leave-type-purple'; }

    $leaveTypeHtml = '<span class="leave-type-tag ' . $leaveColor . '">' . htmlspecialchars($leaveLabel) . '</span>';

    $deductionHtml = '<span class="days-pill days-pill-warning">' . banglaNumber((int)$dataRow['leaveDeduction']) . ' দিন</span>';

    $noteHtml = '';
    if (!empty(trim($dataRow['note']))) {
        $noteHtml = '<div class="note-cell"><i class="ti tabler-message-2 text-muted me-1"></i>' . htmlspecialchars($dataRow['note']) . '</div>';
    } else {
        $noteHtml = '<span class="text-muted small">—</span>';
    }

    $submitDateHtml = '';
    if (!empty($dataRow['createDate']) && $dataRow['createDate'] !== '0000-00-00') {
        $sd = date_create($dataRow['createDate']);
        if ($sd) {
            $submitDateHtml = '<div class="date-range"><i class="ti tabler-calendar"></i><span>' . banglaNumber(date_format($sd, "d/m/Y")) . '</span></div>';
        } else {
            $submitDateHtml = htmlspecialchars($dataRow['createDate']);
        }
    }

    $attachment = '';
    if (!empty($dataRow['attachment'])) {
        $attachment = '<a href="uploads/' . htmlspecialchars($dataRow['attachment']) . '" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                <i class="ti tabler-paperclip me-1"></i>সংযুক্তি
            </a>';
    } else {
        $attachment = '<span class="text-muted small">—</span>';
    }

    $data[] = array(
        'serial' => '<span class="serial-num">' . $serial++ . '</span>',
        'leave_type' => $leaveTypeHtml,
        'deduction_days' => $deductionHtml,
        'note' => $noteHtml,
        'submit_date' => $submitDateHtml,
        'attachment' => $attachment
    );
}
mysqli_stmt_close($dataStmt);

$response = array(
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
);

echo json_encode($response);
