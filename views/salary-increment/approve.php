<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$incrementYear = (int)date('Y');
$loggedUserID  = (int)($_SESSION['userID'] ?? 0);

// ── Single batched query ──────────────────────────────
// Replaces ~7 sub-queries per row + prev-signatory check with one JOIN.
// The WHERE clause keeps only rows where either there's no prev-signatory
// (prevSignatory = 0) OR the prev-signatory has already approved.
$mainSql = "
    SELECT
        a.dataID,
        a.dataRef,
        a.prevSignatory,
        e.id          AS emp_id,
        e.employee_name, e.employee_id, e.photo, e.basic_salary,
        e.designation, e.section_id, e.organization_id,
        jt.job_title_name,
        s.section_name,
        o.organization_name,
        ysi.dataID                AS ysi_id,
        ysi.presentSalaryGrade,
        ysi.incrementAmount,
        ysi.incrementSalary,
        pg.minimum_salary AS present_grade_min,
        pg.maximum_salary AS present_grade_max
    FROM increment_data_for_approval a
    INNER JOIN employee_list e ON e.id = a.employeeID
    LEFT JOIN job_title jt    ON jt.id = e.designation
    LEFT JOIN sections s      ON s.id  = e.section_id
    LEFT JOIN organization o  ON o.id  = e.organization_id
    LEFT JOIN yearly_salary_increment ysi ON ysi.dataID = a.dataRef
    LEFT JOIN grade pg ON pg.id = ysi.presentSalaryGrade
    LEFT JOIN increment_data_for_approval prev
           ON prev.dataRef    = a.dataRef
          AND prev.signatory  = a.prevSignatory
          AND prev.isApproved = 1
    WHERE a.incrementYear = $incrementYear
      AND a.signatory     = $loggedUserID
      AND a.isApproved    = 0
      AND (a.prevSignatory = 0 OR prev.dataID IS NOT NULL)
    ORDER BY a.dataID DESC
";

