<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

// Fetch summary counts for the stats strip
$sessionUserID     = $_SESSION['userID'] ?? '';
$sessionEmployeeId = $getUserInfoQRW['employee_id'] ?? '';
$statsSql = "
    SELECT
      COUNT(*)                                         AS total,
      SUM(CASE WHEN la.status = 0 THEN 1 ELSE 0 END)   AS pending,
      SUM(CASE WHEN la.status = 1 THEN 1 ELSE 0 END)   AS approved,
      SUM(CASE WHEN la.status = 2 THEN 1 ELSE 0 END)   AS rejected
    FROM leave_applications la
    WHERE la.status != 3
      AND (la.applicantID = ? OR la.submitBy = ?)";
$_stmt = mysqli_prepare($con, $statsSql);
mysqli_stmt_bind_param($_stmt, 'ss', $sessionEmployeeId, $sessionUserID);
mysqli_stmt_execute($_stmt);
$_stats   = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt)) ?: ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];
mysqli_stmt_close($_stmt);
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-history me-2 text-primary"></i>ছুটির ইতিহাস ও যোগদান</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Stats Strip (clickable → filters) -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-total is-active" data-filter="all" role="button" tabindex="0">
            <div class="stat-icon"><i class="ti tabler-files"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$_stats['total']); ?></div>
                <div class="stat-label">মোট আবেদন</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-pending" data-filter="pending" role="button" tabindex="0">
            <div class="stat-icon"><i class="ti tabler-hourglass"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$_stats['pending']); ?></div>
                <div class="stat-label">অপেক্ষমান</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-approved" data-filter="approved" role="button" tabindex="0">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$_stats['approved']); ?></div>
                <div class="stat-label">অনুমোদিত</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-rejected" data-filter="rejected" role="button" tabindex="0">
            <div class="stat-icon"><i class="ti tabler-circle-x"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber((int)$_stats['rejected']); ?></div>
                <div class="stat-label">অনুমোদিত হয়নি</div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Applications Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-0">
        <!-- Nav Tabs -->
        <ul class="nav custom-leave-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#applicationBased" role="tab">
                    <i class="ti tabler-file-text me-2"></i>
                    <span class="d-none d-sm-inline">আবেদনের ভিত্তিতে</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#officeBased" role="tab">
                    <i class="ti tabler-file-description me-2"></i>
                    <span class="d-none d-sm-inline">অফিস আদেশের ভিত্তিতে</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content p-3">
            <!-- Tab 1: Application Based -->
            <div class="tab-pane fade show active" id="applicationBased" role="tabpanel">
                <div class="filter-chips mb-3">
                    <button type="button" class="filter-chip active" data-filter="all">
                        <i class="ti tabler-list me-1"></i>সকল <span class="chip-count"><?php echo banglaNumber((int)$_stats['total']); ?></span>
                    </button>
                    <button type="button" class="filter-chip" data-filter="pending">
                        <i class="ti tabler-hourglass me-1"></i>অপেক্ষমান <span class="chip-count"><?php echo banglaNumber((int)$_stats['pending']); ?></span>
                    </button>
                    <button type="button" class="filter-chip" data-filter="approved">
                        <i class="ti tabler-circle-check me-1"></i>অনুমোদিত <span class="chip-count"><?php echo banglaNumber((int)$_stats['approved']); ?></span>
                    </button>
                    <button type="button" class="filter-chip" data-filter="rejected">
                        <i class="ti tabler-circle-x me-1"></i>অনুমোদিত হয়নি <span class="chip-count"><?php echo banglaNumber((int)$_stats['rejected']); ?></span>
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="applicationBasedTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>আবেদনকারী</th>
                                <th>চাহিত ছুটি</th>
                                <th>প্রাথমিক অনুমোদিত ছুটি</th>
                                <th>ভোগকৃত ছুটি</th>
                                <th>যোগদানের প্রকার</th>
                                <th>সংশোধিত ছুটি</th>
                                <th>স্টেটাস</th>
                                <th class="text-center">কার্যাবলী</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Office Based -->
            <div class="tab-pane fade" id="officeBased" role="tabpanel">
                <div class="table-responsive">
                    <table id="officeBasedTable" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>ক্রমিক</th>
                                <th>ছুটির ধরণ</th>
                                <th>কর্তন (দিন)</th>
                                <th>নোট</th>
                                <th>Submit Date</th>
                                <th class="text-center">সংযুক্তি</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Let the কার্যাবলী (actions) dropdown escape the wrapper on this page.
   Bootstrap's default overflow-x: auto on .table-responsive clips the menu
   in some browsers. On mobile the table collapses to card view, so we
   don't lose horizontal scrolling. */
