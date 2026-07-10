<?php
$pageTitle    = 'শিক্ষানবিশ কর্মচারী';
$pageSubtitle = 'অস্থায়ী আইডিধারী কর্মচারী — ২ বছর পূর্ণ হলে স্থায়ীকরণ';

require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Filters
$centerFilter = (int)($_GET['center'] ?? 0);

$where = "WHERE e.employment_type = 'probationary' AND e.employment_status = 1";
if ($centerFilter > 0) $where .= " AND e.organization_id = " . $centerFilter;

$q = mysqli_query($con,
    "SELECT e.id, e.employee_id, e.employee_name, e.probation_start_date, e.organization_id,
            o.organization_name, jt.job_title_name, s.section_name
     FROM employee_list e
     LEFT JOIN organization o ON e.organization_id = o.id
     LEFT JOIN job_title    jt ON e.designation = jt.id
     LEFT JOIN sections     s ON e.section_id = s.id
     $where
     ORDER BY e.probation_start_date ASC, e.employee_name ASC");

$rows = [];
while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;

// Center list for filter
$centerListQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY (id=4) DESC, organization_name ASC");

function be_num($n) { return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']); }
?>

<style>
.prob-wrap { max-width: 1300px; }
.prob-card { border-radius: 0.75rem; border: 1px solid #eef0f5; }
.prob-table { width: 100%; border-collapse: collapse; }
.prob-table th, .prob-table td { padding: 0.7rem 0.9rem; border-bottom: 1px solid #eef0f5; font-size: 0.88rem; vertical-align: middle; }
.prob-table th { background: #fafbfd; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.04em; color: #5d6580; font-weight: 600; text-align: left; }
.prob-table tr:hover td { background: #fdfcff; }
.prob-progress-bar {
    height: 8px; background: #eef0f5;
    border-radius: 999px; overflow: hidden; min-width: 100px;
}
.prob-progress-fill {
    height: 100%; background: linear-gradient(90deg, #6c5ce7, #a29bfe);
    transition: width 0.4s ease;
}
.prob-progress-fill.is-overdue { background: linear-gradient(90deg, #dc3545, #f47174); }
.prob-progress-fill.is-near { background: linear-gradient(90deg, #f59e0b, #ffb84d); }
.prob-stat-card {
    background: #f8f7ff; border: 1px solid #ddd5f6; border-radius: 0.6rem;
    padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.85rem;
}
.prob-stat-card .stat-icon {
    width: 42px; height: 42px; border-radius: 0.5rem;
    background: #6c5ce7; color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
}
.prob-stat-card .stat-count { font-size: 1.5rem; font-weight: 700; color: #2c2e3a; line-height: 1; }
.prob-stat-card .stat-label { font-size: 0.78rem; color: #5d6580; }
</style>

<div class="prob-wrap">
    <!-- Header summary stats -->
    <div class="row mb-3 g-3">
        <?php
        $total = count($rows);
        $overdue = 0; $near = 0;
        foreach ($rows as $r) {
            if (!$r['probation_start_date']) continue;
            $months = (int)((time() - strtotime($r['probation_start_date'])) / (86400 * 30.4));
            if ($months >= 24) $overdue++;
            else if ($months >= 21) $near++;
        }
        ?>
        <div class="col-md-4">
            <div class="prob-stat-card">
                <div class="stat-icon"><i class="ti tabler-clock-hour-4"></i></div>
                <div>
                    <div class="stat-count"><?= banglaNumber($total) ?></div>
                    <div class="stat-label">মোট শিক্ষানবিশ</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="prob-stat-card" style="background:#fff8e1; border-color:#ffe082;">
                <div class="stat-icon" style="background:#f59e0b;"><i class="ti tabler-alert-circle"></i></div>
                <div>
                    <div class="stat-count" style="color:#b8651a;"><?= banglaNumber($near) ?></div>
                    <div class="stat-label">২ বছর পূর্ণ হচ্ছে শীঘ্রই (২১+ মাস)</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="prob-stat-card" style="background:#fff1f0; border-color:#f5c6c6;">
                <div class="stat-icon" style="background:#dc3545;"><i class="ti tabler-alert-triangle"></i></div>
                <div>
                    <div class="stat-count" style="color:#dc3545;"><?= banglaNumber($overdue) ?></div>
                    <div class="stat-label">২ বছর পার হয়েছে — স্থায়ীকরণ বাকি</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter + Table -->
    <div class="card prob-card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <input type="hidden" name="menuslug" value="<?= htmlspecialchars($_GET['menuslug'] ?? 'probationary-employees') ?>" />
                <div class="col-md-4">
                    <select name="center" class="form-select" onchange="this.form.submit()">
                        <option value="0">সব কেন্দ্র</option>
                        <?php while ($c = mysqli_fetch_assoc($centerListQ)): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($centerFilter === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['organization_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php if ($centerFilter > 0): ?>
                <div class="col-auto">
                    <a href="?menuslug=<?= htmlspecialchars($_GET['menuslug'] ?? 'probationary-employees') ?>" class="btn btn-sm btn-label-secondary">
                        <i class="ti tabler-x me-1"></i>রিসেট
                    </a>
                </div>
                <?php endif; ?>
            </form>

            <?php if (empty($rows)): ?>
                <div class="alert alert-info mb-0">
                    <i class="ti tabler-info-circle me-1"></i>কোনো শিক্ষানবিশ কর্মচারী পাওয়া যায়নি।
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="prob-table">
                    <thead>
                        <tr>
                            <th>কর্মচারী</th>
                            <th>অস্থায়ী আইডি</th>
                            <th>পদবি</th>
                            <th>কেন্দ্র</th>
                            <th>শিক্ষানবিশ শুরু</th>
                            <th>অতিবাহিত</th>
                            <th>২ বছর পূর্ণ হওয়ার অবস্থা</th>
                            <th class="text-center">ব্যবস্থা</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $start = $r['probation_start_date'];
                            $months = $start ? (int)((time() - strtotime($start)) / (86400 * 30.4)) : 0;
                            $progressPct = min(100, ($months / 24) * 100);
                            $progressCls = '';
                            if ($months >= 24)       $progressCls = 'is-overdue';
                            else if ($months >= 21)  $progressCls = 'is-near';
                            $daysRemaining = $start ? ((strtotime($start . ' +2 years') - time()) / 86400) : null;
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($r['employee_name']) ?></div>
                                <?php if (!empty($r['section_name'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($r['section_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($r['employee_id']) ?></code></td>
                            <td><?= htmlspecialchars($r['job_title_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['organization_name'] ?? '—') ?></td>
                            <td><?= $start ? be_num(date('d/m/Y', strtotime($start))) : '—' ?></td>
                            <td><strong><?= be_num($months) ?></strong> মাস</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="prob-progress-bar">
                                        <div class="prob-progress-fill <?= $progressCls ?>" style="width: <?= round($progressPct) ?>%;"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php if ($months >= 24): ?>
                                            <span style="color:#dc3545; font-weight:600;"><?= be_num((int)abs($daysRemaining)) ?> দিন পার</span>
                                        <?php elseif ($daysRemaining !== null): ?>
                                            <?= be_num((int)$daysRemaining) ?> দিন বাকি
                                        <?php else: ?>—<?php endif; ?>
                                    </small>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>/views/employees/edit.php?dataID=<?= base64_encode($r['id']) ?>&menuslug=<?= htmlspecialchars($_GET['menuslug'] ?? 'probationary-employees') ?>" class="btn btn-sm btn-success">
                                    <i class="ti tabler-id-badge-2 me-1"></i>স্থায়ীতে রূপান্তর
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
