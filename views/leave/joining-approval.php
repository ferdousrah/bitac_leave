<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Re-query full user
$_stmt = mysqli_prepare($con,
    "SELECT user_id, full_name, employee_id, isCenterAdmin, dashboardType, user_type,
            user_group_id, organization_id
     FROM user_list WHERE user_id = ?");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_stmt, 's', $_un);
mysqli_stmt_execute($_stmt);
$_full = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt)) ?: [];
mysqli_stmt_close($_stmt);
$getUserInfoQRW = array_merge($getUserInfoQRW ?? [], $_full);

$signatoryEmpId = (int)($getUserInfoQRW['employee_id'] ?? 0);

// ── Stats counts ─────────────────────────────────────
$superviseCount = 0;
$approveCount   = 0;
if ($signatoryEmpId > 0) {
    // সুপারিশ = supervisor rows
    $r = mysqli_prepare($con,
        "SELECT COUNT(*) AS c FROM leave_joining_data_for_approval
         WHERE signatory = ? AND isSupervisor = 1 AND isApproved = 0");
    mysqli_stmt_bind_param($r, 'i', $signatoryEmpId);
    mysqli_stmt_execute($r);
    $superviseCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($r))['c'] ?? 0);
    mysqli_stmt_close($r);

    // অনুমোদন = chain rows, current-signatory SQL filter
    $r = mysqli_prepare($con,
        "SELECT COUNT(*) AS c
         FROM leave_joining_data_for_approval ldfa
         INNER JOIN leave_joining_application lja ON lja.leaveApplicationID = ldfa.leaveApplicationID
         WHERE ldfa.signatory     = ?
           AND ldfa.isSupervisor  = 0
           AND ldfa.isSentbyAdmin = 1
           AND ldfa.isApproved    = 0
           AND lja.status         = 0
           AND NOT EXISTS (
               SELECT 1 FROM leave_joining_data_for_approval prev
               WHERE prev.leaveApplicationID = ldfa.leaveApplicationID
                 AND prev.serial < ldfa.serial
                 AND prev.isApproved = 0
           )");
    mysqli_stmt_bind_param($r, 'i', $signatoryEmpId);
    mysqli_stmt_execute($r);
    $approveCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($r))['c'] ?? 0);
    mysqli_stmt_close($r);
}

// ── Filter dropdowns ─────────────────────────────────
$orgOptions = '';
$orgsQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY organization_name ASC");
while ($o = mysqli_fetch_assoc($orgsQ)) {
    $orgOptions .= '<option value="' . htmlspecialchars($o['organization_name'], ENT_QUOTES) . '">' . htmlspecialchars($o['organization_name']) . '</option>';
}
$secOptions = '';
$secQ = mysqli_query($con, "SELECT id, section_name FROM sections ORDER BY section_name ASC");
while ($s = mysqli_fetch_assoc($secQ)) {
    $secOptions .= '<option value="' . htmlspecialchars($s['section_name'], ENT_QUOTES) . '">' . htmlspecialchars($s['section_name']) . '</option>';
}

$joiningTypeMap = [
    1 => 'সঠিক সময়ে যোগদান',
    2 => 'অগ্রিম যোগদান',
    3 => 'বর্ধিত ছুটি + পরে যোগদান',
];
$jtClassMap = [1 => 'jt-ontime', 2 => 'jt-early', 3 => 'jt-extend'];
$jtIconMap  = [1 => 'tabler-clock', 2 => 'tabler-calendar-minus', 3 => 'tabler-calendar-plus'];

