<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(__DIR__ . '/../../library/number_converter.php');

// Resolve viewer's organization
if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = (int)$_SESSION['centerAdminOrgID'];
} else {
    $empID_ = (int)($_SESSION['employeeID'] ?? 0);
    $r = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = $empID_ LIMIT 1");
    $orgID = (int)(mysqli_fetch_assoc($r)['organization_id'] ?? 0);
}

$pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM leave_joining_data_for_approval lj
     LEFT JOIN leave_joining_application lja ON lj.leaveApplicationID = lja.leaveApplicationID
     LEFT JOIN leave_applications la ON lj.leaveApplicationID = la.dataID
     WHERE lj.isSupervisor=1 AND lj.isApproved=1 AND lj.isSentbyAdmin=0 AND lja.joiningType != 1 AND la.organization_id=$orgID"))['c'] ?? 0);
$editedCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM leave_joining_data_for_approval lj
     LEFT JOIN leave_joining_application lja ON lj.leaveApplicationID = lja.leaveApplicationID
     LEFT JOIN leave_applications la ON lj.leaveApplicationID = la.dataID
     WHERE lj.isSupervisor=1 AND lj.isApproved=1 AND lj.isSentbyAdmin=1 AND lja.joiningType != 1 AND la.organization_id=$orgID"))['c'] ?? 0);

// Filter dropdowns
$secOptions = '';
$secQ = mysqli_query($con, "SELECT id, section_name FROM sections ORDER BY section_name ASC");
while ($s = mysqli_fetch_assoc($secQ)) {
    $secOptions .= '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($s['section_name']) . '</option>';
}
$joiningTypeMap = [
    1 => 'সঠিক সময়ে যোগদান',
    2 => 'অগ্রিম যোগদান',
    3 => 'বর্ধিত ছুটির আবেদন',
];
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-check me-2 text-primary"></i>কর্মক্ষেত্রে যোগদানের আবেদন সম্পাদনা</h4>
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
             data-tab-target="#pendingLeaves"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="সুপারভাইজার সুপারিশের পরে — অ্যাডমিনের কাছে এখনো ফরওয়ার্ড করা হয়নি">
            <div class="stat-icon"><i class="ti tabler-clock"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($pendingCount); ?></div>
                <div class="stat-label">প্রক্রিয়াধীন ছুটি</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="stat-card stat-info stat-clickable"
             data-tab-target="#editedLeaves"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="অ্যাডমিন কর্তৃক সম্পাদিত / ফরওয়ার্ড করা যোগদান আবেদন">
            <div class="stat-icon"><i class="ti tabler-edit-circle"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($editedCount); ?></div>
                <div class="stat-label">সম্পাদিত ছুটি</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-0">
        <ul class="nav custom-leave-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#pendingLeaves" role="tab">
                    <i class="ti tabler-clock me-2"></i>
                    <span class="d-none d-sm-inline">প্রক্রিয়াধীন</span>
                    <span class="badge ms-2"><?php echo banglaNumber($pendingCount); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#editedLeaves" role="tab">
                    <i class="ti tabler-edit-circle me-2"></i>
                    <span class="d-none d-sm-inline">সম্পাদিত</span>
                    <span class="badge ms-2"><?php echo banglaNumber($editedCount); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3">
            <!-- Tab 1: Pending -->
            <div class="tab-pane fade show active" id="pendingLeaves" role="tabpanel">
                <?php $scope = 'pendJ'; require __DIR__ . '/manage-approved-leaves-filter.inc.php'; ?>
                <div class="table-responsive">
                    <table id="pendingLeavesTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>আবেদনের প্রকার</th>
                                <th>প্রাথমিক অনুমোদিত</th>
                                <th>ভোগকৃত ছুটি</th>
                                <th>স্টেটাস</th>
                                <th class="text-center">নথি কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Edited -->
            <div class="tab-pane fade" id="editedLeaves" role="tabpanel">
                <?php $scope = 'editJ'; require __DIR__ . '/manage-approved-leaves-filter.inc.php'; ?>
                <div class="table-responsive">
                    <table id="editedLeavesTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>শাখা ও কেন্দ্র</th>
                                <th>আবেদনের প্রকার</th>
                                <th>প্রাথমিক অনুমোদিত</th>
                                <th>ভোগকৃত ছুটি</th>
                                <th>স্টেটাস</th>
                                <th class="text-center">নথি কার্যক্রম</th>
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

<script>
var pendingLeavesTableInstance, editedLeavesTableInstance;

var dtLang = {
    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
    search: "",
    searchPlaceholder: "খুঁজুন...",
    lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
    info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
    infoEmpty: "কোন এন্ট্রি নেই",
    infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
    zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
    emptyTable: '<div class="empty-state-rich"><i class="ti tabler-user-x"></i><div class="empty-title">কোন আবেদন নেই</div><div class="empty-subtitle">এই tab-এ কোনো যোগদান আবেদন পাওয়া যায়নি</div></div>',
    paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
};

