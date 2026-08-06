<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
// banglaNumber() lives here — the segment-history modal formats its dates
// and day counts with it.
require_once(LIBRARY_PATH . '/number_converter.php');

$dataID             = intval($_GET['dataID']             ?? 0);
$leaveApplicationID = intval($_GET['leaveApplicationID'] ?? 0);
$menuslug           = htmlspecialchars($_GET['menuslug'] ?? 'leave-approval');

if (!$dataID || !$leaveApplicationID) {
    echo '<div class="alert alert-danger">অবৈধ অনুরোধ।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$currentEmployeeID = (int)($getUserInfoQRW['employee_id'] ?? 0);

// Get the approval row
$rowStmt = mysqli_prepare($con, "SELECT * FROM leave_data_for_approval WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($rowStmt, 'i', $dataID);
mysqli_stmt_execute($rowStmt);
$approvalRow = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));

if (!$approvalRow || (int)$approvalRow['signatory'] !== $currentEmployeeID) {
    echo '<div class="alert alert-danger">আপনার এই আবেদন দেখার অনুমতি নেই।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$isSupervisor = (int)$approvalRow['isSupervisor'];

// Get leave application + leave type name
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

// Previous signatory notes (supervisor note shown to all signatories)
$supNoteStmt = mysqli_prepare($con,
    "SELECT note, signatory, designation_id, section_id FROM leave_data_for_approval
     WHERE leaveApplicationID=? AND isSupervisor=1 LIMIT 1");
mysqli_stmt_bind_param($supNoteStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($supNoteStmt);
$supNoteRow = mysqli_fetch_assoc(mysqli_stmt_get_result($supNoteStmt));

// All approved signatories with name, designation, comment
$sigHistoryStmt = mysqli_prepare($con,
    "SELECT ldfa.signatory, ldfa.note, ldfa.approvedDate, ldfa.isSupervisor, ldfa.isApproved,
            el.employee_name, jt.job_title_name
     FROM leave_data_for_approval ldfa
     LEFT JOIN employee_list el ON ldfa.signatory = el.id
     LEFT JOIN job_title jt     ON el.designation  = jt.id
     WHERE ldfa.leaveApplicationID = ? AND ldfa.isApproved = 1
     ORDER BY ldfa.serial ASC");
mysqli_stmt_bind_param($sigHistoryStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($sigHistoryStmt);
$sigHistory = mysqli_fetch_all(mysqli_stmt_get_result($sigHistoryStmt), MYSQLI_ASSOC);

// ── Return history (ফেরত / Send-back) ─────────────────────────────────
// Auto-create the history table if missing
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
$returnStmt = mysqli_prepare($con,
    "SELECT * FROM leave_return_history WHERE leaveApplicationID = ? ORDER BY createdAt ASC, dataID ASC");
mysqli_stmt_bind_param($returnStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($returnStmt);
$returnHistory = mysqli_fetch_all(mysqli_stmt_get_result($returnStmt), MYSQLI_ASSOC);
mysqli_stmt_close($returnStmt);

// Resubmission history — every time the applicant edited-and-resubmitted
// after a পুনঃ যাচাই. update-application.php logs this via audit_log
// under action='leave_application_resubmitted', so we just read those.
// Guarded by audit_log's existence.
$resubmitHistory = [];
// Approval timestamps — leave_data_for_approval.approvedDate is DATE-precision
// (00:00:00), so a supervisor who approved AFTER a same-day return sorts
// BEFORE the return by default. audit_log carries the full datetime for
// each approval, keyed by actor name here so we can override the ts on
// supervisor + signatory events below.
$approvalAuditTs = [];

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

    $apvStmt = mysqli_prepare($con,
        "SELECT actor_name, action, createdAt
         FROM audit_log
         WHERE target_type = 'leave_application'
           AND target_id = ?
           AND action IN ('leave_recommended', 'leave_chain_approved', 'leave_approved')
         ORDER BY createdAt ASC, dataID ASC");
    // $approvalAuditTs stores LATEST ts per (actor, action) — used for
    // the current-state event's timestamp.
    // $approvalAuditEventsAll keeps ALL rows so a signatory who acted
    // multiple times (approve → rewind → re-approve) surfaces every
    // approval as its own event; without this, only the latest chain
    // approval showed up and the earlier one was invisible even though
    // audit_log had it.
    $approvalAuditEventsAll = [];
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

// ── Determine return target for the current signatory ────────────────
// If supervisor → applicant; else look for previous APPROVED non-supervisor sig; else admin
$returnTarget = null;
if ($isSupervisor) {
    $applicantStmt = mysqli_prepare($con, "SELECT employee_name FROM employee_list WHERE id = ?");
    mysqli_stmt_bind_param($applicantStmt, 'i', $app['applicantID']);
    mysqli_stmt_execute($applicantStmt);
    $aRow = mysqli_fetch_assoc(mysqli_stmt_get_result($applicantStmt));
    mysqli_stmt_close($applicantStmt);
    $returnTarget = [
        'type'  => 'to_applicant',
        'name'  => $aRow['employee_name'] ?? 'আবেদনকারী',
        'title' => 'আবেদনকারী',
        'label' => 'আবেদনকারীর কাছে ফেরত পাঠান',
    ];
} else {
    $prevStmt = mysqli_prepare($con,
        "SELECT ldfa.dataID, ldfa.signatory, ldfa.serial,
                el.employee_name, jt.job_title_name
         FROM leave_data_for_approval ldfa
         LEFT JOIN employee_list el ON ldfa.signatory = el.id
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE ldfa.leaveApplicationID = ?
           AND ldfa.serial < ?
           AND ldfa.isApproved = 1
           AND ldfa.isSupervisor = 0
         ORDER BY ldfa.serial DESC LIMIT 1");
    mysqli_stmt_bind_param($prevStmt, 'ii', $leaveApplicationID, $approvalRow['serial']);
    mysqli_stmt_execute($prevStmt);
    $prevRow = mysqli_fetch_assoc(mysqli_stmt_get_result($prevStmt));
    mysqli_stmt_close($prevStmt);

    if ($prevRow) {
        $returnTarget = [
            'type'  => 'to_previous_signatory',
            'name'  => $prevRow['employee_name'] ?? '—',
            'title' => $prevRow['job_title_name'] ?? '',
            'label' => 'পূর্ববর্তী অনুমোদনকারীর কাছে ফেরত পাঠান',
        ];
    } else {
        $returnTarget = [
            'type'  => 'to_admin',
            'name'  => 'প্রশাসনিক কর্মকর্তা',
            'title' => 'প্রশাসন',
            'label' => 'প্রশাসনিক কর্মকর্তার কাছে ফেরত পাঠান',
        ];
    }
}

// All leave types
$leaveTypesQ = mysqli_query($con, "SELECT * FROM leave_types ORDER BY leaveTitle ASC");
$leaveTypes  = [];
while ($lt = mysqli_fetch_assoc($leaveTypesQ)) $leaveTypes[] = $lt;

// Build a quick id→title map (used in segment listings)
$leaveTypeMap = [];
foreach ($leaveTypes as $lt) { $leaveTypeMap[(int)$lt['leaveID']] = $lt['leaveTitle']; }

// Segments for this application — split into requested (frozen) + proposed (mutable)
$segStmt = mysqli_prepare($con,
    "SELECT * FROM leave_application_segments
     WHERE applicationID = ?
     ORDER BY kind DESC, serial ASC, dateFrom ASC"); // 'requested' < 'proposed' so DESC = proposed last? actually doesn't matter, we filter in PHP
mysqli_stmt_bind_param($segStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($segStmt);
$allSegs = mysqli_fetch_all(mysqli_stmt_get_result($segStmt), MYSQLI_ASSOC);
mysqli_stmt_close($segStmt);

$requestedSegs = array_values(array_filter($allSegs, function($s){ return ($s['kind'] ?? 'requested') === 'requested'; }));
$proposedSegs  = array_values(array_filter($allSegs, function($s){ return ($s['kind'] ?? 'requested') === 'proposed'; }));
// Backward-compat fallback: if no kind column / no proposed copies yet, treat all as both
if (empty($requestedSegs) && empty($proposedSegs)) {
    $requestedSegs = $allSegs;
    $proposedSegs  = $allSegs;
} else if (empty($proposedSegs)) {
    $proposedSegs = $requestedSegs;
} else if (empty($requestedSegs)) {
    $requestedSegs = $proposedSegs;
}
// Legacy variable kept for places that haven't switched yet
$appSegments = $proposedSegs;

// Edit history (who created/edited/removed which segment)
$histStmt = mysqli_prepare($con,
    "SELECT h.*, el.employee_name FROM leave_segment_history h
     LEFT JOIN user_list ul ON h.changedBy = ul.dataID
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE h.applicationID = ? ORDER BY h.changedAt ASC");
mysqli_stmt_bind_param($histStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($histStmt);
$segHistory = mysqli_fetch_all(mysqli_stmt_get_result($histStmt), MYSQLI_ASSOC);
mysqli_stmt_close($histStmt);

// Leave templates (type 2 = supervisor/recommendation)
$templatesQ = mysqli_query($con, "SELECT * FROM leave_templates WHERE templateType=2");
$templates  = [];
while ($t = mysqli_fetch_assoc($templatesQ)) $templates[] = $t;

// Proposed dates (use approved if set, otherwise requested)
$aDateFrom = !empty($app['approvedDateFrom']) ? $app['approvedDateFrom'] : $app['dateFrom'];
$aDateTo   = !empty($app['approvedDateTo'])   ? $app['approvedDateTo']   : $app['dateTo'];
$dateDiff  = abs((int)round((strtotime($aDateTo) - strtotime($aDateFrom)) / 86400)) + 1;
$reqDiff   = abs((int)round((strtotime($app['dateTo']) - strtotime($app['dateFrom'])) / 86400)) + 1;

// মন্তব্য (note) is now required and always starts blank so the
// approver/supervisor must type their own remark. The old auto-fill
// meant most rows ended up with the same generic sentence.
$defaultComment = '';

// get supervisor details for potential use in comments
$supervisorDetails = null;
$supervisorStmt = mysqli_prepare($con, "SELECT * FROM employee_list WHERE id = ?");
    mysqli_stmt_bind_param($supervisorStmt, 'i', $supNoteRow['signatory']);
    mysqli_stmt_execute($supervisorStmt);
    $supervisorDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($supervisorStmt));

// Mark as read
mysqli_query($con, "UPDATE leave_data_for_approval SET isRead=1 WHERE dataID='$dataID'");

// Admin note history (every forward note — so re-forwards don't drop earlier notes)
$adminNoteHistory = [];
$ensureT = mysqli_query($con, "SHOW TABLES LIKE 'leave_admin_note_history'");
if ($ensureT && mysqli_num_rows($ensureT) > 0) {
    $anhStmt = mysqli_prepare($con,
        "SELECT * FROM leave_admin_note_history
         WHERE leaveApplicationID = ?
         ORDER BY createdAt ASC, dataID ASC");
    mysqli_stmt_bind_param($anhStmt, 'i', $leaveApplicationID);
    mysqli_stmt_execute($anhStmt);
    $adminNoteHistory = mysqli_fetch_all(mysqli_stmt_get_result($anhStmt), MYSQLI_ASSOC);
    mysqli_stmt_close($anhStmt);
}

// admin initiator details (for admin-initiated applications)
$adminInitiatorDetails = null;
$initiatorDesignation  = null;
if ($app['adminInitiator']) {
    $adminInitiatorID = (int)$app['adminInitiator'];
    $stmt = mysqli_prepare($con, "SELECT * FROM employee_list WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $adminInitiatorID);
    mysqli_stmt_execute($stmt);
    $adminInitiatorDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // admin initiator designation
    $initiatorDesignation = null;
    if ($adminInitiatorDetails) {
        $stmt = mysqli_prepare($con, "SELECT * FROM job_title WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $adminInitiatorDetails['adminInitiatorDesignation']);
        $stmt->execute();
        $initiatorDesignation = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // admin initiator organization
        $orgData = null;
        if (!empty($adminInitiatorDetails['adminInitiatorOrganization'])) {
            $stmt = mysqli_prepare($con, "SELECT * FROM organization WHERE id = ?");
            mysqli_stmt_bind_param("i", $adminInitiatorDetails['adminInitiatorOrganization']);
            mysqli_stmt_execute($stmt);
            $orgData = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $stmt->close();
        }
    }
}

// Styles are emitted inline (inside the turbo-frame) so they swap with the
// page content on Turbo navigation — see footer_vuexy.php where the frame closes.
?>
<style>
/* ── Approval page styles ─────────────────────────────── */
.info-label { font-weight: 500; color: #5d6580; font-size: 0.88rem; }

.approve-card { border-radius: 0.75rem; }
.approve-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .approve-card .card-body { padding: 1rem; }
}

/* History card */
.history-card {
    background: #fafbfd;
    border: 1px solid #eef0f5 !important;
    border-radius: 0.75rem;
}
.history-card-title {
    font-size: 0.82rem;
    color: #5648c4;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    margin-bottom: 0.85rem !important;
}

/* Collapsible variant */
.history-card-collapsible .history-card-toggle {
    width: 100%;
    background: transparent;
    border: 0;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    color: inherit;
}
.history-card-collapsible .history-card-toggle:focus-visible {
    outline: 2px solid #c4bdf1;
    outline-offset: 2px;
    border-radius: 0.4rem;
}
.history-card-collapsible .history-card-toggle .history-card-title {
    margin-bottom: 0 !important;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.history-card-count {
    background: #ede8ff;
    color: #5648c4;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    letter-spacing: 0;
    text-transform: none;
}
.history-card-chevron {
    transition: transform 0.2s ease;
    color: #8592a3;
    font-size: 1.05rem;
}
.history-card-collapsible:not(.is-collapsed) .history-card-chevron {
    transform: rotate(180deg);
}
.history-card-collapsible .thread-wrap {
    overflow: hidden;
    margin-top: 0.85rem;
}
/* Initial collapsed state — hidden before JS hooks in */
.history-card-collapsible.is-collapsed .thread-wrap {
    display: none;
}

/* Chat-thread style comments */
.thread-wrap { position: relative; padding-left: 0; }
.thread-item {
    position: relative;
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    padding-bottom: 0.9rem;
}
.thread-item:not(.is-last)::before {
    content: '';
    position: absolute;
    left: 17px;
    top: 38px;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #e7e9f4, #e7e9f4 4px, transparent 4px, transparent 8px) 0 0/2px 8px repeat-y;
}
.thread-avatar {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
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
    left: -7px;
    top: 12px;
    width: 12px;
    height: 12px;
    background: #fff;
    border-left: 1px solid #eef0f5;
    border-bottom: 1px solid #eef0f5;
    transform: rotate(45deg);
}
.thread-bubble-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.5rem;
    margin-bottom: 0.3rem;
}
.thread-name {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 0.84rem;
}
.thread-title {
    color: #8592a3;
    font-size: 0.78rem;
    font-weight: 400;
}
.thread-badge {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    line-height: 1.4;
    white-space: nowrap;
}
.thread-extra {
    color: #8592a3;
    font-size: 0.74rem;
}
.thread-time {
    margin-left: auto;
    color: #adb5bd;
    font-size: 0.72rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
}
.thread-bubble-body {
    color: #4a5060;
    font-size: 0.82rem;
    line-height: 1.6;
}
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

/* Action button — balance */
.btn-balance {
    background: linear-gradient(135deg, #6c5ce7 0%, #5648c4 100%);
    color: #fff;
    border: none;
    transition: filter 0.15s ease;
}
.btn-balance:hover, .btn-balance:focus { color: #fff; filter: brightness(1.08); }

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
.seg-table-wrap.is-proposed tbody tr.total-row td {
    background: #efeaff;
}

/* Helper note under tables */
.seg-helper {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 0.4rem;
}

/* App-type badges - keep semantic color but soften */
.app-type-badge {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.45em 0.85em;
    border-radius: 0.4rem;
}

/* Form field focus polish */
#approvalForm .form-control:focus,
#approvalForm .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}

/* Action row at bottom */
.approve-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.25rem;
}

/* Modal refinements */
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
#prevCommentsModal .modal-title {
    color: #fff !important;
    font-weight: 600;
}
#segmentEditModal .btn-close,
#segmentHistoryModal .btn-close,
#prevCommentsModal .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.85;
}

/* Custom close button — bypasses Vuexy/Bootstrap .btn-close override that hides the X icon */
.ai-modal-close {
    background: transparent;
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    cursor: pointer;
    opacity: 0.85;
    transition: all .2s ease;
    padding: 0;
    line-height: 1;
    margin-left: auto;
    flex-shrink: 0;
}
.ai-modal-close:hover { background: rgba(255,255,255,0.18); opacity: 1; }
.ai-modal-close i { color: #fff; }
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
<?php
$_appNo = $app['application_no'] ?? '';
if (!$_appNo && function_exists('generateApplicationNo')) {
    $_appNo = generateApplicationNo($leaveApplicationID, $app['submitDate'] ?? '');
}
?>
<div class="row mb-4 align-items-center">
    <div class="col-12 col-lg-5">
        <h4 class="fw-bold mb-0">
            <i class="ti <?= $isSupervisor ? 'tabler-thumb-up' : 'tabler-clipboard-check' ?> me-2 text-primary"></i>
            <?= $isSupervisor ? 'ছুটির সুপারিশ' : 'ছুটির অনুমোদন' ?>
        </h4>
        <?php if ($_appNo): ?>
            <div class="text-muted small mt-1 ms-1"><i class="ti tabler-hash me-1"></i>আবেদন নং: <strong class="text-dark"><?= htmlspecialchars($_appNo) ?></strong></div>
        <?php else: ?>
            <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i><?= $isSupervisor ? 'আবেদনটি পর্যালোচনা করে সুপারিশ করুন' : 'আবেদনটি পর্যালোচনা করে অনুমোদন করুন' ?></div>
        <?php endif; ?>
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
        <a href="approval.php?menuslug=<?= urlencode($menuslug) ?>" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<?php
// ── Build messaging-thread events ────────────────────────────────
// Each event: ['ts' => unix timestamp, 'role' => …, 'name' => …, 'title' => …,
//              'badge' => [label, css var], 'icon' => '…', 'color' => '#…',
//              'subject' => optional, 'body' => …, 'extra' => optional]
$threadEvents = [];

// 1) Applicant's original application (always first).
// Include the supervisor the applicant selected as `extra` so the
// timeline explicitly shows "→ [supervisor name]" — otherwise the
// initial "sent to supervisor for recommendation" step is implicit
// and readers can't tell who received it first.
$applicantTs = !empty($app['submitDate']) ? strtotime($app['submitDate']) : 0;
$applicantBodyParts = [];
if (!empty(trim($app['subject'] ?? ''))) {
    $applicantBodyParts[] = '<strong>বিষয়:</strong> ' . htmlspecialchars($app['subject']);
}
if (!empty(trim($app['leaveApplication'] ?? ''))) {
    $applicantBodyParts[] = nl2br(htmlspecialchars($app['leaveApplication']));
}
$_supName = '';
foreach ($sigHistory as $_sh) {
    if ((int)($_sh['isSupervisor'] ?? 0) === 1) {
        $_supName = trim($_sh['employee_name'] ?? '');
        break;
    }
}
$threadEvents[] = [
    'ts'     => $applicantTs,
    'order'  => 0,
    'name'   => $emp['employee_name'] ?? 'আবেদনকারী',
    'title'  => trim(($emp['job_title_name'] ?? '') . (!empty($emp['section_name']) ? ', ' . $emp['section_name'] : '')),
    'badge'  => ['আবেদনকারী', '#e8e5ff', '#5648c4'],
    'color'  => '#6c5ce7',
    'icon'   => 'tabler-user',
    'extra'  => $_supName !== '' ? '→ ' . htmlspecialchars($_supName) . ' (সুপারভাইজার)' : '',
    'body'   => $applicantBodyParts ? implode('<br>', $applicantBodyParts) : '<em class="text-muted">— কোনো বিবরণ নেই —</em>',
];

// 2) Supervisor's recommendation note (from leave_data_for_approval)
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

// 3) Admin initiator's note(s)
if (!empty($adminNoteHistory)) {
    // Every forward note from history — keeps order across re-forwards after return
    foreach ($adminNoteHistory as $anh) {
        $threadEvents[] = [
            'ts'    => !empty($anh['createdAt']) ? strtotime($anh['createdAt']) : 0,
            'order' => 2,
            'name'  => $anh['adminInitiatorName'] ?? ($adminInitiatorDetails['employee_name'] ?? 'নোট উপস্থাপনকারী'),
            'title' => $anh['adminInitiatorTitle'] ?? ($initiatorDesignation['job_title_name'] ?? ''),
            'badge' => ['নোট উপস্থাপনকারী', '#ede5fa', '#5e3eaa'],
            'color' => '#6f42c1',
            'icon'  => 'tabler-user-edit',
            'body'  => nl2br(htmlspecialchars($anh['note'])),
        ];
    }
} elseif (!empty($app['adminNote'])) {
    // Legacy fallback — apps forwarded before the history table existed
    $threadEvents[] = [
        'ts'    => !empty($app['adminNoteDate']) ? strtotime($app['adminNoteDate']) : 0,
        'order' => 2,
        'name'  => $adminInitiatorDetails['employee_name'] ?? 'নোট উপস্থাপনকারী',
        'title' => $initiatorDesignation['job_title_name'] ?? '',
        'badge' => ['নোট উপস্থাপনকারী', '#ede5fa', '#5e3eaa'],
        'color' => '#6f42c1',
        'icon'  => 'tabler-user-edit',
        'body'  => nl2br(htmlspecialchars($app['adminNote'])),
    ];
}

// 4) Each approved non-supervisor signatory
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

// 4b) Historical approvals — sigHistory only holds the LATEST state of
// each row, so a signatory who approved, was rewound after a ফেরত, and
// re-approved appears only once. Iterate audit_log entries and add
// EXTRA events for any older approval whose timestamp doesn't match the
// current row's rendered ts. The historical body is generic because the
// specific note wasn't preserved — only the current row keeps its note.
foreach ($approvalAuditEventsAll as $ae) {
    $_aeName   = $ae['name'];
    $_aeAction = $ae['action'];
    $_isSupAction = ($_aeAction === 'leave_recommended');
    $_currentTs = $_isSupAction
        ? ($approvalAuditTs[$_aeName . '|leave_recommended'] ?? 0)
        : ($approvalAuditTs[$_aeName . '|leave_chain_approved']
           ?? $approvalAuditTs[$_aeName . '|leave_approved'] ?? 0);
    // Skip the LATEST entry per (actor, action) — already rendered above.
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

// 5) Return (ফেরত) entries
foreach ($returnHistory as $rh) {
    $rType = $rh['returnType'] ?? '';
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

// 6) Resubmission events — the applicant edited and resent after পুনঃ যাচাই.
// Sourced from audit_log; the applicant's name comes from the audit row and
// designation from the already-loaded employee record. `note` on the audit
// row is "segments=N" (internal marker), not a user message, so we render a
// friendly description instead.
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

// Sort chronologically; same timestamp falls back to defined order
usort($threadEvents, function($a, $b) {
    if ($a['ts'] === $b['ts']) return $a['order'] <=> $b['order'];
    return $a['ts'] <=> $b['ts'];
});

// Build the thread HTML once — used by the modal below (no inline accordion anymore)
$threadHtml = '';
if (!empty($threadEvents)) {
    $threadHtml = '<div class="thread-wrap" id="commentThread">';
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
?>

<div class="card approve-card shadow-sm border-0">
    <div class="card-body">
        <form id="approvalForm">
            <input type="hidden" name="dataID"             value="<?= $dataID ?>">
            <input type="hidden" name="leaveApplicationID" value="<?= $leaveApplicationID ?>">
            <input type="hidden" name="isSupervisor"       value="<?= $isSupervisor ?>">

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
                    <?= htmlspecialchars($emp['employee_name']) ?>,
                    <?= htmlspecialchars($emp['job_title_name'] ?? '') ?>,
                    <?= htmlspecialchars($emp['section_name']   ?? '') ?>,
                    <?= htmlspecialchars($emp['organization_name'] ?? '') ?>
                </div>
            </div>

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

            <!-- প্রস্তাবিত ছুটি (proposed — editable, supervisor/signatory's recommendation) -->
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

                    <!-- Hidden fields auto-populated from proposed segments for backend compat -->
                    <input type="hidden" id="leaveFrom"     name="leaveFrom"     value="<?= htmlspecialchars(!empty($proposedSegs) ? $proposedSegs[0]['dateFrom'] : '') ?>">
                    <input type="hidden" id="leaveTo"       name="leaveTo"       value="<?= htmlspecialchars(!empty($proposedSegs) ? end($proposedSegs)['dateTo'] : '') ?>">
                    <input type="hidden" id="approvedDays"  name="approvedDays"  value="<?= array_sum(array_column($proposedSegs, 'days')) ?>">
                    <input type="hidden" name="approvedLeaveType" value="<?= !empty($proposedSegs) ? (int)$proposedSegs[0]['leaveType'] : '' ?>">
                </div>
            </div>

            <!-- কর্তন হইবে: removed (per-segment leaveType replaces this in multi-segment system) -->
            <?php if (!$isSupervisor): ?>
                <input type="hidden" name="leaveTypeInTwo" value="<?= !empty($proposedSegs) ? (int)$proposedSegs[0]['leaveType'] : (int)$app['leaveTypeInTwo'] ?>">
            <?php endif; ?>

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
                <label class="col-md-3 info-label col-form-label" for="note">
                    মন্তব্য <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <textarea class="form-control" name="note" id="note" rows="3"
                        placeholder="<?= $isSupervisor ? 'সুপারিশের মন্তব্য লিখুন...' : 'অনুমোদনের মন্তব্য লিখুন...' ?>"
                        required><?= htmlspecialchars($defaultComment) ?></textarea>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="approve-actions d-flex gap-2 flex-wrap align-items-center">
                <a href="approval.php?menuslug=<?= urlencode($menuslug) ?>" class="btn btn-label-secondary">
                    <i class="ti tabler-arrow-left me-1"></i>বাতিল
                </a>
                <button type="button" class="btn btn-label-warning ms-auto" id="returnBtn"
                        title="<?= htmlspecialchars($returnTarget['name']) ?> এর কাছে ফেরত পাঠান">
                    <i class="ti tabler-corner-up-left me-1"></i>ফেরত পাঠান
                </button>
                <button type="button" class="btn btn-label-danger" id="declineBtn">
                    <i class="ti tabler-x me-1"></i>না মঞ্জুর করুন
                </button>
                <button type="submit" class="btn btn-success px-4" id="approveBtn">
                    <i class="ti tabler-check me-1"></i><?= $isSupervisor ? 'সুপারিশ করুন' : 'অনুমোদন করুন' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     Segment Edit Modal — approver can add/remove/modify segments
═══════════════════════════════════════════════════════════ -->
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
                    আপনার পরিবর্তনগুলো history-তে রেকর্ড হবে। Save করলে পরবর্তী signatory পরিবর্তিত state দেখবেন।
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

<!-- ═══════════════════════════════════════════════════════════
     Segment History Modal
═══════════════════════════════════════════════════════════ -->
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
                        <?php
                        // Render one segment payload (from oldData/newData JSON) as a
                        // seg-pill chip. `removed` rows only carry oldData, and `edited`
                        // rows carry both — guarding on newData alone left the বিবরণ
                        // column blank for removals and hid the before-state on edits.
                        $renderSegChip = function ($json, $strike = false) use ($leaveTypeMap) {
                            $d = $json ? json_decode($json, true) : null;
                            if (!$d || !isset($d['leaveType'])) return '';
                            $label = $leaveTypeMap[(int)$d['leaveType']] ?? 'অজানা';
                            $from  = !empty($d['dateFrom']) ? banglaNumber(date('d/m/Y', strtotime($d['dateFrom']))) : '';
                            $to    = !empty($d['dateTo'])   ? banglaNumber(date('d/m/Y', strtotime($d['dateTo'])))   : '';
                            $days  = banglaNumber((int)($d['days'] ?? 0));
                            $style = 'display:inline-block;background:#f9f5e8;color:#8a6d1a;padding:3px 9px;'
                                   . 'border-radius:4px;font-size:0.78rem;border:1px solid #f0e7c8;line-height:1.5;';
                            if ($strike) {
                                $style = 'display:inline-block;background:#fdecec;color:#a52a2a;padding:3px 9px;'
                                       . 'border-radius:4px;font-size:0.78rem;border:1px solid #f5c5c1;'
                                       . 'line-height:1.5;text-decoration:line-through;';
                            }
                            return '<span style="' . $style . '">'
                                 . htmlspecialchars($label) . ' — ' . $from . ' → ' . $to
                                 . ' (' . $days . ' দিন)</span>';
                        };
                        ?>
                        <?php foreach ($segHistory as $h): ?>
                            <?php
                            $actionBadge = ['created'=>'success','edited'=>'warning','removed'=>'danger'][$h['action']] ?? 'secondary';
                            $actionLabel = ['created'=>'যোগ','edited'=>'সম্পাদনা','removed'=>'অপসারণ'][$h['action']] ?? $h['action'];
                            $oldChip = $renderSegChip($h['oldData'] ?? null, $h['action'] === 'removed');
                            $newChip = $renderSegChip($h['newData'] ?? null);
                            ?>
                            <tr>
                                <td><?= banglaNumber(date('d/m/Y H:i', strtotime($h['changedAt']))) ?></td>
                                <td><?= htmlspecialchars($h['changedByName'] ?? $h['employee_name'] ?? '—') ?></td>
                                <td><span class="badge bg-label-<?= $actionBadge ?>"><?= $actionLabel ?></span></td>
                                <td>
                                    <?php if ($h['action'] === 'edited' && $oldChip !== '' && $newChip !== ''): ?>
                                        <?= $oldChip ?>
                                        <i class="ti tabler-arrow-narrow-right text-muted mx-1"></i>
                                        <?= $newChip ?>
                                    <?php elseif ($newChip !== ''): ?>
                                        <?= $newChip ?>
                                    <?php elseif ($oldChip !== ''): ?>
                                        <?= $oldChip ?>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                    <?php if (!empty($h['note'])): ?>
                                        <small class="text-muted d-block mt-1"><?= htmlspecialchars($h['note']) ?></small>
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


<script>
// Wait for jQuery (loaded by footer) before running modal logic.
function __initApproveModalScript() {
    if (typeof jQuery === 'undefined') {
        // jQuery not yet loaded — retry shortly
        return setTimeout(__initApproveModalScript, 50);
    }
    var $ = jQuery;
(function(){
    const LEAVE_TYPES = <?= json_encode($leaveTypes, JSON_UNESCAPED_UNICODE) ?>;
    const APP_ID = <?= (int)$leaveApplicationID ?>;
    const APPROVAL_ID = <?= (int)$dataID ?>;
    const INITIAL_SEGS = <?= json_encode(array_map(function($s){ return [
        'segmentID' => (int)$s['dataID'],
        'leaveType' => (int)$s['leaveType'],
        'dateFrom'  => $s['dateFrom'],
        'dateTo'    => $s['dateTo'],
        'days'      => (int)$s['days'],
    ]; }, $appSegments), JSON_UNESCAPED_UNICODE) ?>;

    function fmtDmy(yyyymmdd) {
        if (!yyyymmdd) return '';
        const p = yyyymmdd.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }
    function parseDmy(dmy) {
        if (!dmy) return null;
        const p = dmy.split('/');
        if (p.length !== 3) return null;
        return p[2] + '-' + p[1] + '-' + p[0];
    }
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
                <div class="col-md-4">
                    <label class="form-label small">ছুটির ধরণ</label>
                    <select class="form-select form-select-sm modal-seg-type">${opts}</select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">শুরু</label>
                    <input type="text" class="form-control form-control-sm modal-seg-from" placeholder="dd/mm/yyyy" value="${seg ? fmtDmy(seg.dateFrom) : ''}" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">শেষ</label>
                    <input type="text" class="form-control form-control-sm modal-seg-to" placeholder="dd/mm/yyyy" value="${seg ? fmtDmy(seg.dateTo) : ''}" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">দিন</label>
                    <input type="text" class="form-control form-control-sm modal-seg-days" value="${seg ? seg.days : ''}" readonly>
                </div>
            </div>
        </div>`;
    }

    function rebuildModalSegments() {
        const $c = $('#modalSegments');
        $c.empty();
        const segs = INITIAL_SEGS.length ? INITIAL_SEGS : [{leaveType:'',dateFrom:'',dateTo:'',days:0}];
        segs.forEach((s, i) => $c.append(renderRow(s, i)));
        bindModalHandlers();
        recalcModal();
    }

    function bindModalHandlers() {
        $('#modalSegments .modal-seg-from, #modalSegments .modal-seg-to').each(function(){
            if (!$(this).hasClass('hasDatepicker')) {
                $(this).datepicker({ dateFormat: 'dd/mm/yy' });
            }
        });
        $('#modalSegments .modal-seg-from, #modalSegments .modal-seg-to, #modalSegments .modal-seg-type')
            .off('change.seg').on('change.seg', recalcModal);
        $('#modalSegments .modal-remove-seg').off('click.seg').on('click.seg', function(){
            if ($('.modal-segment').length <= 1) return;
            $(this).closest('.modal-segment').remove();
            renumberModal();
            recalcModal();
        });
    }

    function renumberModal() {
        $('.modal-segment').each(function(i){
            $(this).find('.modal-seg-badge').text('ধরন ' + toBn(i+1));
        });
    }

    function collectModalSegs() {
        const segs = [];
        $('.modal-segment').each(function(){
            const lt = parseInt($(this).find('.modal-seg-type').val()) || 0;
            const fromDmy = $(this).find('.modal-seg-from').val();
            const toDmy   = $(this).find('.modal-seg-to').val();
            if (!lt || !fromDmy || !toDmy) return;
            const fromY = parseDmy(fromDmy);
            const toY   = parseDmy(toDmy);
            const days  = Math.floor((new Date(toY) - new Date(fromY)) / 86400000) + 1;
            segs.push({
                segmentID: $(this).attr('data-seg-id') || null,
                leaveType: lt,
                dateFrom: fromY,
                dateTo: toY,
                days: days
            });
            $(this).find('.modal-seg-days').val(days > 0 ? days : '');
        });
        return segs;
    }

    function recalcModal() {
        const segs = collectModalSegs();
        const total = segs.reduce((a, s) => a + (s.days > 0 ? s.days : 0), 0);
        $('#modalTotalDays').text(toBn(total) + ' দিন');

        // BSR validation
        let msg = null;
        const hasCl = segs.some(s => s.leaveType === 8);
        const hasNonCl = segs.some(s => s.leaveType && s.leaveType !== 8);
        if (hasCl && hasNonCl) msg = 'নৈমিত্তিক ছুটি অন্য ধরনের ছুটির সাথে এক আবেদনে মিশানো যাবে না (সরকারি চাকরি বিধিমালা)।';
        else {
            for (const s of segs) {
                if (s.leaveType === 1 && s.days > 120) { msg = `পূর্ণ গড় বেতনে একটানা সর্বোচ্চ ১২০ দিন।`; break; }
                if (s.leaveType === 8 && s.days > 10)  { msg = `নৈমিত্তিক একটানা সর্বোচ্চ ১০ দিন।`; break; }
            }
        }
        if (!msg) {
            for (let i = 0; i < segs.length; i++) {
                for (let j = i+1; j < segs.length; j++) {
                    if (segs[i].dateFrom <= segs[j].dateTo && segs[j].dateFrom <= segs[i].dateTo) {
                        msg = `ধরন ${i+1} ও ধরন ${j+1} এর তারিখ overlap।`;
                        break;
                    }
                }
                if (msg) break;
            }
        }
        const $w = $('#modalBsrWarning');
        if (msg) { $w.text(msg).show(); $('#saveSegmentsBtn').prop('disabled', true); }
        else     { $w.hide();        $('#saveSegmentsBtn').prop('disabled', false); }
    }

    // Namespaced off/on so this script block is idempotent — turbo:load or
    // any re-render of the page (e.g. after modal open) would otherwise stack
    // handlers and cause each click on "আরেকটা ধরন যোগ করুন" to add 2, 4, 6+ rows.
    $('#segmentEditModal').off('shown.bs.modal.seg').on('shown.bs.modal.seg', rebuildModalSegments);

    // Also render once on page load so segments are pre-populated even before modal opens
    $(function(){
        if (typeof rebuildModalSegments === 'function') {
            try { rebuildModalSegments(); } catch (e) { console.error('rebuildModalSegments init error:', e); }
        }
    });

    $('#modalAddSegment').off('click.addSeg').on('click.addSeg', function(){
        const idx = $('.modal-segment').length;
        $('#modalSegments').append(renderRow(null, idx));
        bindModalHandlers();
        recalcModal();
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
            data: { applicationID: APP_ID, approvalID: APPROVAL_ID, segments: JSON.stringify(segs) },
            dataType: 'json',
            success: function(res){
                if (res.ok) {
                    Swal.fire({title:'সম্পন্ন', text:'Segments সংরক্ষিত। পেজ reload হচ্ছে...', icon:'success',
                               timer: 1500, showConfirmButton: false,
                               confirmButtonColor:'#6c5ce7', customClass:{confirmButton:'btn btn-primary'}, buttonsStyling:false})
                        .then(() => location.reload());
                } else {
                    Swal.fire({title:'ত্রুটি', text: res.error || 'সংরক্ষণ ব্যর্থ', icon:'error',
                               confirmButtonColor:'#ff3e1d', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                }
            },
            error: function(xhr) {
                Swal.fire({title:'নেটওয়ার্ক ত্রুটি', text: xhr.responseText || 'সার্ভার থেকে উত্তর পাওয়া যায়নি', icon:'error',
                           confirmButtonColor:'#ff3e1d', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
            }
        });
    });
})();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', __initApproveModalScript);
} else {
    __initApproveModalScript();
}
document.addEventListener('turbo:load', __initApproveModalScript);
document.addEventListener('turbo:frame-load', __initApproveModalScript);
</script>

<?php
// Applicant balance viewer modal
$applicantEmpID = (int)$app['applicantID'];
$applicantName  = $emp['employee_name'] ?? '';
include(__DIR__ . '/../../includes/applicant_balance_modal.php');
?>

<script>
var MENUSLUG = <?= json_encode($menuslug) ?>;
var IS_SUPERVISOR = <?= $isSupervisor ?>;

// Wait for libs (loaded by footer_vuexy AFTER this script on hard load) before
// binding form handlers. On Turbo nav libs are already in memory so this runs
// immediately. Keeps the script inside turbo-frame so handlers re-bind on nav.
(function bootApproveActions() {
    if (typeof jQuery === 'undefined' || !jQuery.fn ||
        typeof Swal === 'undefined') {
        return setTimeout(bootApproveActions, 20);
    }

$(document).ready(function () {

    // Comment-thread collapse toggle (smooth slide)
    $('.history-card-collapsible .history-card-toggle').on('click', function () {
        var $card  = $(this).closest('.history-card-collapsible');
        var $panel = $card.find('.thread-wrap').first();
        var nowCollapsed = $card.hasClass('is-collapsed');
        if (nowCollapsed) {
            $card.removeClass('is-collapsed');
            $panel.stop(true, true).hide().slideDown(240);
            $(this).attr('aria-expanded', 'true');
        } else {
            $panel.stop(true, true).slideUp(220, function () {
                $card.addClass('is-collapsed');
            });
            $(this).attr('aria-expanded', 'false');
        }
    });

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

    // Approve / Recommend submit
    $('#approvalForm').on('submit', function (e) {
        e.preventDefault();
        var label = IS_SUPERVISOR ? 'সুপারিশ' : 'অনুমোদন';

        // মন্তব্য is mandatory — block submission with a friendly error
        var noteVal = ($('#note').val() || '').trim();
        if (noteVal === '') {
            Swal.fire({
                icon: 'warning',
                title: 'মন্তব্য প্রয়োজন',
                text:  label + ' করার আগে মন্তব্য লিখুন।',
                confirmButtonColor: '#ff9f43',
                customClass: { confirmButton: 'btn btn-warning' },
                buttonsStyling: false
            }).then(function () {
                $('#note').trigger('focus');
            });
            return;
        }

        Swal.fire({
            title: 'নিশ্চিত করুন',
            text: 'আপনি কি এই আবেদনটি ' + label + ' করতে চান?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28c76f',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, ' + label + ' করুন',
            cancelButtonText: 'বাতিল',
            customClass: {
                confirmButton: 'btn btn-success me-3',
                cancelButton:  'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var $btn = $('#approveBtn');
            $btn.attr('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span>প্রেরণ হচ্ছে...');
            $.ajax({
                url:      '../../api/leave/approve-application.php',
                type:     'POST',
                data:     $('#approvalForm').serialize(),
                dataType: 'json',
                success:  function (res) {
                    $btn.removeAttr('disabled')
                        .html('<i class="ti tabler-check me-1"></i>' + (IS_SUPERVISOR ? 'সুপারিশ করুন' : 'অনুমোদন করুন'));
                    if (res.status == 1) {
                        Swal.fire({ icon: 'success', title: 'সম্পন্ন', text: res.message,
                                    timer: 2000, showConfirmButton: false,
                                    confirmButtonColor: '#6c5ce7',
                                    customClass:{confirmButton:'btn btn-primary'}, buttonsStyling:false })
                            .then(function () {
                                window.location = 'approval.php?menuslug=' + encodeURIComponent(MENUSLUG);
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ত্রুটি', text: res.message,
                                    confirmButtonColor: '#ff3e1d',
                                    customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false });
                    }
                },
                error: function () {
                    $btn.removeAttr('disabled')
                        .html('<i class="ti tabler-check me-1"></i>' + (IS_SUPERVISOR ? 'সুপারিশ করুন' : 'অনুমোদন করুন'));
                    Swal.fire({ icon: 'error', title: 'ত্রুটি', text: 'সমস্যা হয়েছে। আবার চেষ্টা করুন।',
                                confirmButtonColor: '#ff3e1d',
                                customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false });
                }
            });
        });
    });

    // Decline button
    $('#declineBtn').on('click', function () {
        Swal.fire({
            title:        'না মঞ্জুর করুন',
            input:        'textarea',
            inputLabel:   'কারণ লিখুন',
            inputValue:   $('#note').val(),
            inputPlaceholder: 'না মঞ্জুরের কারণ...',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'না মঞ্জুর করুন',
            cancelButtonText:  'বাতিল',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton:  'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url:  '../../api/leave/approve-application.php',
                type: 'POST',
                data: {
                    dataID:             <?= $dataID ?>,
                    leaveApplicationID: <?= $leaveApplicationID ?>,
                    isSupervisor:       <?= $isSupervisor ?>,
                    action:             'decline',
                    note:               result.value
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status == 1) {
                        Swal.fire({ icon: 'success', title: 'সম্পন্ন', text: res.message,
                                    timer: 2000, showConfirmButton: false,
                                    confirmButtonColor: '#6c5ce7',
                                    customClass:{confirmButton:'btn btn-primary'}, buttonsStyling:false })
                            .then(function () {
                                window.location = 'approval.php?menuslug=' + encodeURIComponent(MENUSLUG);
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ত্রুটি', text: res.message,
                                    confirmButtonColor: '#ff3e1d',
                                    customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false });
                    }
                }
            });
        });
    });

    // ── Return (ফেরত পাঠান) button — uses the existing মন্তব্য textarea ────
    var RETURN_TARGET_NAME = <?= json_encode($returnTarget['name'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
    $('#returnBtn').on('click', function () {
        var note = $('#note').val().trim();
        if (!note) {
            Swal.fire({
                icon: 'warning',
                title: 'মন্তব্য প্রয়োজন',
                text: 'মন্তব্য ঘরে ফেরত পাঠানোর কারণ লিখুন',
                confirmButtonColor: '#6c5ce7',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            }).then(function () {
                $('#note').focus();
            });
            return;
        }

        Swal.fire({
            title: 'নিশ্চিত করুন',
            html: 'আবেদনটি <strong>' + $('<div>').text(RETURN_TARGET_NAME).html() + '</strong> এর কাছে ফেরত পাঠানো হবে।',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6c5ce7',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, ফেরত পাঠান',
            cancelButtonText: 'বাতিল',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton:  'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var $btn = $('#returnBtn');
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span>প্রক্রিয়াকরণ...');

            $.ajax({
                url: '../../api/leave/return-application.php',
                type: 'POST',
                data: {
                    dataID:             <?= $dataID ?>,
                    leaveApplicationID: <?= $leaveApplicationID ?>,
                    note:               note
                },
                dataType: 'json',
                success: function (res) {
                    $btn.prop('disabled', false)
                        .html('<i class="ti tabler-corner-up-left me-1"></i>ফেরত পাঠান');
                    if (res.status == 1) {
                        Swal.fire({
                            icon: 'success', title: 'সম্পন্ন', text: res.message,
                            timer: 2200, showConfirmButton: false,
                            confirmButtonColor: '#6c5ce7',
                            customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                        }).then(function () {
                            window.location = 'approval.php?menuslug=' + encodeURIComponent(MENUSLUG);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error', title: 'ত্রুটি', text: res.message,
                            confirmButtonColor: '#ff3e1d',
                            customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                        });
                    }
                },
                error: function () {
                    $btn.prop('disabled', false)
                        .html('<i class="ti tabler-corner-up-left me-1"></i>ফেরত পাঠান');
                    Swal.fire({
                        icon: 'error', title: 'ত্রুটি',
                        text: 'সার্ভার ত্রুটি — আবার চেষ্টা করুন',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                }
            });
        });
    });
});

})(); // end bootApproveActions
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
