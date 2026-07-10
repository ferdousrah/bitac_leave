<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$incrementYear = date('Y');

$soption = 1;
if (isset($_POST['submit']) && isset($_POST['soption'])) {
    $soption = (int)$_POST['soption'];
} elseif (isset($_GET['soption'])) {
    $soption = (int)$_GET['soption'];
}

$categoryMap = [
    1 => ['চাকরিরত কর্মচারীগণ', 'ti-users'],
    2 => ['পি আর এল',          'ti-user-clock'],
    3 => ['আঞ্চলিক কেন্দ্র',     'ti-building-bank'],
];

// Map category → WHERE clause
if ($soption === 2) {
    $whereSql = "e.employment_status = 2";
} elseif ($soption === 3) {
    $whereSql = "e.organization_id IN (6, 7, 8, 9)";
} else {
    $soption = 1;
    $whereSql = "e.employment_status = 1 AND e.organization_id IN (4, 5)";
}
$title = $categoryMap[$soption][0];

// ── Single batched query: employees + designation + section + organization
//    + this-year increment + present grade + new grade
//    + has-request flag + has-approval flag.
// Replaces ~9 sub-queries per row (N+1) with one JOIN.
$year = (int)$incrementYear;
$mainSql = "
    SELECT
        e.id, e.employee_name, e.employee_id, e.photo, e.designation, e.section_id,
        e.organization_id, e.basic_salary, e.display_order,
        jt.job_title_name,
        s.section_name,
        o.organization_name,
        ysi.presentSalary, ysi.incrementAmount, ysi.incrementSalary,
        ysi.presentSalaryGrade, ysi.incrementSalaryGrade,
        pg.minimum_salary AS present_grade_min, pg.maximum_salary AS present_grade_max,
        ng.minimum_salary AS new_grade_min,     ng.maximum_salary AS new_grade_max,
        (req.req_cnt IS NOT NULL AND req.req_cnt > 0) AS has_request,
        (apr.apr_cnt IS NOT NULL AND apr.apr_cnt > 0) AS has_approval
    FROM employee_list e
    LEFT JOIN job_title jt    ON jt.id = e.designation
    LEFT JOIN sections s      ON s.id  = e.section_id
    LEFT JOIN organization o  ON o.id  = e.organization_id
    LEFT JOIN yearly_salary_increment ysi
           ON ysi.employeeID = e.id AND ysi.incrementYear = $year
    LEFT JOIN grade pg ON pg.id = ysi.presentSalaryGrade
    LEFT JOIN grade ng ON ng.id = ysi.incrementSalaryGrade
    LEFT JOIN (
        SELECT employeeID, COUNT(*) AS req_cnt
        FROM increment_data_update_permission
        WHERE incrementYear = $year
        GROUP BY employeeID
    ) req ON req.employeeID = e.id
    LEFT JOIN (
        SELECT employeeID, COUNT(*) AS apr_cnt
        FROM increment_data_for_approval
        WHERE incrementYear = $year
        GROUP BY employeeID
    ) apr ON apr.employeeID = e.id
    WHERE $whereSql
    ORDER BY e.display_order ASC
";
$mainRes = mysqli_query($con, $mainSql);

