<?php
// Set page title + subtitle (shown in the top navbar)
require_once(__DIR__ . '/function.php');
$__bnMonths = ['', 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
$__bnYear = (function_exists('banglaNumber') ? banglaNumber(date('Y')) : date('Y'));
// Dashboard page title/subtitle removed from top navbar per user request.
// Leaving blank string suppresses the H5/subtitle without triggering the
// organization_name fallback (which is already shown in the sidebar).
$pageTitle    = '';
$pageSubtitle = '';

include(__DIR__ . '/includes/header_vuexy.php');
require_once('function.php');

// ── Role-based dashboard routing ──────────────────────────────────────
// Note: $getUserInfoQRW is overwritten by sidebar_menu_vuexy.php (only 3 cols).
// Re-query user_list with full data for accurate role detection.
$_routeStmt = mysqli_prepare($con,
    "SELECT user_id, full_name, employee_id, isCenterAdmin, dashboardType, user_type,
            user_group_id, organization_id
     FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($_routeStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($_routeStmt);
$_userFull = mysqli_fetch_assoc(mysqli_stmt_get_result($_routeStmt));
mysqli_stmt_close($_routeStmt);

// Re-merge full data back into $getUserInfoQRW so center-admin.php / signatory-block.php
// can access isCenterAdmin / organization_id / etc.
if ($_userFull) {
    $getUserInfoQRW = array_merge($getUserInfoQRW ?? [], $_userFull);
}

// Super Admin (Master) → all-centers folder grid
// Regional Super Admin (group_id=7) → folder grid filtered to their own center only
$_userGroupID        = (int)($getUserInfoQRW['user_group_id'] ?? 0);
$isSuperAdmin        = ($_userGroupID === 1);
$isRegionalSuperAdmin = ($_userGroupID === 7);

if ($isSuperAdmin || $isRegionalSuperAdmin) {
    if (!function_exists('banglaNumber')) {
        require_once(LIBRARY_PATH . '/number_converter.php');
    }

    // Resolve the regional admin's own center
    $_myCenterID = 0;
    if ($isRegionalSuperAdmin) {
        $_myCenterID = (int)($getUserInfoQRW['organization_id'] ?? 0);
        if ($_myCenterID === 0 && !empty($getUserInfoQRW['employee_id'])) {
            $_eq = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = " . (int)$getUserInfoQRW['employee_id'] . " LIMIT 1");
            if ($_eq && $_er = mysqli_fetch_assoc($_eq)) $_myCenterID = (int)$_er['organization_id'];
        }
    }

    // Load centers (filtered for regional, all for super admin)
    $_centerFilter = $isRegionalSuperAdmin ? " WHERE org.id = " . (int)$_myCenterID : "";
    $_centersQ = mysqli_query($con,
        "SELECT org.id, org.organization_name,
                COUNT(el.id) AS emp_count
         FROM organization org
         LEFT JOIN employee_list el ON el.organization_id = org.id AND el.employment_status = 1
         $_centerFilter
         GROUP BY org.id, org.organization_name
         ORDER BY (org.id = 4) DESC, org.organization_name ASC");
    $_centers = [];
    if ($_centersQ) while ($_r = mysqli_fetch_assoc($_centersQ)) $_centers[] = $_r;
    ?>
    <style>
    .sa-wrap { max-width: 1400px; }
    .sa-header { margin-bottom: 1.5rem; }
    .sa-header h4 { font-weight: 700; color: #2c2e3a; margin-bottom: 0.25rem; }
    .sa-header .subtitle { color: #5d6580; font-size: 0.88rem; }

    .center-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1.25rem;
    }

    .center-folder {
        position: relative;
        background: #fff;
        border: 1px solid #eef0f5;
        border-radius: 0.75rem;
        padding: 1.5rem 1.25rem 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        min-height: 180px;
    }
    .center-folder:hover {
        border-color: #ddd5f6;
        box-shadow: 0 6px 20px rgba(108, 92, 231, 0.12);
        transform: translateY(-3px);
        color: inherit;
        text-decoration: none;
    }
    .center-folder.is-hq {
        background: linear-gradient(135deg, #f8f7ff 0%, #fefefe 100%);
        border-color: #ddd5f6;
    }
    .center-folder.is-hq:hover {
        border-color: #b9b0f4;
    }

    .center-folder .folder-icon {
        width: 72px;
        height: 60px;
        position: relative;
        margin-bottom: 0.25rem;
    }
    .center-folder .folder-tab {
        position: absolute;
        top: 0;
        left: 4px;
        width: 26px;
        height: 8px;
        background: #a29bfe;
        border-radius: 4px 4px 0 0;
    }
    .center-folder .folder-body {
        position: absolute;
        top: 6px;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
        border-radius: 4px 8px 6px 6px;
        box-shadow: inset 0 -10px 20px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .center-folder .folder-body i {
        color: #fff;
        font-size: 1.5rem;
        opacity: 0.85;
    }
    .center-folder.is-hq .folder-tab { background: #ffb84d; }
    .center-folder.is-hq .folder-body {
        background: linear-gradient(135deg, #b8651a 0%, #ffb84d 100%);
    }

    .center-folder .center-name {
        font-weight: 600;
        color: #2c2e3a;
        font-size: 0.95rem;
        line-height: 1.35;
        margin-top: 0.4rem;
        min-height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 0.25rem;
    }
    .center-folder .center-meta {
        font-size: 0.78rem;
        color: #8a90a6;
        margin-top: 0.25rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #fafbfd;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        border: 1px solid #eef0f5;
    }
    .center-folder .center-meta i { color: #6c5ce7; }
    .center-folder.is-hq .center-meta i { color: #b8651a; }
    .center-folder.is-hq .hq-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #b8651a;
        color: #fff;
        font-size: 0.62rem;
        padding: 2px 8px;
        border-radius: 999px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    </style>

    <div class="sa-wrap">
        <div class="sa-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <?php if ($isSuperAdmin): ?>
                    <h4><i class="ti tabler-crown me-2" style="color:#6c5ce7;"></i>সুপার অ্যাডমিন প্যানেল</h4>
                    <div class="subtitle"><i class="ti tabler-info-circle me-1"></i>কেন্দ্রভিত্তিক ফোল্ডার — যে কেন্দ্রে যেতে চান সেটিতে ক্লিক করুন</div>
                <?php else: ?>
                    <h4><i class="ti tabler-shield-star me-2" style="color:#1a7e44;"></i>Regional Super Admin প্যানেল</h4>
                    <div class="subtitle"><i class="ti tabler-info-circle me-1"></i>আপনার কেন্দ্রের ফোল্ডার — বিস্তারিত দেখতে ক্লিক করুন</div>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge bg-label-primary" style="font-size:0.78rem; padding: 0.55em 0.9em;">
                    <i class="ti tabler-building me-1"></i>মোট <?php echo banglaNumber(count($_centers)); ?> টি কেন্দ্র
                </span>
            </div>
        </div>

        <?php if (empty($_centers)): ?>
            <div class="alert alert-warning">কোনো কেন্দ্র পাওয়া যায়নি।</div>
        <?php else: ?>
        <div class="center-grid">
            <?php foreach ($_centers as $_c):
                $_isHQ = ((int)$_c['id'] === 4);
            ?>
            <a href="#"
               class="center-folder<?= $_isHQ ? ' is-hq' : '' ?>"
               data-center-id="<?= (int)$_c['id'] ?>"
               data-center-name="<?= htmlspecialchars($_c['organization_name'], ENT_QUOTES) ?>"
               onclick="event.preventDefault(); openCenterFolder(this);">
                <?php if ($_isHQ): ?>
                    <span class="hq-badge">HQ</span>
                <?php endif; ?>
                <div class="folder-icon">
                    <div class="folder-tab"></div>
                    <div class="folder-body">
                        <i class="ti <?= $_isHQ ? 'tabler-building-skyscraper' : 'tabler-building' ?>"></i>
                    </div>
                </div>
                <div class="center-name"><?= htmlspecialchars($_c['organization_name']) ?></div>
                <div class="center-meta">
                    <i class="ti tabler-users"></i>
                    <?= banglaNumber((int)$_c['emp_count']) ?> জন
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function openCenterFolder(el) {
        var id = el.getAttribute('data-center-id');
        if (!id) return;
        window.location.href = 'views/dashboard/center-overview.php?center_id=' + encodeURIComponent(id);
    }
    </script>
    <?php
    include(__DIR__ . '/includes/footer_vuexy.php');
    exit;
}

// Center-admin dashboard route — legacy isCenterAdmin=1 flag (Center Admin id=2)
// OR new Regional Super Admin (active group_id=7). Both groups manage one
// center and see the same center-admin overview.
$isCenterAdmin = (!empty($getUserInfoQRW['isCenterAdmin']) && (int)$getUserInfoQRW['isCenterAdmin'] === 1)
              || ((int)($getUserInfoQRW['user_group_id'] ?? 0) === 7);
if ($isCenterAdmin) {
    include(__DIR__ . '/views/dashboard/center-admin.php');
    include(__DIR__ . '/includes/footer_vuexy.php');
    exit;
}

// Detect signatory: any pending row in leave_data_for_approval for this employee
$_sigPendingCount = 0;
if (!empty($getUserInfoQRW['employee_id'])) {
    // Show only rows that are actually "active" for this user:
    //  - Supervisor rows: visible as soon as application is submitted
    //  - Signatory rows: only visible after center admin forwards (isSentbyAdmin=1)
    //  - And no earlier-serial row is still unapproved
    $sigStmt = mysqli_prepare($con,
        "SELECT COUNT(*) AS c
         FROM leave_data_for_approval lda
         INNER JOIN leave_applications la ON la.dataID = lda.leaveApplicationID
         WHERE lda.signatory = ? AND lda.isApproved = 0 AND la.status IN (0, 2)
           AND (lda.isSupervisor = 1 OR lda.isSentbyAdmin = 1)
           AND NOT EXISTS (
               SELECT 1 FROM leave_data_for_approval prev
               WHERE prev.leaveApplicationID = lda.leaveApplicationID
                 AND prev.serial < lda.serial
                 AND prev.isApproved = 0
           )"
    );
    mysqli_stmt_bind_param($sigStmt, 's', $getUserInfoQRW['employee_id']);
    mysqli_stmt_execute($sigStmt);
    $_sigRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sigStmt));
    mysqli_stmt_close($sigStmt);
    $_sigPendingCount = (int)($_sigRow['c'] ?? 0);
}
$isSignatory = $_sigPendingCount > 0;

$dateStart = date('Y-m-01');
$enddate = date('Y-m-t');
$todayDate = ShowBangladeshDate();

// Get total employees
$getTotalEmpQ = mysqli_query($con, "select count(*) as totalEmp from employee_list where employment_status=1");
$getTotalEmpQRW = mysqli_fetch_assoc($getTotalEmpQ);

// Get today's leave count
$getTodayOnLeaveEMPQ = mysqli_query($con, "select count(*) as todayOnLeave from leave_applications where status=1 and ('$todayDate' between `dateFrom` and `dateTo`)");
$getTodayOnLeaveEMPQRW = mysqli_fetch_assoc($getTodayOnLeaveEMPQ);

$employeeID = $getUserInfoQRW['employee_id'];

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

// ── Leave calculations via reusable function ──────────────────────────
$leaveInfo = getEmployeeLeaveInfo($employeeID);

// Unpack for template use
$employmentyears  = $leaveInfo['employment']['years'];
$employmentmonths = $leaveInfo['employment']['months'];
$employmentdays   = $leaveInfo['employment']['days'];

$fullAvgVugkritoSalLeaveyears  = $leaveInfo['fullAvgUsed']['years'];
$fullAvgVugkritoSalLeavemonths = $leaveInfo['fullAvgUsed']['months'];
$fullAvgVugkritoSalLeavedays   = $leaveInfo['fullAvgUsed']['days'];

// Use 'halfAvgUsedActual' (actual days taken) for display — 'halfAvgUsed' is doubled for balance math
$halfAvgVugkritoSalLeaveyears  = $leaveInfo['halfAvgUsedActual']['years']  ?? $leaveInfo['halfAvgUsed']['years'];
$halfAvgVugkritoSalLeavemonths = $leaveInfo['halfAvgUsedActual']['months'] ?? $leaveInfo['halfAvgUsed']['months'];
$halfAvgVugkritoSalLeavedays   = $leaveInfo['halfAvgUsedActual']['days']   ?? $leaveInfo['halfAvgUsed']['days'];

$totalWithoutPayyears  = $leaveInfo['withoutPay']['years'];
$totalWithoutPaymonths = $leaveInfo['withoutPay']['months'];
$totalWithoutPaydays   = $leaveInfo['withoutPay']['days'];

$totalExtraOrdinaryLeaveYears  = $leaveInfo['extraOrdinary']['years'];
$totalExtraOrdinaryLeaveMonths = $leaveInfo['extraOrdinary']['months'];
$totalExtraOrdinaryLeaveDays   = $leaveInfo['extraOrdinary']['days'];

$totalUndeductibleLeave = $leaveInfo['undeductible']['total'];

$fullAvgRestSalLeaveyears  = $leaveInfo['fullAvgBalance']['years'];
$fullAvgRestSalLeavemonths = $leaveInfo['fullAvgBalance']['months'];
$fullAvgRestSalLeavedays   = $leaveInfo['fullAvgBalance']['days'];

// BSR split — ভোগযোগ্য (max 4 months) + রিজার্ভ (excess)
$fullAvgAvailYears  = $leaveInfo['fullAvgAvailable']['years'];
$fullAvgAvailMonths = $leaveInfo['fullAvgAvailable']['months'];
$fullAvgAvailDays   = $leaveInfo['fullAvgAvailable']['days'];

$fullAvgReserveYears  = $leaveInfo['fullAvgReserve']['years'];
$fullAvgReserveMonths = $leaveInfo['fullAvgReserve']['months'];
$fullAvgReserveDays   = $leaveInfo['fullAvgReserve']['days'];
$fullAvgReserveTotal  = $leaveInfo['fullAvgReserve']['total']; // days — for conditional display

// Encashment cap = 18 months (540 days) per BSR.
$ENCASH_CAP_DAYS   = 540;
$encashPct         = $fullAvgReserveTotal > 0 ? min(100, round(($fullAvgReserveTotal / $ENCASH_CAP_DAYS) * 100)) : 0;
$encashOverLimit   = $fullAvgReserveTotal > $ENCASH_CAP_DAYS;
$encashExcessDays  = $encashOverLimit ? ($fullAvgReserveTotal - $ENCASH_CAP_DAYS) : 0;

$halfAvgRestSalLeaveyears  = $leaveInfo['halfAvgBalance']['years'];
$halfAvgRestSalLeavemonths = $leaveInfo['halfAvgBalance']['months'];
$halfAvgRestSalLeavedays   = $leaveInfo['halfAvgBalance']['days'];

$casualCurrentBalance = $leaveInfo['casual']['balance'];
$optionalLeaveCurrentBalance = $leaveInfo['optional']['balance'];

$actualJobDurationInYears  = $leaveInfo['actualDuration']['years'];
$actualJobDurationInMonths = $leaveInfo['actualDuration']['months'];
$actualJobDurationInDays   = $leaveInfo['actualDuration']['days'];

// ══════════════════════════════════════════════════════════
// পরামর্শ (Insights) engine — personalized alerts for the employee
// ══════════════════════════════════════════════════════════
$insights = [];
$currentYear = (int)date('Y');
$yearEndDate = "{$currentYear}-12-31";
$daysToYearEnd = max(0, (int)((strtotime($yearEndDate) - strtotime($todayDate)) / 86400));

// 1) নৈমিত্তিক (Casual) lapse risk
$clBalance = (int)$leaveInfo['casual']['balance'];
if ($clBalance > 0) {
    if ($daysToYearEnd <= 30) {
        $insights[] = ['kind' => 'danger', 'icon' => 'tabler-alert-triangle',
            'title' => $obj->engToBn($clBalance) . ' দিন নৈমিত্তিক ছুটি অব্যবহৃত',
            'text'  => 'মাত্র ' . $obj->engToBn($daysToYearEnd) . ' দিন পর lapse হবে — এখনই ব্যবহার করুন।'];
    } elseif ($daysToYearEnd <= 90) {
        $insights[] = ['kind' => 'warning', 'icon' => 'tabler-calendar-event',
            'title' => $obj->engToBn($clBalance) . ' দিন নৈমিত্তিক ছুটি বাকি',
            'text'  => $obj->engToBn($daysToYearEnd) . ' দিন পর (৩১ ডিসেম্বরে) lapse হবে। বছর শেষের আগে ব্যবহার করুন।'];
    }
}

// 2) ঐচ্ছিক lapse risk
$optBalance = (int)$leaveInfo['optional']['balance'];
if ($optBalance > 0 && $daysToYearEnd <= 90) {
    $insights[] = ['kind' => 'warning', 'icon' => 'tabler-star',
        'title' => $obj->engToBn($optBalance) . ' দিন ঐচ্ছিক ছুটি বাকি',
        'text'  => 'সরকার ঘোষিত তালিকা থেকে ' . $obj->engToBn($daysToYearEnd) . ' দিনের মধ্যে ব্যবহার করুন, না হলে lapse।'];
}

// 3) Reserve vs encashment cap (18 months / 540 days)
$reserveDays = (int)$leaveInfo['fullAvgReserve']['total'];
if ($reserveDays > 540) {
    $excess = $reserveDays - 540;
    $insights[] = ['kind' => 'danger', 'icon' => 'tabler-alert-octagon',
        'title' => 'রিজার্ভ ১৮ মাসের ceiling পার করেছে',
        'text'  => $obj->engToBn($excess) . ' দিন অবসরে lapse হবে। এখন ছুটি নিলে বাঁচানো সম্ভব।'];
} elseif ($reserveDays >= 450) {
    $remain = 540 - $reserveDays;
    $insights[] = ['kind' => 'warning', 'icon' => 'tabler-archive',
        'title' => 'রিজার্ভ ১৮ মাসের কাছাকাছি',
        'text'  => 'আর ' . $obj->engToBn($remain) . ' দিন জমা হলে অতিরিক্ত অংশ lapse হবে।'];
} elseif ($reserveDays > 0) {
    $insights[] = ['kind' => 'success', 'icon' => 'tabler-shield-check',
        'title' => 'রিজার্ভ safe range-এ',
        'text'  => 'বর্তমান ' . $obj->engToBn($reserveDays) . ' দিন — পুরোটাই অবসরে encashable।'];
}

// 4) Pending applications (employee's own, status=0)
$empEsc = mysqli_real_escape_string($con, $employeeID);
$pendingQ = mysqli_query($con, "SELECT COUNT(*) AS cnt, MIN(submitDate) AS oldest
                                FROM leave_applications
                                WHERE applicantID = '$empEsc' AND status = 0");
$pRow = mysqli_fetch_assoc($pendingQ);
if ($pRow && (int)$pRow['cnt'] > 0 && !empty($pRow['oldest'])) {
    $oldestAgeDays = (int)((strtotime($todayDate) - strtotime($pRow['oldest'])) / 86400);
    if ($oldestAgeDays >= 3) {
        $insights[] = ['kind' => 'info', 'icon' => 'tabler-clock',
            'title' => $obj->engToBn($pRow['cnt']) . 'টা আবেদন অনুমোদনের অপেক্ষায়',
            'text'  => 'সবচেয়ে পুরনো আবেদন ' . $obj->engToBn($oldestAgeDays) . ' দিন ধরে pending। Supervisor-কে মনে করিয়ে দিন।'];
    }
}

// 5) Positive fallback if no alerts
if (empty($insights)) {
    $insights[] = ['kind' => 'success', 'icon' => 'tabler-check',
        'title' => 'সবকিছু ঠিকঠাক চলছে',
        'text'  => 'আপনার ছুটির ব্যালেন্স সুসংগতিপূর্ণ এবং কোনো urgent action প্রয়োজন নেই।'];
}

// ══════════════════════════════════════════════════════════
// Analytics data — for visual panels (donut, timeline, trend)
// ══════════════════════════════════════════════════════════

// 1) Leave-usage breakdown for donut
$usageSegments = [
    ['label' => 'পূর্ণ গড়',   'days' => (int)$leaveInfo['fullAvgUsed']['total'],   'color' => '#8b9dc9'],
    ['label' => 'অর্ধ-গড়',   'days' => (int)($leaveInfo['halfAvgUsedActual']['total'] ?? $leaveInfo['halfAvgUsed']['total']),   'color' => '#7fb5c5'],
    ['label' => 'নৈমিত্তিক', 'days' => (int)$leaveInfo['casual']['spent'],        'color' => '#7fb59c'],
    ['label' => 'বিনা বেতনে','days' => (int)$leaveInfo['withoutPay']['total'],    'color' => '#a89cc4'],
    ['label' => 'অসাধারণ',   'days' => (int)$leaveInfo['extraOrdinary']['total'], 'color' => '#d4a056'],
];
$totalUsedDays = array_sum(array_column($usageSegments, 'days'));

$donutGradient = 'conic-gradient(#e5e7eb 0% 100%)';
if ($totalUsedDays > 0) {
    $running = 0;
    $stops = [];
    foreach ($usageSegments as $seg) {
        if ($seg['days'] <= 0) continue;
        $pct = ($seg['days'] / $totalUsedDays) * 100;
        $stops[] = sprintf('%s %.2f%% %.2f%%', $seg['color'], $running, $running + $pct);
        $running += $pct;
    }
    $donutGradient = 'conic-gradient(' . implode(', ', $stops) . ')';
}

// 2) Service timeline — joining / today / retirement
// retirement_date often empty → fall back to DOB + 59 years (BD gov retirement age)
$empDatesQ = mysqli_query($con, "SELECT joining_date, retirement_date, date_of_birth FROM employee_list WHERE id='" . mysqli_real_escape_string($con, $employeeID) . "'");
$empDatesRow = $empDatesQ ? mysqli_fetch_assoc($empDatesQ) : null;
$joiningDate = $empDatesRow['joining_date'] ?? null;
$retireDate  = $empDatesRow['retirement_date'] ?? null;
$dobDate     = $empDatesRow['date_of_birth'] ?? null;

// If retirement is blank, compute from DOB + 59 years
$retireDerived = false;
if (empty($retireDate) && !empty($dobDate)) {
    $dobTs = strtotime($dobDate);
    if ($dobTs) {
        $retireDate = date('Y-m-d', strtotime('+59 years', $dobTs));
        $retireDerived = true;
    }
}

$timelinePercent = 0;
$yearsServed = 0;
$yearsRemaining = 0;
$timelineOK = false;
if (!empty($joiningDate) && !empty($retireDate)) {
    $jTs = strtotime($joiningDate);
    $rTs = strtotime($retireDate);
    $nowTs = strtotime($todayDate);
    if ($jTs && $rTs && $rTs > $jTs) {
        $total = $rTs - $jTs;
        $elapsed = max(0, min($total, $nowTs - $jTs));
        $timelinePercent = round(($elapsed / $total) * 100);
        $yearsServed    = round($elapsed / (86400 * 365), 1);
        $yearsRemaining = max(0, round(($rTs - $nowTs) / (86400 * 365), 1));
        $timelineOK = true;
    }
}

// 3) Monthly trend — last 12 months
$trendMonths = [];
for ($i = 11; $i >= 0; $i--) {
    $d = strtotime("-$i months", strtotime($todayDate));
    $key = date('Y-m', $d);
    $trendMonths[$key] = ['label' => $obj->engToBn(date('M', $d)), 'days' => 0];
}
$empEsc2 = mysqli_real_escape_string($con, $employeeID);
$todayEsc = mysqli_real_escape_string($con, $todayDate);
$trendQ = mysqli_query($con, "
    SELECT DATE_FORMAT(approvedDateFrom, '%Y-%m') AS ym, SUM(approvedDays) AS days
    FROM leave_applications
    WHERE applicantID = '$empEsc2'
      AND status = 1
      AND approvedDateFrom IS NOT NULL
      AND approvedDateFrom >= DATE_SUB('$todayEsc', INTERVAL 12 MONTH)
    GROUP BY ym
");
while ($trendQ && $tRow = mysqli_fetch_assoc($trendQ)) {
    if (isset($trendMonths[$tRow['ym']])) {
        $trendMonths[$tRow['ym']]['days'] = (int)$tRow['days'];
    }
}
$trendMaxDays = max(1, max(array_column($trendMonths, 'days')));

// My Leave Applications for Dashboard Widget — includes segment count for multi-type apps
$myLeaveAppsQ = mysqli_query($con, "
    SELECT la.dataID, la.leaveType, la.leaveTypeInTwo, la.dateFrom, la.dateTo,
           la.approvedDateFrom, la.approvedDateTo, la.approvedDays, la.status,
           la.submitDate, la.subject,
           COALESCE(
               lt2.leaveTitle,
               lt1.leaveTitle,
               (SELECT ltx.leaveTitle FROM leave_application_segments s
                LEFT JOIN leave_types ltx ON s.leaveType = ltx.leaveID
                WHERE s.applicationID = la.dataID
                ORDER BY s.serial ASC, s.dataID ASC LIMIT 1)
           ) as leaveTypeName,
           (SELECT COUNT(*) FROM leave_application_segments s WHERE s.applicationID = la.dataID) as segCount
    FROM leave_applications la
    LEFT JOIN leave_types lt2 ON la.leaveTypeInTwo = lt2.leaveID
    LEFT JOIN leave_types lt1 ON la.leaveType      = lt1.leaveID
    WHERE la.applicantID = '$employeeID'
    AND la.status != 3
    ORDER BY la.submitDate DESC, la.dataID DESC
    LIMIT 8
");
?>

<style>
/* ── Layout helpers ───────────────────────────── */
@media (min-width: 1200px) {
    .row-cols-xl-7 > * {
        flex: 0 0 auto;
        width: calc(100% / 7);
        min-width: 0;
    }
}

/* ── Hero greeting strip ──────────────────────── */
.hero-card {
    position: relative;
    border-radius: 14px;
    padding: 22px 26px;
    overflow: hidden;
    background:
        radial-gradient(circle at 85% -20%, rgba(115,103,240,0.45) 0%, transparent 55%),
        radial-gradient(circle at 10% 120%, rgba(105,108,255,0.35) 0%, transparent 55%),
        linear-gradient(130deg, #343769 0%, #1f2141 100%);
    color: #fff;
    box-shadow: 0 4px 20px rgba(52,55,105,0.22);
}
.hero-card::after {
    content: "";
    position: absolute;
    right: -40px; top: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    pointer-events: none;
}
.hero-card::before {
    content: "";
    position: absolute;
    right: 40px; bottom: -60px;
    width: 120px; height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    pointer-events: none;
}
.hero-avatar {
    width: 54px; height: 54px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    display: inline-flex;
    align-items: center; justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 1.2rem;
    overflow: hidden;
    border: 2px solid rgba(255,255,255,0.18);
    flex-shrink: 0;
}
.hero-avatar img { width:100%; height:100%; object-fit: cover; display:block; }
.hero-greet {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.7);
    margin: 0 0 2px;
    font-weight: 500;
}
.hero-name {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0;
    color: #fff;
}
.hero-sub {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.65);
    margin-top: 2px;
}
.hero-stat {
    position: relative;
    z-index: 1;
    min-width: 130px;
    padding: 10px 16px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 10px;
    backdrop-filter: blur(4px);
}
.hero-stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.65);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin: 0 0 2px;
}
.hero-stat-value {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
    color: #fff;
    line-height: 1.1;
}
.hero-stat-value small {
    font-size: 0.65rem;
    font-weight: 500;
    color: rgba(255,255,255,0.6);
    margin-left: 4px;
}

/* ── KPI / Stat cards ─────────────────────────── */
.kpi-card {
    position: relative;
    background: #fff;
    border: 1px solid #eef0f3;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16,24,40,0.04);
    transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
    overflow: hidden;
}
.kpi-card::before {
    content: "";
    position: absolute;
    left: 0; top: 0; right: 0;
    height: 3px;
    background: var(--kpi-accent, #8b9dc9);
    opacity: 0.85;
}
.kpi-card:hover {
    box-shadow: 0 10px 24px rgba(16,24,40,0.09);
    border-color: #dfe3ea;
    transform: translateY(-2px);
}
.kpi-card .card-body {
    padding: 18px 16px 16px;
    position: relative;
    z-index: 1;
}
.kpi-card .kpi-watermark {
    position: absolute;
    right: -14px; bottom: -14px;
    font-size: 5.5rem;
    color: var(--kpi-accent, #8b9dc9);
    opacity: 0.06;
    line-height: 1;
    pointer-events: none;
    z-index: 0;
}

.kpi-icon {
    width: 44px; height: 44px;
    border-radius: 11px;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    box-shadow: 0 4px 10px var(--kpi-glow, rgba(105,108,255,0.18));
}
.kpi-icon.a-indigo { background:#eef0ff; color:#8b9dc9; }
.kpi-icon.a-teal   { background:#e0f9fc; color:#7fb5c5; }
.kpi-icon.a-amber  { background:#fff3e5; color:#d4a056; }
.kpi-icon.a-green  { background:#e6f8ee; color:#7fb59c; }
.kpi-icon.a-rose   { background:#fde7e7; color:#c97777; }
.kpi-icon.a-purple { background:#eeebfb; color:#a89cc4; }
.kpi-icon.a-slate  { background:#eef0f5; color:#475569; }

.kpi-label {
    font-size: 0.82rem;
    font-weight: 400;
    color: #64748b;
    margin: 0 0 4px;
    line-height: 1.35;
}
.kpi-value {
    font-size: 1.05rem;
    font-weight: 400;
    color: #111827;
    line-height: 1.45;
    margin: 0;
    word-break: keep-all;
    letter-spacing: 0;
}

/* ── Section card shell ───────────────────────── */
.section-card {
    background: #fff;
    border: 1px solid #eef0f3;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16,24,40,0.04);
    overflow: hidden;
}

/* ── Collapsible card ─────────────────────────── */
.section-card.collapsible-card .section-head {
    cursor: pointer;
    user-select: none;
    transition: background 0.15s ease;
}
.section-card.collapsible-card .section-head:hover {
    background: linear-gradient(180deg, #f4f6ff 0%, #fafbff 100%);
}
.section-card.collapsible-card .section-head .section-chevron {
    margin-left: auto;
    font-size: 1.2rem;
    color: #8b9dc9;
    transition: transform 0.3s ease;
    flex-shrink: 0;
}
.section-card.collapsible-card.is-open .section-head .section-chevron {
    transform: rotate(180deg);
}
.section-card.collapsible-card .section-body-wrap {
    overflow: hidden;
    transition: max-height 0.35s ease, opacity 0.25s ease;
}
.section-card.collapsible-card:not(.is-open) .section-body-wrap {
    max-height: 0 !important;
    opacity: 0;
}
.section-card.collapsible-card .section-head {
    border-bottom: 1px solid transparent;
    transition: border-color 0.2s ease;
}
.section-card.collapsible-card.is-open .section-head {
    border-bottom-color: #eef0f3;
}
.section-card.collapsible-card:not(.is-open) .section-head::after {
    opacity: 0;
}
.section-card .section-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid #eef0f3;
    background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
    position: relative;
}
.section-card .section-head::after {
    content: "";
    position: absolute;
    left: 20px; bottom: -1px;
    width: 44px; height: 3px;
    background: linear-gradient(90deg, #8b9dc9, #a89cc4);
    border-radius: 2px;
}
.section-card .section-head .head-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: #eef0ff;
    color: #8b9dc9;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 1.15rem;
    box-shadow: 0 4px 10px rgba(105,108,255,0.18);
}
.section-card .section-head .head-title {
    font-size: 1.02rem;
    font-weight: 400;
    color: #1f2937;
    margin: 0;
    letter-spacing: 0;
}
.section-card .section-head .head-sub {
    font-size: 0.82rem;
    color: #64748b;
    margin-top: 3px;
}

/* ── Balance tiles ────────────────────────────── */
.balance-tile {
    position: relative;
    background: #fff;
    border: 1px solid #eef0f3;
    border-radius: 12px;
    padding: 16px 14px;
    height: 100%;
    transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
    overflow: hidden;
}
.balance-tile::before {
    content: "";
    position: absolute;
    left: 0; top: 0; right: 0;
    height: 3px;
    background: var(--tile-accent, #8b9dc9);
    opacity: 0.85;
}
.balance-tile:hover {
    box-shadow: 0 10px 24px rgba(16,24,40,0.09);
    border-color: #dfe3ea;
    transform: translateY(-2px);
}
.balance-tile .tile-watermark {
    position: absolute;
    right: -12px; bottom: -14px;
    font-size: 4.5rem;
    color: var(--tile-accent, #8b9dc9);
    opacity: 0.06;
    line-height: 1;
    pointer-events: none;
}
.balance-tile .tile-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 12px;
    box-shadow: 0 4px 10px var(--tile-glow, rgba(105,108,255,0.18));
}
.balance-tile .tile-label {
    font-size: 0.84rem;
    font-weight: 400;
    color: #64748b;
    margin-bottom: 5px;
    line-height: 1.35;
}
.balance-tile .tile-value {
    font-size: 1.08rem;
    font-weight: 400;
    color: #111827;
    line-height: 1.45;
    letter-spacing: 0;
}

/* ── Tile info button + popover ─────────────────── */
.tile-info-btn {
    position: absolute;
    top: 12px; right: 12px;
    width: 24px; height: 24px;
    border-radius: 50%;
    border: 0;
    background: transparent;
    color: #9ca3af;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    padding: 0;
    transition: all .15s ease;
    z-index: 2;
}
.tile-info-btn:hover {
    background: #f3f5f8;
    color: #8b9dc9;
}
/* Smaller variant for KPI cards (less padding) */
.kpi-card .tile-info-btn {
    top: 6px; right: 6px;
    width: 22px; height: 22px;
    font-size: 0.9rem;
}

/* ── Reserve progress bar + caption ─────────────── */
.tile-progress-wrap {
    margin-top: 8px;
    position: relative;
    z-index: 1;
}
.tile-progress {
    width: 100%;
    height: 5px;
    background: #eef0f3;
    border-radius: 999px;
    overflow: hidden;
}
.tile-progress-bar {
    height: 100%;
    border-radius: 999px;
    transition: width .4s ease;
    background: var(--tile-accent, #8b9dc9);
}
.tile-progress-bar.warn { background: #d4a056; }
.tile-progress-bar.over { background: #c97777; }
.tile-progress-cap {
    margin-top: 6px;
    font-size: 0.7rem;
    color: #6b7280;
    display: flex;
    justify-content: space-between;
    font-weight: 500;
}
.tile-progress-cap .cap-warn { color: #c97777; font-weight: 700; }

/* ── Insights card (পরামর্শ) ──────────────────── */
.insights-list { padding: 0; margin: 0; list-style: none; }
.insight-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 18px;
    border-bottom: 1px solid #f3f5f8;
    transition: background .15s ease;
}
.insight-item:last-child { border-bottom: 0; }
.insight-item:hover { background: #fafbff; }

.insight-icon {
    flex-shrink: 0;
    width: 40px; height: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.insight-body { flex: 1; min-width: 0; }
.insight-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 2px;
    line-height: 1.3;
}
.insight-text {
    font-size: 0.82rem;
    color: #6b7280;
    margin: 0;
    line-height: 1.45;
}

/* Kind-specific accents */
.insight-item.kind-danger  .insight-icon { background:#fde7e7; color:#c97777; }
.insight-item.kind-warning .insight-icon { background:#fff3e5; color:#d4a056; }
.insight-item.kind-info    .insight-icon { background:#eef0ff; color:#8b9dc9; }
.insight-item.kind-success .insight-icon { background:#e6f8ee; color:#7fb59c; }

.insight-item.kind-danger  .insight-title { color:#a06262; }

/* ── Analytics — Donut chart ──────────────────── */
.donut-wrap { display: flex; align-items: center; gap: 20px; }
.donut {
    width: 150px; height: 150px;
    border-radius: 50%;
    position: relative;
    flex-shrink: 0;
    background: var(--donut-bg, conic-gradient(#e5e7eb 0 100%));
}
.donut::after {
    content: "";
    position: absolute;
    inset: 22px;
    background: #fff;
    border-radius: 50%;
}
.donut-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 2;
    text-align: center;
}
.donut-center .num {
    font-size: 1.4rem; font-weight: 800; color: #111827; line-height: 1; font-variant-numeric: tabular-nums;
}
.donut-center .lbl {
    font-size: 0.7rem; color: #6b7280; margin-top: 3px; font-weight: 500;
}
.donut-legend { flex: 1; min-width: 0; }
.donut-legend .lg-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
    font-size: 0.82rem;
}
.donut-legend .lg-row:last-child { margin-bottom: 0; }
.donut-legend .lg-dot {
    width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
}
.donut-legend .lg-label { color: #4b5563; flex: 1; }
.donut-legend .lg-val { color: #111827; font-weight: 700; font-variant-numeric: tabular-nums; }

/* ── Analytics — Service timeline ─────────────── */
.svc-timeline {
    margin: 14px 0 4px;
    position: relative;
    padding: 0 12px;
}
.svc-track {
    height: 8px;
    background: #eef0f3;
    border-radius: 999px;
    position: relative;
    overflow: visible;
}
.svc-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #8b9dc9, #a89cc4);
}
.svc-marker {
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 16px; height: 16px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #8b9dc9;
    box-shadow: 0 2px 6px rgba(105,108,255,0.35);
    z-index: 2;
}
.svc-marker.start { left: 0; border-color: #7fb59c; }
.svc-marker.end   { left: 100%; border-color: #c97777; }
.svc-marker.now   { /* left set inline */ }
.svc-endpoints {
    display: flex;
    justify-content: space-between;
    margin-top: 14px;
    font-size: 0.78rem;
    color: #6b7280;
}
.svc-endpoints .svc-lbl { text-align: center; }
.svc-endpoints .svc-lbl strong { display: block; color: #111827; font-size: 0.88rem; font-weight: 700; }
.svc-stats {
    display: flex;
    justify-content: space-around;
    gap: 12px;
    margin-top: 12px;
    padding: 10px;
    background: #fafbff;
    border-radius: 8px;
}
.svc-stats .s-num { font-size: 1.05rem; font-weight: 800; color: #8b9dc9; }
.svc-stats .s-lbl { font-size: 0.72rem; color: #6b7280; }

/* ── Analytics — Monthly trend bars ───────────── */
.trend-bars {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    height: 140px;
    padding: 8px 4px 0;
    margin-top: 8px;
}
.trend-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
    gap: 4px;
}
.trend-bar {
    width: 100%;
    background: linear-gradient(180deg, #8b9dc9 0%, #a89cc4 100%);
    border-radius: 4px 4px 0 0;
    min-height: 4px;
    transition: all .3s ease;
    position: relative;
}
.trend-bar.zero {
    background: #eef0f3;
    height: 4px !important;
}
.trend-bar:hover {
    background: linear-gradient(180deg, #7d9bc5 0%, #8b9dc9 100%);
}
.trend-val {
    font-size: 0.7rem;
    color: #111827;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    margin-bottom: -2px;
}
.trend-label {
    font-size: 0.7rem;
    color: #6b7280;
    font-weight: 500;
    margin-top: 4px;
}

.analytics-panel h6 {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 4px;
}
.analytics-panel .panel-sub {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 14px;
}

/* ── Rule popover styling ───────────────────────── */
.popover.rule-popover {
    max-width: 340px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(16,24,40,0.12);
}
.rule-popover .popover-header {
    background: #fafbff;
    color: #111827;
    font-weight: 700;
    font-size: 0.88rem;
    border-bottom: 1px solid #eef0f3;
    padding: 10px 14px;
}
.rule-popover .popover-body {
    padding: 12px 14px;
    font-size: 0.82rem;
    color: #4b5563;
    line-height: 1.55;
}
.rule-popover .rule-ref {
    display: inline-block;
    background: #eef0ff;
    color: #8b9dc9;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    margin-bottom: 8px;
    letter-spacing: 0.2px;
}
.rule-popover hr { margin: 10px 0; border-top: 1px solid #eef0f3; }
.rule-popover strong { color: #111827; }

/* ── PRL Countdown (retirement) ───────────────── */
.prl-card {
    position: relative;
    background:
        radial-gradient(circle at 90% 10%, rgba(115,103,240,0.4) 0%, transparent 45%),
        radial-gradient(circle at 10% 90%, rgba(105,108,255,0.3) 0%, transparent 50%),
        linear-gradient(150deg, #2a2d52 0%, #1a1c38 100%);
    border-radius: 12px;
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(42,45,82,0.25);
}
.prl-card::after {
    content: "";
    position: absolute;
    right: -30px; top: -30px;
    width: 140px; height: 140px;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
    pointer-events: none;
}
.prl-head {
    padding: 18px 20px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    position: relative;
    z-index: 1;
}
.prl-head .prl-head-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.prl-head .prl-head-icon {
    width: 36px; height: 36px;
    border-radius: 9px;
    background: rgba(255,255,255,0.1);
    color: #fff;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 1.1rem;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);
}
.prl-head .prl-title {
    color: #fff;
    font-weight: 700;
    font-size: 0.92rem;
    margin: 0;
    letter-spacing: -0.1px;
}
.prl-head .prl-date {
    color: rgba(255,255,255,0.72);
    font-size: 0.88rem;
    letter-spacing: 0.5px;
    margin: 0;
    font-weight: 600;
}
.prl-body {
    flex: 1;
    padding: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}
.prl-units {
    display: flex;
    gap: 8px;
    width: 100%;
    justify-content: center;
}
.prl-unit-box {
    flex: 1;
    max-width: 80px;
    background: linear-gradient(180deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.03) 100%);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 12px 6px;
    text-align: center;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
}
.prl-unit-box .prl-num {
    font-size: 1.7rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    display: block;
    letter-spacing: -0.5px;
    text-shadow: 0 2px 8px rgba(115,103,240,0.35);
}
.prl-unit-box .prl-lbl {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.6);
    margin-top: 5px;
    display: block;
    font-weight: 500;
}
.prl-foot {
    padding: 12px 18px 16px;
    text-align: center;
    color: rgba(255,255,255,0.5);
    font-size: 0.74rem;
    position: relative;
    z-index: 1;
    border-top: 1px solid rgba(255,255,255,0.05);
}

/* ── My Leave table ───────────────────────────── */
.leaves-table { margin: 0; }
.leaves-table thead th {
    background: #fafbff;
    color: #6b7280;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1px solid #eef0f3 !important;
    border-top: 0;
    padding: 14px 16px;
}
.leaves-table tbody td {
    padding: 15px 16px;
    border-bottom: 1px solid #f3f5f8;
    vertical-align: middle;
    transition: background .15s ease;
}
.leaves-table tbody tr { position: relative; }
.leaves-table tbody tr:last-child td { border-bottom: 0; }
.leaves-table tbody tr:hover td { background: #fafbff; }
.leaves-table tbody td:first-child {
    position: relative;
    padding-left: 22px;
}
.leaves-table tbody td:first-child::before {
    content: "";
    position: absolute;
    left: 8px; top: 12px; bottom: 12px;
    width: 3px;
    border-radius: 2px;
    background: var(--row-accent, transparent);
}

.lt-type {
    font-weight: 400;
    color: #1f2937;
    font-size: 0.95rem;
    letter-spacing: 0;
}
.lt-subject {
    color: #6b7280;
    font-size: 0.76rem;
    max-width: 240px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lt-date {
    color: #4b5563;
    font-size: 0.82rem;
    white-space: nowrap;
}
.lt-days {
    display: inline-flex;
    align-items: center; justify-content: center;
    width: 34px; height: 34px;
    background: #f3f5f8;
    border-radius: 8px;
    font-weight: 700;
    color: #1f2937;
    font-size: 0.9rem;
}
.lt-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.2px;
    white-space: nowrap;
    border: 1px solid transparent;
}
.lt-badge.approved { background:#e6f8ee; color:#5fa885; border-color:#cdefd8; }
.lt-badge.pending  { background:#fff3e5; color:#a47b54; border-color:#ffe1bc; }
.lt-badge.declined { background:#fde7e7; color:#a06262; border-color:#f7cfcf; }

.sig-avatar {
    position: relative;
    width: 30px; height: 30px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 0.66rem;
    font-weight: 700;
    cursor: help;
    flex-shrink: 0;
    transition: transform .15s ease;
    overflow: hidden;
}
.sig-avatar:hover {
    transform: scale(1.12);
    z-index: 3;
}
.sig-dot {
    position: absolute;
    bottom: -1px; right: -1px;
    width: 9px; height: 9px;
    border-radius: 50%;
    border: 2px solid #fff;
}
.sig-chain-sep {
    color: #cbd2da;
    font-size: 0.7rem;
    line-height: 1;
    margin: 0 1px;
}
.sig-meta {
    margin-top: 6px;
    font-size: 0.72rem;
    color: #6b7280;
}
.sig-meta .signed { color: #5fa885; font-weight: 600; }
.sig-meta .waiting { color: #a47b54; font-weight: 600; }

.empty-state {
    text-align: center;
    padding: 48px 16px;
}
.empty-state .es-icon {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: #f3f5f8;
    color: #9aa0ae;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 12px;
}
.empty-state .es-title {
    color: #4b5563;
    font-weight: 600;
    margin: 0;
}
.empty-state .es-sub {
    color: #9aa0ae;
    font-size: 0.82rem;
    margin-top: 4px;
}

.btn-link-soft {
    font-size: 0.82rem;
    font-weight: 500;
    color: #8b9dc9;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    transition: background .15s ease;
}
.btn-link-soft:hover {
    background: #eef0ff;
    color: #7d9bc5;
}

/* ─────────────────────────────────────────────
   REFINEMENTS — entrance, shimmer, textures
───────────────────────────────────────────── */

/* Hero dot pattern + bottom wave */
.hero-card {
    background-image:
        radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px),
        radial-gradient(circle at 85% -20%, rgba(115,103,240,0.45) 0%, transparent 55%),
        radial-gradient(circle at 10% 120%, rgba(105,108,255,0.35) 0%, transparent 55%),
        linear-gradient(130deg, #343769 0%, #1f2141 100%);
    background-size: 22px 22px, auto, auto, auto;
    background-position: 0 0, 0 0, 0 0, 0 0;
}
.hero-name {
    background: linear-gradient(135deg, #ffffff 0%, #e0e1ff 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    text-fill-color: transparent;
}

/* Live pulse dot (on "today on leave") */
.pulse-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #7fb59c;
    margin-right: 6px;
    position: relative;
    vertical-align: middle;
    box-shadow: 0 0 0 0 rgba(40,199,111,0.6);
    animation: pulseDot 2s infinite;
}
@keyframes pulseDot {
    0%   { box-shadow: 0 0 0 0 rgba(40,199,111,0.6); }
    70%  { box-shadow: 0 0 0 8px rgba(40,199,111,0); }
    100% { box-shadow: 0 0 0 0 rgba(40,199,111,0); }
}

/* Entrance animations (stagger) */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.anim-hero   { animation: fadeDown .5s ease both; }
.anim-up     { animation: fadeUp .45s ease both; }
.anim-up-d1  { animation: fadeUp .45s ease .05s both; }
.anim-up-d2  { animation: fadeUp .45s ease .10s both; }
.anim-up-d3  { animation: fadeUp .45s ease .15s both; }
.anim-up-d4  { animation: fadeUp .45s ease .20s both; }
.anim-up-d5  { animation: fadeUp .45s ease .25s both; }
.anim-up-d6  { animation: fadeUp .45s ease .30s both; }
.anim-up-d7  { animation: fadeUp .45s ease .35s both; }
.anim-up-section { animation: fadeUp .5s ease .40s both; }

/* Traveling shimmer on KPI card hover */
.kpi-card::after {
    content: "";
    position: absolute;
    top: 0; left: -120%;
    width: 80%; height: 100%;
    background: linear-gradient(
        100deg,
        transparent 30%,
        rgba(255,255,255,0.45) 50%,
        transparent 70%
    );
    transform: skewX(-20deg);
    pointer-events: none;
    transition: left .65s ease;
    z-index: 2;
}
.kpi-card:hover::after { left: 130%; }

/* Tabular numbers + subtle polish */
.kpi-value, .tile-value, .hero-stat-value, .prl-num, .lt-days {
    font-variant-numeric: tabular-nums;
}

/* Decorative section divider (small centered dots) */
.section-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin: 6px 0 18px;
    opacity: 0.5;
}
.section-divider .dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: #cbd2da;
}
.section-divider .dot.accent {
    background: #8b9dc9;
    width: 6px; height: 6px;
}

/* PRL number subtle continuous glow */
.prl-num {
    animation: prlGlow 3s ease-in-out infinite alternate;
}
@keyframes prlGlow {
    from { text-shadow: 0 2px 8px rgba(115,103,240,0.25); }
    to   { text-shadow: 0 2px 14px rgba(115,103,240,0.55); }
}

/* Leave table row lift on hover */
.leaves-table tbody tr {
    transition: transform .15s ease;
}
.leaves-table tbody tr:hover {
    transform: translateX(2px);
}

/* Smoother scrollbar inside table */
.table-responsive::-webkit-scrollbar { height: 8px; }
.table-responsive::-webkit-scrollbar-thumb {
    background: #dfe3ea;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover { background: #c5ccd5; }

/* Respect reduced-motion preference */
@media (prefers-reduced-motion: reduce) {
    .anim-hero, .anim-up,
    .anim-up-d1, .anim-up-d2, .anim-up-d3, .anim-up-d4,
    .anim-up-d5, .anim-up-d6, .anim-up-d7, .anim-up-section,
    .pulse-dot, .prl-num,
    .kpi-card::after,
    .leaves-table tbody tr { animation: none !important; transition: none !important; }
}

/* ═══════════════════════════════════════════════
   POLISH — purple-aligned accents to match app theme
═══════════════════════════════════════════════ */
/* Hero card → app's purple gradient */
.hero-card {
    background:
        radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px),
        radial-gradient(circle at 85% -20%, rgba(108,92,231,0.35) 0%, transparent 55%),
        radial-gradient(circle at 10% 120%, rgba(86,72,196,0.28) 0%, transparent 55%),
        linear-gradient(135deg, #5648c4 0%, #3a2fa8 100%) !important;
    background-size: 22px 22px, auto, auto, auto !important;
    background-position: 0 0, 0 0, 0 0, 0 0 !important;
    box-shadow: 0 4px 22px rgba(86, 72, 196, 0.22);
}
/* Section head underline → purple gradient */
.section-card .section-head::after {
    background: linear-gradient(90deg, #6c5ce7, #a29bfe) !important;
}
/* Section card head icon */
.section-card .section-head .head-icon {
    background: #f0edff !important;
    color: #5648c4 !important;
    box-shadow: 0 4px 10px rgba(108, 92, 231, 0.18) !important;
}
/* Service timeline fill → purple gradient */
.svc-fill { background: linear-gradient(90deg, #6c5ce7, #a29bfe) !important; }
.svc-stats .s-num { color: #5648c4 !important; }
/* Section dividers — keep neutral but tweak accent */
.section-divider .dot.accent { background: #6c5ce7 !important; }
/* Soft "info" insight uses purple */
.insight-item.kind-info .insight-icon {
    background: #f0edff !important;
    color: #5648c4 !important;
}
/* Indigo KPI accent → app purple */
.kpi-icon.a-indigo { background: #f0edff !important; color: #5648c4 !important; }
/* btn-link-soft → purple */
.btn-link-soft { color: #5648c4 !important; }
.btn-link-soft:hover { background: #f0edff !important; color: #4a3ba8 !important; }

/* ═══════════════════════════════════════════════
   MOBILE RESPONSIVE — dashboard scaling
═══════════════════════════════════════════════ */

/* Tablet ≤ 991px */
@media (max-width: 991.98px) {
    .hero-card {
        padding: 18px 20px;
        border-radius: 12px;
    }
    .hero-card .d-flex.gap-3.flex-wrap.align-items-center.justify-content-between {
        gap: 14px !important;
    }
    .hero-avatar { width: 46px; height: 46px; font-size: 1rem; }
    .hero-name { font-size: 1.02rem; }
    .hero-greet, .hero-sub { font-size: 0.78rem; }
    .hero-stat { min-width: auto; padding: 8px 12px; }
    .hero-stat-value { font-size: 1.05rem; }

    /* KPI cards: smaller padding + icon + text */
    .kpi-card .card-body { padding: 14px 12px 12px; }
    .kpi-icon { width: 38px; height: 38px; font-size: 1.1rem; border-radius: 9px; }
    .kpi-label { font-size: 0.76rem; }
    .kpi-value { font-size: 0.94rem; }
    .kpi-card .kpi-watermark { font-size: 4rem; }

    /* Balance tile compact */
    .balance-tile { padding: 14px 12px; }
    .balance-tile .tile-icon { width: 34px; height: 34px; font-size: 1.05rem; margin-bottom: 8px; }
    .balance-tile .tile-label { font-size: 0.78rem; }
    .balance-tile .tile-value { font-size: 0.95rem; }
    .balance-tile .tile-watermark { font-size: 3.5rem; }

    /* Section heads */
    .section-card .section-head { padding: 12px 16px; gap: 10px; }
    .section-card .section-head .head-icon { width: 34px; height: 34px; font-size: 1rem; }
    .section-card .section-head .head-title { font-size: 0.95rem; }
    .section-card .section-head .head-sub { font-size: 0.76rem; }

    /* Donut chart smaller on mobile */
    .donut { width: 130px; height: 130px; }
    .donut::after { inset: 18px; }
    .donut-center .num { font-size: 1.2rem; }
    .donut-wrap { gap: 16px; }

    /* Service timeline */
    .svc-endpoints { font-size: 0.72rem; }
    .svc-endpoints .svc-lbl strong { font-size: 0.82rem; }
    .svc-stats .s-num { font-size: 0.95rem; }
    .svc-stats .s-lbl { font-size: 0.68rem; }
    .svc-stats { padding: 8px; gap: 8px; }

    /* Trend bars */
    .trend-bars { height: 110px; gap: 4px; }

    /* Insights */
    .insight-item { padding: 12px 14px; gap: 10px; }
    .insight-icon { width: 36px; height: 36px; font-size: 1.05rem; }
    .insight-title { font-size: 0.86rem; }
    .insight-text { font-size: 0.78rem; }
}

/* Phones ≤ 575px */
@media (max-width: 575.98px) {
    /* Hero: stack the user info and quick stats vertically */
    .hero-card {
        padding: 16px;
        border-radius: 10px;
    }
    .hero-card .d-flex.gap-3.flex-wrap.align-items-center.justify-content-between {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 14px !important;
    }
    .hero-card .d-flex.gap-3.flex-wrap.align-items-center.justify-content-between > div:last-child {
        width: 100%;
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 10px !important;
    }
    .hero-stat { min-width: 0; width: 100%; padding: 10px 12px; }
    .hero-stat-value { font-size: 1rem; }
    .hero-stat-label { font-size: 0.66rem; }

    /* KPI cards on phones: 2-up grid (already via row-cols-2) — make compact */
    .kpi-card .card-body { padding: 12px 10px 10px; }
    .kpi-icon { width: 34px; height: 34px; font-size: 1rem; }
    .kpi-value { font-size: 0.88rem; }
    .kpi-label { font-size: 0.72rem; }
    .tile-info-btn { display: none; } /* hide info popovers on tiny screens */

    /* Balance tiles in 2-up grid */
    .balance-tile { padding: 12px 10px; }
    .balance-tile .tile-value { font-size: 0.88rem; }
    .balance-tile .tile-label { font-size: 0.74rem; line-height: 1.3; }

    /* Section heads */
    .section-card .section-head { padding: 10px 14px; }
    .section-card .section-head .head-title { font-size: 0.9rem; }
    .section-card .section-head .head-sub { display: none; } /* hide subtitle on phones */

    /* Donut + legend stack vertically */
    .donut-wrap { flex-direction: column; gap: 14px; }
    .donut { width: 110px; height: 110px; margin: 0 auto; }
    .donut::after { inset: 16px; }
    .donut-legend { width: 100%; }

    /* Insights */
    .insight-item { padding: 12px; }
    .insight-icon { width: 32px; height: 32px; font-size: 0.95rem; }

    /* Service timeline endpoint labels narrower */
    .svc-endpoints .svc-lbl strong { font-size: 0.78rem; }
    .svc-stats { flex-wrap: wrap; }
    .svc-stats > * { flex: 1 1 30%; text-align: center; }

    /* Trend bars even tighter */
    .trend-bars { height: 90px; gap: 3px; }

    /* Section page-level container — tighten gutter on mobile */
    .container-fluid.container-p-y { padding-left: 12px !important; padding-right: 12px !important; }
}

/* Extra-tight phones ≤ 374px (older iPhone SE, etc.) */
@media (max-width: 374.98px) {
    .hero-card .d-flex.gap-3.flex-wrap.align-items-center.justify-content-between > div:last-child {
        grid-template-columns: 1fr;
    }
    .kpi-card .card-body { padding: 10px 8px; }
    .kpi-icon { width: 30px; height: 30px; font-size: 0.95rem; }
}
</style>

<?php
// ── Signatory block — REMOVED from dashboard (per user request).
// Pending approvals are visible via sidebar submenu badges (see api/menu-counts.php).
// To re-enable, uncomment the include below.
// if (!empty($isSignatory) && $isSignatory) {
//     include(__DIR__ . '/views/dashboard/signatory-block.php');
// }
?>

<!-- ══════════════════════════════════════════════════════
     Stat KPIs — 7 cards, equal width on xl
═══════════════════════════════════════════════════════ -->
<div class="row row-cols-2 row-cols-md-4 row-cols-xl-7 g-3 mb-4">

    <!-- চাকরিকাল -->
    <div class="col anim-up-d1">
        <div class="kpi-card h-100" style="--kpi-accent:#8b9dc9;--kpi-glow:rgba(105,108,255,0.22);">
            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                data-bs-title="<i class='ti tabler-info-circle me-1'></i> চাকরিকাল"
                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                    <div><strong>কী:</strong> মোট সার্ভিস সময় — যোগদানের তারিখ থেকে আজ পর্যন্ত।</div>
                    <hr>
                    <div><strong>হিসাব:</strong> আজকের তারিখ − যোগদান তারিখ।</div>
                    <div><strong>নোট:</strong> এই সময়ের সব অংশই accrual-এ গণ্য হয় না। Leave-adjusted hissab-টা পাশের <strong>প্রকৃত চাকরিকাল</strong> tile-এ।</div>">
                <i class="ti tabler-info-circle"></i>
            </button>
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon a-indigo"><i class="ti tabler-briefcase"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <p class="kpi-label">চাকরিকাল</p>
                    <p class="kpi-value">
                        <?= $obj->engToBn($employmentyears) ?> বছর
                        <?= $obj->engToBn($employmentmonths) ?> মাস
                        <?= $obj->engToBn($employmentdays) ?> দিন
                    </p>
                </div>
            </div>
            <i class="ti tabler-briefcase kpi-watermark"></i>
        </div>
    </div>

    <!-- গড়-বেতনে ভোগকৃত -->
    <div class="col anim-up-d2">
        <div class="kpi-card h-100" style="--kpi-accent:#c97777;--kpi-glow:rgba(234,84,85,0.22);">
            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                data-bs-title="<i class='ti tabler-info-circle me-1'></i> গড়-বেতনে ভোগকৃত"
                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                    <div><strong>কী:</strong> সার্ভিস জীবনে এ পর্যন্ত ভোগ করা সব পূর্ণ গড় বেতনে ছুটির সমষ্টি।</div>
                    <hr>
                    <div><strong>হিসাব:</strong> Current + previous সব অনুমোদিত পূর্ণ গড়-বেতনে ছুটির যোগফল।</div>
                    <div><strong>Impact:</strong> Accrual হিসাব থেকে এই পরিমাণ বাদ যায় → অবশিষ্ট গড় বেতনে balance।</div>">
                <i class="ti tabler-info-circle"></i>
            </button>
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon a-rose"><i class="ti tabler-calendar-minus"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <p class="kpi-label">গড়-বেতনে ভোগকৃত</p>
                    <p class="kpi-value">
                        <?= $obj->engToBn($fullAvgVugkritoSalLeaveyears) ?> বছর
                        <?= $obj->engToBn($fullAvgVugkritoSalLeavemonths) ?> মাস
                        <?= $obj->engToBn($fullAvgVugkritoSalLeavedays) ?> দিন
                    </p>
                </div>
            </div>
            <i class="ti tabler-calendar-minus kpi-watermark"></i>
        </div>
    </div>

    <!-- অর্ধ-গড় বেতনে ভোগকৃত -->
    <div class="col anim-up-d3">
        <div class="kpi-card h-100" style="--kpi-accent:#7fb5c5;--kpi-glow:rgba(0,207,232,0.22);">
            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                data-bs-title="<i class='ti tabler-info-circle me-1'></i> অর্ধ-গড় বেতনে ভোগকৃত"
                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                    <div><strong>কী:</strong> মোট ভোগকৃত অর্ধ-গড় বেতনে ছুটি (debit হিসাব)।</div>
                    <hr>
                    <div><strong>হিসাব:</strong> ভোগের দিন × ২ (কারণ ১ দিন অর্ধ-গড় = ০.৫ দিন বেতন দাবি, কিন্তু balance থেকে ১ দিন কাটে)।</div>
                    <div><strong>Commutation:</strong> Medical ground-এ পূর্ণ গড়ে convert করা হলে 2:1 ratio-তে debit হবে।</div>">
                <i class="ti tabler-info-circle"></i>
            </button>
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon a-teal"><i class="ti tabler-calendar-stats"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <p class="kpi-label">অর্ধ-গড় বেতনে ভোগকৃত</p>
                    <p class="kpi-value">
                        <?= $obj->engToBn($halfAvgVugkritoSalLeaveyears) ?> বছর
                        <?= $obj->engToBn($halfAvgVugkritoSalLeavemonths) ?> মাস
                        <?= $obj->engToBn($halfAvgVugkritoSalLeavedays) ?> দিন
                    </p>
                </div>
            </div>
            <i class="ti tabler-calendar-stats kpi-watermark"></i>
        </div>
    </div>

    <!-- বিনা বেতনে ভোগকৃত -->
    <div class="col anim-up-d4">
        <div class="kpi-card h-100" style="--kpi-accent:#a89cc4;--kpi-glow:rgba(115,103,240,0.22);">
            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                data-bs-title="<i class='ti tabler-info-circle me-1'></i> বিনা বেতনে ভোগকৃত"
                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                    <div><strong>কী:</strong> বেতন ছাড়া যে ছুটি এ পর্যন্ত নেওয়া হয়েছে।</div>
                    <hr>
                    <div><strong>সীমা:</strong> সার্ভিস জীবনে aggregate <strong>৫ বছর</strong>।</div>
                    <div><strong>Impact:</strong></div>
                    <ul style='margin:4px 0 4px 18px; padding:0;'>
                        <li>প্রকৃত চাকরিকাল থেকে <strong>বাদ যাবে</strong></li>
                        <li>Promotion/seniority-তে গণ্য হবে না</li>
                        <li>Earned leave accrual-ও কমবে</li>
                    </ul>">
                <i class="ti tabler-info-circle"></i>
            </button>
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon a-purple"><i class="ti tabler-cash-off"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <p class="kpi-label">বিনা বেতনে ভোগকৃত</p>
                    <p class="kpi-value">
                        <?= $obj->engToBn($totalWithoutPayyears) ?> বছর
                        <?= $obj->engToBn($totalWithoutPaymonths) ?> মাস
                        <?= $obj->engToBn($totalWithoutPaydays) ?> দিন
                    </p>
                </div>
            </div>
            <i class="ti tabler-cash-off kpi-watermark"></i>
        </div>
    </div>

    <!-- অসাধারণ ভোগকৃত -->
    <div class="col anim-up-d5">
        <div class="kpi-card h-100" style="--kpi-accent:#d4a056;--kpi-glow:rgba(255,159,67,0.22);">
            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                data-bs-title="<i class='ti tabler-info-circle me-1'></i> অসাধারণ ভোগকৃত"
                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা — অসাধারণ ছুটি</span>
                    <div><strong>কী:</strong> Regular leave শেষ হয়ে যাওয়ার পর বা বিশেষ পরিস্থিতিতে নেওয়া বিনা বেতনের ছুটি।</div>
                    <hr>
                    <div><strong>Impact:</strong></div>
                    <ul style='margin:4px 0 4px 18px; padding:0;'>
                        <li>বেতন পাবেন <strong>না</strong></li>
                        <li>প্রকৃত চাকরিকাল থেকে বাদ</li>
                        <li>Promotion গণনায় <strong>গণ্য হবে না</strong></li>
                    </ul>
                    <div><strong>অনুমোদন:</strong> উচ্চতর কর্তৃপক্ষের বিশেষ অনুমোদন লাগে।</div>">
                <i class="ti tabler-info-circle"></i>
            </button>
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon a-amber"><i class="ti tabler-star-off"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <p class="kpi-label">অসাধারণ ভোগকৃত</p>
                    <p class="kpi-value">
                        <?= $obj->engToBn($totalExtraOrdinaryLeaveYears) ?> বছর
                        <?= $obj->engToBn($totalExtraOrdinaryLeaveMonths) ?> মাস
                        <?= $obj->engToBn($totalExtraOrdinaryLeaveDays) ?> দিন
                    </p>
                </div>
            </div>
            <i class="ti tabler-star-off kpi-watermark"></i>
        </div>
    </div>

    <!-- কর্তনহীন ছুটি -->
    <div class="col anim-up-d6">
        <div class="kpi-card h-100" style="--kpi-accent:#475569;--kpi-glow:rgba(71,85,105,0.18);">
            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                data-bs-title="<i class='ti tabler-info-circle me-1'></i> কর্তনহীন ছুটি"
                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা — বিশেষ/চিকিৎসা</span>
                    <div><strong>কী:</strong> যে ছুটি employee-এর ছুটির balance থেকে <strong>কাটা হয় না</strong> (deduction হয় না)।</div>
                    <hr>
                    <div><strong>উদাহরণ:</strong> সরকার-অনুমোদিত quarantine, disability, bond-এর অধীন training ইত্যাদি।</div>
                    <div><strong>Impact:</strong> Accrual বাড়ায় না, কমায়ও না — neutral রেকর্ড।</div>">
                <i class="ti tabler-info-circle"></i>
            </button>
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon a-slate"><i class="ti tabler-shield-check"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <p class="kpi-label">কর্তনহীন ছুটি</p>
                    <p class="kpi-value">
                        <?= $obj->engToBn($totalUndeductibleLeave) ?> দিন
                    </p>
                </div>
            </div>
            <i class="ti tabler-shield-check kpi-watermark"></i>
        </div>
    </div>

    <!-- প্রকৃত চাকরিকাল -->
    <div class="col anim-up-d7">
        <div class="kpi-card h-100" style="--kpi-accent:#7fb59c;--kpi-glow:rgba(40,199,111,0.22);">
            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                data-bs-title="<i class='ti tabler-info-circle me-1'></i> প্রকৃত চাকরিকাল"
                data-bs-content="<span class='rule-ref'>BSR — চাকরির বিধানাবলী, পৃ. ১৪৫</span>
                    <div><strong>কী:</strong> ছুটির হিসাবের জন্য যোগ্য কর্মকাল (qualifying service)।</div>
                    <hr>
                    <div><strong>হিসাব:</strong></div>
                    <div style='background:#f8fafc;padding:8px 10px;border-radius:6px;font-size:0.78rem;margin:4px 0;'>
                        চাকরিকাল − (গড়-বেতনে ভোগকৃত + অর্ধ-গড় বেতনে ভোগকৃত + বিনা বেতনে + অসাধারণ + কর্তনহীন)
                    </div>
                    <div style='font-size:0.72rem;color:#64748b;margin-top:4px;'>* অর্ধ-গড় ছুটি এখানে <strong>প্রকৃত দিন</strong> হিসেবে বাদ যায় (×২ নয় — সেটা শুধু balance accrual-এর জন্য)।</div>
                    <div style='margin-top:6px;'><strong>গুরুত্ব:</strong> এই দিনের উপর ভিত্তি করে <strong>গড় বেতনে accrual (1/11)</strong> ও <strong>অর্ধ-গড় accrual (1/12)</strong> হয়।</div>">
                <i class="ti tabler-info-circle"></i>
            </button>
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon a-green"><i class="ti tabler-clock-check"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <p class="kpi-label">প্রকৃত চাকরিকাল</p>
                    <p class="kpi-value">
                        <?= $obj->engToBn($actualJobDurationInYears) ?> বছর
                        <?= $obj->engToBn($actualJobDurationInMonths) ?> মাস
                        <?= $obj->engToBn($actualJobDurationInDays) ?> দিন
                    </p>
                </div>
            </div>
            <i class="ti tabler-clock-check kpi-watermark"></i>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════
     Leave Balance + PRL Countdown
═══════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4 anim-up-section">

    <!-- Leave Balance Tiles -->
    <div class="col-xl-9">
        <div class="section-card h-100">
            <div class="section-head">
                <span class="head-icon"><i class="ti tabler-wallet"></i></span>
                <div>
                    <h6 class="head-title">অবশিষ্ট পাওনা ছুটি</h6>
                    <div class="head-sub">বর্তমানে ভোগের জন্য প্রাপ্য</div>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 h-100">

                    <!-- গড় বেতনে — ভোগযোগ্য (BSR §12-A: max 4 months at a stretch) -->
                    <div class="col-6 col-md-4 col-xl">
                        <div class="balance-tile" style="--tile-accent:#7fb59c;--tile-glow:rgba(40,199,111,0.22);">
                            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                                data-bs-title="<i class='ti tabler-info-circle me-1'></i> ভোগযোগ্য গড় বেতনে ছুটি"
                                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা — অনুচ্ছেদ ১২-ক</span>
                                    <div><strong>নিয়ম:</strong> একবারে সর্বোচ্চ <strong>১২০ দিন (৪ মাস)</strong> পূর্ণ গড় বেতনে ছুটি নেওয়া যাবে।</div>
                                    <hr>
                                    <div><strong>হিসাব:</strong> min(মোট জমা, ১২০ দিন)</div>
                                    <div><strong>ব্যবহার:</strong> এই পরিমাণ এখনই ছুটির আবেদনের জন্য প্রাপ্য।</div>">
                                <i class="ti tabler-info-circle"></i>
                            </button>
                            <span class="tile-icon" style="background:#e6f8ee;color:#7fb59c;">
                                <i class="ti tabler-check"></i>
                            </span>
                            <div class="tile-label">গড় বেতনে — ভোগযোগ্য</div>
                            <div class="tile-value">
                                <?= $obj->engToBn($fullAvgAvailYears) ?> বছর
                                <?= $obj->engToBn($fullAvgAvailMonths) ?> মাস
                                <?= $obj->engToBn($fullAvgAvailDays) ?> দিন
                            </div>
                            <i class="ti tabler-check tile-watermark"></i>
                        </div>
                    </div>

                    <!-- গড় বেতনে — রিজার্ভ (অবসরে encashable, max 18 months) -->
                    <div class="col-6 col-md-4 col-xl">
                        <div class="balance-tile" style="--tile-accent:#8b9dc9;--tile-glow:rgba(105,108,255,0.22);">
                            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                                data-bs-title="<i class='ti tabler-info-circle me-1'></i> রিজার্ভ গড় বেতনে ছুটি"
                                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা + Encashment নিয়ম</span>
                                    <div><strong>এটা কী:</strong> ১২০ দিনের বেশি জমাকৃত গড় বেতনে ছুটি। পরবর্তীতে কাজে আসবে।</div>
                                    <hr>
                                    <div><strong>ব্যবহার:</strong></div>
                                    <ol style='margin:4px 0 4px 18px; padding:0;'>
                                        <li>অবসরে সর্বোচ্চ <strong>১৮ মাস (৫৪০ দিন)</strong> encash করা যাবে (last-drawn মূল বেতন × ১৮)</li>
                                        <li>Medical emergency-তে বিশেষ অনুমোদন সাপেক্ষে ভোগ (অর্ধ-গড়ে convert)</li>
                                        <li>সার্ভিসে মৃত্যু হলে পরিবার encash পাবে (same 18mo cap)</li>
                                    </ol>
                                    <div><strong>⚠️ সতর্কতা:</strong> ১৮ মাসের বেশি জমলে অতিরিক্তটা <strong>lapse হবে</strong> (অবসরে দেওয়া হবে না)।</div>">
                                <i class="ti tabler-info-circle"></i>
                            </button>
                            <span class="tile-icon" style="background:#eef0ff;color:#8b9dc9;">
                                <i class="ti tabler-archive"></i>
                            </span>
                            <div class="tile-label">গড় বেতনে — রিজার্ভ</div>
                            <div class="tile-value">
                                <?php if ($fullAvgReserveTotal > 0): ?>
                                    <?= $obj->engToBn($fullAvgReserveYears) ?> বছর
                                    <?= $obj->engToBn($fullAvgReserveMonths) ?> মাস
                                    <?= $obj->engToBn($fullAvgReserveDays) ?> দিন
                                <?php else: ?>
                                    <span style="color:#9ca3af;font-weight:600;">—</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($fullAvgReserveTotal > 0): ?>
                                <div class="tile-progress-wrap">
                                    <div class="tile-progress">
                                        <div class="tile-progress-bar <?= $encashOverLimit ? 'over' : ($encashPct >= 90 ? 'warn' : '') ?>" style="width: <?= $encashPct ?>%;"></div>
                                    </div>
                                    <div class="tile-progress-cap">
                                        <span>Encash cap ১৮ মাস</span>
                                        <?php if ($encashOverLimit): ?>
                                            <span class="cap-warn"><?= $obj->engToBn($encashExcessDays) ?> দিন lapse হবে</span>
                                        <?php else: ?>
                                            <span><?= $obj->engToBn($encashPct) ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <i class="ti tabler-archive tile-watermark"></i>
                        </div>
                    </div>

                    <!-- অর্ধ-গড় বেতনে -->
                    <div class="col-6 col-md-4 col-xl">
                        <div class="balance-tile" style="--tile-accent:#7fb5c5;--tile-glow:rgba(0,207,232,0.22);">
                            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                                data-bs-title="<i class='ti tabler-info-circle me-1'></i> অর্ধ-গড় বেতনে ছুটি"
                                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                                    <div><strong>Accrual:</strong> প্রতি ১২ দিন যোগ্য কাজের জন্য ১ দিন অর্ধ-গড় ছুটি (প্রায় ২০ দিন/বছর)।</div>
                                    <hr>
                                    <div><strong>ব্যবহার:</strong> সাধারণ কারণ এবং চিকিৎসা উভয়ের জন্য।</div>
                                    <div><strong>Commutation:</strong> Medical certificate থাকলে পূর্ণ গড়ে convert করা যাবে — <strong>1 day full = 2 days half</strong> debit।</div>">
                                <i class="ti tabler-info-circle"></i>
                            </button>
                            <span class="tile-icon" style="background:#e0f9fc;color:#7fb5c5;">
                                <i class="ti tabler-chart-bar"></i>
                            </span>
                            <div class="tile-label">অর্ধ-গড় বেতনে</div>
                            <div class="tile-value">
                                <?= $obj->engToBn($halfAvgRestSalLeaveyears) ?> বছর
                                <?= $obj->engToBn($halfAvgRestSalLeavemonths) ?> মাস
                                <?= $obj->engToBn($halfAvgRestSalLeavedays) ?> দিন
                            </div>
                            <i class="ti tabler-chart-bar tile-watermark"></i>
                        </div>
                    </div>

                    <!-- নৈমিত্তিক -->
                    <div class="col-6 col-md-4 col-xl">
                        <div class="balance-tile" style="--tile-accent:#7fb59c;--tile-glow:rgba(40,199,111,0.22);">
                            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                                data-bs-title="<i class='ti tabler-info-circle me-1'></i> নৈমিত্তিক (Casual) ছুটি"
                                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                                    <div><strong>Balance:</strong> ২০ দিন প্রতি calendar year।</div>
                                    <hr>
                                    <div><strong>সীমা:</strong></div>
                                    <ul style='margin:4px 0 4px 18px; padding:0;'>
                                        <li>একটানা সর্বোচ্চ <strong>১০ দিন</strong></li>
                                        <li>অন্য ধরনের ছুটির সাথে combine করা <strong>যাবে না</strong></li>
                                        <li>৩১ ডিসেম্বরে অব্যবহৃত ছুটি <strong>lapse হবে</strong> (carry-forward নেই)</li>
                                    </ul>">
                                <i class="ti tabler-info-circle"></i>
                            </button>
                            <span class="tile-icon" style="background:#e6f8ee;color:#7fb59c;">
                                <i class="ti tabler-calendar-event"></i>
                            </span>
                            <div class="tile-label">নৈমিত্তিক</div>
                            <div class="tile-value">
                                <?= $obj->engToBn($casualCurrentBalance) ?> দিন
                            </div>
                            <i class="ti tabler-calendar-event tile-watermark"></i>
                        </div>
                    </div>

                    <!-- বিনা বেতনে -->
                    <div class="col-6 col-md-4 col-xl">
                        <div class="balance-tile" style="--tile-accent:#a89cc4;--tile-glow:rgba(115,103,240,0.22);">
                            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                                data-bs-title="<i class='ti tabler-info-circle me-1'></i> বিনা বেতনে ছুটি"
                                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                                    <div><strong>Rule:</strong> সার্ভিস জীবনে aggregate <strong>৫ বছর</strong> পর্যন্ত নেওয়া যাবে।</div>
                                    <hr>
                                    <div><strong>প্রভাব:</strong></div>
                                    <ul style='margin:4px 0 4px 18px; padding:0;'>
                                        <li>এই সময়ে বেতন পাবেন <strong>না</strong></li>
                                        <li>প্রকৃত চাকরিকাল থেকে <strong>বাদ যাবে</strong></li>
                                        <li>Promotion/seniority গণনায় <strong>গণ্য হবে না</strong></li>
                                    </ul>">
                                <i class="ti tabler-info-circle"></i>
                            </button>
                            <span class="tile-icon" style="background:#eeebfb;color:#a89cc4;">
                                <i class="ti tabler-cash-off"></i>
                            </span>
                            <div class="tile-label">বিনা বেতনে</div>
                            <div class="tile-value">
                                <?= $obj->engToBn($totalWithoutPayyears) ?> বছর
                                <?= $obj->engToBn($totalWithoutPaymonths) ?> মাস
                                <?= $obj->engToBn($totalWithoutPaydays) ?> দিন
                            </div>
                            <i class="ti tabler-cash-off tile-watermark"></i>
                        </div>
                    </div>

                    <!-- ঐচ্ছিক ছুটি -->
                    <div class="col-6 col-md-4 col-xl">
                        <div class="balance-tile" style="--tile-accent:#d4a056;--tile-glow:rgba(255,159,67,0.22);">
                            <button type="button" class="tile-info-btn" data-bs-toggle="popover" data-bs-html="true" data-bs-custom-class="rule-popover" data-bs-trigger="focus" tabindex="0"
                                data-bs-title="<i class='ti tabler-info-circle me-1'></i> ঐচ্ছিক ছুটি"
                                data-bs-content="<span class='rule-ref'>সরকারি চাকরি বিধিমালা</span>
                                    <div><strong>Balance:</strong> ৩ দিন প্রতি calendar year।</div>
                                    <hr>
                                    <div><strong>ব্যবহার:</strong> সরকার কর্তৃক ঘোষিত <strong>ঐচ্ছিক ছুটির তালিকা</strong> থেকে বেছে নিতে হবে (ধর্মীয় / সাংস্কৃতিক দিবস)।</div>
                                    <div><strong>Lapse:</strong> ৩১ ডিসেম্বরে অব্যবহৃত ছুটি বাতিল হবে (carry-forward নেই)।</div>">
                                <i class="ti tabler-info-circle"></i>
                            </button>
                            <span class="tile-icon" style="background:#fff3e5;color:#d4a056;">
                                <i class="ti tabler-star"></i>
                            </span>
                            <div class="tile-label">ঐচ্ছিক ছুটি</div>
                            <div class="tile-value">
                                <?= $obj->engToBn($optionalLeaveCurrentBalance) ?> দিন
                            </div>
                            <i class="ti tabler-star tile-watermark"></i>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- PRL Countdown -->
    <div class="col-xl-3">
        <div class="prl-card">
            <div class="prl-head">
                <div class="prl-head-row">
                    <span class="prl-head-icon"><i class="ti tabler-sunset-2"></i></span>
                    <h6 class="prl-title">অবসর গ্রহণের তারিখ</h6>
                </div>
                <p class="prl-date" id="prlDateDisplay">--/--/----</p>
            </div>
            <div class="prl-body">
                <div class="prl-units">
                    <div class="prl-unit-box">
                        <span class="prl-num" id="years">0</span>
                        <span class="prl-lbl">বছর</span>
                    </div>
                    <div class="prl-unit-box">
                        <span class="prl-num" id="months">0</span>
                        <span class="prl-lbl">মাস</span>
                    </div>
                    <div class="prl-unit-box">
                        <span class="prl-num" id="days">0</span>
                        <span class="prl-lbl">দিন</span>
                    </div>
                </div>
            </div>
            <div class="prl-foot">
                <i class="ti tabler-clock me-1"></i>অবসরের বাকি সময়
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════
     পরামর্শ (Insights) — personalized suggestions
═══════════════════════════════════════════════════════ -->
<div class="row mb-4 anim-up-section">
    <div class="col-12">
        <div class="section-card collapsible-card">
            <div class="section-head">
                <span class="head-icon"><i class="ti tabler-bulb"></i></span>
                <div>
                    <h6 class="head-title">আপনার জন্য পরামর্শ</h6>
                    <div class="head-sub">ব্যক্তিগত অবস্থার উপর ভিত্তি করে স্মার্ট সাজেশন</div>
                </div>
            </div>
            <ul class="insights-list">
                <?php foreach ($insights as $ins): ?>
                    <li class="insight-item kind-<?= htmlspecialchars($ins['kind']) ?>">
                        <span class="insight-icon">
                            <i class="ti <?= htmlspecialchars($ins['icon']) ?>"></i>
                        </span>
                        <div class="insight-body">
                            <p class="insight-title"><?= $ins['title'] /* already-encoded Bangla */ ?></p>
                            <p class="insight-text"><?= $ins['text'] ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     ছুটির বিশ্লেষণ (Analytics) — donut + timeline + trend
═══════════════════════════════════════════════════════ -->
<div class="row mb-4 anim-up-section">
    <div class="col-12">
        <div class="section-card collapsible-card">
            <div class="section-head">
                <span class="head-icon"><i class="ti tabler-chart-pie"></i></span>
                <div>
                    <h6 class="head-title">ছুটির বিশ্লেষণ</h6>
                    <div class="head-sub">ভিজ্যুয়াল সারসংক্ষেপ, timeline ও মাসিক ট্রেন্ড</div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">

                    <!-- Panel 1: Donut — Leave usage breakdown -->
                    <div class="col-lg-5 analytics-panel">
                        <h6>ছুটির বণ্টন (ভোগকৃত)</h6>
                        <div class="panel-sub">লাইফটাইম মোট ব্যবহৃত ছুটির ধরণ অনুযায়ী</div>
                        <div class="donut-wrap">
                            <div class="donut" style="--donut-bg: <?= $donutGradient ?>;">
                                <div class="donut-center">
                                    <div class="num"><?= $obj->engToBn($totalUsedDays) ?></div>
                                    <div class="lbl">মোট দিন</div>
                                </div>
                            </div>
                            <div class="donut-legend">
                                <?php foreach ($usageSegments as $seg): ?>
                                    <?php if ($seg['days'] <= 0) continue; ?>
                                    <div class="lg-row">
                                        <span class="lg-dot" style="background:<?= $seg['color'] ?>;"></span>
                                        <span class="lg-label"><?= $seg['label'] ?></span>
                                        <span class="lg-val"><?= $obj->engToBn($seg['days']) ?> দিন</span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($totalUsedDays === 0): ?>
                                    <div style="color:#9ca3af; font-size:0.85rem; text-align:center; padding:20px 0;">
                                        <i class="ti tabler-calendar-off" style="font-size:1.5rem; display:block; margin-bottom:6px;"></i>
                                        কোনো ছুটি ভোগ করা হয়নি
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Panel 2: Service timeline -->
                    <div class="col-lg-7 analytics-panel">
                        <h6>চাকরির Timeline</h6>
                        <div class="panel-sub">যোগদান থেকে অবসর পর্যন্ত যাত্রা</div>
                        <?php if ($timelineOK): ?>
                            <div class="svc-timeline">
                                <div class="svc-track">
                                    <div class="svc-fill" style="width: <?= $timelinePercent ?>%;"></div>
                                    <div class="svc-marker start" title="যোগদান"></div>
                                    <div class="svc-marker now" style="left: <?= max(2, min(98, $timelinePercent)) ?>%;" title="আজ"></div>
                                    <div class="svc-marker end" title="অবসর"></div>
                                </div>
                                <div class="svc-endpoints">
                                    <div class="svc-lbl">
                                        <strong><?= $obj->engToBn(date('d/m/Y', strtotime($joiningDate))) ?></strong>
                                        যোগদান
                                    </div>
                                    <div class="svc-lbl" style="color:#8b9dc9;">
                                        <strong><?= $obj->engToBn($timelinePercent) ?>% সম্পন্ন</strong>
                                        আজ
                                    </div>
                                    <div class="svc-lbl">
                                        <strong><?= $obj->engToBn(date('d/m/Y', strtotime($retireDate))) ?></strong>
                                        অবসর<?php if ($retireDerived): ?> <small style="opacity:0.6;" title="DOB + ৫৯ বছর থেকে হিসাব করা">(আনুমানিক)</small><?php endif; ?>
                                    </div>
                                </div>
                                <div class="svc-stats">
                                    <div>
                                        <div class="s-num"><?= $obj->engToBn($yearsServed) ?> বছর</div>
                                        <div class="s-lbl">কাটানো হয়েছে</div>
                                    </div>
                                    <div>
                                        <div class="s-num" style="color:#7fb59c;"><?= $obj->engToBn($yearsRemaining) ?> বছর</div>
                                        <div class="s-lbl">অবসরের বাকি</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="padding: 24px; background:#fafbff; border:1px dashed #e5e7eb; border-radius:10px; text-align:center; color:#6b7280;">
                                <i class="ti tabler-calendar-x" style="font-size:1.8rem; display:block; margin-bottom:8px;"></i>
                                যোগদান / অবসর তারিখ সেট করা নেই
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 3: Monthly trend -->
                    <div class="col-12 analytics-panel">
                        <h6>মাসিক ছুটির প্যাটার্ন (শেষ ১২ মাস)</h6>
                        <div class="panel-sub">অনুমোদিত ছুটির মাসিক সমষ্টি</div>
                        <div class="trend-bars">
                            <?php foreach ($trendMonths as $m): ?>
                                <?php
                                $hpct = $m['days'] > 0 ? max(10, round(($m['days'] / $trendMaxDays) * 100)) : 0;
                                $isZero = $m['days'] === 0;
                                ?>
                                <div class="trend-col">
                                    <?php if (!$isZero): ?>
                                        <div class="trend-val"><?= $obj->engToBn($m['days']) ?></div>
                                    <?php endif; ?>
                                    <div class="trend-bar <?= $isZero ? 'zero' : '' ?>" style="height: <?= $hpct ?>%;" title="<?= $m['label'] ?>: <?= $obj->engToBn($m['days']) ?> দিন"></div>
                                    <div class="trend-label"><?= $m['label'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- My Leave Applications Widget -->
<div class="row mb-4 anim-up-section">
    <div class="col-12">
        <div class="section-card collapsible-card">
            <div class="section-head justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="head-icon"><i class="ti tabler-clipboard-list"></i></span>
                    <div>
                        <h6 class="head-title">আমার ছুটির আবেদন</h6>
                        <div class="head-sub">সর্বশেষ আবেদনসমূহ ও অনুমোদন অবস্থা</div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/views/leave/all-applications.php?menuslug=all-leave-application" class="btn-link-soft" onclick="event.stopPropagation();">
                    সব দেখুন <i class="ti tabler-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="card-body p-0">
                <?php if (mysqli_num_rows($myLeaveAppsQ) == 0): ?>
                    <div class="empty-state">
                        <span class="es-icon"><i class="ti tabler-calendar-off"></i></span>
                        <p class="es-title">কোনো ছুটির আবেদন পাওয়া যায়নি</p>
                        <div class="es-sub">নতুন আবেদন তৈরি করুন</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table leaves-table" style="min-width: 650px;">
                            <thead>
                                <tr>
                                    <th>ছুটির ধরণ</th>
                                    <th>তারিখ</th>
                                    <th class="text-center">দিন</th>
                                    <th class="text-center">স্ট্যাটাস</th>
                                    <th>অনুমোদনকারীগণ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($leaveApp = mysqli_fetch_assoc($myLeaveAppsQ)): ?>
                                    <?php
                                    // Get signatory chain for this leave
                                    $signatoryQ = mysqli_query($con, "
                                        SELECT ldfa.isApproved, ldfa.serial, ldfa.approvedDate,
                                               el.employee_name, el.photo, jt.job_title_name
                                        FROM leave_data_for_approval ldfa
                                        LEFT JOIN employee_list el ON ldfa.signatory = el.id
                                        LEFT JOIN job_title jt ON el.designation = jt.id
                                        WHERE ldfa.leaveApplicationID = '{$leaveApp['dataID']}'
                                        ORDER BY ldfa.serial ASC
                                    ");

                                    $signatories = [];
                                    $lastSigned = null;
                                    while ($sig = mysqli_fetch_assoc($signatoryQ)) {
                                        $signatories[] = $sig;
                                        if ($sig['isApproved'] == 1) {
                                            $lastSigned = $sig;
                                        }
                                    }

                                    // Status badge + row accent stripe color
                                    if ($leaveApp['status'] == 1) {
                                        $statusHtml = '<span class="lt-badge approved">অনুমোদিত</span>';
                                        $rowAccent  = '#7fb59c';
                                    } elseif ($leaveApp['status'] == 0) {
                                        $statusHtml = '<span class="lt-badge pending">প্রক্রিয়াধীন</span>';
                                        $rowAccent  = '#d4a056';
                                    } else {
                                        $statusHtml = '<span class="lt-badge declined">বাতিল</span>';
                                        $rowAccent  = '#c97777';
                                    }

                                    // Dates
                                    $dateFrom = date('d/m/Y', strtotime($leaveApp['dateFrom']));
                                    $dateTo   = date('d/m/Y', strtotime($leaveApp['dateTo']));
                                    $diffDays = (int)((strtotime($leaveApp['dateTo']) - strtotime($leaveApp['dateFrom'])) / 86400) + 1;
                                    $leaveDays = ($leaveApp['approvedDays'] > 0) ? $leaveApp['approvedDays'] : $diffDays;
                                    ?>
                                    <tr style="--row-accent: <?= $rowAccent ?>;">
                                        <td>
                                            <div class="lt-type">
                                                <?= htmlspecialchars($leaveApp['leaveTypeName'] ?? 'অজানা') ?>
                                                <?php if ((int)($leaveApp['segCount'] ?? 0) > 1): ?>
                                                    <span class="badge bg-label-info" style="font-size:0.65rem;font-weight:600;margin-left:4px;" title="একাধিক ধরন">
                                                        +<?= $obj->engToBn((int)$leaveApp['segCount'] - 1) ?> ধরন
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($leaveApp['subject'])): ?>
                                                <div class="lt-subject" title="<?= htmlspecialchars($leaveApp['subject']) ?>">
                                                    <?= htmlspecialchars($leaveApp['subject']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="lt-date">
                                                <i class="ti tabler-calendar-event me-1" style="color:#9aa0b5;"></i>
                                                <?= $obj->engToBn($dateFrom) ?>&nbsp;—&nbsp;<?= $obj->engToBn($dateTo) ?>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <span class="lt-days"><?= $obj->engToBn($leaveDays) ?></span>
                                        </td>

                                        <td class="text-center"><?= $statusHtml ?></td>

                                        <td>
                                            <?php if (!empty($signatories)): ?>
                                                <div class="d-flex align-items-center flex-wrap" style="gap:3px;">
                                                    <?php foreach ($signatories as $idx => $sig):
                                                        if ($sig['isApproved'] == 1) {
                                                            $cBg = '#e6f8ee'; $cBorder = '#7fb59c'; $cText = '#5fa885';
                                                            $cLabel = 'অনুমোদন করেছেন';
                                                        } elseif ($sig['isApproved'] == 2) {
                                                            $cBg = '#fde7e7'; $cBorder = '#c97777'; $cText = '#a06262';
                                                            $cLabel = 'প্রত্যাখ্যান করেছেন';
                                                        } else {
                                                            $cBg = '#f3f5f8'; $cBorder = '#cbd2da'; $cText = '#6b7280';
                                                            $cLabel = 'অপেক্ষায় আছেন';
                                                        }
                                                        $initials  = mb_strtoupper(mb_substr($sig['employee_name'] ?? '?', 0, 2));
                                                        $photoFile = !empty($sig['photo']) ? BASE_URL . '/uploads/' . $sig['photo'] : '';
                                                    ?>
                                                        <?php if ($idx > 0): ?>
                                                            <span class="sig-chain-sep">›</span>
                                                        <?php endif; ?>
                                                        <div class="sig-avatar"
                                                             style="border:2px solid <?= $cBorder ?>; background: <?= $photoFile ? '#f0f0f0' : $cBg ?>; color: <?= $cText ?>;"
                                                             title="<?= htmlspecialchars(($sig['employee_name'] ?? '') . ' (' . ($sig['job_title_name'] ?? '') . ') — ' . $cLabel) ?>"
                                                             data-bs-toggle="tooltip" data-bs-placement="top">
                                                            <?php if ($photoFile): ?>
                                                                <img src="<?= $photoFile ?>"
                                                                     alt="<?= htmlspecialchars($sig['employee_name'] ?? '') ?>"
                                                                     style="width:100%; height:100%; object-fit:cover; display:block;"
                                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                                                                <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center; background:<?= $cBg ?>; color:<?= $cText ?>;"><?= $initials ?></span>
                                                            <?php else: ?>
                                                                <?= $initials ?>
                                                            <?php endif; ?>
                                                            <span class="sig-dot" style="background: <?= $cBorder ?>;"></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <?php if ($lastSigned): ?>
                                                    <div class="sig-meta">
                                                        <i class="ti tabler-pencil me-1" style="color:#7fb59c;"></i>শেষ স্বাক্ষর:
                                                        <span class="signed"><?= htmlspecialchars($lastSigned['employee_name']) ?></span>
                                                    </div>
                                                <?php elseif ($leaveApp['status'] == 0 && !empty($signatories)): ?>
                                                    <div class="sig-meta">
                                                        <i class="ti tabler-clock me-1" style="color:#d4a056;"></i>
                                                        <span class="waiting">অনুমোদনের অপেক্ষায়</span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.78rem;">অনুমোদনকারী নেই</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Absent Employees Table (if dashboardType == 2) -->
<?php if($getUserInfoQRW['dashboardType'] == 2){ ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">ছুটি হইতে কর্মস্থলে যোগদান করেননি</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="absent_employees" class="table table-hover" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>কর্মকর্তা/কর্মচারীর নাম</th>
                                <th>আইডি</th>
                                <th>পদবী</th>
                                <th>শাখা</th>
                                <th>যোগদানের তারিখ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be inserted here dynamically by DataTables -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<script>
// ── Collapsible cards: default collapsed, smooth expand on header click ──
function initCollapsibleCards() {
    document.querySelectorAll('.section-card.collapsible-card').forEach(function(card) {
        if (card.dataset.collapsibleInit === '1') return;
        card.dataset.collapsibleInit = '1';

        var head = card.querySelector(':scope > .section-head');
        if (!head) return;

        // Wrap all siblings after .section-head into a single body-wrap element
        var wrap = document.createElement('div');
        wrap.className = 'section-body-wrap';
        var next = head.nextElementSibling;
        while (next) {
            var toMove = next;
            next = next.nextElementSibling;
            wrap.appendChild(toMove);
        }
        card.appendChild(wrap);

        // Add chevron icon to header
        if (!head.querySelector('.section-chevron')) {
            var chev = document.createElement('i');
            chev.className = 'ti tabler-chevron-down section-chevron';
            head.appendChild(chev);
        }

        // Default collapsed
        wrap.style.maxHeight = '0px';

        head.addEventListener('click', function(e) {
            // Don't collapse if user clicked an inner link/button (e.g., "সব দেখুন")
            if (e.target.closest('a, button')) return;

            var isOpen = card.classList.toggle('is-open');
            if (isOpen) {
                // Measure natural height for smooth expand
                wrap.style.maxHeight = wrap.scrollHeight + 'px';
                // After transition, allow content to grow naturally (for dynamic content)
                setTimeout(function() {
                    if (card.classList.contains('is-open')) {
                        wrap.style.maxHeight = 'none';
                    }
                }, 380);
            } else {
                // Set explicit height first so the transition works when collapsing
                wrap.style.maxHeight = wrap.scrollHeight + 'px';
                requestAnimationFrame(function() {
                    wrap.style.maxHeight = '0px';
                });
            }
        });
    });
}

$(document).ready(function() {
    initCollapsibleCards();

    // Initialize tooltips for signatory avatars
    var tooltipEls = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipEls.forEach(function(el) { new bootstrap.Tooltip(el); });

    // Initialize BSR rule popovers on balance tiles
    var popoverEls = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverEls.forEach(function(el) {
        if (!bootstrap.Popover.getInstance(el)) {
            new bootstrap.Popover(el, { html: true, sanitize: false });
        }
    });

    <?php if($getUserInfoQRW['dashboardType'] == 2){ ?>
    // Destroy the DataTable if it's already initialized
    if ($.fn.dataTable.isDataTable('#absent_employees')) {
        $('#absent_employees').DataTable().destroy();
    }

    // Reinitialize the DataTable
    $('#absent_employees').DataTable({
        "processing": true,
        "serverSide": true,
        "responsive": true,
        "ajax": {
            "url": "get_absent_employees.php",
            "type": "POST"
        },
        "columns": [
            { "data": "serial" },
            { "data": "employee_name" },
            { "data": "employee_id" },
            { "data": "job_title" },
            { "data": "section_name" },
            { "data": "joining_date" }
        ],
        "columnDefs": [
            // Column priorities: lower = hidden first on small screens
            { "responsivePriority": 1, "targets": 1 },   // Name — keep visible
            { "responsivePriority": 2, "targets": -1 },  // Joining date — keep visible
            { "responsivePriority": 3, "targets": 0 },   // Serial
            { "responsivePriority": 4, "targets": 2 },   // ID
            { "responsivePriority": 5, "targets": 3 },   // Designation
            { "responsivePriority": 6, "targets": 4 }    // Section — hides first
        ],
        "language": {
            "search": "খুঁজুন:",
            "lengthMenu": "প্রদর্শন করুন _MENU_ এন্ট্রি",
            "info": "মোট _TOTAL_ এন্ট্রির মধ্যে _START_ থেকে _END_ প্রদর্শন করা হচ্ছে",
            "infoEmpty": "কোন এন্ট্রি পাওয়া যায়নি",
            "paginate": {
                "first": "প্রথম",
                "last": "শেষ",
                "next": "পরবর্তী",
                "previous": "পূর্ববর্তী"
            },
            "processing": "প্রক্রিয়াকরণ..."
        }
    });
    <?php } ?>
});

function fetchCountdown() {
    fetch('prlcountdown.php')
        .then(response => response.json())
        .then(data => updateCountdown(data))
        .catch(error => console.error('Error fetching countdown:', error));
}

function updateCountdown(data) {
    document.getElementById('years').textContent = data.years;
    document.getElementById('months').textContent = data.months;
    document.getElementById('days').textContent = data.days;
    document.getElementById('prlDateDisplay').textContent = data.prl_date;
}

// Initial load
fetchCountdown();

// Update countdown every day
setInterval(fetchCountdown, 24 * 60 * 60 * 1000); // 24 hours in milliseconds
</script>
