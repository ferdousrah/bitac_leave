<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Resolve viewer's scope:
//  - Super Admin (user_group_id=1) → sees all centers; center dropdown shows all
//  - Others (Center Admin, Regional Super Admin, etc.) → scoped to their own center
$viewerGroupId = (int)($getUserInfoQRW['user_group_id'] ?? 0);
$isSuperAdminViewer = ($viewerGroupId === 1);

if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = (int)$_SESSION['centerAdminOrgID'];
} else {
    $empID = (int)($_SESSION['employeeID'] ?? 0);
    $r = mysqli_query($con, "SELECT organization_id FROM employee_list WHERE id = $empID LIMIT 1");
    $orgID = (int)(mysqli_fetch_assoc($r)['organization_id'] ?? 0);
}

// Centers for the filter dropdown
if ($isSuperAdminViewer) {
    $centersRes = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted = 0 ORDER BY organization_name ASC");
    $filterCenters = mysqli_fetch_all($centersRes, MYSQLI_ASSOC);
} else {
    // Non-super-admin sees only their own center in the picker (no cross-center access)
    $cStmt = $con->prepare("SELECT id, organization_name FROM organization WHERE id = ? AND deleted = 0");
    $cStmt->bind_param("i", $orgID);
    $cStmt->execute();
    $filterCenters = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cStmt->close();
}

