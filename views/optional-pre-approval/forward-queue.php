<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$actorStmt = mysqli_prepare($con,
    "SELECT employee_id, isCenterAdmin, user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);
$myEmpID       = (int)($actor['employee_id']    ?? 0);
$isCenterAdmin = (int)($actor['isCenterAdmin']  ?? 0);
$myGroupID     = (int)($actor['user_group_id']  ?? 0);

if (!$isCenterAdmin && $myGroupID > 0) {
    $_permStmt = mysqli_prepare($con,
        "SELECT 1 FROM group_access_permission gap
         INNER JOIN submodules sm ON gap.submodule_id = sm.dataID
         WHERE gap.user_group_id = ? AND sm.slug = 'optional-pre-approval-forward-queue'
         LIMIT 1");
    mysqli_stmt_bind_param($_permStmt, 'i', $myGroupID);
    mysqli_stmt_execute($_permStmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($_permStmt))) { $isCenterAdmin = 1; }
    mysqli_stmt_close($_permStmt);
}

// Count summaries for the stats strip
$myOrgID = 0;
if ($myEmpID > 0) {
    $_orgQ = mysqli_prepare($con, "SELECT organization_id FROM employee_list WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($_orgQ, 'i', $myEmpID);
    mysqli_stmt_execute($_orgQ);
    $_orgRow = mysqli_fetch_assoc(mysqli_stmt_get_result($_orgQ)) ?: [];
    mysqli_stmt_close($_orgQ);
    $myOrgID = (int)($_orgRow['organization_id'] ?? 0);
}

$pendingCount = 0;
$forwardedCount = 0;
if ($isCenterAdmin && $myOrgID > 0) {
    $pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con, "
        SELECT COUNT(*) AS c
        FROM optional_leave_pre_approval opa
        WHERE opa.status = 0 AND opa.organization_id = $myOrgID
          AND EXISTS (SELECT 1 FROM optional_leave_pre_approval_signatory sSup
                      WHERE sSup.preApprovalID = opa.id AND sSup.isSupervisor = 1 AND sSup.isApproved = 1)
          AND NOT EXISTS (SELECT 1 FROM optional_leave_pre_approval_signatory sC
                          WHERE sC.preApprovalID = opa.id AND sC.isSupervisor = 0 AND sC.isSentbyAdmin = 1)
    "))['c'] ?? 0);
    $forwardedCount = (int)(mysqli_fetch_assoc(mysqli_query($con, "
        SELECT COUNT(*) AS c
        FROM optional_leave_pre_approval opa
        WHERE opa.organization_id = $myOrgID
          AND EXISTS (SELECT 1 FROM optional_leave_pre_approval_signatory sC
                      WHERE sC.preApprovalID = opa.id AND sC.isSupervisor = 0 AND sC.isSentbyAdmin = 1)
    "))['c'] ?? 0);
}
?>

<style>
.emp-cell { display:flex; align-items:center; gap:.55rem; }
.emp-avatar { width:34px; height:34px; border-radius:50%; background:#e0e7ff; color:#3730a3; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.85rem; overflow:hidden; }
.emp-avatar img { width:100%; height:100%; object-fit:cover; }
.opa-year-pill { background:#eef2ff; color:#4338ca; padding:.2rem .55rem; border-radius:.4rem; font-size:.75rem; font-weight:500; }
.action-group { display:inline-flex; gap:.35rem; }
.action-icon { width:32px; height:32px; border-radius:.4rem; display:inline-flex; align-items:center; justify-content:center; border:0; text-decoration:none; }
.icon-forward { background:#eef2ff; color:#4338ca; }
.icon-forward:hover { background:#4338ca; color:#fff; }
.icon-pdf-app  { background:#eef2ff; color:#4338ca; }
.icon-pdf-app:hover  { background:#4338ca; color:#fff; }
.icon-pdf-fwd  { background:#dbeafe; color:#1d4ed8; }
.icon-pdf-fwd:hover  { background:#1d4ed8; color:#fff; }
.icon-pdf-order{ background:#dcfce7; color:#166534; }
.icon-pdf-order:hover{ background:#166534; color:#fff; }
.opa-status-pending  { background:#fef3c7; color:#92400e; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.opa-status-approved { background:#dcfce7; color:#166534; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.opa-status-rejected { background:#fee2e2; color:#991b1b; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
.opa-status-forwarded{ background:#e0e7ff; color:#3730a3; padding:.2rem .5rem; border-radius:.35rem; font-size:.72rem; font-weight:500; }
</style>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-8">
        <h4 class="fw-bold mb-0"><i class="ti tabler-send me-2 text-primary"></i>ঐচ্ছিক ছুটি — অনুমোদনের জন্য প্রেরণ</h4>
        <div class="text-muted small mt-1">সুপারভাইজার-সুপারিশপ্রাপ্ত আবেদনগুলো signatory চেইনে প্রেরণ করুন</div>
    </div>
</div>

<?php if (!$isCenterAdmin): ?>
    <div class="alert alert-warning">
        <i class="ti tabler-alert-triangle me-1"></i>এই কার্যক্রম কেবল কেন্দ্র প্রশাসনের জন্য।
    </div>
<?php else: ?>

<!-- Stats strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6">
        <div class="stat-card stat-pending stat-clickable" data-tab-target="#opaPendingTab">
            <div class="stat-icon"><i class="ti tabler-clock"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($pendingCount) ?></div>
                <div class="stat-label">প্রক্রিয়াধীন</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="stat-card stat-info stat-clickable" data-tab-target="#opaForwardedTab">
            <div class="stat-icon"><i class="ti tabler-send"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($forwardedCount) ?></div>
                <div class="stat-label">প্রেরিত</div>
            </div>
        </div>
    </div>
</div>

<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-0">
        <ul class="nav custom-leave-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#opaPendingTab" role="tab">
                    <i class="ti tabler-clock me-2"></i>
                    <span class="d-none d-sm-inline">প্রক্রিয়াধীন</span>
                    <span class="badge ms-2"><?= banglaNumber($pendingCount) ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#opaForwardedTab" role="tab">
                    <i class="ti tabler-send me-2"></i>
                    <span class="d-none d-sm-inline">প্রেরিত</span>
                    <span class="badge ms-2"><?= banglaNumber($forwardedCount) ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3">
            <!-- Tab 1: Pending -->
            <div class="tab-pane fade show active" id="opaPendingTab" role="tabpanel">
                <div class="table-responsive">
                    <table id="opaFwdQueueTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:60px;">ক্রমিক</th>
                                <th>কর্মচারী</th>
                                <th>বছর</th>
                                <th class="text-center">চাহিত দিন</th>
                                <th>উৎসব নোট</th>
                                <th>সুপারভাইজার</th>
                                <th>সাবমিট তারিখ</th>
                                <th class="text-center">কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Forwarded -->
            <div class="tab-pane fade" id="opaForwardedTab" role="tabpanel">
                <div class="table-responsive">
                    <table id="opaFwdedTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:60px;">ক্রমিক</th>
                                <th>কর্মচারী</th>
                                <th>বছর</th>
                                <th class="text-center">চাহিত দিন</th>
                                <th class="text-center">অনুমোদিত দিন</th>
                                <th>প্রেরণ তারিখ</th>
                                <th>স্টেটাস</th>
                                <th class="text-center">কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forward modal -->
<div class="modal fade" id="opaFwdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-send me-2"></i>অনুমোদনের জন্য প্রেরণ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="opaFwdSummary" class="mb-3">
                    <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
                </div>
                <form id="opaFwdForm">
                    <input type="hidden" name="id" id="fwd_pid" />
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">অনুমোদিত দিন <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" min="0.5" max="3" required name="approved_days" id="fwd_approved_days" class="form-control" />
                            <div class="form-text small">চাহিত দিনের চেয়ে বেশি অনুমোদন করা যাবে না।</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">প্রশাসনিক মন্তব্য</label>
                            <textarea name="admin_note" class="form-control" rows="2" placeholder="ঐচ্ছিক..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
                    <i class="ti tabler-x me-1"></i>বাতিল
                </button>
                <button type="button" id="opaFwdSubmitBtn" class="btn btn-primary">
                    <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Detail modal -->
<div class="modal fade" id="opaFwdDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-file-info me-2"></i>পূর্বানুমোদন বিবরণ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="opaFwdDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
window.BITAC_OPA_FWD = {
    menuslug: '<?= htmlspecialchars($_GET['menuslug'] ?? 'optional-pre-approval-forward-queue') ?>'
};

(function bootOpaFwdQueue() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || typeof Swal === 'undefined' || !jQuery.fn.DataTable) {
        return setTimeout(bootOpaFwdQueue, 30);
    }
    var qTable = null;
    var fwdedTable = null;

    function initPage() {
        // Pending tab DataTable
        if ($.fn.DataTable.isDataTable('#opaFwdQueueTable')) {
            $('#opaFwdQueueTable').DataTable().destroy();
        }
        qTable = $('#opaFwdQueueTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: 'lrtip',
            ajax: {
                url: '../../api/optional-pre-approval/fetch-forward-queue.php?menuslug=' + BITAC_OPA_FWD.menuslug,
                type: 'POST'
            },
            columns: [
                { data: 'sl',         orderable: false },
                { data: 'employee',   orderable: false },
                { data: 'year',       orderable: false },
                { data: 'days',       orderable: false, className: 'text-center' },
                { data: 'notes',      orderable: false },
                { data: 'supervisor', orderable: false },
                { data: 'submit_date',orderable: false },
                { data: 'action',     orderable: false, className: 'text-center' }
            ],
            language: {
                processing: '<div class="spinner-border text-primary"></div>',
                lengthMenu: 'প্রদর্শন করুন _MENU_ টি',
                info: 'প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি',
                infoEmpty: 'কোনো এন্ট্রি নেই',
                zeroRecords: 'কোনো মিল নেই',
                emptyTable: '<div class="text-center py-4 text-muted"><i class="ti tabler-inbox-off" style="font-size:2.5rem;color:#cbd5e1;"></i><div class="mt-2">প্রেরণের জন্য কোনো আবেদন নেই</div></div>',
                paginate: { first: 'প্রথম', previous: 'পূর্ববর্তী', next: 'পরবর্তী', last: 'শেষ' }
            }
        });

        // Forwarded tab (lazy init on first tab-shown)
        $(document).off('shown.bs.tab.opaFwded').on('shown.bs.tab.opaFwded', 'button[data-bs-target="#opaForwardedTab"]', function () {
            if (fwdedTable) return;
            fwdedTable = $('#opaFwdedTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                dom: 'lrtip',
                ajax: {
                    url: '../../api/optional-pre-approval/fetch-forwarded-history.php?menuslug=' + BITAC_OPA_FWD.menuslug,
                    type: 'POST'
                },
                columns: [
                    { data: 'sl',            orderable: false },
                    { data: 'employee',      orderable: false },
                    { data: 'year',          orderable: false },
                    { data: 'days',          orderable: false, className: 'text-center' },
                    { data: 'approved_days', orderable: false, className: 'text-center' },
                    { data: 'forward_date',  orderable: false },
                    { data: 'status',        orderable: false },
                    { data: 'action',        orderable: false, className: 'text-center' }
                ],
                language: {
                    processing: '<div class="spinner-border text-primary"></div>',
                    lengthMenu: 'প্রদর্শন করুন _MENU_ টি',
                    info: 'প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি',
                    infoEmpty: 'কোনো এন্ট্রি নেই',
                    zeroRecords: 'কোনো মিল নেই',
                    emptyTable: '<div class="text-center py-4 text-muted"><i class="ti tabler-file-off" style="font-size:2.5rem;color:#cbd5e1;"></i><div class="mt-2">এখনো কোনো আবেদন প্রেরিত হয়নি</div></div>',
                    paginate: { first: 'প্রথম', previous: 'পূর্ববর্তী', next: 'পরবর্তী', last: 'শেষ' }
                }
            });
        });

        // Stat card → tab
        $(document).off('click.opaFwdStat').on('click.opaFwdStat', '.stat-clickable', function() {
            var target = $(this).data('tab-target');
            if (!target) return;
            var $btn = $('button[data-bs-target="' + target + '"]');
            if ($btn.length && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance($btn[0]).show();
            } else { $btn.trigger('click'); }
        });

        $(document).off('click.opaFwdView').on('click.opaFwdView', '.btn-opa-view', function() {
            var pid = $(this).data('pid');
            $('#opaFwdDetailBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
            $('#opaFwdDetailModal').modal('show');
            $.get('../../api/optional-pre-approval/detail.php', { id: pid }, function(html) {
                $('#opaFwdDetailBody').html(html);
            }).fail(function() { $('#opaFwdDetailBody').html('<div class="alert alert-danger">লোড ব্যর্থ</div>'); });
        });

        $(document).off('click.opaFwdOpen').on('click.opaFwdOpen', '.btn-opa-forward', function() {
            var pid       = $(this).data('pid');
            var requested = parseFloat($(this).data('requested')) || 0;
            $('#fwd_pid').val(pid);
            $('#fwd_approved_days').val(requested).attr('max', requested);
            $('#opaFwdForm textarea[name="admin_note"]').val('');
            $('#opaFwdSummary').html('<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>');
            $('#opaFwdModal').modal('show');
            $.get('../../api/optional-pre-approval/detail.php', { id: pid }, function(html) {
                $('#opaFwdSummary').html(html);
            }).fail(function() { $('#opaFwdSummary').html('<div class="alert alert-danger">লোড ব্যর্থ</div>'); });
        });

        $(document).off('click.opaFwdSubmit').on('click.opaFwdSubmit', '#opaFwdSubmitBtn', function() {
            if (!$('#opaFwdForm')[0].reportValidity()) return;
            var payload = $('#opaFwdForm').serialize();
            $('#opaFwdSubmitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>প্রেরণ হচ্ছে...');
            $.ajax({
                type: 'POST',
                url: '../../api/optional-pre-approval/forward-to-approval.php',
                data: payload,
                dataType: 'json',
                success: function(resp) {
                    $('#opaFwdSubmitBtn').prop('disabled', false).html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
                    if (resp && resp.status === 1) {
                        $('#opaFwdModal').modal('hide');
                        Swal.fire({ title: 'সফল', text: resp.message, icon: 'success', confirmButtonColor: '#6c5ce7', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        qTable.ajax.reload(null, false);
                        if (fwdedTable) fwdedTable.ajax.reload(null, false);
                    } else {
                        Swal.fire({ title: 'ত্রুটি', text: (resp && resp.message) || 'ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                    }
                },
                error: function() {
                    $('#opaFwdSubmitBtn').prop('disabled', false).html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
                    Swal.fire({ title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                }
            });
        });
    }

    $(document).ready(initPage);
    document.addEventListener('turbo:load', initPage);
})();
</script>

<?php endif; ?>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
