<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Re-query full user (header overwrites $getUserInfoQRW with only 3 cols)
$_stmt = mysqli_prepare($con,
    "SELECT employee_id, user_group_id FROM user_list WHERE user_id = ?");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_stmt, 's', $_un);
mysqli_stmt_execute($_stmt);
$_full = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt)) ?: [];
mysqli_stmt_close($_stmt);

$actorEmpId   = (int)($_full['employee_id']   ?? 0);
$isSuperAdmin = ((int)($_full['user_group_id'] ?? 0) === 1);

// Build org gate: which centers can this actor approve for?
$allowedOrgIDs = [];
if ($actorEmpId > 0) {
    $sigQ = mysqli_query($con,
        "SELECT organization_id FROM leave_edit_approval_signatory WHERE employeeID = $actorEmpId");
    if ($sigQ) while ($r = mysqli_fetch_assoc($sigQ)) $allowedOrgIDs[] = (int)$r['organization_id'];
}

// Pending count — gated by signatory authorization
$pendingCount = 0;
$showNoAuth   = false;
if ($isSuperAdmin) {
    $pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(*) AS c FROM previous_leave_deduction WHERE isApproved = 0"))['c'] ?? 0);
} elseif (!empty($allowedOrgIDs)) {
    $orgList = implode(',', $allowedOrgIDs);
    $pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(*) AS c
         FROM previous_leave_deduction pld
         INNER JOIN employee_list el ON pld.employeeID = el.id
         WHERE pld.isApproved = 0 AND el.organization_id IN ($orgList)"))['c'] ?? 0);
} else {
    $showNoAuth = true;
}

