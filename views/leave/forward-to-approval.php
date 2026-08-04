<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$leaveApplicationID = intval($_GET['leaveApplicationID'] ?? 0);
$menuslug           = htmlspecialchars($_GET['menuslug'] ?? 'allowed-leave-applications');

if (!$leaveApplicationID) {
    echo '<div class="alert alert-danger">অবৈধ অনুরোধ।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Get leave application with leave type name
$appStmt = mysqli_prepare($con,
    "SELECT la.*, lt.leaveTitle
     FROM leave_applications la
     LEFT JOIN leave_types lt ON la.leaveType = lt.leaveID
     WHERE la.dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($appStmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));

if (!$app) {
    echo '<div class="alert alert-danger">ছুটির আবেদন পাওয়া যায়নি।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Applicant details
$empStmt = mysqli_prepare($con,
    "SELECT el.*, jt.job_title_name, s.section_name, o.organization_name
     FROM employee_list el
     LEFT JOIN job_title jt  ON el.designation    = jt.id
     LEFT JOIN sections  s   ON el.section_id     = s.id
     LEFT JOIN organization o ON el.organization_id = o.id
     WHERE el.id = ? LIMIT 1");
mysqli_stmt_bind_param($empStmt, 'i', $app['applicantID']);
mysqli_stmt_execute($empStmt);
$emp = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));

//
$stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
$stmt->bind_param("i", $app['designation_id']);
$stmt->execute();
$designationData = $stmt->get_result()->fetch_assoc();
$stmt->close();
        
$stmt = $con->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->bind_param("i", $app['section_id']);
$stmt->execute();
$sectionData = $stmt->get_result()->fetch_assoc();
$stmt->close();
        
$stmt = $con->prepare("SELECT * FROM organization WHERE id = ?");
$stmt->bind_param("i", $app['organization_id']);
$stmt->execute();
$orgData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// All leave types
$leaveTypesQ = mysqli_query($con, "SELECT * FROM leave_types ORDER BY leaveTitle ASC");
$leaveTypes  = [];
while ($lt = mysqli_fetch_assoc($leaveTypesQ)) $leaveTypes[] = $lt;

// Leave templates (type 2 = admin note)
$templatesQ = mysqli_query($con, "SELECT * FROM leave_templates WHERE templateType=2");
$templates  = [];
while ($t = mysqli_fetch_assoc($templatesQ)) $templates[] = $t;

// Auto-migrate: leave_notice_copy needs a `label` column so the 3 fixed
// অনুলিপি entries (প্রশাসন বিভাগ / ব্যক্তিগত নথি / অফিস কপি) can be
// stored as rows alongside real employees, letting the admin drag/reorder
// the whole list instead of forcing defaults to always sit at the top.
$__colChk = mysqli_query($con, "SHOW COLUMNS FROM leave_notice_copy LIKE 'label'");
if ($__colChk && mysqli_num_rows($__colChk) === 0) {
    mysqli_query($con, "ALTER TABLE leave_notice_copy ADD COLUMN label VARCHAR(255) NULL AFTER employeeID");
}

// Existing copy-to list (mixed: label rows + employee rows)
$copyToStmt = mysqli_prepare($con,
    "SELECT * FROM leave_notice_copy WHERE applicationID=? ORDER BY serial ASC, dataID ASC");
mysqli_stmt_bind_param($copyToStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($copyToStmt);
$copyToRes  = mysqli_stmt_get_result($copyToStmt);
$copyToList = [];
while ($ct = mysqli_fetch_assoc($copyToRes)) $copyToList[] = $ct;

// First-time seed — if this application has never had its default
// অনুলিপি labels attached, load the configured defaults from the
// default_notice_copies table (managed via কনফিগারেশন → ডিফল্ট অনুলিপি)
// and prepend them as in-memory rows. `{center}` in the label is
// replaced with the applicant's org name at render time. Center is
// pulled from leave_applications.organization_id — not the applicant's
// current employee record — so a later transfer never rewrites an old
// notice.
$_hasAnyCopy = !empty($copyToList);
$_hasLabelRow = false;
foreach ($copyToList as $_ct) {
    if (!empty(trim($_ct['label'] ?? ''))) { $_hasLabelRow = true; break; }
}
if (!$_hasAnyCopy || !$_hasLabelRow) {
    // Pull configured defaults from DB — table auto-created + seeded
    // by views/default-notice-copies/manage.php. Fall back to the
    // legacy hardcoded trio if the table doesn't exist yet.
    $_seedLabels = [];
    $__tblChk = mysqli_query($con, "SHOW TABLES LIKE 'default_notice_copies'");
    if ($__tblChk && mysqli_num_rows($__tblChk) > 0) {
        $_dcQ = mysqli_query($con,
            "SELECT label FROM default_notice_copies
             WHERE isActive = 1
             ORDER BY serial ASC, dataID ASC");
        while ($_dcQ && $_dcR = mysqli_fetch_assoc($_dcQ)) $_seedLabels[] = $_dcR['label'];
    }
    if (empty($_seedLabels)) {
        $_seedLabels = [
            'প্রশাসন বিভাগ, বিটাক, {center}',
            'ব্যক্তিগত নথির কপি',
            'অফিস কপি',
        ];
    }
    // Substitute {center} with the applicant's org name
    $_centerName = trim($orgData['organization_name'] ?? '—');
    $_seedLabels = array_map(function ($lbl) use ($_centerName) {
        return str_replace('{center}', $_centerName, $lbl);
    }, $_seedLabels);

    // Bump existing employee-row serials to make room at 1..N
    $__seedCount = count($_seedLabels);
    foreach ($copyToList as &$_r) { $_r['serial'] = (int)$_r['serial'] + $__seedCount; }
    unset($_r);
    $_seedRows = [];
    foreach ($_seedLabels as $_i => $_lbl) {
        $_seedRows[] = [
            'dataID'          => 0,
            'employeeID'      => 0,
            'label'           => $_lbl,
            'organization_id' => 0,
            'section_id'      => 0,
            'designation_id'  => 0,
            'applicationID'   => $leaveApplicationID,
            'serial'          => $_i + 1,
        ];
    }
    $copyToList = array_merge($_seedRows, $copyToList);
}

// All active employees (for copy-to dropdown)
$empListQ = mysqli_query($con,
    "SELECT id, employee_id, employee_name FROM employee_list WHERE employment_status=1 ORDER BY employee_name ASC");
$empList = [];
while ($e = mysqli_fetch_assoc($empListQ)) $empList[] = $e;

// Proposed dates (use approved if set, otherwise requested)
$aDateFrom = !empty($app['approvedDateFrom']) ? $app['approvedDateFrom'] : $app['dateFrom'];
$aDateTo   = !empty($app['approvedDateTo'])   ? $app['approvedDateTo']   : $app['dateTo'];
$dateDiff  = abs((int)round((strtotime($aDateTo) - strtotime($aDateFrom)) / 86400)) + 1;
$reqDiff   = abs((int)round((strtotime($app['dateTo']) - strtotime($app['dateFrom'])) / 86400)) + 1;

// ── Segments: requested (frozen) + proposed (mutable) ──────────────────
$segStmt = mysqli_prepare($con,
    "SELECT * FROM leave_application_segments
     WHERE applicationID = ?
     ORDER BY kind DESC, serial ASC, dateFrom ASC");
mysqli_stmt_bind_param($segStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($segStmt);
$allSegs = mysqli_fetch_all(mysqli_stmt_get_result($segStmt), MYSQLI_ASSOC);
mysqli_stmt_close($segStmt);

$requestedSegs = array_values(array_filter($allSegs, function($s){ return ($s['kind'] ?? 'requested') === 'requested'; }));
$proposedSegs  = array_values(array_filter($allSegs, function($s){ return ($s['kind'] ?? 'requested') === 'proposed'; }));
if (empty($requestedSegs) && empty($proposedSegs))      { $requestedSegs = $allSegs; $proposedSegs = $allSegs; }
else if (empty($proposedSegs))                          { $proposedSegs  = $requestedSegs; }
else if (empty($requestedSegs))                         { $requestedSegs = $proposedSegs; }

// Leave-type id → title map
$leaveTypeMap = [];
foreach ($leaveTypes ?? [] as $lt) { $leaveTypeMap[(int)$lt['leaveID']] = $lt['leaveTitle']; }
// $leaveTypes is loaded later — preload now for table rendering
if (empty($leaveTypeMap)) {
    $ltQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types ORDER BY leaveTitle ASC");
    while ($lt = mysqli_fetch_assoc($ltQ)) $leaveTypeMap[(int)$lt['leaveID']] = $lt['leaveTitle'];
}

// Edit history
$histStmt = mysqli_prepare($con,
    "SELECT h.*, el.employee_name FROM leave_segment_history h
     LEFT JOIN user_list ul ON h.changedBy = ul.dataID
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE h.applicationID = ?
     ORDER BY h.changedAt ASC, h.dataID ASC");
mysqli_stmt_bind_param($histStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($histStmt);
$segHistory = mysqli_fetch_all(mysqli_stmt_get_result($histStmt), MYSQLI_ASSOC);
mysqli_stmt_close($histStmt);

// Return history (so admin sees why a returned-to-admin app came back).
// Chain order: oldest first → supervisor → signatories in order.
$retStmt = mysqli_prepare($con,
    "SELECT * FROM leave_return_history
     WHERE leaveApplicationID = ?
     ORDER BY createdAt ASC, dataID ASC");
mysqli_stmt_bind_param($retStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($retStmt);
$returnHistory = mysqli_fetch_all(mysqli_stmt_get_result($retStmt), MYSQLI_ASSOC);
mysqli_stmt_close($retStmt);

// Supervisor's recommendation note + info
$supStmt = mysqli_prepare($con,
    "SELECT lda.note, lda.approvedDate, lda.approvedDays,
            el.employee_name, jt.job_title_name
     FROM leave_data_for_approval lda
     LEFT JOIN employee_list el ON lda.signatory = el.id
     LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE lda.leaveApplicationID = ? AND lda.isSupervisor = 1 AND lda.isApproved = 1
     LIMIT 1");
mysqli_stmt_bind_param($supStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($supStmt);
$supInfo = mysqli_fetch_assoc(mysqli_stmt_get_result($supStmt));
mysqli_stmt_close($supStmt);

// ── Previous comments thread (mirrors approve-application.php) ─────────────
// All approved signatories with name, designation, comment
$sigHistoryStmt = mysqli_prepare($con,
    "SELECT ldfa.signatory, ldfa.note, ldfa.approvedDate, ldfa.isSupervisor, ldfa.isApproved,
            el.employee_name, jt.job_title_name
     FROM leave_data_for_approval ldfa
     LEFT JOIN employee_list el ON ldfa.signatory = el.id
     LEFT JOIN job_title jt     ON el.designation = jt.id
     WHERE ldfa.leaveApplicationID = ? AND ldfa.isApproved = 1
     ORDER BY ldfa.serial ASC");
mysqli_stmt_bind_param($sigHistoryStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($sigHistoryStmt);
$sigHistory = mysqli_fetch_all(mysqli_stmt_get_result($sigHistoryStmt), MYSQLI_ASSOC);
mysqli_stmt_close($sigHistoryStmt);

// Return / send-back history
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS leave_return_history (
        dataID INT AUTO_INCREMENT PRIMARY KEY,
        leaveApplicationID INT NOT NULL,
        returnedBy INT NOT NULL,
        returnedByName VARCHAR(255),
        returnedByTitle VARCHAR(255),
        returnedTo INT DEFAULT 0,
        returnedToName VARCHAR(255),
        returnType ENUM('to_applicant', 'to_previous_signatory', 'to_admin') NOT NULL,
        note TEXT NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app (leaveApplicationID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$returnStmtF = mysqli_prepare($con,
    "SELECT * FROM leave_return_history WHERE leaveApplicationID = ? ORDER BY createdAt ASC, dataID ASC");
mysqli_stmt_bind_param($returnStmtF, 'i', $leaveApplicationID);
mysqli_stmt_execute($returnStmtF);
$returnHistory = mysqli_fetch_all(mysqli_stmt_get_result($returnStmtF), MYSQLI_ASSOC);
mysqli_stmt_close($returnStmtF);

// Resubmission events — each time the applicant edited-and-resubmitted after
// a পুনঃ যাচাই. Sourced from audit_log (action='leave_application_resubmitted').
$resubmitHistory = [];
// Approval timestamps — leave_data_for_approval.approvedDate is DATE-precision
// (00:00:00), so a supervisor who approved at 19:09 the SAME day as an
// earlier 17:13 return sorts BEFORE the return. audit_log carries the full
// datetime for each approval action; we key by (target_id, actor name) so
// events land in true chronological order.
$approvalAuditTs = []; // key = actor_name → latest audit createdAt for approval actions

$_alChk = mysqli_query($con, "SHOW TABLES LIKE 'audit_log'");
if ($_alChk && mysqli_num_rows($_alChk) > 0) {
    $resStmt = mysqli_prepare($con,
        "SELECT actor_user_id, actor_name, note, createdAt
         FROM audit_log
         WHERE action = 'leave_application_resubmitted'
           AND target_type = 'leave_application'
           AND target_id = ?
         ORDER BY createdAt ASC, dataID ASC");
    if ($resStmt) {
        mysqli_stmt_bind_param($resStmt, 'i', $leaveApplicationID);
        mysqli_stmt_execute($resStmt);
        $resubmitHistory = mysqli_fetch_all(mysqli_stmt_get_result($resStmt), MYSQLI_ASSOC);
        mysqli_stmt_close($resStmt);
    }

    // Pull approval-action audit rows. $approvalAuditTs holds the LATEST
    // ts per (actor, action) — used for the current-state event.
    // $approvalAuditEventsAll keeps every row so superseded approvals
    // (approve → rewind → re-approve) can be rendered as extra historical
    // events; without this the earlier approval was invisible.
    $approvalAuditEventsAll = [];
    $apvStmt = mysqli_prepare($con,
        "SELECT actor_name, action, createdAt
         FROM audit_log
         WHERE target_type = 'leave_application'
           AND target_id = ?
           AND action IN ('leave_recommended', 'leave_chain_approved', 'leave_approved')
         ORDER BY createdAt ASC, dataID ASC");
    if ($apvStmt) {
        mysqli_stmt_bind_param($apvStmt, 'i', $leaveApplicationID);
        mysqli_stmt_execute($apvStmt);
        $apvRes = mysqli_stmt_get_result($apvStmt);
        while ($ar = mysqli_fetch_assoc($apvRes)) {
            $_n = trim($ar['actor_name'] ?? '');
            if ($_n === '') continue;
            $_k = $_n . '|' . $ar['action'];
            $approvalAuditTs[$_k] = strtotime($ar['createdAt']);
            $approvalAuditEventsAll[] = [
                'name'   => $_n,
                'action' => $ar['action'],
                'ts'     => strtotime($ar['createdAt']),
            ];
        }
        mysqli_stmt_close($apvStmt);
    }
}

// Admin note history (if the app has been forwarded and returned before)
$adminNoteHistory = [];
$adminNoteChk = mysqli_query($con, "SHOW TABLES LIKE 'leave_admin_note_history'");
if ($adminNoteChk && mysqli_num_rows($adminNoteChk) > 0) {
    $anhStmt = mysqli_prepare($con,
        "SELECT * FROM leave_admin_note_history WHERE leaveApplicationID = ? ORDER BY createdAt ASC");
    mysqli_stmt_bind_param($anhStmt, 'i', $leaveApplicationID);
    mysqli_stmt_execute($anhStmt);
    $adminNoteHistory = mysqli_fetch_all(mysqli_stmt_get_result($anhStmt), MYSQLI_ASSOC);
    mysqli_stmt_close($anhStmt);
}

// Build thread events chronologically
$threadEvents = [];

// 1) Applicant's original submission — with an explicit "→ supervisor"
// hint so the reader can tell who the applicant sent it to first,
// otherwise that initial routing step is invisible in the timeline.
$applicantTs = !empty($app['submitDate']) ? strtotime($app['submitDate']) : 0;
$applicantBodyParts = [];
if (!empty($app['subject'])) $applicantBodyParts[] = '<strong>বিষয়:</strong> ' . htmlspecialchars($app['subject']);
if (!empty(trim($app['leaveApplication'] ?? ''))) $applicantBodyParts[] = nl2br(htmlspecialchars($app['leaveApplication']));
$_supName = '';
foreach ($sigHistory as $_sh) {
    if ((int)($_sh['isSupervisor'] ?? 0) === 1) {
        $_supName = trim($_sh['employee_name'] ?? '');
        break;
    }
}
$threadEvents[] = [
    'ts'    => $applicantTs,
    'order' => 0,
    'name'  => $emp['employee_name'] ?? 'আবেদনকারী',
    'title' => trim(($emp['job_title_name'] ?? '') . (!empty($emp['section_name']) ? ', ' . $emp['section_name'] : '')),
    'badge' => ['আবেদনকারী', '#e8e5ff', '#5648c4'],
    'color' => '#6c5ce7',
    'icon'  => 'tabler-user',
    'extra' => $_supName !== '' ? '→ ' . htmlspecialchars($_supName) . ' (সুপারভাইজার)' : '',
    'body'  => $applicantBodyParts ? implode('<br>', $applicantBodyParts) : '<em class="text-muted">— কোনো বিবরণ নেই —</em>',
];

// 2) Supervisor's recommendation
foreach ($sigHistory as $sig) {
    if ((int)$sig['isSupervisor'] !== 1) continue;
    $_sigName = $sig['employee_name'] ?? '';
    $_ts = $approvalAuditTs[$_sigName . '|leave_recommended']
        ?? (!empty($sig['approvedDate']) ? strtotime($sig['approvedDate']) : 0);
    $threadEvents[] = [
        'ts'    => $_ts,
        'order' => 1,
        'name'  => $_sigName,
        'title' => $sig['job_title_name'] ?? '',
        'badge' => ['বিভাগীয় প্রধান', '#d1f4ff', '#0883a3'],
        'color' => '#0dcaf0',
        'icon'  => 'tabler-circle-check',
        'body'  => !empty(trim($sig['note'] ?? '')) ? nl2br(htmlspecialchars($sig['note'])) : '<em class="text-muted">কোনো মন্তব্য নেই</em>',
    ];
}

// 3) Past admin notes (from prior forwards, if any)
if (!empty($adminNoteHistory)) {
    foreach ($adminNoteHistory as $anh) {
        $threadEvents[] = [
            'ts'    => !empty($anh['createdAt']) ? strtotime($anh['createdAt']) : 0,
            'order' => 2,
            'name'  => $anh['adminInitiatorName'] ?? 'নোট উপস্থাপনকারী',
            'title' => $anh['adminInitiatorTitle'] ?? '',
            'badge' => ['নোট উপস্থাপনকারী', '#ede5fa', '#5e3eaa'],
            'color' => '#6f42c1',
            'icon'  => 'tabler-user-edit',
            'body'  => nl2br(htmlspecialchars($anh['note'])),
        ];
    }
}

// 4) Non-supervisor signatories who already approved (rare at forward stage but supports re-forward after return)
foreach ($sigHistory as $sig) {
    if ((int)$sig['isSupervisor'] === 1) continue;
    $_sigName = $sig['employee_name'] ?? '';
    $_ts = $approvalAuditTs[$_sigName . '|leave_chain_approved']
        ?? $approvalAuditTs[$_sigName . '|leave_approved']
        ?? (!empty($sig['approvedDate']) ? strtotime($sig['approvedDate']) : 0);
    $threadEvents[] = [
        'ts'    => $_ts,
        'order' => 3,
        'name'  => $_sigName,
        'title' => $sig['job_title_name'] ?? '',
        'badge' => ['অনুমোদনকারী', '#d8f5e3', '#1a7e44'],
        'color' => '#28c76f',
        'icon'  => 'tabler-circle-check',
        'body'  => !empty(trim($sig['note'] ?? '')) ? nl2br(htmlspecialchars($sig['note'])) : '<em class="text-muted">কোনো মন্তব্য নেই</em>',
    ];
}

// 4b) Historical approvals — sigHistory holds only the LATEST state, so
// a signatory who approved, was rewound, and re-approved appears just
// once. Iterate audit_log to add EXTRA events for older approvals.
// Historical body is generic (specific notes weren't preserved).
foreach ($approvalAuditEventsAll as $ae) {
    $_aeName   = $ae['name'];
    $_aeAction = $ae['action'];
    $_isSupAction = ($_aeAction === 'leave_recommended');
    $_currentTs = $_isSupAction
        ? ($approvalAuditTs[$_aeName . '|leave_recommended'] ?? 0)
        : ($approvalAuditTs[$_aeName . '|leave_chain_approved']
           ?? $approvalAuditTs[$_aeName . '|leave_approved'] ?? 0);
    if ($_currentTs > 0 && $ae['ts'] === $_currentTs) continue;

    if ($_isSupAction) {
        $threadEvents[] = [
            'ts'    => $ae['ts'],
            'order' => 1,
            'name'  => $_aeName,
            'title' => '',
            'badge' => ['বিভাগীয় প্রধান (পূর্ববর্তী)', '#d1f4ff', '#0883a3'],
            'color' => '#0dcaf0',
            'icon'  => 'tabler-history',
            'body'  => '<em class="text-muted">সিদ্ধান্ত নেওয়া হয়েছিল (পরবর্তীতে পুনর্বিবেচনার আগে)</em>',
        ];
    } else {
        $threadEvents[] = [
            'ts'    => $ae['ts'],
            'order' => 3,
            'name'  => $_aeName,
            'title' => '',
            'badge' => ['অনুমোদনকারী (পূর্ববর্তী)', '#d8f5e3', '#1a7e44'],
            'color' => '#28c76f',
            'icon'  => 'tabler-history',
            'body'  => '<em class="text-muted">অনুমোদন করা হয়েছিল (ফেরতের কারণে পুনর্বিবেচনার আগে)</em>',
        ];
    }
}

// 5) Return / send-back entries
foreach ($returnHistory as $rh) {
    $rType  = $rh['returnType'] ?? '';
    $rLabel = $rType === 'to_applicant'           ? 'আবেদনকারীর কাছে ফেরত'
            : ($rType === 'to_previous_signatory' ? 'পূর্ববর্তীর কাছে ফেরত'
            : ($rType === 'to_admin'              ? 'প্রশাসনের কাছে ফেরত' : 'ফেরত'));
    $threadEvents[] = [
        'ts'    => !empty($rh['createdAt']) ? strtotime($rh['createdAt']) : 0,
        'order' => 4,
        'name'  => $rh['returnedByName'] ?? '—',
        'title' => $rh['returnedByTitle'] ?? '',
        'badge' => [$rLabel, '#fff3e1', '#b8651a'],
        'color' => '#d4a056',
        'icon'  => 'tabler-corner-up-left',
        'extra' => !empty($rh['returnedToName']) ? '→ ' . htmlspecialchars($rh['returnedToName']) : '',
        'body'  => !empty(trim($rh['note'] ?? '')) ? nl2br(htmlspecialchars($rh['note'])) : '<em class="text-muted">কোনো কারণ লেখা হয়নি</em>',
    ];
}

// 6) Resubmission events — applicant edited + resent after পুনঃ যাচাই.
// Sourced from audit_log; body is a friendly description because the
// audit row's `note` is just an internal marker (e.g. "segments=1").
foreach ($resubmitHistory as $rs) {
    $threadEvents[] = [
        'ts'    => !empty($rs['createdAt']) ? strtotime($rs['createdAt']) : 0,
        'order' => 5,
        'name'  => $rs['actor_name'] ?? ($emp['employee_name'] ?? 'আবেদনকারী'),
        'title' => trim(($emp['job_title_name'] ?? '') . (!empty($emp['section_name']) ? ', ' . $emp['section_name'] : '')),
        'badge' => ['পুনঃ যাচাইয়ের পর জমা', '#e0f3ff', '#0d63a3'],
        'color' => '#3aa1e0',
        'icon'  => 'tabler-refresh',
        'body'  => 'আবেদনটি সংশোধন করে পুনরায় জমা দেওয়া হয়েছে।',
    ];
}

usort($threadEvents, function($a, $b) {
    if ($a['ts'] === $b['ts']) return $a['order'] <=> $b['order'];
    return $a['ts'] <=> $b['ts'];
});

$threadHtml = '';
if (!empty($threadEvents)) {
    $threadHtml = '<div class="thread-wrap" id="commentThreadFwd">';
    foreach ($threadEvents as $idx => $ev) {
        $dateStr = $ev['ts'] ? date('d/m/Y', $ev['ts']) : '';
        $isLast  = $idx === count($threadEvents) - 1;
        $threadHtml .= '<div class="thread-item' . ($isLast ? ' is-last' : '') . '">'
            . '<div class="thread-avatar" style="background:' . htmlspecialchars($ev['color']) . ';">'
            .   '<i class="ti ' . htmlspecialchars($ev['icon']) . '"></i>'
            . '</div>'
            . '<div class="thread-bubble" style="--bubble-accent:' . htmlspecialchars($ev['color']) . ';">'
            .   '<div class="thread-bubble-head">'
            .     '<span class="thread-name">' . htmlspecialchars($ev['name']) . '</span>'
            .     (!empty($ev['title']) ? '<span class="thread-title">— ' . htmlspecialchars($ev['title']) . '</span>' : '')
            .     '<span class="thread-badge" style="background:' . htmlspecialchars($ev['badge'][1]) . ';color:' . htmlspecialchars($ev['badge'][2]) . ';">'
            .       htmlspecialchars($ev['badge'][0])
            .     '</span>'
            .     (!empty($ev['extra']) ? '<span class="thread-extra">' . $ev['extra'] . '</span>' : '')
            .     ($dateStr ? '<span class="thread-time"><i class="ti tabler-clock me-1"></i>' . $dateStr . '</span>' : '')
            .   '</div>'
            .   '<div class="thread-bubble-body">' . $ev['body'] . '</div>'
            . '</div>'
            . '</div>';
    }
    $threadHtml .= '</div>';
}

// Styles are emitted inline (inside the turbo-frame) so they swap with the
// page content on Turbo navigation — see footer_vuexy.php where the frame closes.
?>
<style>
/* ── Forward page styles ─────────────────────────────── */
.info-label { font-weight: 500; color: #5d6580; font-size: 0.88rem; }

.fwd-card { border-radius: 0.75rem; }
.fwd-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .fwd-card .card-body { padding: 1rem; }
}

/* Action button — balance */
.btn-balance {
    background: linear-gradient(135deg, #6c5ce7 0%, #5648c4 100%);
    color: #fff;
    border: none;
    transition: filter 0.15s ease;
}
.btn-balance:hover, .btn-balance:focus { color: #fff; filter: brightness(1.08); }

/* Return-from-signatory banner (admin sees why app came back) */
.ret-banner {
    background: #fff7e6;
    border: 1px solid #ffd699;
    border-left: 3px solid #d97706;
    border-radius: 0.5rem;
    padding: 12px 14px;
}
.ret-banner-head {
    font-size: 0.85rem;
    color: #7a4d00;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-bottom: 0.4rem;
}
.ret-banner-body {
    font-size: 0.9rem;
    color: #2c2e3a;
    line-height: 1.6;
}

/* Supervisor recommendation card */
.sup-recommend {
    background: #f0faf4;
    border: 1px solid #c4ebd4;
    border-left: 3px solid #1a7e44;
    border-radius: 0.5rem;
    padding: 12px 14px;
}
.sup-recommend-head {
    font-size: 0.85rem;
    color: #1a7e44;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-bottom: 0.4rem;
}
.sup-recommend-body {
    font-size: 0.9rem;
    color: #2c2e3a;
    line-height: 1.6;
}

/* Segment tables */
.seg-table-wrap {
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    background: #fafbfd;
    padding: 0.5rem 0.6rem;
}
.seg-table-wrap.is-proposed {
    background: #faf9ff;
    border-color: #ddd5f6;
}
.seg-table-wrap .table { margin-bottom: 0; }
.seg-table-wrap thead th {
    font-size: 0.78rem;
    color: #5d6580;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #eef0f5;
    padding: 0.5rem 0.6rem;
    background: transparent !important;
}
.seg-table-wrap tbody td {
    font-size: 0.86rem;
    padding: 0.55rem 0.6rem;
    color: #2c2e3a;
    vertical-align: middle;
    border-bottom: 1px solid #f3f4fa;
}
.seg-table-wrap tbody tr:last-child td { border-bottom: 0; }
.seg-table-wrap tbody tr.total-row td {
    background: #f0edff;
    color: #5648c4;
    font-weight: 700;
    border-top: 1px solid #ddd5f6;
}
.seg-table-wrap.is-proposed tbody tr.total-row td { background: #efeaff; }

.seg-helper {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 0.4rem;
}

/* App-type badges */
.app-type-badge {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.45em 0.85em;
    border-radius: 0.4rem;
}

/* Form focus polish */
#forwardForm .form-control:focus,
#forwardForm .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}

/* Copy-to section */
.copyto-section-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2c2e3a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1.75rem 0 1rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #eef0f5;
}
.copyto-section-title .ti-tile {
    width: 28px; height: 28px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.4rem;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.95rem;
}

#copyToTable thead th {
    background: #fafbfd !important;
    font-size: 0.78rem;
    color: #5d6580;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #eef0f5;
}
#copyToTable tbody td {
    vertical-align: middle;
    font-size: 0.88rem;
}
#copyToTable .row-serial { color: #5d6580; font-weight: 500; }

/* Bottom actions */
.fwd-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.25rem;
}

/* Modals */
#segmentEditModal .modal-content,
#segmentHistoryModal .modal-content {
    border: none;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
#segmentEditModal .modal-header,
#segmentHistoryModal .modal-header,
#prevCommentsModal .modal-header {
    background: linear-gradient(135deg, #6c5ce7 0%, #5648c4 100%);
    color: #fff;
    border: none;
    padding: 16px 22px;
}
#segmentEditModal .modal-title,
#segmentHistoryModal .modal-title,
#prevCommentsModal .modal-title { color: #fff !important; font-weight: 600; }
#segmentEditModal .btn-close,
#segmentHistoryModal .btn-close,
#prevCommentsModal .btn-close { filter: brightness(0) invert(1); opacity: 0.85; }

/* Custom close button — bypasses Vuexy/Bootstrap .btn-close override that hides the X icon */
.ai-modal-close {
    background: transparent;
    border: none;
    color: #fff;
    width: 32px; height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 1.15rem;
    cursor: pointer;
    opacity: 0.85;
    transition: all .2s ease;
    padding: 0; line-height: 1;
    margin-left: auto;
    flex-shrink: 0;
}
.ai-modal-close:hover { background: rgba(255,255,255,0.18); opacity: 1; }
.ai-modal-close i { color: #fff; }

/* Chat-thread style comments */
.thread-wrap { position: relative; padding-left: 0; }
.thread-item { position: relative; display: flex; gap: 0.85rem; align-items: flex-start; padding-bottom: 0.9rem; }
.thread-item:not(.is-last)::before {
    content: '';
    position: absolute;
    left: 17px; top: 38px; bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #e7e9f4, #e7e9f4 4px, transparent 4px, transparent 8px) 0 0/2px 8px repeat-y;
}
.thread-avatar {
    flex-shrink: 0;
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    box-shadow: 0 2px 6px rgba(20,20,40,0.12);
    position: relative;
    z-index: 1;
}
.thread-avatar i { font-size: 1.05rem; }
.thread-bubble {
    flex-grow: 1;
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 0.7rem;
    padding: 0.6rem 0.85rem;
    box-shadow: 0 1px 2px rgba(20,20,40,0.04);
    position: relative;
}
.thread-bubble::before {
    content: '';
    position: absolute;
    left: -7px; top: 12px;
    width: 12px; height: 12px;
    background: #fff;
    border-left: 1px solid #eef0f5;
    border-bottom: 1px solid #eef0f5;
    transform: rotate(45deg);
}
.thread-bubble-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.5rem; margin-bottom: 0.3rem; }
.thread-name { font-weight: 600; color: #2c2e3a; font-size: 0.84rem; }
.thread-title { color: #8592a3; font-size: 0.78rem; font-weight: 400; }
.thread-badge {
    font-size: 0.68rem; font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    line-height: 1.4;
    white-space: nowrap;
}
.thread-extra { color: #8592a3; font-size: 0.74rem; }
.thread-time {
    margin-left: auto; color: #adb5bd; font-size: 0.72rem; font-weight: 500;
    display: inline-flex; align-items: center;
}
.thread-bubble-body { color: #4a5060; font-size: 0.82rem; line-height: 1.6; }
.thread-bubble-body strong { color: #2c2e3a; }
@media (max-width: 575px) {
    .thread-item { gap: 0.6rem; }
    .thread-avatar { width: 30px; height: 30px; }
    .thread-avatar i { font-size: 0.85rem; }
    .thread-bubble { padding: 0.5rem 0.7rem; }
    .thread-bubble::before { display: none; }
    .thread-time { margin-left: 0; flex-basis: 100%; }
    .thread-item:not(.is-last)::before { left: 14px; top: 32px; }
}

.modal-segment {
    background: #fafbfd !important;
    border: 1px solid #eef0f5 !important;
    border-radius: 0.5rem !important;
}
.modal-segment .modal-seg-badge {
    background: #f0edff !important;
    color: #5648c4 !important;
    font-weight: 600;
}
</style>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-lg-5">
        <h4 class="fw-bold mb-0">
            <i class="ti tabler-send me-2 text-primary"></i>অনুমোদনের জন্য প্রেরণ
        </h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>অনুমোদনকারীদের কাছে আবেদনটি forward করুন</div>
    </div>
    <div class="col-12 col-lg-7 text-lg-end d-flex gap-2 justify-content-lg-end align-items-center flex-wrap mt-3 mt-lg-0">
        <a href="application-details.php?menuslug=<?= urlencode($menuslug) ?>&leaveApplicationID=<?= $leaveApplicationID ?>" target="_blank" class="btn btn-label-danger">
            <i class="ti tabler-file-text me-1"></i>আবেদনপত্র
        </a>
        <?php if (!empty($app['attachment'])): ?>
        <a href="../../uploads/<?= htmlspecialchars($app['attachment']) ?>" target="_blank" class="btn btn-label-info">
            <i class="ti tabler-paperclip me-1"></i>সংযুক্তি
        </a>
        <?php endif; ?>
        <button type="button" class="btn btn-balance" data-bs-toggle="modal" data-bs-target="#applicantBalanceModal">
            <i class="ti tabler-wallet me-1"></i>ব্যালেন্স
        </button>
        <a href="allowed-applications.php?menuslug=<?= urlencode($menuslug) ?>" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<?php
// Applicant balance viewer modal
$applicantEmpID = (int)$app['applicantID'];
$applicantName  = $emp['employee_name'] ?? '';
include(__DIR__ . '/../../includes/applicant_balance_modal.php');
?>

<div class="card fwd-card shadow-sm border-0">
    <div class="card-body">
        <form id="forwardForm">
            <input type="hidden" name="leaveApplicationID" value="<?= $leaveApplicationID ?>">

            <!-- Application type badge -->
            <div class="row mb-3">
                <div class="col-md-3 info-label col-form-label">আবেদনের প্রকার</div>
                <div class="col-md-9 d-flex align-items-center">
                    <?php if ($app['applicationType'] == 1): ?>
                        <span class="app-type-badge" style="background:#e6f7ee;color:#1a7e44;">
                            <i class="ti tabler-calendar-check me-1"></i>নিয়মিত ছুটির আবেদন
                        </span>
                    <?php elseif ($app['applicationType'] == 2): ?>
                        <span class="app-type-badge" style="background:#fff1f0;color:#b13c3c;">
                            <i class="ti tabler-alert-triangle me-1"></i>অনুপস্থিতকালের জন্য ছুটির আবেদন
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Applicant info -->
            <div class="row mb-3">
                <label class="col-md-3 info-label col-form-label">আবেদনকারী</label>
                <div class="col-md-9 col-form-label">
                    <?= htmlspecialchars($emp['employee_name'] ?? '') ?>,
                    <?= htmlspecialchars($designationData['job_title_name'] ?? '') ?>,
                    <?= htmlspecialchars($sectionData['section_name']   ?? '') ?>,
                    <?= htmlspecialchars($orgData['organization_name'] ?? '') ?>
                </div>
            </div>

            <?php // Supervisor's note is now shown inside the "পূর্ববর্তী মন্তব্য" modal (thread). ?>

            <?php if (!empty($returnHistory)): ?>
            <!-- Return history (chain order: oldest first) -->
            <div class="row mb-3">
                <label class="col-md-3 info-label col-form-label">ফেরতের কারণ</label>
                <div class="col-md-9">
                    <?php foreach ($returnHistory as $rh): ?>
                    <div class="ret-banner mb-2">
                        <div class="ret-banner-head">
                            <i class="ti tabler-corner-up-left"></i>
                            <strong><?= htmlspecialchars($rh['returnedByName'] ?? '—') ?></strong>
                            <?php if (!empty($rh['returnedByTitle'])): ?>
                                <span class="text-muted">·</span>
                                <span class="text-muted"><?= htmlspecialchars($rh['returnedByTitle']) ?></span>
                            <?php endif; ?>
                            <span class="text-muted">·</span>
                            <span class="text-muted"><i class="ti tabler-calendar me-1"></i><?= date('d/m/Y', strtotime($rh['createdAt'])) ?></span>
                        </div>
                        <div class="ret-banner-body"><?= nl2br(htmlspecialchars($rh['note'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- চাহিত ছুটি (requested — read-only, employee's original ask) -->
            <div class="row mb-3">
                <label class="col-md-3 info-label col-form-label">চাহিত ছুটি</label>
                <div class="col-md-9">
                    <div class="seg-table-wrap">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:36px;">#</th>
                                    <th>ছুটির ধরণ</th>
                                    <th>শুরু</th>
                                    <th>শেষ</th>
                                    <th class="text-end">দিন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($requestedSegs)): ?>
                                    <?php foreach ($requestedSegs as $i => $seg): ?>
                                        <tr>
                                            <td class="text-muted"><?= $i + 1 ?></td>
                                            <td><strong><?= htmlspecialchars($leaveTypeMap[(int)$seg['leaveType']] ?? 'অজানা') ?></strong></td>
                                            <td><?= date('d/m/Y', strtotime($seg['dateFrom'])) ?></td>
                                            <td><?= date('d/m/Y', strtotime($seg['dateTo'])) ?></td>
                                            <td class="text-end"><?= (int)$seg['days'] ?> দিন</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="4" class="text-end">মোট</td>
                                        <td class="text-end"><?= array_sum(array_column($requestedSegs, 'days')) ?> দিন</td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-muted text-center py-2">— কোনো segment নেই —</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="seg-helper"><i class="ti tabler-lock me-1"></i>আবেদনকারীর মূল আবেদন (read-only)</div>
                </div>
            </div>

            <!-- প্রস্তাবিত ছুটি (proposed — editable) -->
            <div class="row mb-3">
                <label class="col-md-3 info-label col-form-label">প্রস্তাবিত ছুটি <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <div class="seg-table-wrap is-proposed">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:36px;">#</th>
                                    <th>ছুটির ধরণ</th>
                                    <th>শুরু</th>
                                    <th>শেষ</th>
                                    <th class="text-end">দিন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($proposedSegs)): ?>
                                    <?php foreach ($proposedSegs as $i => $seg): ?>
                                        <tr>
                                            <td class="text-muted"><?= $i + 1 ?></td>
                                            <td><strong><?= htmlspecialchars($leaveTypeMap[(int)$seg['leaveType']] ?? 'অজানা') ?></strong></td>
                                            <td><?= date('d/m/Y', strtotime($seg['dateFrom'])) ?></td>
                                            <td><?= date('d/m/Y', strtotime($seg['dateTo'])) ?></td>
                                            <td class="text-end"><?= (int)$seg['days'] ?> দিন</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="4" class="text-end">মোট</td>
                                        <td class="text-end"><?= array_sum(array_column($proposedSegs, 'days')) ?> দিন</td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-muted text-center py-2">— কোনো segment নেই —</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
                        <?php if (!empty($threadEvents)): ?>
                            <button type="button" class="btn btn-sm btn-label-info" data-bs-toggle="modal" data-bs-target="#prevCommentsModal">
                                <i class="ti tabler-messages me-1"></i>পূর্ববর্তী মন্তব্য
                                <span class="badge bg-info ms-1"><?= count($threadEvents) ?></span>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-label-primary" id="editSegmentsBtn" data-bs-toggle="modal" data-bs-target="#segmentEditModal">
                            <i class="ti tabler-edit me-1"></i>প্রস্তাবিত ছুটি সম্পাদনা
                        </button>
                        <?php if (!empty($segHistory)): ?>
                            <button type="button" class="btn btn-sm btn-label-secondary" data-bs-toggle="modal" data-bs-target="#segmentHistoryModal">
                                <i class="ti tabler-history me-1"></i>পরিবর্তন ইতিহাস (<?= count($segHistory) ?>)
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Hidden compat fields auto-populated from proposed segments -->
                    <input type="hidden" id="leaveFrom"     name="leaveFrom"     value="<?= htmlspecialchars(!empty($proposedSegs) ? $proposedSegs[0]['dateFrom'] : $aDateFrom) ?>">
                    <input type="hidden" id="leaveTo"       name="leaveTo"       value="<?= htmlspecialchars(!empty($proposedSegs) ? end($proposedSegs)['dateTo'] : $aDateTo) ?>">
                    <input type="hidden" id="approvedDays"  name="approvedDays"  value="<?= !empty($proposedSegs) ? array_sum(array_column($proposedSegs, 'days')) : $dateDiff ?>">
                    <input type="hidden" name="approvedLeaveType" value="<?= !empty($proposedSegs) ? (int)$proposedSegs[0]['leaveType'] : (int)$app['approvedLeaveType'] ?>">
                </div>
            </div>

            <!-- কর্তন হইবে: removed (per-segment leaveType replaces this in multi-segment system) -->
            <input type="hidden" name="leaveTypeInTwo" value="<?= !empty($proposedSegs) ? (int)$proposedSegs[0]['leaveType'] : (int)$app['leaveTypeInTwo'] ?>">

            <!-- Template selector -->
            <?php if (!empty($templates)): ?>
            <div class="row mb-3">
                <label class="col-md-3 info-label col-form-label">টেম্পলেট</label>
                <div class="col-md-9">
                    <select class="form-select" id="templateSelect">
                        <option value="">-- টেম্পলেট নির্বাচন করুন --</option>
                        <?php foreach ($templates as $t): ?>
                        <option value="<?= htmlspecialchars($t['templateData']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($t['templateData'], 0, 70, '…')) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <!-- Note / comment -->
            <div class="row mb-4">
                <label class="col-md-3 info-label col-form-label">নোট / মন্তব্য</label>
                <div class="col-md-9">
                    <textarea class="form-control" name="note" id="note" rows="3"
                        ><?= htmlspecialchars($app['adminNote'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Copy-to section -->
            <h6 class="copyto-section-title">
                <span class="ti-tile"><i class="ti tabler-copy"></i></span>
                অনুলিপি
            </h6>
            <div class="text-muted small mb-2" style="font-size:0.78rem;">
                <i class="ti tabler-info-circle me-1"></i>বাম দিকের <i class="ti tabler-grip-vertical"></i> আইকন ধরে সারি টেনে (drag) নতুন ক্রমে সাজানো যাবে অথবা সরাসরি অনুক্রম নম্বর সম্পাদনা করা যাবে।
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="copyToTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">—</th>
                            <th width="70" class="text-center">ক্রমিক</th>
                            <th>প্রাপক</th>
                            <th width="120" class="text-center">অনুক্রম</th>
                            <th width="60" class="text-center">—</th>
                        </tr>
                    </thead>
                    <tbody id="copyToBody">
                        <?php foreach ($copyToList as $i => $ct):
                            $_isLabel = !empty(trim($ct['label'] ?? ''));
                            $_rowBg   = $_isLabel ? 'background:#f7f6ff;' : '';
                        ?>
                        <tr style="<?= $_rowBg ?>">
                            <td class="text-center drag-handle" style="cursor:grab;color:#8a90a6;" title="টেনে সরান">
                                <i class="ti tabler-grip-vertical"></i>
                            </td>
                            <td class="text-center row-serial"><?= $i + 1 ?></td>
                            <td>
                                <?php if ($_isLabel): ?>
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <i class="ti tabler-pin text-primary"></i>
                                        <?= htmlspecialchars($ct['label']) ?>
                                    </span>
                                    <span class="badge bg-label-secondary ms-2" style="font-size:0.65rem;">নির্ধারিত</span>
                                    <input type="hidden" name="copyKind[]"  value="label">
                                    <input type="hidden" name="copyLabel[]" value="<?= htmlspecialchars($ct['label']) ?>">
                                    <input type="hidden" name="copyEmp[]"   value="0">
                                <?php else: ?>
                                    <select class="form-select select2 copy-to-select" name="copyEmp[]">
                                        <option value="">-- নির্বাচন করুন --</option>
                                        <?php foreach ($empList as $e): ?>
                                        <option value="<?= $e['id'] ?>" <?= ($ct['employeeID'] == $e['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['employee_id'] . ' - ' . $e['employee_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="copyKind[]"  value="emp">
                                    <input type="hidden" name="copyLabel[]" value="">
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <input type="number" class="form-control text-center" name="copySerial[]" value="<?= (int)$ct['serial'] ?>" min="1">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-icon btn-label-danger row-delete" title="সারি মুছুন">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2 mb-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-label-primary" id="addRow">
                    <i class="ti tabler-plus me-1"></i>কর্মকর্তা সারি যোগ করুন
                </button>
                <button type="button" class="btn btn-sm btn-label-secondary" id="reseqRows" title="অনুক্রম ইনপুট অনুযায়ী সারি সাজান">
                    <i class="ti tabler-arrows-sort me-1"></i>অনুক্রম অনুযায়ী সাজান
                </button>
            </div>

            <style>
                #copyToBody tr.ui-sortable-helper {
                    background: #fff !important;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                #copyToBody .drag-handle:hover { color: #6c5ce7 !important; }
                #copyToBody .drag-handle:active { cursor: grabbing !important; }
                .copy-drop-placeholder {
                    background: #eef0f8;
                    height: 46px;
                    border: 2px dashed #b9b0f4;
                }
            </style>

            <!-- Action buttons -->
            <div class="fwd-actions d-flex gap-2 justify-content-end flex-wrap">
                <a href="allowed-applications.php?menuslug=<?= urlencode($menuslug) ?>" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল
                </a>
                <button type="submit" class="btn btn-primary px-4" id="forwardBtn">
                    <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     Previous Comments Modal (পূর্ববর্তী মন্তব্য)
═══════════════════════════════════════════════════════════ -->
<?php if (!empty($threadEvents)): ?>
<div class="modal fade" id="prevCommentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti tabler-messages me-1"></i>পূর্ববর্তী মন্তব্য
                    <span class="badge bg-label-info ms-2"><?= count($threadEvents) ?></span>
                </h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <div class="modal-body">
                <?= $threadHtml ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Segment Edit Modal -->
<div class="modal fade" id="segmentEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-edit me-1"></i>প্রস্তাবিত ছুটি সম্পাদনা</h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="alert mb-3" style="background:#fff7ed;border:1px solid #ffe4b8;border-left:3px solid #d4a056;color:#8b6f47;font-size:0.85rem;">
                    <i class="ti tabler-info-circle me-1"></i>
                    আপনার পরিবর্তনগুলো history-তে রেকর্ড হবে। Save করলে forward-এর সময় পরিবর্তিত state পরবর্তী signatory দেখবেন।
                </div>
                <div id="modalSegments"></div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn btn-sm btn-label-primary" id="modalAddSegment">
                        <i class="ti tabler-plus me-1"></i>আরেকটা ধরন যোগ করুন
                    </button>
                    <div class="text-muted small">
                        মোট: <strong id="modalTotalDays" style="color:#5648c4;">০ দিন</strong>
                    </div>
                </div>
                <div id="modalBsrWarning" class="alert alert-danger mt-3" style="display:none; font-size:0.85rem; border-left:3px solid #dc3545;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-primary" id="saveSegmentsBtn">
                    <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Segment History Modal -->
<?php if (!empty($segHistory)): ?>
<div class="modal fade" id="segmentHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-history me-1"></i>Segment পরিবর্তনের ইতিহাস</h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm align-middle" style="font-size:0.85rem;">
                    <thead style="background:#fafbfd;">
                        <tr>
                            <th style="font-size:0.78rem;color:#5d6580;text-transform:uppercase;letter-spacing:0.03em;">সময়</th>
                            <th style="font-size:0.78rem;color:#5d6580;text-transform:uppercase;letter-spacing:0.03em;">কে</th>
                            <th style="font-size:0.78rem;color:#5d6580;text-transform:uppercase;letter-spacing:0.03em;">কাজ</th>
                            <th style="font-size:0.78rem;color:#5d6580;text-transform:uppercase;letter-spacing:0.03em;">বিবরণ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($segHistory as $h):
                            $actionBadge = ['created'=>'success','edited'=>'warning','removed'=>'danger'][$h['action']] ?? 'secondary';
                            $actionLabel = ['created'=>'যোগ','edited'=>'সম্পাদনা','removed'=>'অপসারণ'][$h['action']] ?? $h['action'];
                            $newD = $h['newData'] ? json_decode($h['newData'], true) : null; ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($h['changedAt']))) ?></td>
                                <td><?= htmlspecialchars($h['changedByName'] ?? $h['employee_name'] ?? '—') ?></td>
                                <td><span class="badge bg-label-<?= $actionBadge ?>"><?= $actionLabel ?></span></td>
                                <td>
                                    <?php if ($newD && isset($newD['leaveType'])): ?>
                                        <?= htmlspecialchars($leaveTypeMap[(int)$newD['leaveType']] ?? 'অজানা') ?> —
                                        <?= htmlspecialchars(date('d/m/Y', strtotime($newD['dateFrom']))) ?>
                                        → <?= htmlspecialchars(date('d/m/Y', strtotime($newD['dateTo']))) ?>
                                        (<?= (int)$newD['days'] ?> দিন)
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Segment edit modal logic (waits for jQuery) -->
<script>
function __initFwdSegmentModal() {
    if (typeof jQuery === 'undefined') return setTimeout(__initFwdSegmentModal, 50);
    var $ = jQuery;
(function(){
    const LEAVE_TYPES = <?= json_encode(array_values(array_map(function($id, $title){ return ['leaveID'=>$id, 'leaveTitle'=>$title]; }, array_keys($leaveTypeMap), $leaveTypeMap)), JSON_UNESCAPED_UNICODE) ?>;
    const APP_ID = <?= (int)$leaveApplicationID ?>;
    const INITIAL_SEGS = <?= json_encode(array_map(function($s){ return [
        'segmentID' => (int)$s['dataID'],
        'leaveType' => (int)$s['leaveType'],
        'dateFrom'  => $s['dateFrom'],
        'dateTo'    => $s['dateTo'],
        'days'      => (int)$s['days'],
    ]; }, $proposedSegs), JSON_UNESCAPED_UNICODE) ?>;

    function fmtDmy(yyyymmdd) { if (!yyyymmdd) return ''; const p = yyyymmdd.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }
    function parseDmy(dmy) { if (!dmy) return null; const p = dmy.split('/'); if (p.length !== 3) return null; return p[2]+'-'+p[1]+'-'+p[0]; }
    function toBn(n) { return String(n).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[d]); }

    function renderRow(seg, idx) {
        let opts = `<option value="">-- নির্বাচন করুন --</option>`;
        LEAVE_TYPES.forEach(lt => {
            const sel = (seg && parseInt(seg.leaveType) === parseInt(lt.leaveID)) ? 'selected' : '';
            opts += `<option value="${lt.leaveID}" ${sel}>${lt.leaveTitle}</option>`;
        });
        return `
        <div class="modal-segment p-2 mb-2 border rounded" data-seg-id="${seg ? seg.segmentID : ''}" style="background:#fafbff;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="badge bg-label-primary modal-seg-badge">ধরন ${toBn(idx+1)}</span>
                <button type="button" class="btn btn-sm btn-outline-danger modal-remove-seg"><i class="ti tabler-x"></i></button>
            </div>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small">ছুটির ধরণ</label><select class="form-select form-select-sm modal-seg-type">${opts}</select></div>
                <div class="col-md-3"><label class="form-label small">শুরু</label><input type="text" class="form-control form-control-sm modal-seg-from" placeholder="dd/mm/yyyy" value="${seg ? fmtDmy(seg.dateFrom) : ''}" autocomplete="off"></div>
                <div class="col-md-3"><label class="form-label small">শেষ</label><input type="text" class="form-control form-control-sm modal-seg-to" placeholder="dd/mm/yyyy" value="${seg ? fmtDmy(seg.dateTo) : ''}" autocomplete="off"></div>
                <div class="col-md-2"><label class="form-label small">দিন</label><input type="text" class="form-control form-control-sm modal-seg-days" value="${seg ? seg.days : ''}" readonly></div>
            </div>
        </div>`;
    }

    function rebuildModalSegments() {
        const $c = $('#modalSegments'); $c.empty();
        const segs = INITIAL_SEGS.length ? INITIAL_SEGS : [{leaveType:'',dateFrom:'',dateTo:'',days:0}];
        segs.forEach((s, i) => $c.append(renderRow(s, i)));
        bindModalHandlers(); recalcModal();
    }

    function bindModalHandlers() {
        $('#modalSegments .modal-seg-from, #modalSegments .modal-seg-to').each(function(){
            if (!$(this).hasClass('hasDatepicker')) {
                try { $(this).datepicker({ dateFormat: 'dd/mm/yy' }); } catch(e) {}
            }
        });
        $('#modalSegments .modal-seg-from, #modalSegments .modal-seg-to, #modalSegments .modal-seg-type')
            .off('change.seg').on('change.seg', recalcModal);
        $('#modalSegments .modal-remove-seg').off('click.seg').on('click.seg', function(){
            if ($('.modal-segment').length <= 1) return;
            $(this).closest('.modal-segment').remove();
            renumberModal(); recalcModal();
        });
    }

    function renumberModal() {
        $('.modal-segment').each(function(i){ $(this).find('.modal-seg-badge').text('ধরন ' + toBn(i+1)); });
    }

    function recalcModal() {
        let total = 0;
        $('.modal-segment').each(function(){
            const f = parseDmy($(this).find('.modal-seg-from').val());
            const t = parseDmy($(this).find('.modal-seg-to').val());
            if (f && t) {
                const days = Math.floor((new Date(t) - new Date(f))/86400000) + 1;
                if (days > 0) { $(this).find('.modal-seg-days').val(days); total += days; }
                else $(this).find('.modal-seg-days').val('');
            } else $(this).find('.modal-seg-days').val('');
        });
        $('#modalTotalDays').text(toBn(total) + ' দিন');
    }

    function collectModalSegs() {
        const out = [];
        $('.modal-segment').each(function(){
            const t = parseInt($(this).find('.modal-seg-type').val()) || 0;
            const f = parseDmy($(this).find('.modal-seg-from').val());
            const to = parseDmy($(this).find('.modal-seg-to').val());
            const days = parseInt($(this).find('.modal-seg-days').val()) || 0;
            const sid = $(this).attr('data-seg-id');
            if (t && f && to && days > 0) out.push({segmentID: sid || null, leaveType: t, dateFrom: f, dateTo: to, days: days});
        });
        return out;
    }

    // Namespaced off/on so this script block is idempotent — turbo:load or
    // any re-render of the page (e.g. after modal open) would otherwise stack
    // handlers and cause each click on "আরেকটা ধরন যোগ করুন" to add 2, 4, 6+ rows.
    $('#segmentEditModal').off('shown.bs.modal.seg').on('shown.bs.modal.seg', rebuildModalSegments);
    $(function(){ try { rebuildModalSegments(); } catch(e) { console.error(e); } });

    $('#modalAddSegment').off('click.addSeg').on('click.addSeg', function(){
        const idx = $('.modal-segment').length;
        $('#modalSegments').append(renderRow(null, idx));
        bindModalHandlers(); recalcModal();
    });

    $('#saveSegmentsBtn').off('click.saveSeg').on('click.saveSeg', function(){
        const segs = collectModalSegs();
        if (segs.length === 0) {
            Swal.fire({title:'ত্রুটি', text:'অন্তত একটা segment থাকতে হবে।', icon:'error',
                       confirmButtonColor:'#ff3e1d', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
            return;
        }
        $.ajax({
            url: '<?= BASE_URL ?>/api/leave/save-segments.php',
            method: 'POST',
            data: { applicationID: APP_ID, segments: JSON.stringify(segs) },
            dataType: 'json',
            success: function(r){
                if (r && r.ok) {
                    Swal.fire({title:'সম্পন্ন', text:'প্রস্তাবিত ছুটি সংরক্ষণ হয়েছে', icon:'success',
                               timer:1400, showConfirmButton:false,
                               confirmButtonColor:'#6c5ce7', customClass:{confirmButton:'btn btn-primary'}, buttonsStyling:false})
                        .then(() => location.reload());
                } else {
                    Swal.fire({title:'ত্রুটি', text:(r && r.error) || 'Save failed', icon:'error',
                               confirmButtonColor:'#ff3e1d', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                }
            },
            error: function(){
                Swal.fire({title:'ত্রুটি', text:'Server error', icon:'error',
                           confirmButtonColor:'#ff3e1d', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
            }
        });
    });
})();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', __initFwdSegmentModal);
else __initFwdSegmentModal();
document.addEventListener('turbo:load', __initFwdSegmentModal);
document.addEventListener('turbo:frame-load', __initFwdSegmentModal);
</script>

<script>
var MENUSLUG = <?= json_encode($menuslug) ?>;
var EMP_DATA = <?= json_encode(array_values($empList)) ?>;

// Wait for libs (loaded by footer_vuexy AFTER this script on hard load) before
// running form logic. On Turbo nav libs are already in memory so this runs
// immediately. Keeps script inside turbo-frame so it executes on every nav.
(function bootForwardPage() {
    if (typeof jQuery === 'undefined' || !jQuery.fn ||
        !jQuery.fn.select2 || typeof Swal === 'undefined') {
        return setTimeout(bootForwardPage, 20);
    }

$(document).ready(function () {

    // Auto-calc days when dates change
    $('#leaveFrom, #leaveTo').on('change', function () {
        var from = $('#leaveFrom').val(), to = $('#leaveTo').val();
        if (from && to) {
            var d1 = new Date(from), d2 = new Date(to);
            if (!isNaN(d1) && !isNaN(d2)) {
                $('#approvedDays').val(Math.round(Math.abs(d2 - d1) / 86400000) + 1);
            }
        }
    });

    // Template selector
    $('#templateSelect').on('change', function () {
        if (this.value) { $('#note').val(this.value); this.value = ''; }
    });

    // Build employee options HTML
    function buildEmpOptions() {
        var html = '<option value="">-- নির্বাচন করুন --</option>';
        EMP_DATA.forEach(function (e) {
            html += '<option value="' + e.id + '">' +
                    $('<span>').text(e.employee_id + ' - ' + e.employee_name).html() +
                    '</option>';
        });
        return html;
    }

    // Update ক্রমিক column (visual only) after any row add/remove/resort.
    // The অনুক্রম input is the source of truth for the PDF order — we don't
    // reset it here, only the display index.
    function reIndex() {
        $('#copyToBody tr').each(function (i) {
            $(this).find('.row-serial').text(i + 1);
        });
    }

    // Add an employee row (defaults are seeded server-side)
    $('#addRow').on('click', function () {
        // Next default অনুক্রম = max existing + 1
        var maxSerial = 0;
        $('#copyToBody input[name="copySerial[]"]').each(function () {
            var v = parseInt($(this).val(), 10);
            if (!isNaN(v) && v > maxSerial) maxSerial = v;
        });
        var nextSerial = maxSerial + 1;
        var $row = $('<tr>' +
            '<td class="text-center drag-handle" style="cursor:grab;color:#8a90a6;" title="টেনে সরান"><i class="ti tabler-grip-vertical"></i></td>' +
            '<td class="text-center row-serial"></td>' +
            '<td>' +
                '<select class="form-select copy-to-select" name="copyEmp[]">' + buildEmpOptions() + '</select>' +
                '<input type="hidden" name="copyKind[]"  value="emp">' +
                '<input type="hidden" name="copyLabel[]" value="">' +
            '</td>' +
            '<td class="text-center"><input type="number" class="form-control text-center" name="copySerial[]" value="' + nextSerial + '" min="1"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-icon btn-label-danger row-delete" title="সারি মুছুন"><i class="ti tabler-trash"></i></button></td>' +
            '</tr>');
        $('#copyToBody').append($row);
        $row.find('.copy-to-select').select2({ width: '100%' });
        reIndex();
    });

    // Drag-and-drop reorder via jQuery UI Sortable. Drop → renumber the
    // অনুক্রম inputs left-to-right so the visual order becomes the
    // canonical order for the office notice. Users can still fine-tune
    // the numbers manually afterwards.
    if ($.fn.sortable) {
        $('#copyToBody').sortable({
            handle: '.drag-handle',
            axis: 'y',
            placeholder: 'copy-drop-placeholder',
            forcePlaceholderSize: true,
            helper: function (e, tr) {
                // Preserve column widths while dragging (default helper collapses cells)
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function (i) {
                    $(this).width($originals.eq(i).outerWidth());
                });
                return $helper;
            },
            start: function () {
                // Close any open Select2 dropdowns during the drag
                $('.copy-to-select').select2('close');
            },
            update: function () {
                // Renumber অনুক্রম inputs 1..N in the new visual order
                $('#copyToBody tr').each(function (i) {
                    $(this).find('input[name="copySerial[]"]').val(i + 1);
                });
                reIndex();
            }
        }).disableSelection();
    }

    // Per-row delete
    $(document).on('click', '#copyToBody .row-delete', function () {
        $(this).closest('tr').remove();
        reIndex();
    });

    // Re-sort rows in visual order matching the অনুক্রম input values
    $('#reseqRows').on('click', function () {
        var $rows = $('#copyToBody tr').toArray();
        $rows.sort(function (a, b) {
            var av = parseInt($(a).find('input[name="copySerial[]"]').val(), 10) || 0;
            var bv = parseInt($(b).find('input[name="copySerial[]"]').val(), 10) || 0;
            return av - bv;
        });
        // Re-append in the sorted order — jQuery detaches + reattaches
        var $body = $('#copyToBody');
        $rows.forEach(function (r) { $body.append(r); });
        reIndex();
    });

    // Submit form
    $('#forwardForm').on('submit', function (e) {
        e.preventDefault();
        Swal.fire({
            title: 'নিশ্চিত করুন',
            text: 'আবেদনটি অনুমোদনের জন্য পাঠানো হবে। আপনি কি নিশ্চিত?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6c5ce7',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, পাঠান',
            cancelButtonText: 'বাতিল',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton:  'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var $btn = $('#forwardBtn');
            $btn.attr('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span>প্রেরণ হচ্ছে...');
            $.ajax({
                url:      '../../api/leave/forward-to-approval.php',
                type:     'POST',
                data:     $('#forwardForm').serialize(),
                dataType: 'json',
                success:  function (res) {
                    $btn.removeAttr('disabled')
                        .html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
                    if (res.status == 1) {
                        Swal.fire({ icon: 'success', title: 'সম্পন্ন', text: res.message,
                                    timer: 2000, showConfirmButton: false,
                                    confirmButtonColor: '#6c5ce7',
                                    customClass:{confirmButton:'btn btn-primary'}, buttonsStyling:false })
                            .then(function () {
                                window.location = 'allowed-applications.php?menuslug=' + encodeURIComponent(MENUSLUG);
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ত্রুটি', text: res.message,
                                    confirmButtonColor: '#ff3e1d',
                                    customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false });
                    }
                },
                error: function () {
                    $btn.removeAttr('disabled')
                        .html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
                    Swal.fire({ icon: 'error', title: 'ত্রুটি', text: 'সমস্যা হয়েছে। আবার চেষ্টা করুন।',
                                confirmButtonColor: '#ff3e1d',
                                customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false });
                }
            });
        });
    });

});

})(); // end bootForwardPage
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
