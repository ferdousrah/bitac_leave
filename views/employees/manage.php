<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Detect actor — Super Admin sees all centers, others see only own center
$_actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, ul.employee_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actor = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);
$_isSuperAdmin = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID   = (int)($_actor['emp_org'] ?? 0);
$_isHQ         = ($_myCenterID === 4); // HQ users with this page access also see all centers
$_seeAllCenters = ($_isSuperAdmin || $_isHQ);

// Org-gate for stats + dropdowns
$_statOrgWhere = $_seeAllCenters ? '' : ' AND organization_id = ' . $_myCenterID;

// Stats — active vs inactive vs pending-section employees (filtered for non-Super-Admin)
$activeCount   = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM employee_list WHERE employment_status = 1 AND pending_section_assignment = 0 $_statOrgWhere"))['c'] ?? 0);
$inactiveCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM employee_list WHERE employment_status != 1 $_statOrgWhere"))['c'] ?? 0);
$pendingCount  = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM employee_list WHERE employment_status = 1 AND pending_section_assignment = 1 $_statOrgWhere"))['c'] ?? 0);

// Fetch organizations for filter dropdown (Super Admin / HQ user: all; others: only own center)
$organizations = [];
if ($_seeAllCenters) {
    $orgQ = mysqli_query($con, "SELECT * FROM organization WHERE deleted = 0 ORDER BY display_order ASC");
} else {
    $orgQ = mysqli_query($con, "SELECT * FROM organization WHERE deleted = 0 AND id = $_myCenterID ORDER BY display_order ASC");
}
while ($org = mysqli_fetch_assoc($orgQ)) $organizations[] = $org;

// Fetch sections for filter dropdown
$sections = [];
$secQ = mysqli_query($con, "SELECT id, section_name FROM sections ORDER BY section_name ASC");
while ($s = mysqli_fetch_assoc($secQ)) $sections[] = $s;
?>
<!-- Expose actor's org gate to the AJAX layer; non-Super-Admin / non-HQ requests force-filter to own center -->
<script>
window.BITAC_EMP_GATE = {
    seeAllCenters: <?= $_seeAllCenters ? 'true' : 'false' ?>,
    myCenterID:    <?= (int)$_myCenterID ?>
};
</script>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-users me-2 text-primary"></i>কর্মকর্তা/কর্মচারীদের তালিকা</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="new.php?menuslug=manage-employee" class="btn btn-primary" data-turbo="true">
            <i class="ti tabler-plus me-1"></i>নতুন সংযোজন
        </a>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="<?= $_seeAllCenters ? 'col-12 col-md-6' : 'col-12' ?>">
        <div class="stat-card stat-approved stat-clickable"
             data-tab-target="#active_employees"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="চাকরিরত কর্মকর্তা/কর্মচারীর সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-users"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($activeCount); ?></div>
                <div class="stat-label">চাকরিরত</div>
            </div>
        </div>
    </div>
    <?php if ($_seeAllCenters): // অবসরপ্রাপ্তদের তালিকা কেবল হেড অফিস থেকে ?>
    <div class="col-12 col-md-6">
        <div class="stat-card stat-rejected stat-clickable"
             data-tab-target="#inactive_employees"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="অবসরপ্রাপ্ত / চাকরিরত নন">
            <div class="stat-icon"><i class="ti tabler-user-off"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($inactiveCount); ?></div>
                <div class="stat-label">অবসরপ্রাপ্ত / চাকরিরত নন</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-0">
        <ul class="nav custom-leave-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" role="tab"
                        data-bs-toggle="tab" data-bs-target="#active_employees">
                    <i class="ti tabler-users me-2"></i>
                    <span class="d-none d-sm-inline">চাকরিরত</span>
                    <span class="badge ms-2"><?php echo banglaNumber($activeCount); ?></span>
                </button>
            </li>
            <?php if ($_seeAllCenters): ?>
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab"
                        data-bs-toggle="tab" data-bs-target="#inactive_employees">
                    <i class="ti tabler-user-off me-2"></i>
                    <span class="d-none d-sm-inline">অবসরপ্রাপ্ত</span>
                    <span class="badge ms-2"><?php echo banglaNumber($inactiveCount); ?></span>
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <button type="button" class="nav-link <?= $pendingCount > 0 ? 'has-pending' : '' ?>" role="tab"
                        data-bs-toggle="tab" data-bs-target="#pending_section_employees">
                    <i class="ti tabler-transfer-in me-2"></i>
                    <span class="d-none d-sm-inline">নতুন বদলি</span>
                    <span class="badge ms-2 <?= $pendingCount > 0 ? 'bg-warning' : '' ?>"><?php echo banglaNumber($pendingCount); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3">
            <!-- Active Employees Tab -->
            <div class="tab-pane fade show active" id="active_employees" role="tabpanel">
                <?php $scope = 'act'; require __DIR__ . '/manage-filter.inc.php'; ?>
                <div class="table-responsive">
                    <table id="activeEmployeeTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:80px;">ক্রমিক</th>
                                <th>কর্মচারী</th>
                                <th>কেন্দ্র</th>
                                <th>শাখা</th>
                                <th class="text-center" style="width:170px;">কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Inactive Employees Tab -->
            <?php if ($_seeAllCenters): ?>
            <div class="tab-pane fade" id="inactive_employees" role="tabpanel">
                <?php $scope = 'inact'; require __DIR__ . '/manage-filter.inc.php'; ?>
                <div class="table-responsive">
                    <table id="inactiveEmployeeTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:80px;">ক্রমিক</th>
                                <th>কর্মচারী</th>
                                <th>কেন্দ্র</th>
                                <th>শাখা</th>
                                <th class="text-center" style="width:170px;">কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pending Section Assignment (Incoming Transfers) Tab -->
            <div class="tab-pane fade" id="pending_section_employees" role="tabpanel">
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <i class="ti tabler-info-circle me-2"></i>
                    <div>
                        <strong>সেকশন বরাদ্দ অপেক্ষমান:</strong>
                        এই কর্মচারীরা প্রধান কার্যালয়ের আদেশে এই কেন্দ্রে বদলি হয়েছেন। সেকশন বরাদ্দ না হওয়া পর্যন্ত তাঁরা ছুটি/অনুমোদন কার্যক্রমে অংশগ্রহণ করতে পারবেন না।
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="pendingSectionTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:80px;">ক্রমিক</th>
                                <th>কর্মচারী</th>
                                <th>পূর্বের কেন্দ্র</th>
                                <th>কার্যকর তারিখ</th>
                                <th>আদেশ নং</th>
                                <th class="text-center" style="width:100px;">সংযুক্তি</th>
                                <th class="text-center" style="width:200px;">কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section Assignment Modal -->
