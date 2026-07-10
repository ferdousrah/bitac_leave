<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Actor scope — Super Admin + HQ (org=4) all centers; others centerwise
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

// Filters
$f_from   = isset($_GET['from'])       ? trim($_GET['from'])       : '';
$f_to     = isset($_GET['to'])         ? trim($_GET['to'])         : '';
$f_center = isset($_GET['center'])     ? (int)$_GET['center']      : 0;
$f_search = isset($_GET['q'])          ? trim($_GET['q'])          : '';
$f_status = isset($_GET['status'])     ? trim($_GET['status'])     : '';

if ($f_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = '';
if ($f_to   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = '';

$wParts = ['1=1'];
if (!$_seeAllCenters) {
    $wParts[] = 'el.organization_id = ' . $_myCenterID;
}
if ($f_center > 0 && $_seeAllCenters) $wParts[] = 'el.organization_id = ' . $f_center;
if ($f_from !== '') $wParts[] = "r.deducted_on >= '$f_from'";
if ($f_to   !== '') $wParts[] = "r.deducted_on <= '$f_to'";
if ($f_search !== '') {
    $s = mysqli_real_escape_string($con, $f_search);
    $wParts[] = "(el.employee_name LIKE '%$s%' OR el.employee_id LIKE '%$s%')";
}
if ($f_status === 'matured') {
    $wParts[] = 'r.next_maturity_date <= CURDATE()';
} elseif ($f_status === 'upcoming') {
    $wParts[] = 'r.next_maturity_date > CURDATE()';
} elseif ($f_status === 'approved') {
    $wParts[] = 'ldh.isApproved = 1';
} elseif ($f_status === 'pending') {
    $wParts[] = 'ldh.isApproved = 0';
}
$where = implode(' AND ', $wParts);

$sql = "
    SELECT r.id, r.employee_id, r.deduction_days, r.deducted_on, r.next_maturity_date,
           r.attachment, r.note, r.leave_deduction_history_id,
           el.employee_name, el.employee_id AS emp_code, el.photo,
           jt.job_title_name, o.organization_name, s.section_name,
           ldh.isApproved AS approval_status,
           DATEDIFF(r.next_maturity_date, CURDATE()) AS days_until_maturity
    FROM recreation_leave_history r
    INNER JOIN employee_list el ON r.employee_id = el.id
    LEFT JOIN job_title jt ON el.designation = jt.id
    LEFT JOIN organization o ON el.organization_id = o.id
    LEFT JOIN sections s ON el.section_id = s.id
    LEFT JOIN leave_deduction_history ldh ON r.leave_deduction_history_id = ldh.dataID
    WHERE $where
    ORDER BY r.next_maturity_date ASC, r.deducted_on DESC
";
$res = mysqli_query($con, $sql);
$rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Center list for filter
if ($_seeAllCenters) {
    $orgQ = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted=0 ORDER BY organization_name ASC");
} else {
    $orgQ = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted=0 AND id=$_myCenterID");
}
$centers = mysqli_fetch_all($orgQ, MYSQLI_ASSOC);

// Aggregate stats
$stats = ['total' => count($rows), 'matured' => 0, 'upcoming' => 0];
foreach ($rows as $r) {
    if ((int)$r['days_until_maturity'] <= 0) $stats['matured']++;
    else $stats['upcoming']++;
}

function bn_date($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $p = explode('-', $d);
    if (count($p) !== 3) return htmlspecialchars($d);
    return banglaNumber($p[2]) . '-' . banglaNumber($p[1]) . '-' . banglaNumber($p[0]);
}
?>

<style>
@media print {
    @page { margin: 12mm 10mm; size: A4 landscape; }

    /* Hide app chrome: sidebar, navbar, footer, everything except main content */
    html, body { background: #fff !important; color: #000 !important; margin: 0 !important; padding: 0 !important; }
    .layout-wrapper, .layout-container, .layout-page, .content-wrapper, .container-xxl, .container-fluid { display: block !important; margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
    aside, .layout-menu, .menu-vertical, .menu, #layout-menu,
    .layout-navbar, .navbar, nav, .app-brand, .layout-footer, footer,
    .bitac-nav-right, .bitac-navbar, header,
    .no-print { display: none !important; visibility: hidden !important; }

    /* Content flows full width */
    main, .content-wrapper { padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #d5d5d5 !important; page-break-inside: avoid; }
    .card-body { padding: 12px !important; }

    /* Report title stays prominent */
    .print-header { display: block !important; text-align: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #333; }
    .print-header h5 { font-size: 15pt !important; font-weight: 700 !important; margin: 0 0 3px !important; color: #000 !important; }
    .print-header .print-sub { font-size: 10pt !important; color: #333 !important; }

    /* Table: print-friendly borders */
    table { border-collapse: collapse !important; width: 100% !important; page-break-inside: auto; }
    thead { display: table-header-group; }
    tr, td, th { page-break-inside: avoid; }
    table th, table td { border: 1px solid #666 !important; padding: 5px 7px !important; font-size: 9pt !important; color: #000 !important; }
    table thead th { background: #eee !important; font-weight: 600 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* Neutralize badges — plain text with light bg for print */
    .badge-matured, .badge-upcoming, .badge-pending, .badge-rejected {
        background: transparent !important; color: #000 !important;
        padding: 0 !important; border-radius: 0 !important;
        font-size: 9pt !important; font-weight: 400 !important;
    }

    /* Employee cell — hide avatar image on print, keep just name */
    .emp-cell { display: block !important; gap: 0 !important; }
    .emp-avatar { display: none !important; }
}

.rec-stat-card { border-radius:.6rem; padding:.85rem 1.1rem; display:flex; gap:.75rem; align-items:center; background:#fff; box-shadow:0 0 10px rgba(0,0,0,.04); }
.rec-stat-card .icon { width:40px; height:40px; border-radius:.5rem; display:flex; align-items:center; justify-content:center; font-size:1.3rem; }
.rec-stat-card .num { font-weight:700; font-size:1.25rem; line-height:1; }
.rec-stat-card .label { font-size:.8rem; color:#64748b; margin-top:.15rem; }
.stat-b .icon { background:#eaf3ff; color:#3b82f6; }
.stat-g .icon { background:#dcfce7; color:#16a34a; }
.stat-a .icon { background:#fef3c7; color:#d97706; }
.emp-cell { display:flex; align-items:center; gap:.6rem; }
.emp-avatar { width:36px; height:36px; border-radius:50%; background:#e0e7ff; color:#3730a3; display:flex; align-items:center; justify-content:center; font-weight:600; overflow:hidden; font-size:.85rem; }
.emp-avatar img { width:100%; height:100%; object-fit:cover; }
.badge-matured { background:#fef3c7; color:#92400e; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.badge-upcoming { background:#dcfce7; color:#166534; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.badge-pending { background:#e0e7ff; color:#3730a3; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.badge-rejected { background:#fee2e2; color:#991b1b; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
</style>

<!-- Header -->
<div class="row mb-3 align-items-center no-print">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-beach me-2 text-primary"></i>শ্রান্তি বিনোদন প্রতিবেদন</h4>
        <div class="text-muted small mt-1">কর্মচারীদের শ্রান্তি বিনোদন ছুটি ইতিহাস ও পরবর্তী ম্যাচিউরিটি</div>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" class="btn btn-label-secondary" onclick="window.print()"><i class="ti tabler-printer me-1"></i>প্রিন্ট</button>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-3 no-print">
    <div class="col-12 col-md-4">
        <div class="rec-stat-card stat-b">
            <div class="icon"><i class="ti tabler-list-details"></i></div>
            <div><div class="num"><?= banglaNumber($stats['total']) ?></div><div class="label">মোট এন্ট্রি</div></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="rec-stat-card stat-a">
            <div class="icon"><i class="ti tabler-clock-check"></i></div>
            <div><div class="num"><?= banglaNumber($stats['matured']) ?></div><div class="label">ম্যাচিউরড (পরবর্তীর জন্য যোগ্য)</div></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="rec-stat-card stat-g">
            <div class="icon"><i class="ti tabler-hourglass"></i></div>
            <div><div class="num"><?= banglaNumber($stats['upcoming']) ?></div><div class="label">অপেক্ষমান</div></div>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="card shadow-sm border-0 mb-3 no-print" style="border-radius:.65rem;">
    <input type="hidden" name="menuslug" value="<?= htmlspecialchars($_GET['menuslug'] ?? 'recreation-leave-report') ?>">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">কর্তন হতে</label>
                <input type="text" name="from" value="<?= htmlspecialchars($f_from) ?>" class="form-control form-control-sm flatpickr-input" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">পর্যন্ত</label>
                <input type="text" name="to" value="<?= htmlspecialchars($f_to) ?>" class="form-control form-control-sm flatpickr-input" placeholder="YYYY-MM-DD">
            </div>
            <?php if ($_seeAllCenters): ?>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">কেন্দ্র</label>
                <select name="center" class="form-select form-select-sm">
                    <option value="0">সব</option>
                    <?php foreach ($centers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $f_center === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['organization_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">অবস্থা</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">সব</option>
                    <option value="matured"  <?= $f_status==='matured'  ? 'selected' : '' ?>>ম্যাচিউরড</option>
                    <option value="upcoming" <?= $f_status==='upcoming' ? 'selected' : '' ?>>অপেক্ষমান</option>
                    <option value="approved" <?= $f_status==='approved' ? 'selected' : '' ?>>অনুমোদিত</option>
                    <option value="pending"  <?= $f_status==='pending'  ? 'selected' : '' ?>>অনুমোদন অপেক্ষমান</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">খুঁজুন (নাম/আইডি)</label>
                <input type="text" name="q" value="<?= htmlspecialchars($f_search) ?>" class="form-control form-control-sm" placeholder="নাম বা কর্মচারী আইডি">
            </div>
            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="ti tabler-filter me-1"></i>প্রয়োগ</button>
            </div>
        </div>
    </div>
</form>

<!-- Report table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="text-center mb-3 print-header">
            <h5 class="fw-bold mb-1">বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র (বিটাক)</h5>
            <div class="mb-1" style="font-size:0.95rem;">শ্রান্তি বিনোদন ছুটি প্রতিবেদন</div>
            <?php if ($f_from !== '' || $f_to !== ''): ?>
            <div class="text-muted small print-sub">
                সময়কাল: <?= $f_from !== '' ? bn_date($f_from) : '(শুরু)' ?> হতে <?= $f_to !== '' ? bn_date($f_to) : '(বর্তমান)' ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">ক্র.</th>
                        <th>কর্মচারী</th>
                        <th>কেন্দ্র</th>
                        <th class="text-center">দিন</th>
                        <th>কর্তনের তারিখ</th>
                        <th>পরবর্তী ম্যাচিউর</th>
                        <th class="text-center">ম্যাচিউরিটি</th>
                        <th class="text-center">অবস্থা</th>
                        <th class="text-center no-print">সংযুক্তি</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">
                            <i class="ti tabler-beach" style="font-size:2rem; color:#cbd5e1;"></i>
                            <div class="mt-2">কোনো রেকর্ড পাওয়া যায়নি</div>
                        </td></tr>
                    <?php else: $sl=1; foreach ($rows as $r):
                        $dtm = (int)$r['days_until_maturity'];
                        $maturedNow = $dtm <= 0;
                        $matBadge = $maturedNow
                            ? '<span class="badge-matured">ম্যাচিউরড</span>'
                            : '<span class="badge-upcoming">' . banglaNumber($dtm) . ' দিন বাকি</span>';
                        $appStatus = (int)($r['approval_status'] ?? 0);
                        if ($appStatus === 1)      $statBadge = '<span class="badge-upcoming">অনুমোদিত</span>';
                        elseif ($appStatus === 2)  $statBadge = '<span class="badge-rejected">প্রত্যাখ্যাত</span>';
                        else                        $statBadge = '<span class="badge-pending">অপেক্ষমান</span>';

                        $empName = trim($r['employee_name'] ?? '');
                        $initials = mb_substr($empName, 0, 1, 'UTF-8');
                        $parts = preg_split('/\s+/u', $empName);
                        if (count($parts) > 1) $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
                        $avatar = !empty($r['photo'])
                            ? '<div class="emp-avatar"><img src="' . BASE_URL . '/uploads/' . htmlspecialchars($r['photo']) . '" alt=""></div>'
                            : '<div class="emp-avatar">' . htmlspecialchars($initials) . '</div>';
                    ?>
                        <tr>
                            <td><?= banglaNumber($sl++) ?></td>
                            <td>
                                <div class="emp-cell"><?= $avatar ?>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($empName) ?></div>
                                        <div class="text-muted small">
                                            <?= banglaNumber($r['emp_code'] ?? '') ?>
                                            <?= !empty($r['job_title_name']) ? ' · ' . htmlspecialchars($r['job_title_name']) : '' ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($r['organization_name'] ?? '—') ?>
                                <?php if (!empty($r['section_name'])): ?><div class="text-muted small"><?= htmlspecialchars($r['section_name']) ?></div><?php endif; ?>
                            </td>
                            <td class="text-center fw-semibold"><?= banglaNumber((float)$r['deduction_days']) ?></td>
                            <td><?= bn_date($r['deducted_on']) ?></td>
                            <td><?= bn_date($r['next_maturity_date']) ?></td>
                            <td class="text-center"><?= $matBadge ?></td>
                            <td class="text-center"><?= $statBadge ?></td>
                            <td class="text-center no-print">
                                <?php if (!empty($r['attachment'])): ?>
                                    <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($r['attachment']) ?>" target="_blank" class="btn btn-sm btn-label-primary" data-bs-toggle="tooltip" title="আদেশের কপি">
                                        <i class="ti tabler-paperclip"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function bootRecReport() {
    if (typeof jQuery === 'undefined' || !jQuery.fn) return setTimeout(bootRecReport, 30);
    function init() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr('input[name="from"]', { dateFormat: 'Y-m-d', allowInput: true });
            flatpickr('input[name="to"]',   { dateFormat: 'Y-m-d', allowInput: true });
        }
    }
    $(document).ready(init);
    document.addEventListener('turbo:load', init);
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
