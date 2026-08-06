<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Load organizations for filter dropdown
$orgsQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY organization_name ASC");
$orgOptions = '';
while ($orgRow = mysqli_fetch_assoc($orgsQ)) {
    $orgOptions .= '<option value="' . intval($orgRow['id']) . '">' . htmlspecialchars($orgRow['organization_name']) . '</option>';
}

// Load sections
$secQ = mysqli_query($con, "SELECT id, section_name FROM sections ORDER BY section_name ASC");
$sectionOptions = '';
while ($secRow = mysqli_fetch_assoc($secQ)) {
    $sectionOptions .= '<option value="' . intval($secRow['id']) . '">' . htmlspecialchars($secRow['section_name']) . '</option>';
}

// Load leave types
$ltQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types ORDER BY leaveID ASC");
$leaveTypeOptions = '';
while ($ltRow = mysqli_fetch_assoc($ltQ)) {
    $leaveTypeOptions .= '<option value="' . intval($ltRow['leaveID']) . '">' . htmlspecialchars($ltRow['leaveTitle']) . '</option>';
}

// Stats: pending counts for the current signatory + how many applications
// this user has personally sent back for re-verification (tracking).
$signatoryEmpId = $getUserInfoQRW['employee_id'] ?? '';
$superviseCount = 0;
$approveCount   = 0;
if ($signatoryEmpId) {
    // Exclude returned apps (la.status = 3) — while sitting on the applicant's
    // desk they must not inflate the supervisor's pending count. See the
    // matching filter in fetch-waiting-supervise.php.
    $_s = mysqli_prepare($con,
        "SELECT COUNT(*) AS c
         FROM leave_data_for_approval ldfa
         INNER JOIN leave_applications la ON ldfa.leaveApplicationID = la.dataID
         WHERE ldfa.signatory = ? AND ldfa.isSupervisor = 1 AND ldfa.isApproved = 0
           AND la.status <> 3");
    mysqli_stmt_bind_param($_s, 's', $signatoryEmpId);
    mysqli_stmt_execute($_s);
    $superviseCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_s))['c'] ?? 0);
    mysqli_stmt_close($_s);

    // Mirror the fetch-waiting-approve.php list query — only count rows the user
    // can act on right now: admin has forwarded AND the previous signatory in the
    // chain has already approved (so signatories down the chain don't get badged
    // before their turn).
    $_s = mysqli_prepare($con,
        "SELECT COUNT(*) AS c
         FROM leave_data_for_approval ldfa
         INNER JOIN leave_applications la ON ldfa.leaveApplicationID = la.dataID
         WHERE ldfa.signatory = ?
           AND ldfa.isSupervisor = 0
           AND ldfa.isApproved   = 0
           AND ldfa.isSentbyAdmin = 1
           AND la.status <> 3
           AND (
             ldfa.prevSignatory = 0
             OR EXISTS (
               SELECT 1 FROM leave_data_for_approval prev
               WHERE prev.leaveApplicationID = ldfa.leaveApplicationID
                 AND prev.signatory = ldfa.prevSignatory
                 AND prev.isApproved = 1
                 AND prev.serial    = ldfa.serial - 1
             )
           )");
    mysqli_stmt_bind_param($_s, 's', $signatoryEmpId);
    mysqli_stmt_execute($_s);
    $approveCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($_s))['c'] ?? 0);
    mysqli_stmt_close($_s);

}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-clipboard-check me-2 text-primary"></i>ছুটির সুপারিশ ও অনুমোদন</h4>
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
        <div class="stat-card stat-pending stat-clickable"
             data-tab-target="#supervision"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="সুপারিশের জন্য অপেক্ষমান আবেদনের সংখ্যা — যেগুলোতে আপনি সুপারিশকারী (supervisor) হিসেবে এখনো সুপারিশ করেননি">
            <div class="stat-icon"><i class="ti tabler-clipboard-list"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($superviseCount); ?></div>
                <div class="stat-label">সুপারিশের অপেক্ষায়</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="stat-card stat-info stat-clickable"
             data-tab-target="#approval"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="অনুমোদনের জন্য অপেক্ষমান আবেদনের সংখ্যা — যেগুলোতে আপনি signatory (approver) হিসেবে এখনো সিদ্ধান্ত নেননি">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($approveCount); ?></div>
                <div class="stat-label">অনুমোদনের অপেক্ষায়</div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Approval Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-0">
        <!-- Nav Tabs -->
        <ul class="nav custom-leave-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#supervision" role="tab">
                    <i class="ti tabler-clipboard-check me-2"></i>
                    <span class="d-none d-sm-inline">সুপারিশ</span>
                    <span class="badge bg-label-warning ms-2"><?php echo banglaNumber($superviseCount); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#approval" role="tab">
                    <i class="ti tabler-circle-check me-2"></i>
                    <span class="d-none d-sm-inline">অনুমোদন</span>
                    <span class="badge bg-label-info ms-2"><?php echo banglaNumber($approveCount); ?></span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content p-3">
            <!-- Tab 1: Supervision (সুপারিশ) -->
            <div class="tab-pane fade show active" id="supervision" role="tabpanel">
                <div class="filter-panel mb-3 is-collapsed" data-scope="supervise">
                    <div class="filter-panel-header">
                        <button type="button" class="filter-panel-toggle" data-scope="supervise" aria-expanded="false" aria-controls="filterBody-supervise">
                            <i class="ti tabler-filter me-1"></i>
                            <span class="filter-panel-title">ফিল্টার</span>
                            <span class="filter-active-count" data-scope="supervise"></span>
                            <i class="ti tabler-chevron-down filter-chevron ms-2"></i>
                        </button>
                        <div class="filter-panel-actions">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary table-refresh" data-scope="supervise" title="টেবিল রিফ্রেশ" data-bs-toggle="tooltip">
                                <i class="ti tabler-refresh"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-label-secondary filter-reset" data-scope="supervise">
                                <i class="ti tabler-x me-1"></i>রিসেট
                            </button>
                        </div>
                    </div>
                    <div class="filter-panel-body" id="filterBody-supervise">
                    <div class="row g-2">
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                            <select id="superviseOrgFilter" class="form-select form-select-sm filter-input" data-scope="supervise">
                                <option value="">সকল কেন্দ্র</option>
                                <?= $orgOptions ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                            <select id="superviseSectionFilter" class="form-select form-select-sm filter-input filter-section" data-scope="supervise">
                                <option value="">সকল শাখা</option>
                                <?= $sectionOptions ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-user"></i>আবেদনকারী</label>
                            <select id="superviseEmployeeFilter" class="form-select form-select-sm filter-input filter-employee" data-scope="supervise">
                                <option value="">সকল</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-clipboard-list"></i>ছুটির ধরণ</label>
                            <select id="superviseLeaveTypeFilter" class="form-select form-select-sm filter-input" data-scope="supervise">
                                <option value="">সকল ধরণ</option>
                                <?= $leaveTypeOptions ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-6">
                            <label class="filter-label"><i class="ti tabler-calendar-event"></i>ছুটির শুরু তারিখ (Range)</label>
                            <div class="field-shell">
                                <i class="ti tabler-calendar field-icon"></i>
                                <input type="text" id="superviseDateRange" class="form-control form-control-sm filter-input filter-daterange" data-scope="supervise" placeholder="তারিখ নির্বাচন করুন...">
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="leaveTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th class="col-checkbox text-center"><input type="checkbox" class="form-check-input select-all" data-scope="supervise" title="সকল নির্বাচন"></th>
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

            <!-- Tab 2: Approval (অনুমোদন) -->
            <div class="tab-pane fade" id="approval" role="tabpanel">
                <div class="filter-panel mb-3 is-collapsed" data-scope="approve">
                    <div class="filter-panel-header">
                        <button type="button" class="filter-panel-toggle" data-scope="approve" aria-expanded="false" aria-controls="filterBody-approve">
                            <i class="ti tabler-filter me-1"></i>
                            <span class="filter-panel-title">ফিল্টার</span>
                            <span class="filter-active-count" data-scope="approve"></span>
                            <i class="ti tabler-chevron-down filter-chevron ms-2"></i>
                        </button>
                        <div class="filter-panel-actions">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary table-refresh" data-scope="approve" title="টেবিল রিফ্রেশ" data-bs-toggle="tooltip">
                                <i class="ti tabler-refresh"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-label-secondary filter-reset" data-scope="approve">
                                <i class="ti tabler-x me-1"></i>রিসেট
                            </button>
                        </div>
                    </div>
                    <div class="filter-panel-body" id="filterBody-approve">
                    <div class="row g-2">
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                            <select id="approveOrgFilter" class="form-select form-select-sm filter-input" data-scope="approve">
                                <option value="">সকল কেন্দ্র</option>
                                <?= $orgOptions ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                            <select id="approveSectionFilter" class="form-select form-select-sm filter-input filter-section" data-scope="approve">
                                <option value="">সকল শাখা</option>
                                <?= $sectionOptions ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-user"></i>আবেদনকারী</label>
                            <select id="approveEmployeeFilter" class="form-select form-select-sm filter-input filter-employee" data-scope="approve">
                                <option value="">সকল</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <label class="filter-label"><i class="ti tabler-clipboard-list"></i>ছুটির ধরণ</label>
                            <select id="approveLeaveTypeFilter" class="form-select form-select-sm filter-input" data-scope="approve">
                                <option value="">সকল ধরণ</option>
                                <?= $leaveTypeOptions ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-6">
                            <label class="filter-label"><i class="ti tabler-calendar-event"></i>ছুটির শুরু তারিখ (Range)</label>
                            <div class="field-shell">
                                <i class="ti tabler-calendar field-icon"></i>
                                <input type="text" id="approveDateRange" class="form-control form-control-sm filter-input filter-daterange" data-scope="approve" placeholder="তারিখ নির্বাচন করুন...">
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="leaveApproveTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th class="col-checkbox text-center"><input type="checkbox" class="form-check-input select-all" data-scope="approve" title="সকল নির্বাচন"></th>
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