<div class="modal fade" id="assignSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="assignSectionForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti tabler-building me-2 text-primary"></i>সেকশন বরাদ্দ করুন</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="asg_emp_id" name="dataID">
                    <div class="alert alert-light border mb-3 py-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong id="asg_emp_name">—</strong>
                                <div class="small text-muted" id="asg_emp_id_disp"></div>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">পূর্বের কেন্দ্র</div>
                                <div class="fw-semibold" id="asg_from_center">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">সেকশন <span class="text-danger">*</span></label>
                        <select id="asg_section_id" name="section_id" class="form-select" required>
                            <option value="">— লোড হচ্ছে —</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">প্রকৃত যোগদান তারিখ <span class="text-danger">*</span></label>
                        <input type="text" id="asg_joining_date" name="actual_joining_date" class="form-control flatpickr-input" required placeholder="YYYY-MM-DD">
                        <small class="text-muted">কর্মচারী কোন তারিখে এই কেন্দ্রে যোগদান করেছেন</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="button" class="btn btn-primary" id="btnSubmitAssign"><i class="ti tabler-check me-1"></i>বরাদ্দ চূড়ান্ত করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
var dtLang = {
    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
    search: "",
    searchPlaceholder: "নাম, পদবি দিয়ে খুঁজুন...",
    lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
    info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
    infoEmpty: "কোন এন্ট্রি নেই",
    infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
    zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
    emptyTable: '<div class="empty-state-rich"><i class="ti tabler-users-off"></i><div class="empty-title">কোন কর্মচারী নেই</div><div class="empty-subtitle">এই বিভাগে কোনো কর্মচারীর রেকর্ড পাওয়া যায়নি</div></div>',
    paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
};

var dtCols = [
    { data: "sl",                orderable: false, searchable: false },
    { data: "employee_cell",     orderable: false },
    { data: "organization_name", orderable: false },
    { data: "section_name",      orderable: false },
    { data: "action",            orderable: false, searchable: false }
];

function decorateRow(row) {
    var labels = ['ক্রমিক', 'কর্মচারী', 'কেন্দ্র', 'শাখা', 'কার্যক্রম'];
    var compact = [0, 4];
    $(row).find('td').each(function(i){
        var $td = $(this);
        $td.attr('data-label', labels[i] || '');
        if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
        if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
    });
}

var activeTable, inactiveTable, pendingSectionTable;

