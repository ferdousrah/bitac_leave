<?php
$pageTitle    = 'কেন্দ্রের বিস্তারিত';
$pageSubtitle = '';
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Re-query user for full data
$_stmt = mysqli_prepare($con,
    "SELECT user_id, full_name, employee_id, user_group_id, organization_id
     FROM user_list WHERE user_id = ?");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_stmt, 's', $_un);
mysqli_stmt_execute($_stmt);
$_full = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt)) ?: [];
mysqli_stmt_close($_stmt);
$getUserInfoQRW = array_merge($getUserInfoQRW ?? [], $_full);

// Access control:
// - Super Admin (group_id=1) → any center
// - Regional Super Admin (group_id=7) → only their own center
$_actorGroup = (int)($getUserInfoQRW['user_group_id'] ?? 0);
if ($_actorGroup !== 1 && $_actorGroup !== 7) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-lock me-2"></i>আপনার এই পেজ দেখার অনুমতি নেই</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$centerID = (int)($_GET['center_id'] ?? 0);
if ($centerID <= 0) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>অবৈধ কেন্দ্র আইডি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Regional Super Admin org gate: must match their own center
if ($_actorGroup === 7) {
    $_myCenter = (int)($getUserInfoQRW['organization_id'] ?? 0);
    if ($_myCenter === 0 && !empty($getUserInfoQRW['employee_id'])) {
        $_eq = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = " . (int)$getUserInfoQRW['employee_id'] . " LIMIT 1");
        if ($_eq && $_er = mysqli_fetch_assoc($_eq)) $_myCenter = (int)$_er['organization_id'];
    }
    if ($_myCenter !== $centerID) {
        echo '<div class="alert alert-danger m-4"><i class="ti tabler-lock me-2"></i>আপনি শুধু আপনার নিজের কেন্দ্রের বিস্তারিত দেখতে পারবেন</div>';
        require_once(__DIR__ . '/../../includes/footer_vuexy.php');
        exit;
    }
}

// Load center
$cStmt = mysqli_prepare($con, "SELECT * FROM organization WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($cStmt, 'i', $centerID);
mysqli_stmt_execute($cStmt);
$center = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt));
mysqli_stmt_close($cStmt);