// Filter dropdown options
$orgsQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY organization_name ASC");
$orgOptions = '';
while ($o = mysqli_fetch_assoc($orgsQ)) {
    $orgOptions .= '<option value="' . (int)$o['id'] . '">' . htmlspecialchars($o['organization_name']) . '</option>';
}
$secQ = mysqli_query($con, "SELECT id, section_name FROM sections ORDER BY section_name ASC");
$secOptions = '';
while ($s = mysqli_fetch_assoc($secQ)) {
    $secOptions .= '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($s['section_name']) . '</option>';
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-calendar-due me-2 text-primary"></i>পূর্ববর্তী ছুটির তথ্য অনুমোদন</h4>
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
             title="<?= $isSuperAdmin ? 'সকল কেন্দ্রের অপেক্ষমান' : 'আপনার সিগনেটরি দায়িত্বে থাকা অপেক্ষমান' ?> পূর্ববর্তী ছুটির তথ্যের সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-clipboard-list"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($pendingCount); ?></div>
                <div class="stat-label">অনুমোদনের অপেক্ষায়</div>
            </div>
        </div>
    </div>
</div>

<?php if ($showNoAuth): ?>
<div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
    <i class="ti tabler-alert-triangle me-2"></i>
    <div>
        আপনি কোনো কেন্দ্রের জন্য পূর্ববর্তী ছুটির অনুমোদনে নিযুক্ত সিগনেটরি নন।
        সিগনেটরি নির্ধারণের জন্য
        <a href="../../views/signatory/previous_leave_deduction_addition_certificate_main.php?menuslug=leave-settings" class="alert-link">সেটিংস</a>
        দেখুন।
    </div>
</div>
<?php endif; ?>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <!-- Filter Panel -->
        <div class="filter-panel mb-3 is-collapsed" data-scope="prev">
            <div class="filter-panel-header">
                <button type="button" class="filter-panel-toggle" data-scope="prev" aria-expanded="false" aria-controls="filterBody-prev">
                    <i class="ti tabler-filter me-1"></i>
                    <span class="filter-panel-title">ফিল্টার</span>
                    <span class="filter-active-count" data-scope="prev"></span>
                    <i class="ti tabler-chevron-down filter-chevron ms-2"></i>
                </button>
                <div class="filter-panel-actions">
                    <button type="button" class="btn btn-sm btn-icon btn-label-primary table-refresh" data-scope="prev" title="টেবিল রিফ্রেশ" data-bs-toggle="tooltip">
                        <i class="ti tabler-refresh"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-label-secondary filter-reset" data-scope="prev">
                        <i class="ti tabler-x me-1"></i>রিসেট
                    </button>
                </div>
            </div>
            <div class="filter-panel-body" id="filterBody-prev">
                <div class="row g-2">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                        <select id="prevOrgFilter" class="form-select form-select-sm filter-input" data-scope="prev">
                            <option value="">সকল কেন্দ্র</option>
                            <?= $orgOptions ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="filter-label"><i class="ti tabler-building"></i>শাখা</label>
                        <select id="prevSectionFilter" class="form-select form-select-sm filter-input" data-scope="prev">
                            <option value="">সকল শাখা</option>
                            <?= $secOptions ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="filter-label"><i class="ti tabler-user"></i>কর্মচারী</label>
                        <select id="prevEmployeeFilter" class="form-select form-select-sm filter-input" data-scope="prev">
                            <option value="">সকল</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="previousLeaveTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>ক্রমিক</th>
                        <th>কর্মচারী</th>
                        <th>গড়-বেতনে ছুটি</th>
                        <th>অর্ধ-গড় বেতনে</th>
                        <th>নৈমিত্তিক</th>
                        <th>অসাধারণ</th>
                        <th>কর্তনহীন</th>
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
var prevLeaveDT;

function buildPrevPayload() {
    return {
        centerFilter:   $('#prevOrgFilter').val()      || '',
        sectionFilter:  $('#prevSectionFilter').val()  || '',
        employeeFilter: $('#prevEmployeeFilter').val() || ''
    };
}

function updatePrevActiveCount() {
    var p = buildPrevPayload();
    var n = 0;
    if (p.centerFilter)   n++;
    if (p.sectionFilter)  n++;
    if (p.employeeFilter) n++;
    var $b = $('.filter-active-count[data-scope="prev"]');
    if (n > 0) $b.text(n).addClass('has-active');
    else       $b.text('').removeClass('has-active');
    ['#prevOrgFilter', '#prevSectionFilter', '#prevEmployeeFilter'].forEach(function(sel){
        var $el = $(sel);
        var hv = !!($el.val() && $el.val().length);
        $el.toggleClass('has-value', hv);
        $el.next('.select2-container').toggleClass('select2-active-value', hv);
    });
}

function initPrevEmployeeSelect() {
    $('#prevEmployeeFilter').select2({
        placeholder: 'নাম বা আইডি দিয়ে খুঁজুন...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '../../api/leave/search-employees.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term || '', org: $('#prevOrgFilter').val() || 0, page: params.page || 1 };
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

jQuery(function($) {
    // Init Select2
    $('#prevOrgFilter, #prevSectionFilter').select2({ width: '100%', allowClear: true, placeholder: '— সকল —' });
    initPrevEmployeeSelect();

    // DataTable
    prevLeaveDT = $('#previousLeaveTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: "../../api/employees/fetch-previous-leave-approval.php",
            type: "POST",
            data: function(d) { Object.assign(d, buildPrevPayload()); }
        },
        columns: [
            { data: "serial",            orderable: false },
            { data: "employee_info",     orderable: false },
            { data: "avg_salary",        orderable: false },
            { data: "half_avg_salary",   orderable: false },
            { data: "casual",            orderable: false },
            { data: "leave_without_pay", orderable: false },
            { data: "undeductible",      orderable: false },
            { data: "action",            orderable: false, searchable: false }
        ],
        createdRow: function(row) {
            var labels = ['ক্রমিক', 'কর্মচারী', 'গড়-বেতনে', 'অর্ধ-গড়', 'নৈমিত্তিক', 'অসাধারণ', 'কর্তনহীন', 'কার্যাবলী'];
            var compact = [0, 7];
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
            emptyTable: '<div class="empty-state-rich"><i class="ti tabler-clipboard-off"></i><div class="empty-title">কোন তথ্য নেই</div><div class="empty-subtitle">এই মুহূর্তে কোনো পূর্ববর্তী ছুটির তথ্য অনুমোদনের অপেক্ষায় নেই</div></div>',
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
        updatePrevActiveCount();
        prevLeaveDT.ajax.reload();
    });
    $(document).on('click', '.filter-reset', function(e){
        e.stopPropagation();
        $('#prevOrgFilter, #prevSectionFilter, #prevEmployeeFilter').val(null).trigger('change.select2');
        updatePrevActiveCount();
        prevLeaveDT.ajax.reload();
    });
    $(document).on('click', '.table-refresh', function(){
        var $i = $(this).find('i').addClass('ti-spin');
        prevLeaveDT.ajax.reload();
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

    // Tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }
});

function approveLeaveInfo(dataID, isApproved) {
    var successText = isApproved == 1 ? 'অনুমোদন করা হয়েছে' : 'প্রত্যাখ্যান করা হয়েছে';

    var firePromise;
    if (isApproved == 2) {
        firePromise = Swal.fire({
            title: 'প্রত্যাখ্যান করুন',
            input: 'textarea',
            inputLabel: 'প্রত্যাখ্যানের কারণ',
            inputPlaceholder: 'কেন প্রত্যাখ্যান করছেন বিস্তারিত লিখুন...',
            inputAttributes: { rows: 4 },
            showCancelButton: true,
            confirmButtonText: 'প্রত্যাখ্যান',
            cancelButtonText: 'বাতিল',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#8592a3',
            customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false,
            inputValidator: function(v) { if (!v || !v.trim()) return 'কারণ আবশ্যক'; }
        });
    } else {
        firePromise = Swal.fire({
            title: 'অনুমোদন নিশ্চিত করুন?',
            text: "এই তথ্য অনুমোদন করতে চান?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1a7e44',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, অনুমোদন',
            cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        });
    }

    firePromise.then(function(result) {
        if (!result.isConfirmed) return;
        var payload = { dataID: dataID, isApproved: isApproved };
        if (isApproved == 2) payload.reason = result.value || '';

        $.ajax({
            type: 'POST',
            url: '../../api/employees/approve-leave-info-action.php',
            data: payload,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: 'সম্পন্ন', text: successText, icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(function(){ prevLeaveDT.ajax.reload(); });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি!', text: response.message || 'অপারেশন ব্যর্থ হয়েছে', icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
            },
            error: function() {
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
