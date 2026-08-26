<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Same gate the save endpoint applies: Super Admin, or an HQ-based user.
// A transfer is HQ-driven; the receiving centre cannot initiate one.
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

$_isSuperAdmin = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID   = (int)($_actor['emp_org'] ?? 0);
$_canWrite     = ($_isSuperAdmin || $_myCenterID === 4);

if (!$_canWrite) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-triangle me-2"></i>'
       . 'বদলির আদেশ দেওয়ার অনুমতি নেই — এটি শুধুমাত্র প্রধান কার্যালয় ও সুপার অ্যাডমিনের জন্য।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$centersRes = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted = 0 ORDER BY organization_name ASC");
$allCenters = mysqli_fetch_all($centersRes, MYSQLI_ASSOC);

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'employee-transfer-new');
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-transfer me-2 text-primary"></i>নতুন বদলির আদেশ</h4>
        <div class="text-muted small mt-1">কর্মচারীকে এক কেন্দ্র থেকে অন্য কেন্দ্রে বদলি করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-3 mt-md-0">
        <a href="manage.php?menuslug=employee-transfer" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>বদলি ব্যবস্থাপনা
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form id="newTransferForm" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-12 col-lg-8">
                    <label class="form-label">কর্মচারী <span class="text-danger">*</span></label>
                    <select id="tr_employee" name="dataID" class="form-select" required></select>
                    <small class="text-muted">নাম বা আইডি দিয়ে খুঁজুন</small>
                </div>

                <div class="col-12 col-lg-4">
                    <div id="empCurrentBox" class="alert alert-light border d-none mb-0 py-2 h-100 d-flex align-items-center">
                        <div>
                            <small class="text-muted d-block">বর্তমান কেন্দ্র</small>
                            <span id="empCurrentCenter" class="fw-semibold">—</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">নতুন কেন্দ্র <span class="text-danger">*</span></label>
                    <select id="tr_to_organization_id" name="to_organization_id" class="form-select" required>
                        <option value="">— নির্বাচন করুন —</option>
                        <?php foreach ($allCenters as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['organization_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">কার্যকর তারিখ <span class="text-danger">*</span></label>
                    <input type="text" id="tr_transfer_date" name="transfer_date" class="form-control" required placeholder="YYYY-MM-DD">
                    <small class="text-muted">যে তারিখ থেকে বদলি কার্যকর</small>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">আদেশ নম্বর</label>
                    <input type="text" id="tr_order_number" name="order_number" class="form-control" placeholder="অফিস আদেশের নম্বর">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">আদেশ তারিখ</label>
                    <input type="text" id="tr_order_date" name="order_date" class="form-control" placeholder="YYYY-MM-DD">
                </div>

                <div class="col-12">
                    <label class="form-label">কারণ / মন্তব্য</label>
                    <textarea id="tr_reason" name="reason" class="form-control" rows="2" placeholder="বদলির কারণ"></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">আদেশের কপি <span class="text-danger">*</span></label>
                    <input type="file" id="tr_attachment" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                    <small class="text-muted">JPG / PNG / PDF, সর্বোচ্চ ২ MB — আদেশের কপি ছাড়া বদলি সংরক্ষণ করা যাবে না</small>
                </div>
            </div>

            <div class="row mt-4 pt-3 border-top">
                <div class="col-12 text-end">
                    <a href="manage.php?menuslug=employee-transfer" class="btn btn-label-secondary me-2" data-turbo="true">
                        <i class="ti tabler-x me-1"></i>বাতিল
                    </a>
                    <button type="button" class="btn btn-primary" id="btnSubmitTransfer">
                        <i class="ti tabler-check me-1"></i>বদলি সংরক্ষণ
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
(function bootNewTransfer() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2 ||
        typeof Swal === 'undefined') {
        return setTimeout(bootNewTransfer, 20);
    }
    var $ = jQuery;
    if ($('#newTransferForm').data('bound')) return;
    $('#newTransferForm').data('bound', true);

    // flatpickr is not a jQuery plugin, so guard on the global — guarding on
    // $.fn.flatpickr silently hands the fields to jQuery UI's datepicker.
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#tr_transfer_date', { dateFormat: 'Y-m-d', allowInput: true });
        flatpickr('#tr_order_date',    { dateFormat: 'Y-m-d', allowInput: true });
    }

    var $emp = $('#tr_employee');
    if ($emp.hasClass('select2-hidden-accessible')) {
        try { $emp.select2('destroy'); } catch (e) {}
    }
    $emp.select2({
        placeholder: 'নাম বা আইডি দিয়ে খুঁজুন...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '../../api/transfer/employee-search.php',
            dataType: 'json',
            delay: 250,
            data: function (p) { return { q: p.term || '' }; },
            processResults: function (data) {
                return { results: (data.items || []).map(function (it) {
                    return {
                        id: it.id,
                        text: it.label,
                        current_org_id: it.current_org_id,
                        current_org_name: it.current_org_name
                    };
                }) };
            }
        }
    });

    // Transferring someone to the centre they are already in is a no-op, so
    // that option is disabled once the employee is known.
    $emp.on('select2:select', function (e) {
        var d = e.params.data;
        $('#empCurrentBox').removeClass('d-none');
        $('#empCurrentCenter').text(d.current_org_name || '—');
        $('#tr_to_organization_id option').prop('disabled', false);
        $('#tr_to_organization_id option[value="' + d.current_org_id + '"]').prop('disabled', true);
        if ($('#tr_to_organization_id').val() == d.current_org_id) {
            $('#tr_to_organization_id').val('').trigger('change');
        }
    });

    $emp.on('select2:clear', function () {
        $('#empCurrentBox').addClass('d-none');
        $('#tr_to_organization_id option').prop('disabled', false);
    });

    // Bound to the button, not the form's submit: Turbo intercepts form submits.
    $('#btnSubmitTransfer').on('click', function (e) {
        e.preventDefault();
        var formEl = document.getElementById('newTransferForm');
        if (formEl && typeof formEl.checkValidity === 'function' && !formEl.checkValidity()) {
            formEl.reportValidity();
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>সংরক্ষণ হচ্ছে...');
        $.ajax({
            type: 'POST',
            url: '../../api/employees/transfer.php',
            data: new FormData(formEl),
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.status === 1) {
                    Swal.fire({
                        title: 'সফল', text: resp.message || 'বদলি সংরক্ষণ হয়েছে', icon: 'success',
                        confirmButtonText: 'বদলি ব্যবস্থাপনায় যান',
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    }).then(function () {
                        window.location = 'manage.php?menuslug=employee-transfer';
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি', text: (resp && resp.message) || 'বদলি সংরক্ষণ ব্যর্থ', icon: 'error',
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
                $btn.prop('disabled', false).html('<i class="ti tabler-check me-1"></i>বদলি সংরক্ষণ');
            }
        });
    });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
