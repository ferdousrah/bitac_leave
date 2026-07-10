<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(__DIR__ . '/../../library/number_converter.php');

$viewerOrgID  = (int)($getUserInfoQRW['organization_id'] ?? 0);
$isSuperAdmin = empty($getUserInfoQRW['isCenterAdmin']) && $viewerOrgID === 0;

// Pending count (scoped to viewer's org)
$scopeSql = $viewerOrgID > 0 ? " AND el.organization_id = $viewerOrgID" : "";
$pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c
     FROM leave_addition_history lah
     INNER JOIN employee_list el ON lah.employeeID = el.id
     WHERE lah.isApproved = 0 $scopeSql"))['c'] ?? 0);

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
        <h4 class="fw-bold mb-0"><i class="ti tabler-circle-plus me-2 text-primary"></i>ছুটি সংযোজনের অনুমোদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card stat-pending"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="অনুমোদনের জন্য অপেক্ষমান ছুটি সংযোজনের সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-clipboard-list"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($pendingCount); ?></div>
                <div class="stat-label">অনুমোদনের অপেক্ষায়</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <!-- Filter Panel -->
        <div class="filter-panel mb-3 is-collapsed" data-scope="add">
            <div class="filter-panel-header">
                <button type="button" class="filter-panel-toggle" data-scope="add" aria-expanded="false" aria-controls="filterBody-add">
                    <i class="ti tabler-filter me-1"></i>
                    <span class="filter-panel-title">ফিল্টার</span>
                    <span class="filter-active-count" data-scope="add"></span>
                    <i class="ti tabler-chevron-down filter-chevron ms-2"></i>
                </button>
                <div class="filter-panel-actions">
                    <button type="button" class="btn btn-sm btn-icon btn-label-primary table-refresh" data-scope="add" title="টেবিল রিফ্রেশ" data-bs-toggle="tooltip">
                        <i class="ti tabler-refresh"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-label-secondary filter-reset" data-scope="add">
                        <i class="ti tabler-x me-1"></i>রিসেট
                    </button>
                </div>
            </div>
            <div class="filter-panel-body" id="filterBody-add">
                <div class="row g-2">
                    <?php if ($isSuperAdmin): ?>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                        <select id="addOrgFilter" class="form-select form-select-sm filter-input" data-scope="add">
                            <option value="">সকল কেন্দ্র</option>
                            <?= $orgOptions ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                        <select id="addSectionFilter" class="form-select form-select-sm filter-input" data-scope="add">
                            <option value="">সকল শাখা</option>
                            <?= $secOptions ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-user"></i>কর্মচারী</label>
                        <select id="addEmployeeFilter" class="form-select form-select-sm filter-input" data-scope="add">
                            <option value="">সকল</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="filter-label"><i class="ti tabler-clipboard-list"></i>ছুটির ধরণ</label>
                        <select id="addLeaveTypeFilter" class="form-select form-select-sm filter-input" data-scope="add">
                            <option value="">সকল ধরণ</option>
                            <?= $leaveTypeOptions ?>
                        </select>
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
                        <th>মন্তব্য</th>
                        <th>অফিস আদেশ</th>
                        <th class="text-center">কার্যাবলী</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
var addLeaveDT;

function buildAddPayload() {
    return {
        centerFilter:    $('#addOrgFilter').val()         || '',
        sectionFilter:   $('#addSectionFilter').val()     || '',
        employeeFilter:  $('#addEmployeeFilter').val()    || '',
        leaveTypeFilter: $('#addLeaveTypeFilter').val()   || ''
    };
}

function updateAddActiveCount() {
    var p = buildAddPayload();
    var n = 0;
    if (p.centerFilter)    n++;
    if (p.sectionFilter)   n++;
    if (p.employeeFilter)  n++;
    if (p.leaveTypeFilter) n++;
    var $b = $('.filter-active-count[data-scope="add"]');
    if (n > 0) $b.text(n).addClass('has-active');
    else       $b.text('').removeClass('has-active');
    ['#addOrgFilter', '#addSectionFilter', '#addEmployeeFilter', '#addLeaveTypeFilter'].forEach(function(sel){
        var $el = $(sel);
        if (!$el.length) return;
        var hv = !!($el.val() && $el.val().length);
        $el.toggleClass('has-value', hv);
        $el.next('.select2-container').toggleClass('select2-active-value', hv);
    });
}