var pendingDtCols = [
    { data: "sl",              orderable: false, searchable: false },
    { data: "employee_cell",   orderable: false },
    { data: "from_center",     orderable: false },
    { data: "transfer_date",   orderable: false },
    { data: "order_number",    orderable: false },
    { data: "attachment",      orderable: false, searchable: false, className: 'text-center' },
    { data: "action",          orderable: false, searchable: false, className: 'text-center' }
];

function decoratePendingRow(row) {
    var labels = ['ক্রমিক', 'কর্মচারী', 'পূর্বের কেন্দ্র', 'কার্যকর তারিখ', 'আদেশ নং', 'সংযুক্তি', 'কার্যক্রম'];
    var compact = [0, 5, 6];
    $(row).find('td').each(function(i){
        var $td = $(this);
        $td.attr('data-label', labels[i] || '');
        if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
        if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
    });
}

function buildPayload(scope) {
    var prefix = '#' + scope;
    var gate = window.BITAC_EMP_GATE || { seeAllCenters: false, myCenterID: 0 };
    // Restricted users: pin center_id to their own org; privileged: respect dropdown
    var pickedCenter = $(prefix + 'CenterFilter').val() || '';
    var defaultCenter = gate.seeAllCenters ? 0 : gate.myCenterID;
    return {
        center_id: pickedCenter !== '' && gate.seeAllCenters ? pickedCenter : defaultCenter,
        // Column searches map to API column-search slots; the API uses the raw fields:
        // employee_name (col 2), employee_id (col 3), organization_name_raw (col 4), section_name_raw (col 5)
        'columns[2][search][value]': $(prefix + 'NameSearch').val()    || '',
        'columns[3][search][value]': $(prefix + 'IdSearch').val()      || '',
        'columns[4][search][value]': gate.seeAllCenters ? (pickedCenter || '') : '',
        'columns[5][search][value]': $(prefix + 'SectionSearch').val() || ''
    };
}

function updateActiveCount(scope) {
    var prefix = '#' + scope;
    var n = 0;
    if ($(prefix + 'NameSearch').val())    n++;
    if ($(prefix + 'IdSearch').val())      n++;
    if ($(prefix + 'CenterFilter').val())  n++;
    if ($(prefix + 'SectionSearch').val()) n++;
    var $b = $('.filter-active-count[data-scope="' + scope + '"]');
    if (n > 0) $b.text(n).addClass('has-active');
    else       $b.text('').removeClass('has-active');
    [prefix + 'NameSearch', prefix + 'IdSearch', prefix + 'CenterFilter', prefix + 'SectionSearch'].forEach(function(sel) {
        var $el = $(sel);
        if (!$el.length) return;
        var hv = !!($el.val() && $el.val().length);
        $el.toggleClass('has-value', hv);
        var $s2 = $el.next('.select2-container');
        if ($s2.length) $s2.toggleClass('select2-active-value', hv);
    });
}

function reloadTable(scope) {
    if (scope === 'act' && activeTable) activeTable.ajax.reload();
    if (scope === 'inact' && inactiveTable) inactiveTable.ajax.reload();
}

function initFilterControls(scope) {
    var prefix = '#' + scope;
    $(prefix + 'CenterFilter').select2({ width: '100%', allowClear: true, placeholder: '— সকল কেন্দ্র —' });
    $(prefix + 'SectionSearch').select2({ width: '100%', allowClear: true, placeholder: '— সকল শাখা —' });
}

