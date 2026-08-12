<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Auto-create routing rules table if missing (grades column = comma-separated grade IDs)
mysqli_query($con, "CREATE TABLE IF NOT EXISTS leave_signatory_rule (
    id            INT(11)      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grades        TEXT         NOT NULL COMMENT 'Comma-separated grade.id values',
    leave_type_id INT(11)      DEFAULT NULL COMMENT 'NULL = all types',
    route         ENUM('center_only','center_then_hq','hq_only') NOT NULL DEFAULT 'center_only',
    description   VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Only show main BITAC centers
$centersQ = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE id IN (4,5,6,7,8,9) ORDER BY id");

// Load grade list (keyed by id for lookup) & leave types for modal dropdowns
$gradesQ     = mysqli_query($con, "SELECT id, grade_title FROM grade WHERE deleted=0 ORDER BY id ASC");
$leaveTypesQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types ORDER BY leaveTitle ASC");

// Build grade lookup map: [id => title]
$gradeMap = [];
while ($g = mysqli_fetch_assoc($gradesQ)) $gradeMap[$g['id']] = $g['grade_title'];

// Load existing rules
$rulesQ = mysqli_query($con, "
    SELECT r.*, lt.leaveTitle AS leave_type_title
    FROM leave_signatory_rule r
    LEFT JOIN leave_types lt ON r.leave_type_id = lt.leaveID
    ORDER BY r.id ASC
");


// ── Coverage gap check ────────────────────────────────────────────────
// A grade with staff but no routing rule is invisible until someone applies:
// buildSignatoryChain() returns nothing for them, so the application silently
// drops to the legacy designation-based path — no route, no HQ escalation, and
// no forced climb to the DG for a signatory's own leave. Surfacing it here means
// it gets noticed while the rules are being edited rather than months later.
$coveredGrades = [];
$_covQ = mysqli_query($con, "SELECT grades FROM leave_signatory_rule");
if ($_covQ) {
    while ($_c = mysqli_fetch_assoc($_covQ)) {
        foreach (explode(',', $_c['grades']) as $_g) {
            $_g = (int)trim($_g);
            if ($_g > 0) $coveredGrades[$_g] = true;
        }
    }
}

$gradeGaps = [];
$_gapQ = mysqli_query($con, "
    SELECT el.pay_scale AS grade_id, g.grade_title, COUNT(*) AS staff,
           SUM(CASE WHEN las.employeeID IS NOT NULL THEN 1 ELSE 0 END) AS signatories
    FROM employee_list el
    INNER JOIN grade g ON g.id = el.pay_scale
    LEFT JOIN (SELECT DISTINCT employeeID FROM leave_approval_signatory) las
           ON las.employeeID = el.id
    WHERE el.employment_status = 1
      AND el.pending_section_assignment = 0
      AND el.pay_scale IS NOT NULL AND el.pay_scale <> ''
    GROUP BY el.pay_scale, g.grade_title
    ORDER BY CAST(el.pay_scale AS UNSIGNED) ASC
");
if ($_gapQ) {
    while ($_r = mysqli_fetch_assoc($_gapQ)) {
        if (!isset($coveredGrades[(int)$_r['grade_id']])) $gradeGaps[] = $_r;
    }
}

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-settings');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-route me-2 text-primary"></i>সুপারিশ / অনুমোদন / সংশোধন সিগনেটরি</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>প্রতিটি কেন্দ্রের সিগনেটরি ও গ্রেড-ভিত্তিক রাউটিং নিয়ম নির্ধারণ করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
/* ── Section title (used for center grid + rules block) ── */
.sig-section-title {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 1rem;
}
.sig-section-title .ti-tile {
    width: 32px; height: 32px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.45rem;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.sig-section-title h6 {
    margin: 0;
    color: #2c2e3a;
    font-size: 1rem;
    font-weight: 600;
}
.sig-section-title .sig-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 2px;
    line-height: 1.4;
}

/* Center cards */
.center-card {
    border: 1px solid #eef0f5 !important;
    border-radius: 0.75rem !important;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.center-card:hover {
    border-color: #ddd5f6 !important;
    box-shadow: 0 4px 16px rgba(108, 92, 231, 0.08);
    transform: translateY(-2px);
}
.center-card .card-body { padding: 1.1rem 1.25rem; }
.center-card .center-name {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 1rem;
    margin-bottom: 0.15rem;
}
.center-card .chain-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.3em 0.7em;
    border-radius: 0.4rem;
    margin-top: 4px;
}
.center-card .chain-status-pill.has { background: #e6f7ee; color: #1a7e44; }
.center-card .chain-status-pill.empty { background: #fff3e1; color: #b8651a; }
.center-card .manage-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #5648c4;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    margin-top: 0.5rem;
}
.center-card .manage-link:hover { color: #5648c4; text-decoration: underline; }
.center-card .avatar-group .avatar img,
.center-card .avatar-group .avatar .avatar-initial {
    border: 2px solid #fff;
    width: 36px;
    height: 36px;
}
.center-card .avatar-initial.bg-label-primary {
    background: #f0edff !important;
    color: #5648c4 !important;
    font-size: 0.78rem;
    font-weight: 600;
}

/* Rules table */
.rules-card {
    border-radius: 0.75rem;
}
#rulesTable thead {
    background: #fafbfd !important;
    color: #5d6580 !important;
}
#rulesTable thead th {
    background: transparent !important;
    color: #5d6580 !important;
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #eef0f5;
    padding: 0.85rem 1rem;
}
#rulesTable tbody td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: #2c2e3a;
    border-bottom: 1px solid #f3f4fa;
}
#rulesTable tbody tr:last-child td { border-bottom: 0; }
#rulesTable .badge {
    font-size: 0.74rem;
    font-weight: 500;
    padding: 0.4em 0.7em;
    border-radius: 0.35rem;
}

/* Routing rule modal */
#ruleModal .modal-content {
    border: none;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
#ruleModal .modal-header {
    background: linear-gradient(135deg, #6c5ce7 0%, #5648c4 100%);
    color: #fff;
    border: none;
    padding: 16px 22px;
}
#ruleModal .modal-title { color: #fff !important; font-weight: 600; }
#ruleModal .btn-close { filter: brightness(0) invert(1); opacity: 0.85; }
#ruleModal .form-label { font-size: 0.85rem; color: #3a3d53; font-weight: 500; }
#ruleModal .form-control:focus,
#ruleModal .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
#hqApprovalField {
    background: #faf9ff !important;
    border: 1px solid #ddd5f6 !important;
    border-radius: 0.5rem;
}
#hqApprovalField .form-check-input:checked {
    background-color: #6c5ce7;
    border-color: #6c5ce7;
}
</style>

