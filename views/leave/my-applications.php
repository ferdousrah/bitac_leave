<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$signatoryEmpId = $getUserInfoQRW['employee_id'] ?? '';

// Stats counts
$supervisedCount = 0;
$approvedCount   = 0;
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
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script type="text/javascript">
var supleaveTableInstance, approvedLeaveTableInstance;

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
});
</script>