// Total non-center-admin users in scope
if ($isSuperAdminViewer) {
    $userCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(*) AS c FROM user_list ul
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         WHERE (ul.isCenterAdmin IS NULL OR ul.isCenterAdmin = 0)"))['c'] ?? 0);
} else {
    $userCount = (int)(mysqli_fetch_assoc(mysqli_query($con,
        "SELECT COUNT(*) AS c FROM user_list ul
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         WHERE el.organization_id = $orgID
           AND (ul.isCenterAdmin IS NULL OR ul.isCenterAdmin = 0)"))['c'] ?? 0);
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-users me-2 text-primary"></i>ব্যবহারকারী তালিকা</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="new.php?menuslug=<?php echo htmlspecialchars($_GET['menuslug'] ?? 'manage-user'); ?>" class="btn btn-primary" data-turbo="true">
            <i class="ti tabler-plus me-1"></i>নতুন যোগ করুন
        </a>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card stat-info"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="এই কেন্দ্রের সকল সাধারণ ব্যবহারকারীর সংখ্যা (সেন্টার অ্যাডমিন বাদে)">
            <div class="stat-icon"><i class="ti tabler-users-group"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($userCount); ?></div>
                <div class="stat-label">মোট ব্যবহারকারী</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter row -->
<div class="card shadow-sm border-0 mb-3" style="border-radius:0.75rem;">
    <div class="card-body py-3 px-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted mb-1" for="centerFilter">
                    <i class="ti tabler-building me-1"></i>কেন্দ্র
                </label>
                <select id="centerFilter" class="form-select form-select-sm">
                    <option value="0">সকল কেন্দ্র</option>
                    <?php foreach ($filterCenters as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['organization_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted mb-1" for="userSearchInput">
                    <i class="ti tabler-search me-1"></i>খুঁজুন (নাম / employee_id / ইউজারনেম / ইমেইল)
                </label>
                <input type="text" id="userSearchInput" class="form-control form-control-sm"
                       placeholder="যেকোনো keyword দিয়ে খুঁজুন...">
            </div>
            <div class="col-12 col-md-2">
                <button type="button" id="resetUserFilter" class="btn btn-sm btn-label-secondary w-100">
                    <i class="ti tabler-x me-1"></i>রিসেট
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User List Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="userListTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th class="col-checkbox text-center"><input type="checkbox" class="form-check-input" id="selectAllUsers" title="সকল নির্বাচন"></th>
                        <th>ক্রমিক</th>
                        <th>পূর্ণ নাম</th>
                        <th>পদবী</th>
                        <th>কেন্দ্র</th>
                        <th>ইমেইল</th>
                        <th>ইউজারনেম</th>
                        <th class="text-center">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Floating bulk-action bar -->
<div class="bulk-action-bar" id="bulkUserActionBar" hidden>
    <div class="bulk-action-inner">
        <span class="bulk-count"><i class="ti tabler-checkbox me-1"></i><span id="bulkUserCount">০</span> জন নির্বাচিত</span>
        <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn">
            <i class="ti tabler-trash me-1"></i>মুছে ফেলুন
        </button>
        <button type="button" class="btn btn-sm btn-label-secondary" id="bulkClearBtn">
            <i class="ti tabler-x me-1"></i>বাতিল
        </button>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
var userListDt = null;
function initUserListTable() {
    setTimeout(function() {
        if ($.fn.DataTable.isDataTable('#userListTable')) {
            $('#userListTable').DataTable().destroy();
        }
        const urlParams = new URLSearchParams(window.location.search);
        const menuslug = urlParams.get('menuslug') || 'manage-user';

        userListDt = $('#userListTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: 'lrtip', // hide built-in search (we use our own input above the table)
            ajax: {
                url: "../../api/users/fetch.php?menuslug=" + menuslug,
                type: "POST",
                data: function (d) {
                    // Pass current filter values with every request
                    d.centerFilter = $('#centerFilter').val() || '0';
                    // Mirror our custom search input into DataTables' search payload
                    d.search = d.search || {};
                    d.search.value = $('#userSearchInput').val() || '';
                }
            },
            columns: [
                { data: "row_check",   orderable: false, searchable: false, className: 'col-checkbox text-center' },
                { data: "sl",          orderable: false },
                { data: "full_name",   orderable: true },
                { data: "designation", orderable: true },
                { data: "center",      orderable: true },
                { data: "email",       orderable: true },
                { data: "user_id",     orderable: true },
                { data: "action",      orderable: false, searchable: false }
            ],
            order: [[2, 'asc']],
            createdRow: function(row) {
                var labels = ['', 'ক্রমিক', 'পূর্ণ নাম', 'পদবী', 'কেন্দ্র', 'ইমেইল', 'ইউজারনেম', 'কার্যক্রম'];
                var compact = [0, 1, 7];
                $(row).find('td').each(function(i){
                    var $td = $(this);
                    $td.attr('data-label', labels[i] || '');
                    if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
                    if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
                });
            },
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
                lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
                info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
                infoEmpty: "কোন এন্ট্রি নেই",
                infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
                emptyTable: '<div class="empty-state-rich"><i class="ti tabler-user-off"></i><div class="empty-title">কোন ব্যবহারকারী নেই</div><div class="empty-subtitle">এখনো কোনো ব্যবহারকারী যোগ করা হয়নি</div></div>',
                paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
            },
            drawCallback: function() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                        bootstrap.Tooltip.getOrCreateInstance(el);
                    });
                }
                // Reset header checkbox state when redrawing (after filter/page change)
                $('#selectAllUsers').prop('checked', false).prop('indeterminate', false);
                refreshBulkBar();
            }
        });

        // Filter wiring
        $('#centerFilter').off('change.userListFilter').on('change.userListFilter', function () {
            userListDt.ajax.reload();
        });
        // Debounce free-text search a bit so we don't fire per-keystroke
        var _searchT;
        $('#userSearchInput').off('input.userListFilter').on('input.userListFilter', function () {
            clearTimeout(_searchT);
            _searchT = setTimeout(function () { userListDt.ajax.reload(); }, 250);
        });
        $('#resetUserFilter').off('click.userListFilter').on('click.userListFilter', function () {
            $('#centerFilter').val('0');
            $('#userSearchInput').val('');
            userListDt.ajax.reload();
        });
    }, 100);
}

// ── Bulk selection + delete ──────────────────────────────────────────
function toBnNum(n) {
    return String(n).replace(/[0-9]/g, function (d) { return '০১২৩৪৫৬৭৮৯'[+d]; });
}
function refreshBulkBar() {
    var n = $('.user-row-check:checked').length;
    var $bar = $('#bulkUserActionBar');
    if (n > 0) {
        $('#bulkUserCount').text(toBnNum(n));
        $bar.attr('hidden', false).addClass('is-visible');
    } else {
        $bar.removeClass('is-visible').attr('hidden', true);
    }
}

// Row checkbox toggle (delegated so it survives DataTables redraws)
$(document).on('change', '.user-row-check', function () {
    refreshBulkBar();
    var $all = $('.user-row-check');
    var $checked = $('.user-row-check:checked');
    $('#selectAllUsers')
        .prop('checked', $all.length > 0 && $all.length === $checked.length)
        .prop('indeterminate', $checked.length > 0 && $checked.length < $all.length);
});