#applicationBased .table-responsive,
#officeBased .table-responsive { overflow: visible !important; }

/* Subtle full-grid borders — this page only.
   Apply on the table itself (not the wrapper) so left/right always show
   even if the DataTables responsive/overflow context hides wrapper borders. */
#applicationBasedTable.modern-leave-table,
#officeBasedTable.modern-leave-table {
    border-collapse: separate !important;
    border-spacing: 0;
    margin: 0 !important;
}
/* Rounded corners via corner cells (not overflow:hidden — that clips the
   কার্যাবলী dropdown menu when it opens beyond the table edge) */
#applicationBasedTable.modern-leave-table thead th:first-child,
#officeBasedTable.modern-leave-table thead th:first-child { border-top-left-radius: 8px; }
#applicationBasedTable.modern-leave-table thead th:last-child,
#officeBasedTable.modern-leave-table thead th:last-child { border-top-right-radius: 8px; }
#applicationBasedTable.modern-leave-table tbody tr:last-child td:first-child,
#officeBasedTable.modern-leave-table tbody tr:last-child td:first-child { border-bottom-left-radius: 8px; }
#applicationBasedTable.modern-leave-table tbody tr:last-child td:last-child,
#officeBasedTable.modern-leave-table tbody tr:last-child td:last-child { border-bottom-right-radius: 8px; }
/* Column dividers */
#applicationBasedTable.modern-leave-table thead th + th,
#applicationBasedTable.modern-leave-table tbody td + td,
#officeBasedTable.modern-leave-table thead th + th,
#officeBasedTable.modern-leave-table tbody td + td {
    border-left: 1px solid #eef0f5;
}
/* Outer edges — left/right on all cells, top on thead, bottom on last row */
#applicationBasedTable.modern-leave-table thead th:first-child,
#applicationBasedTable.modern-leave-table tbody td:first-child,
#officeBasedTable.modern-leave-table thead th:first-child,
#officeBasedTable.modern-leave-table tbody td:first-child {
    border-left: 1px solid #e0e4ee;
}
#applicationBasedTable.modern-leave-table thead th:last-child,
#applicationBasedTable.modern-leave-table tbody td:last-child,
#officeBasedTable.modern-leave-table thead th:last-child,
#officeBasedTable.modern-leave-table tbody td:last-child {
    border-right: 1px solid #e0e4ee;
}
#applicationBasedTable.modern-leave-table thead th,
#officeBasedTable.modern-leave-table thead th {
    border-top: 1px solid #e0e4ee !important;
}
#applicationBasedTable.modern-leave-table tbody tr:last-child td,
#officeBasedTable.modern-leave-table tbody tr:last-child td {
    border-bottom: 1px solid #e0e4ee !important;
}
@media (max-width: 767.98px) {
    /* On mobile the table collapses to cards — drop the grid */
    #applicationBasedTable.modern-leave-table,
    #officeBasedTable.modern-leave-table { border-radius: 0; }
    #applicationBasedTable.modern-leave-table tbody td,
    #officeBasedTable.modern-leave-table tbody td {
        border-left: 0 !important; border-right: 0 !important;
    }
}
</style>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>


<script>
var applicationBasedTableInstance;
var officeBasedTableInstance;