$rows = [];
$totalCount = 0;
$pendingReqCount = 0;
$approvedCount   = 0;
while ($mainRes && $r = mysqli_fetch_assoc($mainRes)) {
    $rows[] = $r;
    $totalCount++;
    $hasReq = (int)$r['has_request'];
    $hasApr = (int)$r['has_approval'];
    if ($hasApr)            $approvedCount++;
    elseif ($hasReq)        $pendingReqCount++;
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-coins me-2 text-primary"></i>বেতন বৃদ্ধি পরিচালনা</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Stats Strip -->
<div class="row stats-strip mb-3 g-2">
    <div class="col-12 col-md-4">
        <div class="stat-card stat-info stat-clickable is-active" data-row-filter=""
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="সকল কর্মচারী দেখান">
            <div class="stat-icon"><i class="ti tabler-users"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($totalCount); ?></div>
                <div class="stat-label"><?php echo htmlspecialchars($title); ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card stat-pending stat-clickable" data-row-filter="is-pending-change"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="পরিবর্তনের অনুরোধ অপেক্ষমান (এই কার্ডে ক্লিক করুন ফিল্টার করতে)">
            <div class="stat-icon"><i class="ti tabler-edit-circle"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($pendingReqCount); ?></div>
                <div class="stat-label">পরিবর্তনের অনুরোধ</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card stat-approved stat-clickable" data-row-filter="is-approved"
             data-bs-toggle="tooltip" data-bs-placement="top"
             title="অনুমোদনের জন্য পাঠানো হয়েছে (এই কার্ডে ক্লিক করুন ফিল্টার করতে)">
            <div class="stat-icon"><i class="ti tabler-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($approvedCount); ?></div>
                <div class="stat-label">অনুমোদিত</div>
            </div>
        </div>
    </div>
</div>

<!-- Category tabs -->
<ul class="nav custom-leave-tabs px-3 pt-3 mb-3" role="tablist">
    <?php foreach ($categoryMap as $k => $info): ?>
    <li class="nav-item">
        <a href="?menuslug=manage-salary-increment&soption=<?= $k ?>"
           class="nav-link <?= ($soption === $k) ? 'active' : '' ?>"
           data-turbo="true">
            <i class="ti <?= $info[1] ?> me-2"></i>
            <span><?= htmlspecialchars($info[0]) ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <form name="form" id="form" enctype="multipart/form-data">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="text-muted small">
                    <i class="ti tabler-info-circle me-1"></i>চেকবক্স দিয়ে কর্মচারী বাছাই করে অনুমোদনের জন্য পাঠান
                </div>
                <button type="submit" name="submit" id="submit" class="btn btn-success">
                    <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান
                </button>
            </div>

            <div class="table-responsive">
                <table id="incrementManageTable" class="table modern-leave-table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th class="col-checkbox text-center"><input type="checkbox" id="select_all" class="form-check-input" title="সকল নির্বাচন"></th>
                            <th>কর্মচারী</th>
                            <th>শাখা ও কেন্দ্র</th>
                            <th>স্টেটাস</th>
                            <th class="text-center">বর্তমান গ্রেড / বেতন</th>
                            <th class="text-center">নতুন গ্রেড / বৃদ্ধি / নতুন বেতন</th>
                            <th class="text-center">কার্যক্রম</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sl = 0;
                    foreach ($rows as $empRow):
                        $sl++;
                        $empId    = (int)$empRow['id'];
                        $hasReq   = (int)$empRow['has_request'];
                        $hasApr   = (int)$empRow['has_approval'];

                        // Avatar cell
                        $empName  = trim($empRow['employee_name'] ?? '');
                        $empJob   = trim($empRow['job_title_name'] ?? '');
                        $empPhoto = trim($empRow['photo'] ?? '');
                        $empCode  = trim($empRow['employee_id'] ?? '');
                        $initials = mb_substr($empName, 0, 1, 'UTF-8');
                        $parts = preg_split('/\s+/u', $empName);
                        if (count($parts) > 1) {
                            $initials = mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr(end($parts), 0, 1, 'UTF-8');
                        }
                        if (!empty($empPhoto)) {
                            $photoUrl = BASE_URL . '/uploads/' . htmlspecialchars($empPhoto);
                            $avatarHtml = '<div class="emp-avatar"><img src="' . $photoUrl . '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="emp-avatar-fallback" style="display:none;">' . htmlspecialchars($initials) . '</span></div>';
                        } else {
                            $avatarHtml = '<div class="emp-avatar"><span class="emp-avatar-fallback">' . htmlspecialchars($initials) . '</span></div>';
                        }

                        // Status pill
                        if ($hasApr) {
                            $statusPill = '<span class="status-pill status-approved"><i class="ti tabler-check me-1"></i>অনুমোদনে পাঠানো</span>';
                            $rowClass = 'is-approved';
                        } elseif ($hasReq) {
                            $statusPill = '<span class="status-pill status-pending"><i class="ti tabler-edit me-1"></i>পরিবর্তনের অনুরোধ</span>';
                            $rowClass = 'is-pending-change';
                        } else {
                            $statusPill = '<span class="text-muted small">—</span>';
                            $rowClass = '';
                        }

                        $presentGradeStr = ($empRow['present_grade_min'] !== null)
                            ? banglaNumber($empRow['present_grade_min']) . '–' . banglaNumber($empRow['present_grade_max'])
                            : '—';
                        $newGradeStr = ($empRow['new_grade_min'] !== null)
                            ? banglaNumber($empRow['new_grade_min']) . '–' . banglaNumber($empRow['new_grade_max'])
                            : '—';
                    ?>
                    <tr id="tr_<?php echo $sl; ?>" class="<?= $rowClass ?>">
                        <td class="col-checkbox text-center">
                            <input type="checkbox" class="checkbox form-check-input" name="listedemp[]" value="<?php echo $empId; ?>" />
                        </td>
                        <td>
                            <div class="emp-cell">
                                <?php echo $avatarHtml; ?>
                                <div class="emp-meta">
                                    <div class="emp-name"><?php echo htmlspecialchars($empName); ?><?php if ($empCode): ?> <span class="emp-sub-light">(<?php echo banglaNumber($empCode); ?>)</span><?php endif; ?></div>
                                    <?php if ($empJob): ?><div class="emp-sub"><?php echo htmlspecialchars($empJob); ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($empRow['section_name'])): ?>
                            <span class="meta-chip section"><i class="ti tabler-building"></i><?php echo htmlspecialchars($empRow['section_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($empRow['organization_name'])): ?>
                            <br><span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i><?php echo htmlspecialchars($empRow['organization_name']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $statusPill; ?></td>
                        <td class="text-center">
                            <div class="grade-cell">
                                <span class="grade-chip"><?php echo $presentGradeStr; ?></span>
                                <div class="salary-amount mt-1"><?php echo banglaNumber(number_format((float)($empRow['presentSalary'] ?? 0), 0)); ?> ৳</div>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="grade-cell">
                                <span class="grade-chip grade-chip-new"><?php echo $newGradeStr; ?></span>
                                <div class="leave-meta mt-1 justify-content-center">
                                    <span class="days-pill days-pill-success">+<?php echo banglaNumber(number_format((float)($empRow['incrementAmount'] ?? 0), 0)); ?></span>
                                </div>
                                <div class="salary-new mt-1"><?php echo banglaNumber(number_format((float)($empRow['incrementSalary'] ?? 0), 0)); ?> ৳</div>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="action-group">
                                <a href="copyto-form.php?menuslug=manage-salary-increment&employeeID=<?php echo $empId; ?>" data-turbo="true" class="action-icon icon-attach" data-bs-toggle="tooltip" title="অনুলিপি সেটিংস">
                                    <i class="ti tabler-copy"></i>
                                </a>
                                <a href="confirm.php?menuslug=manage-salary-increment&employeeID=<?php echo $empId; ?>" data-turbo="true" class="action-icon icon-view" data-bs-toggle="tooltip" title="সম্পাদনা করুন">
                                    <i class="ti tabler-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalCount === 0): ?>
            <div class="empty-state-rich py-5">
                <i class="ti tabler-users-group"></i>
                <div class="empty-title">কোন কর্মচারী পাওয়া যায়নি</div>
                <div class="empty-subtitle">এই বিভাগে কোনো কর্মচারীর রেকর্ড নেই</div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
.salary-amount {
    font-family: var(--bs-font-monospace, monospace);
    font-size: 0.85rem;
    color: #4a4f6f;
    font-weight: 500;
}
.salary-new {
    font-family: var(--bs-font-monospace, monospace);
    font-size: 0.92rem;
    color: #1a7e44;
    font-weight: 700;
}
.grade-chip {
    display: inline-block;
    background: #f3f4fa;
    color: #5d6580;
    padding: 3px 10px;
    border-radius: 0.4rem;
    font-size: 0.78rem;
    font-weight: 500;
    border: 1px solid #e7e9f0;
    font-family: var(--bs-font-monospace, monospace);
}
.grade-chip-new {
    background: #efeaff;
    color: #5648c4;
    border-color: #ddd5f6;
}
.grade-cell {
    line-height: 1.4;
}
/* Soft row tinting based on status (replaces the harsh full-row backgrounds) */
.modern-leave-table tbody tr.is-pending-change td {
    background: rgba(184, 101, 26, 0.04);
}
.modern-leave-table tbody tr.is-approved td {
    background: rgba(26, 126, 68, 0.04);
}
.custom-leave-tabs .nav-link { text-decoration: none; }
</style>

<script type="text/javascript">
$(document).ready(function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    // Wrap table in DataTables for client-side pagination + search + sort.
    // Data is server-rendered (preloaded), so no AJAX delay — DataTables just
    // paginates the existing DOM rows.
    var $table = $('#incrementManageTable');
    var manageDT = null;
    if ($table.length && $table.find('tbody tr').length) {
        manageDT = $table.DataTable({
            responsive: false,
            autoWidth: false,
            paging: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
            order: [],
            columnDefs: [
                { targets: [0, 6], orderable: false, searchable: false } // checkbox + actions
            ],
            createdRow: function(row) {
                var labels = ['', 'কর্মচারী', 'শাখা ও কেন্দ্র', 'স্টেটাস', 'বর্তমান গ্রেড / বেতন', 'নতুন গ্রেড / বৃদ্ধি / নতুন বেতন', 'কার্যক্রম'];
                var compact = [3, 4, 5, 6];
                $(row).find('td').each(function(i){
                    var $td = $(this);
                    if (labels[i]) $td.attr('data-label', labels[i]);
                    if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
                });
            },
            language: {
                search: "",
                searchPlaceholder: "নাম, পদবি, আইডি দিয়ে খুঁজুন...",
                lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
                info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
                infoEmpty: "কোন এন্ট্রি নেই",
                infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
                emptyTable: "টেবিলে কোন ডেটা নেই",
                paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
            }
        });
    }
    // expose for filter handlers below
    window._incrementManageDT = manageDT;

    // Stat-card click → filter rows by status (uses DataTables custom search if available)
    var _statusFilter = '';
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, rowData, rowNode) {
        if (settings.nTable.id !== 'incrementManageTable') return true;
        if (!_statusFilter) return true;
        return $(rowNode).hasClass(_statusFilter);
    });

    $('.stat-clickable').on('click', function() {
        _statusFilter = $(this).data('row-filter') || '';
        $('.stat-clickable').removeClass('is-active');
        $(this).addClass('is-active');
        if (window._incrementManageDT) {
            window._incrementManageDT.draw();
        } else {
            // Fallback if DataTables didn't init (empty table)
            $('#incrementManageTable tbody tr').each(function() {
                $(this).toggle(!_statusFilter || $(this).hasClass(_statusFilter));
            });
        }
        $('#form .checkbox').prop('checked', false);
        $('#select_all').prop('checked', false).prop('indeterminate', false);
    });

    // Select-all (delegated for Turbo + scoped to this form)
    $(document).off('click.selall').on('click.selall', '#select_all', function() {
        var checked = this.checked;
        $('#form .checkbox').each(function() { this.checked = checked; });
    });
    $(document).off('change.rowchk').on('change.rowchk', '#form .checkbox', function() {
        var $checks = $('#form .checkbox');
        var total = $checks.length;
        var checked = $checks.filter(':checked').length;
        $('#select_all').prop('checked', total > 0 && total === checked)
                        .prop('indeterminate', checked > 0 && checked < total);
    });

    // Bulk submit
    $('#form').on('submit', function(e) {
        e.preventDefault();
        if ($('.checkbox:checked').length === 0) {
            Swal.fire({
                title: 'সতর্কতা', text: 'অনুগ্রহ করে অন্তত একজন কর্মচারী নির্বাচন করুন', icon: 'warning',
                confirmButtonColor: '#ff9f43',
                customClass: { confirmButton: 'btn btn-warning' },
                buttonsStyling: false
            });
            return false;
        }
        var $sub = $('#submit');
        $.ajax({
            url: '../../api/salary-increment/submit-for-approval.php',
            type: 'POST',
            dataType: 'json',
            data: new FormData(this),
            processData: false,
            contentType: false,
            beforeSend: function() {
                $sub.attr('disabled', 'disabled').html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>প্রক্রিয়াকরণ হচ্ছে...');
                Swal.fire({ title: 'অপেক্ষা করুন', text: 'প্রক্রিয়াকরণ হচ্ছে...', allowOutsideClick: false, allowEscapeKey: false, didOpen: function() { Swal.showLoading(); } });
            },
            success: function(response) {
                if (response.status == 1) {
                    Swal.fire({
                        title: 'সম্পন্ন', text: response.message, icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(function() { location.reload(); });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি', text: response.message, icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
                $sub.removeAttr('disabled').html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
            },
            error: function() {
                Swal.fire({
                    title: 'ত্রুটি', text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।', icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
                $sub.removeAttr('disabled').html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
            }
        });
    });
});
</script>
