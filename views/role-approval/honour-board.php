<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'honour-board');

// Center selector — default to user's own center if set, else first available
$selectedCenter = (int)($_GET['centerID'] ?? 0);

// Fetch all centers for the picker (alphabetically)
$centersStmt = $con->prepare("SELECT id, organization_name FROM organization WHERE deleted = 0 ORDER BY organization_name ASC");
$centersStmt->execute();
$centers = $centersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$centersStmt->close();

// Default fallback
if ($selectedCenter <= 0 && !empty($centers)) {
    // Try the current user's center first
    if (!empty($_SESSION['employeeID'])) {
        $myCenterStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
        $myCenterStmt->bind_param("i", $_SESSION['employeeID']);
        $myCenterStmt->execute();
        $myCenterRow = $myCenterStmt->get_result()->fetch_assoc();
        $myCenterStmt->close();
        $selectedCenter = (int)($myCenterRow['organization_id'] ?? 0);
    }
    if ($selectedCenter <= 0) {
        $selectedCenter = (int)$centers[0]['id'];
    }
}

// Center name for header
$centerName = '';
foreach ($centers as $c) {
    if ((int)$c['id'] === $selectedCenter) { $centerName = $c['organization_name']; break; }
}

// Roles to display (Regional Super Admin + Regional Op Admin)
$ROLES_DISPLAY = [
    7 => ['label' => 'Regional Super Admin', 'icon' => 'tabler-shield-star', 'accent' => '#6c5ce7', 'bg' => '#f0edff'],
    8 => ['label' => 'Regional Op. Admin',   'icon' => 'tabler-shield-half', 'accent' => '#1a7e44', 'bg' => '#e6f7ee'],
];

/**
 * For a role+center, return the tenure history (all assignment rows,
 * active + ended), joined with user/employee info.
 */
