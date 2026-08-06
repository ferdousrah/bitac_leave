<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$signatoryEmpId = $getUserInfoQRW['employee_id'] ?? '';

// Stats counts
$supervisedCount = 0;
$approvedCount   = 0;
$returnedCount   = 0;
$declinedCount   = 0;
if ($signatoryEmpId) {
    $r = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM leave_data_for_approval WHERE signatory = ? AND isSupervisor = 1 AND isApproved = 1");
    mysqli_stmt_bind_param($r, 's', $signatoryEmpId);
    mysqli_stmt_execute($r);
    $supervisedCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($r))['c'] ?? 0);
    mysqli_stmt_close($r);

    $r = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM leave_data_for_approval WHERE signatory = ? AND isSentbyAdmin = 1 AND isSupervisor != 1 AND isApproved = 1");
    mysqli_stmt_bind_param($r, 's', $signatoryEmpId);
    mysqli_stmt_execute($r);
    $approvedCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($r))['c'] ?? 0);
    mysqli_stmt_close($r);

    // Total applications this user has ever sent back via "ফেরত পাঠান".
    // This is a history tab, so nothing is excluded once returned — the count
    // must match the unfiltered list in fetch-returned-by-me.php.
    // Table is created lazily by api/leave/return-application.php, so guard
    // with a table-exists check to avoid a fatal on first-boot.
    $_tblCheck = mysqli_query($con, "SHOW TABLES LIKE 'leave_return_history'");
    if ($_tblCheck && mysqli_num_rows($_tblCheck) > 0) {
        $r = mysqli_prepare($con,
            "SELECT COUNT(*) c FROM leave_return_history WHERE returnedBy = ?");
        mysqli_stmt_bind_param($r, 's', $signatoryEmpId);
        mysqli_stmt_execute($r);
        $returnedCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($r))['c'] ?? 0);
        mysqli_stmt_close($r);
    }

    // Applications this user personally declined. Scoped on
    // leave_applications.declinedBy — declining flips every still-pending
    // chain row to isApproved = 2, so that flag can't identify the decider.
    $r = mysqli_prepare($con,
        "SELECT COUNT(*) c FROM leave_applications WHERE status = 2 AND declinedBy = ?");
    mysqli_stmt_bind_param($r, 's', $signatoryEmpId);
    mysqli_stmt_execute($r);
    $declinedCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($r))['c'] ?? 0);
    mysqli_stmt_close($r);
}

