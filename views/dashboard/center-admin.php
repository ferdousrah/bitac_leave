<?php
// ───────────────────────────────────────────────────────────
// Center Admin Dashboard — operational/oversight focus
// Uses $con, $getUserInfoQRW, $getEmployeeDetailsQRW from header_vuexy.php
// ───────────────────────────────────────────────────────────

$_orgId = (int)($getUserInfoQRW['organization_id'] ?? 0);
$_today = date('Y-m-d');
$_weekStart  = date('Y-m-d', strtotime('-6 days'));
$_monthStart = date('Y-m-01');
$_monthEnd   = date('Y-m-t');

// Center name
$_orgName = $getEmployeeDetailsQRW['organization_name'] ?? 'BITAC';

// helper: bn digits
$_bnNum = function($n) {
    return function_exists('banglaNumber') ? banglaNumber((string)$n) : (string)$n;
};

// ── Total employees in this center ────────────────────────────────────
$_q = mysqli_prepare($con, "SELECT COUNT(*) c FROM employee_list WHERE organization_id=? AND employment_status=1");
mysqli_stmt_bind_param($_q, 'i', $_orgId);
mysqli_stmt_execute($_q);
$_totalEmp = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_q))['c'] ?? 0);
mysqli_stmt_close($_q);

// ── Currently on leave (today) ────────────────────────────────────────
$_q = mysqli_prepare($con,
    "SELECT COUNT(*) c FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     WHERE el.organization_id=? AND la.status=1
       AND ? BETWEEN la.dateFrom AND la.dateTo");
mysqli_stmt_bind_param($_q, 'is', $_orgId, $_today);
mysqli_stmt_execute($_q);
$_onLeaveToday = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_q))['c'] ?? 0);
mysqli_stmt_close($_q);

$_atOffice = max(0, $_totalEmp - $_onLeaveToday);

// ── Pending approvals in this center ──────────────────────────────────
$_q = mysqli_prepare($con,
    "SELECT COUNT(DISTINCT la.dataID) c FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     WHERE el.organization_id=? AND la.status IN (0,2)");
mysqli_stmt_bind_param($_q, 'i', $_orgId);
mysqli_stmt_execute($_q);
$_pendingCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_q))['c'] ?? 0);
mysqli_stmt_close($_q);

// ── Approved this month ──────────────────────────────────────────────
$_q = mysqli_prepare($con,
    "SELECT COUNT(*) c FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     WHERE el.organization_id=? AND la.status=1
       AND la.approvedDateFrom BETWEEN ? AND ?");
mysqli_stmt_bind_param($_q, 'iss', $_orgId, $_monthStart, $_monthEnd);
mysqli_stmt_execute($_q);
$_approvedMonth = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_q))['c'] ?? 0);
mysqli_stmt_close($_q);

// ── New applications this week ────────────────────────────────────────
$_q = mysqli_prepare($con,
    "SELECT COUNT(*) c FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     WHERE el.organization_id=? AND la.submitDate >= ?");
mysqli_stmt_bind_param($_q, 'is', $_orgId, $_weekStart);
mysqli_stmt_execute($_q);
$_weekNew = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_q))['c'] ?? 0);
mysqli_stmt_close($_q);

