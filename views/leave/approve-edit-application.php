<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Re-query full user
$_stmt = mysqli_prepare($con,
    "SELECT user_id, full_name, employee_id, isCenterAdmin, dashboardType, user_type,
            user_group_id, organization_id
     FROM user_list WHERE user_id = ?");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_stmt, 's', $_un);
mysqli_stmt_execute($_stmt);
$_full = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt)) ?: [];
mysqli_stmt_close($_stmt);
$getUserInfoQRW = array_merge($getUserInfoQRW ?? [], $_full);
$currentEmployeeID = (int)($getUserInfoQRW['employee_id'] ?? 0);

$editID = (int)($_GET['editID'] ?? 0);
if ($editID <= 0) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>অবৈধ আইডি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Load parent edit request
$ledStmt = mysqli_prepare($con, "SELECT * FROM leave_edit_data WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($ledStmt, 'i', $editID);
mysqli_stmt_execute($ledStmt);
$led = mysqli_fetch_assoc(mysqli_stmt_get_result($ledStmt));
mysqli_stmt_close($ledStmt);

if (!$led) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>সংশোধন প্রস্তাব খুঁজে পাওয়া যায়নি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$leaveAppID = (int)$led['leaveApplicationID'];

// Verify current user is the current signatory
$myRowStmt = mysqli_prepare($con,
    "SELECT * FROM leave_edit_data_for_approval
     WHERE editRequestID = ? AND signatory = ? AND isApproved = 0 LIMIT 1");
mysqli_stmt_bind_param($myRowStmt, 'ii', $editID, $currentEmployeeID);
mysqli_stmt_execute($myRowStmt);
$myRow = mysqli_fetch_assoc(mysqli_stmt_get_result($myRowStmt));
mysqli_stmt_close($myRowStmt);

$canAct = false;
$alreadyHandled = false;
if ($myRow) {
    // Confirm no earlier-serial row is still pending
    $checkStmt = mysqli_prepare($con,
        "SELECT COUNT(*) c FROM leave_edit_data_for_approval
         WHERE editRequestID = ? AND serial < ? AND isApproved = 0");
    mysqli_stmt_bind_param($checkStmt, 'ii', $editID, $myRow['serial']);
    mysqli_stmt_execute($checkStmt);
    $blockers = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt))['c'] ?? 0);
    mysqli_stmt_close($checkStmt);
    $canAct = ($blockers === 0 && (int)$led['status'] === 0);
    if ($blockers > 0) {
        $alreadyHandled = false; // pending but blocked by earlier
    }
}

