<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Resolve viewer's organization
if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = (int)$_SESSION['centerAdminOrgID'];
} else {
    $empID = (int)($_SESSION['employeeID'] ?? 0);
    $r = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = $empID LIMIT 1");
    $orgID = (int)(mysqli_fetch_assoc($r)['organization_id'] ?? 0);
}
$isSuperAdmin = empty($_SESSION['isCenterAdmin']) && $orgID === 0;

// Stats by status
$scopeSql = $orgID > 0 ? " AND el.organization_id = $orgID" : "";
$statsRow = mysqli_fetch_assoc(mysqli_query($con,
    "SELECT
       COUNT(*) AS total,
       SUM(CASE WHEN lah.isApproved = 0 THEN 1 ELSE 0 END) AS pending,
       SUM(CASE WHEN lah.isApproved = 1 THEN 1 ELSE 0 END) AS approved,
       SUM(CASE WHEN lah.isApproved = 2 THEN 1 ELSE 0 END) AS rejected
     FROM leave_addition_history lah
     INNER JOIN employee_list el ON lah.employeeID = el.id
     WHERE 1=1 $scopeSql")) ?: ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];

// Filter dropdowns
$orgOptions = '';
if ($isSuperAdmin) {
    $orgsQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY organization_name ASC");
    while ($o = mysqli_fetch_assoc($orgsQ)) {
        $orgOptions .= '<option value="' . (int)$o['id'] . '">' . htmlspecialchars($o['organization_name']) . '</option>';
    }
}
$secQ = mysqli_query($con, "SELECT id, section_name FROM sections ORDER BY section_name ASC");
$secOptions = '';
while ($s = mysqli_fetch_assoc($secQ)) {
    $secOptions .= '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($s['section_name']) . '</option>';
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
        <h4 class="fw-bold mb-0"><i class="ti tabler-circle-plus me-2 text-primary"></i>ছুটি যোগ তথ্য</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-total stat-clickable" data-status-filter=""
             data-bs-toggle="tooltip" data-bs-placement="top" title="মোট সংযোজনের সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-files"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$statsRow['total']); ?></div>
                <div class="stat-label">মোট</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-pending stat-clickable" data-status-filter="0"
             data-bs-toggle="tooltip" data-bs-placement="top" title="অপেক্ষমান সংযোজন">
            <div class="stat-icon"><i class="ti tabler-hourglass"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$statsRow['pending']); ?></div>
                <div class="stat-label">অপেক্ষমান</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-approved stat-clickable" data-status-filter="1"
             data-bs-toggle="tooltip" data-bs-placement="top" title="অনুমোদিত সংযোজন">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$statsRow['approved']); ?></div>
                <div class="stat-label">অনুমোদিত</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-rejected stat-clickable" data-status-filter="2"
             data-bs-toggle="tooltip" data-bs-placement="top" title="বাতিল সংযোজন">
            <div class="stat-icon"><i class="ti tabler-circle-x"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$statsRow['rejected']); ?></div>
                <div class="stat-label">বাতিল</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <!-- Filter Panel -->
        <div class="filter-panel mb-3 is-collapsed" data-scope="addh">
            <div class="filter-panel-header">
                <button type="button" class="filter-panel-toggle" data-scope="addh" aria-expanded="false" aria-controls="filterBody-addh">
                    <i class="ti tabler-filter me-1"></i>
                    <span class="filter-panel-title">ফিল্টার</span>
                    <span class="filter-active-count" data-scope="addh"></span>
                    <i class="ti tabler-chevron-down filter-chevron ms-2"></i>
                </button>
                <div class="filter-panel-actions">
                    <button type="button" class="btn btn-sm btn-icon btn-label-primary table-refresh" data-scope="addh" title="টেবিল রিফ্রেশ" data-bs-toggle="tooltip">
                        <i class="ti tabler-refresh"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-label-secondary filter-reset" data-scope="addh">
                        <i class="ti tabler-x me-1"></i>রিসেট
                    </button>
                </div>
            </div>
            <div class="filter-panel-body" id="filterBody-addh">
                <div class="row g-2">
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                        <select id="addhOrgFilter" class="form-select form-select-sm filter-input" data-scope="addh">
                            <option value="">সকল কেন্দ্র</option>
                            <?= $orgOptions ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                        <select id="addhSectionFilter" class="form-select form-select-sm filter-input" data-scope="addh">
                            <option value="">সকল শাখা</option>
                            <?= $secOptions ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-user"></i>কর্মচারী</label>
                        <select id="addhEmployeeFilter" class="form-select form-select-sm filter-input" data-scope="addh">
                            <option value="">সকল</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-clipboard-list"></i>ছুটির ধরণ</label>
                        <select id="addhLeaveTypeFilter" class="form-select form-select-sm filter-input" data-scope="addh">
                            <option value="">সকল ধরণ</option>
                            <?= $leaveTypeOptions ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-circle-check"></i>স্টেটাস</label>
                        <select id="addhStatusFilter" class="form-select form-select-sm filter-input" data-scope="addh">
                            <option value="">সকল</option>
                            <option value="0">অপেক্ষমান</option>
                            <option value="1">অনুমোদিত</option>
                            <option value="2">বাতিল</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-9">
                        <label class="filter-label"><i class="ti tabler-calendar-event"></i>তারিখ Range</label>
                        <div class="field-shell">
                            <i class="ti tabler-calendar field-icon"></i>
                            <input type="text" id="addhDateRange" class="form-control form-control-sm filter-input filter-daterange" data-scope="addh" placeholder="তারিখ নির্বাচন...">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="leaveAdditionTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>ক্রমিক</th>
                        <th>কর্মচারী</th>
                        <th>ছুটির ধরণ</th>
                        <th>সংযোজন (দিন)</th>
                        <th>নোট</th>
                        <th>তারিখ</th>
                        <th class="text-center">সংযুক্তি</th>
                        <th class="text-center">স্টেটাস</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
