<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$getLeaveTypesQ = mysqli_query($con, "SELECT * FROM leave_types ORDER BY leaveTitle");
$rows = [];
while ($getLeaveTypesQ && $r = mysqli_fetch_assoc($getLeaveTypesQ)) $rows[] = $r;
$totalCount = count($rows);

// Color/icon map by leaveID — matches the leave-type-tag scheme used elsewhere
$leaveTypeStyle = [
    1  => ['leave-type-primary', 'tabler-cash'],
    2  => ['leave-type-info',    'tabler-cash-banknote'],
    3  => ['leave-type-success', 'tabler-coffee'],
    4  => ['leave-type-warning', 'tabler-cash-off'],
    5  => ['leave-type-purple',  'tabler-flag'],
    6  => ['leave-type-default', 'tabler-shield'],
    7  => ['leave-type-default', 'tabler-baby-bottle'],
    8  => ['leave-type-default', 'tabler-wheelchair'],
    9  => ['leave-type-default', 'tabler-school'],
    10 => ['leave-type-warning', 'tabler-alert-triangle'],
];
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-list-details me-2 text-primary"></i>ছুটির প্রকারভেদ</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="new.php?menuslug=<?php echo htmlspecialchars($_GET['menuslug'] ?? 'leave-types'); ?>" class="btn btn-primary" data-turbo="true">
            <i class="ti tabler-plus me-1"></i>নতুন যোগ করুন
        </a>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card stat-info"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="মোট ছুটির প্রকারভেদের সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-list-details"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($totalCount); ?></div>
                <div class="stat-label">মোট প্রকার</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="leaveTypesTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:80px;">ক্রমিক</th>
                        <th>ছুটির নাম</th>
                        <th class="text-center" style="width:130px;">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sl = 0; foreach ($rows as $dataRow):
                        $sl++;
                        $lid = (int)$dataRow['leaveID'];
                        $style = $leaveTypeStyle[$lid] ?? ['leave-type-default', 'tabler-list-check'];
                    ?>
                    <tr id="tr_<?php echo $sl; ?>">
                        <td><span class="serial-num"><?php echo $sl; ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="leave-type-icon-tile leave-type-icon-<?php echo $style[0]; ?>">
                                    <i class="ti <?php echo $style[1]; ?>"></i>
                                </span>
                                <span class="center-name"><?php echo htmlspecialchars($dataRow['leaveTitle']); ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="action-group">
                                <a data-turbo="true" data-bs-toggle="tooltip" data-bs-placement="top" title="সম্পাদনা" href="edit.php?dataID=<?php echo $lid; ?>&menuslug=<?php echo htmlspecialchars($_GET['menuslug'] ?? 'leave-types'); ?>" class="action-icon icon-view">
                                    <i class="ti tabler-edit"></i>
                                </a>
                                <button type="button" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন" onclick="removeData(<?php echo $sl; ?>,<?php echo $lid; ?>)" class="action-icon icon-reject">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
.leave-type-icon-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.leave-type-icon-leave-type-primary { background: #efeaff; color: #5648c4; }
.leave-type-icon-leave-type-info    { background: #e3f1fb; color: #1a6ea8; }
.leave-type-icon-leave-type-success { background: #e6f7ee; color: #1a7e44; }
.leave-type-icon-leave-type-warning { background: #fff3e1; color: #b8651a; }
.leave-type-icon-leave-type-purple  { background: #f5e9ff; color: #7c3aed; }
.leave-type-icon-leave-type-default { background: #f3f4fa; color: #5d6580; }
/* Leave-type name — lighter weight than the standard .center-name */
#leaveTypesTable .center-name {
    font-weight: 500;
    color: #2c2e3a;
    font-size: 0.9rem;
}
</style>

<script type="text/javascript">
$(document).ready(function() {
    $('#leaveTypesTable').DataTable({
        responsive: false,
        autoWidth: false,
        order: [[1, 'asc']],
        columnDefs: [
            { targets: [0, 2], orderable: false, searchable: false }
        ],
        createdRow: function(row) {
            var labels = ['ক্রমিক', 'ছুটির নাম', 'কার্যক্রম'];
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
            searchPlaceholder: "ছুটির নাম দিয়ে খুঁজুন...",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: '<div class="empty-state-rich"><i class="ti tabler-list-check"></i><div class="empty-title">কোন প্রকার নেই</div><div class="empty-subtitle">এখনো কোনো ছুটির প্রকার যোগ করা হয়নি</div></div>',
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

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
    }
});

function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই ছুটির প্রকার মুছে ফেলতে চান? এটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না।",
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
            url: '../../api/leave-type/delete.php',
            data: 'dataID=' + dataID,
            dataType: 'json',
            success: function(response) {
                if (response.status == 1) {
                    $("#tr_" + sl).fadeOut(800);
                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে',
                        text: response.message || 'ছুটির প্রকার সফলভাবে মুছে ফেলা হয়েছে',
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
                    text: 'ছুটির প্রকার মুছে ফেলতে ব্যর্থ হয়েছে',
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
