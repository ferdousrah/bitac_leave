<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Actor scope — same gating as manage.php
$_actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actor = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);

$_isSuperAdmin  = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID    = (int)($_actor['emp_org'] ?? 0);
$_seeAllCenters = ($_isSuperAdmin || $_myCenterID === 4);

// Filters from query string (so URL is shareable)
$f_from   = isset($_GET['from'])       ? trim($_GET['from'])       : date('Y') . '-01-01';
$f_to     = isset($_GET['to'])         ? trim($_GET['to'])         : date('Y-m-d');
$f_fromC  = isset($_GET['from_center']) ? (int)$_GET['from_center'] : 0;
$f_toC    = isset($_GET['to_center'])   ? (int)$_GET['to_center']   : 0;
$f_search = isset($_GET['q'])           ? trim($_GET['q'])           : '';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = date('Y') . '-01-01';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = date('Y-m-d');

// Build WHERE
$wParts = ["h.from_organization_id IS NOT NULL", "h.transfer_date BETWEEN '$f_from' AND '$f_to'"];
if (!$_seeAllCenters) {
    $mc = $_myCenterID;
    $wParts[] = "(h.from_organization_id = $mc OR h.to_organization_id = $mc)";
}
if ($f_fromC > 0) $wParts[] = "h.from_organization_id = $f_fromC";
if ($f_toC > 0)   $wParts[] = "h.to_organization_id = $f_toC";
if ($f_search !== '') {
    $s = mysqli_real_escape_string($con, $f_search);
    $wParts[] = "(e.employee_name LIKE '%$s%' OR e.employee_id LIKE '%$s%' OR h.order_number LIKE '%$s%')";
}
$where = implode(' AND ', $wParts);

$sql = "
    SELECT h.dataID, h.employee_ref_id, h.transfer_date, h.actual_joining_date, h.effective_to,
           h.order_number, h.order_date,
           e.employee_name, e.employee_id, jt.job_title_name,
           ofrm.organization_name AS from_name,
           oto.organization_name  AS to_name,
           sec.section_name       AS section_at_join
    FROM employee_transfer_history h
    LEFT JOIN employee_list e   ON e.id = h.employee_ref_id
    LEFT JOIN job_title jt      ON jt.id = e.designation
    LEFT JOIN organization ofrm ON ofrm.id = h.from_organization_id
    LEFT JOIN organization oto  ON oto.id  = h.to_organization_id
    LEFT JOIN sections sec      ON sec.id  = h.section_id_at_join
    WHERE $where
    ORDER BY h.transfer_date DESC, h.dataID DESC
";
$rows = mysqli_query($con, $sql);

// Centers list for filter dropdowns
if ($_seeAllCenters) {
    $orgsRes = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted = 0 ORDER BY organization_name ASC");
} else {
    $orgsRes = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted = 0 AND id = $_myCenterID");
}
$centers = mysqli_fetch_all($orgsRes, MYSQLI_ASSOC);

function bn_date($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $parts = explode('-', $d);
    if (count($parts) !== 3) return htmlspecialchars($d);
    return banglaNumber($parts[2]) . '-' . banglaNumber($parts[1]) . '-' . banglaNumber($parts[0]);
}
function tenure_days($from, $to) {
    if (!$from || $from === '0000-00-00') return null;
    $end = ($to && $to !== '0000-00-00') ? $to : date('Y-m-d');
    $d1 = strtotime($from); $d2 = strtotime($end);
    if (!$d1 || !$d2) return null;
    return max(0, (int)floor(($d2 - $d1) / 86400));
}
?>

