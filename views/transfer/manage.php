<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Resolve actor scope:
//  - Super Admin (user_group_id=1) → all centers, can initiate
//  - HQ users (organization_id=4 with menu access) → all centers, can initiate
//  - Other center users → read-only, scoped to own center
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
$_isHQ          = ($_myCenterID === 4);
$_canWrite      = ($_isSuperAdmin || $_isHQ);
$_seeAllCenters = ($_isSuperAdmin || $_isHQ);

// Centers for dropdowns
if ($_seeAllCenters) {
    $centersRes = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted = 0 ORDER BY organization_name ASC");
    $allCenters = mysqli_fetch_all($centersRes, MYSQLI_ASSOC);
} else {
    $cStmt = mysqli_prepare($con, "SELECT id, organization_name FROM organization WHERE id = ? AND deleted = 0");
    mysqli_stmt_bind_param($cStmt, 'i', $_myCenterID);
    mysqli_stmt_execute($cStmt);
    $allCenters = mysqli_fetch_all(mysqli_stmt_get_result($cStmt), MYSQLI_ASSOC);
    mysqli_stmt_close($cStmt);
}

// Stats — scoped
$_statOrgWhere = $_seeAllCenters
    ? ''
    : ' AND (h.from_organization_id = ' . $_myCenterID . ' OR h.to_organization_id = ' . $_myCenterID . ')';