$mainRes = mysqli_query($con, $mainSql);
$rows = [];
while ($mainRes && $r = mysqli_fetch_assoc($mainRes)) $rows[] = $r;
$totalPending = count($rows);
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-coin-taka me-2 text-primary"></i>বেতন বৃদ্ধি অনুমোদন</h4>
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
             title="<?php echo banglaNumber($incrementYear); ?> সালের বেতন বৃদ্ধি অনুমোদনের অপেক্ষায়">
            <div class="stat-icon"><i class="ti tabler-clipboard-check"></i></div>
            <div class="stat-body">
                <div class="stat-num"><?php echo banglaNumber($totalPending); ?></div>
                <div class="stat-label">অনুমোদনের অপেক্ষায়</div>
            </div>
        </div>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body p-3">
        <div class="statusMsg" style="display:none;"></div>

        <form name="form" id="form" enctype="multipart/form-data">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="text-muted small">
                    <i class="ti tabler-info-circle me-1"></i>চেকবক্স দিয়ে নির্বাচন করে একাধিক একসাথে অনুমোদন করুন
                </div>
                <button type="submit" name="submit" id="submit" class="btn btn-success submitBtn">
                    <i class="ti tabler-checks me-1"></i>একাধিক অনুমোদন
                </button>
            </div>

            <div class="table-responsive">
                <table id="approveIncrementTable" class="table modern-leave-table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th class="col-checkbox text-center"><input type="checkbox" id="select_all" class="form-check-input" title="সকল নির্বাচন"></th>
                            <th>কর্মচারী</th>
                            <th>শাখা ও কেন্দ্র</th>
                            <th class="text-center">বর্তমান গ্রেড / বেতন</th>
                            <th class="text-center">বৃদ্ধি / নতুন বেতন</th>
                            <th class="text-center">কার্যক্রম</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sl = 0;
                    foreach ($rows as $r):
                        $sl++;
                        $empId    = (int)$r['emp_id'];
                        $empName  = trim($r['employee_name'] ?? '');
                        $empJob   = trim($r['job_title_name'] ?? '');
                        $empPhoto = trim($r['photo'] ?? '');
                        $empCode  = trim($r['employee_id'] ?? '');
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
                        $presentGradeStr = ($r['present_grade_min'] !== null)
                            ? banglaNumber($r['present_grade_min']) . '–' . banglaNumber($r['present_grade_max'])
                            : '—';
                    ?>
                    <tr id="tr_<?php echo $sl; ?>">
                        <td class="col-checkbox text-center">
                            <input type="checkbox" class="checkbox form-check-input" name="listedemp[]" value="<?php echo (int)$r['dataID']; ?>" />
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
                            <?php if (!empty($r['section_name'])): ?>
                            <span class="meta-chip section"><i class="ti tabler-building"></i><?php echo htmlspecialchars($r['section_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($r['organization_name'])): ?>
                            <br><span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i><?php echo htmlspecialchars($r['organization_name']); ?></span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <div class="grade-cell">
                                <span class="grade-chip"><?php echo $presentGradeStr; ?></span>
                                <div class="salary-amount mt-1"><?php echo banglaNumber(number_format((float)($r['basic_salary'] ?? 0), 0)); ?> ৳</div>
                            </div>
                        </td>

                        <td class="text-center">
                            <div class="grade-cell">
                                <div class="leave-meta justify-content-center">
                                    <span class="days-pill days-pill-success">+<?php echo banglaNumber(number_format((float)($r['incrementAmount'] ?? 0), 0)); ?></span>
                                </div>
                                <div class="salary-new mt-1"><?php echo banglaNumber(number_format((float)($r['incrementSalary'] ?? 0), 0)); ?> ৳</div>
                            </div>
                        </td>

                        <td class="text-center">
                            <div class="action-group">
                                <a href="../../increment_form_getmethod_result.php?employeeID=<?php echo $empId; ?>" target="_blank" class="action-icon icon-view" data-bs-toggle="tooltip" title="ফরম দেখুন">
                                    <i class="ti tabler-file-text"></i>
                                </a>
                                <button type="button" onclick="confirmAction(<?php echo (int)$r['dataID']; ?>, <?php echo (int)($r['ysi_id'] ?? 0); ?>, 1)" class="action-icon icon-approve" data-bs-toggle="tooltip" title="অনুমোদন করুন">
                                    <i class="ti tabler-check"></i>
                                </button>
                                <button type="button" onclick="confirmAction(<?php echo (int)$r['dataID']; ?>, <?php echo (int)($r['ysi_id'] ?? 0); ?>, 2)" class="action-icon icon-reject" data-bs-toggle="tooltip" title="বাতিল করুন">
                                    <i class="ti tabler-x"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPending === 0): ?>
            <div class="empty-state-rich py-5">
                <i class="ti tabler-info-circle"></i>
                <div class="empty-title">কোন অপেক্ষমান অনুমোদন নেই</div>
                <div class="empty-subtitle"><?php echo banglaNumber($incrementYear); ?> সালের কোনো বেতন বৃদ্ধি আপনার অনুমোদনের অপেক্ষায় নেই</div>
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
.grade-cell { line-height: 1.4; }
</style>

<script type="text/javascript">
$(document).ready(function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    // DataTables wrap (data is preloaded; client-side pagination only)
    var $tbl = $('#approveIncrementTable');
    if ($tbl.length && $tbl.find('tbody tr').length) {
        $tbl.DataTable({
            responsive: false,
            autoWidth: false,
            paging: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
            order: [],
            columnDefs: [
                { targets: [0, 5], orderable: false, searchable: false }
            ],
            createdRow: function(row) {
                var labels = ['', 'কর্মচারী', 'শাখা ও কেন্দ্র', 'বর্তমান গ্রেড / বেতন', 'বৃদ্ধি / নতুন বেতন', 'কার্যক্রম'];
                var compact = [3, 4, 5];
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

    // Select-all (delegated, scoped to form)
    $(document).off('click.selapr').on('click.selapr', '#select_all', function() {
        var checked = this.checked;
        $('#form .checkbox').each(function() { this.checked = checked; });
    });
    $(document).off('change.rowapr').on('change.rowapr', '#form .checkbox', function() {
        var $checks = $('#form .checkbox');
        var total = $checks.length;
        var checked = $checks.filter(':checked').length;
        $('#select_all').prop('checked', total > 0 && total === checked)
                        .prop('indeterminate', checked > 0 && checked < total);
    });

    // Bulk submit
    $(document).off('submit.aprform').on('submit.aprform', '#form', function(e) {
        e.preventDefault();
        if ($('#form .checkbox:checked').length === 0) {
            Swal.fire({
                title: 'সতর্কতা', text: 'অনুগ্রহ করে অন্তত একটি আইটেম নির্বাচন করুন', icon: 'warning',
                confirmButtonColor: '#ff9f43',
                customClass: { confirmButton: 'btn btn-warning' },
                buttonsStyling: false
            });
            return false;
        }
        var $sub = $('#submit');
        $.ajax({
            url: '../../api/salary-increment/approve-multiple.php',
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
                        title: 'ত্রুটি', text: response.message || 'কিছু ভুল হয়েছে', icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
                $sub.removeAttr('disabled').html('<i class="ti tabler-checks me-1"></i>একাধিক অনুমোদন');
            },
            error: function() {
                Swal.fire({
                    title: 'ত্রুটি', text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।', icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
                $sub.removeAttr('disabled').html('<i class="ti tabler-checks me-1"></i>একাধিক অনুমোদন');
            }
        });
    });
});

// Individual approve/reject action
function confirmAction(dataID, dataRef, isApproved) {
    var actionText  = isApproved == 1 ? 'অনুমোদন' : 'বাতিল';
    var confirmColor = isApproved == 1 ? '#1a7e44' : '#dc3545';
    var confirmClass = isApproved == 1 ? 'btn btn-success me-3' : 'btn btn-danger me-3';

    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: 'এই বেতন বৃদ্ধি ' + actionText + ' করতে চান?',
        icon: isApproved == 1 ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, ' + actionText,
        cancelButtonText: 'বাতিল',
        customClass: { confirmButton: confirmClass, cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
    }).then(function(result) {
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'অপেক্ষা করুন', text: 'প্রক্রিয়াকরণ হচ্ছে...', allowOutsideClick: false, allowEscapeKey: false, didOpen: function() { Swal.showLoading(); } });
        $.ajax({
            url: '../../api/salary-increment/approve-single.php',
            type: 'POST',
            dataType: 'json',
            data: { dataID: dataID, dataRef: dataRef, isApproved: isApproved },
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
                        title: 'ত্রুটি', text: response.message || 'কিছু ভুল হয়েছে', icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'ত্রুটি', text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।', icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
}
</script>
