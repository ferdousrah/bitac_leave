<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../library/number_converter.php');
ob_end_clean();

error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['username'])) {
    echo json_encode(["draw" => 0, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    exit;
}

$request = $_REQUEST;

$columns = [
    0 => 'lah.dataID',
    1 => 'el.employee_name',
    2 => 'lah.leaveID',
    3 => 'lah.leaveAddition',
    4 => 'lah.note',
    5 => 'lah.attachment',
    6 => 'action'
];

// ── Resolve user's org & employee_id ──────────────────
$orgStmt = $con->prepare("SELECT isCenterAdmin, organization_id, employee_id, user_group_id FROM user_list WHERE user_id = ?");
$orgStmt->bind_param("s", $_SESSION['username']);
$orgStmt->execute();
$orgUserRow = $orgStmt->get_result()->fetch_assoc();
$orgStmt->close();
$isSuperAdmin = (int)($orgUserRow['user_group_id'] ?? 0) === 1;
if (!empty($orgUserRow['isCenterAdmin'])) {
    $userOrgID = (int)$orgUserRow['organization_id'];
    $userEmpID = (int)($orgUserRow['employee_id'] ?? 0);
} elseif (!empty($orgUserRow['employee_id'])) {
    $userEmpID  = (int)$orgUserRow['employee_id'];
    $empOrgStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $empOrgStmt->bind_param("i", $userEmpID);
    $empOrgStmt->execute();
    $userOrgID  = (int)($empOrgStmt->get_result()->fetch_assoc()['organization_id'] ?? 0);
    $empOrgStmt->close();
} else {
    $userOrgID = 0;
    $userEmpID = 0;
}
// Super Admin bypass — always sees everything, matches menu-counts.php + page stat
if ($isSuperAdmin) { $userOrgID = 0; }

// Determine if the user is the org's default approver (legacy path).
// New office-order rows carry an explicit override_signatory_id and are
// visible only to that signatory, regardless of whether they hold the
// org's default signatory role.
$isOrgSignatory = false;
if ($userOrgID > 0) {
    $sigStmt = $con->prepare("SELECT dataID FROM leave_edit_approval_signatory WHERE employeeID = ? AND organization_id = ? LIMIT 1");
    $sigStmt->bind_param("ii", $userEmpID, $userOrgID);
    $sigStmt->execute();
    $sigRow = $sigStmt->get_result()->fetch_assoc();
    $sigStmt->close();
    $isOrgSignatory = (bool)$sigRow;
}

// Signatory-scope clause: super admin sees all; others see either rows
// explicitly assigned to them via override_signatory_id, OR (if they are the
// org's default signatory) legacy rows without an override in their org.
if ($userOrgID === 0) {
    $orgScope = '';
} elseif ($isOrgSignatory) {
    $orgScope = "AND (lah.override_signatory_id = $userEmpID
                     OR (lah.override_signatory_id IS NULL AND el.organization_id = $userOrgID))";
} else {
    $orgScope = "AND lah.override_signatory_id = $userEmpID";
}

// ── Filter params ────────────────────────────────────
$centerFilter    = (int)($_REQUEST['centerFilter']    ?? 0);
$sectionFilter   = (int)($_REQUEST['sectionFilter']   ?? 0);
$employeeFilter  = (int)($_REQUEST['employeeFilter']  ?? 0);
$leaveTypeFilter = (int)($_REQUEST['leaveTypeFilter'] ?? 0);

$filterClause = '';
if ($userOrgID === 0 && $centerFilter > 0) $filterClause .= " AND el.organization_id = $centerFilter";
if ($sectionFilter   > 0) $filterClause .= " AND el.section_id = $sectionFilter";
if ($employeeFilter  > 0) $filterClause .= " AND el.id = $employeeFilter";
if ($leaveTypeFilter > 0) $filterClause .= " AND lah.leaveID = $leaveTypeFilter";

// Batch grouping: rows sharing a batch_id belong to one office order — show
// them as ONE approval card. Legacy rows (batch_id NULL) each get their own
// synthetic batch key based on dataID.
$batchKeyExpr = "COALESCE(lah.batch_id, CONCAT('_solo_', lah.dataID))";

$sqlBase = "FROM leave_addition_history lah
            INNER JOIN employee_list el ON lah.employeeID = el.id
            LEFT JOIN job_title jt ON el.designation = jt.id
            LEFT JOIN sections s ON el.section_id = s.id
            LEFT JOIN organization o ON el.organization_id = o.id
            WHERE lah.isApproved = 0 $orgScope $filterClause";

// Search
$searchSql = '';
if (!empty($request['search']['value'])) {
    $sv = mysqli_real_escape_string($con, $request['search']['value']);
    $searchSql = " AND (el.employee_name LIKE '%$sv%'
                    OR el.employee_id LIKE '%$sv%'
                    OR jt.job_title_name LIKE '%$sv%'
                    OR s.section_name LIKE '%$sv%'
                    OR lah.leaveAddition LIKE '%$sv%'
                    OR lah.note LIKE '%$sv%')";
}

