<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Actor scope
$actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);

$isSuperAdmin  = ((int)($actor['user_group_id'] ?? 0) === 1);
$myCenterID    = (int)($actor['emp_org'] ?? 0);
$seeAllCenters = ($isSuperAdmin || $myCenterID === 4);

$empId = isset($_GET['emp']) ? (int)$_GET['emp'] : 0;
if ($empId <= 0) { echo '<div class="alert alert-danger">অবৈধ কর্মচারী</div>'; exit; }

// Verify employee + scope
$empStmt = mysqli_prepare($con,
    "SELECT e.id, e.employee_name, e.employee_id, e.organization_id, e.pending_section_assignment,
            jt.job_title_name, o.organization_name AS current_org, s.section_name AS current_section
     FROM employee_list e
     LEFT JOIN job_title jt ON jt.id = e.designation
     LEFT JOIN organization o ON o.id = e.organization_id
     LEFT JOIN sections s ON s.id = e.section_id
     WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($empStmt, 'i', $empId);
mysqli_stmt_execute($empStmt);
$emp = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));
mysqli_stmt_close($empStmt);
if (!$emp) { echo '<div class="alert alert-danger">কর্মচারী পাওয়া যায়নি</div>'; exit; }

// Scope check
if (!$seeAllCenters) {
    $accessChk = mysqli_prepare($con,
        "SELECT 1 FROM employee_transfer_history
         WHERE employee_ref_id = ?
           AND (from_organization_id = ? OR to_organization_id = ?)
         LIMIT 1");
    mysqli_stmt_bind_param($accessChk, 'iii', $empId, $myCenterID, $myCenterID);
    mysqli_stmt_execute($accessChk);
    $hasAccess = mysqli_fetch_assoc(mysqli_stmt_get_result($accessChk));
    mysqli_stmt_close($accessChk);
    if (!$hasAccess && (int)$emp['organization_id'] !== $myCenterID) {
        echo '<div class="alert alert-warning">এই কর্মচারীর ইতিহাস দেখার অনুমতি নেই</div>';
        exit;
    }
}

// Fetch full posting timeline (oldest → newest)
$histRes = mysqli_query($con, "
    SELECT h.*, ofrm.organization_name AS from_name, oto.organization_name AS to_name,
           s.section_name AS section_at_join
    FROM employee_transfer_history h
    LEFT JOIN organization ofrm ON ofrm.id = h.from_organization_id
    LEFT JOIN organization oto  ON oto.id  = h.to_organization_id
    LEFT JOIN sections s        ON s.id    = h.section_id_at_join
    WHERE h.employee_ref_id = $empId
    ORDER BY h.transfer_date ASC, h.dataID ASC");

function bn_date($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $parts = explode('-', $d);
    if (count($parts) !== 3) return htmlspecialchars($d);
    return banglaNumber($parts[2]) . '-' . banglaNumber($parts[1]) . '-' . banglaNumber($parts[0]);
}

function tenure_days($from, $to) {
    if (!$from || $from === '0000-00-00') return null;
    $end = ($to && $to !== '0000-00-00') ? $to : date('Y-m-d');
    $d1 = strtotime($from);
    $d2 = strtotime($end);
    if (!$d1 || !$d2) return null;
    return max(0, (int)floor(($d2 - $d1) / 86400));
}
?>

<div class="emp-header mb-3 pb-3 border-bottom">
    <div class="d-flex align-items-center gap-3">
        <div style="width:56px;height:56px;border-radius:50%;background:#e0e7ff;color:#3730a3;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.4rem;">
            <?= mb_substr($emp['employee_name'] ?? '?', 0, 1, 'UTF-8') ?>
        </div>
        <div>
            <div class="fw-bold fs-5"><?= htmlspecialchars($emp['employee_name']) ?>
                <span class="text-muted fw-normal fs-6">(<?= banglaNumber($emp['employee_id']) ?>)</span>
            </div>
            <div class="text-muted small"><?= htmlspecialchars($emp['job_title_name'] ?? '') ?></div>
            <div class="mt-1">
                <span class="badge bg-label-primary"><i class="ti tabler-map-pin me-1"></i><?= htmlspecialchars($emp['current_org'] ?? '—') ?></span>
                <?php if (!empty($emp['current_section'])): ?>
                    <span class="badge bg-label-info"><i class="ti tabler-building me-1"></i><?= htmlspecialchars($emp['current_section']) ?></span>
                <?php elseif ((int)$emp['pending_section_assignment'] === 1): ?>
                    <span class="badge bg-label-warning"><i class="ti tabler-clock-pause me-1"></i>সেকশন বরাদ্দ অপেক্ষমান</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h6 class="fw-semibold mb-3"><i class="ti tabler-route me-1"></i>পোস্টিং ইতিহাস</h6>

<?php
$timeline = mysqli_fetch_all($histRes, MYSQLI_ASSOC);
if (empty($timeline)):
?>
    <div class="alert alert-light border text-center text-muted">কোনো পোস্টিং রেকর্ড নেই</div>
<?php else: ?>
    <div class="position-relative ps-4">
        <div style="position:absolute;left:11px;top:0;bottom:0;width:2px;background:#e2e8f0;"></div>
        <?php foreach ($timeline as $i => $h):
            $isInitial = empty($h['from_organization_id']);
            $isOpen    = empty($h['effective_to']);
            $effFrom   = $h['actual_joining_date'] ?: $h['transfer_date'];
            $days      = tenure_days($effFrom, $h['effective_to']);
        ?>
            <div class="mb-3 position-relative">
                <div style="position:absolute;left:-21px;top:6px;width:14px;height:14px;border-radius:50%;background:<?= $isOpen ? '#10b981' : '#94a3b8' ?>;border:2px solid #fff;box-shadow:0 0 0 2px <?= $isOpen ? '#10b981' : '#94a3b8' ?>;"></div>
                <div class="card border-0 shadow-sm" style="background:<?= $isOpen ? '#f0fdf4' : '#f8fafc' ?>;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <?php if ($isInitial): ?>
                                    <span class="badge bg-label-info me-1">প্রাথমিক পোস্টিং</span>
                                <?php else: ?>
                                    <span class="text-muted small"><?= htmlspecialchars($h['from_name'] ?? '—') ?></span>
                                    <i class="ti tabler-arrow-right text-muted mx-1"></i>
                                <?php endif; ?>
                                <span class="fw-semibold"><?= htmlspecialchars($h['to_name'] ?? '—') ?></span>
                                <?php if (!empty($h['section_at_join'])): ?>
                                    <span class="badge bg-label-secondary ms-1"><?= htmlspecialchars($h['section_at_join']) ?></span>
                                <?php elseif ($isOpen && !$isInitial): ?>
                                    <span class="badge bg-label-warning ms-1"><i class="ti tabler-clock-pause me-1"></i>সেকশন অপেক্ষমান</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isOpen): ?>
                                <span class="badge bg-success">বর্তমান</span>
                            <?php endif; ?>
                        </div>
                        <div class="row small text-muted g-2">
                            <div class="col-6 col-md-3">
                                <i class="ti tabler-calendar-event me-1"></i>কার্যকর: <span class="fw-semibold text-dark"><?= bn_date($h['transfer_date']) ?></span>
                            </div>
                            <?php if (!empty($h['actual_joining_date'])): ?>
                            <div class="col-6 col-md-3">
                                <i class="ti tabler-login me-1"></i>যোগদান: <span class="fw-semibold text-dark"><?= bn_date($h['actual_joining_date']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($h['effective_to'])): ?>
                            <div class="col-6 col-md-3">
                                <i class="ti tabler-calendar-x me-1"></i>সমাপ্ত: <span class="fw-semibold text-dark"><?= bn_date($h['effective_to']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($days !== null): ?>
                            <div class="col-6 col-md-3">
                                <i class="ti tabler-clock me-1"></i>মেয়াদ: <span class="fw-semibold text-dark"><?= banglaNumber($days) ?> দিন</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($h['order_number'])): ?>
                            <div class="col-12 col-md-6">
                                <i class="ti tabler-file-text me-1"></i>আদেশ: <span class="fw-semibold text-dark"><?= htmlspecialchars($h['order_number']) ?></span>
                                <?php if (!empty($h['order_date'])): ?>
                                    <span class="ms-2 text-muted">(<?= bn_date($h['order_date']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($h['attachment'])): ?>
                            <div class="col-12 col-md-3">
                                <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($h['attachment']) ?>" target="_blank" class="text-primary">
                                    <i class="ti tabler-paperclip me-1"></i>আদেশের কপি
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($h['reason'])): ?>
                            <div class="mt-2 pt-2 border-top small text-muted">
                                <i class="ti tabler-message me-1"></i><?= nl2br(htmlspecialchars($h['reason'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
