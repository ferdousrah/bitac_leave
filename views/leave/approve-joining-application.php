<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Re-query user
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

$joiningID = (int)($_GET['joiningID'] ?? 0);
if ($joiningID <= 0) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>অবৈধ আইডি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Load joining application
$ljaStmt = mysqli_prepare($con, "SELECT * FROM leave_joining_application WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($ljaStmt, 'i', $joiningID);
mysqli_stmt_execute($ljaStmt);
$lja = mysqli_fetch_assoc(mysqli_stmt_get_result($ljaStmt));
mysqli_stmt_close($ljaStmt);

if (!$lja) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>যোগদান পত্র খুঁজে পাওয়া যায়নি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$leaveApplicationID = (int)$lja['leaveApplicationID'];
$joiningType        = (int)$lja['joiningType'];
$joiningDateIso     = $lja['requestedJoiningDate'];
$extLeaveType       = (int)$lja['approvedLeaveType']; // For type 3, this is the extension's leaveType

// Load leave application
$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($appStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);

// Verify actor is in chain for this joining (current signatory)
$myRowStmt = mysqli_prepare($con,
    "SELECT * FROM leave_joining_data_for_approval
     WHERE leaveApplicationID = ? AND signatory = ? AND isApproved = 0 LIMIT 1");
mysqli_stmt_bind_param($myRowStmt, 'ii', $leaveApplicationID, $currentEmployeeID);
mysqli_stmt_execute($myRowStmt);
$myRow = mysqli_fetch_assoc(mysqli_stmt_get_result($myRowStmt));
mysqli_stmt_close($myRowStmt);

$canAct = false;
$isSupervisor = false;
if ($myRow && (int)$lja['status'] === 0) {
    $isSupervisor = ((int)$myRow['isSupervisor'] === 1);
    // For supervisor row → always actionable (it's serial=1)
    // For chain row → must be isSentbyAdmin=1 AND no earlier serial pending
    if ($isSupervisor) {
        $canAct = true;
    } elseif ((int)$myRow['isSentbyAdmin'] === 1) {
        $checkStmt = mysqli_prepare($con,
            "SELECT COUNT(*) c FROM leave_joining_data_for_approval
             WHERE leaveApplicationID = ? AND serial < ? AND isApproved = 0");
        mysqli_stmt_bind_param($checkStmt, 'ii', $leaveApplicationID, $myRow['serial']);
        mysqli_stmt_execute($checkStmt);
        $blockers = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt))['c'] ?? 0);
        mysqli_stmt_close($checkStmt);
        $canAct = ($blockers === 0);
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
mysqli_stmt_bind_param($apStmt, 'i', $lja['applicantID']);
mysqli_stmt_execute($apStmt);
$applicant = mysqli_fetch_assoc(mysqli_stmt_get_result($apStmt));
mysqli_stmt_close($apStmt);

// Original approved segments (kind='proposed' or fallback)
function load_original_segs($con, $leaveAppID, $leaveApp) {
    $rows = [];
    $q = mysqli_query($con,
        "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.applicationID = " . (int)$leaveAppID . " AND s.kind = 'proposed'
         ORDER BY s.serial ASC, s.dataID ASC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
    if (empty($rows)) {
        $q2 = mysqli_query($con,
            "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
             LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
             WHERE s.applicationID = " . (int)$leaveAppID . " AND s.kind = 'requested'
             ORDER BY s.serial ASC, s.dataID ASC");
        if ($q2) while ($r = mysqli_fetch_assoc($q2)) $rows[] = $r;
    }
    if (empty($rows) && !empty($leaveApp['approvedDateFrom'])) {
        $ltQ = mysqli_query($con, "SELECT leaveTitle FROM leave_types WHERE leaveID = " . (int)$leaveApp['approvedLeaveType']);
        $ltTitle = $ltQ ? (mysqli_fetch_assoc($ltQ)['leaveTitle'] ?? '') : '';
        $rows[] = [
            'leaveType'  => (int)$leaveApp['approvedLeaveType'],
            'leaveTitle' => $ltTitle,
            'dateFrom'   => $leaveApp['approvedDateFrom'],
            'dateTo'     => $leaveApp['approvedDateTo'],
            'days'       => (int)$leaveApp['approvedDays'],
        ];
    }
    return $rows;
}

$origSegs = load_original_segs($con, $leaveApplicationID, $leaveApp);

// Chain history
$chainStmt = mysqli_prepare($con,
    "SELECT ldfa.*, el.employee_name, jt.job_title_name
     FROM leave_joining_data_for_approval ldfa
     LEFT JOIN employee_list el ON ldfa.signatory = el.id
     LEFT JOIN job_title jt     ON el.designation  = jt.id
     WHERE ldfa.leaveApplicationID = ?
     ORDER BY ldfa.serial ASC");
mysqli_stmt_bind_param($chainStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($chainStmt);
$chain = mysqli_fetch_all(mysqli_stmt_get_result($chainStmt), MYSQLI_ASSOC);
mysqli_stmt_close($chainStmt);

// Leave types (for extension type override in detail page)
$leaveTypes = [];
$ltQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types WHERE leaveID != 22 ORDER BY leaveTitle ASC");
while ($r = mysqli_fetch_assoc($ltQ)) $leaveTypes[] = $r;
$leaveTypeMap = array_column($leaveTypes, 'leaveTitle', 'leaveID');

function be_num($n) {
    return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}

$typeMeta = [
    1 => ['title' => 'সঠিক সময়ে যোগদান',  'color' => 'green',  'icon' => 'tabler-clock'],
    2 => ['title' => 'অগ্রিম যোগদান',     'color' => 'amber',  'icon' => 'tabler-calendar-minus'],
    3 => ['title' => 'বর্ধিত ছুটি ও পরে যোগদান', 'color' => 'indigo', 'icon' => 'tabler-calendar-plus'],
];
$meta = $typeMeta[$joiningType] ?? $typeMeta[1];

$approvedDateFrom = $leaveApp['approvedDateFrom'] ?: $leaveApp['dateFrom'];
$approvedDateTo   = $leaveApp['approvedDateTo']   ?: $leaveApp['dateTo'];
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
.section-hdr .section-num { width: 26px; height: 26px; border-radius: 0.4rem; background: var(--sec-bg); color: var(--sec-accent); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.82rem; }
.section-hdr .section-title { font-size: 0.92rem; font-weight: 600; margin: 0; color: #2c2e3a; }
.section-hdr .section-sub { font-size: 0.72rem; color: #8a90a6; }
.section-hdr .section-text { flex: 1; min-width: 0; }
.section-hdr .section-icon { width: 32px; height: 32px; border-radius: 0.5rem; background: var(--sec-bg); color: var(--sec-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; }

.applicant-card { background: linear-gradient(135deg, #f8f7ff 0%, #fefefe 100%); border: 1px solid #ddd5f6; border-radius: 0.6rem; padding: 14px 18px; margin-bottom: 14px; }
.applicant-card .ap-name { font-weight: 700; font-size: 1rem; color: #2c2e3a; }
.applicant-card .ap-meta { font-size: 0.82rem; color: #5d6580; margin-top: 4px; }
.applicant-card .ap-type-pill { background: #6c5ce7; color: #fff; padding: 4px 10px; border-radius: 0.3rem; font-size: 0.78rem; font-weight: 600; }

.seg-table { width: 100%; border-collapse: collapse; }
.seg-table th, .seg-table td { padding: 8px 10px; border-bottom: 1px solid #eef0f5; font-size: 0.86rem; vertical-align: middle; }
.seg-table th { background: #fafbfd; color: #5d6580; font-weight: 600; text-align: left; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.02em; }
.seg-table td.text-end, .seg-table th.text-end { text-align: right; }
.orig-table th, .orig-table td { background: #fffaf0; }
.orig-table th { background: #fde0a8; color: #8b6f47; }
.preview-table th, .preview-table td { background: #f0faf4; }
.preview-table th { background: #c4ebd4; color: #1a7e44; }
.preview-table .truncated td { background: #fff9e6; }
.preview-table .deleted td { background: #fff1f0; color: #a52a2a; text-decoration: line-through; }
.preview-table .new-seg td { background: #e9e3ff; font-weight: 600; }
/* .preview-table td above paints every cell, hiding anything set on the <tr> —
   so the total row has to be coloured on the td like the status rows. */
.preview-table .seg-total td { background: #1a7e44; color: #fff; font-weight: 700; }
.orig-table .seg-total td { background: #fde0a8; color: #6b4910; font-weight: 700; }

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

.action-bar { border-top: 1px solid #eef0f5; padding-top: 14px; margin-top: 14px; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }

.app-card .form-control, .app-card .form-select { padding: 0.3rem 0.55rem !important; font-size: 0.84rem !important; min-height: 30px !important; height: 30px !important; }
</style>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0">
            <i class="ti <?= $meta['icon'] ?> me-2 text-primary"></i><?= htmlspecialchars($meta['title']) ?>
            <small class="text-muted" style="font-size:0.7em; font-weight: 400;">#<?= be_num($joiningID) ?></small>
        </h4>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="joining-approval.php?menuslug=leave-joining-approval" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>তালিকা
        </a>
    </div>
</div>

<div class="app-wrap">
<div class="card app-card shadow-sm border-0">
    <div class="card-body">

        <!-- Applicant -->
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
                    <span class="ap-type-pill"><i class="ti <?= $meta['icon'] ?> me-1"></i>Type <?= be_num($joiningType) ?></span>
                    <a href="application-details.php?menuslug=leave-joining-approval&leaveApplicationID=<?= $leaveApplicationID ?>" target="_blank" class="btn btn-sm btn-label-secondary ms-1" title="মূল আবেদন">
                        <i class="ti tabler-external-link"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Section 1: Original approved leave -->
        <div class="section-hdr" data-color="amber">
            <div class="section-num">১</div>
            <div class="section-text">
                <h6 class="section-title">অনুমোদিত ছুটি</h6>
                <span class="section-sub"><?= be_num(date('d/m/Y', strtotime($approvedDateFrom))) ?> হইতে <?= be_num(date('d/m/Y', strtotime($approvedDateTo))) ?></span>
            </div>
            <span class="section-icon"><i class="ti tabler-clipboard-check"></i></span>
        </div>

        <table class="seg-table orig-table mb-3">
            <thead><tr><th>অংশ</th><th>ছুটির ধরন</th><th>শুরু</th><th>শেষ</th><th class="text-end">দিন</th></tr></thead>
            <tbody>
            <?php $origTotal = 0; foreach ($origSegs as $i => $sg): $origTotal += (int)$sg['days']; ?>
                <tr>
                    <td><strong><?= be_num($i + 1) ?></strong></td>
                    <td><?= htmlspecialchars($sg['leaveTitle'] ?? '—') ?></td>
                    <td><?= be_num(date('d/m/Y', strtotime($sg['dateFrom']))) ?></td>
                    <td><?= be_num(date('d/m/Y', strtotime($sg['dateTo']))) ?></td>
                    <td class="text-end"><strong><?= be_num($sg['days']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
                <tr class="seg-total">
                    <td colspan="4">মোট</td>
                    <td class="text-end"><?= be_num($origTotal) ?> দিন</td>
                </tr>
            </tbody>
        </table>

        <form id="approveForm">
            <input type="hidden" name="joiningID" value="<?= $joiningID ?>" />

            <!-- Section 2: Joining info (editable for signatory) -->
            <div class="section-hdr" data-color="<?= $meta['color'] ?>">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">যোগদানের তথ্য</h6>
                    <span class="section-sub">আপনি অনুমোদনের আগে এডিট করতে পারেন</span>
                </div>
                <span class="section-icon"><i class="ti <?= $meta['icon'] ?>"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label">যোগদানের তারিখ <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <input type="text" class="form-control" id="joiningDate" name="joiningDate"
                           value="<?= htmlspecialchars(date('d/m/Y', strtotime($joiningDateIso))) ?>"
                           <?= ($canAct && $joiningType !== 1) ? '' : 'readonly' ?> autocomplete="off" />
                </div>
            </div>

            <?php if ($joiningType === 3): ?>
            <div class="row mb-3">
                <label class="col-md-3 col-form-label">বর্ধিত অংশের ছুটির ধরন <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <select class="form-select" name="extensionLeaveType" id="extensionLeaveType" <?= $canAct ? '' : 'disabled' ?>>
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= (int)$lt['leaveID'] ?>" <?= ((int)$lt['leaveID'] === $extLeaveType) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lt['leaveTitle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label">প্রাথমিক মন্তব্য</label>
                <div class="col-md-9">
                    <div class="alert alert-light mb-0" style="background:#fafbfd; border:1px solid #eef0f5;">
                        <?= $lja['reason'] ? nl2br(htmlspecialchars($lja['reason'])) : '<span class="text-muted">—</span>' ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($lja['attachment'])): ?>
            <div class="row mb-3">
                <label class="col-md-3 col-form-label">সংযুক্তি</label>
                <div class="col-md-9">
                    <a href="../../uploads/<?= htmlspecialchars($lja['attachment']) ?>" target="_blank" class="btn btn-sm btn-label-warning">
                        <i class="ti tabler-paperclip me-1"></i>সংযুক্তি দেখুন
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section 3: Preview of segment changes -->
            <div class="section-hdr" data-color="indigo">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">চূড়ান্ত করলে অংশগুলো যেভাবে পরিবর্তিত হবে</h6>
                    <span class="section-sub">অনুমোদনের সাথে সাথে প্রস্তাবিত ছুটি এই অনুযায়ী আপডেট হবে</span>
                </div>
                <span class="section-icon"><i class="ti tabler-eye"></i></span>
            </div>

            <table class="seg-table preview-table mb-3" id="previewTable">
                <thead><tr><th>অংশ</th><th>ছুটির ধরন</th><th>শুরু</th><th>শেষ</th><th class="text-end">দিন</th><th>অবস্থা</th></tr></thead>
                <tbody id="previewBody"></tbody>
            </table>
            <div id="previewDiff" style="background:#f0edff; border:1px solid #ddd5f6; border-radius:0.5rem; padding:8px 12px; font-size:0.82rem; color:#2c2e3a; margin-top:6px;"></div>

            <!-- Section 4: Chain history -->
            <div class="section-hdr" data-color="slate">
                <div class="section-num">৪</div>
                <div class="section-text">
                    <h6 class="section-title">অনুমোদন চেইন</h6>
                </div>
                <span class="section-icon"><i class="ti tabler-route"></i></span>
            </div>

            <?php
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
                $roleTag = ((int)$cr['isSupervisor'] === 1) ? ' <small class="text-muted">(সুপারভাইজার)</small>' : '';
            ?>
            <div class="chain-line <?= $cls ?>">
                <span class="chain-serial"><?= be_num($cr['serial']) ?></span>
                <div>
                    <div class="chain-name"><?= htmlspecialchars($cr['employee_name'] ?? '—') ?><?= $roleTag ?></div>
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

            <?php if (!$canAct): ?>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="ti tabler-info-circle me-2"></i>
                    <?php
                    if ((int)$lja['status'] === 1)      echo 'এই যোগদান পত্র ইতিমধ্যে অনুমোদিত হয়েছে।';
                    elseif ((int)$lja['status'] === 2)  echo 'এই যোগদান পত্র প্রত্যাখ্যাত হয়েছে।';
                    elseif (!$myRow)                    echo 'আপনি এই যোগদান পত্রের অনুমোদন চেইনে নেই।';
                    elseif ((int)$myRow['isSupervisor'] === 0 && (int)$myRow['isSentbyAdmin'] === 0)
                                                        echo 'সুপারভাইজারের সুপারিশ ও কেন্দ্র অ্যাডমিন কর্তৃক forwarded হওয়ার অপেক্ষায়।';
                    else                                echo 'এই মুহূর্তে আপনার পালা নয় — পূর্বের স্বাক্ষরকারীর অনুমোদনের অপেক্ষায়।';
                    ?>
                </div>
            <?php else: ?>
            <div class="action-bar">
                <?php if (!$isSupervisor): ?>
                <button type="button" class="btn btn-warning" onclick="returnAction()">
                    <i class="ti tabler-arrow-back-up me-1"></i>আবেদনকারীর কাছে ফেরত
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-danger" onclick="rejectAction()">
                    <i class="ti tabler-x me-1"></i>প্রত্যাখ্যান
                </button>
                <button type="button" class="btn btn-success" onclick="approveAction()">
                    <i class="ti tabler-check me-1"></i><?= $isSupervisor ? 'সুপারিশ করুন' : 'অনুমোদন' ?>
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
</div>

<script type="text/javascript">
(function() {
    var joiningType   = <?= $joiningType ?>;
    var approvedFrom  = "<?= htmlspecialchars($approvedDateFrom) ?>";
    var approvedTo    = "<?= htmlspecialchars($approvedDateTo) ?>";
    var origSegs      = <?= json_encode(array_map(function($s) {
        return ['leaveType' => (int)$s['leaveType'], 'leaveTitle' => $s['leaveTitle'] ?? '', 'dateFrom' => $s['dateFrom'], 'dateTo' => $s['dateTo'], 'days' => (int)$s['days']];
    }, $origSegs), JSON_UNESCAPED_UNICODE) ?>;
    var leaveTypeMap  = <?= json_encode($leaveTypeMap, JSON_UNESCAPED_UNICODE) ?>;
    var canAct        = <?= $canAct ? 'true' : 'false' ?>;

    function init() {
        if (typeof $ === 'undefined' || typeof flatpickr === 'undefined') { setTimeout(init, 100); return; }

        try { if ($.fn.datepicker) $('#joiningDate').datepicker('destroy'); } catch(e) {}

        function beNum(n) { return String(n).replace(/[0-9]/g, function(d){ return {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'}[d]; }); }
        function isoToDisplay(iso) { if (!iso) return ''; var p = iso.split('-'); if (p.length !== 3) return iso; return beNum(p[2] + '/' + p[1] + '/' + p[0]); }
        function displayToIso(disp) { if (!disp) return ''; var p = disp.split('/'); if (p.length !== 3) return ''; var pad = function(n){ return n.length === 1 ? '0' + n : n; }; return p[2] + '-' + pad(p[1]) + '-' + pad(p[0]); }
        function daysBetween(a, b) { return Math.round((new Date(b) - new Date(a)) / 86400000) + 1; }
        function addDays(iso, n) { var d = new Date(iso); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); }

        if (canAct && joiningType !== 1) {
            // Convention: joiningDate = last leave day (inclusive).
            var minDate = null, maxDate = null;
            if (joiningType === 2) {
                // Type 2 (অগ্রিম): minDate = leave start, maxDate = approved end - 1 (must be "early")
                minDate = approvedFrom;
                maxDate = addDays(approvedTo, -1);
            } else {
                // Type 3 (বর্ধিত): joining > approved end → extension
                minDate = addDays(approvedTo, 1);
            }
            flatpickr('#joiningDate', {
                dateFormat: 'd/m/Y',
                minDate: minDate,
                maxDate: maxDate,
                allowInput: false,
                onChange: renderPreview
            });
            $('#extensionLeaveType').on('change', renderPreview);
        }

        function renderPreview() {
            var $body = $('#previewBody'), $diff = $('#previewDiff');
            var joinDisp = $('#joiningDate').val();
            var joinIso = displayToIso(joinDisp);
            if (!joinIso) { $body.html('<tr><td colspan="6" class="text-center text-muted">—</td></tr>'); $diff.empty(); return; }

            var rows = [], origTotal = 0, newTotal = 0;
            origSegs.forEach(function(s) { origTotal += s.days; });

            if (joiningType === 1) {
                // No change
                origSegs.forEach(function(s, idx) {
                    rows.push({ idx: idx+1, type: s.leaveTitle, from: s.dateFrom, to: s.dateTo, days: s.days, status: 'kept' });
                    newTotal += s.days;
                });
            } else if (joiningType === 2) {
                // Convention: joiningDate = last leave day (inclusive). Truncate at joiningDate.
                var truncTo = joinIso;
                origSegs.forEach(function(s, idx) {
                    if (s.dateTo <= truncTo) {
                        rows.push({ idx: idx+1, type: s.leaveTitle, from: s.dateFrom, to: s.dateTo, days: s.days, status: 'kept' });
                        newTotal += s.days;
                    } else if (s.dateFrom <= truncTo) {
                        var nd = daysBetween(s.dateFrom, truncTo);
                        rows.push({ idx: idx+1, type: s.leaveTitle, from: s.dateFrom, to: truncTo, days: nd, status: 'truncated' });
                        newTotal += nd;
                    } else {
                        rows.push({ idx: idx+1, type: s.leaveTitle, from: s.dateFrom, to: s.dateTo, days: s.days, status: 'deleted' });
                    }
                });
            } else if (joiningType === 3) {
                origSegs.forEach(function(s, idx) {
                    rows.push({ idx: idx+1, type: s.leaveTitle, from: s.dateFrom, to: s.dateTo, days: s.days, status: 'kept' });
                    newTotal += s.days;
                });
                var extLT = $('#extensionLeaveType').val() || '';
                var extTitle = extLT ? (leaveTypeMap[extLT] || ('Type ' + extLT)) : '<em style="color:#a52a2a;">— ছুটির ধরন নির্বাচন করুন —</em>';
                var extFrom = addDays(approvedTo, 1);
                var extTo = joinIso;
                var extDays = daysBetween(extFrom, extTo);
                if (extDays > 0) {
                    rows.push({ idx: origSegs.length+1, type: extTitle, from: extFrom, to: extTo, days: extDays, status: 'new' });
                    newTotal += extDays;
                }
            }

            var html = '';
            rows.forEach(function(r) {
                var cls = '', badge = '';
                if (r.status === 'kept')      { cls = '';          badge = '<span class="badge bg-label-success">অপরিবর্তিত</span>'; }
                else if (r.status === 'truncated') { cls = 'truncated'; badge = '<span class="badge bg-label-warning">সংক্ষিপ্ত</span>'; }
                else if (r.status === 'deleted')   { cls = 'deleted';   badge = '<span class="badge bg-label-danger">বাদ</span>'; }
                else if (r.status === 'new')       { cls = 'new-seg';   badge = '<span class="badge bg-label-primary">নতুন</span>'; }
                html += '<tr class="' + cls + '">'
                    + '<td><strong>' + beNum(r.idx) + '</strong></td>'
                    + '<td>' + r.type + '</td>'
                    + '<td>' + beNum(isoToDisplay(r.from)) + '</td>'
                    + '<td>' + beNum(isoToDisplay(r.to)) + '</td>'
                    + '<td class="text-end"><strong>' + beNum(r.days) + '</strong></td>'
                    + '<td>' + badge + '</td>'
                    + '</tr>';
            });
            html += '<tr class="seg-total"><td colspan="4">নতুন মোট</td><td class="text-end">' + beNum(newTotal) + ' দিন</td><td></td></tr>';
            $body.html(html);

            var delta = newTotal - origTotal;
            var deltaText = '';
            if (delta < 0) deltaText = '<i class="ti tabler-arrow-down-right text-success me-1"></i>' + beNum(Math.abs(delta)) + ' দিন কম — ব্যালেন্সে ফেরত যাবে';
            else if (delta > 0) deltaText = '<i class="ti tabler-arrow-up-right text-warning me-1"></i>' + beNum(delta) + ' দিন বেশি — বর্ধিত অংশ থেকে কাটবে';
            else deltaText = '<i class="ti tabler-equal me-1"></i>মোট দিনে কোনো পরিবর্তন নেই';
            $diff.html('<strong>' + beNum(origTotal) + ' দিন</strong> → <strong>' + beNum(newTotal) + ' দিন</strong> &nbsp;&nbsp;' + deltaText);
        }

        renderPreview();

        function collectPayload(action, reason) {
            var p = {
                action: action,
                joiningID: <?= $joiningID ?>,
                joiningDate: displayToIso($('#joiningDate').val() || '')
            };
            if (joiningType === 3) p.extensionLeaveType = $('#extensionLeaveType').val() || '';
            if (reason !== undefined) p.reason = reason;
            return p;
        }

        function postAction(action, reason) {
            return $.ajax({
                url: '../../api/leave/joining-approval-action.php',
                type: 'POST',
                data: collectPayload(action, reason),
                dataType: 'json'
            });
        }

        window.approveAction = function() {
            <?php if ($joiningType === 3): ?>
            if (!$('#extensionLeaveType').val()) {
                Swal.fire({title:'ত্রুটি', text:'বর্ধিত অংশের ছুটির ধরন নির্বাচন করুন', icon:'error',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                return;
            }
            <?php endif; ?>
            Swal.fire({
                title: <?= $isSupervisor ? "'সুপারিশ নিশ্চিত করুন?'" : "'অনুমোদন নিশ্চিত করুন?'" ?>,
                text: 'এই সিদ্ধান্ত চেইনের পরবর্তী ধাপে যাবে।',
                icon: 'question', showCancelButton: true,
                confirmButtonText: <?= $isSupervisor ? "'হ্যাঁ, সুপারিশ'" : "'হ্যাঁ, অনুমোদন'" ?>,
                cancelButtonText: 'বাতিল',
                confirmButtonColor: '#1a7e44', cancelButtonColor: '#8592a3',
                customClass: {confirmButton:'btn btn-success me-2', cancelButton:'btn btn-label-secondary'},
                buttonsStyling: false
            }).then(function(r) {
                if (!r.isConfirmed) return;
                postAction('approve').done(handleResp('সম্পন্ন', 'success', '#1a7e44', 'btn-success')).fail(serverErr);
            });
        };

        window.rejectAction = function() {
            Swal.fire({
                title: 'প্রত্যাখ্যান করুন',
                input: 'textarea',
                inputLabel: 'প্রত্যাখ্যানের কারণ',
                inputAttributes: { rows: 4 },
                showCancelButton: true,
                confirmButtonText: 'প্রত্যাখ্যান',
                cancelButtonText: 'বাতিল',
                confirmButtonColor: '#dc3545', cancelButtonColor: '#8592a3',
                customClass: {confirmButton:'btn btn-danger me-2', cancelButton:'btn btn-label-secondary'},
                buttonsStyling: false,
                inputValidator: function(v) { if (!v || !v.trim()) return 'কারণ আবশ্যক'; }
            }).then(function(r) {
                if (!r.isConfirmed) return;
                postAction('reject', r.value).done(handleResp('সম্পন্ন', 'success', '#dc3545', 'btn-danger')).fail(serverErr);
            });
        };

        window.returnAction = function() {
            Swal.fire({
                title: 'আবেদনকারীর কাছে ফেরত',
                input: 'textarea',
                inputLabel: 'ফেরত পাঠানোর কারণ',
                inputAttributes: { rows: 4 },
                showCancelButton: true,
                confirmButtonText: 'ফেরত পাঠান',
                cancelButtonText: 'বাতিল',
                confirmButtonColor: '#b8651a', cancelButtonColor: '#8592a3',
                customClass: {confirmButton:'btn btn-warning me-2', cancelButton:'btn btn-label-secondary'},
                buttonsStyling: false,
                inputValidator: function(v) { if (!v || !v.trim()) return 'কারণ আবশ্যক'; }
            }).then(function(r) {
                if (!r.isConfirmed) return;
                postAction('return', r.value).done(handleResp('সম্পন্ন', 'success', '#b8651a', 'btn-warning')).fail(serverErr);
            });
        };

        function handleResp(_title, _icon, _color, _cls) {
            return function(resp) {
                if (resp && resp.status === 1) {
                    Swal.fire({title:'সম্পন্ন', text: resp.message || 'সফল', icon:'success',
                        confirmButtonColor: _color, customClass:{confirmButton:'btn ' + _cls}, buttonsStyling:false})
                        .then(function() { window.location.href = 'joining-approval.php?menuslug=leave-joining-approval'; });
                } else {
                    Swal.fire({title:'ত্রুটি', text:(resp&&resp.message)||'ব্যর্থ', icon:'error',
                        confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                }
            };
        }
        function serverErr() {
            Swal.fire({title:'সার্ভার ত্রুটি', icon:'error', confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
