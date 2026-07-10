<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Total user groups count for stats
$totalGroups = (int)(mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COUNT(*) AS c FROM user_group"))['c'] ?? 0);
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-users-group me-2 text-primary"></i>ব্যবহারকারী গ্রুপের তালিকা</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="new-group.php?menuslug=<?php echo htmlspecialchars($_GET['menuslug'] ?? 'manage-user-group'); ?>" class="btn btn-primary" data-turbo="true">
            <i class="ti tabler-plus me-1"></i>নতুন যোগ করুন
        </a>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card stat-info"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="মোট ব্যবহারকারী গ্রুপের সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-users-group"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($totalGroups); ?></div>
                <div class="stat-label">মোট গ্রুপ</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="userGroupListTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:80px;">ক্রমিক</th>
                        <th>গ্রুপের নাম</th>
                        <th class="text-center" style="width:170px;">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
.group-icon-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #e3f1fb 0%, #c0dff2 100%);
    color: #1a6ea8;
    border-radius: 0.5rem;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.center-name {
    font-weight: 600;
    color: #1a1d2e;
    font-size: 0.95rem;
}
</style>

<script>
function initUserGroupListTable() {
    setTimeout(function() {
        if ($.fn.DataTable.isDataTable('#userGroupListTable')) {
            $('#userGroupListTable').DataTable().destroy();
        }
        const urlParams = new URLSearchParams(window.location.search);
        const menuslug = urlParams.get('menuslug') || 'manage-user-group';

        $('#userGroupListTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            ajax: {
                url: "../../api/users/fetch-groups.php?menuslug=" + menuslug,
                type: "POST"
            },
            columns: [
                { data: "sl",         orderable: false },
                { data: "group_name", orderable: true },
                { data: "action",     orderable: false, searchable: false }
            ],
            order: [[1, 'asc']],
            createdRow: function(row) {
                var labels = ['ক্রমিক', 'গ্রুপের নাম', 'কার্যক্রম'];
                var compact = [0, 2];
                $(row).find('td').each(function(i){
                    var $td = $(this);
                    $td.attr('data-label', labels[i] || '');
                    if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
                    if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
                });
            },
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
                search: "",
                searchPlaceholder: "গ্রুপের নাম দিয়ে খুঁজুন...",
                lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
                info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
                infoEmpty: "কোন এন্ট্রি নেই",
                infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
                emptyTable: '<div class="empty-state-rich"><i class="ti tabler-users-off"></i><div class="empty-title">কোন গ্রুপ নেই</div><div class="empty-subtitle">এখনো কোনো ব্যবহারকারী গ্রুপ যোগ করা হয়নি</div></div>',
                paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
            },
            drawCallback: function() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                        bootstrap.Tooltip.getOrCreateInstance(el);
                    });
                }
            }
        });
    }, 100);
}

$(document).ready(function() {
    if ($('#userGroupListTable').length) initUserGroupListTable();
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }
});

document.addEventListener('turbo:load', function() {
    if ($('#userGroupListTable').length) initUserGroupListTable();
});

function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই গ্রুপ মুছে ফেলতে চান? এটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না।",
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
            url: '../../api/users/delete-group.php',
            data: 'dataID=' + dataID,
            dataType: 'json',
            success: function(response) {
                if (response.status == 1) {
                    $('#userGroupListTable').DataTable().ajax.reload();
                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে',
                        text: response.message || 'গ্রুপ সফলভাবে মুছে ফেলা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি',
                        text: response.message || 'অপারেশন ব্যর্থ হয়েছে',
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
                    text: 'গ্রুপ মুছে ফেলতে ব্যর্থ হয়েছে',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
}
</script>