// Header "select all" — only affects the visible page
$(document).on('change', '#selectAllUsers', function () {
    $('.user-row-check').prop('checked', this.checked);
    refreshBulkBar();
});

// "Cancel selection"
$(document).on('click', '#bulkClearBtn', function () {
    $('.user-row-check, #selectAllUsers').prop('checked', false).prop('indeterminate', false);
    refreshBulkBar();
});

// "Bulk delete"
$(document).on('click', '#bulkDeleteBtn', function () {
    var ids = $('.user-row-check:checked').map(function () { return $(this).val(); }).get();
    if (!ids.length) return;
    Swal.fire({
        title: 'নিশ্চিত করুন',
        html: '<b>' + toBnNum(ids.length) + '</b> জন ব্যবহারকারী মুছে ফেলা হবে। এটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না।',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন',
        cancelButtonText: 'বাতিল',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
    }).then(function (r) {
        if (!r.isConfirmed) return;
        $.ajax({
            type: 'POST',
            url: '../../api/users/bulk-delete.php',
            data: { dataIDs: ids },
            dataType: 'json',
            success: function (resp) {
                var deleted = (resp && resp.deleted) || 0;
                var skipped = (resp && resp.skipped) || 0;
                var msg = toBnNum(deleted) + ' জন মুছে ফেলা হয়েছে';
                if (skipped) msg += ', ' + toBnNum(skipped) + ' জন বাদ পড়েছে';
                Swal.fire({
                    title: (resp && resp.status === 1) ? 'সম্পন্ন' : 'আংশিক সম্পন্ন',
                    html: msg + (resp && resp.errors && resp.errors.length ? '<br><small class="text-muted">' + resp.errors.join('<br>') + '</small>' : ''),
                    icon: (resp && resp.status === 1 && deleted > 0) ? 'success' : 'warning',
                    confirmButtonColor: '#6c5ce7',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                $('.user-row-check, #selectAllUsers').prop('checked', false).prop('indeterminate', false);
                refreshBulkBar();
                if (userListDt) userListDt.ajax.reload(null, false);
            },
            error: function () {
                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'সার্ভার সংযোগ ব্যর্থ',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
});

$(document).ready(function() {
    if ($('#userListTable').length) initUserListTable();
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }
});

document.addEventListener('turbo:load', function() {
    if ($('#userListTable').length) initUserListTable();
});

function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই ব্যবহারকারী মুছে ফেলতে চান? এটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না।",
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
            url: '../../api/users/delete.php',
            data: { dataID: dataID },
            success: function(resp) {
                if ($.trim(resp) === '1') {
                    $('#userListTable').DataTable().ajax.reload();
                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে',
                        text: 'ব্যবহারকারী সফলভাবে মুছে ফেলা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি',
                        text: 'ব্যবহারকারী মুছে ফেলতে ব্যর্থ হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'ব্যবহারকারী মুছে ফেলতে ব্যর্থ হয়েছে',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
}

// Unlock a locked account (super admin only; the button is only rendered for them)
function unlockUser(dataID) {
    Swal.fire({
        title: 'অ্যাকাউন্ট আনলক?',
        text: "ব্যবহারকারী আবার লগইন করতে পারবেন। ভুল চেষ্টার কাউন্টার শূন্য করা হবে।",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4338ca',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, আনলক করুন',
        cancelButtonText: 'বাতিল',
        customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            type: 'post',
            url: '../../api/users/unlock.php',
            data: { dataID: dataID },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.status === 1) {
                    $('#userListTable').DataTable().ajax.reload(null, false);
                    Swal.fire({
                        title: 'সফল', text: resp.message, icon: 'success',
                        confirmButtonColor: '#4338ca',
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি', text: (resp && resp.message) || 'আনলক ব্যর্থ',
                        icon: 'error', confirmButtonColor: '#dc3545',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ',
                    icon: 'error', confirmButtonColor: '#dc3545',
                    customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                });
            }
        });
    });
}
</script>

<style>
/* Lock badge on user list */
.user-lock-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    margin-left: 6px;
    vertical-align: middle;
}
.user-lock-badge i { font-size: 0.85rem; }

/* Unlock action button */
.action-icon.icon-unlock {
    background: #eef2ff;
    color: #4338ca;
}
.action-icon.icon-unlock:hover { background: #4338ca; color: #fff; }
</style>
