<?php
/**
 * DataTables source for the ছুটি সনদ অনুমোদন queue.
 * POST: status (0 pending / 1 approved / 2 rejected), centerFilter, yearFilter
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');
header('Content-Type: application/json');

$draw = (int)($_POST['draw'] ?? 0);

function out($draw, $rows = [], $total = 0) {
    echo json_encode([
        'draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $rows
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['username'])) out($draw);

$uStmt = mysqli_prepare($con, "SELECT employee_id, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt)) ?: [];
mysqli_stmt_close($uStmt);

$actorEmpId   = (int)($actor['employee_id']   ?? 0);
$isSuperAdmin = ((int)($actor['user_group_id'] ?? 0) === 1);

// Same gate as the page: the centres this actor is a certificate signatory for.
$allowedOrgIDs = [];
if ($actorEmpId > 0) {
    $sigQ = mysqli_query($con,
        "SELECT organization_id FROM leave_edit_approval_signatory
          WHERE employeeID = $actorEmpId AND organization_id > 0");
    // organization_id = 0 rows are legacy leftovers assigned to no centre. They
    // authorise nothing, so counting them would show an empty queue with no
    // explanation instead of the "you are not a signatory" notice.
    if ($sigQ) while ($r = mysqli_fetch_assoc($sigQ)) $allowedOrgIDs[] = (int)$r['organization_id'];
}
if (!$isSuperAdmin && empty($allowedOrgIDs)) out($draw);

$status = (int)($_POST['status'] ?? 0);
if (!in_array($status, [0, 1, 2], true)) $status = 0;

$where  = ["yls.isApproved = $status"];
if (!$isSuperAdmin) {
    $where[] = 'el.organization_id IN (' . implode(',', $allowedOrgIDs) . ')';
}
$center = (int)($_POST['centerFilter'] ?? 0);
if ($center > 0) $where[] = "el.organization_id = $center";

$year = trim((string)($_POST['yearFilter'] ?? ''));
if ($year !== '') $where[] = "yls.year = '" . mysqli_real_escape_string($con, $year) . "'";

$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c
     FROM yearly_leave_summary yls
     INNER JOIN employee_list el ON yls.employeeID = el.id
     $whereSql"))['c'] ?? 0);

$start  = max(0, (int)($_POST['start'] ?? 0));
$length = (int)($_POST['length'] ?? 10);
if ($length < 1) $length = 10;

$q = mysqli_query($con,
    "SELECT yls.*, el.employee_name, el.employee_id AS emp_code, el.organization_id,
            jt.job_title_name, o.organization_name,
            sig.employee_name AS signatory_name, sjt.job_title_name AS signatory_title
     FROM yearly_leave_summary yls
     INNER JOIN employee_list el ON yls.employeeID = el.id
     LEFT JOIN job_title    jt  ON jt.id = el.designation
     LEFT JOIN organization o   ON o.id  = el.organization_id
     LEFT JOIN employee_list sig ON sig.id = yls.signatory
     LEFT JOIN job_title    sjt ON sjt.id = sig.designation
     $whereSql
     ORDER BY yls.creationDate DESC, yls.leaveSummaryID DESC
     LIMIT $start, $length");

$rows = [];
$sl = $start;
while ($r = mysqli_fetch_assoc($q)) {
    $sl++;
    $id   = (int)$r['leaveSummaryID'];
    $name = $r['employee_name'] ?? '';

    $employee = '<div class="fw-semibold">' . htmlspecialchars($name)
              . ($r['emp_code'] !== '' ? ' <span class="text-muted small">(' . banglaNumber($r['emp_code']) . ')</span>' : '')
              . '</div><div class="text-muted small">' . htmlspecialchars($r['job_title_name'] ?? '') . '</div>';

    $centerCell = '<span class="badge bg-label-info">' . htmlspecialchars($r['organization_name'] ?? '—') . '</span>';

    $yearCell = '<div class="fw-semibold">' . banglaNumber($r['year']) . '</div>'
              . '<div class="text-muted small">স্মারক: ' . banglaNumber($r['memorial_number'] ?? '') . '</div>';

    // The three figures the certificate actually asserts.
    $figures = '<div class="small">'
             . 'পূর্ণ গড়: <strong>' . banglaNumber((int)$r['fullHalfSalaryInDays']) . '</strong> দিন<br>'
             . 'অর্ধ-গড়: <strong>'  . banglaNumber((int)$r['HalfSalaryInDays'])     . '</strong> দিন<br>'
             . 'বিনাবেতনে: <strong>' . banglaNumber((int)$r['withoutSalaryInDays']) . '</strong> দিন'
             . '</div>';

    $signatory = $r['signatory_name']
        ? '<div class="small">' . htmlspecialchars($r['signatory_name']) . '<br>'
          . '<span class="text-muted">' . htmlspecialchars($r['signatory_title'] ?? '') . '</span></div>'
        : '<span class="text-muted small">—</span>';

    $docUrl = '../../views/leave-certificate/documents/yearly-certificate.php?employeeID='
            . (int)$r['employeeID'] . '&year=' . (int)$r['year'];

    $actions = '<a href="javascript:void(0);" class="action-icon icon-view cert-view" '
             . 'data-url="' . htmlspecialchars($docUrl, ENT_QUOTES) . '" title="সনদ দেখুন">'
             . '<i class="ti tabler-eye"></i></a>';

    if ((int)$r['isApproved'] === 0) {
        $actions .= ' <a href="javascript:void(0);" class="action-icon icon-approve cert-approve" '
                  . 'data-id="' . $id . '" data-name="' . htmlspecialchars($name, ENT_QUOTES) . '" title="অনুমোদন করুন">'
                  . '<i class="ti tabler-check"></i></a>'
                  . ' <a href="javascript:void(0);" class="action-icon icon-reject cert-reject" '
                  . 'data-id="' . $id . '" data-name="' . htmlspecialchars($name, ENT_QUOTES) . '" title="অননুমোদিত করুন">'
                  . '<i class="ti tabler-x"></i></a>';
    } elseif ((int)$r['isApproved'] === 1) {
        $actions .= ' <span class="badge bg-label-success ms-1">'
                  . (!empty($r['approvedDate']) ? banglaNumber(date('d/m/Y', strtotime($r['approvedDate']))) : 'অনুমোদিত')
                  . '</span>';
    } else {
        $reason = trim((string)($r['rejectionReason'] ?? ''));
        $actions .= ' <span class="badge bg-label-danger ms-1" title="' . htmlspecialchars($reason, ENT_QUOTES) . '">অননুমোদিত</span>';
        if ($reason !== '') {
            $figures .= '<div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>'
                      . htmlspecialchars(mb_substr($reason, 0, 120)) . '</div>';
        }
    }

    $rows[] = [
        'sl'        => banglaNumber($sl),
        'employee'  => $employee,
        'center'    => $centerCell,
        'year'      => $yearCell,
        'figures'   => $figures,
        'signatory' => $signatory,
        'actions'   => $actions,
    ];
}

out($draw, $rows, $total);