// Filter dropdowns
$orgsQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY organization_name ASC");
$orgOptions = '';
while ($o = mysqli_fetch_assoc($orgsQ)) {
    $orgOptions .= '<option value="' . (int)$o['id'] . '">' . htmlspecialchars($o['organization_name']) . '</option>';
}
$secQ = mysqli_query($con, "SELECT id, section_name FROM sections ORDER BY section_name ASC");
$sectionOptions = '';
while ($s = mysqli_fetch_assoc($secQ)) {
    $sectionOptions .= '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($s['section_name']) . '</option>';
}
$ltQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types ORDER BY leaveID ASC");
$leaveTypeOptions = '';
while ($l = mysqli_fetch_assoc($ltQ)) {
    $leaveTypeOptions .= '<option value="' . (int)$l['leaveID'] . '">' . htmlspecialchars($l['leaveTitle']) . '</option>';
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-checks me-2 text-primary"></i>সুপারিশপ্রাপ্ত ও অনুমোদিত আবেদন</h4>
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
        <div class="stat-card stat-info stat-clickable"
             data-tab-target="#supervised"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="যেসব আবেদনে আপনি সুপারিশকারী (supervisor) হিসেবে সুপারিশ দিয়েছেন">
            <div class="stat-icon"><i class="ti tabler-clipboard-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($supervisedCount); ?></div>
                <div class="stat-label">সুপারিশপ্রাপ্ত আবেদন</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="stat-card stat-approved stat-clickable"
             data-tab-target="#approved"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="যেসব আবেদনে আপনি অনুমোদনকারী (signatory) হিসেবে অনুমোদন দিয়েছেন">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($approvedCount); ?></div>
                <div class="stat-label">অনুমোদিত আবেদন</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-0">
        <ul class="nav custom-leave-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#supervised" role="tab">
                    <i class="ti tabler-clipboard-check me-2"></i>
                    <span class="d-none d-sm-inline">সুপারিশপ্রাপ্ত</span>
                    <span class="badge ms-2"><?php echo banglaNumber($supervisedCount); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#approved" role="tab">
                    <i class="ti tabler-circle-check me-2"></i>
                    <span class="d-none d-sm-inline">অনুমোদিত</span>
                    <span class="badge ms-2"><?php echo banglaNumber($approvedCount); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#returnedByMe" role="tab"
                        title="আপনি যেসব আবেদন ফেরত পাঠিয়েছেন সেগুলো ট্র্যাক করুন">
                    <i class="ti tabler-corner-up-left me-2"></i>
                    <span class="d-none d-sm-inline">পুনঃ যাচাই</span>
                    <span class="badge bg-label-warning ms-2"><?php echo banglaNumber($returnedCount); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#declinedByMe" role="tab"
                        title="আপনি যেসব আবেদন না মঞ্জুর করেছেন">
                    <i class="ti tabler-circle-x me-2"></i>
                    <span class="d-none d-sm-inline">অননুমোদিত</span>
                    <span class="badge bg-label-danger ms-2"><?php echo banglaNumber($declinedCount); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3">
            <!-- Tab 1: Supervised -->
            <div class="tab-pane fade show active" id="supervised" role="tabpanel">
                <?php $scope = 'supervised'; require __DIR__ . '/my-applications-filter.inc.php'; ?>
                <div class="table-responsive">
                    <table id="supleaveTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>চাহিত ছুটি</th>
                                <th>প্রস্তাবিত ছুটি</th>
                                <th class="text-center">কার্যাবলী</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Approved -->
            <div class="tab-pane fade" id="approved" role="tabpanel">
                <?php $scope = 'approved'; require __DIR__ . '/my-applications-filter.inc.php'; ?>
                <div class="table-responsive">
                    <table id="approvedLeaveTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>চাহিত ছুটি</th>
                                <th>প্রস্তাবিত ছুটি</th>
                                <th class="text-center">কার্যাবলী</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Returned-by-me (পুনঃ যাচাই) — tracking view -->
            <div class="tab-pane fade" id="returnedByMe" role="tabpanel">
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert" style="border-radius:0.5rem;">
                    <i class="ti tabler-info-circle mt-1"></i>
                    <div>
                        <strong>পুনঃ যাচাই</strong> — আপনি যে সব আবেদন সংশোধনের জন্য ফেরত পাঠিয়েছেন তার সম্পূর্ণ ইতিহাস।
                        আবেদনকারী পুনরায় জমা দিলে বা প্রক্রিয়া শেষ হলেও এন্ট্রিগুলো এখানে থেকে যাবে —
                        প্রতিটির বর্তমান অবস্থা পাশে দেখানো হয়েছে।
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="leaveReturnedTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>চাহিত ছুটি</th>
                                <th>ফেরত পাঠানো হয়েছে</th>
                                <th>ফেরতের কারণ</th>
                                <th>বর্তমান অবস্থা</th>
                                <th class="text-center">কার্যাবলী</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 4: Declined-by-me (অননুমোদিত) — history of my rejections -->
            <div class="tab-pane fade" id="declinedByMe" role="tabpanel">
                <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert" style="border-radius:0.5rem;">
                    <i class="ti tabler-info-circle mt-1"></i>
                    <div>
                        <strong>অননুমোদিত</strong> — আপনি যে সব আবেদন না মঞ্জুর করেছেন তার সম্পূর্ণ ইতিহাস,
                        না মঞ্জুরের তারিখ ও কারণসহ।
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="declinedLeaveTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>চাহিত ছুটি</th>
                                <th>প্রস্তাবিত ছুটি</th>
                                <th>না মঞ্জুরের তারিখ</th>
                                <th>কারণ</th>
                                <th class="text-center">কার্যাবলী</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     আবেদনপত্র preview modal — same-page popup with iframe
     (identical pattern to views/reports/leave-self.php)
═══════════════════════════════════════════════════════ -->
<style>
#appDocModal .modal-dialog { max-width: 95vw; margin: 1rem auto; }
#appDocModal .modal-content { height: calc(100vh - 2rem); display: flex; flex-direction: column; }
#appDocModal .modal-body { flex: 1 1 auto; min-height: 0; padding: 0; position: relative; background: #f5f7fa; }
#appDocModal #appDocIframe { width: 100%; height: 100%; border: 0; background: #fff; display: block; }
#appDocModal #appDocLoader {
    position: absolute; inset: 0;
    background: #fff; z-index: 2;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    transition: opacity 0.2s ease;
}
#appDocModal #appDocLoader.d-none { display: none !important; }
</style>
<div class="modal fade" id="appDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 px-3" style="background:linear-gradient(155deg,#0e1e34 0%,#1e3a5f 100%);color:#fff;border:none;">
                <h5 class="modal-title mb-0" style="color:#fff;font-size:1rem;">
                    <i class="ti tabler-file-text me-2"></i>আবেদনপত্র
                </h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a href="#" id="appDocDownloadBtn" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                        <i class="ti tabler-external-link me-1"></i>নতুন ট্যাবে খুলুন
                    </a>
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                        <i class="ti tabler-x"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div id="appDocLoader">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="text-muted small">আবেদনপত্র লোড হচ্ছে...</div>
                </div>
                <iframe id="appDocIframe" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script type="text/javascript">
