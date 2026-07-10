<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'role-audit-log');

// Filters
$filterAction = trim($_GET['action'] ?? '');
$filterRole   = (int)($_GET['role_id'] ?? 0);
$filterCenter = (int)($_GET['centerID'] ?? 0);
$filterFrom   = trim($_GET['from'] ?? '');
$filterTo     = trim($_GET['to']   ?? '');

// Fetch reference data for filter dropdowns
$centersStmt = $con->prepare("SELECT id, organization_name FROM organization WHERE deleted = 0 ORDER BY organization_name ASC");
$centersStmt->execute();
$centers = $centersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$centersStmt->close();

// Only the regional roles go through the proposal workflow → only these need to show in filter
$rolesStmt = $con->prepare("SELECT id, group_name FROM user_group WHERE id IN (7, 8) ORDER BY id ASC");
$rolesStmt->execute();
$roles = $rolesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rolesStmt->close();

// Build the query with optional WHERE filters
$where = [];
$params = [];
$types  = '';
if ($filterAction !== '' && in_array($filterAction, ['proposed','approved','rejected','replaced','removed'], true)) {
    $where[] = 'l.action = ?'; $types .= 's'; $params[] = $filterAction;
}
if ($filterRole > 0) {
    $where[] = 'l.role_id = ?'; $types .= 'i'; $params[] = $filterRole;
}
if ($filterCenter > 0) {
    $where[] = 'l.organization_id = ?'; $types .= 'i'; $params[] = $filterCenter;
}
if ($filterFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterFrom)) {
    $where[] = 'DATE(l.createdAt) >= ?'; $types .= 's'; $params[] = $filterFrom;
}
if ($filterTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTo)) {
    $where[] = 'DATE(l.createdAt) <= ?'; $types .= 's'; $params[] = $filterTo;
}
$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$sql = "SELECT l.*,
               o.organization_name,
               ug.group_name,
               el.employee_name AS target_employee_name,
               el.employee_id AS target_emp_no,
               targetUl.user_id AS target_username
        FROM role_assignment_log l
        LEFT JOIN organization o ON l.organization_id = o.id
        LEFT JOIN user_group ug  ON l.role_id = ug.id
        LEFT JOIN employee_list el ON l.target_employee_id = el.id
        LEFT JOIN user_list targetUl ON l.target_user_id = targetUl.dataID
        $whereSql
        ORDER BY l.createdAt DESC, l.dataID DESC
        LIMIT 200";