// Totals = distinct batch count (not row count)
$totCntSql = "SELECT COUNT(DISTINCT $batchKeyExpr) AS c $sqlBase";
$totalData = (int)(mysqli_fetch_assoc(mysqli_query($con, $totCntSql))['c'] ?? 0);
$fltCntSql = "SELECT COUNT(DISTINCT $batchKeyExpr) AS c $sqlBase $searchSql";
$totalFiltered = (int)(mysqli_fetch_assoc(mysqli_query($con, $fltCntSql))['c'] ?? 0);

// Order — since we aggregate, sort by MIN(dataID) DESC
$start  = max(0, (int)($request['start']  ?? 0));
$length = max(1, (int)($request['length'] ?? 10));

$aggSelect = "$batchKeyExpr AS batch_key,
              MIN(lah.dataID) AS first_dataID,
              lah.employeeID,
              MAX(lah.attachment) AS attachment,
              MAX(lah.batch_id) AS batch_id_val,
              SUM(lah.leaveAddition) AS total_days,
              COUNT(*) AS row_count,
              GROUP_CONCAT(CONCAT(lah.leaveID, ':', lah.leaveAddition) ORDER BY lah.dataID SEPARATOR '|') AS types_days,
              GROUP_CONCAT(lah.note ORDER BY lah.dataID SEPARATOR ' || ') AS all_notes,
              el.employee_name, el.employee_id AS emp_code, el.photo, el.designation, el.section_id,
              jt.job_title_name, s.section_name, o.organization_name";

