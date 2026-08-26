<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Re-query the full user: the sidebar overwrites $getUserInfoQRW with three columns.
$_stmt = mysqli_prepare($con, "SELECT employee_id, user_group_id FROM user_list WHERE user_id = ?");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_stmt, 's', $_un);
mysqli_stmt_execute($_stmt);
$_full = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt)) ?: [];
mysqli_stmt_close($_stmt);

$actorEmpId   = (int)($_full['employee_id']   ?? 0);
$isSuperAdmin = ((int)($_full['user_group_id'] ?? 0) === 1);

// Which centres may this actor sign certificates for? Same table that the
// signatory setup page writes — leave_edit_approval_signatory.
$allowedOrgIDs = [];
if ($actorEmpId > 0) {
    $sigQ = mysqli_query($con,
        "SELECT organization_id FROM leave_edit_approval_signatory
          WHERE employeeID = $actorEmpId AND organization_id > 0");
    // organization_id = 0 rows are legacy leftovers assigned to no centre. They
    // authorise nothing, so counting them would show an empty queue with no
    // explanation instead of the "you are not a signatory" notice.
    if ($sigQ) while ($r = mysqli_fetch_assoc($sigQ)) $allowedOrgIDs[] = (int)$r['organization_id'];
}

$pendingCount = 0;
$showNoAuth   = false;
if ($isSuperAdmin) {
    $pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(*) AS c FROM yearly_leave_summary WHERE isApproved = 0"))['c'] ?? 0);
} elseif (!empty($allowedOrgIDs)) {
    $orgList = implode(',', $allowedOrgIDs);
    $pendingCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(*) AS c
         FROM yearly_leave_summary yls
         INNER JOIN employee_list el ON yls.employeeID = el.id
         WHERE yls.isApproved = 0 AND el.organization_id IN ($orgList)"))['c'] ?? 0);
} else {
    $showNoAuth = true;
}

$approvedCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM yearly_leave_summary WHERE isApproved = 1"))['c'] ?? 0);

$orgsQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY organization_name ASC");
$orgOptions = '';
while ($o = mysqli_fetch_assoc($orgsQ)) {
    $orgOptions .= '<option value="' . (int)$o['id'] . '">' . htmlspecialchars($o['organization_name']) . '</option>';
}

$yearQ = mysqli_query($con, "SELECT DISTINCT year FROM yearly_leave_summary ORDER BY year DESC");
$yearOptions = '';
while ($y = mysqli_fetch_assoc($yearQ)) {
    $yearOptions .= '<option value="' . htmlspecialchars($y['year']) . '">' . banglaNumber($y['year']) . '</option>';
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-certificate me-2 text-primary"></i>ছুটি সনদ অনুমোদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card stat-pending"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="<?= $isSuperAdmin ? 'সকল কেন্দ্রের' : 'আপনার সিগনেটরি দায়িত্বে থাকা' ?> অপেক্ষমান সনদের সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-clock"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($pendingCount) ?></div>
                <div class="stat-label">অনুমোদনের অপেক্ষায়</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="ti tabler-checks"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?= banglaNumber($approvedCount) ?></div>
                <div class="stat-label">অনুমোদিত</div>
            </div>
        </div>
    </div>
</div>

<?php if ($showNoAuth): ?>
<div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
    <i class="ti tabler-alert-triangle me-2"></i>
    <div>
        আপনি কোনো কেন্দ্রের ছুটি সনদ অনুমোদনে নিযুক্ত সিগনেটরি নন।
        সিগনেটরি নির্ধারণের জন্য
        <a href="../../views/signatory/previous_leave_deduction_addition_certificate_main.php?menuslug=leave-settings" class="alert-link">সেটিংস</a>
        দেখুন।
    </div>
</div>
<?php endif; ?>

<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-status="0">
                    <i class="ti tabler-clock me-1"></i>অপেক্ষমান
                    <span class="badge bg-white text-dark ms-1"><?= banglaNumber($pendingCount) ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-status="1"><i class="ti tabler-check me-1"></i>অনুমোদিত</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-status="2"><i class="ti tabler-x me-1"></i>অননুমোদিত</button>
            </li>
        </ul>

        <div class="filter-panel mb-3 is-collapsed" data-scope="cert">
            <div class="filter-panel-header">
                <button type="button" class="filter-panel-toggle" data-scope="cert" aria-expanded="false" aria-controls="filterBody-cert">
                    <i class="ti tabler-filter me-1"></i>
                    <span class="filter-panel-title">ফিল্টার</span>
                    <span class="filter-active-count" data-scope="cert"></span>
                    <i class="ti tabler-chevron-down filter-chevron ms-2"></i>
                </button>
                <div class="filter-panel-actions">
                    <button type="button" class="btn btn-sm btn-icon btn-label-primary" id="certRefresh" title="টেবিল রিফ্রেশ" data-bs-toggle="tooltip">
                        <i class="ti tabler-refresh"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-label-secondary" id="certReset">
                        <i class="ti tabler-x me-1"></i>রিসেট
                    </button>
                </div>
            </div>
            <div class="filter-panel-body" id="filterBody-cert">
                <div class="row g-2">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="filter-label"><i class="ti tabler-map-pin"></i>কেন্দ্র</label>
                        <select id="certOrgFilter" class="form-select form-select-sm filter-input" data-scope="cert">
                            <option value="">সকল কেন্দ্র</option>
                            <?= $orgOptions ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="filter-label"><i class="ti tabler-calendar"></i>বছর</label>
                        <select id="certYearFilter" class="form-select form-select-sm filter-input" data-scope="cert">
                            <option value="">সকল বছর</option>
                            <?= $yearOptions ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="certApprovalTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:70px;">ক্রমিক</th>
                        <th>কর্মচারী</th>
                        <th>কেন্দ্র</th>
                        <th>বছর ও স্মারক</th>
                        <th>ছুটির হিসাব</th>
                        <th>স্বাক্ষরকারী</th>
                        <th class="text-center" style="width:150px;">কার্যাবলী</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Certificate preview -->
<style>
#certDocModal .modal-dialog { max-width: 95vw; margin: 1rem auto; }
#certDocModal .modal-content { height: calc(100vh - 2rem); display: flex; flex-direction: column; }
#certDocModal .modal-body { flex: 1 1 auto; min-height: 0; padding: 0; position: relative; background: #f5f7fa; }
#certDocModal #certDocIframe { width: 100%; height: 100%; border: 0; background: #fff; display: block; }
#certDocModal #certDocLoader { position: absolute; inset: 0; background: #fff; z-index: 2;
    display: flex; flex-direction: column; align-items: center; justify-content: center; }
#certDocModal #certDocLoader.d-none { display: none !important; }
</style>
<div class="modal fade" id="certDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 px-3" style="background:linear-gradient(155deg,#0e1e34 0%,#1e3a5f 100%);color:#fff;border:none;">
                <h5 class="modal-title mb-0" style="color:#fff;font-size:1rem;">
                    <i class="ti tabler-certificate me-2"></i>ছুটির সনদ
                </h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a href="#" id="certDocOpenBtn" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                        <i class="ti tabler-external-link me-1"></i>নতুন ট্যাবে খুলুন
                    </a>
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                        <i class="ti tabler-x"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div id="certDocLoader">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="text-muted small">সনদ লোড হচ্ছে...</div>
                </div>
                <iframe id="certDocIframe" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function bootCertApproval() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable ||
        typeof Swal === 'undefined' || typeof bootstrap === 'undefined') {
        return setTimeout(bootCertApproval, 20);
    }
    var $ = jQuery;
    if ($('#certApprovalTable').data('bound')) return;
    $('#certApprovalTable').data('bound', true);

    var status = 0;

    var dt = $('#certApprovalTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: '../../api/leave-certificate/fetch-approval.php',
            type: 'POST',
            data: function (d) {
                d.status       = status;
                d.centerFilter = $('#certOrgFilter').val()  || '';
                d.yearFilter   = $('#certYearFilter').val() || '';
            }
        },
        columns: [
            { data: 'sl',        orderable: false },
            { data: 'employee',  orderable: false },
            { data: 'center',    orderable: false },
            { data: 'year',      orderable: false },
            { data: 'figures',   orderable: false },
            { data: 'signatory', orderable: false },
            { data: 'actions',   orderable: false, className: 'text-center' }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: 'লোড হচ্ছে...',
            emptyTable: 'কোনো সনদ নেই',
            zeroRecords: 'কিছু পাওয়া যায়নি',
            info: 'প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি',
            infoEmpty: 'কোনো এন্ট্রি নেই',
            lengthMenu: 'প্রদর্শন করুন _MENU_ টি এন্ট্রি',
            search: 'খুঁজুন:',
            paginate: { first: 'প্রথম', last: 'শেষ', next: 'পরবর্তী', previous: 'পূর্ববর্তী' }
        }
    });

    $('.nav-pills [data-status]').on('click', function () {
        $('.nav-pills [data-status]').removeClass('active');
        $(this).addClass('active');
        status = parseInt($(this).data('status'), 10);
        dt.ajax.reload();
    });

    $('#certOrgFilter, #certYearFilter').on('change', function () { dt.ajax.reload(); });
    $('#certRefresh').on('click', function () { dt.ajax.reload(null, false); });
    $('#certReset').on('click', function () {
        $('#certOrgFilter, #certYearFilter').val('');
        dt.ajax.reload();
    });

    // ── Certificate preview ───────────────────────────────────────────
    var $m = $('#certDocModal'), $if = $('#certDocIframe'), $ld = $('#certDocLoader');
    // Delegated — DataTables rebuilds the rows on every draw.
    $(document).on('click', '.cert-view', function () {
        var url = $(this).data('url');
        if (!url) return;
        $ld.removeClass('d-none');
        $if[0].src = url;
        $('#certDocOpenBtn').attr('href', url);
        $m.modal('show');
    });
    $if[0].addEventListener('load', function () {
        if ($if[0].src.indexOf('about:blank') === -1) $ld.addClass('d-none');
    });
    $m.on('hidden.bs.modal', function () {
        $if[0].src = 'about:blank';
        $ld.removeClass('d-none');
    });

    function act(id, isApproved, reason) {
        $.post('../../api/leave-certificate/approval-action.php',
               { leaveSummaryID: id, isApproved: isApproved, reason: reason || '' }, null, 'json')
            .done(function (r) {
                if (r && r.status === 'success') {
                    Swal.fire({ icon: 'success', title: r.message, timer: 1300, showConfirmButton: false });
                    dt.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'সম্পন্ন হয়নি', text: (r && r.message) || 'অজানা ত্রুটি',
                                customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                }
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'ত্রুটি', text: 'সার্ভারে পৌঁছানো যায়নি',
                            customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
            });
    }

    $(document).on('click', '.cert-approve', function () {
        var id = parseInt($(this).data('id'), 10), name = $(this).data('name');
        Swal.fire({
            title: 'সনদ অনুমোদন করবেন?',
            html: '<strong>' + $('<div>').text(name).html() + '</strong>-এর ছুটির সনদ অনুমোদিত হবে।',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'হ্যাঁ, অনুমোদন করুন', cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-success me-3', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        }).then(function (res) { if (res.isConfirmed) act(id, 1, ''); });
    });

    $(document).on('click', '.cert-reject', function () {
        var id = parseInt($(this).data('id'), 10), name = $(this).data('name');
        Swal.fire({
            title: 'সনদ অননুমোদিত করা',
            html: '<div class="mb-2"><strong>' + $('<div>').text(name).html() + '</strong>-এর সনদ অননুমোদিত করছেন।</div>',
            input: 'textarea',
            inputPlaceholder: 'কারণ লিখুন (আবশ্যক)',
            inputAttributes: { 'aria-label': 'কারণ' },
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'অননুমোদিত করুন', cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false,
            inputValidator: function (v) {
                if (!v || !v.trim()) return 'কারণ ছাড়া অননুমোদিত করা যাবে না';
            }
        }).then(function (res) { if (res.isConfirmed) act(id, 2, res.value); });
    });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