var dtCols = [
    { data: "serial", orderable: false },
    { data: "applicant_name", orderable: false },
    { data: "section", orderable: false },
    { data: "application_type", orderable: false },
    { data: "primary_approved_leave", orderable: false },
    { data: "leave_spent", orderable: false },
    { data: "status", orderable: false },
    { data: "action", orderable: false, searchable: false }
];

function decorateRow(row) {
    var labels = ['ক্রমিক', 'আবেদনকারী', 'শাখা ও কেন্দ্র', 'আবেদনের প্রকার', 'প্রাথমিক অনুমোদিত', 'ভোগকৃত ছুটি', 'স্টেটাস', 'নথি কার্যক্রম'];
    var compact = [0, 3, 7, 8];
    $(row).find('td').each(function(i){
        var $td = $(this);
        $td.attr('data-label', labels[i] || '');
        if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
        if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
    });
}

function buildPayload(scope) {
    var prefix = '#' + scope;
    return {
        sectionFilter:     $(prefix + 'SectionFilter').val()     || '',
        employeeFilter:    $(prefix + 'EmployeeFilter').val()    || '',
        joiningTypeFilter: $(prefix + 'JoiningTypeFilter').val() || ''
    };
}

function updateActiveCount(scope) {
    var p = buildPayload(scope);
    var n = 0;
    if (p.sectionFilter)     n++;
    if (p.employeeFilter)    n++;
    if (p.joiningTypeFilter) n++;
    var $b = $('.filter-active-count[data-scope="' + scope + '"]');
    if (n > 0) $b.text(n).addClass('has-active');
    else       $b.text('').removeClass('has-active');
    var prefix = '#' + scope;
    [prefix + 'SectionFilter', prefix + 'EmployeeFilter', prefix + 'JoiningTypeFilter'].forEach(function(sel){
        var $el = $(sel);
        if (!$el.length) return;
        var hv = !!($el.val() && $el.val().length);
        $el.toggleClass('has-value', hv);
        $el.next('.select2-container').toggleClass('select2-active-value', hv);
    });
}

function initEmployeeSelect(scope) {
    $('#' + scope + 'EmployeeFilter').select2({
        placeholder: 'নাম বা আইডি দিয়ে খুঁজুন...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '../../api/leave/search-employees.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '', page: params.page || 1 }; },
            processResults: function(data, params) {
                params.page = params.page || 1;
                return { results: data.results, pagination: { more: !!(data.pagination && data.pagination.more) } };
            },
            cache: true
        },
        minimumInputLength: 0
    });
}

function initFilterControls(scope) {
    var prefix = '#' + scope;
    $(prefix + 'SectionFilter, ' + prefix + 'JoiningTypeFilter')
        .select2({ width: '100%', allowClear: true, placeholder: '— সকল —' });
    initEmployeeSelect(scope);
}

function reloadTable(scope) {
    if (scope === 'pendJ' && pendingLeavesTableInstance) pendingLeavesTableInstance.ajax.reload();
    if (scope === 'editJ' && editedLeavesTableInstance)  editedLeavesTableInstance.ajax.reload();
}

$(document).ready(function() {
    initFilterControls('pendJ');

    pendingLeavesTableInstance = $('#pendingLeavesTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: "../../api/leave/fetch-approved-leaves-pending.php",
            type: "POST",
            data: function(d) { Object.assign(d, buildPayload('pendJ')); }
        },
        columns: dtCols,
        createdRow: function(row) { decorateRow(row); },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: dtLang
    });

    // Filter handlers
    $(document).on('change', '.filter-input', function() {
        var scope = $(this).closest('[data-scope]').data('scope');
        updateActiveCount(scope);
        reloadTable(scope);
    });
    $(document).on('click', '.filter-reset', function(e){
        e.stopPropagation();
        var scope = $(this).data('scope');
        var prefix = '#' + scope;
        $(prefix + 'SectionFilter, ' + prefix + 'JoiningTypeFilter, ' + prefix + 'EmployeeFilter')
            .val(null).trigger('change.select2');
        updateActiveCount(scope);
        reloadTable(scope);
    });
    $(document).on('click', '.table-refresh', function(){
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
            $body.stop(true, true).slideUp(200, function(){ $panel.addClass('is-collapsed'); });
            $(btn).attr('aria-expanded', 'false');
        }
        $(btn).find('.filter-chevron').css('transform', nowCollapsed ? 'rotate(180deg)' : '');
    }
    $('.filter-panel.is-collapsed .filter-panel-body').hide();
    document.querySelectorAll('.filter-panel-toggle').forEach(function(btn){
        btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); _toggleFilterPanel(btn); });
    });

    // Edited DataTable (lazy)
    $('button[data-bs-target="#editedLeaves"]').on('shown.bs.tab', function () {
        if (!editedLeavesTableInstance) {
            initFilterControls('editJ');
            editedLeavesTableInstance = $('#editedLeavesTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: {
                    url: "../../api/leave/fetch-approved-leaves-edited.php",
                    type: "POST",
                    data: function(d) { Object.assign(d, buildPayload('editJ')); }
                },
                columns: dtCols,
                createdRow: function(row) { decorateRow(row); },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
                language: dtLang
            });
        }
    });

    // Stat-card → tab + active highlight
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