$query = mysqli_query($con, "SELECT $aggSelect $sqlBase $searchSql
                             GROUP BY batch_key, lah.employeeID
                             ORDER BY first_dataID DESC
                             LIMIT $start, $length");

$leaveTypeMap = [
    1  => ['গড় বেতন', 'leave-type-primary'],
    2  => ['অর্ধ-গড় বেতন', 'leave-type-info'],
    3  => ['নৈমিত্তিক (Casual)', 'leave-type-success'],
    4  => ['বিনা বেতনে ছুটি', 'leave-type-warning'],
    5  => ['ঐচ্ছিক ছুটি', 'leave-type-purple'],
    6  => ['কর্তনহীন ছুটি', 'leave-type-default'],
    10 => ['অসাধারণ ছুটি', 'leave-type-warning'],
];

$data = [];
$sl   = $start;

while ($row = mysqli_fetch_assoc($query)) {
    $sl++;

    // Employee cell with avatar
    $empName  = trim($row['employee_name'] ?? '');
    $empJob   = trim($row['job_title_name'] ?? '');
    $empSec   = trim($row['section_name'] ?? '');
    $empOrg   = trim($row['organization_name'] ?? '');
    $empPhoto = trim($row['photo'] ?? '');
    $empCode  = trim($row['emp_code'] ?? '');
    $initials = mb_substr($empName, 0, 1, 'UTF-8');
    $parts = preg_split('/\s+/u', $empName);
    if (count($parts) > 1) {
        $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
    }
    if (!empty($empPhoto)) {
        $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
        $avatarHtml = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
    } else {
        $avatarHtml = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
    }
    $empSubLight = trim($empSec . ($empSec && $empOrg ? ' • ' : '') . $empOrg);
    $employeeInfo = '<div class="emp-cell">' . $avatarHtml
                  . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName) . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                  . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                  . ($empSubLight ? '<div class="emp-sub-light">' . htmlspecialchars($empSubLight) . '</div>' : '')
                  . '</div></div>';

    // Leave-type tags — one chip per row in the batch
    $typesDays = trim($row['types_days'] ?? '');
    $chips = [];
    if ($typesDays !== '') {
        foreach (explode('|', $typesDays) as $pair) {
            list($ltID, $days) = array_pad(explode(':', $pair), 2, '');
            $ltInfo = $leaveTypeMap[(int)$ltID] ?? [' ', 'leave-type-default'];
            $chips[] = '<span class="leave-type-tag ' . $ltInfo[1] . '">'
                     . htmlspecialchars($ltInfo[0]) . ' <b class="ms-1">+' . banglaNumber((int)$days) . '</b></span>';
        }
    }
    $leaveTypeHtml = !empty($chips)
        ? '<div class="d-flex flex-wrap gap-1">' . implode(' ', $chips) . '</div>'
        : '<span class="text-muted small">—</span>';

    // Total days pill (batched)
    $rowCount = (int)$row['row_count'];
    $batchBadge = $rowCount > 1
        ? ' <span class="badge bg-label-secondary ms-1">' . banglaNumber($rowCount) . 'টি এন্ট্রি</span>'
        : '';
    $addHtml = '<span class="days-pill days-pill-success">+' . banglaNumber((float)$row['total_days']) . ' দিন</span>' . $batchBadge;

    // Note (may be a concat if the batch has different notes per row)
    $noteHtml = '<span class="text-muted small">—</span>';
    if (!empty(trim($row['all_notes'] ?? ''))) {
        $noteHtml = '<div class="note-cell"><i class="ti tabler-message-2 text-muted me-1"></i>' . htmlspecialchars($row['all_notes']) . '</div>';
    }

    // Attachment
    $attHtml = '<span class="text-muted small">—</span>';
    if (!empty($row['attachment'])) {
        $attHtml = '<a href="../../uploads/' . htmlspecialchars($row['attachment']) . '" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                <i class="ti tabler-paperclip me-1"></i>সংযুক্তি
            </a>';
    }

    // Actions — one approve/reject per batch (batch_key includes the '_solo_' prefix for legacy single rows)
    $batchKeyJs = htmlspecialchars($row['batch_key'], ENT_QUOTES);
    $action = '<div class="action-group">'
            . '<button type="button" onclick="approveLeaveAddition(\'' . $batchKeyJs . '\', 1)" class="action-icon icon-approve" data-bs-toggle="tooltip" title="অনুমোদন"><i class="ti tabler-check"></i></button>'
            . '<button type="button" onclick="approveLeaveAddition(\'' . $batchKeyJs . '\', 2)" class="action-icon icon-reject" data-bs-toggle="tooltip" title="প্রত্যাখ্যান"><i class="ti tabler-x"></i></button>'
            . '</div>';

    $data[] = [
        'serial'        => '<span class="serial-num">' . $sl . '</span>',
        'employee_info' => $employeeInfo,
        'leave_type'    => $leaveTypeHtml,
        'leave_addition'=> $addHtml,
        'note'          => $noteHtml,
        'attachment'    => $attHtml,
        'action'        => $action,
    ];
}

echo json_encode([
    "draw"            => intval($request['draw']),
    "recordsTotal"    => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data"            => $data
]);