// ── Today on leave list (top 8) ───────────────────────────────────────
$_q = mysqli_prepare($con,
    "SELECT el.employee_name, el.id AS emp_id, jt.job_title_name, s.section_name,
            la.dateFrom, la.dateTo, lt.leaveTitle, lt.leaveID
     FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     LEFT JOIN job_title jt ON jt.id = el.designation
     LEFT JOIN sections s ON s.id = el.section_id
     LEFT JOIN leave_types lt ON lt.leaveID = la.leaveTypeInTwo
     WHERE el.organization_id=? AND la.status=1
       AND ? BETWEEN la.dateFrom AND la.dateTo
     ORDER BY la.dateTo ASC
     LIMIT 8");
mysqli_stmt_bind_param($_q, 'is', $_orgId, $_today);
mysqli_stmt_execute($_q);
$_onLeaveListRes = mysqli_stmt_get_result($_q);
$_onLeaveList = [];
while ($r = mysqli_fetch_assoc($_onLeaveListRes)) $_onLeaveList[] = $r;
mysqli_stmt_close($_q);

// ── Upcoming returns (next 7 days) ────────────────────────────────────
$_nextWeek = date('Y-m-d', strtotime('+7 days'));
$_q = mysqli_prepare($con,
    "SELECT el.employee_name, la.dateTo
     FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     WHERE el.organization_id=? AND la.status=1
       AND la.dateTo BETWEEN ? AND ?
     ORDER BY la.dateTo ASC
     LIMIT 6");
mysqli_stmt_bind_param($_q, 'iss', $_orgId, $_today, $_nextWeek);
mysqli_stmt_execute($_q);
$_upcomingRes = mysqli_stmt_get_result($_q);
$_upcomingList = [];
while ($r = mysqli_fetch_assoc($_upcomingRes)) $_upcomingList[] = $r;
mysqli_stmt_close($_q);

// ── Monthly trend (last 6 months) ─────────────────────────────────────
$_monthlyTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01', strtotime("-$i month"));
    $end   = date('Y-m-t',  strtotime("-$i month"));
    $monthLabel = ['', 'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে'][(int)date('n', strtotime($start))];
    $_q = mysqli_prepare($con,
        "SELECT COUNT(*) c FROM leave_applications la
         INNER JOIN employee_list el ON el.id = la.applicantID
         WHERE el.organization_id=? AND la.status=1
           AND la.approvedDateFrom BETWEEN ? AND ?");
    mysqli_stmt_bind_param($_q, 'iss', $_orgId, $start, $end);
    mysqli_stmt_execute($_q);
    $cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_q))['c'] ?? 0);
    mysqli_stmt_close($_q);
    $_monthlyTrend[] = ['label' => $monthLabel, 'count' => $cnt];
}
$_maxTrend = max(1, max(array_column($_monthlyTrend, 'count')));