// Applicant
$applicant = null;
$apStmt = mysqli_prepare($con,
    "SELECT el.*, jt.job_title_name, s.section_name, o.organization_name
     FROM employee_list el
     LEFT JOIN job_title jt    ON el.designation     = jt.id
     LEFT JOIN sections s      ON el.section_id      = s.id
     LEFT JOIN organization o  ON el.organization_id = o.id
     WHERE el.id = ? LIMIT 1");
mysqli_stmt_bind_param($apStmt, 'i', $led['applicantID']);
mysqli_stmt_execute($apStmt);
$applicant = mysqli_fetch_assoc(mysqli_stmt_get_result($apStmt));
mysqli_stmt_close($apStmt);

// Original leave + approved segments (kind='proposed' from main flow)
$origAppStmt = mysqli_prepare($con,
    "SELECT la.*, lt.leaveTitle FROM leave_applications la
     LEFT JOIN leave_types lt ON la.approvedLeaveType = lt.leaveID
     WHERE la.dataID = ? LIMIT 1");
mysqli_stmt_bind_param($origAppStmt, 'i', $leaveAppID);
mysqli_stmt_execute($origAppStmt);
$origApp = mysqli_fetch_assoc(mysqli_stmt_get_result($origAppStmt));
mysqli_stmt_close($origAppStmt);

function load_segs($con, $editID, $kind) {
    $rows = [];
    $q = mysqli_prepare($con,
        "SELECT s.*, lt.leaveTitle FROM leave_edit_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.editRequestID = ? AND s.kind = ?
         ORDER BY s.serial ASC, s.dataID ASC");
    mysqli_stmt_bind_param($q, 'is', $editID, $kind);
    mysqli_stmt_execute($q);
    $r = mysqli_stmt_get_result($q);
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($q);
    return $rows;
}

function load_orig_approved_segs($con, $leaveAppID) {
    $rows = [];
    $q = mysqli_prepare($con,
        "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.applicationID = ? AND s.kind = 'proposed'
         ORDER BY s.serial ASC, s.dataID ASC");
    mysqli_stmt_bind_param($q, 'i', $leaveAppID);
    mysqli_stmt_execute($q);
    $r = mysqli_stmt_get_result($q);
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($q);
    if (empty($rows)) {
        $q2 = mysqli_prepare($con,
            "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
             LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
             WHERE s.applicationID = ? AND s.kind = 'requested'
             ORDER BY s.serial ASC, s.dataID ASC");
        mysqli_stmt_bind_param($q2, 'i', $leaveAppID);
        mysqli_stmt_execute($q2);
        $r2 = mysqli_stmt_get_result($q2);
        while ($row = mysqli_fetch_assoc($r2)) $rows[] = $row;
        mysqli_stmt_close($q2);
    }
    return $rows;
}

$origApprovedSegs = load_orig_approved_segs($con, $leaveAppID);
$proposedSegs     = load_segs($con, $editID, 'proposed');

// Chain history
$chainStmt = mysqli_prepare($con,
    "SELECT ldfa.*, el.employee_name, jt.job_title_name
     FROM leave_edit_data_for_approval ldfa
     LEFT JOIN employee_list el ON ldfa.signatory = el.id
     LEFT JOIN job_title jt     ON el.designation  = jt.id
     WHERE ldfa.editRequestID = ?
     ORDER BY ldfa.serial ASC");
mysqli_stmt_bind_param($chainStmt, 'i', $editID);
mysqli_stmt_execute($chainStmt);
$chain = mysqli_fetch_all(mysqli_stmt_get_result($chainStmt), MYSQLI_ASSOC);
mysqli_stmt_close($chainStmt);

// Return history
$returnStmt = mysqli_prepare($con,
    "SELECT * FROM leave_edit_return_history WHERE editRequestID = ? ORDER BY createdAt ASC, dataID ASC");
mysqli_stmt_bind_param($returnStmt, 'i', $editID);
mysqli_stmt_execute($returnStmt);
$returnHistory = mysqli_fetch_all(mysqli_stmt_get_result($returnStmt), MYSQLI_ASSOC);
mysqli_stmt_close($returnStmt);

// Admin initiator info
$initRow = null;
if ($led['adminInitiator']) {
    $initStmt = mysqli_prepare($con,
        "SELECT ul.full_name, el.employee_name, jt.job_title_name
         FROM user_list ul
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         LEFT JOIN job_title jt     ON el.designation  = jt.id
         WHERE ul.dataID = ? LIMIT 1");
    mysqli_stmt_bind_param($initStmt, 'i', $led['adminInitiator']);
    mysqli_stmt_execute($initStmt);
    $initRow = mysqli_fetch_assoc(mysqli_stmt_get_result($initStmt));
    mysqli_stmt_close($initStmt);
}

$leaveTypes = [];
$ltQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types WHERE leaveID != 22 ORDER BY leaveTitle ASC");
while ($r = mysqli_fetch_assoc($ltQ)) $leaveTypes[] = $r;

function be_num($n) {
    return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}
?>

<style>
.app-wrap { max-width: 1200px; }
.app-card { border-radius: 0.75rem; }
.app-card .card-body { padding: 1.1rem 1.5rem; }

.section-hdr {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; margin: 14px 0 12px;
    background: #fafbfd; border: 1px solid #eef0f5;
    border-left: 3px solid var(--sec-accent, #6c5ce7);
    border-radius: 0.5rem;
}
.section-hdr[data-color="amber"]  { --sec-bg: #fff3e1; --sec-accent: #b8651a; }
.section-hdr[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }
.section-hdr[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.section-hdr[data-color="slate"]  { --sec-bg: #eef2f7; --sec-accent: #4b5563; }
.section-hdr .section-num {
    width: 26px; height: 26px; border-radius: 0.4rem;
    background: var(--sec-bg); color: var(--sec-accent);
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.82rem;
}
.section-hdr .section-title { font-size: 0.92rem; font-weight: 600; margin: 0; color: #2c2e3a; }
.section-hdr .section-sub   { font-size: 0.72rem; color: #8a90a6; }
.section-hdr .section-text  { flex: 1; min-width: 0; }
.section-hdr .section-icon  {
    width: 32px; height: 32px; border-radius: 0.5rem;
    background: var(--sec-bg); color: var(--sec-accent);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
}

.applicant-card {
    background: linear-gradient(135deg, #f8f7ff 0%, #fefefe 100%);
    border: 1px solid #ddd5f6;
    border-radius: 0.6rem;
    padding: 14px 18px;
    margin-bottom: 14px;
}
.applicant-card .ap-name { font-weight: 700; font-size: 1rem; color: #2c2e3a; }
.applicant-card .ap-meta { font-size: 0.82rem; color: #5d6580; margin-top: 4px; }
.applicant-card .ap-app-no { background: #6c5ce7; color: #fff; padding: 4px 10px; border-radius: 0.3rem; font-size: 0.78rem; font-weight: 600; }

.seg-table { width: 100%; border-collapse: collapse; }
.seg-table th, .seg-table td { padding: 8px 10px; border-bottom: 1px solid #eef0f5; font-size: 0.86rem; vertical-align: middle; }
.seg-table th { background: #fafbfd; color: #5d6580; font-weight: 600; text-align: left; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.02em; }
.seg-table td.text-end { text-align: right; }

.orig-table th, .orig-table td { background: #fffaf0; }
.orig-table th { background: #fde0a8; color: #8b6f47; }
/* .orig-table td above paints every cell, hiding anything set on the <tr>. */
.orig-table .seg-total td { background: #fde0a8; color: #6b4910; font-weight: 700; }
.prop-table th { background: #e6f7ee; color: #2a6c45; }

.note-box {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-left: 3px solid #b8651a;
    padding: 12px 14px;
    border-radius: 0.5rem;
    color: #6b4910;
    font-size: 0.88rem;
    line-height: 1.5;
}

.chain-line {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; margin-bottom: 6px;
    background: #fff; border: 1px solid #eef0f5;
    border-radius: 0.45rem; font-size: 0.86rem;
}
.chain-line.is-done { background: #f0fdf4; border-color: #bbf7d0; }
.chain-line.is-current { background: #f0edff; border-color: #ddd5f6; border-left: 3px solid #6c5ce7; }
.chain-line.is-rejected { background: #fff1f0; border-color: #f5c6c6; border-left: 3px solid #dc3545; }
.chain-line .chain-serial { background:#6c5ce7; color:#fff; min-width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:0.78rem; }
.chain-line.is-done .chain-serial { background:#1a7e44; }
.chain-line.is-rejected .chain-serial { background:#dc3545; }
.chain-line .chain-name { font-weight: 600; color: #2c2e3a; }
.chain-line .chain-sub  { font-size: 0.74rem; color: #8a90a6; }
.chain-line .chain-status { margin-left: auto; font-size: 0.74rem; font-weight: 600; }
.chain-line.is-done .chain-status { color: #1a7e44; }
.chain-line.is-current .chain-status { color: #6c5ce7; }
.chain-line.is-rejected .chain-status { color: #dc3545; }

.action-bar {
    border-top: 1px solid #eef0f5;
    padding-top: 14px;
    margin-top: 14px;
    display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;
}

/* Editable segment row (signatory can adjust before approving) */
.app-card .form-control, .app-card .form-select {
    padding: 0.3rem 0.55rem !important;
    font-size: 0.84rem !important;
    min-height: 30px !important;
    height: 30px !important;
}
.app-card .form-select option { font-size: 0.84rem; }

.return-history-item {
    background: #fffaf0;
    border-left: 3px solid #b8651a;
    padding: 8px 12px;
    border-radius: 0 0.4rem 0.4rem 0;
    margin-bottom: 6px;
    font-size: 0.82rem;
}
</style>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0">
            <i class="ti tabler-pencil me-2 text-primary"></i>ছুটি সংশোধন প্রস্তাব
            <small class="text-muted" style="font-size:0.7em; font-weight: 400;">#<?= be_num($editID) ?></small>
        </h4>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="edit-approval.php?menuslug=leave-edit-approval" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>তালিকা
        </a>
    </div>
</div>

<div class="app-wrap">
<div class="card app-card shadow-sm border-0">
    <div class="card-body">

        <!-- Applicant context -->
        <div class="applicant-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="ap-name"><?= htmlspecialchars($applicant['employee_name'] ?? '—') ?></div>
                    <div class="ap-meta">
                        <i class="ti tabler-id-badge-2 me-1"></i><?= be_num(htmlspecialchars($applicant['employee_id'] ?? '')) ?>
                        <span class="mx-2">•</span>
                        <i class="ti tabler-briefcase me-1"></i><?= htmlspecialchars($applicant['job_title_name'] ?? '—') ?>
                        <span class="mx-2">•</span>
                        <i class="ti tabler-building me-1"></i><?= htmlspecialchars($applicant['organization_name'] ?? '—') ?>
                    </div>
                </div>
                <div>
                    <span class="ap-app-no">আবেদন #<?= be_num($leaveAppID) ?></span>
                    <a href="application-details.php?menuslug=leave-edit-approval&leaveApplicationID=<?= $leaveAppID ?>" target="_blank" class="btn btn-sm btn-label-secondary ms-1" title="মূল আবেদন">
                        <i class="ti tabler-external-link"></i>
                    </a>
                </div>
            </div>
            <?php if ($initRow): ?>
            <div class="mt-2" style="font-size: 0.78rem; color: #5d6580;">
                <i class="ti tabler-user-edit me-1"></i>
                সংশোধন প্রস্তাবক: <strong><?= htmlspecialchars($initRow['employee_name'] ?? $initRow['full_name'] ?? '') ?></strong>
                <?php if (!empty($initRow['job_title_name'])): ?>, <?= htmlspecialchars($initRow['job_title_name']) ?><?php endif; ?>
                <span class="mx-2">•</span>
                <i class="ti tabler-clock me-1"></i><?= htmlspecialchars(trim(($led['submitDate'] ?? '') . ' ' . ($led['submitTime'] ?? ''))) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Section 1: Original approved leave -->
        <div class="section-hdr" data-color="amber">
            <div class="section-num">১</div>
            <div class="section-text">
                <h6 class="section-title">বর্তমান অনুমোদিত ছুটি</h6>
                <span class="section-sub">যা সংশোধনের প্রস্তাব দেওয়া হয়েছে</span>
            </div>
            <span class="section-icon"><i class="ti tabler-clipboard-check"></i></span>
        </div>

        <table class="seg-table orig-table mb-3">
            <thead>
                <tr><th>অংশ</th><th>ছুটির ধরন</th><th>শুরু</th><th>শেষ</th><th class="text-end">দিন</th></tr>
            </thead>
            <tbody>
            <?php $origTotal = 0; if (!empty($origApprovedSegs)): foreach ($origApprovedSegs as $i => $sg): $origTotal += (int)$sg['days']; ?>
                <tr>
                    <td><strong><?= be_num($i + 1) ?></strong></td>
                    <td><?= htmlspecialchars($sg['leaveTitle'] ?? '—') ?></td>
                    <td><?= be_num(date('d/m/Y', strtotime($sg['dateFrom']))) ?></td>
                    <td><?= be_num(date('d/m/Y', strtotime($sg['dateTo']))) ?></td>
                    <td class="text-end"><strong><?= be_num($sg['days']) ?></strong></td>
                </tr>
            <?php endforeach;
            elseif ($origApp): ?>
                <tr>
                    <td><strong>১</strong></td>
                    <td><?= htmlspecialchars($origApp['leaveTitle'] ?? '—') ?></td>
                    <td><?= be_num(date('d/m/Y', strtotime($origApp['approvedDateFrom']))) ?></td>
                    <td><?= be_num(date('d/m/Y', strtotime($origApp['approvedDateTo']))) ?></td>
                    <td class="text-end"><strong><?= be_num($origApp['approvedDays']) ?></strong></td>
                </tr>
                <?php $origTotal = (int)$origApp['approvedDays']; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center text-muted">—</td></tr>
            <?php endif; ?>
                <tr class="seg-total">
                    <td colspan="4">মোট</td>
                    <td class="text-end"><?= be_num($origTotal) ?> দিন</td>
                </tr>
            </tbody>
        </table>

        <!-- Section 2: Proposed (editable by signatory) -->
        <div class="section-hdr" data-color="green">
            <div class="section-num">২</div>
            <div class="section-text">
                <h6 class="section-title">প্রস্তাবিত সংশোধন</h6>
                <span class="section-sub">আপনি অনুমোদনের আগে এডিট করতে পারেন</span>
            </div>
            <span class="section-icon"><i class="ti tabler-edit"></i></span>
        </div>

        <form id="editApproveForm">
            <input type="hidden" name="editID" value="<?= $editID ?>" />

            <div id="propSegs">
                <?php $propTotal = 0; foreach ($proposedSegs as $i => $sg): $propTotal += (int)$sg['days']; ?>
                <div class="row g-2 align-items-end mb-2 prop-seg" data-id="<?= (int)$sg['dataID'] ?>">
                    <div class="col-md-1"><strong><?= be_num($i + 1) ?>.</strong></div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">ছুটির ধরন</label>
                        <select class="form-select prop-lt" name="prop_leaveType[]" <?= $canAct ? '' : 'disabled' ?>>
                            <option value="">--</option>
                            <?php foreach ($leaveTypes as $lt): ?>
                                <option value="<?= (int)$lt['leaveID'] ?>" <?= ((int)$lt['leaveID'] === (int)$sg['leaveType']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lt['leaveTitle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">শুরু</label>
                        <input type="text" class="form-control prop-from" name="prop_from[]" value="<?= htmlspecialchars(date('d/m/Y', strtotime($sg['dateFrom']))) ?>" readonly <?= $canAct ? '' : 'disabled' ?> />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">শেষ</label>
                        <input type="text" class="form-control prop-to" name="prop_to[]" value="<?= htmlspecialchars(date('d/m/Y', strtotime($sg['dateTo']))) ?>" readonly <?= $canAct ? '' : 'disabled' ?> />
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-1">দিন</label>
                        <input type="text" class="form-control prop-days" name="prop_days[]" value="<?= (int)$sg['days'] ?>" readonly />
                    </div>
                    <input type="hidden" name="prop_id[]" value="<?= (int)$sg['dataID'] ?>" />
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-end mb-3" style="font-size:0.86rem;">
                মোট: <strong id="propTotal"><?= be_num($propTotal) ?> দিন</strong>
            </div>

            <!-- Section 3: Admin note + attachment -->
            <div class="section-hdr" data-color="indigo">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">সংশোধনের কারণ</h6>
                    <span class="section-sub">প্রস্তাবকের বিবরণ</span>
                </div>
                <span class="section-icon"><i class="ti tabler-file-description"></i></span>
            </div>

            <div class="note-box mb-3">
                <?= nl2br(htmlspecialchars($led['adminNote'] ?? '—')) ?>
                <?php if (!empty($led['attachment'])): ?>
                <div class="mt-2">
                    <a href="../../uploads/<?= htmlspecialchars($led['attachment']) ?>" target="_blank" class="btn btn-sm btn-label-warning">
                        <i class="ti tabler-paperclip me-1"></i>সংযুক্তি দেখুন
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Section 4: Chain history -->
            <div class="section-hdr" data-color="slate">
                <div class="section-num">৪</div>
                <div class="section-text">
                    <h6 class="section-title">অনুমোদন চেইন</h6>
                    <span class="section-sub">যারা এই সংশোধনে সিদ্ধান্ত নিবেন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-route"></i></span>
            </div>

            <?php
            // Determine "current" signatory in chain (first row with isApproved=0)
            $currentRowID = 0;
            foreach ($chain as $cr) { if ((int)$cr['isApproved'] === 0 && (int)$cr['signatory'] === $currentEmployeeID) { $currentRowID = (int)$cr['dataID']; break; } }
            if ($currentRowID === 0) {
                foreach ($chain as $cr) { if ((int)$cr['isApproved'] === 0) { $currentRowID = (int)$cr['dataID']; break; } }
            }
            foreach ($chain as $cr):
                $cls = '';
                if ((int)$cr['isApproved'] === 1) $cls = 'is-done';
                elseif ((int)$cr['isApproved'] === 2) $cls = 'is-rejected';
                elseif ((int)$cr['dataID'] === $currentRowID) $cls = 'is-current';
                $statusText = '';
                if ((int)$cr['isApproved'] === 1) $statusText = '✓ অনুমোদিত' . (!empty($cr['approvedDate']) ? ' — ' . htmlspecialchars($cr['approvedDate']) : '');
                elseif ((int)$cr['isApproved'] === 2) $statusText = '✗ প্রত্যাখ্যাত';
                elseif ((int)$cr['dataID'] === $currentRowID) $statusText = 'অপেক্ষমান';
                else $statusText = 'পরবর্তী';
            ?>
            <div class="chain-line <?= $cls ?>">
                <span class="chain-serial"><?= be_num($cr['serial']) ?></span>
                <div>
                    <div class="chain-name"><?= htmlspecialchars($cr['employee_name'] ?? '—') ?></div>
                    <div class="chain-sub"><?= htmlspecialchars($cr['job_title_name'] ?? '') ?></div>
                </div>
                <span class="chain-status"><?= $statusText ?></span>
            </div>
            <?php if (!empty($cr['note'])): ?>
                <div style="margin: -2px 0 6px 38px; padding: 6px 10px; background: #f9f9fb; border-left: 2px solid #ddd5f6; font-size: 0.78rem; color:#5d6580; border-radius: 0 0.4rem 0.4rem 0;">
                    <i class="ti tabler-message me-1"></i><?= nl2br(htmlspecialchars($cr['note'])) ?>
                </div>
            <?php endif; ?>
            <?php endforeach; ?>

            <?php if (!empty($returnHistory)): ?>
            <div class="section-hdr" data-color="amber" style="margin-top: 18px;">
                <div class="section-num"><i class="ti tabler-arrow-back-up"></i></div>
                <div class="section-text">
                    <h6 class="section-title">ফেরত ইতিহাস</h6>
                </div>
            </div>
            <?php foreach ($returnHistory as $rh): ?>
                <div class="return-history-item">
                    <strong><?= htmlspecialchars($rh['returnedByName'] ?? '—') ?></strong>
                    <small class="text-muted"><?= htmlspecialchars($rh['returnedByTitle'] ?? '') ?></small>
                    — ফেরত পাঠিয়েছেন <strong><?= htmlspecialchars($rh['returnedToName'] ?? '—') ?></strong> এর কাছে
                    <div class="mt-1"><?= nl2br(htmlspecialchars($rh['note'])) ?></div>
                    <small class="text-muted"><i class="ti tabler-clock me-1"></i><?= htmlspecialchars($rh['createdAt']) ?></small>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!$canAct): ?>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="ti tabler-info-circle me-2"></i>
                    <?php
                    if ((int)$led['status'] === 1)      echo 'এই সংশোধন ইতিমধ্যে অনুমোদিত হয়েছে।';
                    elseif ((int)$led['status'] === 2)  echo 'এই সংশোধন প্রত্যাখ্যাত হয়েছে।';
                    elseif ((int)$led['status'] === 3)  echo 'এই সংশোধন প্রস্তাবকের কাছে ফেরত পাঠানো হয়েছে।';
                    elseif (!$myRow)                    echo 'আপনি এই সংশোধনের অনুমোদন চেইনে নেই।';
                    else                                echo 'এই মুহূর্তে আপনার পালা নয় — পূর্বের সাইনেটরির অনুমোদনের অপেক্ষায়।';
                    ?>
                </div>
            <?php else: ?>
            <div class="action-bar">
                <button type="button" class="btn btn-warning" onclick="returnAction()">
                    <i class="ti tabler-arrow-back-up me-1"></i>প্রস্তাবকের কাছে ফেরত
                </button>
                <button type="button" class="btn btn-danger" onclick="rejectAction()">
                    <i class="ti tabler-x me-1"></i>প্রত্যাখ্যান
                </button>
                <button type="button" class="btn btn-success" onclick="approveAction()">
                    <i class="ti tabler-check me-1"></i>অনুমোদন
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
</div>

<?php if ($canAct): ?>
<script type="text/javascript">
(function() {
    function init() {
        if (typeof $ === 'undefined' || typeof flatpickr === 'undefined') { setTimeout(init, 100); return; }

        try { if ($.fn.datepicker) $('.prop-from, .prop-to').datepicker('destroy'); } catch(e) {}

        function beNum(n) {
            return String(n).replace(/[0-9]/g, function(d){ return {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'}[d]; });
        }
        function calcDays($row) {
            var f = $row.find('.prop-from').val(), t = $row.find('.prop-to').val();
            if (!f || !t) { $row.find('.prop-days').val(''); return; }
            var fp = f.split('/'), tp = t.split('/');
            if (fp.length !== 3 || tp.length !== 3) return;
            var d1 = new Date(+fp[2], +fp[1]-1, +fp[0]);
            var d2 = new Date(+tp[2], +tp[1]-1, +tp[0]);
            if (isNaN(d1) || isNaN(d2) || d2 < d1) { $row.find('.prop-days').val(''); return; }
            var days = Math.round((d2 - d1) / 86400000) + 1;
            $row.find('.prop-days').val(days);
        }
        function updateTotal() {
            var t = 0;
            $('.prop-days').each(function() {
                var v = parseInt($(this).val(), 10);
                if (!isNaN(v) && v > 0) t += v;
            });
            $('#propTotal').text(beNum(t) + ' দিন');
        }

        $('.prop-from').each(function() {
            flatpickr(this, { dateFormat: 'd/m/Y', allowInput: false, onChange: function() {
                var $row = $(this.input).closest('.prop-seg');
                var $to = $row.find('.prop-to');
                if ($to[0]._flatpickr) $to[0]._flatpickr.set('minDate', this.selectedDates[0] || null);
                calcDays($row); updateTotal();
            }});
        });
        $('.prop-to').each(function() {
            flatpickr(this, { dateFormat: 'd/m/Y', allowInput: false, onChange: function() {
                calcDays($(this.input).closest('.prop-seg')); updateTotal();
            }});
        });

        function collectFormData() {
            var data = { editID: <?= $editID ?>, segments: [] };
            $('.prop-seg').each(function() {
                data.segments.push({
                    id:        $(this).find('input[name="prop_id[]"]').val(),
                    leaveType: $(this).find('.prop-lt').val(),
                    dateFrom:  $(this).find('.prop-from').val(),
                    dateTo:    $(this).find('.prop-to').val(),
                    days:      $(this).find('.prop-days').val()
                });
            });
            return data;
        }

        function postAction(action, extra) {
            var payload = $.extend({ action: action, segments: JSON.stringify(collectFormData().segments), editID: <?= $editID ?> }, extra || {});
            return $.ajax({
                url: '../../api/leave/edit-approval-action.php',
                type: 'POST',
                data: payload,
                dataType: 'json'
            });
        }

        window.approveAction = function() {
            Swal.fire({
                title: 'অনুমোদন নিশ্চিত করুন?',
                text: 'এটি অনুমোদন চেইনের পরবর্তী ধাপে যাবে অথবা সংশোধন চূড়ান্ত করবে।',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'হ্যাঁ, অনুমোদন',
                cancelButtonText: 'বাতিল',
                confirmButtonColor: '#1a7e44',
                cancelButtonColor: '#8592a3',
                customClass: {confirmButton:'btn btn-success me-2', cancelButton:'btn btn-label-secondary'},
                buttonsStyling: false
            }).then(function(r) {
                if (!r.isConfirmed) return;
                postAction('approve').done(function(resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({title:'সম্পন্ন', text: resp.message || 'অনুমোদিত', icon:'success',
                            confirmButtonColor:'#1a7e44', customClass:{confirmButton:'btn btn-success'}, buttonsStyling:false})
                            .then(function() { window.location.href = 'edit-approval.php?menuslug=leave-edit-approval'; });
                    } else {
                        Swal.fire({title:'ত্রুটি', text:(resp&&resp.message)||'ব্যর্থ', icon:'error',
                            confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                    }
                }).fail(function() {
                    Swal.fire({title:'সার্ভার ত্রুটি', icon:'error', confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                });
            });
        };

        window.rejectAction = function() {
            Swal.fire({
                title: 'প্রত্যাখ্যান করুন',
                input: 'textarea',
                inputLabel: 'প্রত্যাখ্যানের কারণ',
                inputPlaceholder: 'কেন প্রত্যাখ্যান করছেন বিস্তারিত লিখুন...',
                inputAttributes: { rows: 4 },
                showCancelButton: true,
                confirmButtonText: 'প্রত্যাখ্যান',
                cancelButtonText: 'বাতিল',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#8592a3',
                customClass: {confirmButton:'btn btn-danger me-2', cancelButton:'btn btn-label-secondary'},
                buttonsStyling: false,
                inputValidator: function(v) { if (!v || !v.trim()) return 'কারণ আবশ্যক'; }
            }).then(function(r) {
                if (!r.isConfirmed) return;
                postAction('reject', { reason: r.value }).done(function(resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({title:'সম্পন্ন', text: resp.message || 'প্রত্যাখ্যাত', icon:'success',
                            confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false})
                            .then(function() { window.location.href = 'edit-approval.php?menuslug=leave-edit-approval'; });
                    } else {
                        Swal.fire({title:'ত্রুটি', text:(resp&&resp.message)||'ব্যর্থ', icon:'error',
                            confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                    }
                }).fail(function() {
                    Swal.fire({title:'সার্ভার ত্রুটি', icon:'error', confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                });
            });
        };

        window.returnAction = function() {
            Swal.fire({
                title: 'প্রস্তাবকের কাছে ফেরত',
                input: 'textarea',
                inputLabel: 'ফেরত পাঠানোর কারণ',
                inputPlaceholder: 'কেন ফেরত পাঠাচ্ছেন বিস্তারিত লিখুন...',
                inputAttributes: { rows: 4 },
                showCancelButton: true,
                confirmButtonText: 'ফেরত পাঠান',
                cancelButtonText: 'বাতিল',
                confirmButtonColor: '#b8651a',
                cancelButtonColor: '#8592a3',
                customClass: {confirmButton:'btn btn-warning me-2', cancelButton:'btn btn-label-secondary'},
                buttonsStyling: false,
                inputValidator: function(v) { if (!v || !v.trim()) return 'কারণ আবশ্যক'; }
            }).then(function(r) {
                if (!r.isConfirmed) return;
                postAction('return', { reason: r.value }).done(function(resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({title:'সম্পন্ন', text: resp.message || 'ফেরত পাঠানো হয়েছে', icon:'success',
                            confirmButtonColor:'#b8651a', customClass:{confirmButton:'btn btn-warning'}, buttonsStyling:false})
                            .then(function() { window.location.href = 'edit-approval.php?menuslug=leave-edit-approval'; });
                    } else {
                        Swal.fire({title:'ত্রুটি', text:(resp&&resp.message)||'ব্যর্থ', icon:'error',
                            confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                    }
                }).fail(function() {
                    Swal.fire({title:'সার্ভার ত্রুটি', icon:'error', confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                });
            });
        };
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
<?php endif; ?>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
