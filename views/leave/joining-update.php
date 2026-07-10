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

$leaveApplicationID = (int)($_GET['leaveApplicationID'] ?? 0);
if ($leaveApplicationID <= 0) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>অবৈধ আবেদন আইডি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Load joining application
$ljaStmt = mysqli_prepare($con, "SELECT * FROM leave_joining_application WHERE leaveApplicationID = ? ORDER BY dataID DESC LIMIT 1");
mysqli_stmt_bind_param($ljaStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($ljaStmt);
$lja = mysqli_fetch_assoc(mysqli_stmt_get_result($ljaStmt));
mysqli_stmt_close($ljaStmt);

if (!$lja) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>যোগদান পত্র খুঁজে পাওয়া যায়নি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

if ((int)$lja['status'] !== 0) {
    echo '<div class="alert alert-warning m-4"><i class="ti tabler-alert-triangle me-2"></i>এই যোগদান পত্র ইতিমধ্যে নিষ্পত্তি হয়েছে</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$joiningID   = (int)$lja['dataID'];
$joiningType = (int)$lja['joiningType'];
$extLeaveType = (int)$lja['approvedLeaveType'];

if ($joiningType === 1) {
    echo '<div class="alert alert-info m-4"><i class="ti tabler-info-circle me-2"></i>সঠিক সময়ে যোগদান (Type 1) এর জন্য অ্যাডমিন রিভিউ প্রয়োজন নেই — এটি সরাসরি সাইনেটরির কাছে পাঠানো হয়েছে</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Verify supervisor has approved
$supStmt = mysqli_prepare($con,
    "SELECT isApproved FROM leave_joining_data_for_approval
     WHERE leaveApplicationID = ? AND isSupervisor = 1 LIMIT 1");
mysqli_stmt_bind_param($supStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($supStmt);
$supRow = mysqli_fetch_assoc(mysqli_stmt_get_result($supStmt));
mysqli_stmt_close($supStmt);
if (!$supRow || (int)$supRow['isApproved'] !== 1) {
    echo '<div class="alert alert-warning m-4"><i class="ti tabler-alert-triangle me-2"></i>সুপারভাইজার এখনো সুপারিশ করেননি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Check if already forwarded
$fwdCheckStmt = mysqli_prepare($con,
    "SELECT COUNT(*) c FROM leave_joining_data_for_approval
     WHERE leaveApplicationID = ? AND isSupervisor = 0 AND isSentbyAdmin = 1");
mysqli_stmt_bind_param($fwdCheckStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($fwdCheckStmt);
$fwdCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($fwdCheckStmt))['c'] ?? 0);
mysqli_stmt_close($fwdCheckStmt);
$alreadyForwarded = ($fwdCount > 0);

// Org gate
$leaveAppStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($leaveAppStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($leaveAppStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($leaveAppStmt));
mysqli_stmt_close($leaveAppStmt);

$appOrgID = (int)($leaveApp['organization_id'] ?? 0);
$actorEmpID = (int)($getUserInfoQRW['employee_id'] ?? 0);
$actorOrgID = 0;
if ($actorEmpID > 0) {
    $r = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = $actorEmpID LIMIT 1");
    $actorOrgID = (int)(mysqli_fetch_assoc($r)['organization_id'] ?? 0);
}
$isSuperAdmin = ((int)($getUserInfoQRW['user_group_id'] ?? 0) === 1);
if (!$isSuperAdmin && $actorOrgID !== $appOrgID) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-lock me-2"></i>আপনার এই কেন্দ্রের যোগদান পত্র forward করার অনুমতি নেই</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Applicant
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

// Original proposed segments
$origSegs = [];
$segQ = mysqli_query($con,
    "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
     LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
     WHERE s.applicationID = $leaveApplicationID AND s.kind = 'proposed'
     ORDER BY s.serial ASC, s.dataID ASC");
if ($segQ && mysqli_num_rows($segQ) > 0) {
    while ($r = mysqli_fetch_assoc($segQ)) $origSegs[] = $r;
} else {
    $ltQ = mysqli_query($con, "SELECT leaveTitle FROM leave_types WHERE leaveID = " . (int)$leaveApp['approvedLeaveType']);
    $ltTitle = $ltQ ? (mysqli_fetch_assoc($ltQ)['leaveTitle'] ?? '') : '';
    $origSegs[] = [
        'leaveType'  => (int)$leaveApp['approvedLeaveType'],
        'leaveTitle' => $ltTitle,
        'dateFrom'   => $leaveApp['approvedDateFrom'],
        'dateTo'     => $leaveApp['approvedDateTo'],
        'days'       => (int)$leaveApp['approvedDays'],
    ];
}

$approvedDateFrom = $leaveApp['approvedDateFrom'] ?: $leaveApp['dateFrom'];
$approvedDateTo   = $leaveApp['approvedDateTo']   ?: $leaveApp['dateTo'];

// Leave types
$leaveTypes = [];
$ltQ2 = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types WHERE leaveID != 22 ORDER BY leaveTitle ASC");
while ($r = mysqli_fetch_assoc($ltQ2)) $leaveTypes[] = $r;
$leaveTypeMap = array_column($leaveTypes, 'leaveTitle', 'leaveID');

// Templates
$tplQ = mysqli_query($con, "SELECT templateData FROM leave_templates WHERE templateType = 2 ORDER BY templateData ASC");
$templates = [];
while ($r = mysqli_fetch_assoc($tplQ)) $templates[] = $r['templateData'];

function be_num($n) {
    return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}

$typeMeta = [
    2 => ['title' => 'অগ্রিম যোগদান',     'icon' => 'tabler-calendar-minus', 'color' => 'amber',  'sub' => 'ছুটি পূর্ণ ভোগ না করেই কর্মস্থলে ফিরেছেন'],
    3 => ['title' => 'বর্ধিত ছুটি + পরে যোগদান', 'icon' => 'tabler-calendar-plus',  'color' => 'indigo', 'sub' => 'অনুমোদিত তারিখের পরে কর্মস্থলে ফিরেছেন'],
];
$meta = $typeMeta[$joiningType];
?>

<style>
.j-wrap { max-width: 1100px; }
.j-card { border-radius: 0.75rem; }
.j-card .card-body { padding: 1.1rem 1.5rem; }

.section-hdr {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; margin: 14px 0 12px;
    background: #fafbfd; border: 1px solid #eef0f5;
    border-left: 3px solid var(--sec-accent, #6c5ce7);
    border-radius: 0.5rem;
}
.section-hdr[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.section-hdr[data-color="amber"]  { --sec-bg: #fff3e1; --sec-accent: #b8651a; }
.section-hdr[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }
.section-hdr[data-color="slate"]  { --sec-bg: #eef2f7; --sec-accent: #4b5563; }
.section-hdr .section-num { width: 26px; height: 26px; border-radius: 0.4rem; background: var(--sec-bg); color: var(--sec-accent); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.82rem; }
.section-hdr .section-title { font-size: 0.92rem; font-weight: 600; margin: 0; color: #2c2e3a; }
.section-hdr .section-sub { font-size: 0.72rem; color: #8a90a6; }
.section-hdr .section-text { flex: 1; min-width: 0; }
.section-hdr .section-icon { width: 32px; height: 32px; border-radius: 0.5rem; background: var(--sec-bg); color: var(--sec-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; }
.section-hdr:first-of-type { margin-top: 0; }

.applicant-card {
    background: linear-gradient(135deg, #f8f7ff 0%, #fefefe 100%);
    border: 1px solid #ddd5f6;
    border-radius: 0.6rem;
    padding: 14px 18px;
    margin-bottom: 14px;
}
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

.applicant-reason {
    background: #fffaf0;
    border-left: 3px solid #d4a056;
    padding: 12px 14px;
    border-radius: 0.5rem;
    font-size: 0.86rem;
    color: #6b4910;
    margin-bottom: 14px;
}

.j-card .form-control, .j-card .form-select {
    padding: 0.35rem 0.65rem !important;
    font-size: 0.88rem !important;
}
.j-card .col-form-label { font-size: 0.85rem; color: #3a3d53; font-weight: 500; }
</style>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0">
            <i class="ti <?= $meta['icon'] ?> me-2 text-primary"></i>যোগদান পত্র সম্পাদনা ও Forward
        </h4>
        <div class="text-muted small mt-1 ms-1">
            <i class="ti tabler-info-circle me-1"></i><?= htmlspecialchars($meta['title']) ?> — সম্পাদনার পর সাইনেটরি চেইনে forward করুন
        </div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage-approved-leaves.php?menuslug=manage-approved-leaves" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>তালিকা
        </a>
    </div>
</div>

<div class="j-wrap">
<div class="card j-card shadow-sm border-0">
    <div class="card-body">

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
                </div>
            </div>
        </div>

        <?php if ($alreadyForwarded): ?>
            <div class="alert alert-info">
                <i class="ti tabler-info-circle me-2"></i>এই যোগদান পত্র ইতিমধ্যে সাইনেটরি চেইনে forwarded হয়েছে। নিচে edit করে পুনরায় সাবমিট করলে updated তথ্য নিয়ে চেইনে যাবে।
            </div>
        <?php endif; ?>

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
                <tr style="background:#fde0a8; font-weight:700; color:#6b4910;">
                    <td colspan="4">মোট</td>
                    <td class="text-end"><?= be_num($origTotal) ?> দিন</td>
                </tr>
            </tbody>
        </table>

        <!-- Applicant's reason -->
        <?php if (!empty($lja['reason'])): ?>
        <div class="applicant-reason">
            <strong><i class="ti tabler-message me-1"></i>আবেদনকারীর মন্তব্য:</strong><br>
            <?= nl2br(htmlspecialchars($lja['reason'])) ?>
        </div>
        <?php endif; ?>

        <form id="joiningUpdateForm">
            <input type="hidden" name="joiningID" value="<?= $joiningID ?>" />
            <input type="hidden" name="leaveApplicationID" value="<?= $leaveApplicationID ?>" />
            <input type="hidden" name="joiningType" value="<?= $joiningType ?>" />

            <!-- Section 2: Admin edits -->
            <div class="section-hdr" data-color="<?= $meta['color'] ?>">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">যোগদানের তথ্য (সম্পাদনা)</h6>
                    <span class="section-sub">প্রয়োজনে আবেদনকারীর তথ্য সংশোধন করুন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-edit"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label">যোগদানের তারিখ <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <input type="text" class="form-control" id="joiningDate" name="joiningDate" placeholder="dd/mm/yyyy" required autocomplete="off" />
                    <small class="text-muted mt-1 d-block">
                        <i class="ti tabler-info-circle me-1"></i>
                        <?php if ($joiningType === 2): ?>
                            অনুমোদিত শেষ তারিখ (<?= be_num(date('d/m/Y', strtotime($approvedDateTo))) ?>) এর আগের কোনো তারিখ
                        <?php else: ?>
                            অনুমোদিত শেষ তারিখ (<?= be_num(date('d/m/Y', strtotime($approvedDateTo))) ?>) এর পরের কোনো তারিখ
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <?php if ($joiningType === 3): ?>
            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="extensionLeaveType">বর্ধিত অংশের ছুটির ধরন <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <select class="form-select" name="extensionLeaveType" id="extensionLeaveType" required>
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
                <label class="col-md-3 col-form-label" for="templateSelector">লিভ টেম্পলেট</label>
                <div class="col-md-9">
                    <select class="form-select" id="templateSelector" onchange="insertTemplate(this.value)">
                        <option value=''>-- টেম্পলেট নির্বাচন করুন --</option>
                        <?php foreach ($templates as $tpl): ?>
                            <option value="<?= htmlspecialchars($tpl) ?>"><?= htmlspecialchars($tpl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="adminNote">অ্যাডমিন মন্তব্য <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <textarea class="form-control" name="adminNote" id="adminNote" rows="3" required><?= htmlspecialchars($lja['adminNote'] ?? 'অনুমোদন করা যেতে পারে ।') ?></textarea>
                </div>
            </div>

            <!-- Section 3: Segment preview -->
            <div class="section-hdr" data-color="indigo">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">চূড়ান্ত হলে অংশগুলো যেভাবে পরিবর্তিত হবে (প্রিভিউ)</h6>
                </div>
                <span class="section-icon"><i class="ti tabler-eye"></i></span>
            </div>

            <table class="seg-table preview-table mb-3" id="previewTable">
                <thead><tr><th>অংশ</th><th>ছুটির ধরন</th><th>শুরু</th><th>শেষ</th><th class="text-end">দিন</th><th>অবস্থা</th></tr></thead>
                <tbody id="previewBody"><tr><td colspan="6" class="text-center text-muted">তারিখ পরিবর্তন করলে preview দেখাবে</td></tr></tbody>
            </table>
            <div id="previewDiff" style="background:#f0edff; border:1px solid #ddd5f6; border-radius:0.5rem; padding:8px 12px; font-size:0.82rem; color:#2c2e3a; margin-top:6px;"></div>

            <div id="formresult"></div>

            <div class="d-flex justify-content-end gap-2 mt-3 pt-3" style="border-top:1px solid #eef0f5;">
                <a href="manage-approved-leaves.php?menuslug=manage-approved-leaves" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল
                </a>
                <button type="submit" id="submitBtn" class="btn btn-primary px-4">
                    <i class="ti tabler-send me-1"></i><?= $alreadyForwarded ? 'আপডেট ও পুনঃ-Forward' : 'Forward করুন' ?>
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<script type="text/javascript">
(function() {
    var joiningType  = <?= $joiningType ?>;
    var approvedFrom = "<?= htmlspecialchars($approvedDateFrom) ?>";
    var approvedTo   = "<?= htmlspecialchars($approvedDateTo) ?>";
    var initialJoiningIso = "<?= htmlspecialchars($lja['requestedJoiningDate'] ?? '') ?>";
    var origSegs = <?= json_encode(array_map(function($s) {
        return ['leaveType' => (int)$s['leaveType'], 'leaveTitle' => $s['leaveTitle'] ?? '', 'dateFrom' => $s['dateFrom'], 'dateTo' => $s['dateTo'], 'days' => (int)$s['days']];
    }, $origSegs), JSON_UNESCAPED_UNICODE) ?>;
    var leaveTypeMap = <?= json_encode($leaveTypeMap, JSON_UNESCAPED_UNICODE) ?>;

    function init() {
        if (typeof $ === 'undefined' || typeof flatpickr === 'undefined') { setTimeout(init, 100); return; }
        try { if ($.fn.datepicker) $('#joiningDate').datepicker('destroy'); } catch(e) {}

        function beNum(n) { return String(n).replace(/[0-9]/g, function(d){ return {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'}[d]; }); }
        function isoToDisplay(iso) { if (!iso) return ''; var p = iso.split('-'); if (p.length !== 3) return iso; return beNum(p[2] + '/' + p[1] + '/' + p[0]); }
        function displayToIso(disp) { if (!disp) return ''; var p = disp.split('/'); if (p.length !== 3) return ''; var pad = function(n){ return n.length === 1 ? '0' + n : n; }; return p[2] + '-' + pad(p[1]) + '-' + pad(p[0]); }
        function daysBetween(a, b) { return Math.round((new Date(b) - new Date(a)) / 86400000) + 1; }
        function addDays(iso, n) {
            var p = iso.split('-');
            var d = new Date(+p[0], +p[1] - 1, +p[2], 0, 0, 0, 0);
            d.setDate(d.getDate() + n);
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        function isoToLocalDate(iso) { if (!iso) return null; var p = iso.split('-'); if (p.length !== 3) return null; return new Date(+p[0], +p[1] - 1, +p[2], 0, 0, 0, 0); }

        // Init flatpickr (convention: joiningDate = last leave day, inclusive)
        var minDate = null, maxDate = null, defaultDate = null;
        if (joiningType === 2) {
            minDate = isoToLocalDate(approvedFrom);
            maxDate = isoToLocalDate(addDays(approvedTo, -1));
            defaultDate = initialJoiningIso ? isoToLocalDate(initialJoiningIso) : maxDate;
        } else if (joiningType === 3) {
            minDate = isoToLocalDate(addDays(approvedTo, 1));
            defaultDate = initialJoiningIso ? isoToLocalDate(initialJoiningIso) : minDate;
        }

        $('#joiningDate').removeAttr('readonly').val('');

        flatpickr('#joiningDate', {
            dateFormat: 'd/m/Y',
            minDate: minDate,
            maxDate: maxDate,
            defaultDate: defaultDate,
            allowInput: false,
            clickOpens: true,
            disableMobile: true,
            onChange: function(_, dateStr) { renderPreview(dateStr); },
            onReady: function() { renderPreview($('#joiningDate').val()); }
        });

        $('#extensionLeaveType').on('change', function() { renderPreview($('#joiningDate').val()); });

        function renderPreview(joiningDateDisp) {
            var $body = $('#previewBody'), $diff = $('#previewDiff');
            var joinIso = displayToIso(joiningDateDisp);
            if (!joinIso) { $body.html('<tr><td colspan="6" class="text-center text-muted">—</td></tr>'); $diff.empty(); return; }

            var rows = [], origTotal = 0, newTotal = 0;
            origSegs.forEach(function(s) { origTotal += s.days; });

            if (joiningType === 2) {
                // Inclusive truncation at joinIso
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
                if (r.status === 'kept')      { badge = '<span class="badge bg-label-success">অপরিবর্তিত</span>'; }
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
            html += '<tr style="background:#1a7e44; color:#fff; font-weight:700;"><td colspan="4">নতুন মোট</td><td class="text-end">' + beNum(newTotal) + ' দিন</td><td></td></tr>';
            $body.html(html);

            var delta = newTotal - origTotal;
            var deltaText = '';
            if (delta < 0)      deltaText = '<i class="ti tabler-arrow-down-right text-success me-1"></i>' + beNum(Math.abs(delta)) + ' দিন কম ভোগ — ব্যালেন্সে ফেরত যাবে';
            else if (delta > 0) deltaText = '<i class="ti tabler-arrow-up-right text-warning me-1"></i>' + beNum(delta) + ' দিন বেশি — বর্ধিত অংশ থেকে কাটবে';
            else                deltaText = '<i class="ti tabler-equal me-1"></i>মোট দিনে পরিবর্তন নেই';
            $diff.html('<strong>' + beNum(origTotal) + ' দিন</strong> → <strong>' + beNum(newTotal) + ' দিন</strong> &nbsp;&nbsp;' + deltaText);
        }

        $('#joiningUpdateForm').off('submit').on('submit', function(e) {
            e.preventDefault();
            if (!$('#joiningDate').val()) {
                Swal.fire({title:'ত্রুটি', text:'যোগদানের তারিখ নির্বাচন করুন', icon:'error',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                return;
            }
            if (joiningType === 3 && !$('#extensionLeaveType').val()) {
                Swal.fire({title:'ত্রুটি', text:'বর্ধিত অংশের ছুটির ধরন নির্বাচন করুন', icon:'error',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                return;
            }

            Swal.fire({
                title: 'নিশ্চিত?',
                text: 'এই যোগদান পত্র সাইনেটরি চেইনে forward করা হবে।',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'হ্যাঁ, Forward করুন',
                cancelButtonText: 'বাতিল',
                confirmButtonColor: '#6c5ce7', cancelButtonColor: '#8592a3',
                customClass: {confirmButton:'btn btn-primary me-2', cancelButton:'btn btn-label-secondary'},
                buttonsStyling: false
            }).then(function(r) {
                if (!r.isConfirmed) return;
                var $btn = $('#submitBtn');
                $btn.prop('disabled', true).html('<i class="ti tabler-loader me-1"></i>প্রক্রিয়াকরণ...');

                var fd = new FormData(this);
                fd.set('joiningDate', displayToIso(fd.get('joiningDate')));

                $.ajax({
                    url: '../../api/leave/joining-update-action.php',
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(resp) {
                        if (resp && resp.status === 1) {
                            Swal.fire({title:'সম্পন্ন', text: resp.message || 'Forwarded', icon:'success',
                                confirmButtonColor:'#1a7e44', customClass:{confirmButton:'btn btn-success'}, buttonsStyling:false})
                                .then(function() { window.location.href = 'manage-approved-leaves.php?menuslug=manage-approved-leaves'; });
                        } else {
                            Swal.fire({title:'ত্রুটি', text:(resp && resp.message)||'ব্যর্থ', icon:'error',
                                confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                            $btn.prop('disabled', false).html('<i class="ti tabler-send me-1"></i>Forward করুন');
                        }
                    },
                    error: function() {
                        Swal.fire({title:'সার্ভার ত্রুটি', icon:'error', confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                        $btn.prop('disabled', false).html('<i class="ti tabler-send me-1"></i>Forward করুন');
                    }
                });
            }.bind(this));
        });
    }

    window.insertTemplate = function(str) { $('#adminNote').val(str); };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
