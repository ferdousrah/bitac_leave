<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(__DIR__ . '/../../includes/signatory_route_helper.php');

$leaveApplicationID = (int)($_GET['leaveApplicationID'] ?? 0);
$joiningType        = (int)($_GET['type'] ?? 1);

if (!in_array($joiningType, [1, 2, 3], true)) $joiningType = 1;

if ($leaveApplicationID <= 0) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>অবৈধ আবেদন আইডি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Load leave application
$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($appStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);

if (!$leaveApp) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>আবেদন খুঁজে পাওয়া যায়নি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

if ((int)$leaveApp['status'] !== 1) {
    echo '<div class="alert alert-warning m-4"><i class="ti tabler-alert-triangle me-2"></i>শুধু অনুমোদিত ছুটির জন্য যোগদান পত্র জমা দেওয়া যাবে</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Block if pending joining exists already
$existStmt = mysqli_prepare($con, "SELECT dataID, status, joiningType FROM leave_joining_application WHERE leaveApplicationID = ? ORDER BY dataID DESC LIMIT 1");
mysqli_stmt_bind_param($existStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($existStmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existStmt));
mysqli_stmt_close($existStmt);
if ($existing && (int)$existing['status'] === 0) {
    echo '<div class="alert alert-info m-4"><i class="ti tabler-info-circle me-2"></i>এই ছুটির জন্য একটি যোগদান পত্র ইতিমধ্যে অনুমোদনের অপেক্ষায় রয়েছে (#' . (int)$existing['dataID'] . ')</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}
if ($existing && (int)$existing['status'] === 1) {
    echo '<div class="alert alert-success m-4"><i class="ti tabler-check me-2"></i>এই ছুটির জন্য যোগদান পত্র ইতিমধ্যে অনুমোদিত (#' . (int)$existing['dataID'] . ')</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Applicant
$applicantID = (int)$leaveApp['applicantID'];
$empStmt = mysqli_prepare($con,
    "SELECT el.*, jt.job_title_name, s.section_name, o.organization_name
     FROM employee_list el
     LEFT JOIN job_title jt    ON el.designation     = jt.id
     LEFT JOIN sections s      ON el.section_id      = s.id
     LEFT JOIN organization o  ON el.organization_id = o.id
     WHERE el.id = ? LIMIT 1");
mysqli_stmt_bind_param($empStmt, 'i', $applicantID);
mysqli_stmt_execute($empStmt);
$applicant = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));
mysqli_stmt_close($empStmt);

// Original approved segments
$origSegs = [];
$segQ = mysqli_query($con,
    "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
     LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
     WHERE s.applicationID = $leaveApplicationID AND s.kind = 'proposed'
     ORDER BY s.serial ASC, s.dataID ASC");
if ($segQ && mysqli_num_rows($segQ) > 0) {
    while ($r = mysqli_fetch_assoc($segQ)) $origSegs[] = $r;
} else {
    // Fallback to legacy single-row data
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

// Compute approved range
$approvedDateFrom = $leaveApp['approvedDateFrom'] ?: $leaveApp['dateFrom'];
$approvedDateTo   = $leaveApp['approvedDateTo']   ?: $leaveApp['dateTo'];
$approvedDays     = (int)$leaveApp['approvedDays'];
if ($approvedDays === 0 && $approvedDateFrom && $approvedDateTo) {
    $approvedDays = (int)((strtotime($approvedDateTo) - strtotime($approvedDateFrom)) / 86400) + 1;
}

// Pre-fill supervisor from the leave's chain, re-resolved to whoever holds that
// seat now — reading the stored id alone would offer someone who has since moved
// on and can no longer act.
$supervisorID = joiningSupervisorDefault($con, $leaveApplicationID, $applicantID);

// Employees for supervisor dropdown — same center
$empListQ = mysqli_prepare($con,
    "SELECT el.id, el.employee_id, el.employee_name, jt.job_title_name
     FROM employee_list el
     LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE el.employment_status = 1 AND el.organization_id = ?
     ORDER BY el.display_order ASC, el.employee_name ASC");
$_org = (int)($applicant['organization_id'] ?? 0);
mysqli_stmt_bind_param($empListQ, 'i', $_org);
mysqli_stmt_execute($empListQ);
$empList = mysqli_stmt_get_result($empListQ);
$empListRows = [];
while ($e = mysqli_fetch_assoc($empList)) $empListRows[] = $e;
mysqli_stmt_close($empListQ);

// Leave types (for Type 3 extension selector)
$leaveTypes = [];
$ltQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types WHERE leaveID != 22 ORDER BY leaveTitle ASC");
while ($r = mysqli_fetch_assoc($ltQ)) $leaveTypes[] = $r;

function be_num($n) {
    return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}

$typeMeta = [
    1 => ['title' => 'সঠিক সময়ে যোগদান',  'icon' => 'tabler-clock',          'color' => 'green',  'sub' => 'অনুমোদিত শেষ তারিখেই কর্মস্থলে ফিরছেন'],
    2 => ['title' => 'অগ্রিম যোগদান',     'icon' => 'tabler-calendar-minus', 'color' => 'amber',  'sub' => 'ছুটি পূর্ণ ভোগ না করে আগেই কর্মস্থলে ফিরছেন'],
    3 => ['title' => 'বর্ধিত ছুটি ও পরে যোগদান', 'icon' => 'tabler-calendar-plus', 'color' => 'indigo', 'sub' => 'অনুমোদিত তারিখের পরে কর্মস্থলে ফিরছেন (বর্ধিত ছুটি প্রয়োজন)'],
];
$meta = $typeMeta[$joiningType];
?>

<style>
.j-wrap { max-width: 1100px; margin-left: auto; margin-right: auto; }
.j-card { border-radius: 0.75rem; }
.j-card .card-body { padding: 1.1rem 1.5rem; }

.section-hdr {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; margin: 14px 0 12px;
    background: #fcfdfe; border: 1px solid #f1f3f7;
    border-left: 2px solid var(--sec-accent, #d5dcf0);
    border-radius: 0.5rem;
}
.section-hdr[data-color="indigo"] { --sec-bg: #f5f4ff; --sec-accent: #c7c2f0; --sec-icon: #7a70c4; }
.section-hdr[data-color="amber"]  { --sec-bg: #fef9f1; --sec-accent: #f0d9b8; --sec-icon: #b8823a; }
.section-hdr[data-color="green"]  { --sec-bg: #f2fbf6; --sec-accent: #c7e9d5; --sec-icon: #4a9268; }
.section-hdr[data-color="slate"]  { --sec-bg: #f5f7fa; --sec-accent: #d5dce3; --sec-icon: #6b7280; }
.section-hdr .section-num { width: 26px; height: 26px; border-radius: 0.4rem; background: var(--sec-bg); color: var(--sec-icon); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.82rem; }
.section-hdr .section-title { font-size: 0.92rem; font-weight: 600; margin: 0; color: #374151; }
.section-hdr .section-sub { font-size: 0.72rem; color: #9ca3af; }
.section-hdr .section-text { flex: 1; min-width: 0; }
.section-hdr .section-icon { width: 32px; height: 32px; border-radius: 0.5rem; background: var(--sec-bg); color: var(--sec-icon); display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; }
.section-hdr:first-of-type { margin-top: 0; }

/* Page header */
.j-page-hdr {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.j-page-hdr .j-title-block { display: flex; align-items: center; gap: 14px; min-width: 0; }
.j-page-hdr .j-title-icon {
    width: 44px; height: 44px;
    border-radius: 0.55rem;
    background: var(--sec-bg, #f5f4ff);
    color: var(--sec-icon, #7a70c4);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    border: 1px solid var(--sec-border, #f1f3f7);
}
.j-page-hdr[data-color="green"]  { --sec-bg:#f2fbf6; --sec-icon:#4a9268; --sec-border:#e6f4ec; --sec-pill-bg:#eefaf3; --sec-pill-text:#4a9268; }
.j-page-hdr[data-color="amber"]  { --sec-bg:#fef9f1; --sec-icon:#b8823a; --sec-border:#faf1e0; --sec-pill-bg:#fdf3e3; --sec-pill-text:#a16f2c; }
.j-page-hdr[data-color="indigo"] { --sec-bg:#f5f4ff; --sec-icon:#7a70c4; --sec-border:#eae7f8; --sec-pill-bg:#efedfb; --sec-pill-text:#6a5eb4; }
.j-page-hdr .j-title { font-size: 1.15rem; font-weight: 600; margin: 0; color: #374151; letter-spacing: -0.2px; line-height: 1.2; }
.j-page-hdr .j-sub { font-size: 0.8rem; color: #9ca3af; margin-top: 3px; }

/* Applicant chip strip */
.applicant-card {
    background: #fcfdfe;
    border: 1px solid #f1f3f7;
    border-left: 2px solid var(--sec-icon, #d5dcf0);
    border-radius: 0.5rem;
    padding: 12px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.applicant-card[data-color="green"]  { --sec-icon: #4a9268; --sec-pill-bg:#eefaf3; --sec-pill-text:#3a7a58; }
.applicant-card[data-color="amber"]  { --sec-icon: #b8823a; --sec-pill-bg:#fdf3e3; --sec-pill-text:#8f652b; }
.applicant-card[data-color="indigo"] { --sec-icon: #7a70c4; --sec-pill-bg:#efedfb; --sec-pill-text:#5b52a3; }
.applicant-card .ap-name { font-weight: 600; font-size: 0.98rem; color: #374151; letter-spacing: -0.1px; }
.applicant-card .ap-meta { font-size: 0.8rem; color: #9ca3af; margin-top: 3px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.applicant-card .ap-meta .sep { color: #e5e7eb; padding: 0 4px; }
.applicant-card .ap-type-pill {
    background: var(--sec-pill-bg, #f5f4ff);
    color: var(--sec-pill-text, #7a70c4);
    padding: 5px 12px;
    border-radius: 0.35rem;
    font-size: 0.76rem;
    font-weight: 600;
    display: inline-flex; align-items: center; gap: 6px;
    border: 1px solid var(--sec-pill-text, #7a70c4);
    border-color: rgba(0,0,0,0.06);
}

/* Table wrappers — rounded corners via overflow:hidden */
.seg-table-wrap {
    border: 1px solid #eef0f5;
    border-radius: 0.55rem;
    overflow: hidden;
    margin-bottom: 14px;
    background: #fff;
}
.seg-table { width: 100%; border-collapse: collapse; margin: 0; }
.seg-table th, .seg-table td {
    padding: 9px 12px;
    border-bottom: 1px solid #f1f3f7;
    font-size: 0.86rem;
    vertical-align: middle;
    background: #ffffff;
}
.seg-table tbody tr:last-child td { border-bottom: 0; }
.seg-table th {
    background: #f9fafb;
    color: #6b7280;
    font-weight: 600;
    text-align: left;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid #e5e7eb;
}
.seg-table td.text-end, .seg-table th.text-end { text-align: right; }

/* Original approved table — barely-there warm tint */
.orig-table th { background: #fefaf3; color: #b8823a; }
.orig-table td { background: #ffffff; }
.orig-table tbody tr.total-row td {
    background: #fef9f1 !important;
    color: #a16f2c !important;
    font-weight: 600;
    border-top: 1px solid #faf1e0;
}

/* Preview table — barely-there mint tint */
.preview-table th { background: #f4faf6; color: #4a9268; }
.preview-table td { background: #ffffff; }
.preview-table tr.truncated td { background: #fefbf1; color: #a16f2c; }
.preview-table tr.deleted td { background: #fdf5f5; color: #b06060; text-decoration: line-through; opacity: 0.75; }
.preview-table tr.new-seg td { background: #f5f4ff; color: #6a5eb4; font-weight: 500; }
.preview-table tr.total-row td {
    background: #f2fbf6 !important;
    color: #3a7a58 !important;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 10px 12px;
    border-top: 1px solid #e6f4ec;
}

.preview-diff {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 0.4rem;
    padding: 8px 12px;
    font-size: 0.82rem;
    color: #374151;
    margin-top: 6px;
}

.j-card .form-control, .j-card .form-select {
    padding: 0.45rem 0.75rem !important;
    font-size: 0.88rem !important;
    border-radius: 0.4rem !important;
}
.j-card .form-control[readonly] { background-color: #f9fafb; color: #4b5563; }
.j-card .col-form-label { font-size: 0.85rem; color: #374151; font-weight: 500; }
.j-card .card-body { padding: 1.5rem 1.75rem !important; }
.j-card-actions { border-top: 1px solid #eef0f5; padding-top: 1.15rem; margin-top: 1.15rem; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
</style>

<div class="j-wrap">
    <!-- Page header -->
    <div class="j-page-hdr" data-color="<?= htmlspecialchars($meta['color']) ?>">
        <div class="j-title-block">
            <span class="j-title-icon"><i class="ti <?= htmlspecialchars($meta['icon']) ?>"></i></span>
            <div>
                <h4 class="j-title"><?= htmlspecialchars($meta['title']) ?></h4>
                <div class="j-sub"><?= htmlspecialchars($meta['sub']) ?></div>
            </div>
        </div>
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-sm btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>

<div class="card j-card shadow-sm border-0">
    <div class="card-body">

        <!-- Applicant context -->
        <div class="applicant-card" data-color="<?= htmlspecialchars($meta['color']) ?>">
            <div>
                <div class="ap-name"><?= htmlspecialchars($applicant['employee_name'] ?? '') ?></div>
                <div class="ap-meta">
                    <i class="ti tabler-id-badge-2"></i><?= be_num(htmlspecialchars($applicant['employee_id'] ?? '')) ?>
                    <span class="sep">·</span>
                    <i class="ti tabler-briefcase"></i><?= htmlspecialchars($applicant['job_title_name'] ?? '—') ?>
                    <span class="sep">·</span>
                    <i class="ti tabler-building"></i><?= htmlspecialchars($applicant['organization_name'] ?? '—') ?>
                </div>
            </div>
            <span class="ap-type-pill">
                <i class="ti <?= htmlspecialchars($meta['icon']) ?>"></i>
                Type <?= be_num($joiningType) ?>
            </span>
        </div>

        <!-- Section 1: Original approved leave -->
        <div class="section-hdr" data-color="amber">
            <div class="section-num">১</div>
            <div class="section-text">
                <h6 class="section-title">অনুমোদিত ছুটি</h6>
                <span class="section-sub"><?= be_num(date('d/m/Y', strtotime($approvedDateFrom))) ?> হইতে <?= be_num(date('d/m/Y', strtotime($approvedDateTo))) ?> — <?= be_num($approvedDays) ?> দিন</span>
            </div>
            <span class="section-icon"><i class="ti tabler-clipboard-check"></i></span>
        </div>

        <div class="seg-table-wrap">
            <table class="seg-table orig-table">
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
                    <tr class="total-row">
                        <td colspan="4">মোট</td>
                        <td class="text-end"><?= be_num($origTotal) ?> দিন</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form id="joiningForm" enctype="multipart/form-data">
            <input type="hidden" name="leaveApplicationID" value="<?= $leaveApplicationID ?>" />
            <input type="hidden" name="joiningType" value="<?= $joiningType ?>" />

            <!-- Section 2: Joining details -->
            <div class="section-hdr" data-color="<?= $meta['color'] ?>">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">যোগদানের তথ্য</h6>
                    <span class="section-sub">
                        <?php if ($joiningType === 1): ?>আপনি অনুমোদিত শেষ তারিখেই কর্মস্থলে ফিরছেন<?php endif; ?>
                        <?php if ($joiningType === 2): ?>আগে কর্মস্থলে ফেরার তারিখ নির্বাচন করুন (অনুমোদিত শেষ তারিখের আগে)<?php endif; ?>
                        <?php if ($joiningType === 3): ?>পরে কর্মস্থলে ফেরার তারিখ ও বর্ধিত অংশের ছুটির ধরন নির্বাচন করুন<?php endif; ?>
                    </span>
                </div>
                <span class="section-icon"><i class="ti <?= $meta['icon'] ?>"></i></span>
            </div>

            <?php if ($joiningType === 1): ?>
                <div class="row mb-3">
                    <label class="col-md-3 col-form-label">যোগদানের তারিখ</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" name="joiningDateDisplay" value="<?= be_num(date('d/m/Y', strtotime($approvedDateTo))) ?>" readonly />
                        <input type="hidden" name="joiningDate" value="<?= htmlspecialchars($approvedDateTo) ?>" />
                    </div>
                </div>
            <?php else: ?>
                <div class="row mb-3">
                    <label class="col-md-3 col-form-label">যোগদানের তারিখ <span class="text-danger">*</span></label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="joiningDate" name="joiningDate" placeholder="dd/mm/yyyy" required autocomplete="off" readonly />
                        <small class="text-muted mt-1 d-block" id="joiningDateHint">
                            <i class="ti tabler-info-circle me-1"></i>
                            <?php if ($joiningType === 2): ?>
                                অনুমোদিত শেষ তারিখ (<?= be_num(date('d/m/Y', strtotime($approvedDateTo))) ?>) এর আগের কোনো তারিখ নির্বাচন করুন
                            <?php else: ?>
                                অনুমোদিত শেষ তারিখ (<?= be_num(date('d/m/Y', strtotime($approvedDateTo))) ?>) এর পরের কোনো তারিখ নির্বাচন করুন
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($joiningType === 3): ?>
                <!-- Multi-segment extension: split extended days across multiple leave types -->
                <div class="row mb-3">
                    <label class="col-md-3 col-form-label">
                        বর্ধিত অংশের ছুটি <span class="text-danger">*</span>
                        <div class="ext-total-inline mt-1">
                            <span class="ext-pill ext-target" title="অনুমোদিত শেষ তারিখের পরদিন থেকে যোগদানের তারিখ পর্যন্ত হিসাব করা">
                                <i class="ti tabler-calendar-clock me-1"></i>
                                প্রয়োজন <strong id="extTotalTarget">০</strong> দিন
                            </span>
                            <span class="ext-pill ext-given" title="নিচের সারিগুলোতে দেওয়া দিনের যোগফল">
                                বন্টিত <strong id="extTotalGiven">০</strong> দিন
                            </span>
                            <span id="extBalanceHint" class="ext-pill ext-warn" style="display:none;">
                                <i class="ti tabler-alert-triangle me-1"></i><span id="extBalanceHintText"></span>
                            </span>
                        </div>
                    </label>
                    <div class="col-md-9">
                        <div class="ext-table-wrap">
                            <table class="ext-table" id="extRowsTable">
                                <thead>
                                    <tr>
                                        <th style="width:38%;">ছুটির ধরন</th>
                                        <th style="width:22%;">শুরু</th>
                                        <th style="width:22%;">শেষ</th>
                                        <th class="text-center" style="width:12%;">দিন</th>
                                        <th style="width:50px;" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="extRowsBody">
                                    <!-- seeded by JS -->
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-label-primary mt-2" id="addExtRowBtn">
                            <i class="ti tabler-plus me-1"></i>সারি যোগ
                        </button>
                    </div>
                </div>
                <style>
                .ext-total-inline { display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.35rem; }
                .ext-pill {
                    display:inline-flex; align-items:center; gap:.2rem;
                    padding:.2rem .55rem; border-radius:.35rem;
                    font-size:.75rem; font-weight:500; line-height:1.3;
                }
                .ext-pill strong { font-weight:700; }
                .ext-pill.ext-target { background:#eef2ff; color:#3730a3; }
                .ext-pill.ext-given  { background:#e0e7ff; color:#312e81; }
                .ext-pill.ext-warn   { background:#fef3c7; color:#92400e; }
                .ext-table-wrap { border:1px solid #e5e7eb; border-radius:.5rem; overflow:hidden; }
                .ext-table { width:100%; border-collapse:collapse; margin:0; }
                .ext-table thead th {
                    background:#f8fafc; padding:.5rem .6rem;
                    font-size:.78rem; font-weight:600; color:#475569;
                    border-bottom:1px solid #e5e7eb;
                }
                .ext-table tbody td {
                    padding:.5rem .6rem;
                    border-top:1px solid #f1f5f9;
                    vertical-align:middle;
                }
                .ext-table .form-control, .ext-table .form-select {
                    font-size:.9rem; padding:.35rem .55rem;
                }
                .ext-table .ext-from, .ext-table .ext-to {
                    background:#fafbff !important; color:#475569;
                    font-weight:500; text-align:center;
                }
                .ext-table .ext-days { font-weight:600; }
                </style>
            <?php endif; ?>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="reason">
                    মন্তব্য / বিলম্বের কারণ
                    <?php if ($joiningType === 3): ?><span class="text-danger">*</span><?php endif; ?>
                </label>
                <div class="col-md-9">
                    <textarea class="form-control" name="reason" id="reason" rows="3" placeholder="" <?= $joiningType === 3 ? 'required' : '' ?>></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="attachment">সংযুক্তি (ঐচ্ছিক)</label>
                <div class="col-md-9">
                    <input type="file" name="attachment" id="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf" />
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>JPEG, JPG, PNG বা PDF — সর্বোচ্চ ২ MB</small>
                </div>
            </div>

            <?php if ($joiningType !== 1): ?>
                <!-- Section 3: Preview of segment changes -->
                <div class="section-hdr" data-color="indigo">
                    <div class="section-num">৩</div>
                    <div class="section-text">
                        <h6 class="section-title">প্রস্তাবিত সংশোধন (প্রিভিউ)</h6>
                        <span class="section-sub">
                            <?php if ($joiningType === 2): ?>অগ্রিম ফেরার ফলে অনুমোদিত ছুটি যেভাবে পরিবর্তিত হবে<?php endif; ?>
                            <?php if ($joiningType === 3): ?>বর্ধিত অংশসহ চূড়ান্ত ছুটির অংশসমূহ<?php endif; ?>
                        </span>
                    </div>
                    <span class="section-icon"><i class="ti tabler-eye"></i></span>
                </div>

                <div class="seg-table-wrap">
                    <table class="seg-table preview-table" id="previewTable">
                        <thead><tr><th>অংশ</th><th>ছুটির ধরন</th><th>শুরু</th><th>শেষ</th><th class="text-end">দিন</th><th>অবস্থা</th></tr></thead>
                        <tbody id="previewBody">
                            <tr><td colspan="6" class="text-center text-muted">যোগদানের তারিখ নির্বাচন করলে প্রিভিউ দেখাবে</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="preview-diff" id="previewDiff" style="display:none;"></div>
            <?php endif; ?>

            <!-- Section 4: Supervisor -->
            <div class="section-hdr" data-color="slate">
                <div class="section-num"><?= $joiningType === 1 ? '৩' : '৪' ?></div>
                <div class="section-text">
                    <h6 class="section-title">সুপারভাইজার</h6>
                </div>
                <span class="section-icon"><i class="ti tabler-user-check"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="supervisorID">সুপারভাইজার <span class="text-danger">*</span></label>
                <div class="col-md-9">
                    <select class="form-select select2" name="supervisorID" id="supervisorID" required>
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php foreach ($empListRows as $e): ?>
                            <option value="<?= (int)$e['id'] ?>" <?= ((int)$e['id'] === $supervisorID) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['employee_name']) ?><?= !empty($e['job_title_name']) ? ', ' . htmlspecialchars($e['job_title_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="formresult"></div>

            <div class="d-flex justify-content-end gap-2 mt-3 pt-3" style="border-top:1px solid #eef0f5;">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল
                </button>
                <button type="submit" id="submitBtn" class="btn btn-primary px-4">
                    <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য প্রেরণ করুন
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<script type="text/javascript">
(function() {
    var joiningType    = <?= $joiningType ?>;
    var approvedFromIso = "<?= htmlspecialchars($approvedDateFrom) ?>";
    var approvedToIso   = "<?= htmlspecialchars($approvedDateTo) ?>";
    var origSegs = <?= json_encode(array_map(function($s) {
        return [
            'leaveType'  => (int)$s['leaveType'],
            'leaveTitle' => $s['leaveTitle'] ?? '',
            'dateFrom'   => $s['dateFrom'],
            'dateTo'     => $s['dateTo'],
            'days'       => (int)$s['days'],
        ];
    }, $origSegs), JSON_UNESCAPED_UNICODE) ?>;
    var leaveTypeMap = <?= json_encode(array_column($leaveTypes, 'leaveTitle', 'leaveID'), JSON_UNESCAPED_UNICODE) ?>;

    function init() {
        if (typeof $ === 'undefined' || typeof flatpickr === 'undefined') { setTimeout(init, 100); return; }

        // Detach any auto-bound jQuery UI datepicker (which doesn't respect our min/max)
        try { if ($.fn.datepicker) $('#joiningDate').datepicker('destroy'); } catch(e) {}

        if ($.fn.select2) {
            $('#supervisorID').select2({ width: '100%', allowClear: true, placeholder: '-- নির্বাচন করুন --' });
        }

        // Build the leave-type <option> markup once for reuse in extension rows.
        var LEAVE_TYPES_JSON = <?= json_encode(array_values(array_map(function($lt) {
            return ['id' => (int)$lt['leaveID'], 'title' => $lt['leaveTitle']];
        }, $leaveTypes)), JSON_UNESCAPED_UNICODE) ?>;
        function extTypeOptionsHtml(selectedId) {
            var s = '<option value="">-- ধরন --</option>';
            LEAVE_TYPES_JSON.forEach(function(lt) {
                s += '<option value="' + lt.id + '"' + (String(selectedId) === String(lt.id) ? ' selected' : '') + '>'
                   + $('<div>').text(lt.title).html() + '</option>';
            });
            return s;
        }

        // Extension multi-segment helpers — only relevant for Type 3.
        function newExtRow(fromIso, toIso, days, leaveTypeId) {
            var fromDisp = isoToDisplay(fromIso);
            var toDisp   = isoToDisplay(toIso);
            return '<tr>'
                + '<td><select class="form-select form-select-sm ext-type" required>' + extTypeOptionsHtml(leaveTypeId || '') + '</select></td>'
                + '<td><input type="text" class="form-control form-control-sm ext-from" value="' + fromDisp + '" data-iso="' + fromIso + '" readonly></td>'
                + '<td><input type="text" class="form-control form-control-sm ext-to" value="' + toDisp + '" data-iso="' + toIso + '" readonly></td>'
                + '<td class="text-center"><input type="number" class="form-control form-control-sm text-center ext-days" min="1" value="' + days + '" required></td>'
                + '<td class="text-center"><button type="button" class="btn btn-sm btn-icon btn-label-danger removeExtRow" title="মুছুন"><i class="ti tabler-trash"></i></button></td>'
                + '</tr>';
        }

        // Recompute per-row dates from days (rows are contiguous starting after approvedDateTo)
        function recalcExtSegments() {
            var totalTarget = extensionTotalDays();
            var cursor = addDays(approvedToIso, 1); // extension starts day after approved end
            var given = 0;
            $('#extRowsBody tr').each(function() {
                var $tr = $(this);
                var days = Math.max(1, parseInt($tr.find('.ext-days').val() || 0, 10));
                $tr.find('.ext-days').val(days);
                var toIso = addDays(cursor, days - 1);
                $tr.find('.ext-from').val(isoToDisplay(cursor)).attr('data-iso', cursor);
                $tr.find('.ext-to').val(isoToDisplay(toIso)).attr('data-iso', toIso);
                cursor = addDays(toIso, 1);
                given += days;
            });
            $('#extTotalTarget').text(beNum(totalTarget));
            $('#extTotalGiven').text(beNum(given));
            var $hint = $('#extBalanceHint'), $hintTxt = $('#extBalanceHintText');
            if (totalTarget <= 0) {
                $hint.hide();
            } else if (given === totalTarget) {
                $hint.hide();
            } else if (given < totalTarget) {
                $hint.show(); $hintTxt.text('আরও ' + beNum(totalTarget - given) + ' দিন যোগ করুন');
            } else {
                $hint.show(); $hintTxt.text(beNum(given - totalTarget) + ' দিন অতিরিক্ত — দিন কমান বা যোগদানের তারিখ পেছান');
            }
            // Refresh preview (uses rows' contents)
            var d = $('#joiningDate').val();
            if (d) renderPreview(d);
        }

        function extensionTotalDays() {
            var joinDisp = $('#joiningDate').val();
            if (!joinDisp) return 0;
            var joinIso = displayToIso(joinDisp);
            if (!joinIso) return 0;
            var fromIso = addDays(approvedToIso, 1);
            return daysBetween(fromIso, joinIso);
        }

        function seedExtRows() {
            var target = extensionTotalDays();
            var $body = $('#extRowsBody');
            $body.empty();
            if (target <= 0) return;
            var fromIso = addDays(approvedToIso, 1);
            var toIso   = displayToIso($('#joiningDate').val());
            $body.append(newExtRow(fromIso, toIso, target, ''));
            recalcExtSegments();
        }

        // Type-3 row event delegation
        $(document).off('click.extAdd', '#addExtRowBtn').on('click.extAdd', '#addExtRowBtn', function() {
            var target = extensionTotalDays();
            if (target <= 0) {
                Swal.fire({title:'তথ্য', text:'আগে যোগদানের তারিখ নির্বাচন করুন', icon:'info',
                    confirmButtonColor:'#0dcaf0', customClass:{confirmButton:'btn btn-info'}, buttonsStyling:false});
                return;
            }
            var $rows = $('#extRowsBody tr');
            // Split the last row: give the new row 1 day, subtract from the last
            var $last = $rows.last();
            var lastDays = Math.max(1, parseInt($last.find('.ext-days').val() || 0, 10));
            if (lastDays < 2) {
                Swal.fire({title:'সতর্কতা', text:'শেষ সারিতে কমপক্ষে ২ দিন থাকতে হবে নতুন সারি যোগ করতে', icon:'warning',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-warning'}, buttonsStyling:false});
                return;
            }
            $last.find('.ext-days').val(lastDays - 1);
            $('#extRowsBody').append(newExtRow('', '', 1, ''));
            recalcExtSegments();
        });

        $(document).off('click.extRm', '.removeExtRow').on('click.extRm', '.removeExtRow', function() {
            if ($('#extRowsBody tr').length <= 1) {
                Swal.fire({title:'সতর্কতা', text:'কমপক্ষে একটি সারি থাকতে হবে', icon:'warning',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-warning'}, buttonsStyling:false});
                return;
            }
            var removedDays = parseInt($(this).closest('tr').find('.ext-days').val() || 0, 10);
            $(this).closest('tr').remove();
            // Push the removed days onto the (new) last row so the total stays balanced
            var $last = $('#extRowsBody tr').last();
            var lastDays = parseInt($last.find('.ext-days').val() || 0, 10);
            $last.find('.ext-days').val(lastDays + removedDays);
            recalcExtSegments();
        });

        $(document).off('input.extDays change.extDays', '.ext-days').on('input.extDays change.extDays', '.ext-days', recalcExtSegments);
        $(document).off('change.extType', '.ext-type').on('change.extType', '.ext-type', function() {
            var d = $('#joiningDate').val();
            if (d) renderPreview(d);
        });

        function beNum(n) {
            return String(n).replace(/[0-9]/g, function(d){ return {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'}[d]; });
        }
        function isoToDisplay(iso) {
            if (!iso) return '';
            var p = iso.split('-');
            if (p.length !== 3) return iso;
            return beNum(p[2] + '/' + p[1] + '/' + p[0]);
        }
        function displayToIso(disp) {
            if (!disp) return '';
            var p = disp.split('/');
            if (p.length !== 3) return '';
            var pad = function(n) { return n.length === 1 ? '0' + n : n; };
            return p[2] + '-' + pad(p[1]) + '-' + pad(p[0]);
        }
        function daysBetween(d1Iso, d2Iso) {
            var t1 = new Date(d1Iso).getTime(), t2 = new Date(d2Iso).getTime();
            return Math.round((t2 - t1) / 86400000) + 1;
        }
        function addDays(iso, n) {
            var d = new Date(iso);
            d.setDate(d.getDate() + n);
            return d.toISOString().slice(0, 10);
        }

        // Helper: ISO 'YYYY-MM-DD' → JS Date at local midnight (avoids UTC interpretation bugs)
        function isoToLocalDate(iso) {
            if (!iso) return null;
            var p = iso.split('-');
            if (p.length !== 3) return null;
            return new Date(+p[0], +p[1] - 1, +p[2], 0, 0, 0, 0);
        }

        // Init flatpickr for date input (types 2 & 3 only)
        // Convention: যোগদানের তারিখ = শেষ ছুটির দিন (inclusive). কর্মে যোগদান হবে পরের দিন।
        if (joiningType !== 1) {
            var minDate = null, maxDate = null, defaultDate = null;
            if (joiningType === 2) {
                // Type 2 (অগ্রিম): joiningDate must be within original range but earlier than approvedDateTo
                //   minDate = approvedDateFrom (consume at least 1 day of leave)
                //   maxDate = approvedDateTo - 1 (must end leave before original end → "early")
                minDate = isoToLocalDate(approvedFromIso);
                maxDate = isoToLocalDate(addDays(approvedToIso, -1));
                defaultDate = maxDate;
            } else if (joiningType === 3) {
                // Type 3 (বর্ধিত): joiningDate must be after approvedDateTo (extension)
                minDate = isoToLocalDate(addDays(approvedToIso, 1));
                defaultDate = minDate;
            }

            // Remove readonly so flatpickr controls the input itself
            // Clear any stale value (from previous buggy binding) so flatpickr opens at defaultDate
            $('#joiningDate').removeAttr('readonly').val('');

            var fpInstance = flatpickr('#joiningDate', {
                dateFormat: 'd/m/Y',
                minDate: minDate,
                maxDate: maxDate,
                defaultDate: defaultDate,
                allowInput: false,
                clickOpens: true,
                disableMobile: true,
                onChange: function(selectedDates, dateStr) { renderPreview(dateStr); },
                onReady: function() { renderPreview($('#joiningDate').val()); }
            });
            // Debug
            try {
                console.log('[joining-form] flatpickr bound', {
                    joiningType: joiningType,
                    minDate: minDate ? minDate.toString() : null,
                    maxDate: maxDate ? maxDate.toString() : null,
                    defaultDate: defaultDate ? defaultDate.toString() : null,
                    approvedFromIso: approvedFromIso,
                    approvedToIso: approvedToIso,
                    todayLocal: new Date().toString()
                });
            } catch(e) {}

            if (joiningType === 3) {
                // Re-seed extension rows when joining date changes
                var _fpOnChange = fpInstance.config.onChange;
                fpInstance.config.onChange = [function(selectedDates, dateStr) {
                    seedExtRows();
                    renderPreview(dateStr);
                }];
                // Also seed on initial load if joiningDate already set (defaultDate)
                setTimeout(seedExtRows, 0);
            }
        }

        function renderPreview(joiningDateDisp) {
            var $body = $('#previewBody'), $diff = $('#previewDiff');
            if (!joiningDateDisp) { $diff.hide(); return; }
            var joinIso = displayToIso(joiningDateDisp);
            if (!joinIso) { $diff.hide(); return; }

            var rows = [];
            var origTotal = 0;
            origSegs.forEach(function(s) { origTotal += s.days; });
            var newTotal = 0;

            if (joiningType === 2) {
                // Convention: joiningDate = last leave day (inclusive). Truncate segments at joiningDate.
                var truncTo = joinIso;
                origSegs.forEach(function(s, idx) {
                    if (s.dateTo <= truncTo) {
                        // Whole segment kept
                        rows.push({ idx: idx + 1, type: s.leaveTitle, from: s.dateFrom, to: s.dateTo, days: s.days, status: 'kept' });
                        newTotal += s.days;
                    } else if (s.dateFrom <= truncTo) {
                        // Truncated at joiningDate (inclusive)
                        var newDays = daysBetween(s.dateFrom, truncTo);
                        rows.push({ idx: idx + 1, type: s.leaveTitle, from: s.dateFrom, to: truncTo, days: newDays, status: 'truncated' });
                        newTotal += newDays;
                    } else {
                        // Whole segment deleted
                        rows.push({ idx: idx + 1, type: s.leaveTitle, from: s.dateFrom, to: s.dateTo, days: s.days, status: 'deleted' });
                    }
                });
            } else if (joiningType === 3) {
                // All original kept + one or more new extension segments (from the multi-row table).
                origSegs.forEach(function(s, idx) {
                    rows.push({ idx: idx + 1, type: s.leaveTitle, from: s.dateFrom, to: s.dateTo, days: s.days, status: 'kept' });
                    newTotal += s.days;
                });
                var extIdx = origSegs.length;
                $('#extRowsBody tr').each(function() {
                    extIdx++;
                    var $tr = $(this);
                    var lt = $tr.find('.ext-type').val() || '';
                    var title = lt ? (leaveTypeMap[lt] || ('Type ' + lt)) : '<em style="color:#a52a2a;">— ছুটির ধরন নির্বাচন করুন —</em>';
                    var fromIso = $tr.find('.ext-from').attr('data-iso') || '';
                    var toIso   = $tr.find('.ext-to').attr('data-iso') || '';
                    var days    = parseInt($tr.find('.ext-days').val() || 0, 10);
                    if (days > 0 && fromIso && toIso) {
                        rows.push({ idx: extIdx, type: title, from: fromIso, to: toIso, days: days, status: 'new' });
                        newTotal += days;
                    }
                });
            }

            // Render rows
            var html = '';
            rows.forEach(function(r) {
                var cls = '';
                var badge = '';
                if (r.status === 'kept')      { cls = '';            badge = '<span class="badge bg-label-success">অপরিবর্তিত</span>'; }
                else if (r.status === 'truncated') { cls = 'truncated';   badge = '<span class="badge bg-label-warning">সংক্ষিপ্ত</span>'; }
                else if (r.status === 'deleted')   { cls = 'deleted';     badge = '<span class="badge bg-label-danger">বাদ</span>'; }
                else if (r.status === 'new')       { cls = 'new-seg';     badge = '<span class="badge bg-label-primary">নতুন</span>'; }

                html += '<tr class="' + cls + '">'
                    + '<td><strong>' + beNum(r.idx) + '</strong></td>'
                    + '<td>' + r.type + '</td>'
                    + '<td>' + (r.status === 'new' ? beNum(isoToDisplay(r.from)) : beNum(isoToDisplay(r.from))) + '</td>'
                    + '<td>' + beNum(isoToDisplay(r.to)) + '</td>'
                    + '<td class="text-end"><strong>' + beNum(r.days) + '</strong></td>'
                    + '<td>' + badge + '</td>'
                    + '</tr>';
            });
            html += '<tr class="total-row">'
                  + '<td colspan="4">নতুন মোট</td>'
                  + '<td class="text-end">' + beNum(newTotal) + ' দিন</td>'
                  + '<td></td>'
                  + '</tr>';
            $body.html(html);

            var delta = newTotal - origTotal;
            var deltaText = '';
            if (delta < 0) deltaText = '<i class="ti tabler-arrow-down-right text-success me-1"></i>' + beNum(Math.abs(delta)) + ' দিন কম ভোগ — ব্যালেন্সে ফেরত যাবে';
            else if (delta > 0) deltaText = '<i class="ti tabler-arrow-up-right text-warning me-1"></i>' + beNum(delta) + ' দিন বেশি — বর্ধিত অংশ থেকে কাটবে';
            else deltaText = '<i class="ti tabler-equal me-1"></i>মোট দিনে কোনো পরিবর্তন নেই';
            $diff.html('<strong>' + beNum(origTotal) + ' দিন</strong> → <strong>' + beNum(newTotal) + ' দিন</strong> &nbsp;&nbsp;' + deltaText).show();
        }

        // Form submit
        $('#joiningForm').off('submit').on('submit', function(e) {
            e.preventDefault();

            // Client-side validation
            if (joiningType !== 1 && !$('#joiningDate').val()) {
                Swal.fire({title:'ত্রুটি', text:'যোগদানের তারিখ নির্বাচন করুন', icon:'error',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                return;
            }
            if (joiningType === 3) {
                var target = extensionTotalDays();
                var given = 0, hasEmptyType = false, extSegs = [];
                $('#extRowsBody tr').each(function() {
                    var $tr = $(this);
                    var lt = parseInt($tr.find('.ext-type').val() || 0, 10);
                    var days = parseInt($tr.find('.ext-days').val() || 0, 10);
                    var fromIso = $tr.find('.ext-from').attr('data-iso') || '';
                    var toIso = $tr.find('.ext-to').attr('data-iso') || '';
                    if (lt <= 0) hasEmptyType = true;
                    given += days;
                    extSegs.push({ leaveType: lt, dateFrom: fromIso, dateTo: toIso, days: days });
                });
                if (hasEmptyType) {
                    Swal.fire({title:'ত্রুটি', text:'বর্ধিত অংশের প্রতিটি সারিতে ছুটির ধরন নির্বাচন করুন', icon:'error',
                        confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                    return;
                }
                if (given !== target) {
                    // Spell out where `target` comes from — on its own the old
                    // "(2) must equal (1)" said nothing about the joining date
                    // being what decides it.
                    var extFromIso = addDays(approvedToIso, 1);
                    var joinIsoNow = displayToIso($('#joiningDate').val()) || '';
                    var spanTxt = (extFromIso === joinIsoNow)
                        ? 'কেবল <strong>' + beNum(isoToDisplay(joinIsoNow)) + '</strong>'
                        : '<strong>' + beNum(isoToDisplay(extFromIso)) + '</strong> থেকে <strong>'
                          + beNum(isoToDisplay(joinIsoNow)) + '</strong> পর্যন্ত';
                    Swal.fire({
                        title: 'দিনের হিসাব মিলছে না',
                        html: 'যোগদানের তারিখ <strong>' + beNum(isoToDisplay(joinIsoNow)) + '</strong> হলে বর্ধিত অংশ চলবে '
                            + spanTxt + ' — অর্থাৎ <strong>' + beNum(target) + ' দিন</strong>।<br>'
                            + 'সারিগুলোতে দেওয়া আছে <strong>' + beNum(given) + ' দিন</strong>।<br><br>'
                            + '<span class="text-muted">সারির দিন সংখ্যা বদলে মিলিয়ে নিন, অথবা যোগদানের তারিখ পরিবর্তন করুন।</span>',
                        icon: 'error',
                        confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                    return;
                }
                // Stash JSON for injection into FormData below
                window._extSegsPayload = JSON.stringify(extSegs);
            }

            var $btn = $('#submitBtn');
            $btn.prop('disabled', true).html('<i class="ti tabler-loader me-1"></i>প্রক্রিয়াকরণ...');
            var fd = new FormData(this);

            // Convert joiningDate display → iso for API
            if (joiningType !== 1) {
                fd.set('joiningDate', displayToIso(fd.get('joiningDate')));
            }
            // Attach extension segments payload for Type 3
            if (joiningType === 3 && window._extSegsPayload) {
                fd.set('extensionSegments', window._extSegsPayload);
            }

            $.ajax({
                url: '../../api/leave/submit-joining-application.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({title:'সফল', text: resp.message || 'যোগদান পত্র প্রেরিত', icon:'success',
                            confirmButtonColor:'#6c5ce7', customClass:{confirmButton:'btn btn-primary'}, buttonsStyling:false})
                            .then(function() { window.location.href = 'all-applications.php?menuslug=all-leave-application'; });
                    } else {
                        Swal.fire({title:'ত্রুটি', text: (resp && resp.message) || 'প্রেরণ ব্যর্থ', icon:'error',
                            confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                        $btn.prop('disabled', false).html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য প্রেরণ করুন');
                    }
                },
                error: function() {
                    Swal.fire({title:'সার্ভার ত্রুটি', text:'অনুরোধ ব্যর্থ', icon:'error',
                        confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                    $btn.prop('disabled', false).html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য প্রেরণ করুন');
                }
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
