<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Unlike the transfer order itself — which only HQ may issue — assigning a
// section is the receiving centre's job, so any centre may work its own list.
// fetch-pending-section.php applies the same scope to the rows it returns.
$_actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actor = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);

$_isSuperAdmin  = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID    = (int)($_actor['emp_org'] ?? 0);
$_seeAllCenters = ($_isSuperAdmin || $_myCenterID === 4);
$_statOrgWhere  = $_seeAllCenters ? '' : ' AND organization_id = ' . $_myCenterID;

$pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM employee_list
     WHERE employment_status = 1 AND pending_section_assignment = 1 $_statOrgWhere"))['c'] ?? 0);
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-building-community me-2 text-primary"></i>সেকশন বরাদ্দ</h4>
        <div class="text-muted small mt-1">বদলি হয়ে আসা কর্মচারীদের শাখা ও যোগদানের তারিখ নির্ধারণ</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-3 mt-md-0">
        <a href="manage.php?menuslug=employee-transfer" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-transfer me-1"></i>বদলি ব্যবস্থাপনা
        </a>
    </div>
</div>

<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card <?= $pendingCount > 0 ? 'stat-pending' : 'stat-success' ?>"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="<?= $_seeAllCenters ? 'সকল কেন্দ্রের' : 'আপনার কেন্দ্রের' ?> সেকশন বরাদ্দের অপেক্ষায় থাকা কর্মচারী">
            <div class="stat-icon"><i class="ti tabler-transfer-in"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($pendingCount) ?></div>
                <div class="stat-label">বরাদ্দের অপেক্ষায়</div>
            </div>
        </div>
    </div>
</div>

<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
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
                        <input type="text" id="asg_joining_date" name="actual_joining_date" class="form-control" required placeholder="YYYY-MM-DD">
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

<script type="text/javascript">
(function bootSectionAssign() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable ||
        typeof Swal === 'undefined' || typeof bootstrap === 'undefined') {
        return setTimeout(bootSectionAssign, 20);
    }
    var $ = jQuery;
    if ($('#pendingSectionTable').data('bound')) return;
    $('#pendingSectionTable').data('bound', true);

    var dtLang = {
        processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
        search: "",
        searchPlaceholder: "নাম, পদবি দিয়ে খুঁজুন...",
        lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
        info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
        infoEmpty: "কোন এন্ট্রি নেই",
        emptyTable: "সেকশন বরাদ্দের অপেক্ষায় কোনো কর্মচারী নেই",
        zeroRecords: "কিছু পাওয়া যায়নি",
        paginate: { first: "প্রথম", last: "শেষ", next: "পরবর্তী", previous: "পূর্ববর্তী" }
    };

    function decoratePendingRow(row) {
        var labels = ['ক্রমিক', 'কর্মচারী', 'পূর্বের কেন্দ্র', 'কার্যকর তারিখ', 'আদেশ নং', 'সংযুক্তি', 'কার্যক্রম'];
        var compact = [0, 5, 6];
        $(row).find('td').each(function (i) {
            var $td = $(this);
            $td.attr('data-label', labels[i] || '');
            if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
            if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
        });
    }

    var pendingSectionTable = $('#pendingSectionTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: { url: "../../api/employees/fetch-pending-section.php", type: "POST" },
        columns: [
            { data: "sl",            orderable: false, searchable: false },
            { data: "employee_cell", orderable: false },
            { data: "from_center",   orderable: false },
            { data: "transfer_date", orderable: false },
            { data: "order_number",  orderable: false },
            { data: "attachment",    orderable: false, searchable: false, className: 'text-center' },
            { data: "action",        orderable: false, searchable: false, className: 'text-center' }
        ],
        createdRow: function (row) { decoratePendingRow(row); },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: dtLang
    });

    // Delegated — DataTables rebuilds the rows on every draw.
    $(document).on('click', '.btn-assign-section', function () {
        var $btn = $(this);
        var trDate = $btn.data('transfer-date') || '';

        $('#asg_emp_id').val($btn.data('emp'));
        $('#asg_emp_name').text($btn.data('name') || '');
        var code = $btn.data('code') || '';
        $('#asg_emp_id_disp').text(code ? '(' + code + ')' : '');
        $('#asg_from_center').text($btn.data('from') || '—');
        $('#asg_section_id').html('<option value="">— লোড হচ্ছে —</option>');
        $('#asg_joining_date').val(trDate);

        // Guarded on the global: modern flatpickr is not a jQuery plugin, and
        // testing $.fn.flatpickr hands the field to jQuery UI's datepicker.
        if (typeof flatpickr !== 'undefined') {
            flatpickr('#asg_joining_date', { dateFormat: 'Y-m-d', allowInput: true, defaultDate: trDate, static: true });
        }

        $.get('../../api/transfer/sections-by-center.php', { org_id: $btn.data('org') }, function (resp) {
            if (resp && resp.status === 1 && resp.items && resp.items.length) {
                var opts = '<option value="">— নির্বাচন করুন —</option>';
                resp.items.forEach(function (s) {
                    opts += '<option value="' + s.id + '">' + $('<div>').text(s.name).html() + '</option>';
                });
                $('#asg_section_id').html(opts);
            } else {
                $('#asg_section_id').html('<option value="">— সেকশন নেই —</option>');
            }
        }, 'json');

        $('#assignSectionModal').modal('show');
    });

    // Bound to the button, not the form's submit: Turbo intercepts form submits
    // and turns this into a native GET.
    $(document).on('click', '#btnSubmitAssign', function (e) {
        e.preventDefault();
        var $form = $('#assignSectionForm');
        if ($form[0] && typeof $form[0].checkValidity === 'function' && !$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>প্রক্রিয়াকরণ...');
        $.ajax({
            type: 'POST',
            url: '../../api/transfer/assign-section.php',
            data: $form.serialize(),
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.status === 1) {
                    $('#assignSectionModal').modal('hide');
                    Swal.fire({
                        title: 'সফল', text: resp.message || 'সেকশন বরাদ্দ সম্পন্ন', icon: 'success',
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    }).then(function () {
                        pendingSectionTable.ajax.reload(null, false);
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি', text: (resp && resp.message) || 'বরাদ্দ ব্যর্থ', icon: 'error',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ', icon: 'error',
                    customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                });
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="ti tabler-check me-1"></i>বরাদ্দ চূড়ান্ত করুন');
            }
        });
    });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