// ── Leave types breakdown (this month) ────────────────────────────────
$_q = mysqli_prepare($con,
    "SELECT lt.leaveTitle, COUNT(*) c
     FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     LEFT JOIN leave_types lt ON lt.leaveID = la.leaveTypeInTwo
     WHERE el.organization_id=? AND la.status=1
       AND la.approvedDateFrom BETWEEN ? AND ?
     GROUP BY la.leaveTypeInTwo
     ORDER BY c DESC");
mysqli_stmt_bind_param($_q, 'iss', $_orgId, $_monthStart, $_monthEnd);
mysqli_stmt_execute($_q);
$_typeBreakdownRes = mysqli_stmt_get_result($_q);
$_typeBreakdown = [];
$_typeTotalCount = 0;
while ($r = mysqli_fetch_assoc($_typeBreakdownRes)) {
    $_typeBreakdown[] = $r;
    $_typeTotalCount += (int)$r['c'];
}
mysqli_stmt_close($_q);

// ── Section-wise breakdown (this month) ───────────────────────────────
$_q = mysqli_prepare($con,
    "SELECT s.section_name, COUNT(*) c
     FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     LEFT JOIN sections s ON s.id = el.section_id
     WHERE el.organization_id=? AND la.status=1
       AND la.approvedDateFrom BETWEEN ? AND ?
     GROUP BY el.section_id
     ORDER BY c DESC
     LIMIT 6");
mysqli_stmt_bind_param($_q, 'iss', $_orgId, $_monthStart, $_monthEnd);
mysqli_stmt_execute($_q);
$_sectionRes = mysqli_stmt_get_result($_q);
$_sectionList = [];
while ($r = mysqli_fetch_assoc($_sectionRes)) $_sectionList[] = $r;
mysqli_stmt_close($_q);
$_sectionCounts = array_column($_sectionList, 'c');
$_maxSection = !empty($_sectionCounts) ? max(1, max($_sectionCounts)) : 1;

// ── Top leave takers this year ────────────────────────────────────────
$_yearStart = date('Y-01-01');
$_q = mysqli_prepare($con,
    "SELECT el.employee_name, s.section_name, SUM(la.approvedDays) total_days
     FROM leave_applications la
     INNER JOIN employee_list el ON el.id = la.applicantID
     LEFT JOIN sections s ON s.id = el.section_id
     WHERE el.organization_id=? AND la.status=1
       AND la.approvedDateFrom >= ?
     GROUP BY la.applicantID
     ORDER BY total_days DESC
     LIMIT 5");
mysqli_stmt_bind_param($_q, 'is', $_orgId, $_yearStart);
mysqli_stmt_execute($_q);
$_topTakersRes = mysqli_stmt_get_result($_q);
$_topTakers = [];
while ($r = mysqli_fetch_assoc($_topTakersRes)) $_topTakers[] = $r;
mysqli_stmt_close($_q);

// helper: leave-type badge color
$_badgeColor = function($leaveID) {
    $map = [
        1  => ['bg'=>'#e8eef9','color'=>'#5b7396'], // পূর্ণ গড়
        2  => ['bg'=>'#fbeded','color'=>'#c97777'], // অর্ধ-গড়
        5  => ['bg'=>'#efeaf5','color'=>'#7c6ba4'], // সংগনিরোধ
        6  => ['bg'=>'#fbe7eb','color'=>'#b46578'], // প্রসূতি
        7  => ['bg'=>'#efeaf5','color'=>'#7c6ba4'], // ঐচ্ছিক
        8  => ['bg'=>'#e8f5ee','color'=>'#5fa885'], // নৈমিত্তিক
        9  => ['bg'=>'#e8eef9','color'=>'#5b7396'], // বিনা বেতনে
        10 => ['bg'=>'#faf2dc','color'=>'#a47b54'], // অসাধারণ
        19 => ['bg'=>'#fbeded','color'=>'#c97777'], // অক্ষমতাজনিত
    ];
    return $map[(int)$leaveID] ?? ['bg'=>'#f3f4f6','color'=>'#4b5563'];
};

// helper: avatar initial (Bn)
$_avatarInit = function($name) {
    $name = trim($name);
    if (!$name) return '?';
    return mb_substr($name, 0, 1, 'UTF-8');
};

// helper: format Bn date "৫ মে"
$_bnDate = function($date) {
    if (!$date || $date === '0000-00-00') return '—';
    $months = ['', 'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে'];
    $d = (int)date('j', strtotime($date));
    $m = (int)date('n', strtotime($date));
    return (function_exists('banglaNumber') ? banglaNumber($d) : $d) . ' ' . $months[$m];
};
?>

<style>
.dash-hero {
    background: linear-gradient(135deg, #5b7396 0%, #7d9bc5 100%);
    color: #fff; border-radius: 14px; padding: 22px 26px;
    margin-bottom: 20px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 14px;
    box-shadow: 0 4px 20px rgba(91, 115, 150, 0.15);
}
.dash-hero h4 { color:#fff; margin:0 0 4px; font-weight:700; }
.dash-hero .role-pill {
    background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 99px;
    font-size: 0.78rem; font-weight: 600;
}
.dash-hero .hero-meta { font-size:0.85rem; opacity:0.9; }
.hero-stats { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.hero-stat {
    background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.18);
    border-radius: 10px; padding: 10px 16px; text-align: center; min-width: 110px;
    backdrop-filter: blur(4px);
}
.hero-stat .hs-num { font-size: 1.5rem; font-weight: 700; line-height: 1; }
.hero-stat .hs-label { font-size: 0.74rem; opacity: 0.88; margin-top: 4px; }
.hero-stat.is-emphasis {
    background: rgba(255,255,255,0.95); color: #4a5b78;
    border-color: rgba(255,255,255,0.95);
}
.hero-stat.is-emphasis .hs-label { opacity: 0.75; color:#4a5b78; }

.kpi-card {
    background:#fff; border-radius:12px; padding:18px;
    box-shadow:0 2px 8px rgba(0,0,0,0.06);
    transition: all .2s ease;
    height:100%; position:relative; overflow:hidden;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.1); }
.kpi-card .kpi-icon {
    width: 48px; height: 48px; border-radius: 10px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:1.4rem; margin-bottom:10px;
}
.kpi-card .kpi-num { font-size:1.8rem; font-weight:700; color:#1f2937; line-height:1; }
.kpi-card .kpi-label { font-size:0.82rem; color:#6b7280; margin-top:4px; }
.kpi-card .kpi-action { font-size:0.75rem; color:#7d9bc5; margin-top:8px; display:inline-block; text-decoration:none; }
.kpi-card.k-red    .kpi-icon { background:#fef0f0; color:#e76f6f; }
.kpi-card.k-amber  .kpi-icon { background:#fef7e6; color:#d4a056; }
.kpi-card.k-green  .kpi-icon { background:#e8f5ee; color:#5fa885; }
.kpi-card.k-blue   .kpi-icon { background:#e8eef9; color:#6c8cc4; }

.section-card {
    background:#fff; border-radius:12px; padding:18px;
    box-shadow:0 2px 8px rgba(0,0,0,0.06);
    margin-bottom:18px;
    height: calc(100% - 18px);
    display: flex; flex-direction: column;
}
.section-card .sc-title {
    flex-shrink: 0;
    font-weight:600; color:#1f2937; margin-bottom:14px;
    display:flex; align-items:center; justify-content:space-between;
    gap:8px; font-size:0.95rem;
}
.section-card .sc-title .sc-link {
    font-size:0.78rem; color:#7d9bc5; text-decoration:none; font-weight:500;
}

.emp-row {
    display:flex; align-items:center; gap:12px;
    padding:10px 6px; border-bottom:1px solid #f3f4f6; font-size:0.85rem;
}
.emp-row:last-child { border-bottom:none; }
.emp-row .emp-avatar {
    width:36px; height:36px; border-radius:50%;
    background:linear-gradient(135deg, #c7d2fe 0%, #a5b4fc 100%);
    color:#3730a3;
    display:inline-flex; align-items:center; justify-content:center;
    font-weight:600; font-size:0.8rem; flex-shrink:0;
}
.emp-row .emp-info { flex:1; min-width:0; }
.emp-row .emp-name { font-weight:600; color:#1f2937; }
.emp-row .emp-meta { font-size:0.75rem; color:#6b7280; }
.emp-row .emp-pill {
    font-size:0.7rem; padding:2px 8px; border-radius:4px;
    font-weight:500; flex-shrink:0;
}
.emp-row .emp-return { font-size:0.74rem; color:#5fa885; flex-shrink:0; }

.donut {
    width:140px; height:140px; border-radius:50%;
    margin:8px auto;
    display:flex; align-items:center; justify-content:center;
}
.donut::after {
    content:""; width:84px; height:84px; background:#fff; border-radius:50%;
}
.donut-legend { font-size:0.78rem; }
.donut-legend .dl-row { display:flex; align-items:center; gap:8px; padding:4px 0; }
.donut-legend .dl-dot { width:10px; height:10px; border-radius:2px; flex-shrink:0; }
.donut-empty { text-align:center; color:#9ca3af; font-size:0.84rem; padding:30px 10px; }

.bar-trend { display:flex; align-items:flex-end; gap:6px; height:140px; padding-top:8px; }
.bar-trend .bt-bar {
    flex:1;
    background:linear-gradient(180deg, #8da9ce 0%, #5b7396 100%);
    border-radius:4px 4px 0 0;
    position:relative; min-height:4px;
}
.bar-trend .bt-bar::after {
    content: attr(data-val);
    position:absolute; top:-18px; left:50%;
    transform:translateX(-50%);
    font-size:0.7rem; color:#1f2937; font-weight:600;
}
.bar-trend .bt-label {
    text-align:center; font-size:0.68rem; color:#6b7280; margin-top:6px;
}

.section-bar {
    display:flex; align-items:center; gap:10px; padding:8px 0;
}
.section-bar .sb-name { flex:0 0 130px; font-size:0.84rem; color:#1f2937; font-weight:500; }
.section-bar .sb-track { flex:1; height:8px; background:#f3f4f6; border-radius:99px; overflow:hidden; }
.section-bar .sb-fill {
    height:100%; border-radius:99px;
    background: linear-gradient(90deg, #8b9dc9 0%, #a89cc4 100%);
}
.section-bar .sb-num { flex:0 0 60px; text-align:right; font-size:0.82rem; color:#7382a6; font-weight:600; }

.quick-action {
    display:flex; align-items:center; gap:10px;
    padding:14px 12px; background:#f9fafb;
    border:1px solid #e5e7eb; border-radius:10px;
    cursor:pointer; transition: all .15s ease;
    text-decoration:none; color:#1f2937;
}
.quick-action:hover {
    background:#fff; border-color:#7d9bc5;
    transform:translateY(-1px); color:#7d9bc5;
}
.quick-action i { font-size:1.2rem; color:#7d9bc5; }
.quick-action span { font-size:0.85rem; font-weight:500; }

.empty-msg { color:#9ca3af; font-size:0.85rem; text-align:center; padding:18px 8px; }
</style>

<!-- Hero -->
<div class="dash-hero">
    <div>
        <span class="role-pill"><i class="ti tabler-shield-check me-1"></i>সেন্টার অ্যাডমিন</span>
        <h4 class="mt-2">স্বাগতম, <?= htmlspecialchars($getUserInfoQRW['full_name'] ?? '') ?></h4>
        <div class="hero-meta"><?= htmlspecialchars($_orgName) ?> · <?= ShowBangladeshDate() ?></div>
    </div>
    <div class="hero-stats">
        <div class="hero-stat is-emphasis">
            <div class="hs-num"><?= $_bnNum($_totalEmp) ?></div>
            <div class="hs-label">মোট কর্মচারী</div>
        </div>
        <div class="hero-stat">
            <div class="hs-num"><?= $_bnNum($_atOffice) ?></div>
            <div class="hs-label">কর্মস্থলে</div>
        </div>
        <div class="hero-stat">
            <div class="hs-num"><?= $_bnNum($_onLeaveToday) ?></div>
            <div class="hs-label">ছুটিতে</div>
        </div>
    </div>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card k-amber">
            <span class="kpi-icon"><i class="ti tabler-clock"></i></span>
            <div class="kpi-num"><?= $_bnNum($_pendingCount) ?></div>
            <div class="kpi-label">approval-এর অপেক্ষায়</div>
            <a href="<?= BASE_URL ?>/all_leave_application.php" class="kpi-action">সব দেখুন →</a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card k-red">
            <span class="kpi-icon"><i class="ti tabler-user-off"></i></span>
            <div class="kpi-num"><?= $_bnNum($_onLeaveToday) ?></div>
            <div class="kpi-label">আজ ছুটিতে আছেন</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card k-green">
            <span class="kpi-icon"><i class="ti tabler-check"></i></span>
            <div class="kpi-num"><?= $_bnNum($_approvedMonth) ?></div>
            <div class="kpi-label">এই মাসে অনুমোদিত</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card k-blue">
            <span class="kpi-icon"><i class="ti tabler-file-text"></i></span>
            <div class="kpi-num"><?= $_bnNum($_weekNew) ?></div>
            <div class="kpi-label">এই সপ্তাহে নতুন আবেদন</div>
        </div>
    </div>
</div>

<!-- Today on leave + Upcoming returns -->
<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-user-off me-1" style="color:#c97777;"></i>আজ ছুটিতে আছেন</span>
                <a href="<?= BASE_URL ?>/all_leave_application.php" class="sc-link">সম্পূর্ণ তালিকা →</a>
            </div>
            <?php if (empty($_onLeaveList)): ?>
                <div class="empty-msg">আজ কেউ ছুটিতে নেই 🎉</div>
            <?php else: foreach ($_onLeaveList as $row):
                $color = $_badgeColor($row['leaveID']); ?>
                <div class="emp-row">
                    <span class="emp-avatar"><?= htmlspecialchars($_avatarInit($row['employee_name'])) ?></span>
                    <div class="emp-info">
                        <div class="emp-name"><?= htmlspecialchars($row['employee_name']) ?></div>
                        <div class="emp-meta"><?= htmlspecialchars($row['job_title_name'] ?? '') ?><?php if (!empty($row['section_name'])): ?> · <?= htmlspecialchars($row['section_name']) ?><?php endif; ?></div>
                    </div>
                    <span class="emp-pill" style="background:<?= $color['bg'] ?>;color:<?= $color['color'] ?>;"><?= htmlspecialchars($row['leaveTitle'] ?? '') ?></span>
                    <span class="emp-return">ফিরবেন: <?= $_bnDate($row['dateTo']) ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-calendar-check me-1" style="color:#5fa885;"></i>আসন্ন ফেরত</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">পরের ৭ দিনে</span>
            </div>
            <?php if (empty($_upcomingList)): ?>
                <div class="empty-msg">পরের ৭ দিনে কারো ফেরত নেই</div>
            <?php else: foreach ($_upcomingList as $row): ?>
                <div class="emp-row">
                    <span class="emp-avatar"><?= htmlspecialchars($_avatarInit($row['employee_name'])) ?></span>
                    <div class="emp-info">
                        <div class="emp-name"><?= htmlspecialchars($row['employee_name']) ?></div>
                        <div class="emp-meta"><?= $_bnDate($row['dateTo']) ?></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Charts: Monthly trend + Donut -->
<div class="row g-3">
    <div class="col-12 col-md-7">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-chart-bar me-1" style="color:#7d9bc5;"></i>মাসিক অনুমোদনের প্রবণতা</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">সর্বশেষ ৬ মাস</span>
            </div>
            <div class="bar-trend">
                <?php foreach ($_monthlyTrend as $m):
                    $h = max(4, round(($m['count'] / $_maxTrend) * 100)); ?>
                    <div style="flex:1;display:flex;flex-direction:column;">
                        <div class="bt-bar" style="height:<?= $h ?>%;" data-val="<?= $_bnNum($m['count']) ?>"></div>
                        <div class="bt-label"><?= $m['label'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-5">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-chart-donut me-1" style="color:#a89cc4;"></i>ছুটির ধরণ অনুযায়ী</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">এই মাসে</span>
            </div>
            <?php if ($_typeTotalCount === 0): ?>
                <div class="donut-empty">এই মাসে কোনো অনুমোদিত ছুটি নেই</div>
            <?php else:
                $donutColors = ['#7d9bc5','#d4a056','#7fb59c','#a89cc4','#c97777','#8b9dc9'];
                $cumPct = 0; $stops = [];
                foreach ($_typeBreakdown as $i => $t) {
                    $pct = ($t['c'] / $_typeTotalCount) * 100;
                    $start = $cumPct; $cumPct += $pct;
                    $stops[] = $donutColors[$i % count($donutColors)] . ' ' . round($start, 1) . '% ' . round($cumPct, 1) . '%';
                }
                $gradient = 'conic-gradient(' . implode(', ', $stops) . ')';
            ?>
                <div class="row align-items-center">
                    <div class="col-6"><div class="donut" style="background:<?= htmlspecialchars($gradient) ?>;"></div></div>
                    <div class="col-6">
                        <div class="donut-legend">
                            <?php foreach ($_typeBreakdown as $i => $t):
                                $pct = round(($t['c'] / $_typeTotalCount) * 100); ?>
                                <div class="dl-row">
                                    <span class="dl-dot" style="background:<?= $donutColors[$i % count($donutColors)] ?>;"></span>
                                    <?= htmlspecialchars($t['leaveTitle'] ?? 'অজানা') ?> <?= $_bnNum($pct) ?>%
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Section breakdown + Top takers -->
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-building me-1" style="color:#8b9dc9;"></i>শাখা অনুযায়ী ছুটি</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">এই মাসে</span>
            </div>
            <?php if (empty($_sectionList)): ?>
                <div class="empty-msg">এই মাসে কোনো ডেটা নেই</div>
            <?php else: foreach ($_sectionList as $s):
                $w = round(($s['c'] / $_maxSection) * 100); ?>
                <div class="section-bar">
                    <div class="sb-name"><?= htmlspecialchars($s['section_name'] ?? 'অজানা') ?></div>
                    <div class="sb-track"><div class="sb-fill" style="width:<?= $w ?>%;"></div></div>
                    <div class="sb-num"><?= $_bnNum($s['c']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-flame me-1" style="color:#c97777;"></i>সর্বাধিক ছুটিগ্রহণকারী</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">এই বছর</span>
            </div>
            <?php if (empty($_topTakers)): ?>
                <div class="empty-msg">কোনো ডেটা নেই</div>
            <?php else: foreach ($_topTakers as $i => $t): ?>
                <div class="emp-row">
                    <span class="emp-avatar" style="background:#faf2dc;color:#a47b54;"><?= $_bnNum($i + 1) ?></span>
                    <div class="emp-info">
                        <div class="emp-name"><?= htmlspecialchars($t['employee_name']) ?></div>
                        <div class="emp-meta"><?= htmlspecialchars($t['section_name'] ?? '') ?></div>
                    </div>
                    <span class="emp-pill" style="background:#faf2dc;color:#8b6f47;"><?= $_bnNum((int)$t['total_days']) ?> দিন</span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="section-card">
    <div class="sc-title">
        <span><i class="ti tabler-bolt me-1" style="color:#d4a056;"></i>দ্রুত কাজ</span>
    </div>
    <div class="row g-2">
        <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/all_leave_application.php" class="quick-action"><i class="ti tabler-clipboard-list"></i><span>সব আবেদন</span></a></div>
        <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/views/employee/list.php" class="quick-action"><i class="ti tabler-users"></i><span>কর্মচারী তালিকা</span></a></div>
        <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/leave_calendar.php" class="quick-action"><i class="ti tabler-calendar-event"></i><span>ছুটির ক্যালেন্ডার</span></a></div>
        <div class="col-6 col-md-3"><a href="<?= BASE_URL ?>/views/info/leave-rules.php?menuslug=leave-rules" class="quick-action"><i class="ti tabler-book-2"></i><span>ছুটির বিধিমালা</span></a></div>
    </div>
</div>