var supleaveTableInstance, approvedLeaveTableInstance, leaveReturnedTableInstance, declinedLeaveTableInstance;

var dtLang = {
    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
    search: "",
    searchPlaceholder: "খুঁজুন...",
    lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
    info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
    infoEmpty: "কোন এন্ট্রি নেই",
    infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
    zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
    emptyTable: '<div class="empty-state-rich"><i class="ti tabler-history-toggle"></i><div class="empty-title">কোনো এন্ট্রি নেই</div><div class="empty-subtitle">এই tab-এ এখনো কোনো আবেদন নেই</div></div>',
    paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
};

var dtColumns = [
    { data: "serial",          orderable: true },
    { data: "applicant_cell",  orderable: true },
    { data: "section_center",  orderable: false },
    { data: "requested",       orderable: true },
    { data: "proposed",        orderable: true },
    { data: "action",          orderable: false, searchable: false }
];

var colLabels = ['ক্রমিক', 'আবেদনকারী', 'শাখা ও কেন্দ্র', 'চাহিত ছুটি', 'প্রস্তাবিত ছুটি', 'কার্যাবলী'];
var compactCols = [0, 5];

function decorateRow(row) {
    $(row).find('td').each(function(i) {
        var $td = $(this);
        $td.attr('data-label', colLabels[i] || '');
        if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
        if (compactCols.indexOf(i) !== -1) $td.addClass('compact-cell');
    });
}

function buildFilterPayload(scope) {
    var prefix = '#' + scope;
    var dateRange = $(prefix + 'DateRange').val() || '';
    var dateFrom = '', dateTo = '';
    if (dateRange.indexOf(' to ') !== -1) {
        var parts = dateRange.split(' to ');
        dateFrom = parts[0].trim();
        dateTo   = parts[1].trim();
    } else if (dateRange) {
        dateFrom = dateRange.trim();
        dateTo   = dateRange.trim();
    }
    return {
        centerFilter:    $(prefix + 'OrgFilter').val()        || '',
        sectionFilter:   $(prefix + 'SectionFilter').val()    || '',
        employeeFilter:  $(prefix + 'EmployeeFilter').val()   || '',
        leaveTypeFilter: $(prefix + 'LeaveTypeFilter').val()  || '',
        dateFrom:        dateFrom,
        dateTo:          dateTo
    };
}

function initSelect2Employee($el, scope) {
    $el.select2({
        placeholder: 'নাম বা আইডি দিয়ে খুঁজুন...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '../../api/leave/search-employees.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term || '',
                    org: $('#' + scope + 'OrgFilter').val() || 0,
                    page: params.page || 1
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results,
                    pagination: { more: !!(data.pagination && data.pagination.more) }
                };
            },
            cache: true
        },
        minimumInputLength: 0
    });
}

function initFilterControls(scope) {
    var prefix = '#' + scope;
    $(prefix + 'OrgFilter, ' + prefix + 'SectionFilter, ' + prefix + 'LeaveTypeFilter')
        .select2({ width: '100%', allowClear: true, placeholder: '— সকল —' });
    initSelect2Employee($(prefix + 'EmployeeFilter'), scope);
    if ($.fn.flatpickr) {
        $(prefix + 'DateRange').flatpickr({
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y'
        });
    }
}

function reloadTable(scope) {
    if (scope === 'supervised' && supleaveTableInstance)      supleaveTableInstance.ajax.reload();
    if (scope === 'approved'   && approvedLeaveTableInstance) approvedLeaveTableInstance.ajax.reload();
}