$stmt = $con->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$ACTION_META = [
    'proposed' => ['label' => 'প্রস্তাবিত', 'icon' => 'tabler-send',       'bg' => '#e8f4ff', 'fg' => '#1c5aa3'],
    'approved' => ['label' => 'অনুমোদিত',  'icon' => 'tabler-check',      'bg' => '#dcfce7', 'fg' => '#166534'],
    'rejected' => ['label' => 'প্রত্যাখ্যাত', 'icon' => 'tabler-x',         'bg' => '#fee2e2', 'fg' => '#991b1b'],
    'replaced' => ['label' => 'প্রতিস্থাপিত', 'icon' => 'tabler-replace',   'bg' => '#f3e8ff', 'fg' => '#6b21a8'],
    'removed'  => ['label' => 'অপসারিত',   'icon' => 'tabler-trash',      'bg' => '#fee2e2', 'fg' => '#991b1b'],
];
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-list-details me-2 text-primary"></i>রোল কার্যক্রম লগ</h4>
        <div class="text-muted small mt-1 ms-1">
            <i class="ti tabler-info-circle me-1"></i>সব role assignment activity (proposal / approval / rejection ইত্যাদি) এর ইতিহাস
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
    border-bottom: 1px solid #eef0f5;
    padding: 0.85rem 1rem;
}
.audit-table td {
    font-size: 0.86rem; color: #2c2e3a;
    padding: 0.8rem 1rem; vertical-align: middle;
    border-bottom: 1px solid #f3f4fa;
}
.audit-table tr:last-child td { border-bottom: 0; }
.action-pill {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.72rem; font-weight: 600;
    padding: 0.25rem 0.6rem; border-radius: 999px;
}
.role-tag {
    display: inline-block; font-size: 0.72rem; font-weight: 600;
    padding: 0.18rem 0.55rem; border-radius: 999px;
}
.role-tag.r7 { background: #f0edff; color: #5648c4; }
.role-tag.r8 { background: #e6f7ee; color: #1a7e44; }
.audit-note { color: #5d6580; font-size: 0.8rem; max-width: 320px; }
.empty-log {
    padding: 3rem 2rem; text-align: center; color: #8a90a6;
}
.empty-log i { font-size: 2.5rem; color: #c4c9d6; display: block; margin-bottom: 0.5rem; }
.audit-actor { display: flex; flex-direction: column; gap: 0.05rem; }
.audit-actor .actor-name { font-weight: 600; color: #2c2e3a; }
.audit-actor .actor-meta { font-size: 0.72rem; color: #8a90a6; }
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
                    <?php foreach ($ACTION_META as $key => $meta): ?>
                    <option value="<?= $key ?>" <?= $filterAction === $key ? 'selected' : '' ?>>
                        <?= $meta['label'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label" for="role_id">রোল</label>
                <select id="role_id" name="role_id" class="form-select form-select-sm">
                    <option value="0">সকল</option>
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= $filterRole === (int)$r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['group_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label" for="centerID">কেন্দ্র</label>
                <select id="centerID" name="centerID" class="form-select form-select-sm">
                    <option value="0">সকল</option>
                    <?php foreach ($centers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filterCenter === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['organization_name']) ?>
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
            <div class="col-12 col-md-6 col-lg-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="ti tabler-filter"></i>
                </button>
                <a href="audit-log.php?menuslug=<?= $menuslug ?>" class="btn btn-sm btn-label-secondary" title="রিসেট" data-turbo="false">
                    <i class="ti tabler-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Audit log table -->
<div class="card audit-log-card shadow-sm border-0">
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="empty-log">
                <i class="ti tabler-search-off"></i>
                <div>কোনো লগ এন্ট্রি পাওয়া যায়নি</div>
                <small>ফিল্টার পরিবর্তন করে আবার চেষ্টা করুন</small>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table audit-table">
                    <thead>
                        <tr>
                            <th>তারিখ ও সময়</th>
                            <th>কার্যক্রম</th>
                            <th>রোল</th>
                            <th>কেন্দ্র</th>
                            <th>লক্ষ্য কর্মকর্তা</th>
                            <th>সম্পাদনকারী</th>
                            <th>মন্তব্য</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $l):
                        $meta = $ACTION_META[$l['action']] ?? ['label' => $l['action'], 'icon' => 'tabler-point', 'bg' => '#f3f4f8', 'fg' => '#5d6580'];
                        $roleClass = ((int)$l['role_id'] === 7) ? 'r7' : (((int)$l['role_id'] === 8) ? 'r8' : '');
                    ?>
                    <tr>
                        <td>
                            <div><?= date('d/m/Y', strtotime($l['createdAt'])) ?></div>
                            <small class="text-muted"><?= date('H:i', strtotime($l['createdAt'])) ?></small>
                        </td>
                        <td>
                            <span class="action-pill" style="background:<?= $meta['bg'] ?>; color:<?= $meta['fg'] ?>;">
                                <i class="ti <?= $meta['icon'] ?>"></i><?= htmlspecialchars($meta['label']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($l['group_name'])): ?>
                                <span class="role-tag <?= $roleClass ?>"><?= htmlspecialchars($l['group_name']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($l['organization_name'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($l['target_employee_name'])): ?>
                                <?php if (!empty($l['target_emp_no'])): ?>
                                    <span class="text-muted small">(<?= htmlspecialchars($l['target_emp_no']) ?>)</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($l['target_employee_name']) ?>
                                <?php if (!empty($l['target_username'])): ?>
                                    <div class="small text-muted">→ <code><?= htmlspecialchars($l['target_username']) ?></code></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="audit-actor">
                                <span class="actor-name"><?= htmlspecialchars($l['actor_name'] ?? '—') ?></span>
                            </div>
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
            <?php if (count($logs) >= 200): ?>
            <div class="text-center text-muted small py-2 border-top">
                <i class="ti tabler-info-circle me-1"></i>সর্বশেষ ২০০ টি এন্ট্রি দেখানো হচ্ছে — পুরোনো এন্ট্রি দেখতে তারিখ ফিল্টার ব্যবহার করুন
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