<!-- Section: Centers -->
<div class="sig-section-title">
    <span class="ti-tile"><i class="ti tabler-building"></i></span>
    <div>
        <h6>কেন্দ্রভিত্তিক সিগনেটরি</h6>
        <span class="sig-sub">প্রতিটি কেন্দ্রের জন্য সিগনেটরি চেইন কনফিগার করুন</span>
    </div>
</div>

<!-- Center Cards -->
<div class="row g-3 mb-4">
    <?php while ($center = mysqli_fetch_assoc($centersQ)):
        $centerId   = $center['id'];
        $centerName = $center['organization_name'];

        // Fetch signatories for this center, ordered by approvalSL
        $sigQ = mysqli_query($con, "
            SELECT el.employee_name, el.photo, jt.job_title_name
            FROM leave_approval_signatory las
            LEFT JOIN employee_list el ON las.employeeID = el.id
            LEFT JOIN job_title jt ON las.designationID = jt.id
            WHERE las.organization_id = '$centerId'
            ORDER BY las.approvalSL ASC
        ");
        $sigCount  = mysqli_num_rows($sigQ);
        $signatories = [];
        while ($s = mysqli_fetch_assoc($sigQ)) $signatories[] = $s;

        // Build avatar group (show up to 4)
        $avatarHtml = '';
        if ($sigCount > 0) {
            foreach (array_slice($signatories, 0, 4) as $s) {
                $name    = htmlspecialchars($s['employee_name'] ?? ($s['job_title_name'] ?? 'অজানা'));
                $photo   = $s['photo'] ?? '';
                $imgSrc  = !empty($photo) && file_exists(__DIR__ . '/../../uploads/' . $photo)
                    ? '../../uploads/' . htmlspecialchars($photo)
                    : '../../assets/img/avatars/1.png';
                $avatarHtml .= '
                    <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                        data-bs-placement="top" title="' . $name . '" class="avatar pull-up">
                        <img class="rounded-circle" src="' . $imgSrc . '" alt="' . $name . '" />
                    </li>';
            }
            if ($sigCount > 4) {
                $avatarHtml .= '
                    <li class="avatar pull-up">
                        <span class="avatar-initial rounded-circle bg-label-primary">+' . ($sigCount - 4) . '</span>
                    </li>';
            }
            $chainLabel = '<span class="chain-status-pill has"><i class="ti tabler-shield-check"></i>' . $sigCount . ' জন সিগনেটরি</span>';
        } else {
            $avatarHtml = '
                <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                    data-bs-placement="top" title="সিগনেটরি নির্ধারণ করা হয়নি" class="avatar pull-up">
                    <span class="avatar-initial rounded-circle bg-label-secondary">
                        <i class="ti tabler-user-question"></i>
                    </span>
                </li>';
            $chainLabel = '<span class="chain-status-pill empty"><i class="ti tabler-alert-triangle"></i>সিগনেটরি নির্ধারণ করা হয়নি</span>';
        }
    ?>
    <div class="col-xl-4 col-lg-6 col-md-6">
        <div class="card center-card shadow-none h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <ul class="list-unstyled d-flex align-items-center avatar-group mb-0">
                        <?= $avatarHtml ?>
                    </ul>
                </div>
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <div class="center-name"><?= htmlspecialchars($centerName) ?></div>
                        <?= $chainLabel ?>
                        <div>
                            <a href="leave_approval_signatory_form.php?center_id=<?= $centerId ?>&menuslug=<?= $menuslug ?>" class="manage-link" data-turbo="true">
                                <i class="ti tabler-settings"></i>সিগনেটরি ব্যবস্থাপনা
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<!-- Routing Rules Section -->
<div class="d-flex justify-content-between align-items-center mt-4 mb-3 flex-wrap gap-2">
    <div class="sig-section-title mb-0">
        <span class="ti-tile"><i class="ti tabler-route"></i></span>
        <div>
            <h6>গ্রেড-ভিত্তিক রাউটিং নিয়মাবলী</h6>
            <span class="sig-sub">কোন গ্রেডের কর্মকর্তার আবেদন কোন চেইনে যাবে তা নির্ধারণ করুন</span>
        </div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ruleModal" onclick="openRuleModal()">
        <i class="ti tabler-plus me-1"></i>নতুন নিয়ম যোগ করুন
    </button>
</div>

<?php if (!empty($gradeGaps)): ?>
<div class="alert alert-warning d-flex mb-3" role="alert">
    <i class="ti tabler-alert-triangle me-2 mt-1"></i>
    <div>
        <div class="fw-semibold mb-1">
            <?= banglaNumber(count($gradeGaps)) ?> টি গ্রেডে কর্মরত কর্মচারী আছেন, কিন্তু কোনো রাউটিং নিয়ম নেই
        </div>
        <div class="mb-2">
            <?php foreach ($gradeGaps as $gp): ?>
                <span class="badge bg-label-warning me-1 mb-1">
                    <?= htmlspecialchars($gp['grade_title']) ?>
                    — <?= banglaNumber((int)$gp['staff']) ?> জন<?php
                        if ((int)$gp['signatories'] > 0) {
                            echo ', এর মধ্যে ' . banglaNumber((int)$gp['signatories']) . ' জন স্বাক্ষরকারী';
                        } ?>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="small mb-1">
            নিয়ম না থাকলে এই গ্রেডের আবেদন গ্রেড-ভিত্তিক চেইন পায় না — পুরনো পদ-ভিত্তিক
            পথে চলে যায়। ফলে <strong>প্রধান কার্যালয় পর্যন্ত escalation হয় না</strong>, আর
            <strong>স্বাক্ষরকারী নিজে আবেদন করলে মহাপরিচালক পর্যন্ত পৌঁছায় না</strong>।
        </div>
        <div class="small text-muted">
            সমাধান: নিচের তালিকায় উপযুক্ত নিয়মটি সম্পাদনা করে গ্রেডটি যোগ করুন, অথবা নতুন নিয়ম তৈরি করুন।
            সাধারণত পাশের গ্রেডের নিয়মই অনুসরণ করা হয়।
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-success d-flex align-items-center mb-3 py-2" role="alert">
    <i class="ti tabler-circle-check me-2"></i>
    <div class="small">কর্মরত কর্মচারী আছেন এমন প্রতিটি গ্রেডেই রাউটিং নিয়ম নির্ধারিত আছে।</div>
</div>
<?php endif; ?>

<div class="card rules-card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle" id="rulesTable">
                <thead>
                    <tr>
                        <th class="ps-3" style="width:60px;">#</th>
                        <th>গ্রেড সমূহ</th>
                        <th>ছুটির ধরন</th>
                        <th>রাউটিং</th>
                        <th>বিবরণ</th>
                        <th class="text-center" style="width:120px;">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $routeLabels = [
                    'center_only'    => '<span class="badge" style="background:#e3f1fb;color:#1a6ea8;">শুধু কেন্দ্র</span>',
                    'center_then_hq' => '<span class="badge" style="background:#fff3e1;color:#b8651a;">কেন্দ্র → প্রধান কার্যালয়</span>',
                    'hq_only'        => '<span class="badge" style="background:#fff1f0;color:#b13c3c;">শুধু প্রধান কার্যালয়</span>',
                ];
                $buildRouteCell = function($rule) use ($routeLabels) {
                    $base = $routeLabels[$rule['route']] ?? $rule['route'];
                    if ($rule['route'] === 'center_then_hq' && empty($rule['hq_approval_required'])) {
                        $base .= ' <span class="badge" style="background:#f3f4fa;color:#5d6580;" title="সেন্টার অনুমোদনই যথেষ্ট">HQ স্কিপ</span>';
                    }
                    return $base;
                };
                $sl = 0;
                while ($rule = mysqli_fetch_assoc($rulesQ)):
                    $sl++;
                    $gradeIds    = array_filter(explode(',', $rule['grades']));
                    $gradeBadges = '';
                    foreach ($gradeIds as $gid) {
                        $gtitle = $gradeMap[trim($gid)] ?? 'গ্রেড ' . trim($gid);
                        $gradeBadges .= '<span class="badge me-1 mb-1" style="background:#f0edff;color:#5648c4;">' . htmlspecialchars($gtitle) . '</span>';
                    }
                ?>
                <tr>
                    <td class="ps-3 text-muted"><?= $sl ?></td>
                    <td><?= $gradeBadges ?: '<span class="text-muted">—</span>' ?></td>
                    <td><?= $rule['leave_type_id'] ? '<strong>' . htmlspecialchars($rule['leave_type_title'] ?? '—') . '</strong>' : '<span class="text-muted">সব ধরন</span>' ?></td>
                    <td><?= $buildRouteCell($rule) ?></td>
                    <td><?= htmlspecialchars($rule['description'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                    <td class="text-center">
                        <button onclick='openRuleModal(<?= json_encode($rule) ?>)'
                            class="btn btn-sm btn-icon btn-label-primary me-1" title="সম্পাদনা">
                            <i class="ti tabler-edit"></i>
                        </button>
                        <button onclick="deleteRule(<?= $rule['id'] ?>)"
                            class="btn btn-sm btn-icon btn-label-danger" title="মুছুন">
                            <i class="ti tabler-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($sl === 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="ti tabler-route-off" style="font-size:2rem;color:#b9b0f4;"></i>
                        <div class="mt-2 mb-1" style="font-weight:600;color:#5d6580;">কোন নিয়ম নেই</div>
                        <small>উপরের বাটন দিয়ে নতুন রাউটিং নিয়ম যোগ করুন</small>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Routing Rule Modal -->
<div class="modal fade" id="ruleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ruleModalTitle"><i class="ti tabler-route me-1"></i>নতুন রাউটিং নিয়ম</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ruleForm">
                <div class="modal-body">
                    <input type="hidden" name="rule_id" id="ruleId" value="">

                    <div class="mb-3">
                        <label class="form-label">গ্রেড নির্বাচন করুন <span class="text-danger">*</span></label>
                        <select name="grades[]" id="ruleGrades" class="form-select" multiple required style="height:auto;">
                            <?php foreach ($gradeMap as $gid => $gtitle): ?>
                            <option value="<?= $gid ?>"><?= htmlspecialchars($gtitle) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>একাধিক গ্রেড নির্বাচন করা যাবে</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ছুটির ধরন</label>
                        <select name="leave_type_id" id="ruleLeaveType" class="form-select">
                            <option value="">সব ধরনের ছুটি</option>
                            <?php
                            mysqli_data_seek($leaveTypesQ, 0);
                            while ($lt = mysqli_fetch_assoc($leaveTypesQ)):
                            ?>
                            <option value="<?= $lt['leaveID'] ?>"><?= htmlspecialchars($lt['leaveTitle']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>ফাঁকা রাখলে সব ধরনের ছুটিতে প্রযোজ্য হবে</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">রাউটিং <span class="text-danger">*</span></label>
                        <select name="route" id="ruleRoute" class="form-select" required>
                            <option value="center_only">শুধু কেন্দ্র (Center Only)</option>
                            <option value="center_then_hq">কেন্দ্র → প্রধান কার্যালয় (Center → HQ)</option>
                            <option value="hq_only">শুধু প্রধান কার্যালয় (HQ Only)</option>
                        </select>
                    </div>

                    <!-- shown only when route = center_then_hq -->
                    <div class="mb-3 p-3" id="hqApprovalField" style="display:none;">
                        <label class="form-label mb-2">
                            <i class="ti tabler-building-skyscraper me-1"></i>
                            সেন্টার অনুমোদনের পর প্রধান কার্যালয়ের অনুমোদন প্রয়োজন?
                        </label>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="hq_approval_required" id="hqApprovalRequired" value="1" checked>
                            <label class="form-check-label" for="hqApprovalRequired" id="hqApprovalLabel">
                                হ্যাঁ, প্রধান কার্যালয়ের অনুমোদন লাগবে
                            </label>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="ti tabler-info-circle me-1"></i>বন্ধ থাকলে সেন্টারের সর্বশেষ সিগনেটরি অনুমোদনের পরই ছুটি মঞ্জুর হবে, প্রধান কার্যালয়ে যাবে না
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">বিবরণ</label>
                        <input type="text" name="description" id="ruleDesc" class="form-control" placeholder="যেমন: গ্রেড ১০-২০, সব ছুটি">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary px-4" id="ruleSaveBtn">
                        <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function () {
    if ($.fn.select2) {
        $('#ruleGrades').select2({
            dropdownParent: $('#ruleModal'),
            placeholder: '-- গ্রেড নির্বাচন করুন --',
            allowClear: true
        });
    }

    // Show/hide HQ approval field based on route
    $('#ruleRoute').on('change', function () {
        if ($(this).val() === 'center_then_hq') {
            $('#hqApprovalField').show();
        } else {
            $('#hqApprovalField').hide();
        }
    });

    // Update toggle label text
    $('#hqApprovalRequired').on('change', function () {
        $('#hqApprovalLabel').text(
            this.checked
                ? 'হ্যাঁ, প্রধান কার্যালয়ের অনুমোদন লাগবে'
                : 'না, সেন্টার অনুমোদনই যথেষ্ট'
        );
    });
});

function openRuleModal(rule) {
    rule = rule || null;
    if (rule) {
        $('#ruleModalTitle').html('<i class="ti tabler-route me-1"></i>রাউটিং নিয়ম সম্পাদনা');
        $('#ruleId').val(rule.id);
        var gradeIds = rule.grades ? rule.grades.toString().split(',').map(function(v){ return v.trim(); }) : [];
        if ($.fn.select2) {
            $('#ruleGrades').val(gradeIds).trigger('change');
        } else {
            $('#ruleGrades').val(gradeIds);
        }
        $('#ruleLeaveType').val(rule.leave_type_id || '');
        $('#ruleRoute').val(rule.route).trigger('change');
        var hqReq = (rule.hq_approval_required == null || rule.hq_approval_required == 1);
        $('#hqApprovalRequired').prop('checked', hqReq).trigger('change');
        $('#ruleDesc').val(rule.description || '');
    } else {
        $('#ruleModalTitle').html('<i class="ti tabler-route me-1"></i>নতুন রাউটিং নিয়ম');
        $('#ruleId').val('');
        $('#ruleForm')[0].reset();
        if ($.fn.select2) $('#ruleGrades').val(null).trigger('change');
        $('#ruleRoute').trigger('change');
        $('#hqApprovalRequired').prop('checked', true).trigger('change');
    }
    $('#ruleModal').modal('show');
}

function deleteRule(id) {
    Swal.fire({
        title: 'নিয়মটি মুছবেন?',
        text: 'এই কাজটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না।',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছুন',
        cancelButtonText: 'বাতিল',
        customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
    }).then(r => {
        if (r.isConfirmed) {
            $.post('../../api/signatory/delete-routing-rule.php', { id: id }, function (res) {
                res = JSON.parse(res);
                if (res.status == 1) {
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error', title: 'ত্রুটি', text: res.message,
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                }
            });
        }
    });
}

$('#ruleForm').on('submit', function (e) {
    e.preventDefault();
    $('#ruleSaveBtn').attr('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>সংরক্ষণ হচ্ছে...');
    $.ajax({
        type: 'POST', url: '../../api/signatory/save-routing-rule.php',
        data: $(this).serialize(), dataType: 'json',
        success: function (res) {
            $('#ruleSaveBtn').removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
            if (res.status == 1) {
                $('#ruleModal').modal('hide');
                if (res.warning) {
                    Swal.fire({
                        icon: 'warning', title: 'সম্পন্ন — তবে সতর্কতা', text: res.warning,
                        confirmButtonText: 'ঠিক আছে',
                        confirmButtonColor: '#6c5ce7',
                        buttonsStyling: false,
                        customClass: { confirmButton: 'btn btn-primary' }
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'success', title: 'সম্পন্ন', text: res.message,
                        timer: 1800, showConfirmButton: false,
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    }).then(() => location.reload());
                }
            } else {
                Swal.fire({
                    icon: 'error', title: 'ত্রুটি', text: res.message,
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                });
            }
        },
        error: function () {
            $('#ruleSaveBtn').removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
            Swal.fire({
                icon: 'error', title: 'ত্রুটি', text: 'একটি সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                confirmButtonColor: '#ff3e1d',
                customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
            });
        }
    });
});
</script>