if (!$center) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>কেন্দ্র খুঁজে পাওয়া যায়নি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// Section 1 — Center header stats
// ═══════════════════════════════════════════════════════════════════
$hStmt = mysqli_prepare($con,
    "SELECT
        COUNT(*) AS total_emp,
        COUNT(DISTINCT section_id) AS dept_count,
        SUM(employment_status = 1) AS active_emp
     FROM employee_list WHERE organization_id = ?");
mysqli_stmt_bind_param($hStmt, 'i', $centerID);
mysqli_stmt_execute($hStmt);
$headerStats = mysqli_fetch_assoc(mysqli_stmt_get_result($hStmt)) ?: [];
mysqli_stmt_close($hStmt);

// Last submission for this center
$lsStmt = mysqli_prepare($con,
    "SELECT MAX(submitDate) AS last_submit FROM leave_applications WHERE organization_id = ?");
mysqli_stmt_bind_param($lsStmt, 'i', $centerID);
mysqli_stmt_execute($lsStmt);
$lastSubmit = mysqli_fetch_assoc(mysqli_stmt_get_result($lsStmt))['last_submit'] ?? null;
mysqli_stmt_close($lsStmt);

// ═══════════════════════════════════════════════════════════════════
// Section 2 — Leadership Map (6 role groups)
// ═══════════════════════════════════════════════════════════════════
$ROLE_GROUPS = [
    10 => ['title' => 'বিভাগীয় প্রধান',              'icon' => 'tabler-user-star',     'color' => '#6c5ce7'],
    // Same group ids at the head office, but "Regional" is the wrong word there.
    7  => ['title' => $centerID === 4 ? 'Head Office Super Admin' : 'Regional Super Admin',
           'icon' => 'tabler-shield-star',   'color' => '#1a7e44'],
    8  => ['title' => $centerID === 4 ? 'Head Office Operational Admin' : 'Regional Op. Admin',
           'icon' => 'tabler-settings-cog',  'color' => '#0ea5e9'],
    12 => ['title' => 'Signatory (Lower Admin + Fin)','icon' => 'tabler-file-check',    'color' => '#f59e0b'],
    11 => ['title' => 'Signatory (Lower Admin)',      'icon' => 'tabler-file-text',     'color' => '#b8651a'],
    4  => ['title' => 'Signatory (Mid + Top)',        'icon' => 'tabler-crown',         'color' => '#dc3545'],
];

// Fetch users assigned to each role for this center (via user_group_assignment + employee_list)
$roleMembers = [];
foreach ($ROLE_GROUPS as $gid => $meta) $roleMembers[$gid] = [];

$rmStmt = mysqli_prepare($con,
    "SELECT uga.group_id, ul.dataID AS user_dataID, ul.full_name, ul.employee_id,
            el.id AS emp_id, el.employee_name, el.photo, jt.job_title_name, s.section_name
     FROM user_group_assignment uga
     INNER JOIN user_list ul   ON ul.dataID = uga.user_id
     LEFT  JOIN employee_list el ON ul.employee_id = el.id
     LEFT  JOIN job_title jt   ON el.designation = jt.id
     LEFT  JOIN sections s     ON el.section_id = s.id
     WHERE uga.group_id IN (4, 7, 8, 10, 11, 12)
       AND (uga.effective_to IS NULL OR uga.effective_to > NOW())
       AND el.organization_id = ?
     ORDER BY uga.group_id, el.employee_name");
mysqli_stmt_bind_param($rmStmt, 'i', $centerID);
mysqli_stmt_execute($rmStmt);
$rm = mysqli_stmt_get_result($rmStmt);
while ($r = mysqli_fetch_assoc($rm)) {
    $roleMembers[(int)$r['group_id']][] = $r;
}
mysqli_stmt_close($rmStmt);

// Helper: per-user pending leave count (canonical current-signatory filter)
function bitac_user_pending_leave($con, $empID) {
    $empID = (int)$empID;
    if ($empID <= 0) return 0;
    $q = mysqli_query($con,
        "SELECT COUNT(*) c
         FROM leave_data_for_approval lda
         INNER JOIN leave_applications la ON la.dataID = lda.leaveApplicationID
         WHERE lda.signatory = $empID
           AND lda.isApproved = 0
           AND la.status IN (0, 2)
           AND (lda.isSupervisor = 1 OR lda.isSentbyAdmin = 1)
           AND NOT EXISTS (
               SELECT 1 FROM leave_data_for_approval prev
               WHERE prev.leaveApplicationID = lda.leaveApplicationID
                 AND prev.serial < lda.serial
                 AND prev.isApproved = 0
           )");
    return ($q && $r = mysqli_fetch_assoc($q)) ? (int)$r['c'] : 0;
}

// Oldest pending submitDate per user (days)
function bitac_user_oldest_pending($con, $empID) {
    $empID = (int)$empID;
    if ($empID <= 0) return null;
    $q = mysqli_query($con,
        "SELECT MIN(la.submitDate) AS oldest
         FROM leave_data_for_approval lda
         INNER JOIN leave_applications la ON la.dataID = lda.leaveApplicationID
         WHERE lda.signatory = $empID
           AND lda.isApproved = 0
           AND la.status IN (0, 2)
           AND (lda.isSupervisor = 1 OR lda.isSentbyAdmin = 1)
           AND NOT EXISTS (
               SELECT 1 FROM leave_data_for_approval prev
               WHERE prev.leaveApplicationID = lda.leaveApplicationID
                 AND prev.serial < lda.serial
                 AND prev.isApproved = 0
           )");
    if (!$q) return null;
    $row = mysqli_fetch_assoc($q);
    if (empty($row['oldest'])) return null;
    $diff = (int)((time() - strtotime($row['oldest'])) / 86400);
    return $diff;
}

// ═══════════════════════════════════════════════════════════════════
// Section 3 — Leave Pipeline funnel
// ═══════════════════════════════════════════════════════════════════
// Pending at supervisor stage (waiting সুপারিশ)
$pSup = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(DISTINCT lda.leaveApplicationID) c
     FROM leave_data_for_approval lda
     INNER JOIN leave_applications la ON la.dataID = lda.leaveApplicationID
     WHERE la.organization_id = $centerID AND la.status = 0
       AND lda.isSupervisor = 1 AND lda.isApproved = 0"))['c'] ?? 0);

// Supervisor approved but not admin-forwarded yet
$pAdmin = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(DISTINCT la.dataID) c
     FROM leave_applications la
     WHERE la.organization_id = $centerID AND la.status = 0
       AND EXISTS (SELECT 1 FROM leave_data_for_approval lda
                   WHERE lda.leaveApplicationID = la.dataID AND lda.isSupervisor = 1 AND lda.isApproved = 1)
       AND NOT EXISTS (SELECT 1 FROM leave_data_for_approval lda2
                       WHERE lda2.leaveApplicationID = la.dataID AND lda2.isSentbyAdmin = 1)"))['c'] ?? 0);

// In chain — admin forwarded, awaiting signatory
$pChain = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(DISTINCT la.dataID) c
     FROM leave_applications la
     INNER JOIN leave_data_for_approval lda ON lda.leaveApplicationID = la.dataID
     WHERE la.organization_id = $centerID AND la.status = 0
       AND lda.isSentbyAdmin = 1 AND lda.isApproved = 0
       AND lda.isSupervisor = 0"))['c'] ?? 0);

// Overall approved/rejected (all time)
$pApproved = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM leave_applications
     WHERE organization_id = $centerID AND status = 1"))['c'] ?? 0);
$pRejected = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM leave_applications
     WHERE organization_id = $centerID AND status = 2"))['c'] ?? 0);
$pTotalAll = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM leave_applications
     WHERE organization_id = $centerID"))['c'] ?? 0);

// Bottleneck: which signatory has the most pending leave chain rows?
$bnQ = mysqli_query($con,
    "SELECT lda.signatory, el.employee_name, jt.job_title_name, COUNT(*) c
     FROM leave_data_for_approval lda
     INNER JOIN leave_applications la ON la.dataID = lda.leaveApplicationID
     LEFT JOIN employee_list el ON el.id = lda.signatory
     LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE la.organization_id = $centerID AND la.status = 0
       AND lda.isApproved = 0
       AND (lda.isSupervisor = 1 OR lda.isSentbyAdmin = 1)
       AND NOT EXISTS (SELECT 1 FROM leave_data_for_approval prev
                       WHERE prev.leaveApplicationID = lda.leaveApplicationID
                         AND prev.serial < lda.serial AND prev.isApproved = 0)
     GROUP BY lda.signatory, el.employee_name, jt.job_title_name
     ORDER BY c DESC
     LIMIT 1");
$bottleneck = ($bnQ && $bnRow = mysqli_fetch_assoc($bnQ)) ? $bnRow : null;

// ═══════════════════════════════════════════════════════════════════
// Section 4 — Multi-workflow snapshot
// ═══════════════════════════════════════════════════════════════════
$wfJoining = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM leave_joining_application WHERE organization_id = $centerID AND status = 0"))['c'] ?? 0);

$wfEdit = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM leave_edit_data WHERE organization_id = $centerID AND status = 0"))['c'] ?? 0);

// leave_addition_history: no organization_id; join through employee
$wfAdd = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM leave_addition_history lah
     INNER JOIN employee_list el ON el.id = lah.employeeID
     WHERE el.organization_id = $centerID AND lah.isApproved = 0"))['c'] ?? 0);

$wfDed = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM leave_deduction_history ldh
     INNER JOIN employee_list el ON el.id = ldh.employeeID
     WHERE el.organization_id = $centerID AND ldh.isApproved = 0"))['c'] ?? 0);

$wfPrev = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) c FROM previous_leave_deduction pld
     INNER JOIN employee_list el ON el.id = pld.employeeID
     WHERE el.organization_id = $centerID AND pld.isApproved = 0"))['c'] ?? 0);

// Increment proposals — count distinct pending chain rows for employees in this center
$wfInc = 0;
$incTbl = mysqli_query($con, "SHOW TABLES LIKE 'increment_data_for_approval'");
if ($incTbl && mysqli_num_rows($incTbl) > 0) {
    $wfInc = (int)(mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(DISTINCT ida.dataRef) c
         FROM increment_data_for_approval ida
         INNER JOIN employee_list el ON el.id = ida.employeeID
         WHERE el.organization_id = $centerID AND ida.isApproved = 0"))['c'] ?? 0);
}

// ═══════════════════════════════════════════════════════════════════
// Section 5 — Per-person desk: all role-assigned users in center
// ═══════════════════════════════════════════════════════════════════
// Distinct list of all users assigned to any of these 6 roles in this center
$deskUsers = [];
foreach ($ROLE_GROUPS as $gid => $meta) {
    foreach ($roleMembers[$gid] as $u) {
        $key = (int)$u['emp_id'];
        if (!isset($deskUsers[$key])) {
            $deskUsers[$key] = [
                'emp_id'         => (int)$u['emp_id'],
                'employee_name'  => $u['employee_name'],
                'employee_id'    => $u['employee_id'],
                'job_title_name' => $u['job_title_name'] ?? '',
                'section_name'   => $u['section_name'] ?? '',
                'roles'          => [],
            ];
        }
        $deskUsers[$key]['roles'][] = $meta['title'];
    }
}
// Compute pending count + oldest aging per user
foreach ($deskUsers as $k => $u) {
    $deskUsers[$k]['pending']    = bitac_user_pending_leave($con, $u['emp_id']);
    $deskUsers[$k]['oldest_days'] = bitac_user_oldest_pending($con, $u['emp_id']);
}
// Sort by pending desc, then aging desc
usort($deskUsers, function($a, $b) {
    if ($a['pending'] !== $b['pending']) return $b['pending'] <=> $a['pending'];
    return ($b['oldest_days'] ?? -1) <=> ($a['oldest_days'] ?? -1);
});

function be_num($n) { return strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']); }
?>

<style>
.co-wrap { max-width: 1400px; }
.co-section {
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}
.co-section-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #5d6580;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.co-section-title i { color: #6c5ce7; font-size: 1rem; }

/* Header bar */
.co-header {
    background: linear-gradient(135deg, #f8f7ff 0%, #fefefe 100%);
    border: 1px solid #ddd5f6;
    border-radius: 0.75rem;
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.co-header .center-title { font-weight: 700; color: #2c2e3a; font-size: 1.25rem; }
.co-header .center-stats { font-size: 0.86rem; color: #5d6580; margin-top: 0.3rem; }
.co-header .center-stats span { margin-right: 1rem; }

/* Leadership Map */
.leadership-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
.role-card {
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    padding: 1rem;
    background: #fafbfd;
}
.role-card .role-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
.role-card .role-icon {
    width: 32px; height: 32px; border-radius: 0.4rem;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.role-card .role-name { font-size: 0.82rem; font-weight: 600; color: #2c2e3a; flex: 1; }
.role-card .member { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0; border-top: 1px dashed #eef0f5; }
.role-card .member:first-of-type { border-top: 0; }
.role-card .member-info { flex: 1; min-width: 0; }
.role-card .member-name { font-size: 0.84rem; color: #2c2e3a; font-weight: 600; }
.role-card .member-sub { font-size: 0.72rem; color: #8a90a6; }
.role-card .member-pending {
    background: #fff3e1; color: #b8651a;
    font-size: 0.7rem; font-weight: 700;
    padding: 2px 8px; border-radius: 999px;
}
.role-card .member-pending.is-zero { background: #f0fdf4; color: #1a7e44; }
.role-card .role-empty { font-size: 0.78rem; color: #8a90a6; font-style: italic; padding: 0.5rem 0; }

/* Pipeline funnel */
.pipeline-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
.pipe-stage {
    background: #fafbfd; border: 1px solid #eef0f5;
    border-radius: 0.5rem; padding: 0.85rem; text-align: center;
}
.pipe-stage .pipe-label { font-size: 0.72rem; color: #5d6580; margin-bottom: 0.3rem; }
.pipe-stage .pipe-count { font-size: 1.5rem; font-weight: 700; line-height: 1; }
.pipe-stage.pipe-sup     { background: #fff8e1; border-color: #ffe082; }
.pipe-stage.pipe-sup     .pipe-count { color: #ff8f00; }
.pipe-stage.pipe-admin   { background: #e3f2fd; border-color: #90caf9; }
.pipe-stage.pipe-admin   .pipe-count { color: #1565c0; }
.pipe-stage.pipe-chain   { background: #f0edff; border-color: #ddd5f6; }
.pipe-stage.pipe-chain   .pipe-count { color: #5648c4; }
.pipe-stage.pipe-approved { background: #f0fdf4; border-color: #bbf7d0; }
.pipe-stage.pipe-approved .pipe-count { color: #1a7e44; }
.pipe-stage.pipe-rejected { background: #fff1f0; border-color: #f5c6c6; }
.pipe-stage.pipe-rejected .pipe-count { color: #dc3545; }

.bottleneck-bar {
    background: #fff1f0;
    border: 1px solid #f5c6c6;
    border-left: 3px solid #dc3545;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    font-size: 0.88rem;
    color: #6b2c2c;
}
.bottleneck-bar.is-clear { background: #f0fdf4; border-color: #bbf7d0; border-left-color: #1a7e44; color: #1a7e44; }

/* Workflow widgets */
.wf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.85rem; }
.wf-card {
    background: #fafbfd; border: 1px solid #eef0f5;
    border-radius: 0.5rem; padding: 0.85rem 1rem;
    display: flex; align-items: center; gap: 0.7rem;
}
.wf-card .wf-icon {
    width: 36px; height: 36px; border-radius: 0.45rem;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.05rem; color: #fff;
}
.wf-card .wf-meta { flex: 1; min-width: 0; }
.wf-card .wf-label { font-size: 0.74rem; color: #5d6580; }
.wf-card .wf-count { font-size: 1.1rem; font-weight: 700; color: #2c2e3a; }

/* Desk table */
.desk-table { width: 100%; border-collapse: collapse; }
.desk-table th, .desk-table td { padding: 0.65rem 0.85rem; border-bottom: 1px solid #eef0f5; font-size: 0.86rem; vertical-align: middle; }
.desk-table th { font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.04em; color: #5d6580; font-weight: 600; background: #fafbfd; text-align: left; }
.desk-table td.text-end, .desk-table th.text-end { text-align: right; }
.desk-table tr:hover td { background: #fdfcff; }
.desk-table .pending-pill {
    display: inline-block;
    background: #fff3e1; color: #b8651a;
    font-weight: 700; padding: 0.15em 0.7em;
    border-radius: 999px; font-size: 0.78rem;
}
.desk-table .pending-pill.zero { background: #f0fdf4; color: #1a7e44; }
.desk-table .age-cell { color: #6b2c2c; font-weight: 600; }
.desk-table .age-cell.old { color: #dc3545; }
.desk-table .age-cell.fresh { color: #1a7e44; font-weight: 500; }
.desk-table .role-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.desk-table .role-chip {
    background: #f0edff; color: #5648c4;
    font-size: 0.7rem; padding: 0.1em 0.55em;
    border-radius: 0.3rem;
}
</style>

<div class="co-wrap">

    <!-- Back button -->
    <div class="mb-3">
        <a href="<?= BASE_URL ?>/dashboard.php?menuslug=dashboard" class="btn btn-sm btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>সকল কেন্দ্র
        </a>
    </div>

    <!-- ───── Section 1: Header ───── -->
    <div class="co-header">
        <div>
            <div class="d-flex align-items-center gap-2">
                <i class="ti <?= $centerID === 4 ? 'tabler-building-skyscraper' : 'tabler-building' ?>" style="font-size:1.6rem; color:#6c5ce7;"></i>
                <span class="center-title"><?= htmlspecialchars($center['organization_name']) ?></span>
                <?php if ($centerID === 4): ?><span class="badge bg-label-warning ms-1">HQ</span><?php endif; ?>
            </div>
            <div class="center-stats mt-2">
                <span><i class="ti tabler-users me-1"></i><?= banglaNumber((int)($headerStats['active_emp'] ?? 0)) ?> জন সক্রিয় কর্মকর্তা/কর্মচারী</span>
                <span><i class="ti tabler-building me-1"></i><?= banglaNumber((int)($headerStats['dept_count'] ?? 0)) ?> টি শাখা/বিভাগ</span>
                <?php if ($lastSubmit): ?>
                    <span><i class="ti tabler-clock me-1"></i>সর্বশেষ আবেদন: <?= banglaNumber(date('d/m/Y', strtotime($lastSubmit))) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="text-end">
                <div style="font-size:0.74rem; color:#5d6580;">মোট আবেদন (সর্বমোট)</div>
                <div style="font-size:1.6rem; font-weight:700; color:#6c5ce7;"><?= banglaNumber($pTotalAll) ?></div>
            </div>
        </div>
    </div>

    <!-- ───── Section 2: Leadership Map ───── -->
    <div class="co-section">
        <div class="co-section-title"><i class="ti tabler-users-group"></i>দায়িত্বশীল ব্যক্তিবর্গ (Leadership Map)</div>
        <div class="leadership-grid">
            <?php foreach ($ROLE_GROUPS as $gid => $meta):
                $members = $roleMembers[$gid] ?? [];
            ?>
            <div class="role-card">
                <div class="role-header">
                    <span class="role-icon" style="background:<?= $meta['color'] ?>22; color:<?= $meta['color'] ?>;">
                        <i class="ti <?= $meta['icon'] ?>"></i>
                    </span>
                    <span class="role-name"><?= htmlspecialchars($meta['title']) ?></span>
                    <?php if (!empty($members)): ?>
                        <span class="badge bg-label-secondary"><?= be_num(count($members)) ?> জন</span>
                    <?php endif; ?>
                </div>
                <?php if (empty($members)): ?>
                    <div class="role-empty"><i class="ti tabler-user-off me-1"></i>নিযুক্ত নন</div>
                <?php else: foreach ($members as $m):
                    $pending = bitac_user_pending_leave($con, $m['emp_id']);
                ?>
                    <div class="member">
                        <div class="member-info">
                            <div class="member-name"><?= htmlspecialchars($m['employee_name']) ?></div>
                            <div class="member-sub">
                                <?= htmlspecialchars($m['job_title_name']) ?>
                                <?php if (!empty($m['section_name'])): ?>
                                    • <?= htmlspecialchars($m['section_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="member-pending <?= $pending === 0 ? 'is-zero' : '' ?>">
                            <?= be_num($pending) ?>
                        </span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ───── Section 3: Leave Pipeline funnel ───── -->
    <div class="co-section">
        <div class="co-section-title"><i class="ti tabler-arrow-bar-to-right"></i>ছুটির আবেদন পাইপলাইন (সর্বমোট)</div>
        <div class="pipeline-row">
            <div class="pipe-stage pipe-sup">
                <div class="pipe-label">সুপারিশের অপেক্ষায়</div>
                <div class="pipe-count"><?= be_num($pSup) ?></div>
            </div>
            <div class="pipe-stage pipe-admin">
                <div class="pipe-label">Admin forward অপেক্ষায়</div>
                <div class="pipe-count"><?= be_num($pAdmin) ?></div>
            </div>
            <div class="pipe-stage pipe-chain">
                <div class="pipe-label">চেইন অনুমোদনে</div>
                <div class="pipe-count"><?= be_num($pChain) ?></div>
            </div>
            <div class="pipe-stage pipe-approved">
                <div class="pipe-label">অনুমোদিত</div>
                <div class="pipe-count"><?= be_num($pApproved) ?></div>
            </div>
            <div class="pipe-stage pipe-rejected">
                <div class="pipe-label">প্রত্যাখ্যাত</div>
                <div class="pipe-count"><?= be_num($pRejected) ?></div>
            </div>
        </div>

        <?php if ($bottleneck && (int)$bottleneck['c'] > 0): ?>
            <div class="bottleneck-bar">
                <i class="ti tabler-alert-triangle me-1"></i>
                <strong>Bottleneck:</strong>
                <strong><?= htmlspecialchars($bottleneck['employee_name']) ?></strong>
                <?php if (!empty($bottleneck['job_title_name'])): ?>(<?= htmlspecialchars($bottleneck['job_title_name']) ?>)<?php endif; ?>
                — তাঁর desk-এ <strong><?= be_num($bottleneck['c']) ?> টি</strong> পেন্ডিং
            </div>
        <?php else: ?>
            <div class="bottleneck-bar is-clear">
                <i class="ti tabler-circle-check me-1"></i>কোনো bottleneck নেই — সব আবেদন স্বাভাবিক গতিতে এগোচ্ছে
            </div>
        <?php endif; ?>
    </div>

    <!-- ───── Section 4: Multi-workflow snapshot ───── -->
    <div class="co-section">
        <div class="co-section-title"><i class="ti tabler-list-check"></i>অন্যান্য workflow (pending)</div>
        <div class="wf-grid">
            <div class="wf-card">
                <span class="wf-icon" style="background:#1a7e44;"><i class="ti tabler-door-enter"></i></span>
                <div class="wf-meta">
                    <div class="wf-label">যোগদান পত্র</div>
                    <div class="wf-count"><?= be_num($wfJoining) ?></div>
                </div>
            </div>
            <div class="wf-card">
                <span class="wf-icon" style="background:#6c5ce7;"><i class="ti tabler-pencil"></i></span>
                <div class="wf-meta">
                    <div class="wf-label">ছুটি সংশোধন</div>
                    <div class="wf-count"><?= be_num($wfEdit) ?></div>
                </div>
            </div>
            <div class="wf-card">
                <span class="wf-icon" style="background:#0ea5e9;"><i class="ti tabler-plus"></i></span>
                <div class="wf-meta">
                    <div class="wf-label">ছুটি সংযোজন</div>
                    <div class="wf-count"><?= be_num($wfAdd) ?></div>
                </div>
            </div>
            <div class="wf-card">
                <span class="wf-icon" style="background:#dc3545;"><i class="ti tabler-minus"></i></span>
                <div class="wf-meta">
                    <div class="wf-label">ছুটি কর্তন</div>
                    <div class="wf-count"><?= be_num($wfDed) ?></div>
                </div>
            </div>
            <div class="wf-card">
                <span class="wf-icon" style="background:#f59e0b;"><i class="ti tabler-history"></i></span>
                <div class="wf-meta">
                    <div class="wf-label">পূর্ববর্তী ছুটি</div>
                    <div class="wf-count"><?= be_num($wfPrev) ?></div>
                </div>
            </div>
            <div class="wf-card">
                <span class="wf-icon" style="background:#b8651a;"><i class="ti tabler-currency-taka"></i></span>
                <div class="wf-meta">
                    <div class="wf-label">বেতন বৃদ্ধি</div>
                    <div class="wf-count"><?= be_num($wfInc) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ───── Section 5: Per-person desk table ───── -->
    <div class="co-section">
        <div class="co-section-title"><i class="ti tabler-clipboard-list"></i>দায়িত্বশীলদের পেন্ডিং কাজ</div>
        <?php if (empty($deskUsers)): ?>
            <div class="alert alert-info mb-0">এই কেন্দ্রে কোনো role-এ নিযুক্ত ব্যক্তি নেই।</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="desk-table">
                <thead>
                    <tr>
                        <th>কর্মকর্তা</th>
                        <th>রোল</th>
                        <th>বিভাগ</th>
                        <th class="text-end">পেন্ডিং কাজ</th>
                        <th class="text-end">সবচেয়ে পুরনো</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deskUsers as $u):
                    $age = $u['oldest_days'];
                    $ageCls = $age === null ? 'fresh' : (($age >= 7) ? 'old' : '');
                ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($u['employee_name']) ?></div>
                            <?php if (!empty($u['job_title_name'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($u['job_title_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="role-chips">
                                <?php foreach (array_unique($u['roles']) as $r): ?>
                                    <span class="role-chip"><?= htmlspecialchars($r) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($u['section_name'] ?: '—') ?></td>
                        <td class="text-end">
                            <span class="pending-pill <?= $u['pending'] === 0 ? 'zero' : '' ?>">
                                <?= be_num($u['pending']) ?>
                            </span>
                        </td>
                        <td class="text-end age-cell <?= $ageCls ?>">
                            <?= $age === null ? '—' : (be_num($age) . ' দিন') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
