<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Re-query full user (header overwrites $getUserInfoQRW with only 3 cols)
$_stmt = mysqli_prepare($con,
    "SELECT user_id, full_name, employee_id, isCenterAdmin, dashboardType, user_type,
            user_group_id, organization_id
     FROM user_list WHERE user_id = ?");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_stmt, 's', $_un);
mysqli_stmt_execute($_stmt);
$_full = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt)) ?: [];
mysqli_stmt_close($_stmt);
$getUserInfoQRW = array_merge($getUserInfoQRW ?? [], $_full);

$signatoryEmpId = (int)($getUserInfoQRW['employee_id'] ?? 0);

// Pending count — "current signatory" filter (mirrors API)
$pendingCount = 0;
if ($signatoryEmpId > 0) {
    $cntStmt = mysqli_prepare($con,
        "SELECT COUNT(*) AS c
         FROM leave_edit_data_for_approval ldfa
         INNER JOIN leave_edit_data led ON led.dataID = ldfa.editRequestID
         WHERE ldfa.signatory   = ?
           AND ldfa.isApproved  = 0
           AND led.status       = 0
           AND NOT EXISTS (
               SELECT 1 FROM leave_edit_data_for_approval prev
               WHERE prev.editRequestID = ldfa.editRequestID
                 AND prev.serial        < ldfa.serial
                 AND prev.isApproved    = 0
           )");
    mysqli_stmt_bind_param($cntStmt, 'i', $signatoryEmpId);
    mysqli_stmt_execute($cntStmt);
    $pendingCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cntStmt))['c'] ?? 0);
    mysqli_stmt_close($cntStmt);
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-edit me-2 text-primary"></i>ছুটি সংশোধন অনুমোদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="stat-card stat-pending"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="আপনার অনুমোদনের অপেক্ষায় থাকা সংশোধন প্রস্তাব">
            <div class="stat-icon"><i class="ti tabler-edit-circle"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($pendingCount); ?></div>
                <div class="stat-label">অনুমোদনের অপেক্ষায়</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="leaveEditApprovalTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>ক্রমিক</th>
                        <th>কর্মচারী</th>
                        <th>বর্তমান অনুমোদিত ছুটি</th>
                        <th>প্রস্তাবিত সংশোধন</th>
                        <th>সংশোধনের কারণ</th>
                        <th class="text-center">কার্যাবলী</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script type="text/javascript">
(function() {
    function initTable() {
        if (typeof $ === 'undefined' || !$.fn.DataTable) { setTimeout(initTable, 100); return; }

        var dt = $('#leaveEditApprovalTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            ajax: { url: "../../api/leave/fetch-edit-approval.php", type: "POST" },
            columns: [
                { data: "serial",          orderable: false },
                { data: "employee_cell",   orderable: false },
                { data: "approved_leave",  orderable: false },
                { data: "proposed_leave",  orderable: false },
                { data: "admin_note",      orderable: false },
                { data: "action",          orderable: false, searchable: false }
            ],
            order: [[0, 'desc']],
            createdRow: function(row) {
                var labels = ['ক্রমিক', 'কর্মচারী', 'বর্তমান অনুমোদিত ছুটি', 'প্রস্তাবিত সংশোধন', 'সংশোধনের কারণ', 'কার্যাবলী'];
                var compact = [0, 5];
                $(row).find('td').each(function(i){
                    var $td = $(this);
                    $td.attr('data-label', labels[i] || '');
                    if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
                    if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
                });
            },
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>',
                search: "",
                searchPlaceholder: "খুঁজুন...",
                lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
                info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
                infoEmpty: "কোন এন্ট্রি নেই",
                infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
                emptyTable: '<div class="empty-state-rich"><i class="ti tabler-clipboard-off"></i><div class="empty-title">কোন সংশোধন নেই</div><div class="empty-subtitle">এই মুহূর্তে কোনো ছুটি সংশোধন আপনার অনুমোদনের অপেক্ষায় নেই</div></div>',
                paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
            },
            drawCallback: function() {
                $('[data-bs-toggle="tooltip"]').each(function(){
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) bootstrap.Tooltip.getOrCreateInstance(this);
                });
            }
        });

        window._editApprovalDT = dt;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTable);
    } else {
        initTable();
    }
})();
</script>
