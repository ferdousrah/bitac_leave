<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$request = $_REQUEST;

// ── Resolve actor → org gating ───────────────────────────────────────
$sessionUsername = $_SESSION['username'] ?? '';
$actorEmpId = 0;
$actorUserGroup = 0;
if ($sessionUsername !== '') {
    $uStmt = mysqli_prepare($con,
        "SELECT employee_id, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($uStmt, 's', $sessionUsername);
    mysqli_stmt_execute($uStmt);
    $uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt)) ?: [];
    mysqli_stmt_close($uStmt);
    $actorEmpId     = (int)($uRow['employee_id']   ?? 0);
    $actorUserGroup = (int)($uRow['user_group_id'] ?? 0);
}

$isSuperAdmin = ($actorUserGroup === 1);

// Build org-gate clause: which organizations is the actor allowed to approve for?
$allowedOrgIDs = [];
if ($actorEmpId > 0) {
    $sigQ = mysqli_query($con,
        "SELECT organization_id FROM leave_edit_approval_signatory WHERE employeeID = $actorEmpId");
    if ($sigQ) while ($r = mysqli_fetch_assoc($sigQ)) $allowedOrgIDs[] = (int)$r['organization_id'];
}

$orgGate = '';
if (!$isSuperAdmin) {
    if (empty($allowedOrgIDs)) {
        // No signatory configured for this user — return empty result
        echo json_encode([
            "draw" => intval($request['draw'] ?? 0),
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => []
        ]);
        exit;
    }
    $orgGate = " AND el.organization_id IN (" . implode(',', $allowedOrgIDs) . ") ";
}

// ── Filter params ────────────────────────────────────
$centerFilter   = (int)($_REQUEST['centerFilter']   ?? 0);
$sectionFilter  = (int)($_REQUEST['sectionFilter']  ?? 0);
$employeeFilter = (int)($_REQUEST['employeeFilter'] ?? 0);

$filterClause = '';
if ($centerFilter   > 0) $filterClause .= " AND el.organization_id = $centerFilter";
if ($sectionFilter  > 0) $filterClause .= " AND el.section_id = $sectionFilter";
if ($employeeFilter > 0) $filterClause .= " AND el.id = $employeeFilter";

// Base query
$sqlBase = "FROM previous_leave_deduction pld
            INNER JOIN employee_list el ON pld.employeeID = el.id
            LEFT JOIN job_title jt ON el.designation = jt.id
            LEFT JOIN sections s ON el.section_id = s.id
            LEFT JOIN organization o ON el.organization_id = o.id
            WHERE pld.isApproved = 0
            $orgGate
            $filterClause";

$selectFields = "pld.*, el.employee_name, el.employee_id AS emp_code, el.photo, el.designation, el.section_id, el.organization_id AS emp_org,
                 jt.job_title_name, s.section_name, o.organization_name";

// Total
$totalData = mysqli_num_rows(mysqli_query($con, "SELECT pld.dataID $sqlBase"));
$totalFiltered = $totalData;

// Global search
$searchSql = '';
if (!empty($request['search']['value'])) {
    $sv = mysqli_real_escape_string($con, $request['search']['value']);
    $searchSql = " AND (el.employee_name LIKE '%$sv%'
                    OR el.employee_id LIKE '%$sv%'
                    OR jt.job_title_name LIKE '%$sv%'
                    OR s.section_name LIKE '%$sv%')";
    $totalFiltered = mysqli_num_rows(mysqli_query($con, "SELECT pld.dataID $sqlBase $searchSql"));
}

// Ordering & pagination
$start  = max(0, intval($request['start']  ?? 0));
$length = max(1, intval($request['length'] ?? 10));
$query = mysqli_query($con, "SELECT $selectFields $sqlBase $searchSql ORDER BY pld.dataID DESC LIMIT $start, $length");

$data = [];
$sl = $start;