$(document).ready(function() {
    initFilterControls('act');

    activeTable = $('#activeEmployeeTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: "../../api/employees/fetch-all-active.php",
            type: "POST",
            data: function(d) { Object.assign(d, buildPayload('act')); }
        },
        columns: dtCols,
        createdRow: function(row) { decorateRow(row); },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: dtLang
    });

    // Filter handlers
    $(document).on('input change', '.filter-input', function() {
        var scope = $(this).closest('[data-scope]').data('scope');
        updateActiveCount(scope);
        // Debounce text inputs
        clearTimeout(window['_emp_dbn_' + scope]);
        window['_emp_dbn_' + scope] = setTimeout(function() { reloadTable(scope); }, 250);
    });

    $(document).on('click', '.filter-reset', function(e){
        e.stopPropagation();
        var scope = $(this).data('scope');
        var prefix = '#' + scope;
        $(prefix + 'NameSearch, ' + prefix + 'IdSearch').val('');
        $(prefix + 'CenterFilter, ' + prefix + 'SectionSearch').val(null).trigger('change.select2');
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

    // Inactive tab — lazy init
    $('button[data-bs-target="#inactive_employees"]').on('shown.bs.tab', function() {
        if (!inactiveTable) {
            initFilterControls('inact');
            inactiveTable = $('#inactiveEmployeeTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: {
                    url: "../../api/employees/fetch-inactive.php",
                    type: "POST",
                    data: function(d) { Object.assign(d, buildPayload('inact')); }
                },
                columns: dtCols,
                createdRow: function(row) { decorateRow(row); },
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
                language: dtLang
            });
        }
    });

    // Pending Section tab — lazy init
    $('button[data-bs-target="#pending_section_employees"]').on('shown.bs.tab', function() {
        if (!pendingSectionTable) {
            pendingSectionTable = $('#pendingSectionTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: { url: "../../api/employees/fetch-pending-section.php", type: "POST" },
                columns: pendingDtCols,
                createdRow: function(row) { decoratePendingRow(row); },
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
                language: dtLang
            });
        }
    });

    // "Section বরাদ্দ" button → open modal (delegated for DataTable re-draws)
    $(document).on('click', '.btn-assign-section', function() {
        var $btn = $(this);
        var empId    = $btn.data('emp');
        var empName  = $btn.data('name') || '';
        var empCode  = $btn.data('code') || '';
        var fromCtr  = $btn.data('from') || '—';
        var trDate   = $btn.data('transfer-date') || '';
        var orgId    = $btn.data('org');

        $('#asg_emp_id').val(empId);
        $('#asg_emp_name').text(empName);
        $('#asg_emp_id_disp').text(empCode ? '(' + empCode + ')' : '');
        $('#asg_from_center').text(fromCtr);
        $('#asg_section_id').html('<option value="">— লোড হচ্ছে —</option>');
        $('#asg_joining_date').val(trDate);

        if (typeof flatpickr !== 'undefined') {
            flatpickr('#asg_joining_date', { dateFormat: 'Y-m-d', allowInput: true, defaultDate: trDate, static: true });
        }

        // Load sections for employee's current center
        $.get('../../api/transfer/sections-by-center.php', { org_id: orgId }, function(resp) {
            if (resp && resp.status === 1 && resp.items && resp.items.length) {
                var opts = '<option value="">— নির্বাচন করুন —</option>';
                resp.items.forEach(function(s) {
                    opts += '<option value="' + s.id + '">' + $('<div>').text(s.name).html() + '</option>';
                });
                $('#asg_section_id').html(opts);
            } else {
                $('#asg_section_id').html('<option value="">— সেকশন নেই —</option>');
            }
        }, 'json');

        $('#assignSectionModal').modal('show');
    });

    // Section assignment submit — bound to button click (not form submit) to bypass
    // Turbo's form interception that was causing the form to submit natively (GET to URL).
    $(document).on('click', '#btnSubmitAssign', function(e) {
        e.preventDefault();
        var $form = $('#assignSectionForm');
        // Native validity check (preserves required-field UX)
        if ($form[0] && typeof $form[0].checkValidity === 'function' && !$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }
        var $btn = $('#btnSubmitAssign');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>প্রক্রিয়াকরণ...');
        $.ajax({
            type: 'POST',
            url: '../../api/transfer/assign-section.php',
            data: $form.serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.status === 1) {
                    $('#assignSectionModal').modal('hide');
                    Swal.fire({ title: 'সফল', text: resp.message || 'সেকশন বরাদ্দ সম্পন্ন', icon: 'success', confirmButtonColor: '#6c5ce7', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false }).then(function() {
                        // Refresh both pending and active tables (employee moves from pending → active)
                        if (pendingSectionTable) pendingSectionTable.ajax.reload(null, false);
                        if (activeTable) activeTable.ajax.reload(null, false);
                    });
                } else {
                    Swal.fire({ title: 'ত্রুটি', text: (resp && resp.message) || 'বরাদ্দ ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                }
            },
            error: function() {
                Swal.fire({ title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="ti tabler-check me-1"></i>বরাদ্দ চূড়ান্ত করুন');
            }
        });
    });

    // Stat card → switch tab + active highlight
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

function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই কর্মচারী মুছে ফেলতে চান? এটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না।",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন',
        cancelButtonText: 'বাতিল',
        customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            type: 'post',
            url: '../../api/employees/delete.php',
            data: 'dataID=' + dataID + '&tableName=students',
            success: function() {
                Swal.fire({
                    title: 'মুছে ফেলা হয়েছে',
                    text: 'কর্মচারী সফলভাবে মুছে ফেলা হয়েছে',
                    icon: 'success',
                    confirmButtonColor: '#6c5ce7',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                }).then(function() {
                    if (activeTable) activeTable.ajax.reload();
                    if (inactiveTable) inactiveTable.ajax.reload();
                });
            },
            error: function() {
                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'মুছে ফেলতে ব্যর্থ হয়েছে',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
}
</script>