function updateActiveFilterCount(scope) {
    var payload = buildFilterPayload(scope);
    var count = 0;
    if (payload.centerFilter)    count++;
    if (payload.sectionFilter)   count++;
    if (payload.employeeFilter)  count++;
    if (payload.leaveTypeFilter) count++;
    if (payload.dateFrom || payload.dateTo) count++;
    var $badge = $('.filter-active-count[data-scope="' + scope + '"]');
    if (count > 0) $badge.text(count).addClass('has-active');
    else           $badge.text('').removeClass('has-active');

    var prefix = '#' + scope;
    [
        prefix + 'OrgFilter', prefix + 'SectionFilter',
        prefix + 'EmployeeFilter', prefix + 'LeaveTypeFilter'
    ].forEach(function(sel) {
        var $el = $(sel);
        var hasVal = !!($el.val() && $el.val().length);
        $el.toggleClass('has-value', hasVal);
        $el.next('.select2-container').toggleClass('select2-active-value', hasVal);
    });
    var $dr = $(prefix + 'DateRange');
    $dr.toggleClass('has-value', !!$dr.val());
}

$(document).ready(function() {
    initFilterControls('supervised');

    supleaveTableInstance = $('#supleaveTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: "../../api/leave/fetch-supervised.php",
            type: "POST",
            data: function(d) { Object.assign(d, buildFilterPayload('supervised')); }
        },
        columns: dtColumns,
        createdRow: function(row) { decorateRow(row); },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: dtLang
    });

    // Filter input changes
    $(document).on('change', '.filter-input', function() {
        var scope = $(this).closest('[data-scope]').data('scope');
        updateActiveFilterCount(scope);
        reloadTable(scope);
    });

    // Reset
    $(document).on('click', '.filter-reset', function(e) {
        e.stopPropagation();
        var scope = $(this).data('scope');
        var prefix = '#' + scope;
        $(prefix + 'OrgFilter, ' + prefix + 'SectionFilter, ' + prefix + 'LeaveTypeFilter, ' + prefix + 'EmployeeFilter')
            .val(null).trigger('change.select2');
        var dr = $(prefix + 'DateRange');
        if (dr[0] && dr[0]._flatpickr) dr[0]._flatpickr.clear();
        updateActiveFilterCount(scope);
        reloadTable(scope);
    });

    // Refresh
    $(document).on('click', '.table-refresh', function() {
        var scope = $(this).data('scope');
        var $i = $(this).find('i').addClass('ti-spin');
        reloadTable(scope);
        setTimeout(function(){ $i.removeClass('ti-spin'); }, 600);
    });

    // Filter panel toggle
    function _toggleFilterPanel(btn) {
        var $panel = $(btn).closest('.filter-panel');
        var $body  = $panel.find('.filter-panel-body').first();
        if (!$body.length) return;
        var nowCollapsed = $panel.hasClass('is-collapsed');
        if (nowCollapsed) {
            $panel.removeClass('is-collapsed');
            $body.stop(true, true).slideDown(220);
            $(btn).attr('aria-expanded', 'true');
        } else {
            $body.stop(true, true).slideUp(200, function() { $panel.addClass('is-collapsed'); });
            $(btn).attr('aria-expanded', 'false');
        }
        $(btn).find('.filter-chevron').css('transform', nowCollapsed ? 'rotate(180deg)' : '');
    }
    $('.filter-panel.is-collapsed .filter-panel-body').hide();
    document.querySelectorAll('.filter-panel-toggle').forEach(function(btn){
        btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); _toggleFilterPanel(btn); });
    });

    // Approved table — lazy init on tab show
    $('button[data-bs-target="#approved"]').on('shown.bs.tab', function () {
        if (!approvedLeaveTableInstance) {
            initFilterControls('approved');
            approvedLeaveTableInstance = $('#approvedLeaveTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: {
                    url: "../../api/leave/fetch-approved.php",
                    type: "POST",
                    data: function(d) { Object.assign(d, buildFilterPayload('approved')); }
                },
                columns: dtColumns,
                createdRow: function(row) { decorateRow(row); },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
                language: dtLang
            });
        }
    });

    // Stat card → tab + active highlight
    function syncStatActive() {
        var activeTarget = $('.custom-leave-tabs .nav-link.active').data('bs-target');
        $('.stat-clickable').removeClass('is-active');
        $('.stat-clickable[data-tab-target="' + activeTarget + '"]').addClass('is-active');
    }
    $('.stat-clickable').on('click', function() {
        var target = $(this).data('tab-target');
        if (!target) return;
        var $btn = $('button[data-bs-target="' + target + '"]');
        if ($btn.length && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance($btn[0]).show();
        } else {
            $btn.trigger('click');
        }
    });
    $('.custom-leave-tabs .nav-link').on('shown.bs.tab', syncStatActive);
    syncStatActive();

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }

    // Returned-by-me table (lazy load on tab switch) — history of every
    // application this user has sent back for re-verification.
    var returnedColumns = [
        { data: "serial",         orderable: false },
        { data: "applicant_cell", orderable: false },
        { data: "section_center", orderable: false },
        { data: "requested",      orderable: false },
        { data: "returned_to",    orderable: false },
        { data: "note",           orderable: false },
        { data: "status",         orderable: false },
        { data: "action",         orderable: false, searchable: false, className: 'text-center' }
    ];
    var returnedLabels = ['ক্রমিক','আবেদনকারী','শাখা ও কেন্দ্র','চাহিত ছুটি','ফেরত পাঠানো হয়েছে','ফেরতের কারণ','বর্তমান অবস্থা','কার্যাবলী'];
    var returnedLang = Object.assign({}, dtLang, {
        emptyTable: '<div class="empty-state-rich"><i class="ti tabler-corner-up-left"></i><div class="empty-title">কোনো পুনঃ যাচাই নেই</div><div class="empty-subtitle">আপনি এখনো কোনো আবেদন ফেরত পাঠাননি</div></div>'
    });
    $('button[data-bs-target="#returnedByMe"]').on('shown.bs.tab', function () {
        if (!leaveReturnedTableInstance) {
            leaveReturnedTableInstance = $('#leaveReturnedTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: {
                    url:  '../../api/leave/fetch-returned-by-me.php',
                    type: 'POST'
                },
                columns: returnedColumns,
                createdRow: function(row) {
                    $(row).find('td').each(function(i) {
                        $(this).attr('data-label', returnedLabels[i] || '');
                    });
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
                language: returnedLang,
                order: []
            });
        } else {
            leaveReturnedTableInstance.ajax.reload(null, false);
        }
    });

    // Declined-by-me table (lazy load on tab switch) — history of every
    // application this user has personally declined.
    var declinedColumns = [
        { data: "serial",         orderable: false },
        { data: "applicant_cell", orderable: false },
        { data: "section_center", orderable: false },
        { data: "requested",      orderable: false },
        { data: "proposed",       orderable: false },
        { data: "declined_at",    orderable: false },
        { data: "reason",         orderable: false },
        { data: "action",         orderable: false, searchable: false, className: 'text-center' }
    ];
    var declinedLabels = ['ক্রমিক','আবেদনকারী','শাখা ও কেন্দ্র','চাহিত ছুটি','প্রস্তাবিত ছুটি','না মঞ্জুরের তারিখ','কারণ','কার্যাবলী'];
    var declinedLang = Object.assign({}, dtLang, {
        emptyTable: '<div class="empty-state-rich"><i class="ti tabler-circle-x"></i><div class="empty-title">কোনো অননুমোদিত আবেদন নেই</div><div class="empty-subtitle">আপনি এখনো কোনো আবেদন না মঞ্জুর করেননি</div></div>'
    });
    $('button[data-bs-target="#declinedByMe"]').on('shown.bs.tab', function () {
        if (!declinedLeaveTableInstance) {
            declinedLeaveTableInstance = $('#declinedLeaveTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: {
                    url:  '../../api/leave/fetch-declined-by-me.php',
                    type: 'POST'
                },
                columns: declinedColumns,
                createdRow: function(row) {
                    $(row).find('td').each(function(i) {
                        $(this).attr('data-label', declinedLabels[i] || '');
                    });
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
                language: declinedLang,
                order: []
            });
        } else {
            declinedLeaveTableInstance.ajax.reload(null, false);
        }
    });

    // ── আবেদনপত্র preview modal wiring ─────────────────────
    var $adModal  = $('#appDocModal');
    var $adIframe = $('#appDocIframe');
    var $adLoader = $('#appDocLoader');
    var $adDlBtn  = $('#appDocDownloadBtn');

    // Delegated — rows re-render on every DataTables draw across both tabs
    $(document).on('click', '.app-doc-view', function() {
        var url = $(this).data('url');
        if (!url) return;
        $adLoader.removeClass('d-none');
        $adIframe[0].src = url;
        $adDlBtn.attr('href', url);
        $adModal.modal('show');
    });

    $adIframe[0].addEventListener('load', function() {
        if ($adIframe[0].src && $adIframe[0].src.indexOf('about:blank') === -1) {
            $adLoader.addClass('d-none');
        }
    });

    $adModal.on('hidden.bs.modal', function() {
        $adIframe[0].src = 'about:blank';
        $adLoader.removeClass('d-none');
    });
});
</script>