/**
 * Build a leave-stat cell with ভোগকৃত + অবশিষ্ট + optional attachment.
 * Used and remaining are typically numeric day-counts but displayed as strings.
 */
function buildLeaveCell($used, $remaining, $file) {
    $hasUsed = ($used !== '' && $used !== null && $used !== '0');
    $hasRem  = ($remaining !== '' && $remaining !== null);
    if (!$hasUsed && !$hasRem && empty($file)) {
        return '<span class="text-muted small">—</span>';
    }
    $html = '<div class="prev-leave-cell">';
    if ($hasUsed) {
        $usedFmt = is_numeric($used) ? banglaNumber($used) . ' দিন' : htmlspecialchars($used);
        $html .= '<div class="prev-leave-row"><span class="prev-leave-label">ভোগকৃত</span><span class="days-pill days-pill-warning">' . $usedFmt . '</span></div>';
    }
    if ($hasRem) {
        $remFmt = is_numeric($remaining) ? banglaNumber($remaining) . ' দিন' : htmlspecialchars($remaining);
        $html .= '<div class="prev-leave-row"><span class="prev-leave-label">অবশিষ্ট</span><span class="days-pill days-pill-info">' . $remFmt . '</span></div>';
    }
    if (!empty($file)) {
        $html .= '<a href="../../uploads/' . htmlspecialchars($file) . '" target="_blank" class="prev-leave-file" data-bs-toggle="tooltip" title="ফাইল দেখুন"><i class="ti tabler-paperclip"></i> ফাইল</a>';
    }
    $html .= '</div>';
    return $html;
}

while ($row = mysqli_fetch_assoc($query)) {
    $sl++;

    // ── Employee cell with avatar ────────────────────
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
    $empSubLine = $empJob;
    $empSubLight = trim($empSec . ($empSec && $empOrg ? ' • ' : '') . $empOrg);
    $employeeInfo = '<div class="emp-cell">' . $avatarHtml
                  . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName) . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '') . '</div>'
                  . ($empSubLine ? '<div class="emp-sub">' . htmlspecialchars($empSubLine) . '</div>' : '')
                  . ($empSubLight ? '<div class="emp-sub-light">' . htmlspecialchars($empSubLight) . '</div>' : '')
                  . '</div></div>';

    $action = '<div class="action-group">'
            . '<button type="button" onclick="approveLeaveInfo(' . (int)$row['dataID'] . ', 1)" class="action-icon icon-approve" data-bs-toggle="tooltip" title="অনুমোদন"><i class="ti tabler-check"></i></button>'
            . '<button type="button" onclick="approveLeaveInfo(' . (int)$row['dataID'] . ', 2)" class="action-icon icon-reject" data-bs-toggle="tooltip" title="প্রত্যাখ্যান"><i class="ti tabler-x"></i></button>'
            . '</div>';

    $data[] = [
        'serial'            => '<span class="serial-num">' . $sl . '</span>',
        'employee_info'     => $employeeInfo,
        'avg_salary'        => buildLeaveCell($row['avgSalary'], $row['avgSalaryNote'] ?? '', $row['avgSalaryFile'] ?? ''),
        'half_avg_salary'   => buildLeaveCell($row['halfAvgSalary'], $row['halfAvgSalaryNote'] ?? '', $row['halfAvgSalaryFile'] ?? ''),
        'casual'            => buildLeaveCell($row['casual'], $row['casualNote'] ?? '', $row['casualFile'] ?? ''),
        'leave_without_pay' => buildLeaveCell($row['leaveWithoutPay'], $row['leaveWithoutPayNote'] ?? '', $row['leaveWithoutPayFile'] ?? ''),
        'undeductible'      => buildLeaveCell($row['undeductibleLeave'], $row['undeductibleLeaveRemaining'] ?? '', $row['undeductibleLeaveFile'] ?? ''),
        'action'            => $action,
    ];
}

echo json_encode([
    "draw" => intval($request['draw'] ?? 0),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);

mysqli_close($con);
