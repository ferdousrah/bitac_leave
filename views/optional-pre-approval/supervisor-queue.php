<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

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

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-8">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-check me-2 text-primary"></i>ঐচ্ছিক ছুটি — সুপারিশ কিউ</h4>
        <div class="text-muted small mt-1">আপনার অধীনস্থ কর্মচারীদের ঐচ্ছিক ছুটি পূর্বানুমোদনের সুপারিশ</div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="opaSupQueueTable" class="table modern-leave-table align-middle" style="width:100%">
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

<div class="modal fade" id="opaSupDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-file-info me-2"></i>পূর্বানুমোদন বিবরণ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="opaSupDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
window.BITAC_OPA_SUP = {
    myEmpID: <?= (int)$myEmpID ?>,
    menuslug: '<?= htmlspecialchars($_GET['menuslug'] ?? 'optional-pre-approval-supervisor-queue') ?>'
};

(function bootOpaSupQueue() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || typeof Swal === 'undefined' || !jQuery.fn.DataTable) {
        return setTimeout(bootOpaSupQueue, 30);
    }
    var qTable = null;

    function initPage() {
        if ($.fn.DataTable.isDataTable('#opaSupQueueTable')) {
            $('#opaSupQueueTable').DataTable().destroy();
        }
        qTable = $('#opaSupQueueTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: 'lrtip',
            ajax: {
                url: '../../api/optional-pre-approval/fetch-supervisor-queue.php?menuslug=' + BITAC_OPA_SUP.menuslug,
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
                emptyTable: '<div class="text-center py-4 text-muted"><i class="ti tabler-inbox-off" style="font-size:2.5rem;color:#cbd5e1;"></i><div class="mt-2">সুপারিশের জন্য কোনো আবেদন নেই</div></div>',
                paginate: { first: 'প্রথম', previous: 'পূর্ববর্তী', next: 'পরবর্তী', last: 'শেষ' }
            }
        });

        $(document).off('click.opaSupAppr').on('click.opaSupAppr', '.btn-opa-approve', function() {
            actOnOpa($(this).data('pid'), 1);
        });
        $(document).off('click.opaSupRej').on('click.opaSupRej', '.btn-opa-reject', function() {
            actOnOpa($(this).data('pid'), 2);
        });
        $(document).off('click.opaSupView').on('click.opaSupView', '.btn-opa-view', function() {
            var pid = $(this).data('pid');
            $('#opaSupDetailBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
            $('#opaSupDetailModal').modal('show');
            $.get('../../api/optional-pre-approval/detail.php', { id: pid }, function(html) {
                $('#opaSupDetailBody').html(html);
            }).fail(function() { $('#opaSupDetailBody').html('<div class="alert alert-danger">লোড ব্যর্থ</div>'); });
        });
    }

    function actOnOpa(pid, action) {
        var isReject = (action === 2);
        var swalConfig = isReject
            ? {
                title: 'প্রত্যাখ্যানের কারণ',
                input: 'textarea',
                inputLabel: 'কারণ (আবশ্যক)',
                inputPlaceholder: 'কেন প্রত্যাখ্যান করছেন...',
                inputValidator: function(v) { if (!v || !v.trim()) return 'কারণ লিখতে হবে'; },
                showCancelButton: true,
                confirmButtonText: 'প্রত্যাখ্যান',
                cancelButtonText: 'বাতিল',
                customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
                buttonsStyling: false
            }
            : {
                title: 'সুপারিশ নিশ্চিত করুন',
                text: 'এই আবেদন সুপারিশ করবেন?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'হ্যাঁ, সুপারিশ',
                cancelButtonText: 'বাতিল',
                customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' },
                buttonsStyling: false
            };

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
