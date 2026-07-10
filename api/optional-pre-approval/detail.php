<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid <= 0) { echo '<div class="alert alert-danger">অবৈধ আইডি</div>'; exit; }

$opaQ = mysqli_query($con, "
    SELECT opa.*, el.employee_name, el.employee_id AS emp_code, el.photo,
           jt.job_title_name, o.organization_name, s.section_name
    FROM optional_leave_pre_approval opa
    INNER JOIN employee_list el ON opa.employee_id = el.id
    LEFT JOIN job_title jt ON el.designation = jt.id
    LEFT JOIN organization o ON opa.organization_id = o.id
    LEFT JOIN sections s ON opa.section_id = s.id
    WHERE opa.id = $pid LIMIT 1");
$opa = mysqli_fetch_assoc($opaQ);
if (!$opa) { echo '<div class="alert alert-danger">তথ্য পাওয়া যায়নি</div>'; exit; }

$sigQ = mysqli_query($con, "
    SELECT s.*, el.employee_name AS sig_name, el.employee_id AS sig_code,
           jt.job_title_name AS sig_title
    FROM optional_leave_pre_approval_signatory s
    LEFT JOIN employee_list el ON s.signatory = el.id
    LEFT JOIN job_title jt ON el.designation = jt.id
    WHERE s.preApprovalID = $pid
    ORDER BY s.serial ASC");
$signatories = mysqli_fetch_all($sigQ, MYSQLI_ASSOC);

// Admin (forward) initiator lookup — for the timeline entry between supervisor and chain
$adminInitName = ''; $adminInitTitle = '';
if (!empty($opa['admin_initiator'])) {
    $adminIQ = mysqli_prepare($con,
        "SELECT el.employee_name, jt.job_title_name
         FROM user_list ul
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE ul.dataID = ? LIMIT 1");
    mysqli_stmt_bind_param($adminIQ, 'i', $opa['admin_initiator']);
    mysqli_stmt_execute($adminIQ);
    $ai = mysqli_fetch_assoc(mysqli_stmt_get_result($adminIQ)) ?: [];
    mysqli_stmt_close($adminIQ);
    $adminInitName  = trim($ai['employee_name']  ?? '');
    $adminInitTitle = trim($ai['job_title_name'] ?? '');
}

$statusText = ['অপেক্ষমান', 'অনুমোদিত', 'প্রত্যাখ্যাত'][(int)$opa['status']] ?? '—';
$statusClass = ['opa-status-pending', 'opa-status-approved', 'opa-status-rejected'][(int)$opa['status']] ?? 'opa-status-pending';

// PDF availability flags
$_hasApplication = true;                              // always available
$_hasForwardNote = !empty($opa['admin_forward_date']); // after admin forward
$_hasOfficeOrder = ((int)$opa['status'] === 1);       // after final chain approval

$_baseUrlOpaPdf = defined('BASE_URL') ? BASE_URL : '../..';

function bn_dt($d) {
    if (!$d || $d === '0000-00-00 00:00:00') return '—';
    $p = date_parse($d);
    if (!$p['year']) return htmlspecialchars($d);
    return banglaNumber(sprintf('%02d', $p['day'])) . '-' . banglaNumber(sprintf('%02d', $p['month'])) . '-' . banglaNumber($p['year']);
}
?>

<!-- PDF actions row (context-aware: application always; forward-note if forwarded; office-order if approved) -->
<div class="opa-pdf-actions mb-3 d-flex gap-2 flex-wrap">
    <a href="<?= htmlspecialchars($_baseUrlOpaPdf) ?>/api/reports/opa-application.php?id=<?= (int)$opa['id'] ?>" target="_blank" class="btn btn-sm btn-primary-clean">
        <i class="ti tabler-file-text me-1"></i>আবেদনপত্র (PDF)
    </a>
    <?php if ($_hasForwardNote): ?>
    <a href="<?= htmlspecialchars($_baseUrlOpaPdf) ?>/api/reports/opa-forward-note.php?id=<?= (int)$opa['id'] ?>" target="_blank" class="btn btn-sm btn-forward-note">
        <i class="ti tabler-file-invoice me-1"></i>সম্পাদনার নোট (PDF)
    </a>
    <?php endif; ?>
    <?php if ($_hasOfficeOrder): ?>
    <a href="<?= htmlspecialchars($_baseUrlOpaPdf) ?>/api/reports/opa-office-order.php?id=<?= (int)$opa['id'] ?>" target="_blank" class="btn btn-sm btn-office-order">
        <i class="ti tabler-file-certificate me-1"></i>অফিস আদেশ (PDF)
    </a>
    <?php endif; ?>
</div>

<style>
.opa-pdf-actions .btn { font-size: 0.8rem; padding: 6px 12px; border-radius: 6px; font-weight: 500; }
.opa-pdf-actions .btn-primary-clean {
    background: #4f46e5; border: 1px solid #4f46e5; color: #fff;
}
.opa-pdf-actions .btn-primary-clean:hover { background: #4338ca; border-color: #4338ca; color: #fff; }
.opa-pdf-actions .btn-forward-note {
    background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
}
.opa-pdf-actions .btn-forward-note:hover { background: #dbeafe; color: #1e3a8a; }
.opa-pdf-actions .btn-office-order {
    background: #dcfce7; border: 1px solid #a7f3d0; color: #14532d;
}
.opa-pdf-actions .btn-office-order:hover { background: #bbf7d0; color: #052e16; }
</style>

<div class="mb-3 pb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="fw-bold fs-6"><?= htmlspecialchars($opa['employee_name']) ?>
                <span class="text-muted fw-normal">(<?= banglaNumber($opa['emp_code']) ?>)</span>
            </div>
            <div class="text-muted small"><?= htmlspecialchars($opa['job_title_name'] ?? '') ?></div>
            <div class="mt-1">
                <span class="badge bg-label-primary"><i class="ti tabler-map-pin me-1"></i><?= htmlspecialchars($opa['organization_name'] ?? '—') ?></span>
                <?php if (!empty($opa['section_name'])): ?>
                    <span class="badge bg-label-info"><i class="ti tabler-building me-1"></i><?= htmlspecialchars($opa['section_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <div class="mb-1"><span class="opa-year-pill">বছর: <?= banglaNumber((int)$opa['year']) ?></span></div>
            <div><span class="<?= $statusClass ?>"><?= $statusText ?></span></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3 small">
    <div class="col-6 col-md-4">
        <div class="text-muted">অনুরোধকৃত দিন</div>
        <div class="fw-semibold fs-6"><?= banglaNumber((float)$opa['requested_days']) ?> দিন</div>
    </div>
    <div class="col-6 col-md-4">
        <div class="text-muted">সাবমিট তারিখ</div>
        <div class="fw-semibold"><?= bn_dt($opa['submit_date']) ?></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="text-muted">চূড়ান্ত সিদ্ধান্ত</div>
        <div class="fw-semibold">
            <?php if ((int)$opa['status'] === 1): ?>
                <?= bn_dt($opa['final_approved_date']) ?>
            <?php elseif ((int)$opa['status'] === 2): ?>
                <?= bn_dt($opa['final_rejected_date']) ?>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($opa['approved_days'])): ?>
    <div class="col-6 col-md-4">
        <div class="text-muted">প্রশাসন কর্তৃক অনুমোদিত দিন</div>
        <div class="fw-semibold fs-6"><?= banglaNumber((float)$opa['approved_days']) ?> দিন</div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($opa['admin_forward_date'])): ?>
<div class="admin-forward-box mb-3">
    <div class="d-flex align-items-start gap-2">
        <span class="admin-forward-icon"><i class="ti tabler-send"></i></span>
        <div class="admin-forward-body">
            <div class="admin-forward-title">কেন্দ্র প্রশাসন কর্তৃক প্রেরিত</div>
            <div class="admin-forward-meta">
                <?php if ($adminInitName): ?>
                    <span class="admin-forward-name"><?= htmlspecialchars($adminInitName) ?></span>
                    <?php if ($adminInitTitle): ?>
                        <span class="admin-forward-role"><?= htmlspecialchars($adminInitTitle) ?></span>
                    <?php endif; ?>
                    <span class="admin-forward-sep">·</span>
                <?php endif; ?>
                <span class="admin-forward-date"><?= bn_dt($opa['admin_forward_date']) ?></span>
            </div>
            <?php if (!empty($opa['admin_note'])): ?>
                <div class="admin-forward-note">
                    <span class="admin-forward-note-label">মন্তব্য:</span>
                    <?= htmlspecialchars($opa['admin_note']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.admin-forward-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 4px solid #2563eb;
    border-radius: 8px;
    padding: 12px 14px;
    color: #1e3a8a;
}
.admin-forward-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 1rem;
    flex-shrink: 0;
}
.admin-forward-body { flex: 1; min-width: 0; }
.admin-forward-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: #1e3a8a;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 3px;
}
.admin-forward-meta {
    font-size: 0.9rem;
    color: #1e3a8a;
    line-height: 1.5;
}
.admin-forward-name  { font-weight: 700; }
.admin-forward-role  { color: #3b5998; }
.admin-forward-sep   { color: #93c5fd; margin: 0 4px; }
.admin-forward-date  { color: #1e40af; font-weight: 500; }
.admin-forward-note {
    margin-top: 8px;
    padding: 8px 10px;
    background: #ffffff;
    border-radius: 6px;
    border: 1px solid #dbeafe;
    font-size: 0.88rem;
    color: #1f2937;
    line-height: 1.55;
}
.admin-forward-note-label {
    font-weight: 700;
    color: #1e40af;
    margin-right: 4px;
}
</style>
<?php endif; ?>

<?php if (!empty($opa['festival_notes'])): ?>
<div class="mb-3">
    <div class="text-muted small">উৎসব নোট</div>
    <div class="p-2 bg-light rounded"><?= nl2br(htmlspecialchars($opa['festival_notes'])) ?></div>
</div>
<?php endif; ?>

<?php if (!empty($opa['attachment'])): ?>
<div class="mb-3">
    <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($opa['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
        <i class="ti tabler-paperclip me-1"></i>সংযুক্তি দেখুন
    </a>
</div>
<?php endif; ?>

<?php if ((int)$opa['status'] === 2 && !empty($opa['final_rejection_reason'])): ?>
<div class="alert alert-danger py-2">
    <b>প্রত্যাখ্যানের কারণ:</b> <?= htmlspecialchars($opa['final_rejection_reason']) ?>
</div>
<?php endif; ?>

<h6 class="fw-semibold mt-3 mb-2"><i class="ti tabler-route me-1"></i>অনুমোদন চেইন</h6>

<?php if (empty($signatories)): ?>
    <div class="alert alert-warning py-2">কোনো signatory নির্ধারিত নেই</div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($signatories as $i => $sig):
            $sigStatus = (int)$sig['isApproved'];
            $sigClass = $sigStatus === 1 ? 'list-group-item-success'
                      : ($sigStatus === 2 ? 'list-group-item-danger' : '');
            $sigIcon = $sigStatus === 1 ? 'tabler-check text-success'
                     : ($sigStatus === 2 ? 'tabler-x text-danger' : 'tabler-clock text-warning');
        ?>
            <div class="list-group-item <?= $sigClass ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="ti <?= $sigIcon ?>" style="font-size:1.3rem;"></i>
                        <div>
                            <div class="fw-semibold">
                                <?= banglaNumber((int)$sig['serial']) ?>.
                                <?= htmlspecialchars($sig['sig_name'] ?? '—') ?>
                                <?php if (!empty($sig['isSupervisor'])): ?>
                                    <span class="badge bg-label-info ms-1">সুপারভাইজার</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small">
                                <?= htmlspecialchars($sig['sig_title'] ?? '') ?>
                                <?php if (!empty($sig['sig_code'])): ?>
                                    · <?= banglaNumber($sig['sig_code']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($sigStatus === 2 && !empty($sig['rejectionReason'])): ?>
                                <div class="text-danger small mt-1"><b>কারণ:</b> <?= htmlspecialchars($sig['rejectionReason']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end small">
                        <?php if ($sigStatus === 1): ?>
                            <span class="text-success"><i class="ti tabler-check me-1"></i>অনুমোদিত</span>
                            <div class="text-muted"><?= bn_dt($sig['approvedDate']) ?></div>
                        <?php elseif ($sigStatus === 2): ?>
                            <span class="text-danger"><i class="ti tabler-x me-1"></i>প্রত্যাখ্যাত</span>
                            <div class="text-muted"><?= bn_dt($sig['rejectedDate']) ?></div>
                        <?php else: ?>
                            <span class="text-warning"><i class="ti tabler-clock me-1"></i>অপেক্ষমান</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
