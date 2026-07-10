<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$getModuleListQ = mysqli_query($con, "SELECT * FROM leave_templates ORDER BY dataID DESC");
$templates = [];
while ($r = mysqli_fetch_assoc($getModuleListQ)) $templates[] = $r;

$totalCount     = count($templates);
$applicationCnt = count(array_filter($templates, fn($t) => (int)($t['templateType'] ?? 0) === 1));
$approvalCnt    = count(array_filter($templates, fn($t) => (int)($t['templateType'] ?? 0) === 2));

$typeMap = [
    1 => ['আবেদনপত্র',     'leave-type-info'],
    2 => ['ছুটি অনুমোদন',  'leave-type-success'],
];
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-template me-2 text-primary"></i>ছুটির টেম্পলেট</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="new.php?menuslug=<?php echo htmlspecialchars($_GET['menuslug'] ?? 'manage-leave-template'); ?>" class="btn btn-primary" data-turbo="true">
            <i class="ti tabler-plus me-1"></i>নতুন যোগ করুন
        </a>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-4">
        <div class="stat-card stat-total"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="মোট টেম্পলেটের সংখ্যা">
            <div class="stat-icon"><i class="ti tabler-files"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($totalCount); ?></div>
                <div class="stat-label">মোট টেম্পলেট</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card stat-info"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="ছুটির আবেদনপত্রের জন্য টেম্পলেট">
            <div class="stat-icon"><i class="ti tabler-file-text"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($applicationCnt); ?></div>
                <div class="stat-label">আবেদনপত্র</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card stat-approved"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="ছুটির অনুমোদনের জন্য টেম্পলেট">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($approvalCnt); ?></div>
                <div class="stat-label">ছুটি অনুমোদন</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="leaveTemplateTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>ক্রমিক</th>
                        <th>টেম্পলেট ডাটা</th>
                        <th>টেম্পলেট টাইপ</th>
                        <th class="text-center">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sl = 0;
                    foreach ($templates as $row) {
                        $sl++;
                        $tType    = (int)($row['templateType'] ?? 0);
                        $typePair = $typeMap[$tType] ?? ['—', 'leave-type-default'];
                        $typeChip = '<span class="leave-type-tag ' . $typePair[1] . '">' . htmlspecialchars($typePair[0]) . '</span>';
                        $tData    = trim($row['templateData'] ?? '');
                        $dataHtml = $tData
                            ? '<div class="note-cell"><i class="ti tabler-quote text-muted me-1"></i>' . htmlspecialchars($tData) . '</div>'
                            : '<span class="text-muted small">—</span>';
                    ?>
                    <tr id="tr_<?php echo $sl; ?>">
                        <td><span class="serial-num"><?php echo $sl; ?></span></td>
                        <td><?php echo $dataHtml; ?></td>
                        <td><?php echo $typeChip; ?></td>
                        <td class="text-center">
                            <div class="action-group">
                                <button type="button" class="action-icon icon-reject" data-bs-toggle="tooltip" data-bs-placement="top" title="মুছে ফেলুন" onclick="removeData(<?php echo $sl; ?>, <?php echo (int)$row['dataID']; ?>)">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script type="text/javascript">
$(document).ready(function() {
    $('#leaveTemplateTable').DataTable({
        responsive: false,
        autoWidth: false,
        order: [[0, 'desc']],
        columnDefs: [
            { targets: [0, 3], orderable: false }
        ],
        createdRow: function(row) {
            var labels = ['ক্রমিক', 'টেম্পলেট ডাটা', 'টেম্পলেট টাইপ', 'কার্যক্রম'];
            var compact = [0, 2, 3];
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
            searchPlaceholder: "টেম্পলেট খুঁজুন...",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: '<div class="empty-state-rich"><i class="ti tabler-template-off"></i><div class="empty-title">কোন টেম্পলেট নেই</div><div class="empty-subtitle">এখনো কোনো টেম্পলেট তৈরি করা হয়নি</div></div>',
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
        text: "এই টেম্পলেট মুছে ফেলতে চান? এটি পূর্বাবস্থায় ফিরিয়ে আনা যাবে না।",
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
            url: '../../api/leave-template/delete.php',
            data: 'dataID=' + dataID,
            dataType: 'json',
            success: function(response) {
                if (response.status == 1) {
                    $("#tr_" + sl).fadeOut(800);
                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে',
                        text: response.message || 'টেম্পলেট সফলভাবে মুছে ফেলা হয়েছে',
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
                    text: 'টেম্পলেট মুছে ফেলতে ব্যর্থ হয়েছে',
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