// ── Fetch rows for both tabs ─────────────────────────
function load_supervise_rows($con, $signatoryEmpId) {
    $stmt = mysqli_prepare($con,
        "SELECT ldfa.*, lja.dataID AS joiningID, lja.joiningType, lja.requestedJoiningDate,
                lja.reason, lja.approvedLeaveType, lja.submitDate, lja.submitTime,
                la.dataID AS appID, la.applicantID, la.approvedDateFrom, la.approvedDateTo,
                la.approvedDays, la.leaveType
         FROM leave_joining_data_for_approval ldfa
         INNER JOIN leave_joining_application lja ON lja.leaveApplicationID = ldfa.leaveApplicationID
         INNER JOIN leave_applications la         ON la.dataID = ldfa.leaveApplicationID
         WHERE ldfa.signatory     = ?
           AND ldfa.isSupervisor  = 1
           AND ldfa.isApproved    = 0
           AND lja.status         = 0
         ORDER BY lja.submitDate DESC, ldfa.dataID DESC");
    mysqli_stmt_bind_param($stmt, 'i', $signatoryEmpId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($rs)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function load_approve_rows($con, $signatoryEmpId) {
    $stmt = mysqli_prepare($con,
        "SELECT ldfa.*, lja.dataID AS joiningID, lja.joiningType, lja.requestedJoiningDate,
                lja.reason, lja.approvedLeaveType, lja.submitDate, lja.submitTime,
                la.dataID AS appID, la.applicantID, la.approvedDateFrom, la.approvedDateTo,
                la.approvedDays, la.leaveType
         FROM leave_joining_data_for_approval ldfa
         INNER JOIN leave_joining_application lja ON lja.leaveApplicationID = ldfa.leaveApplicationID
         INNER JOIN leave_applications la         ON la.dataID = ldfa.leaveApplicationID
         WHERE ldfa.signatory     = ?
           AND ldfa.isSupervisor  = 0
           AND ldfa.isSentbyAdmin = 1
           AND ldfa.isApproved    = 0
           AND lja.status         = 0
           AND NOT EXISTS (
               SELECT 1 FROM leave_joining_data_for_approval prev
               WHERE prev.leaveApplicationID = ldfa.leaveApplicationID
                 AND prev.serial < ldfa.serial
                 AND prev.isApproved = 0
           )
         ORDER BY lja.submitDate DESC, ldfa.dataID DESC");
    mysqli_stmt_bind_param($stmt, 'i', $signatoryEmpId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($rs)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function render_joining_row($r, $sl, $con, $joiningTypeMap, $jtClassMap, $jtIconMap) {
    $appID = (int)$r['appID'];
    $joiningID = (int)$r['joiningID'];
    $joiningType = (int)$r['joiningType'];

    // Applicant info
    $empStmt = mysqli_prepare($con,
        "SELECT el.employee_name, el.employee_id, el.photo, jt.job_title_name,
                s.section_name, o.organization_name
         FROM employee_list el
         LEFT JOIN job_title    jt ON el.designation     = jt.id
         LEFT JOIN sections     s  ON el.section_id      = s.id
         LEFT JOIN organization o  ON el.organization_id = o.id
         WHERE el.id = ? LIMIT 1");
    mysqli_stmt_bind_param($empStmt, 'i', $r['applicantID']);
    mysqli_stmt_execute($empStmt);
    $emp = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));
    mysqli_stmt_close($empStmt);

    $empName  = trim($emp['employee_name'] ?? '');
    $empJob   = trim($emp['job_title_name'] ?? '');
    $empPhoto = trim($emp['photo'] ?? '');
    $empCode  = trim($emp['employee_id'] ?? '');
    $secName  = trim($emp['section_name'] ?? '');
    $orgName  = trim($emp['organization_name'] ?? '');
    $parts = preg_split('/\s+/u', $empName);
    $initials = mb_substr($parts[0] ?? '', 0, 1, 'UTF-8');
    if (count($parts) > 1) $initials .= mb_substr(end($parts), 0, 1, 'UTF-8');
    if (!empty($empPhoto)) {
        $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
        $avatar = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
    } else {
        $avatar = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
    }
    $applicantCell = '<div class="emp-cell">' . $avatar
                   . '<div class="emp-meta"><div class="emp-name">' . htmlspecialchars($empName)
                   . ($empCode ? ' <span class="emp-sub-light">(' . banglaNumber($empCode) . ')</span>' : '')
                   . '</div>'
                   . ($empJob ? '<div class="emp-sub">' . htmlspecialchars($empJob) . '</div>' : '')
                   . '</div></div>';

    $secCenter = '';
    if ($secName) $secCenter .= '<span class="meta-chip section"><i class="ti tabler-building"></i>' . htmlspecialchars($secName) . '</span>';
    if ($orgName) {
        if ($secCenter) $secCenter .= '<br>';
        $secCenter .= '<span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i>' . htmlspecialchars($orgName) . '</span>';
    }

    $joiningLabel = $joiningTypeMap[$joiningType] ?? '';
    $joiningClass = $jtClassMap[$joiningType] ?? '';
    $joiningIcon  = $jtIconMap[$joiningType] ?? 'tabler-circle';
    $jtChip = $joiningLabel
        ? '<span class="jt-chip ' . $joiningClass . '"><i class="ti ' . $joiningIcon . ' me-1"></i>' . htmlspecialchars($joiningLabel) . '</span>'
        : '<span class="text-muted small">—</span>';

    // Original approved leave — multi-segment aware. Same seg-list convention
    // as the other approval queues: >1 segment shows a total-days pill plus a
    // chip per segment so the approver sees the split before deciding on the
    // joining request.
    $adateF = $r['approvedDateFrom'];
    $adateT = $r['approvedDateTo'];
    $adateDiff = (int)$r['approvedDays'];
    if ($adateDiff === 0 && $adateF && $adateT) {
        $adateDiff = (int)((strtotime($adateT) - strtotime($adateF)) / 86400) + 1;
    }

    $segStmt = mysqli_prepare($con,
        "SELECT s.days, lt.leaveTitle
         FROM leave_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.applicationID = ?
           AND (s.kind = 'proposed' OR s.kind IS NULL)
         ORDER BY s.serial ASC, s.dataID ASC");
    mysqli_stmt_bind_param($segStmt, 'i', $appID);
    mysqli_stmt_execute($segStmt);
    $segRes = mysqli_stmt_get_result($segStmt);
    $segs = [];
    while ($sg = mysqli_fetch_assoc($segRes)) $segs[] = $sg;
    mysqli_stmt_close($segStmt);

    $primaryHtml = '<span class="text-muted small">—</span>';
    if ($adateF && $adateT) {
        $primaryHtml = '<div class="date-range"><i class="ti tabler-calendar-check"></i><span>' . banglaNumber(date('d/m/Y', strtotime($adateF))) . '</span><i class="ti tabler-arrow-narrow-right text-muted mx-1"></i><span>' . banglaNumber(date('d/m/Y', strtotime($adateT))) . '</span></div>';
        if (count($segs) > 1) {
            $segTotal = array_sum(array_column($segs, 'days'));
            $segParts = [];
            foreach ($segs as $sg) {
                $segParts[] = '<span class="seg-pill">' . banglaNumber((int)$sg['days']) . ' দিন '
                            . htmlspecialchars($sg['leaveTitle'] ?? 'অজানা') . '</span>';
            }
            $primaryHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">মোট ' . banglaNumber($segTotal) . ' দিন</span></div>'
                          . '<div class="seg-list">' . implode(' ', $segParts) . '</div>';
        } else {
            $primaryHtml .= '<div class="leave-meta"><span class="days-pill days-pill-success">' . banglaNumber($adateDiff) . ' দিন</span></div>';
        }
    }

    // Joining date (requested)
    $reqJD = $r['requestedJoiningDate'];
    $joiningDateHtml = $reqJD
        ? '<span class="days-pill days-pill-info"><i class="ti tabler-flag-check me-1"></i>' . banglaNumber(date('d/m/Y', strtotime($reqJD))) . '</span>'
        : '<span class="text-muted small">—</span>';

    // Actions
    $action = '<div class="action-group">'
            . '<a class="action-icon icon-view" href="../../views/leave/approve-joining-application.php?menuslug=leave-joining-approval&joiningID=' . $joiningID . '" data-bs-toggle="tooltip" title="বিস্তারিত ও সিদ্ধান্ত"><i class="ti tabler-eye"></i></a>'
            . '<a class="action-icon icon-attach" target="_blank" href="../../views/leave/application-details.php?menuslug=leave-joining-approval&leaveApplicationID=' . $appID . '" data-bs-toggle="tooltip" title="মূল ছুটির আবেদন"><i class="ti tabler-file-text"></i></a>'
            . '</div>';

    $submitWhen = trim(($r['submitDate'] ?? '') . ' ' . ($r['submitTime'] ?? ''));

    return [
        'serial'         => '<span class="serial-num">' . $sl . '</span>',
        'applicant_cell' => $applicantCell,
        'section_center' => $secCenter,
        'joining_type'   => $jtChip,
        'primary_leave'  => $primaryHtml,
        'joining_date'   => $joiningDateHtml,
        'submitted'      => $submitWhen ? '<small class="text-muted"><i class="ti tabler-clock me-1"></i>' . htmlspecialchars($submitWhen) . '</small>' : '—',
        'action'         => $action,
        '_org'     => $orgName,
        '_section' => $secName,
        '_jtype'   => (string)$joiningType,
    ];
}

$superviseRowsData = load_supervise_rows($con, $signatoryEmpId);
$approveRowsData   = load_approve_rows($con, $signatoryEmpId);

$superviseRows = [];
$sl = 1;
foreach ($superviseRowsData as $row) {
    $superviseRows[] = render_joining_row($row, $sl++, $con, $joiningTypeMap, $jtClassMap, $jtIconMap);
}

$approveRows = [];
$sl = 1;
foreach ($approveRowsData as $row) {
    $approveRows[] = render_joining_row($row, $sl++, $con, $joiningTypeMap, $jtClassMap, $jtIconMap);
}

function emitTableRows($rows) {
    if (empty($rows)) return '';
    $out = '';
    foreach ($rows as $r) {
        $attrs = ' data-org="' . htmlspecialchars($r['_org'], ENT_QUOTES) . '"'
               . ' data-section="' . htmlspecialchars($r['_section'], ENT_QUOTES) . '"'
               . ' data-jtype="' . htmlspecialchars($r['_jtype'], ENT_QUOTES) . '"';
        $out .= '<tr' . $attrs . '>';
        $out .= '<td>' . $r['serial']         . '</td>';
        $out .= '<td>' . $r['applicant_cell'] . '</td>';
        $out .= '<td>' . $r['section_center'] . '</td>';
        $out .= '<td>' . $r['joining_type']   . '</td>';
        $out .= '<td>' . $r['primary_leave']  . '</td>';
        $out .= '<td>' . $r['joining_date']   . '</td>';
        $out .= '<td>' . $r['submitted']      . '</td>';
        $out .= '<td class="text-center">' . $r['action'] . '</td>';
        $out .= '</tr>';
    }
    return $out;
}
?>

<style>
.jt-chip { display: inline-flex; align-items: center; font-size: 0.78rem; padding: 0.3em 0.7em; border-radius: 0.4rem; font-weight: 600; }
.jt-chip.jt-ontime { background: #e6f7ee; color: #1a7e44; }
.jt-chip.jt-early  { background: #fff3e1; color: #b8651a; }
.jt-chip.jt-extend { background: #f0edff; color: #5648c4; }
.meta-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 0.74rem; color: #5d6580; }
.meta-chip i { color: #8a90a6; }
</style>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-check me-2 text-primary"></i>কর্মক্ষেত্রে যোগদান — সুপারিশ ও অনুমোদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6">
        <div class="stat-card stat-pending stat-clickable" data-tab-target="#supervision"
             data-bs-toggle="tooltip" data-bs-placement="top" title="যোগদান পত্রে সুপারিশের জন্য অপেক্ষমান">
            <div class="stat-icon"><i class="ti tabler-clipboard-list"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($superviseCount); ?></div>
                <div class="stat-label">সুপারিশের অপেক্ষায়</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="stat-card stat-info stat-clickable" data-tab-target="#approval"
             data-bs-toggle="tooltip" data-bs-placement="top" title="যোগদান পত্রে অনুমোদনের জন্য অপেক্ষমান">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($approveCount); ?></div>
                <div class="stat-label">অনুমোদনের অপেক্ষায়</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-0">
        <ul class="nav custom-leave-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#supervision" role="tab">
                    <i class="ti tabler-clipboard-check me-2"></i>
                    <span class="d-none d-sm-inline">সুপারিশ</span>
                    <span class="badge ms-2"><?php echo banglaNumber($superviseCount); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#approval" role="tab">
                    <i class="ti tabler-circle-check me-2"></i>
                    <span class="d-none d-sm-inline">অনুমোদন</span>
                    <span class="badge ms-2"><?php echo banglaNumber($approveCount); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3">
            <!-- Tab 1: সুপারিশ -->
            <div class="tab-pane fade show active" id="supervision" role="tabpanel">
                <div class="table-responsive">
                    <table id="superviseTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>যোগদানের প্রকার</th>
                                <th>অনুমোদিত ছুটি</th>
                                <th>যোগদানের তারিখ</th>
                                <th>জমা</th>
                                <th class="text-center">কার্যাবলী</th>
                            </tr>
                        </thead>
                        <tbody><?php echo emitTableRows($superviseRows); ?></tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: অনুমোদন -->
            <div class="tab-pane fade" id="approval" role="tabpanel">
                <div class="table-responsive">
                    <table id="approveTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>যোগদানের প্রকার</th>
                                <th>অনুমোদিত ছুটি</th>
                                <th>যোগদানের তারিখ</th>
                                <th>জমা</th>
                                <th class="text-center">কার্যাবলী</th>
                            </tr>
                        </thead>
                        <tbody><?php echo emitTableRows($approveRows); ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script type="text/javascript">
(function() {
    function init() {
        if (typeof $ === 'undefined' || !$.fn.DataTable) { setTimeout(init, 100); return; }

        var dtLang = {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
            search: "", searchPlaceholder: "খুঁজুন...",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: '<div class="empty-state-rich"><i class="ti tabler-clipboard-off"></i><div class="empty-title">কোন আবেদন নেই</div><div class="empty-subtitle">এই মুহূর্তে কোনো যোগদান পত্র আপনার সিদ্ধান্তের অপেক্ষায় নেই</div></div>',
            paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
        };

        var dtConfig = {
            pageLength: 10,
            responsive: false,
            autoWidth: false,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
            language: dtLang,
            order: [[6, 'desc']],
            columnDefs: [{ targets: [0, 7], orderable: false }],
            createdRow: function(row) {
                var labels = ['ক্রমিক', 'আবেদনকারী', 'শাখা ও কেন্দ্র', 'যোগদানের প্রকার', 'অনুমোদিত ছুটি', 'যোগদানের তারিখ', 'জমা', 'কার্যাবলী'];
                var compact = [0, 3, 5, 7];
                $(row).find('td').each(function(i){
                    var $td = $(this);
                    $td.attr('data-label', labels[i] || '');
                    if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
                    if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
                });
            }
        };

        $('#superviseTable').DataTable(dtConfig);
        $('#approveTable').DataTable(dtConfig);

        // Stat → tab
        function syncStatActive() {
            var t = $('.custom-leave-tabs .nav-link.active').data('bs-target');
            $('.stat-clickable').removeClass('is-active');
            $('.stat-clickable[data-tab-target="' + t + '"]').addClass('is-active');
        }
        $('.stat-clickable').on('click', function() {
            var t = $(this).data('tab-target'); if (!t) return;
            var $btn = $('button[data-bs-target="' + t + '"]');
            if ($btn.length && typeof bootstrap !== 'undefined' && bootstrap.Tab) bootstrap.Tab.getOrCreateInstance($btn[0]).show();
            else $btn.trigger('click');
        });
        $('.custom-leave-tabs .nav-link').on('shown.bs.tab', syncStatActive);
        syncStatActive();

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