function initAddEmployeeSelect() {
    $('#addEmployeeFilter').select2({
        placeholder: 'নাম বা আইডি দিয়ে খুঁজুন...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '../../api/leave/search-employees.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term || '', org: $('#addOrgFilter').val() || 0, page: params.page || 1 };
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
    $('#addOrgFilter, #addSectionFilter, #addLeaveTypeFilter').select2({ width: '100%', allowClear: true, placeholder: '— সকল —' });
    initAddEmployeeSelect();

    addLeaveDT = $('#leaveAdditionTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: "../../api/employees/fetch-regular-leave-addition-approval.php",
            type: "POST",
            data: function(d) { Object.assign(d, buildAddPayload()); }
        },
        columns: [
            { data: "serial",         orderable: false },
            { data: "employee_info",  orderable: true },
            { data: "leave_type",     orderable: true },
            { data: "leave_addition", orderable: true },
            { data: "note",           orderable: false },
            { data: "attachment",     orderable: false, searchable: false },
            { data: "action",         orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        createdRow: function(row) {
            var labels = ['ক্রমিক', 'কর্মচারী', 'ছুটির ধরণ', 'সংযোজন (দিন)', 'মন্তব্য', 'অফিস আদেশ', 'কার্যাবলী'];
            var compact = [0, 2, 3, 5, 6];
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
            emptyTable: '<div class="empty-state-rich"><i class="ti tabler-clipboard-off"></i><div class="empty-title">কোন সংযোজন নেই</div><div class="empty-subtitle">এই মুহূর্তে কোনো ছুটি সংযোজন অনুমোদনের অপেক্ষায় নেই</div></div>',
            paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
        },
        drawCallback: function() {
            $('[data-bs-toggle="tooltip"]').each(function(){
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) bootstrap.Tooltip.getOrCreateInstance(this);
            });
        }
    });

    // Filter handlers
    $(document).on('change', '.filter-input', function() {
        updateAddActiveCount();
        addLeaveDT.ajax.reload();
    });
    $(document).on('click', '.filter-reset', function(e){
        e.stopPropagation();
        $('#addOrgFilter, #addSectionFilter, #addEmployeeFilter, #addLeaveTypeFilter').val(null).trigger('change.select2');
        updateAddActiveCount();
        addLeaveDT.ajax.reload();
    });
    $(document).on('click', '.table-refresh', function(){
        var $i = $(this).find('i').addClass('ti-spin');
        addLeaveDT.ajax.reload();
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

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }
});

function approveLeaveAddition(batchKey, isApproved) {
    var isReject     = (isApproved == 2);
    var successText  = isReject ? 'প্রত্যাখ্যান করা হয়েছে' : 'অনুমোদন করা হয়েছে';
    var confirmColor = isReject ? '#dc3545' : '#1a7e44';
    var confirmClass = isReject ? 'btn btn-danger me-3' : 'btn btn-success me-3';
    var swalConfig;

    if (isReject) {
        // Reject path — reason is mandatory
        swalConfig = {
            title: 'প্রত্যাখ্যানের কারণ',
            input: 'textarea',
            inputLabel: 'কারণ (আবশ্যক)',
            inputPlaceholder: 'কেন প্রত্যাখ্যান করছেন তা লিখুন...',
            inputAttributes: { 'aria-label': 'rejection reason' },
            inputValidator: function (value) {
                if (!value || !value.trim()) return 'কারণ লিখতে হবে';
            },
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'প্রত্যাখ্যান করুন',
            cancelButtonText: 'বাতিল',
            customClass: { confirmButton: confirmClass, cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        };
    } else {
        swalConfig = {
            title: 'আপনি কি নিশ্চিত?',
            text: 'এই তথ্য অনুমোদন করতে চান?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, অনুমোদন',
            cancelButtonText: 'বাতিল',
            customClass: { confirmButton: confirmClass, cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        };
    }

    Swal.fire(swalConfig).then(function (result) {
        if (!result.isConfirmed) return;
        var payload = { batch_key: batchKey, isApproved: isApproved };
        if (isReject) payload.reason = result.value;

        $.ajax({
            type: 'POST',
            url: '../../api/employees/approve-regular-leave-addition-action.php',
            data: payload,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: 'সম্পন্ন', text: successText, icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(function () { addLeaveDT.ajax.reload(); });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি!', text: response.message || 'অপারেশন ব্যর্থ হয়েছে', icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: 'ত্রুটি!', text: 'সার্ভারে সমস্যা হয়েছে', icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
}
</script>
