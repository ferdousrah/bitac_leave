<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$employeeID = base64_decode($_GET['employeeID'] ?? '');
if (empty($employeeID)) {
    echo "<script>window.history.go(-1);</script>";
    exit;
}

// Fetch employee with joins
$stmt = $con->prepare("
    SELECT e.*, o.organization_name, s.section_name, d.job_title_name
    FROM employee_list e
    LEFT JOIN organization o ON e.organization_id = o.id
    LEFT JOIN sections    s ON s.id = e.section_id
    LEFT JOIN job_title   d ON d.id = e.designation
    WHERE e.id = ?
");
$stmt->bind_param("s", $employeeID);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
    echo "<div class='alert alert-warning'>কর্মকর্তার তথ্য পাওয়া যায়নি!</div>";
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Salary increments
$incrementRows = [];
$stmt2 = $con->prepare("SELECT * FROM yearly_salary_increment WHERE employeeID = ? AND status = 1 ORDER BY incrementYear ASC");
if ($stmt2) {
    $stmt2->bind_param("s", $employeeID);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($r2 = $res2->fetch_assoc()) { $incrementRows[] = $r2; }
    $stmt2->close();
}

// Leave types
$leaveTypesQ = mysqli_query($con, "SELECT * FROM leave_types");

// Leave info via reusable function
$leaveInfo = getEmployeeLeaveInfo($employeeID);

// Date formatting
$birthDate   = !empty($emp['date_of_birth'])  ? date('d/m/Y', strtotime($emp['date_of_birth']))  : '—';
$joiningDate = !empty($emp['joining_date'])    ? date('d M Y', strtotime($emp['joining_date']))   : '—';
$photo       = $emp['photo'] ?? '';
$initial     = strtoupper(mb_substr($emp['employee_name'] ?? 'E', 0, 1));
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">কর্মকর্তা/কর্মচারীর তথ্য</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-label-secondary no-print" onclick="window.print()">
            <i class="ti tabler-printer me-1"></i>প্রিন্ট
        </button>
        <button type="button" onclick="window.history.go(-1);" class="btn btn-sm btn-label-secondary no-print">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<div id="printArea">

<!-- ── Personal Info Card ── -->
<div class="card mb-4" style="border:none; box-shadow:0 4px 24px rgba(58,61,83,0.12); overflow:hidden;">

    <!-- Dark banner -->
    <div style="background:linear-gradient(135deg,#3A3D53 0%,#5a5f78 100%); padding:20px 24px 0; position:relative; overflow:hidden;">
        <div style="position:absolute; inset:0; opacity:0.07; background:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%); background-size:12px 12px;"></div>
        <div class="d-flex align-items-end gap-4 position:relative;" style="z-index:1;">
            <!-- Avatar -->
            <div style="flex-shrink:0; margin-bottom:-28px;">
                <?php if (!empty($photo)): ?>
                    <img src="../../uploads/<?= htmlspecialchars($photo) ?>"
                         style="width:90px; height:90px; border-radius:50%; object-fit:cover; border:4px solid rgba(255,255,255,0.3); box-shadow:0 4px 16px rgba(0,0,0,0.25);" />
                <?php else: ?>
                    <div style="width:90px; height:90px; border-radius:50%; background:rgba(255,255,255,0.18); border:4px solid rgba(255,255,255,0.3); display:flex; align-items:center; justify-content:center; font-size:2.2rem; font-weight:700; color:#fff;"><?= $initial ?></div>
                <?php endif; ?>
            </div>
            <!-- Name + designation on banner -->
            <div class="pb-3">
                <h5 class="text-white fw-bold mb-1" style="font-size:1.05rem;"><?= htmlspecialchars($emp['employee_name'] ?? '') ?></h5>
                <div style="font-size:0.82rem; color:rgba(255,255,255,0.7);"><?= htmlspecialchars($emp['job_title_name'] ?? '') ?></div>
                <span style="background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.9); border-radius:20px; padding:2px 12px; font-size:0.7rem; font-weight:600; display:inline-block; margin-top:5px;"><?= htmlspecialchars($emp['organization_name'] ?? 'BITAC') ?></span>
            </div>
        </div>
    </div>

    <!-- Info grid -->
    <div class="card-body pt-5">
        <div class="row g-3">
            <?php
            $infoItems = [
                ['tabler-id-badge-2', '#eef2ff', '#696cff', 'আইডি',             $obj->engToBn($emp['employee_id'] ?? '')],
                ['tabler-building',   '#fff3e0', '#ff9f43', 'শাখা',             $emp['section_name'] ?? '—'],
                ['tabler-calendar',   '#e8f9f0', '#28c76f', 'যোগদানের তারিখ',  $obj->engToBn($joiningDate)],
                ['tabler-cake',       '#fff0f0', '#ea5455', 'জন্ম তারিখ',      $obj->engToBn($birthDate)],
                ['tabler-mail',       '#f3f0ff', '#9c27b0', 'ইমেইল',           $emp['email'] ?? '—'],
                ['tabler-phone',      '#e0f7fa', '#00bcd4', 'মোবাইল',          $obj->engToBn($emp['mobileNo'] ?? '')],
            ];
            foreach ($infoItems as [$icon, $bg, $color, $label, $value]): ?>
            <div class="col-12 col-sm-6 col-xl-4">
                <div style="display:flex; align-items:center; gap:12px; background:#fafbff; border-radius:12px; padding:12px 14px;">
                    <span style="flex-shrink:0; width:36px; height:36px; border-radius:10px; background:<?= $bg ?>; display:flex; align-items:center; justify-content:center;">
                        <i class="ti <?= $icon ?>" style="color:<?= $color ?>; font-size:1rem;"></i>
                    </span>
                    <div style="min-width:0;">
                        <div style="font-size:0.68rem; color:#adb5bd; text-transform:uppercase; letter-spacing:.4px;"><?= $label ?></div>
                        <div style="font-size:0.87rem; font-weight:600; color:#3A3D53;" class="text-truncate"><?= $value ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Leave Info Card ── -->
<div class="card mb-4" style="border:none; box-shadow:0 4px 24px rgba(58,61,83,0.10);">
    <div class="card-body p-0">
        <div style="background:linear-gradient(135deg,#3A3D53,#5a5f78); padding:14px 20px; border-radius:calc(var(--bs-card-border-radius) - 1px) calc(var(--bs-card-border-radius) - 1px) 0 0;">
            <h6 class="text-white mb-0 fw-semibold" style="font-size:0.88rem;"><i class="ti tabler-calendar-stats me-2"></i>ছুটির হিসাব</h6>
        </div>
        <div class="p-3 p-md-4">

            <!-- Service duration -->
            <div class="row g-3 mb-4">
                <?php
                $dur = $leaveInfo['employment'];
                $durCards = [
                    ['চাকরির সময়কাল',         $obj->engToBn($dur['years']).'বছর '.$obj->engToBn($dur['months']).'মাস '.$obj->engToBn($dur['days']).'দিন', 'tabler-clock', '#eef2ff', '#696cff'],
                    ['নৈমিত্তিক পাওনা ছুটি',   $obj->engToBn($leaveInfo['casual']['balance']).' দিন',                                                   'tabler-sun',   '#e8f9f0', '#28c76f'],
                    ['গড়-বেতনে পাওনা ছুটি',    $obj->engToBn($leaveInfo['fullAvgBalance']['years']).'বছর '.$obj->engToBn($leaveInfo['fullAvgBalance']['months']).'মাস '.$obj->engToBn($leaveInfo['fullAvgBalance']['days']).'দিন', 'tabler-beach', '#fff3e0', '#ff9f43'],
                    ['অর্ধ-গড় বেতনে পাওনা ছুটি', $obj->engToBn($leaveInfo['halfAvgBalance']['years']).'বছর '.$obj->engToBn($leaveInfo['halfAvgBalance']['months']).'মাস '.$obj->engToBn($leaveInfo['halfAvgBalance']['days']).'দিন', 'tabler-beach-off', '#f3f0ff', '#9c27b0'],
                ];
                foreach ($durCards as [$label, $val, $icon, $bg, $color]): ?>
                <div class="col-6 col-xl-3">
                    <div style="border-radius:12px; padding:14px; background:<?= $bg ?>; text-align:center;">
                        <i class="ti <?= $icon ?>" style="font-size:1.4rem; color:<?= $color ?>; display:block; margin-bottom:6px;"></i>
                        <div style="font-size:0.68rem; color:#6c757d; margin-bottom:4px;"><?= $label ?></div>
                        <div style="font-size:0.88rem; font-weight:700; color:#3A3D53;"><?= $val ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Leave usage table -->
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0" style="font-size:0.875rem;">
                    <thead style="background:#3A3D53; color:#fff;">
                        <tr>
                            <th>ক্রমিক</th>
                            <th>ছুটির ধরণ</th>
                            <th class="text-center">ভোগকৃত (দিন)</th>
                            <th class="text-center">গড় বেতনে (দিন)</th>
                            <th class="text-center">অর্ধ-গড় বেতনে (দিন)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $ltSl = 0;
                    while ($ltRow = mysqli_fetch_assoc($leaveTypesQ)):
                        $ltSl++;
                        if ($ltRow['leaveID'] == 8) {
                            $r = mysqli_fetch_assoc(mysqli_query($con, "SELECT sum(approvedDays) AS t FROM leave_applications WHERE status=1 AND applicantID='$employeeID' AND leaveTypeInTwo='3' AND approvedDateFrom>='$casualStart' AND approvedDateTo<='$casualEnd'"));
                            $usedLeave = $r['t'] ?? 0; $totalFull = 0; $totalHalf = 0;
                        } elseif ($ltRow['leaveID'] == 3) {
                            $r = mysqli_fetch_assoc(mysqli_query($con, "SELECT sum(approvedDays) AS t FROM leave_applications WHERE status=1 AND applicantID='$employeeID' AND leaveTypeInTwo='4' AND approvedDateFrom>='$casualStart' AND approvedDateTo<='$casualEnd'"));
                            $usedLeave = $r['t'] ?? 0; $totalFull = 0; $totalHalf = 0;
                        } else {
                            $r = mysqli_fetch_assoc(mysqli_query($con, "SELECT sum(approvedDays) AS t FROM leave_applications WHERE status=1 AND applicantID='$employeeID' AND approvedLeaveType='$ltRow[leaveID]'"));
                            $usedLeave = $r['t'] ?? 0;
                            $rf = mysqli_fetch_assoc(mysqli_query($con, "SELECT sum(approvedDays) AS t FROM leave_applications WHERE status=1 AND applicantID='$employeeID' AND approvedLeaveType='$ltRow[leaveID]' AND leaveTypeInTwo='1'"));
                            $totalFull = $rf['t'] ?? 0;
                            $rh = mysqli_fetch_assoc(mysqli_query($con, "SELECT sum(approvedDays) AS t FROM leave_applications WHERE status=1 AND applicantID='$employeeID' AND approvedLeaveType='$ltRow[leaveID]' AND leaveTypeInTwo='2'"));
                            $totalHalf = $rh['t'] ?? 0;
                        }
                    ?>
                    <tr>
                        <td><?= $obj->engToBn($ltSl) ?></td>
                        <td><?= htmlspecialchars($ltRow['leaveTitle']) ?></td>
                        <td class="text-center"><?= $obj->engToBn((int)$usedLeave) ?></td>
                        <td class="text-center"><?= $obj->engToBn((int)$totalFull) ?></td>
                        <td class="text-center"><?= $obj->engToBn((int)$totalHalf) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Salary Increment Card ── -->
<?php if (!empty($incrementRows)): ?>
<div class="card mb-4" style="border:none; box-shadow:0 4px 24px rgba(58,61,83,0.10);">
    <div class="card-body p-0">
        <div style="background:linear-gradient(135deg,#3A3D53,#5a5f78); padding:14px 20px; border-radius:calc(var(--bs-card-border-radius) - 1px) calc(var(--bs-card-border-radius) - 1px) 0 0;">
            <h6 class="text-white mb-0 fw-semibold" style="font-size:0.88rem;"><i class="ti tabler-trending-up me-2"></i>বেতন বৃদ্ধির তথ্য</h6>
        </div>
        <div class="table-responsive p-0">
            <table class="table table-bordered table-sm mb-0" style="font-size:0.875rem;">
                <thead style="background:#3A3D53; color:#fff;">
                    <tr>
                        <th>ক্রমিক</th>
                        <th>বৎসর</th>
                        <th class="text-end">মূল বেতন</th>
                        <th class="text-end">বৃদ্ধির হার</th>
                        <th class="text-end">বৃদ্ধির পর মূল বেতন</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($incrementRows as $i => $row): ?>
                <tr>
                    <td><?= $obj->engToBn($i + 1) ?></td>
                    <td><?= $obj->engToBn($row['incrementYear']) ?></td>
                    <td class="text-end"><?= $obj->engToBn(number_format($row['presentSalary'], 2)) ?></td>
                    <td class="text-end"><?= $obj->engToBn(number_format($row['incrementAmount'], 2)) ?></td>
                    <td class="text-end"><?= $obj->engToBn(number_format($row['incrementSalary'], 2)) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// ═══════════════════════════════════════════════════════════════════
// Posting Timeline (transfer history)
// ═══════════════════════════════════════════════════════════════════
$transferRows = [];
$thStmt = mysqli_prepare($con,
    "SELECT eth.*, o_from.organization_name AS from_name, o_to.organization_name AS to_name
     FROM employee_transfer_history eth
     LEFT JOIN organization o_from ON o_from.id = eth.from_organization_id
     LEFT JOIN organization o_to   ON o_to.id   = eth.to_organization_id
     WHERE eth.employee_ref_id = ?
     ORDER BY eth.transfer_date ASC, eth.dataID ASC");
mysqli_stmt_bind_param($thStmt, 'i', $employeeID);
mysqli_stmt_execute($thStmt);
$thRes = mysqli_stmt_get_result($thStmt);
while ($_r = mysqli_fetch_assoc($thRes)) $transferRows[] = $_r;
mysqli_stmt_close($thStmt);

// Lifecycle info
$_empType    = $emp['employment_type']      ?? 'permanent';
$_probStart  = $emp['probation_start_date'] ?? null;
$_permFrom   = $emp['permanent_from_date']  ?? null;
$_permEmpID  = $emp['permanent_emp_id']     ?? null;
?>

<!-- ───── Lifecycle + Posting Timeline ───── -->
<div class="card mb-4" style="border:none; box-shadow:0 4px 24px rgba(58,61,83,0.10);">
    <div class="card-body p-0">
        <div style="background:linear-gradient(135deg,#3A3D53,#5a5f78); padding:14px 20px; border-radius:calc(var(--bs-card-border-radius) - 1px) calc(var(--bs-card-border-radius) - 1px) 0 0;">
            <h5 class="mb-0" style="color:#fff;"><i class="ti tabler-route me-2"></i>চাকরি জীবনবৃত্তান্ত (পোস্টিং ইতিহাস)</h5>
        </div>
        <div class="p-4">
            <?php if ($_empType === 'probationary'): ?>
                <div class="alert alert-info py-2 mb-3">
                    <i class="ti tabler-clock-hour-4 me-1"></i>
                    <strong>শিক্ষানবিশ পর্যায়ে আছেন</strong>
                    <?php if ($_probStart): ?>
                        — শিক্ষানবিশ শুরু: <?= date('d/m/Y', strtotime($_probStart)) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($_permEmpID): ?>
                <div class="alert alert-success py-2 mb-3">
                    <i class="ti tabler-id-badge-2 me-1"></i>
                    BITAC স্থায়ী আইডি: <strong><?= htmlspecialchars($_permEmpID) ?></strong>
                    <?php if ($_permFrom): ?> • স্থায়ী হয়েছেন: <?= date('d/m/Y', strtotime($_permFrom)) ?><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($transferRows)): ?>
                <div class="text-muted small"><i class="ti tabler-info-circle me-1"></i>কোনো পোস্টিং ইতিহাস পাওয়া যায়নি।</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead style="background:#fafbfd;">
                            <tr>
                                <th style="font-size:0.78rem; color:#5d6580; text-transform:uppercase; letter-spacing:0.04em;">থেকে</th>
                                <th style="font-size:0.78rem; color:#5d6580; text-transform:uppercase; letter-spacing:0.04em;">→</th>
                                <th style="font-size:0.78rem; color:#5d6580; text-transform:uppercase; letter-spacing:0.04em;">গন্তব্য</th>
                                <th style="font-size:0.78rem; color:#5d6580; text-transform:uppercase; letter-spacing:0.04em;">শুরু</th>
                                <th style="font-size:0.78rem; color:#5d6580; text-transform:uppercase; letter-spacing:0.04em;">শেষ</th>
                                <th style="font-size:0.78rem; color:#5d6580; text-transform:uppercase; letter-spacing:0.04em;">আদেশ</th>
                                <th style="font-size:0.78rem; color:#5d6580; text-transform:uppercase; letter-spacing:0.04em;">কারণ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transferRows as $tr):
                                $_fromName = $tr['from_name'] ?: '<em class="text-muted">নতুন এন্ট্রি</em>';
                                $_isCurrent = empty($tr['effective_to']);
                            ?>
                            <tr<?= $_isCurrent ? ' style="background:#f0faf4;"' : '' ?>>
                                <td><?= $_fromName ?></td>
                                <td><i class="ti tabler-arrow-narrow-right text-muted"></i></td>
                                <td>
                                    <strong><?= htmlspecialchars($tr['to_name'] ?? '—') ?></strong>
                                    <?php if ($_isCurrent): ?>
                                        <span class="badge bg-label-success ms-1">বর্তমান</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $tr['transfer_date'] ? date('d/m/Y', strtotime($tr['transfer_date'])) : '—' ?></td>
                                <td><?= $tr['effective_to'] ? date('d/m/Y', strtotime($tr['effective_to'])) : '<em class="text-muted">—</em>' ?></td>
                                <td>
                                    <?php if (!empty($tr['order_number'])): ?>
                                        <?= htmlspecialchars($tr['order_number']) ?>
                                        <?php if (!empty($tr['order_date'])): ?>
                                            <small class="text-muted d-block"><?= date('d/m/Y', strtotime($tr['order_date'])) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>—<?php endif; ?>
                                    <?php if (!empty($tr['attachment'])): ?>
                                        <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($tr['attachment']) ?>" target="_blank" title="সংযুক্তি">
                                            <i class="ti tabler-paperclip"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($tr['reason'] ?? '') ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /#printArea -->

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
@media print {
    .no-print, .layout-menu, #layout-navbar, .layout-menu-toggle, .menu-mobile-toggler { display: none !important; }
    .layout-page { margin: 0 !important; padding: 0 !important; }
    .content-wrapper { padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
    #printArea { width: 100%; }
}
</style>