<style>
@media print {
    .no-print { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
    body { background: #fff !important; }
}
.report-summary { background:#f8fafc; border-radius:.5rem; padding:.85rem 1.15rem; display:flex; gap:1.5rem; flex-wrap:wrap; font-size:.9rem; }
.report-summary .item { display:flex; flex-direction:column; }
.report-summary .item small { color:#64748b; }
.report-summary .item b { font-size:1.1rem; color:#0f172a; }
</style>

<!-- Header -->
<div class="row mb-3 align-items-center no-print">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-report me-2 text-primary"></i>বদলি প্রতিবেদন</h4>
        <div class="text-muted small mt-1">
            <a href="manage.php?menuslug=<?= htmlspecialchars($_GET['menuslug'] ?? 'employee-transfer') ?>" data-turbo="true">
                <i class="ti tabler-arrow-left me-1"></i>ব্যবস্থাপনা পাতায় ফিরে যান
            </a>
        </div>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" class="btn btn-label-secondary" onclick="window.print()">
            <i class="ti tabler-printer me-1"></i>প্রিন্ট
        </button>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="card shadow-sm border-0 mb-3 no-print" style="border-radius:.75rem;">
    <input type="hidden" name="menuslug" value="<?= htmlspecialchars($_GET['menuslug'] ?? 'employee-transfer') ?>">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">হতে</label>
                <input type="text" name="from" value="<?= htmlspecialchars($f_from) ?>" class="form-control form-control-sm flatpickr-input" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">পর্যন্ত</label>
                <input type="text" name="to" value="<?= htmlspecialchars($f_to) ?>" class="form-control form-control-sm flatpickr-input" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">পূর্বের কেন্দ্র</label>
                <select name="from_center" class="form-select form-select-sm">
                    <option value="0">সব</option>
                    <?php foreach ($centers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $f_fromC === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['organization_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">নতুন কেন্দ্র</label>
                <select name="to_center" class="form-select form-select-sm">
                    <option value="0">সব</option>
                    <?php foreach ($centers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $f_toC === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['organization_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">খুঁজুন</label>
                <input type="text" name="q" value="<?= htmlspecialchars($f_search) ?>" class="form-control form-control-sm" placeholder="নাম / আইডি / আদেশ নং">
            </div>
            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="ti tabler-filter me-1"></i>প্রয়োগ</button>
            </div>
        </div>
    </div>
</form>

<?php
$totalRows = mysqli_num_rows($rows);
$results = mysqli_fetch_all($rows, MYSQLI_ASSOC);
$totalDays = 0; $closedCount = 0; $openCount = 0;
foreach ($results as $r) {
    $effFrom = $r['actual_joining_date'] ?: $r['transfer_date'];
    $td = tenure_days($effFrom, $r['effective_to']);
    if ($td !== null) $totalDays += $td;
    if (!empty($r['effective_to'])) $closedCount++; else $openCount++;
}
?>

<!-- Report header (printable) -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="text-center mb-3">
            <h5 class="fw-bold mb-1">কর্মচারী বদলি প্রতিবেদন</h5>
            <div class="text-muted small">সময়কাল: <?= bn_date($f_from) ?> হতে <?= bn_date($f_to) ?></div>
        </div>
        <div class="report-summary">
            <div class="item"><small>মোট বদলি</small><b><?= banglaNumber($totalRows) ?></b></div>
            <div class="item"><small>সক্রিয় পোস্টিং</small><b><?= banglaNumber($openCount) ?></b></div>
            <div class="item"><small>পরবর্তী বদলি হয়েছে</small><b><?= banglaNumber($closedCount) ?></b></div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ক্র.</th>
                        <th>কর্মচারী</th>
                        <th>পদবি</th>
                        <th>পূর্বের কেন্দ্র</th>
                        <th>নতুন কেন্দ্র</th>
                        <th>সেকশন</th>
                        <th>আদেশ নং</th>
                        <th>আদেশ তারিখ</th>
                        <th>কার্যকর</th>
                        <th>যোগদান</th>
                        <th>সমাপ্ত</th>
                        <th>মেয়াদ (দিন)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr><td colspan="12" class="text-center text-muted py-4">কোনো রেকর্ড পাওয়া যায়নি</td></tr>
                    <?php else: $sl = 1; foreach ($results as $r):
                        $effFrom = $r['actual_joining_date'] ?: $r['transfer_date'];
                        $td = tenure_days($effFrom, $r['effective_to']);
                    ?>
                        <tr>
                            <td><?= banglaNumber($sl++) ?></td>
                            <td><?= htmlspecialchars($r['employee_name']) ?><br><small class="text-muted"><?= banglaNumber($r['employee_id']) ?></small></td>
                            <td><?= htmlspecialchars($r['job_title_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['from_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['to_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['section_at_join'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['order_number'] ?? '—') ?></td>
                            <td><?= bn_date($r['order_date']) ?></td>
                            <td><?= bn_date($r['transfer_date']) ?></td>
                            <td><?= bn_date($r['actual_joining_date']) ?></td>
                            <td><?= bn_date($r['effective_to']) ?></td>
                            <td class="text-end"><?= $td === null ? '—' : banglaNumber($td) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function bootReport() {
    if (typeof jQuery === 'undefined' || !jQuery.fn) {
        return setTimeout(bootReport, 30);
    }
    function init() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr('input[name="from"]', { dateFormat: 'Y-m-d', allowInput: true });
            flatpickr('input[name="to"]',   { dateFormat: 'Y-m-d', allowInput: true });
        }
    }
    $(document).ready(init);
    document.addEventListener('turbo:load', init);
    if (document.readyState !== 'loading') init();
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
