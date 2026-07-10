<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Employee self-service page — resolve logged-in user's own employee record.
$_meStmt = mysqli_prepare($con,
    "SELECT ul.employee_id, el.id AS emp_id, el.employee_name, el.employee_id AS emp_code,
            el.employment_status, el.pending_section_assignment,
            el.organization_id, el.section_id,
            jt.job_title_name, o.organization_name, s.section_name
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     LEFT JOIN job_title jt ON el.designation = jt.id
     LEFT JOIN organization o ON el.organization_id = o.id
     LEFT JOIN sections s ON el.section_id = s.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_meStmt, 's', $_un);
mysqli_stmt_execute($_meStmt);
$_me = mysqli_fetch_assoc(mysqli_stmt_get_result($_meStmt)) ?: [];
mysqli_stmt_close($_meStmt);
$_myEmpID = (int)($_me['emp_id'] ?? 0);
$_hasEmpRecord = ($_myEmpID > 0);
$_canApply = $_hasEmpRecord
             && (int)($_me['employment_status'] ?? 0) === 1
             && (int)($_me['pending_section_assignment'] ?? 0) === 0;

$currentYear = (int)date('Y');

// Preload employees of the applicant's org for the supervisor Select2 (mirrors
// views/leave/application-form.php's pattern). Employees marked active in the
// applicant's organization only. Preselect the applicant's assigned supervisor
// (employee_list.supervisor) if any.
$_orgID = (int)($_me['organization_id'] ?? 0);
$_supervisors = [];
$_defaultSupervisorID = 0;
if ($_myEmpID > 0 && $_orgID > 0) {
    // Default supervisor from employee_list.supervisor
    $_defQ = mysqli_prepare($con, "SELECT supervisor FROM employee_list WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($_defQ, 'i', $_myEmpID);
    mysqli_stmt_execute($_defQ);
    $_defRow = mysqli_fetch_assoc(mysqli_stmt_get_result($_defQ)) ?: [];
    mysqli_stmt_close($_defQ);
    $_defaultSupervisorID = (int)($_defRow['supervisor'] ?? 0);

    $_supQ = mysqli_prepare($con,
        "SELECT el.id, el.employee_id, el.employee_name, jt.job_title_name
         FROM employee_list el
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE el.employment_status = 1 AND el.organization_id = ? AND el.id <> ?
         ORDER BY el.display_order ASC, el.employee_name ASC");
    mysqli_stmt_bind_param($_supQ, 'ii', $_orgID, $_myEmpID);
    mysqli_stmt_execute($_supQ);
    $_supRes = mysqli_stmt_get_result($_supQ);
    while ($_r = mysqli_fetch_assoc($_supRes)) { $_supervisors[] = $_r; }
    mysqli_stmt_close($_supQ);
}
?>

<style>
.opa-year-pill { background:#eef2ff; color:#4338ca; padding:.2rem .55rem; border-radius:.4rem; font-size:.75rem; font-weight:500; }
.opa-status-pending  { background:#fef3c7; color:#92400e; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.opa-status-approved { background:#dcfce7; color:#166534; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.opa-status-rejected { background:#fee2e2; color:#991b1b; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.emp-cell { display:flex; align-items:center; gap:.55rem; }
.emp-avatar { width:34px; height:34px; border-radius:50%; background:#e0e7ff; color:#3730a3; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.85rem; overflow:hidden; }
.emp-avatar img { width:100%; height:100%; object-fit:cover; }
</style>

<!-- Header -->
<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-calendar-check me-2 text-primary"></i>ঐচ্ছিক ছুটি — পূর্বানুমোদন</h4>
        <div class="text-muted small mt-1">
            বার্ষিক ঐচ্ছিক ছুটির পূর্বানুমোদনের আবেদন — অনুমোদিত হলে আপনার balance-এ যোগ হবে
        </div>
    </div>
    <div class="col-12 col-md-5 text-md-end">
        <?php if ($_canApply): ?>
            <button type="button" class="btn btn-primary" id="btnNewPreApproval">
                <i class="ti tabler-plus me-1"></i>নতুন আবেদন
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$_hasEmpRecord): ?>
    <div class="alert alert-warning">
        <i class="ti tabler-alert-triangle me-1"></i>
        আপনার user account-এর সাথে কোনো কর্মচারী রেকর্ড লিংক করা নেই। অনুগ্রহ করে administrator-কে জানান।
    </div>
<?php elseif (!$_canApply): ?>
    <div class="alert alert-warning">
        <i class="ti tabler-alert-triangle me-1"></i>
        এই মুহূর্তে আবেদন করা যাবে না — আপনার employment status active নয় অথবা section বরাদ্দ অপেক্ষমান।
    </div>
<?php endif; ?>

<!-- Filter row -->
<div class="card shadow-sm border-0 mb-3" style="border-radius:.65rem;">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">বছর</label>
                <select id="filterYear" class="form-select form-select-sm">
                    <?php for ($y = $currentYear + 1; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= banglaNumber($y) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">অবস্থা</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">সব</option>
                    <option value="0">অপেক্ষমান</option>
                    <option value="1">অনুমোদিত</option>
                    <option value="2">প্রত্যাখ্যাত</option>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted mb-1">খুঁজুন</label>
                <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="কর্মচারীর নাম বা আইডি...">
            </div>
            <div class="col-12 col-md-3">
                <button type="button" id="btnResetFilters" class="btn btn-sm btn-label-secondary w-100">
                    <i class="ti tabler-x me-1"></i>রিসেট
                </button>
            </div>
        </div>
    </div>
</div>

<!-- List -->
<div class="card shadow-sm border-0">
    <div class="card-body p-3">
        <h6 class="fw-semibold mb-3"><i class="ti tabler-history me-1"></i>আপনার পূর্ববর্তী আবেদনসমূহ</h6>
        <div class="table-responsive">
            <table id="preApprovalTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:60px;">ক্রমিক</th>
                        <th>বছর</th>
                        <th class="text-center">দিন</th>
                        <th>উৎসবের নোট</th>
                        <th>সাবমিট তারিখ</th>
                        <th>অবস্থা</th>
                        <th class="text-center">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($_canApply): ?>
<!-- New Pre-Approval Modal -->
<div class="modal fade" id="newPreApprovalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="newPreApprovalForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti tabler-calendar-check me-2 text-primary"></i>নতুন পূর্বানুমোদন আবেদন</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info d-flex align-items-center py-2">
                        <i class="ti tabler-info-circle me-2"></i>
                        <div><small>ঐচ্ছিক ছুটি নেওয়ার আগে পূর্বানুমোদন আবশ্যক। অনুমোদন পেলে দিনগুলো আপনার balance-এ যোগ হবে। বছরে সর্বোচ্চ <b>৩ দিন</b>।</small></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">বছর <span class="text-danger">*</span></label>
                            <select id="modalYear" name="year" class="form-select" required>
                                <?php for ($y = $currentYear + 1; $y >= $currentYear; $y--): ?>
                                    <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= banglaNumber($y) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">অনুরোধকৃত দিন <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" min="0.5" max="3" id="modalDays" name="requested_days" class="form-control" value="3" required>
                            <small class="text-muted">সর্বোচ্চ ৩ দিন (BSR)</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">উৎসব <span class="text-muted small">(এক বা একাধিক)</span></label>
                            <select id="modalNotes" name="festival_notes[]" multiple="multiple" class="festival-select" style="width:100%;">
                                <optgroup label="ইসলামিক">
                                    <option value="শবে বরাত">শবে বরাত</option>
                                    <option value="শবে কদর">শবে কদর</option>
                                    <option value="ঈদুল ফিতর (২য় দিন)">ঈদুল ফিতর (২য় দিন)</option>
                                    <option value="ঈদুল ফিতর (৩য় দিন)">ঈদুল ফিতর (৩য় দিন)</option>
                                    <option value="ঈদুল আজহা (২য় দিন)">ঈদুল আজহা (২য় দিন)</option>
                                    <option value="ঈদুল আজহা (৩য় দিন)">ঈদুল আজহা (৩য় দিন)</option>
                                    <option value="মহররম (আশুরা)">মহররম (আশুরা)</option>
                                    <option value="ঈদে মিলাদুন্নবী">ঈদে মিলাদুন্নবী</option>
                                </optgroup>
                                <optgroup label="হিন্দু">
                                    <option value="দুর্গা পূজা (নবমী)">দুর্গা পূজা (নবমী)</option>
                                    <option value="দুর্গা পূজা (মহাদশমী)">দুর্গা পূজা (মহাদশমী)</option>
                                    <option value="জন্মাষ্টমী">জন্মাষ্টমী</option>
                                    <option value="সরস্বতী পূজা">সরস্বতী পূজা</option>
                                    <option value="লক্ষ্মী পূজা">লক্ষ্মী পূজা</option>
                                    <option value="কালী পূজা">কালী পূজা</option>
                                    <option value="দোলযাত্রা (হোলি)">দোলযাত্রা (হোলি)</option>
                                    <option value="মহাশিবরাত্রি">মহাশিবরাত্রি</option>
                                </optgroup>
                                <optgroup label="বৌদ্ধ">
                                    <option value="বৌদ্ধ পূর্ণিমা">বৌদ্ধ পূর্ণিমা</option>
                                    <option value="মাধু পূর্ণিমা">মাধু পূর্ণিমা</option>
                                    <option value="প্রবারণা পূর্ণিমা">প্রবারণা পূর্ণিমা</option>
                                </optgroup>
                                <optgroup label="খ্রিস্টান">
                                    <option value="বড়দিন (Christmas)">বড়দিন (Christmas)</option>
                                    <option value="ইস্টার সানডে">ইস্টার সানডে</option>
                                    <option value="গুড ফ্রাইডে">গুড ফ্রাইডে</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">সুপারভাইজার / ঊর্ধ্বতন কর্মকর্তা <span class="text-danger">*</span></label>
                            <select id="modalSupervisor" name="supervisorID" class="supervisor-select" required style="width:100%;">
                                <option value="">-- সুপারভাইজার নির্বাচন করুন --</option>
                                <?php foreach ($_supervisors as $sv): ?>
                                    <option value="<?= (int)$sv['id'] ?>" <?= ((int)$sv['id'] === $_defaultSupervisorID ? 'selected' : '') ?>>
                                        <?= htmlspecialchars((string)$sv['employee_id']) ?> - <?= htmlspecialchars($sv['employee_name']) ?><?= !empty($sv['job_title_name']) ? ', ' . htmlspecialchars($sv['job_title_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">সংযুক্তি (ঐচ্ছিক, PDF/JPG/PNG, ২ MB)</label>
                            <input type="file" id="modalAttachment" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="button" class="btn btn-primary" id="btnSubmitPreApproval">
                        <i class="ti tabler-send me-1"></i>জমা দিন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Detail modal -->
<div class="modal fade" id="preApprovalDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-file-info me-2"></i>পূর্বানুমোদন বিবরণ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="preApprovalDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
window.BITAC_OPA = {
    canWrite: true,
    menuslug: '<?= htmlspecialchars($_GET['menuslug'] ?? 'optional-pre-approval') ?>'
};

(function bootOpaPage() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || typeof Swal === 'undefined' || !jQuery.fn.DataTable || !jQuery.fn.select2) {
        return setTimeout(bootOpaPage, 30);
    }
    var opaTable = null;

    function initPage() {
        if ($.fn.DataTable.isDataTable('#preApprovalTable')) {
            $('#preApprovalTable').DataTable().destroy();
        }

        opaTable = $('#preApprovalTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: 'lrtip',
            ajax: {
                url: '../../api/optional-pre-approval/list.php?menuslug=' + BITAC_OPA.menuslug,
                type: 'POST',
                data: function(d) {
                    d.year   = $('#filterYear').val() || '';
                    d.status = $('#filterStatus').val() || '';
                    d.search = d.search || {};
                    d.search.value = $('#filterSearch').val() || '';
                }
            },
            columns: [
                { data: 'sl', orderable: false },
                { data: 'year', orderable: false },
                { data: 'days', orderable: false, className: 'text-center' },
                { data: 'notes', orderable: false },
                { data: 'submit_date', orderable: false },
                { data: 'status', orderable: false },
                { data: 'action', orderable: false, className: 'text-center' }
            ],
            order: [],
            language: {
                processing: '<div class="spinner-border text-primary"></div>',
                lengthMenu: 'প্রদর্শন করুন _MENU_ টি এন্ট্রি',
                info: 'প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি',
                infoEmpty: 'কোনো এন্ট্রি নেই',
                infoFiltered: '(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)',
                zeroRecords: 'কোনো মিল খুঁজে পাওয়া যায়নি',
                emptyTable: '<div class="text-center py-4 text-muted"><i class="ti tabler-calendar-off" style="font-size:2.5rem;color:#cbd5e1;"></i><div class="mt-2">এখনো কোনো পূর্বানুমোদন আবেদন নেই</div></div>',
                paginate: { first: 'প্রথম', previous: 'পূর্ববর্তী', next: 'পরবর্তী', last: 'শেষ' }
            }
        });

        var _t;
        $('#filterSearch').off('input.opa').on('input.opa', function() {
            clearTimeout(_t); _t = setTimeout(function() { opaTable.ajax.reload(); }, 250);
        });
        $('#filterYear, #filterStatus').off('change.opa').on('change.opa', function() {
            opaTable.ajax.reload();
        });
        $('#btnResetFilters').off('click.opa').on('click.opa', function() {
            $('#filterYear').val('<?= $currentYear ?>');
            $('#filterStatus').val('');
            $('#filterSearch').val('');
            opaTable.ajax.reload();
        });

        // Init festival + supervisor selects on modal SHOW event only — destroy
        // prior instances first. Doing it here (not at page init) guarantees a
        // single widget even if initPage() fires multiple times.
        $('#newPreApprovalModal').off('show.bs.modal.opa').on('show.bs.modal.opa', function() {
            var $fest = $('.festival-select');
            if ($fest.hasClass('select2-hidden-accessible')) {
                try { $fest.select2('destroy'); } catch(e) {}
            }
            $fest.select2({
                theme: 'bootstrap-5',
                placeholder: 'উৎসব নির্বাচন করুন...',
                closeOnSelect: false,
                tags: true,
                dropdownParent: $('#newPreApprovalModal'),
                width: '100%'
            });

            var $sup = $('.supervisor-select');
            if ($sup.hasClass('select2-hidden-accessible')) {
                try { $sup.select2('destroy'); } catch(e) {}
            }
            $sup.select2({
                theme: 'bootstrap-5',
                placeholder: '-- সুপারভাইজার নির্বাচন করুন --',
                allowClear: true,
                dropdownParent: $('#newPreApprovalModal'),
                width: '100%'
            });
        });

        // Destroy on hide so next open starts clean
        $('#newPreApprovalModal').off('hidden.bs.modal.opa').on('hidden.bs.modal.opa', function() {
            var $fest = $('.festival-select');
            if ($fest.hasClass('select2-hidden-accessible')) {
                try { $fest.select2('destroy'); } catch(e) {}
            }
            var $sup = $('.supervisor-select');
            if ($sup.hasClass('select2-hidden-accessible')) {
                try { $sup.select2('destroy'); } catch(e) {}
            }
        });

        $('#btnNewPreApproval').off('click.opa').on('click.opa', function() {
            var f = document.getElementById('newPreApprovalForm');
            if (f) f.reset();
            $('#modalYear').val('<?= $currentYear ?>');
            $('#modalDays').val(3);
            $('#newPreApprovalModal').modal('show');
        });

        $('#btnSubmitPreApproval').off('click.opa').on('click.opa', function() {
            var formEl = document.getElementById('newPreApprovalForm');
            if (formEl && !formEl.checkValidity()) { formEl.reportValidity(); return; }
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>প্রক্রিয়াকরণ...');
            $.ajax({
                type: 'POST',
                url: '../../api/optional-pre-approval/submit.php',
                data: new FormData(formEl),
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.status === 1) {
                        $('#newPreApprovalModal').modal('hide');
                        Swal.fire({ title: 'সফল', text: resp.message || 'পূর্বানুমোদন আবেদন জমা হয়েছে', icon: 'success', confirmButtonColor: '#6c5ce7', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        opaTable.ajax.reload(null, false);
                    } else {
                        Swal.fire({ title: 'ত্রুটি', text: (resp && resp.message) || 'ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                },
                complete: function() { $btn.prop('disabled', false).html('<i class="ti tabler-send me-1"></i>জমা দিন'); }
            });
        });

        $(document).off('click.opaDetail').on('click.opaDetail', '.btn-opa-detail', function() {
            var pid = $(this).data('pid');
            $('#preApprovalDetailBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
            $('#preApprovalDetailModal').modal('show');
            $.get('../../api/optional-pre-approval/detail.php', { id: pid }, function(html) {
                $('#preApprovalDetailBody').html(html);
            }).fail(function() { $('#preApprovalDetailBody').html('<div class="alert alert-danger">ডেটা লোড ব্যর্থ</div>'); });
        });
    }

    $(document).ready(initPage);
    document.addEventListener('turbo:load', initPage);
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