function fetchTenureHistory(mysqli $con, int $orgID, int $roleID): array {
    $stmt = $con->prepare(
        "SELECT uga.dataID AS assignment_id,
                uga.effective_from, uga.effective_to, uga.attachment, uga.proposal_id,
                ul.dataID AS user_dataID, ul.user_id, ul.full_name, ul.photo,
                el.employee_name, el.employee_id AS emp_no,
                jt.job_title_name
         FROM user_group_assignment uga
         INNER JOIN user_list ul ON uga.user_id = ul.dataID
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE uga.group_id = ?
           AND (el.organization_id = ? OR (el.id IS NULL AND ul.organization_id = ?))
         ORDER BY (uga.effective_to IS NULL) DESC, uga.effective_from DESC"
    );
    $stmt->bind_param("iii", $roleID, $orgID, $orgID);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$tenureData = [];
foreach ($ROLES_DISPLAY as $rid => $meta) {
    $tenureData[$rid] = fetchTenureHistory($con, $selectedCenter, $rid);
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-trophy me-2 text-primary"></i>রোল ইতিহাস (Honour Board)</h4>
        <div class="text-muted small mt-1 ms-1">
            <i class="ti tabler-info-circle me-1"></i>কোন কেন্দ্রে কে কবে থেকে কত তারিখ পর্যন্ত Regional Super Admin / Regional Op. Admin ছিলেন
        </div>
    </div>
</div>

<style>
.center-picker-card { border-radius: 0.75rem; }
.center-picker-card .card-body { padding: 1.15rem 1.5rem; }
.center-picker-card .form-label-inline {
    font-size: 0.86rem; color: #5d6580; font-weight: 600;
    margin-right: 1rem; white-space: nowrap;
}

.role-history-card {
    border-radius: 0.75rem;
    border: 1px solid #eef0f5 !important;
    margin-bottom: 1.25rem;
}
.role-history-card .card-body { padding: 1.25rem 1.5rem; }
.role-history-card .role-header {
    display: flex; align-items: center; gap: 0.75rem;
    margin-bottom: 1.15rem;
}
.role-history-card .role-icon-tile {
    width: 42px; height: 42px; border-radius: 0.55rem;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.role-history-card .role-name {
    font-size: 1.05rem; color: #2c2e3a; font-weight: 600; margin: 0;
}
.role-history-card .role-sub {
    font-size: 0.78rem; color: #8a90a6;
}

.tenure-timeline {
    position: relative; padding-left: 0;
}
.tenure-item {
    position: relative;
    display: flex; gap: 0.85rem; align-items: flex-start;
    padding-bottom: 1.1rem;
}
.tenure-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 19px; top: 44px; bottom: 0;
    width: 2px; background: #e7e9f4;
}
.tenure-avatar {
    flex-shrink: 0; width: 40px; height: 40px;
    border-radius: 50%; overflow: hidden;
    border: 2px solid #fff; box-shadow: 0 0 0 1px #e5e7eb;
    background: #f0edff; color: #5648c4;
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1rem;
    position: relative; z-index: 1;
}
.tenure-avatar img { width: 100%; height: 100%; object-fit: cover; }
.tenure-content {
    flex-grow: 1;
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    padding: 0.7rem 0.9rem;
}
.tenure-content.is-active {
    background: #f0fdf4; border-color: #bbf7d0; border-left: 3px solid #16a34a;
}
.tenure-name {
    font-weight: 600; color: #2c2e3a; font-size: 0.92rem;
    display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem;
}
.tenure-meta { font-size: 0.78rem; color: #5d6580; margin-top: 0.15rem; }
.tenure-period {
    margin-top: 0.4rem; font-size: 0.82rem; color: #4a5060;
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: #fff; border: 1px solid #eef0f5; border-radius: 999px;
    padding: 0.2rem 0.75rem;
}
.tenure-active-pill {
    font-size: 0.66rem; font-weight: 700;
    color: #16a34a; background: #dcfce7;
    padding: 2px 8px; border-radius: 999px;
    letter-spacing: 0.04em; text-transform: uppercase;
}
.tenure-attachment {
    margin-top: 0.45rem; display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.78rem; color: #5648c4; text-decoration: none;
}
.tenure-attachment:hover { text-decoration: underline; }
.no-history {
    padding: 2rem 1rem; text-align: center; color: #8a90a6; font-size: 0.88rem;
}
.no-history i { font-size: 2.25rem; color: #c4c9d6; display: block; margin-bottom: 0.5rem; }
</style>

<!-- Center picker -->
<div class="card center-picker-card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="get" class="d-flex align-items-center flex-wrap gap-2" data-turbo="false">
            <input type="hidden" name="menuslug" value="<?= $menuslug ?>">
            <label class="form-label-inline" for="centerID">
                <i class="ti tabler-building me-1"></i>কেন্দ্র
            </label>
            <select id="centerID" name="centerID" class="form-select select2" onchange="this.form.submit()" style="flex:1; min-width:240px;">
                <?php foreach ($centers as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $selectedCenter) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['organization_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-primary"><i class="ti tabler-refresh"></i></button></noscript>
        </form>
    </div>
</div>

<?php foreach ($ROLES_DISPLAY as $rid => $meta):
    $tenures = $tenureData[$rid];
?>
<div class="card role-history-card shadow-none">
    <div class="card-body">
        <div class="role-header">
            <span class="role-icon-tile" style="background:<?= $meta['bg'] ?>; color:<?= $meta['accent'] ?>;">
                <i class="ti <?= $meta['icon'] ?>"></i>
            </span>
            <div>
                <h6 class="role-name"><?= htmlspecialchars($meta['label']) ?></h6>
                <div class="role-sub"><?= htmlspecialchars($centerName) ?></div>
            </div>
            <div class="ms-auto small text-muted">মোট <?= count($tenures) ?> জন</div>
        </div>

        <?php if (empty($tenures)): ?>
            <div class="no-history">
                <i class="ti tabler-history-off"></i>
                এই role এ এখনো কেউ দায়িত্বে আসেননি
            </div>
        <?php else: ?>
            <div class="tenure-timeline">
                <?php foreach ($tenures as $t):
                    $isActive = empty($t['effective_to']);
                    $fromStr  = $t['effective_from'] ? date('d/m/Y', strtotime($t['effective_from'])) : '—';
                    $toStr    = $t['effective_to']   ? date('d/m/Y', strtotime($t['effective_to']))   : 'বর্তমান';
                    $displayName = trim($t['employee_name'] ?: $t['full_name'] ?: $t['user_id']);
                    $initial = mb_substr($displayName, 0, 1, 'UTF-8');
                ?>
                <div class="tenure-item">
                    <div class="tenure-avatar">
                        <?php if (!empty($t['photo'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($t['photo']) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($initial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="tenure-content<?= $isActive ? ' is-active' : '' ?>">
                        <div class="tenure-name">
                            <?php if (!empty($t['emp_no'])): ?>
                                <span class="text-muted">(<?= htmlspecialchars($t['emp_no']) ?>)</span>
                            <?php endif; ?>
                            <?= htmlspecialchars($displayName) ?>
                            <?php if ($isActive): ?>
                                <span class="tenure-active-pill">সক্রিয়</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($t['job_title_name'])): ?>
                        <div class="tenure-meta"><?= htmlspecialchars($t['job_title_name']) ?></div>
                        <?php endif; ?>
                        <div class="tenure-period">
                            <i class="ti tabler-calendar"></i>
                            <span><?= $fromStr ?></span>
                            <i class="ti tabler-arrow-narrow-right text-muted"></i>
                            <span><?= $toStr ?></span>
                        </div>
                        <?php if (!empty($t['attachment'])): ?>
                        <a class="tenure-attachment d-block" target="_blank"
                           href="<?= BASE_URL ?>/uploads/role-attachments/<?= rawurlencode($t['attachment']) ?>">
                            <i class="ti tabler-paperclip"></i>অফিস আদেশ
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
(function bootHonourBoard() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
        return setTimeout(bootHonourBoard, 20);
    }
    $('#centerID').select2({ width: '100%' });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