$ytdStart = date('Y') . '-01-01';
$statsRes = mysqli_query($con,
    "SELECT
        COUNT(*) AS total_ytd,
        SUM(CASE WHEN e.pending_section_assignment = 1 THEN 1 ELSE 0 END) AS pending_section_count,
        SUM(CASE WHEN h.section_id_at_join IS NOT NULL AND h.effective_to IS NULL THEN 1 ELSE 0 END) AS active_postings
     FROM employee_transfer_history h
     LEFT JOIN employee_list e ON e.id = h.employee_ref_id
     WHERE h.transfer_date >= '$ytdStart'
       AND h.from_organization_id IS NOT NULL
       $_statOrgWhere");
$stats = mysqli_fetch_assoc($statsRes) ?: ['total_ytd'=>0,'pending_section_count'=>0,'active_postings'=>0];
?>

<style>
.transfer-stat-card { border-radius: 0.75rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: .75rem; background:#fff; box-shadow:0 0 12px rgba(0,0,0,.04); }
.transfer-stat-card .icon { width:44px; height:44px; border-radius:.5rem; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.transfer-stat-card .num { font-size:1.4rem; font-weight:700; line-height:1; }
.transfer-stat-card .label { font-size:.85rem; color:#6c757d; margin-top:.15rem; }
.stat-blue .icon { background:#e7f1ff; color:#3b82f6; }
.stat-amber .icon { background:#fef3c7; color:#d97706; }
.stat-green .icon { background:#dcfce7; color:#16a34a; }

.transfer-route { display:inline-flex; align-items:center; gap:.4rem; font-weight:500; }
.transfer-route .from { color:#6c757d; }
.transfer-route .arrow { color:#94a3b8; }
.transfer-route .to { color:#1e40af; font-weight:600; }

.badge-status-pending { background:#fef3c7; color:#92400e; padding:.25rem .55rem; border-radius:.4rem; font-size:.75rem; font-weight:500; }
.badge-status-active  { background:#dcfce7; color:#166534; padding:.25rem .55rem; border-radius:.4rem; font-size:.75rem; font-weight:500; }
.badge-status-closed  { background:#e5e7eb; color:#374151; padding:.25rem .55rem; border-radius:.4rem; font-size:.75rem; font-weight:500; }

.emp-cell { display:flex; align-items:center; gap:.65rem; }
.emp-avatar { width:36px; height:36px; border-radius:50%; background:#e0e7ff; color:#3730a3; display:flex; align-items:center; justify-content:center; font-weight:600; overflow:hidden; }
.emp-avatar img { width:100%; height:100%; object-fit:cover; }
.emp-meta .emp-name { font-weight:600; font-size:.92rem; color:#1f2937; }
.emp-meta .emp-sub { font-size:.78rem; color:#6b7280; }
.emp-sub-light { color:#9ca3af; font-weight:400; }

.action-icon { display:inline-flex; width:30px; height:30px; align-items:center; justify-content:center; border-radius:.4rem; background:#f3f4f6; color:#4b5563; border:0; margin-right:.25rem; transition:all .15s; }
.action-icon:hover { background:#e5e7eb; color:#1f2937; }
.action-icon.view { color:#0ea5e9; }
.action-icon.edit { color:#16a34a; }
</style>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-transfer me-2 text-primary"></i>কর্মচারী বদলি ব্যবস্থাপনা</h4>
        <div class="text-muted small mt-1">এক কেন্দ্র থেকে অন্য কেন্দ্রে বদলি ও পোস্টিং ইতিহাস</div>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <?php if ($_canWrite): ?>
            <a href="report.php?menuslug=<?= htmlspecialchars($_GET['menuslug'] ?? 'employee-transfer') ?>" class="btn btn-label-secondary me-2" data-turbo="true">
                <i class="ti tabler-report me-1"></i>রিপোর্ট
            </a>
            <button type="button" class="btn btn-primary" id="btnNewTransfer">
                <i class="ti tabler-plus me-1"></i>নতুন বদলি
            </button>
        <?php else: ?>
            <span class="badge bg-label-info"><i class="ti tabler-eye me-1"></i>শুধু দেখার অনুমতি</span>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Strip -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="transfer-stat-card stat-blue">
            <div class="icon"><i class="ti tabler-arrows-exchange"></i></div>
            <div>
                <div class="num"><?= banglaNumber((int)$stats['total_ytd']) ?></div>
                <div class="label">এ বছর মোট বদলি</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="transfer-stat-card stat-amber">
            <div class="icon"><i class="ti tabler-clock-pause"></i></div>
            <div>
                <div class="num"><?= banglaNumber((int)$stats['pending_section_count']) ?></div>
                <div class="label">সেকশন বরাদ্দ অপেক্ষমান</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="transfer-stat-card stat-green">
            <div class="icon"><i class="ti tabler-check"></i></div>
            <div>
                <div class="num"><?= banglaNumber((int)$stats['active_postings']) ?></div>
                <div class="label">সক্রিয় পোস্টিং</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter row -->
<div class="card shadow-sm border-0 mb-3" style="border-radius:0.75rem;">
    <div class="card-body py-3 px-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1" for="filterDateFrom"><i class="ti tabler-calendar me-1"></i>হতে</label>
                <input type="text" id="filterDateFrom" class="form-control form-control-sm flatpickr-input" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1" for="filterDateTo"><i class="ti tabler-calendar me-1"></i>পর্যন্ত</label>
                <input type="text" id="filterDateTo" class="form-control form-control-sm flatpickr-input" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1" for="filterFromCenter">পূর্বের কেন্দ্র</label>
                <select id="filterFromCenter" class="form-select form-select-sm">
                    <option value="0">সব</option>
                    <?php foreach ($allCenters as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['organization_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1" for="filterToCenter">নতুন কেন্দ্র</label>
                <select id="filterToCenter" class="form-select form-select-sm">
                    <option value="0">সব</option>
                    <?php foreach ($allCenters as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['organization_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1" for="filterStatus">অবস্থা</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">সব</option>
                    <option value="pending">সেকশন অপেক্ষমান</option>
                    <option value="active">সক্রিয়</option>
                    <option value="closed">পরবর্তী বদলি হয়েছে</option>
                </select>
            </div>
            <div class="col-12 mt-2">
                <div class="d-flex gap-2">
                    <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="কর্মচারীর নাম / আইডি / আদেশ নং দিয়ে খুঁজুন...">
                    <button type="button" id="btnResetFilters" class="btn btn-sm btn-label-secondary"><i class="ti tabler-x me-1"></i>রিসেট</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer List Card -->
<div class="card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="transferListTable" class="table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>ক্রমিক</th>
                        <th>কর্মচারী</th>
                        <th>রুট</th>
                        <th>আদেশ নং</th>
                        <th>আদেশ তারিখ</th>
                        <th>কার্যকর তারিখ</th>
                        <th>যোগদান</th>
                        <th>অবস্থা</th>
                        <th class="text-center">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($_canWrite): ?>
<!-- New Transfer Modal -->
<div class="modal fade" id="newTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="newTransferForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti tabler-transfer me-2 text-primary"></i>নতুন বদলির আদেশ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">কর্মচারী <span class="text-danger">*</span></label>
                            <select id="modal_employee" name="dataID" class="form-select" required></select>
                            <small class="text-muted">নাম বা আইডি দিয়ে খুঁজুন</small>
                        </div>
                        <div class="col-12">
                            <div id="empCurrentBox" class="alert alert-light border d-none mb-0 py-2">
                                <small class="text-muted">বর্তমান কেন্দ্র:</small>
                                <span id="empCurrentCenter" class="fw-semibold ms-1">—</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">নতুন কেন্দ্র <span class="text-danger">*</span></label>
                            <select id="modal_to_organization_id" name="to_organization_id" class="form-select" required>
                                <option value="">— নির্বাচন করুন —</option>
                                <?php foreach ($allCenters as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['organization_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">কার্যকর তারিখ <span class="text-danger">*</span></label>
                            <input type="text" id="modal_transfer_date" name="transfer_date" class="form-control flatpickr-input" required placeholder="YYYY-MM-DD">
                            <small class="text-muted">যে তারিখ থেকে বদলি কার্যকর</small>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">আদেশ নম্বর</label>
                            <input type="text" id="modal_order_number" name="order_number" class="form-control" placeholder="অফিস আদেশের নম্বর">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">আদেশ তারিখ</label>
                            <input type="text" id="modal_order_date" name="order_date" class="form-control flatpickr-input" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-12">
                            <label class="form-label">কারণ / মন্তব্য</label>
                            <textarea id="modal_reason" name="reason" class="form-control" rows="2" placeholder="বদলির কারণ"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">আদেশের কপি (JPG/PNG/PDF, সর্বোচ্চ ২ MB)</label>
                            <input type="file" id="modal_attachment" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="button" class="btn btn-primary" id="btnSubmitTransfer"><i class="ti tabler-check me-1"></i>বদলি সংরক্ষণ</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Detail / History Modal -->
<div class="modal fade" id="transferDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-history me-2"></i>পোস্টিং ইতিহাস</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ"></button>
            </div>
            <div class="modal-body" id="transferDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
window.BITAC_TRANSFER = {
    canWrite: <?= $_canWrite ? 'true' : 'false' ?>,
    seeAllCenters: <?= $_seeAllCenters ? 'true' : 'false' ?>,
    myCenterID: <?= (int)$_myCenterID ?>,
    menuslug: '<?= htmlspecialchars($_GET['menuslug'] ?? 'employee-transfer') ?>'
};

(function bootTransferPage() {
    if (typeof jQuery === 'undefined' || !jQuery.fn ||
        typeof Swal === 'undefined' ||
        !jQuery.fn.DataTable ||
        !jQuery.fn.select2) {
        return setTimeout(bootTransferPage, 30);
    }

    var transferDt = null;

function initTransferPage() {
    setTimeout(function() {
        // Flatpickr on date filters and modal dates
        if (typeof flatpickr !== 'undefined') {
            flatpickr('#filterDateFrom', { dateFormat: 'Y-m-d', allowInput: true });
            flatpickr('#filterDateTo',   { dateFormat: 'Y-m-d', allowInput: true });
            // static:true keeps the calendar within the modal so it isn't clipped/hidden by Bootstrap's z-index/overflow
            flatpickr('#modal_transfer_date', { dateFormat: 'Y-m-d', allowInput: true, static: true });
            flatpickr('#modal_order_date',    { dateFormat: 'Y-m-d', allowInput: true, static: true });
        }

        if ($.fn.DataTable.isDataTable('#transferListTable')) {
            $('#transferListTable').DataTable().destroy();
        }

        transferDt = $('#transferListTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: 'lrtip',
            ajax: {
                url: '../../api/transfer/list.php?menuslug=' + BITAC_TRANSFER.menuslug,
                type: 'POST',
                data: function(d) {
                    d.date_from   = $('#filterDateFrom').val() || '';
                    d.date_to     = $('#filterDateTo').val() || '';
                    d.from_center = $('#filterFromCenter').val() || '0';
                    d.to_center   = $('#filterToCenter').val() || '0';
                    d.status      = $('#filterStatus').val() || '';
                    d.search      = d.search || {};
                    d.search.value = $('#filterSearch').val() || '';
                }
            },
            columns: [
                { data: 'sl', orderable: false },
                { data: 'employee_cell', orderable: false },
                { data: 'route', orderable: false },
                { data: 'order_number', orderable: false },
                { data: 'order_date', orderable: false },
                { data: 'transfer_date', orderable: true },
                { data: 'actual_joining_date', orderable: false },
                { data: 'status_badge', orderable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ],
            order: [[5, 'desc']],
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
                lengthMenu: 'প্রদর্শন করুন _MENU_ টি এন্ট্রি',
                info: 'প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি',
                infoEmpty: 'কোন এন্ট্রি নেই',
                infoFiltered: '(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)',
                zeroRecords: 'কোন মিল খুঁজে পাওয়া যায়নি',
                emptyTable: '<div class="text-center py-4 text-muted"><i class="ti tabler-transfer" style="font-size:2.5rem;color:#cbd5e1;"></i><div class="mt-2">এখনো কোনো বদলির রেকর্ড নেই</div></div>',
                paginate: { first: 'প্রথম', previous: 'পূর্ববর্তী', next: 'পরবর্তী', last: 'শেষ' }
            }
        });

        // Filter wiring
        var _t;
        $('#filterSearch').off('input.tr').on('input.tr', function() {
            clearTimeout(_t); _t = setTimeout(function() { transferDt.ajax.reload(); }, 250);
        });
        $('#filterDateFrom, #filterDateTo, #filterFromCenter, #filterToCenter, #filterStatus').off('change.tr').on('change.tr', function() {
            transferDt.ajax.reload();
        });
        $('#btnResetFilters').off('click.tr').on('click.tr', function() {
            $('#filterDateFrom, #filterDateTo, #filterSearch').val('');
            $('#filterFromCenter, #filterToCenter').val('0');
            $('#filterStatus').val('');
            transferDt.ajax.reload();
        });

        // New transfer button
        if (BITAC_TRANSFER.canWrite) {
            $('#btnNewTransfer').off('click.tr').on('click.tr', function() {
                openNewTransferModal();
            });

            // Employee select2 with AJAX — destroy previous instance first
            // (initTransferPage can fire on ready + turbo:load + immediate, so guard against double-init)
            if ($.fn.select2) {
                var $modalEmp = $('#modal_employee');
                if ($modalEmp.hasClass('select2-hidden-accessible')) {
                    try { $modalEmp.select2('destroy'); } catch (e) {}
                }
                $modalEmp.select2({
                    dropdownParent: $('#newTransferModal'),
                    placeholder: 'নাম বা আইডি দিয়ে খুঁজুন...',
                    allowClear: true,
                    ajax: {
                        url: '../../api/transfer/employee-search.php',
                        dataType: 'json',
                        delay: 250,
                        data: function(p) { return { q: p.term || '' }; },
                        processResults: function(data) {
                            return { results: (data.items || []).map(function(it) {
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

                $('#modal_employee').off('select2:select.tr').on('select2:select.tr', function(e) {
                    var d = e.params.data;
                    $('#empCurrentBox').removeClass('d-none');
                    $('#empCurrentCenter').text(d.current_org_name || '—');
                    // Hide current center from the "to" dropdown to avoid same-center transfer
                    $('#modal_to_organization_id option').prop('disabled', false);
                    $('#modal_to_organization_id option[value="' + d.current_org_id + '"]').prop('disabled', true);
                    if ($('#modal_to_organization_id').val() == d.current_org_id) {
                        $('#modal_to_organization_id').val('').trigger('change');
                    }
                });

                $('#modal_employee').off('select2:clear.tr').on('select2:clear.tr', function() {
                    $('#empCurrentBox').addClass('d-none');
                    $('#modal_to_organization_id option').prop('disabled', false);
                });
            }

            // Submit handler — bound to button click (bypasses Turbo form interception)
            $('#btnSubmitTransfer').off('click.tr').on('click.tr', function(e) {
                e.preventDefault();
                var formEl = document.getElementById('newTransferForm');
                if (formEl && typeof formEl.checkValidity === 'function' && !formEl.checkValidity()) {
                    formEl.reportValidity();
                    return;
                }
                var $btn = $('#btnSubmitTransfer');
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>সংরক্ষণ হচ্ছে...');
                var fd = new FormData(formEl);
                $.ajax({
                    type: 'POST',
                    url: '../../api/employees/transfer.php',
                    data: fd,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(resp) {
                        if (resp && resp.status === 1) {
                            $('#newTransferModal').modal('hide');
                            Swal.fire({ title: 'সফল', text: resp.message || 'বদলি সংরক্ষণ হয়েছে', icon: 'success', confirmButtonColor: '#6c5ce7', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                            transferDt.ajax.reload(null, false);
                        } else {
                            Swal.fire({ title: 'ত্রুটি', text: (resp && resp.message) || 'বদলি সংরক্ষণ ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                        }
                    },
                    error: function() {
                        Swal.fire({ title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ', icon: 'error', confirmButtonColor: '#dc3545', customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<i class="ti tabler-check me-1"></i>বদলি সংরক্ষণ');
                    }
                });
            });
        }

        // Detail click
        $(document).off('click.trDetail').on('click.trDetail', '.btn-tr-detail', function() {
            var empId = $(this).data('emp');
            $('#transferDetailBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
            $('#transferDetailModal').modal('show');
            $.get('../../api/transfer/employee-history.php', { emp: empId }, function(html) {
                $('#transferDetailBody').html(html);
            }).fail(function() {
                $('#transferDetailBody').html('<div class="alert alert-danger">ডেটা লোড ব্যর্থ</div>');
            });
        });

    }, 100);
}

function openNewTransferModal() {
    $('#newTransferForm')[0].reset();
    $('#modal_employee').val(null).trigger('change');
    $('#empCurrentBox').addClass('d-none');
    $('#modal_to_organization_id option').prop('disabled', false);
    $('#newTransferModal').modal('show');
}

    $(document).ready(function() { if ($('#transferListTable').length) initTransferPage(); });
    document.addEventListener('turbo:load', function() { if ($('#transferListTable').length) initTransferPage(); });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
