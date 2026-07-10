<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Resolve current actor's employee ID (signatory lookup key)
$actorStmt = mysqli_prepare($con,
    "SELECT employee_id FROM user_list WHERE user_id = ? LIMIT 1");
$un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);
$myEmpID = (int)($actor['employee_id'] ?? 0);
?>

<style>
.emp-cell { display:flex; align-items:center; gap:.55rem; }
.emp-avatar { width:34px; height:34px; border-radius:50%; background:#e0e7ff; color:#3730a3; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.85rem; overflow:hidden; }
.emp-avatar img { width:100%; height:100%; object-fit:cover; }
.opa-year-pill { background:#eef2ff; color:#4338ca; padding:.2rem .55rem; border-radius:.4rem; font-size:.75rem; font-weight:500; }
.action-group { display:inline-flex; gap:.35rem; }
.action-icon { width:32px; height:32px; border-radius:.4rem; display:inline-flex; align-items:center; justify-content:center; border:0; }
.icon-approve { background:#dcfce7; color:#166534; }
.icon-approve:hover { background:#166534; color:#fff; }
.icon-reject  { background:#fee2e2; color:#991b1b; }
.icon-reject:hover  { background:#991b1b; color:#fff; }
</style>

<!-- Header -->
<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-8">
        <h4 class="fw-bold mb-0"><i class="ti tabler-inbox me-2 text-primary"></i>ঐচ্ছিক ছুটি পূর্বানুমোদন — অনুমোদন কিউ</h4>
        <div class="text-muted small mt-1">আপনার অনুমোদনের অপেক্ষায় থাকা পূর্বানুমোদন আবেদনসমূহ</div>
    </div>
    <div class="col-12 col-md-4 text-md-end">
        <a href="manage.php?menuslug=<?= htmlspecialchars($_GET['menuslug'] ?? 'optional-pre-approval-queue') ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-list me-1"></i>সকল আবেদন
        </a>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="opaQueueTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:60px;">ক্রমিক</th>
                        <th>কর্মচারী</th>
                        <th>বছর</th>
                        <th class="text-center">দিন</th>
                        <th>উৎসব নোট</th>
                        <th>সাবমিট তারিখ</th>
                        <th class="text-center">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail modal -->
<div class="modal fade" id="opaDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-file-info me-2"></i>পূর্বানুমোদন বিবরণ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="opaDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
window.BITAC_OPA_Q = {
    myEmpID: <?= (int)$myEmpID ?>,
    menuslug: '<?= htmlspecialchars($_GET['menuslug'] ?? 'optional-pre-approval-queue') ?>'
};

(function bootOpaQueue() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || typeof Swal === 'undefined' || !jQuery.fn.DataTable) {
        return setTimeout(bootOpaQueue, 30);
    }
    var qTable = null;

    function initPage() {
        if ($.fn.DataTable.isDataTable('#opaQueueTable')) {
            $('#opaQueueTable').DataTable().destroy();
        }
        qTable = $('#opaQueueTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: 'lrtip',
            ajax: {
                url: '../../api/optional-pre-approval/fetch-queue.php?menuslug=' + BITAC_OPA_Q.menuslug,
                type: 'POST'
            },
            columns: [
                { data: 'sl',         orderable: false },
                { data: 'employee',   orderable: false },
                { data: 'year',       orderable: false },
                { data: 'days',       orderable: false, className: 'text-center' },
                { data: 'notes',      orderable: false },
                { data: 'submit_date',orderable: false },
                { data: 'action',     orderable: false, className: 'text-center' }
            ],
            language: {
                processing: '<div class="spinner-border text-primary"></div>',
                lengthMenu: 'প্রদর্শন করুন _MENU_ টি',
                info: 'প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি',
                infoEmpty: 'কোনো এন্ট্রি নেই',
                zeroRecords: 'কোনো মিল নেই',
                emptyTable: '<div class="text-center py-4 text-muted"><i class="ti tabler-inbox-off" style="font-size:2.5rem;color:#cbd5e1;"></i><div class="mt-2">আপনার কিউ ফাঁকা</div></div>',
                paginate: { first: 'প্রথম', previous: 'পূর্ববর্তী', next: 'পরবর্তী', last: 'শেষ' }
            }
        });

        $(document).off('click.opaAppr').on('click.opaAppr', '.btn-opa-approve', function() {
            var pid = $(this).data('pid');
            actOnOpa(pid, 1);
        });
        $(document).off('click.opaRej').on('click.opaRej', '.btn-opa-reject', function() {
            var pid = $(this).data('pid');
            actOnOpa(pid, 2);
        });
        $(document).off('click.opaView').on('click.opaView', '.btn-opa-view', function() {
            var pid = $(this).data('pid');
            $('#opaDetailBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
            $('#opaDetailModal').modal('show');
            $.get('../../api/optional-pre-approval/detail.php', { id: pid }, function(html) {
                $('#opaDetailBody').html(html);
            }).fail(function() { $('#opaDetailBody').html('<div class="alert alert-danger">লোড ব্যর্থ</div>'); });
        });
    }

    function actOnOpa(pid, action) {
        var isReject = (action === 2);
        var swalConfig;
        if (isReject) {
            swalConfig = {
                title: 'প্রত্যাখ্যানের কারণ',
                input: 'textarea',
                inputLabel: 'কারণ (আবশ্যক)',
                inputPlaceholder: 'কেন প্রত্যাখ্যান করছেন...',
                inputValidator: function(v) { if (!v || !v.trim()) return 'কারণ লিখতে হবে'; },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#8592a3',
                confirmButtonText: 'প্রত্যাখ্যান',
                cancelButtonText: 'বাতিল',
                customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
                buttonsStyling: false
            };
        } else {
            swalConfig = {
                title: 'অনুমোদন নিশ্চিত করুন',
                text: 'এই পূর্বানুমোদন আবেদন অনুমোদন করবেন?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#8592a3',
                confirmButtonText: 'হ্যাঁ, অনুমোদন',
                cancelButtonText: 'বাতিল',
                customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' },
                buttonsStyling: false
            };
        }
        Swal.fire(swalConfig).then(function(r) {
            if (!r.isConfirmed) return;
            var payload = { id: pid, action: action };
            if (isReject) payload.reason = r.value;
            $.ajax({
                type: 'POST',
                url: '../../api/optional-pre-approval/act.php',
                data: payload,
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({ title: 'সফল', text: resp.message, icon: 'success', confirmButtonColor: '#6c5ce7', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        qTable.ajax.reload(null, false);
                    } else {
                        Swal.fire({ title: 'ত্রুটি', text: (resp && resp.message) || 'ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                    }
                },
                error: function() {
                    Swal.fire({ title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                }
            });
        });
    }

    $(document).ready(initPage);
    document.addEventListener('turbo:load', initPage);
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