<!-- Floating bulk-action bar -->
<div class="bulk-action-bar" id="bulkActionBar" hidden>
    <div class="bulk-action-inner">
        <span class="bulk-count"><i class="ti tabler-checkbox me-1"></i><span id="bulkCount">০</span> টি নির্বাচিত</span>
        <button type="button" class="btn btn-sm btn-success" id="bulkApproveBtn">
            <i class="ti tabler-checks me-1"></i>প্রস্তাবিত হিসেবেই অনুমোদন
        </button>
        <button type="button" class="btn btn-sm btn-label-secondary" id="bulkClearBtn">
            <i class="ti tabler-x me-1"></i>বাতিল
        </button>
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

<script type="text/javascript">
// Wait for libs (jQuery + DataTables) to be loaded.
// The script lives inside the turbo-frame so it executes on Turbo nav too,
// but libs are emitted in the footer (after this script on hard load), so we
// poll briefly until they're ready.
(function bootApprovalPage() {
    if (typeof jQuery === 'undefined' || !jQuery.fn ||
        !jQuery.fn.DataTable || !jQuery.fn.select2 ||
        typeof Swal === 'undefined' || typeof bootstrap === 'undefined') {
        return setTimeout(bootApprovalPage, 20);
    }

var leaveTableInstance;
var leaveApproveTableInstance;

var dtLang = {
    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
    search: "",
    searchPlaceholder: "খুঁজুন...",
    lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
    info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
    infoEmpty: "কোন এন্ট্রি নেই",
    infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
    zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
    emptyTable: '<div class="empty-state-rich"><i class="ti tabler-clipboard-off"></i><div class="empty-title">কোন আবেদন নেই</div><div class="empty-subtitle">এই মুহূর্তে কোনো ছুটির আবেদন আপনার সিদ্ধান্তের জন্য অপেক্ষা করছে না</div></div>',
    paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
};

var dtColumns = [
    { data: "row_check",       orderable: false, searchable: false, className: 'col-checkbox text-center' },
    { data: "serial",          orderable: true },
    { data: "applicant_cell",  orderable: true },
    { data: "section_center",  orderable: false },
    { data: "requested",       orderable: true },
    { data: "proposed",        orderable: true },
    { data: "action",          orderable: false, searchable: false }
];

var colLabels = ['', 'ক্রমিক', 'আবেদনকারী', 'শাখা ও কেন্দ্র', 'চাহিত ছুটি', 'প্রস্তাবিত ছুটি', 'কার্যাবলী'];
var compactCols = [0, 1, 6];

function decorateRow(row, data) {
    $(row).find('td').each(function(i) {
        var $td = $(this);
        $td.attr('data-label', colLabels[i] || '');
        if ($.trim($td.text()) === '' && $td.children().length === 0) {
            $td.addClass('is-empty');
        }
        if (compactCols.indexOf(i) !== -1) {
            $td.addClass('compact-cell');
        }
    });
}

function buildFilterPayload(scope) {
    var prefix = scope === 'supervise' ? '#supervise' : '#approve';
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
                    org: $('#' + (scope === 'supervise' ? 'supervise' : 'approve') + 'OrgFilter').val() || 0,
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
    var prefix = '#' + (scope === 'supervise' ? 'supervise' : 'approve');

    // Plain selects already work — just wrap in Select2 for searching where useful
    $(prefix + 'OrgFilter, ' + prefix + 'SectionFilter, ' + prefix + 'LeaveTypeFilter')
        .select2({ width: '100%', allowClear: true, placeholder: '— সকল —' });

    initSelect2Employee($(prefix + 'EmployeeFilter'), scope);

    // Date range
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
    if (scope === 'supervise' && leaveTableInstance) leaveTableInstance.ajax.reload();
    if (scope === 'approve'   && leaveApproveTableInstance) leaveApproveTableInstance.ajax.reload();
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
    if (count > 0) {
        $badge.text(count).addClass('has-active');
    } else {
        $badge.text('').removeClass('has-active');
    }
    // Visually mark which fields have a value
    var prefix = '#' + (scope === 'supervise' ? 'supervise' : 'approve');
    [
        prefix + 'OrgFilter',
        prefix + 'SectionFilter',
        prefix + 'EmployeeFilter',
        prefix + 'LeaveTypeFilter'
    ].forEach(function(sel) {
        var $el = $(sel);
        var hasVal = !!($el.val() && $el.val().length);
        $el.toggleClass('has-value', hasVal);
        // Tint Select2 container for the same field
        var $s2 = $el.next('.select2-container');
        $s2.toggleClass('select2-active-value', hasVal);
    });
    var $dr = $(prefix + 'DateRange');
    $dr.toggleClass('has-value', !!$dr.val());
}

$(document).ready(function() {

    initFilterControls('supervise');

    // Supervision table
    leaveTableInstance = $('#leaveTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: "../../api/leave/fetch-waiting-supervise.php",
            type: "POST",
            data: function(d) { Object.assign(d, buildFilterPayload('supervise')); }
        },
        columns: dtColumns,
        // Newest first — DataTables' default ASC pushed the latest application
        // to the bottom; force column 1 (serial/dataID) DESC so the most recent
        // arrivals are at the top of the reviewer's queue.
        order: [[1, 'desc']],
        createdRow: function(row) { decorateRow(row); },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: dtLang
    });

    // Filter change handlers — both scopes
    $(document).on('change', '.filter-input', function() {
        var scope = $(this).closest('[data-scope]').data('scope');
        updateActiveFilterCount(scope);
        reloadTable(scope);
    });

    // Reset button
    $(document).on('click', '.filter-reset', function(e) {
        e.stopPropagation();
        var scope = $(this).data('scope');
        var prefix = '#' + (scope === 'supervise' ? 'supervise' : 'approve');
        $(prefix + 'OrgFilter, ' + prefix + 'SectionFilter, ' + prefix + 'LeaveTypeFilter, ' + prefix + 'EmployeeFilter')
            .val(null).trigger('change.select2');
        var dr = $(prefix + 'DateRange');
        if (dr[0] && dr[0]._flatpickr) dr[0]._flatpickr.clear();
        updateActiveFilterCount(scope);
        reloadTable(scope);
    });

    // ── Refresh button ──────────────────────────────────────
    $(document).on('click', '.table-refresh', function() {
        var scope = $(this).data('scope');
        var $i = $(this).find('i').addClass('ti-spin');
        reloadTable(scope);
        setTimeout(function(){ $i.removeClass('ti-spin'); }, 600);
    });

    // ── Bulk select / actions ──────────────────────────────
    function getActiveScope() {
        return $('.tab-pane.active').find('table').first().attr('id') === 'leaveTable' ? 'supervise' : 'approve';
    }
    function refreshBulkBar() {
        var n = $('.row-check:checked').length;
        var $bar = $('#bulkActionBar');
        if (n > 0) {
            $('#bulkCount').text(toBangla(n));
            $bar.attr('hidden', false).addClass('is-visible');
        } else {
            $bar.removeClass('is-visible').attr('hidden', true);
        }
    }
    function toBangla(num) {
        return String(num).replace(/[0-9]/g, function(d){ return ['০','১','২','৩','৪','৫','৬','৭','৮','৯'][+d]; });
    }
    // Row checkbox change
    $(document).on('change', '.row-check', function() {
        refreshBulkBar();
        // Sync select-all
        var $tbl = $(this).closest('table');
        var total = $tbl.find('.row-check').length;
        var checked = $tbl.find('.row-check:checked').length;
        $tbl.find('.select-all').prop('checked', total > 0 && total === checked).prop('indeterminate', checked > 0 && checked < total);
    });
    // Select-all
    $(document).on('change', '.select-all', function() {
        var $tbl = $(this).closest('table');
        $tbl.find('.row-check').prop('checked', this.checked);
        refreshBulkBar();
    });
    // Bulk clear
    $('#bulkClearBtn').on('click', function() {
        $('.row-check, .select-all').prop('checked', false).prop('indeterminate', false);
        refreshBulkBar();
    });
    // Bulk approve
    $('#bulkApproveBtn').on('click', function() {
        var ids = $('.row-check:checked').map(function(){ return $(this).val(); }).get();
        if (!ids.length) return;
        Swal.fire({
            title: 'নিশ্চিত করুন',
            html: '<b>' + toBangla(ids.length) + '</b> টি আবেদন <b>প্রস্তাবিত হিসেবেই</b> অনুমোদন করা হবে। কোনো তারিখ বা ধরণ পরিবর্তন হবে না। অগ্রসর হবেন?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1a7e44',
            confirmButtonText: 'হ্যাঁ, অনুমোদন',
            cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        }).then(function(r){
            if (!r.isConfirmed) return;
            $.post('../../api/leave/bulk-approve-asis.php', { dataIDs: ids }, function(resp){
                var msg = (resp.approved||0) + ' টি অনুমোদিত';
                if (resp.skipped) msg += ', ' + resp.skipped + ' টি বাদ পড়েছে';
                Swal.fire({
                    title: resp.success ? 'সম্পন্ন' : 'ত্রুটি',
                    html: msg + (resp.errors && resp.errors.length ? '<br><small class="text-muted">' + resp.errors.join('<br>') + '</small>' : ''),
                    icon: resp.success ? 'success' : 'error',
                    confirmButtonColor: '#6c5ce7',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                $('.row-check, .select-all').prop('checked', false).prop('indeterminate', false);
                refreshBulkBar();
                if (leaveTableInstance) leaveTableInstance.ajax.reload(null, false);
                if (leaveApproveTableInstance) leaveApproveTableInstance.ajax.reload(null, false);
            }, 'json').fail(function(){
                Swal.fire({ title: 'ত্রুটি', text: 'অনুরোধ ব্যর্থ হয়েছে', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
            });
        });
    });

    // Stats card tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }

    // Stat cards → switch to their tab + highlight
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
    syncStatActive(); // initial

    // Filter panel toggle — jQuery slide animation for smooth expand/collapse
    function _toggleFilterPanel(btn) {
        var $panel = $(btn).closest('.filter-panel');
        var $body  = $panel.find('.filter-panel-body').first();
        if (!$body.length) return;
        var nowCollapsed = $panel.hasClass('is-collapsed');
        if (nowCollapsed) {
            // Expand
            $panel.removeClass('is-collapsed');
            $body.stop(true, true).slideDown(220);
            $(btn).attr('aria-expanded', 'true');
        } else {
            // Collapse
            $body.stop(true, true).slideUp(200, function() {
                $panel.addClass('is-collapsed');
            });
            $(btn).attr('aria-expanded', 'false');
        }
        var $chev = $(btn).find('.filter-chevron');
        $chev.css('transform', nowCollapsed ? 'rotate(180deg)' : '');
    }
    // Initial state — hide body if collapsed by default
    $('.filter-panel.is-collapsed .filter-panel-body').hide();
    // Bind clicks (native event listener for resilience)
    document.querySelectorAll('.filter-panel-toggle').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            _toggleFilterPanel(btn);
        });
    });

    // Approval table (lazy load on tab switch)
    $('button[data-bs-target="#approval"]').on('shown.bs.tab', function () {
        if (!leaveApproveTableInstance) {
            initFilterControls('approve');

            leaveApproveTableInstance = $('#leaveApproveTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: {
                    url: "../../api/leave/fetch-waiting-approve.php",
                    type: "POST",
                    data: function(d) { Object.assign(d, buildFilterPayload('approve')); }
                },
                columns: dtColumns,
                order: [[1, 'desc']],
                createdRow: function(row) { decorateRow(row); },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
                language: dtLang
            });
        }
    });

});

// Exposed globally so inline HTML onclick handlers can call it.
window.removeData = function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই ডেটা মুছে ফেলতে চান?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন',
        cancelButtonText: 'বাতিল',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'post',
                url: 'delete_data.php',
                data: 'dataID=' + dataID + '&tableName=modules',
                success: function() {
                    $("#tr_" + sl).fadeOut(1000);
                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে!',
                        text: 'ডেটা সফলভাবে মুছে ফেলা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                },
                error: function(e) {
                    console.log(e);
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'ডেটা মুছতে ব্যর্থ হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
};

})(); // end bootApprovalPage
</script>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>