$(document).ready(function() {
    // Initialize Application Based DataTable
    var currentStatusFilter = 'all';

    // Mobile card-view labels (must match column order)
    var appColLabels = [
        'ক্রমিক', 'আবেদনকারী', 'চাহিত ছুটি', 'প্রাথমিক অনুমোদিত',
        'ভোগকৃত ছুটি', 'যোগদানের প্রকার', 'সংশোধিত ছুটি', 'স্টেটাস', 'কার্যাবলী'
    ];

    applicationBasedTableInstance = $('#applicationBasedTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        ajax: {
            url: '../../api/leave/fetch-all-applications.php',
            type: 'POST',
            data: function(d) {
                d.statusFilter = currentStatusFilter;
                return d;
            }
        },
        columns: [
            { data: 'serial', orderable: false },
            { data: 'employee_info', orderable: false },
            { data: 'requested_leave', orderable: false },
            { data: 'approved_leave', orderable: false },
            { data: 'spent_leave', orderable: false },
            { data: 'joining_type', orderable: false },
            { data: 'corrected_leave', orderable: false },
            { data: 'status', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        createdRow: function(row, data, dataIndex) {
            // Cells that should render label inline-right rather than label-on-top
            // Indices: 0=ক্রমিক, 5=যোগদানের প্রকার, 7=স্টেটাস, 8=কার্যাবলী
            var compactCols = [0, 5, 7, 8];
            $(row).find('td').each(function(i) {
                var $td = $(this);
                $td.attr('data-label', appColLabels[i] || '');
                if ($.trim($td.text()) === '' && $td.children().length === 0) {
                    $td.addClass('is-empty');
                }
                if (compactCols.indexOf(i) !== -1) {
                    $td.addClass('compact-cell');
                }
            });
        },
        order: [[0, 'desc']],
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
            emptyTable: '<div class="empty-state-rich"><i class="ti tabler-inbox"></i><div class="empty-title">কোন আবেদন পাওয়া যায়নি</div><div class="empty-subtitle">এখনো কোনো ছুটির আবেদন জমা দেওয়া হয়নি</div></div>',
            paginate: {
                first: "প্রথম",
                previous: "পূর্ববর্তী",
                next: "পরবর্তী",
                last: "শেষ"
            }
        }
    });

    // Unified filter sync: chips + stat cards
    function applyStatusFilter(filter) {
        currentStatusFilter = filter;
        $('.filter-chip').removeClass('active');
        $('.filter-chip[data-filter="' + filter + '"]').addClass('active');
        $('.stats-strip .stat-card').removeClass('is-active');
        $('.stats-strip .stat-card[data-filter="' + filter + '"]').addClass('is-active');
        applicationBasedTableInstance.ajax.reload();
    }
    $('.filter-chip').on('click', function() {
        applyStatusFilter($(this).data('filter'));
    });
    $('.stats-strip .stat-card').on('click', function() {
        applyStatusFilter($(this).data('filter'));
    });
    $('.stats-strip .stat-card').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            applyStatusFilter($(this).data('filter'));
        }
    });

    // Initialize Office Based DataTable (lazy load on tab switch)
    $('button[data-bs-target="#officeBased"]').on('shown.bs.tab', function (e) {
        if (!officeBasedTableInstance) {
            officeBasedTableInstance = $('#officeBasedTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                autoWidth: false,
                ajax: {
                    url: '../../api/leave/fetch-deduction-history.php',
                    type: 'POST'
                },
                columns: [
                    { data: 'serial', orderable: false },
                    { data: 'leave_type', orderable: false },
                    { data: 'deduction_days', orderable: false },
                    { data: 'note', orderable: false },
                    { data: 'submit_date' },
                    { data: 'attachment', orderable: false, searchable: false }
                ],
                createdRow: function(row) {
                    var labels = ['ক্রমিক', 'ছুটির ধরণ', 'কর্তন (দিন)', 'নোট', 'Submit Date', 'সংযুক্তি'];
                    var compactCols = [0, 1, 2, 5];
                    $(row).find('td').each(function(i) {
                        var $td = $(this);
                        $td.attr('data-label', labels[i] || '');
                        if ($.trim($td.text()) === '' && $td.children().length === 0) {
                            $td.addClass('is-empty');
                        }
                        if (compactCols.indexOf(i) !== -1) {
                            $td.addClass('compact-cell');
                        }
                    });
                },
                order: [[4, 'desc']],
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
                    emptyTable: '<div class="empty-state-rich"><i class="ti tabler-file-off"></i><div class="empty-title">কোন অফিস আদেশ নেই</div><div class="empty-subtitle">এখনো কোনো ছুটি কর্তনের অফিস আদেশ পাওয়া যায়নি</div></div>',
                    paginate: {
                        first: "প্রথম",
                        previous: "পূর্ববর্তী",
                        next: "পরবর্তী",
                        last: "শেষ"
                    }
                }
            });
        }
    });
});

function cancelApplication(applicationID, rowId){
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই আবেদনটি মুছে ফেলতে চান?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন',
        cancelButtonText: 'বাতিল',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            // AJAX request to cancel leave application
            $.ajax({
                type: 'post',
                url: '../../api/leave/cancel-application.php',
                data: 'applicationID='+ applicationID,
                success: function(data) {
                    if (data == 0) {
                        Swal.fire({
                            title: 'ত্রুটি!',
                            text: 'একটি ত্রুটি হয়েছে',
                            icon: 'error',
                            confirmButtonColor: '#ff3e1d',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });
                    } else {
                        // Reload the DataTable
                        applicationBasedTableInstance.ajax.reload();

                        Swal.fire({
                            title: 'সফল!',
                            text: 'আবেদনটি মুছে ফেলা হয়েছে',
                            icon: 'success',
                            confirmButtonColor: '#6c5ce7',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                error: function(e) {
                    console.log(e);
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'অনুরোধ প্রক্রিয়া করতে ব্যর্থ হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}
</script>