var addhDT;

function buildAddhPayload() {
    var dr = $('#addhDateRange').val() || '';
    var dF = '', dT = '';
    if (dr.indexOf(' to ') !== -1) { var p = dr.split(' to '); dF = p[0].trim(); dT = p[1].trim(); }
    else if (dr) { dF = dr.trim(); dT = dr.trim(); }
    return {
        centerFilter:    $('#addhOrgFilter').val()        || '',
        sectionFilter:   $('#addhSectionFilter').val()    || '',
        employeeFilter:  $('#addhEmployeeFilter').val()   || '',
        leaveTypeFilter: $('#addhLeaveTypeFilter').val()  || '',
        statusFilter:    $('#addhStatusFilter').val()     || '',
        dateFrom:        dF,
        dateTo:          dT
    };
}

function updateAddhActiveCount() {
    var p = buildAddhPayload();
    var n = 0;
    if (p.centerFilter)    n++;
    if (p.sectionFilter)   n++;
    if (p.employeeFilter)  n++;
    if (p.leaveTypeFilter) n++;
    if (p.statusFilter)    n++;
    if (p.dateFrom || p.dateTo) n++;
    var $b = $('.filter-active-count[data-scope="addh"]');
    if (n > 0) $b.text(n).addClass('has-active');
    else       $b.text('').removeClass('has-active');
    ['#addhOrgFilter', '#addhSectionFilter', '#addhEmployeeFilter', '#addhLeaveTypeFilter', '#addhStatusFilter'].forEach(function(sel){
        var $el = $(sel);
        if (!$el.length) return;
        var hv = !!($el.val() && $el.val().length);
        $el.toggleClass('has-value', hv);
        $el.next('.select2-container').toggleClass('select2-active-value', hv);
    });
    $('#addhDateRange').toggleClass('has-value', !!$('#addhDateRange').val());
    var current = $('#addhStatusFilter').val() || '';
    $('.stat-clickable').removeClass('is-active');
    $('.stat-clickable[data-status-filter="' + current + '"]').addClass('is-active');
}

function initAddhEmployeeSelect() {
    $('#addhEmployeeFilter').select2({
        placeholder: 'নাম বা আইডি দিয়ে খুঁজুন...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '../../api/leave/search-employees.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term || '', org: $('#addhOrgFilter').val() || 0, page: params.page || 1 };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                return { results: data.results, pagination: { more: !!(data.pagination && data.pagination.more) } };
            },
            cache: true
        },
        minimumInputLength: 0
    });
}

$(document).ready(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var menuslug = urlParams.get('menuslug') || 'approved-leave-addition-data';

    $('#addhOrgFilter, #addhSectionFilter, #addhLeaveTypeFilter, #addhStatusFilter').select2({
        width: '100%', allowClear: true, placeholder: '— সকল —'
    });
    initAddhEmployeeSelect();
    if ($.fn.flatpickr) {
        $('#addhDateRange').flatpickr({
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y'
        });
    }

    addhDT = $('#leaveAdditionTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: "../../api/leave-addition/fetch-history.php?menuslug=" + menuslug,
            type: "POST",
            data: function(d) { Object.assign(d, buildAddhPayload()); }
        },
        columns: [
            { data: "sl",            orderable: false },
            { data: "employee_cell", orderable: true },
            { data: "leave_type",    orderable: true },
            { data: "addition",      orderable: true },
            { data: "note",          orderable: false },
            { data: "submit_date",   orderable: true },
            { data: "attachment",    orderable: false, searchable: false },
            { data: "status",        orderable: true }
        ],
        order: [[5, 'desc']],
        createdRow: function(row) {
            var labels = ['ক্রমিক', 'কর্মচারী', 'ছুটির ধরণ', 'সংযোজন (দিন)', 'নোট', 'তারিখ', 'সংযুক্তি', 'স্টেটাস'];
            var compact = [0, 2, 3, 5, 6, 7];
            $(row).find('td').each(function(i){
                var $td = $(this);
                $td.attr('data-label', labels[i] || '');
                if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
                if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
            });
        },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
            search: "",
            searchPlaceholder: "খুঁজুন...",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: '<div class="empty-state-rich"><i class="ti tabler-clipboard-off"></i><div class="empty-title">কোন তথ্য নেই</div><div class="empty-subtitle">এখনো কোনো ছুটি সংযোজনের তথ্য পাওয়া যায়নি</div></div>',
            paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
        }
    });

    $(document).on('change', '.filter-input', function() {
        updateAddhActiveCount();
        addhDT.ajax.reload();
    });
    $(document).on('click', '.filter-reset', function(e){
        e.stopPropagation();
        $('#addhOrgFilter, #addhSectionFilter, #addhEmployeeFilter, #addhLeaveTypeFilter, #addhStatusFilter')
            .val(null).trigger('change.select2');
        var dr = $('#addhDateRange');
        if (dr[0] && dr[0]._flatpickr) dr[0]._flatpickr.clear();
        updateAddhActiveCount();
        addhDT.ajax.reload();
    });
    $(document).on('click', '.table-refresh', function(){
        var $i = $(this).find('i').addClass('ti-spin');
        addhDT.ajax.reload();
        setTimeout(function(){ $i.removeClass('ti-spin'); }, 600);
    });

    $('.stat-clickable').on('click', function() {
        var status = $(this).data('status-filter');
        $('#addhStatusFilter').val(status === undefined ? '' : String(status)).trigger('change');
    });

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

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }
});
</script>
