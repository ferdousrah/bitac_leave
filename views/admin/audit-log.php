<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'system-audit-log');

// Gate: Super Admin only (group_id=1)
$_uStmt = mysqli_prepare($con, "SELECT user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($_uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($_uStmt);
$_uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($_uStmt));
mysqli_stmt_close($_uStmt);
if (!$_uRow || (int)$_uRow['user_group_id'] !== 1) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-shield-x me-1"></i>এই পেজ শুধু Super Admin এর জন্য।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Filters
$filterAction = trim($_GET['action'] ?? '');
$filterActor  = trim($_GET['actor'] ?? '');
$filterTarget = trim($_GET['target_type'] ?? '');
$filterFrom   = trim($_GET['from'] ?? '');
$filterTo     = trim($_GET['to']   ?? '');

// Distinct values for filter dropdowns (limited to seen-in-DB to keep it dynamic)
$actionsRes = mysqli_query($con, "SELECT DISTINCT action FROM audit_log ORDER BY action ASC LIMIT 100");
$availableActions = $actionsRes ? array_column(mysqli_fetch_all($actionsRes, MYSQLI_ASSOC), 'action') : [];
$targetsRes = mysqli_query($con, "SELECT DISTINCT target_type FROM audit_log WHERE target_type IS NOT NULL ORDER BY target_type ASC LIMIT 50");
$availableTargets = $targetsRes ? array_column(mysqli_fetch_all($targetsRes, MYSQLI_ASSOC), 'target_type') : [];

