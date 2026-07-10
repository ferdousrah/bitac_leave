<?php
/**
 * Global header search.
 * Accepts:
 *   - `q`         : application number (BITAC/2026/16 or trailing digits) OR employee ID
 *   - `leaveType` : optional filter — match applications that have a segment with this leaveID
 *
 * Returns JSON:
 *   {
 *     applications: [ { ... app + signatory chain + status } ],
 *     employees:    [ { ... employee + their pending apps } ]
 *   }
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (empty($_SESSION['username'])) { echo json_encode(['ok'=>false, 'error'=>'Not logged in']); exit; }

$q         = trim($_GET['q'] ?? '');
$leaveType = (int)($_GET['leaveType'] ?? 0);

// Need at least one of: query or leaveType filter
if ($q === '' && $leaveType <= 0) { echo json_encode(['ok'=>true, 'applications'=>[], 'employees'=>[]]); exit; }

// Re-query full user info (sidebar overwrite workaround)
$uStmt = mysqli_prepare($con, "SELECT user_id, employee_id, isCenterAdmin, user_group_id, organization_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
mysqli_stmt_close($uStmt);
if (!$me) { echo json_encode(['ok'=>false, 'error'=>'User not found']); exit; }

$myEmpID  = (int)($me['employee_id'] ?? 0);
$myOrgID  = (int)($me['organization_id'] ?? 0);
$isSuper  = ((int)($me['user_group_id'] ?? 0) === 1);
// Treat Regional Super Admin (group_id=7) as center-scoped admin (same as legacy Center Admin)
$isCenter = !empty($me['isCenterAdmin']) || (int)($me['user_group_id'] ?? 0) === 7;

// If signatory's org is unset, derive from employee_list
if (!$myOrgID && $myEmpID) {
    $oRes = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = $myEmpID");
    if ($oRes && ($r = mysqli_fetch_assoc($oRes))) $myOrgID = (int)$r['organization_id'];
}

// Helper: bn date "৫ মে ২০২৬"
$_bnDate = function($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $months = ['', 'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে'];
    $day = (int)date('j', strtotime($d));
    $mon = (int)date('n', strtotime($d));
    $yr  = (int)date('Y', strtotime($d));
    $bn = function($n) { return function_exists('banglaNumber') ? banglaNumber($n) : $n; };
    return $bn($day) . ' ' . $months[$mon] . ' ' . $bn($yr);
};

// Status label + color
$_statusLabel = function($s) {
    $map = [
        0 => ['অনুমোদনের অপেক্ষায়', '#d4a056', '#fef9e6'],
        1 => ['অনুমোদিত', '#5fa885', '#e8f5ee'],
        2 => ['বাতিল/অনুমোদিত হয়নি', '#c97777', '#fbeded'],
    ];
    return $map[(int)$s] ?? ['—', '#6b7280', '#f3f4f6'];
};

// ── Detect intent: is the query an app number or employee id?
$qStripped = trim($q);
$isAppNo  = (bool)preg_match('/(\d+)$/', $qStripped) || (bool)preg_match('/^\d+$/', $qStripped);
$candidateAppDataID = null;
if ($isAppNo) {
    if (preg_match('/(\d+)\s*$/', $qStripped, $m)) {
        $candidateAppDataID = (int)$m[1];
    }
}

$applications = [];
$employees    = [];

// ── Search by application: try matching application_no exact OR by trailing dataID
//    OR — when only leaveType is given (no query) — recent apps matching that type.
$shouldSearchApps = ($candidateAppDataID > 0) || ($leaveType > 0);
if ($shouldSearchApps) {
    // Center filter for non-super-admin
    $orgFilter = $isSuper ? "" : "AND el.organization_id = " . (int)$myOrgID;

    // Leave-type filter — restrict to apps that have a segment with the given leaveID
    $ltJoin   = '';
    $ltFilter = '';
    if ($leaveType > 0) {
        $ltJoin   = "INNER JOIN leave_application_segments lts
                       ON lts.applicationID = la.dataID
                      AND lts.leaveType = " . (int)$leaveType . "
                      AND (lts.kind = 'proposed' OR lts.kind IS NULL)";
        // Avoid duplicates if multiple segments match
        $ltFilter = "GROUP BY la.dataID";
    }

    // Match clause:
    //   - if there's a query → match dataID or application_no
    //   - if only leaveType  → match all (limit by recent submitDate)
    if ($candidateAppDataID > 0) {
        $matchClause = "(la.dataID = $candidateAppDataID
                         OR la.application_no = '" . mysqli_real_escape_string($con, $qStripped) . "')";
    } else {
        $matchClause = "1=1";
    }

    $orderClause = ($candidateAppDataID > 0) ? "" : "ORDER BY la.submitDate DESC, la.dataID DESC";
    $limit       = ($candidateAppDataID > 0) ? 5  : 15;

    $sql = "SELECT la.dataID, la.application_no, la.subject, la.dateFrom, la.dateTo,
                   la.submitDate, la.status, la.attachment, la.applicantID,
                   el.employee_name, el.employee_id AS emp_hr_id,
                   jt.job_title_name, s.section_name, o.organization_name
            FROM leave_applications la
            $ltJoin
            INNER JOIN employee_list el ON el.id = la.applicantID
            LEFT JOIN job_title jt ON jt.id = el.designation
            LEFT JOIN sections  s  ON s.id = el.section_id
            LEFT JOIN organization o ON o.id = el.organization_id
            WHERE $matchClause
            $orgFilter
            $ltFilter
            $orderClause
            LIMIT $limit";
    $r = mysqli_query($con, $sql);
    while ($r && ($app = mysqli_fetch_assoc($r))) {
        // Approval chain
        $chainQ = mysqli_query($con,
            "SELECT lda.dataID AS approvalID, lda.signatory, lda.isSupervisor, lda.isApproved,
                    lda.approvedDate, lda.serial, lda.note, lda.isSentbyAdmin,
                    el2.employee_name AS sig_name, jt2.job_title_name AS sig_title
             FROM leave_data_for_approval lda
             LEFT JOIN employee_list el2 ON el2.id = lda.signatory
             LEFT JOIN job_title jt2 ON jt2.id = el2.designation
             WHERE lda.leaveApplicationID = " . (int)$app['dataID'] . "
             ORDER BY lda.serial ASC, lda.dataID ASC");
        $chain = [];
        while ($cr = mysqli_fetch_assoc($chainQ)) $chain[] = [
            'serial'        => (int)$cr['serial'],
            'isSupervisor'  => (int)$cr['isSupervisor'],
            'isApproved'    => (int)$cr['isApproved'],
            'isSentbyAdmin' => (int)$cr['isSentbyAdmin'],
            'name'          => $cr['sig_name'] ?? '—',
            'title'         => $cr['sig_title'] ?? '',
            'approvedDate'  => $cr['approvedDate'] && $cr['approvedDate'] !== '0000-00-00' ? $_bnDate($cr['approvedDate']) : null,
            'note'          => $cr['note'] ?? '',
        ];

        // Segments (proposed for current state)
        $segQ = mysqli_query($con,
            "SELECT s.days, lt.leaveTitle FROM leave_application_segments s
             LEFT JOIN leave_types lt ON lt.leaveID = s.leaveType
             WHERE s.applicationID = " . (int)$app['dataID'] . " AND (s.kind = 'proposed' OR s.kind IS NULL)
             ORDER BY s.serial ASC, s.dataID ASC");
        $segs = [];
        $totalDays = 0;
        while ($sr = mysqli_fetch_assoc($segQ)) {
            $segs[] = ['days' => (int)$sr['days'], 'title' => $sr['leaveTitle'] ?? 'অজানা'];
            $totalDays += (int)$sr['days'];
        }

        $st = $_statusLabel($app['status']);
        $applications[] = [
            'dataID'       => (int)$app['dataID'],
            'app_no'       => $app['application_no'] ?: ('BITAC/' . date('Y', strtotime($app['submitDate'] ?: 'now')) . '/' . $app['dataID']),
            'subject'      => $app['subject'] ?? '',
            'applicant'    => $app['employee_name'] ?? '',
            'emp_hr_id'    => $app['emp_hr_id'] ?? '',
            'designation'  => $app['job_title_name'] ?? '',
            'section'      => $app['section_name'] ?? '',
            'organization' => $app['organization_name'] ?? '',
            'dateFrom'     => $_bnDate($app['dateFrom']),
            'dateTo'       => $_bnDate($app['dateTo']),
            'submitDate'   => $_bnDate($app['submitDate']),
            'totalDays'    => $totalDays,
            'segments'     => $segs,
            'status'       => (int)$app['status'],
            'statusLabel'  => $st[0],
            'statusColor'  => $st[1],
            'statusBg'     => $st[2],
            'chain'        => $chain,
        ];
    }
}

// ── Search by employee_id (HR ID) — show pending apps.
//    Skip when query is empty (only leaveType filter set) to avoid mass listings.
$empQ = false;
if ($qStripped !== '') {
    $qEmpEsc = mysqli_real_escape_string($con, $qStripped);
    $empOrgFilter = $isSuper ? "" : "AND el.organization_id = " . (int)$myOrgID;
    $empQ = mysqli_query($con,
        "SELECT el.id, el.employee_id, el.employee_name, jt.job_title_name, s.section_name, o.organization_name
         FROM employee_list el
         LEFT JOIN job_title jt ON jt.id = el.designation
         LEFT JOIN sections  s  ON s.id = el.section_id
         LEFT JOIN organization o ON o.id = el.organization_id
         WHERE el.employee_id = '$qEmpEsc' AND el.employment_status = 1 $empOrgFilter
         LIMIT 3");
}
while ($empQ && ($e = mysqli_fetch_assoc($empQ))) {
    $eID = (int)$e['id'];
    // Leave-type filter — restrict pending apps to those containing the given leaveID
    $pLtJoin = $leaveType > 0
        ? "INNER JOIN leave_application_segments pseg
              ON pseg.applicationID = la.dataID
             AND pseg.leaveType = " . (int)$leaveType . "
             AND (pseg.kind = 'proposed' OR pseg.kind IS NULL)"
        : '';
    $pLtGroup = $leaveType > 0 ? "GROUP BY la.dataID" : '';

    // Pending applications for this employee
    $pQ = mysqli_query($con,
        "SELECT la.dataID, la.application_no, la.dateFrom, la.dateTo, la.submitDate, la.status, la.subject
         FROM leave_applications la
         $pLtJoin
         WHERE la.applicantID = $eID AND la.status IN (0, 2)
         $pLtGroup
         ORDER BY la.submitDate DESC
         LIMIT 10");
    $pending = [];
    while ($p = mysqli_fetch_assoc($pQ)) {
        $segQ2 = mysqli_query($con,
            "SELECT s.days, lt.leaveTitle FROM leave_application_segments s
             LEFT JOIN leave_types lt ON lt.leaveID = s.leaveType
             WHERE s.applicationID = " . (int)$p['dataID'] . " AND (s.kind = 'proposed' OR s.kind IS NULL)
             ORDER BY s.serial ASC, s.dataID ASC");
        $totalP = 0; $segParts = [];
        while ($sr = mysqli_fetch_assoc($segQ2)) {
            $totalP += (int)$sr['days'];
            $segParts[] = ($sr['leaveTitle'] ?? '') . ' (' . (int)$sr['days'] . ')';
        }
        // Find the current signatory's name
        $cur = mysqli_query($con,
            "SELECT el2.employee_name AS curName FROM leave_data_for_approval lda
             LEFT JOIN employee_list el2 ON el2.id = lda.signatory
             WHERE lda.leaveApplicationID = " . (int)$p['dataID'] . " AND lda.isApproved = 0
               AND (lda.isSupervisor = 1 OR lda.isSentbyAdmin = 1)
               AND NOT EXISTS (
                   SELECT 1 FROM leave_data_for_approval prev
                   WHERE prev.leaveApplicationID = lda.leaveApplicationID
                     AND prev.serial < lda.serial AND prev.isApproved = 0
               )
             ORDER BY lda.serial ASC LIMIT 1");
        $curRow = $cur ? mysqli_fetch_assoc($cur) : null;

        $st2 = $_statusLabel($p['status']);
        $pending[] = [
            'dataID'      => (int)$p['dataID'],
            'app_no'      => $p['application_no'] ?: ('BITAC/' . date('Y', strtotime($p['submitDate'] ?: 'now')) . '/' . $p['dataID']),
            'subject'     => $p['subject'] ?? '',
            'dateFrom'    => $_bnDate($p['dateFrom']),
            'dateTo'      => $_bnDate($p['dateTo']),
            'totalDays'   => $totalP,
            'segParts'    => $segParts,
            'submitDate'  => $_bnDate($p['submitDate']),
            'status'      => (int)$p['status'],
            'statusLabel' => $st2[0],
            'statusColor' => $st2[1],
            'statusBg'    => $st2[2],
            'currentSig'  => $curRow['curName'] ?? null,
        ];
    }

    $employees[] = [
        'id'           => $eID,
        'employee_id'  => $e['employee_id'],
        'name'         => $e['employee_name'],
        'designation'  => $e['job_title_name'] ?? '',
        'section'      => $e['section_name'] ?? '',
        'organization' => $e['organization_name'] ?? '',
        'pendingCount' => count($pending),
        'pending'      => $pending,
    ];
}

echo json_encode([
    'ok' => true,
    'applications' => $applications,
    'employees'    => $employees,
], JSON_UNESCAPED_UNICODE);