// Build WHERE clause
$where = [];
$params = [];
$types  = '';
if ($filterAction !== '') { $where[] = 'action = ?'; $types .= 's'; $params[] = $filterAction; }
if ($filterActor  !== '') { $where[] = '(actor_username LIKE ? OR actor_name LIKE ?)'; $types .= 'ss'; $params[] = "%$filterActor%"; $params[] = "%$filterActor%"; }
if ($filterTarget !== '') { $where[] = 'target_type = ?'; $types .= 's'; $params[] = $filterTarget; }
if ($filterFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterFrom)) {
    $where[] = 'DATE(createdAt) >= ?'; $types .= 's'; $params[] = $filterFrom;
}
if ($filterTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTo)) {
    $where[] = 'DATE(createdAt) <= ?'; $types .= 's'; $params[] = $filterTo;
}
$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$sql = "SELECT * FROM audit_log $whereSql ORDER BY dataID DESC LIMIT 300";
$stmt = mysqli_prepare($con, $sql);
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$logs = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Action → color mapping (best effort grouping by prefix)
function actionPillStyle($action) {
    if (strpos($action, 'login_success') !== false || strpos($action, 'created') !== false || strpos($action, 'approved') !== false || strpos($action, 'submitted') !== false || strpos($action, 'forwarded') !== false) {
        return ['#dcfce7', '#166534'];
    }
    if (strpos($action, 'login_failed') !== false || strpos($action, 'rejected') !== false || strpos($action, 'deleted') !== false) {
        return ['#fee2e2', '#991b1b'];
    }
    if (strpos($action, 'updated') !== false || strpos($action, 'returned') !== false || strpos($action, 'resubmitted') !== false) {
        return ['#fef3c7', '#854d0e'];
    }
    if (strpos($action, 'role_') !== false) {
        return ['#f0edff', '#5648c4'];
    }
    return ['#f3f4f8', '#5d6580'];
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-list-search me-2 text-primary"></i>সিস্টেম কার্যক্রম লগ</h4>
        <div class="text-muted small mt-1 ms-1">
            <i class="ti tabler-info-circle me-1"></i>লগইন, ব্যবহারকারী, ছুটি, রোল — সকল কার্যক্রমের audit trail
        </div>
    </div>
</div>

<style>
.audit-filter-card { border-radius: 0.75rem; }
.audit-filter-card .card-body { padding: 1.15rem 1.35rem; }
.audit-filter-card .form-label { font-size: 0.78rem; color: #5d6580; font-weight: 600; margin-bottom: 0.3rem; }
.audit-log-card { border-radius: 0.75rem; }
.audit-log-card .card-body { padding: 0; }
.audit-table { margin-bottom: 0; }
.audit-table th {
    background: #fafbfd; color: #5d6580;
    font-size: 0.74rem; font-weight: 700;
    letter-spacing: 0.04em; text-transform: uppercase;
    border-bottom: 1px solid #eef0f5; padding: 0.85rem 1rem;
}
.audit-table td {
    font-size: 0.85rem; color: #2c2e3a; vertical-align: top;
    padding: 0.7rem 1rem; border-bottom: 1px solid #f3f4fa;
}
.audit-table tr:last-child td { border-bottom: 0; }
.action-pill {
    display: inline-block; font-size: 0.7rem; font-weight: 700;
    padding: 0.18rem 0.6rem; border-radius: 999px;
    font-family: monospace;
}
.audit-meta { font-size: 0.72rem; color: #8a90a6; }
.audit-note { color: #5d6580; font-size: 0.78rem; max-width: 380px; word-break: break-word; }
.audit-ip { font-family: monospace; font-size: 0.78rem; color: #6b7280; }
.empty-log { padding: 3rem 2rem; text-align: center; color: #8a90a6; }
.empty-log i { font-size: 2.5rem; color: #c4c9d6; display: block; margin-bottom: 0.5rem; }
</style>

<!-- Filters -->
<div class="card audit-filter-card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end" data-turbo="false">
            <input type="hidden" name="menuslug" value="<?= $menuslug ?>">
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label" for="action">কার্যক্রম</label>
                <select id="action" name="action" class="form-select form-select-sm">
                    <option value="">সকল</option>
                    <?php foreach ($availableActions as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label" for="actor">সম্পাদনকারী (username/নাম)</label>
                <input type="text" id="actor" name="actor" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filterActor) ?>" placeholder="খুঁজুন...">
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="target_type">টার্গেট</label>
                <select id="target_type" name="target_type" class="form-select form-select-sm">
                    <option value="">সকল</option>
                    <?php foreach ($availableTargets as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $filterTarget === $t ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-1">
                <label class="form-label" for="from">শুরু</label>
                <input type="date" id="from" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($filterFrom) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-1">
                <label class="form-label" for="to">শেষ</label>
                <input type="date" id="to" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($filterTo) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="ti tabler-filter me-1"></i>ফিল্টার
                </button>
                <a href="audit-log.php?menuslug=<?= $menuslug ?>" class="btn btn-sm btn-label-secondary" title="রিসেট" data-turbo="false">
                    <i class="ti tabler-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Log table -->
<div class="card audit-log-card shadow-sm border-0">
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="empty-log">
                <i class="ti tabler-search-off"></i>
                <div>কোনো লগ এন্ট্রি পাওয়া যায়নি</div>
                <small>ফিল্টার পরিবর্তন করুন বা পরে আবার চেষ্টা করুন</small>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table audit-table">
                    <thead>
                        <tr>
                            <th>সময়</th>
                            <th>কার্যক্রম</th>
                            <th>সম্পাদনকারী</th>
                            <th>টার্গেট</th>
                            <th>IP</th>
                            <th>বিস্তারিত</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $l):
                        [$bg, $fg] = actionPillStyle($l['action']);
                    ?>
                    <tr>
                        <td>
                            <div><?= date('d/m/Y', strtotime($l['createdAt'])) ?></div>
                            <div class="audit-meta"><?= date('H:i:s', strtotime($l['createdAt'])) ?></div>
                        </td>
                        <td>
                            <span class="action-pill" style="background:<?= $bg ?>; color:<?= $fg ?>;">
                                <?= htmlspecialchars($l['action']) ?>
                            </span>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($l['actor_name'] ?? '—') ?></div>
                            <?php if (!empty($l['actor_username'])): ?>
                                <div class="audit-meta">@<?= htmlspecialchars($l['actor_username']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($l['target_type'])): ?>
                                <div><code><?= htmlspecialchars($l['target_type']) ?></code></div>
                                <?php if (!empty($l['target_id'])): ?>
                                    <div class="audit-meta">#<?= (int)$l['target_id'] ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($l['ip_address'])): ?>
                                <span class="audit-ip"><?= htmlspecialchars($l['ip_address']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($l['note'])): ?>
                                <div class="audit-note"><?= nl2br(htmlspecialchars($l['note'])) ?></div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($logs) >= 300): ?>
            <div class="text-center text-muted small py-2 border-top">
                <i class="ti tabler-info-circle me-1"></i>সর্বশেষ ৩০০ টি এন্ট্রি দেখানো হচ্ছে — পুরোনো এন্ট্রি দেখতে তারিখ ফিল্টার ব্যবহার করুন
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
