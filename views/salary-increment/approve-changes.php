<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

function Bengali_DTN($NRS){
    $englDTN = array('1','2','3','4','5','6','7','8','9','0');
    $bangDTN = array('১','২','৩','৪','৫','৬','৭','৮','৯','০');
    return str_replace($bangDTN, $englDTN, $NRS);
}

$incrementYear = date('Y');
$getAllDataQ = mysqli_query($con, "SELECT * FROM `increment_data_update_permission` WHERE incrementYear='$incrementYear' AND isApproved=0 ORDER BY dataID DESC");

$rows = [];
while ($getAllDataQ && $r = mysqli_fetch_assoc($getAllDataQ)) $rows[] = $r;
$totalPending = count($rows);
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold mb-0"><i class="ti tabler-edit me-2 text-primary"></i>বেতন বৃদ্ধি অনুমোদন</h4>
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
             title="<?php echo banglaNumber($incrementYear); ?> সালের পরিবর্তন অনুমোদনের অপেক্ষায়">
            <div class="stat-icon"><i class="ti tabler-edit-circle"></i></div>
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
        <div class="table-responsive">
            <table id="incrementChangesTable" class="table modern-leave-table align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>ক্রমিক</th>
                        <th>কর্মচারী</th>
                        <th>শাখা ও কেন্দ্র</th>
                        <th class="text-end">বর্তমান বেতন</th>
                        <th class="text-center">বৃদ্ধির হার পরিবর্তন</th>
                        <th class="text-end">নতুন বেতন পরিবর্তন</th>
                        <th>অনুরোধকারী</th>
                        <th class="text-center">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sl = 0;
                    foreach ($rows as $empRow):
                        $sl++;

                        $emp  = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . (int)$empRow['employeeID'] . "'"));
                        $job  = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM job_title WHERE id='" . (int)($emp['designation'] ?? 0) . "'"));
                        $req  = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM user_list WHERE dataID='" . (int)$empRow['submitBy'] . "'"));
                        $incr = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM `yearly_salary_increment` WHERE incrementYear='$incrementYear' AND employeeID='" . (int)$empRow['employeeID'] . "'"));
                        $sec  = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM sections WHERE id='" . (int)($incr['section_id'] ?? 0) . "'"));
                        $org  = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM organization WHERE id='" . (int)($incr['organization_id'] ?? 0) . "'"));

                        // Avatar + name cell
                        $empName  = trim($emp['employee_name'] ?? '');
                        $empJob   = trim($job['job_title_name'] ?? '');
                        $empPhoto = trim($emp['photo'] ?? '');
                        $empCode  = trim($emp['employee_id'] ?? '');
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

                        $rateCurrent = (float)($empRow['salary_increment_rate_current'] ?? 0);
                        $rateEdited  = (float)($empRow['salary_increment_rate_edited'] ?? 0);
                        $basicCurrent = (float)($empRow['salary_increment_basic_current'] ?? 0);
                        $basicEdited  = (float)($empRow['salary_increment_basic_edited'] ?? 0);

                        $rateUp = $rateEdited > $rateCurrent;
                        $basicUp = $basicEdited > $basicCurrent;
                    ?>
                    <tr id="tr_<?php echo $sl; ?>">
                        <td><span class="serial-num"><?php echo $sl; ?></span></td>

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
                            <?php if (!empty($sec['section_name'])): ?>
                            <span class="meta-chip section"><i class="ti tabler-building"></i><?php echo htmlspecialchars($sec['section_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($org['organization_name'])): ?>
                            <br><span class="meta-chip center mt-1"><i class="ti tabler-map-pin"></i><?php echo htmlspecialchars($org['organization_name']); ?></span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end">
                            <span class="salary-amount"><?php echo banglaNumber(number_format((float)($emp['basic_salary'] ?? 0), 0)); ?> ৳</span>
                        </td>

                        <td class="text-center">
                            <div class="change-row">
                                <span class="change-old"><?php echo banglaNumber($rateCurrent); ?>%</span>
                                <i class="ti tabler-arrow-right text-muted change-arrow"></i>
                                <span class="change-new <?php echo $rateUp ? 'change-up' : 'change-down'; ?>"><?php echo banglaNumber($rateEdited); ?>%</span>
                            </div>
                        </td>

                        <td class="text-end">
                            <div class="change-row justify-content-end">
                                <span class="change-old"><?php echo banglaNumber(number_format($basicCurrent, 0)); ?></span>
                                <i class="ti tabler-arrow-right text-muted change-arrow"></i>
                                <span class="change-new <?php echo $basicUp ? 'change-up' : 'change-down'; ?>"><?php echo banglaNumber(number_format($basicEdited, 0)); ?> ৳</span>
                            </div>
                        </td>

                        <td>
                            <div class="emp-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($req['full_name'] ?? '—'); ?></div>
                            <?php if (!empty(trim($empRow['note'] ?? ''))): ?>
                            <div class="note-cell mt-1" style="max-width:240px;font-size:0.78rem;"><i class="ti tabler-message-2 text-muted me-1"></i><?php echo htmlspecialchars($empRow['note']); ?></div>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <div class="action-group">
                                <?php if (!empty($empRow['office_notice'])): ?>
                                <a href="../../uploads/<?php echo htmlspecialchars($empRow['office_notice']); ?>" target="_blank" class="action-icon icon-attach" data-bs-toggle="tooltip" title="অফিস আদেশ দেখুন">
                                    <i class="ti tabler-file-text"></i>
                                </a>
                                <?php endif; ?>
                                <button type="button" onclick="confirmAction(<?php echo (int)$empRow['dataID']; ?>, 1)" class="action-icon icon-approve" data-bs-toggle="tooltip" title="অনুমোদন">
                                    <i class="ti tabler-check"></i>
                                </button>
                                <button type="button" onclick="confirmAction(<?php echo (int)$empRow['dataID']; ?>, 2)" class="action-icon icon-reject" data-bs-toggle="tooltip" title="বাতিল">
                                    <i class="ti tabler-x"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPending === 0): ?>
            <div class="empty-state-rich py-5">
                <i class="ti tabler-info-circle"></i>
                <div class="empty-title">কোন অনুরোধ নেই</div>
                <div class="empty-subtitle">এই মুহূর্তে কোনো বেতন বৃদ্ধির পরিবর্তন অনুমোদনের অপেক্ষায় নেই</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
.salary-amount {
    font-family: var(--bs-font-monospace, monospace);
    font-size: 0.88rem;
    color: #4a4f6f;
    font-weight: 500;
}
.change-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: nowrap;
}
.change-old {
    color: #8a90a6;
    font-size: 0.82rem;
    text-decoration: line-through;
    font-family: var(--bs-font-monospace, monospace);
}
.change-arrow { font-size: 0.95rem; }
.change-new {
    font-weight: 700;
    font-family: var(--bs-font-monospace, monospace);
    font-size: 0.88rem;
    padding: 2px 8px;
    border-radius: 0.4rem;
}
.change-new.change-up   { background: #e6f7ee; color: #1a7e44; }
.change-new.change-down { background: #fbeaea; color: #b13c3c; }
</style>

<script type="text/javascript">
$(document).ready(function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    if ($('#incrementChangesTable tbody tr').length) {
        $('#incrementChangesTable').DataTable({
            responsive: false,
            autoWidth: false,
            order: [],
            columnDefs: [{ targets: '_all', orderable: false }],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
            language: {
                processing: '<div class="spinner-border text-primary" role="status"></div>',
                search: "",
                searchPlaceholder: "খুঁজুন...",
                lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
                info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
                infoEmpty: "কোন এন্ট্রি নেই",
                infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
                emptyTable: "টেবিলে কোন ডেটা নেই",
                paginate: { first: "প্রথম", previous: "পূর্ববর্তী", next: "পরবর্তী", last: "শেষ" }
            },
            createdRow: function(row) {
                var labels = ['ক্রমিক', 'কর্মচারী', 'শাখা ও কেন্দ্র', 'বর্তমান বেতন', 'বৃদ্ধির হার পরিবর্তন', 'নতুন বেতন পরিবর্তন', 'অনুরোধকারী', 'কার্যক্রম'];
                var compact = [0, 3, 4, 5, 7];
                $(row).find('td').each(function(i){
                    var $td = $(this);
                    $td.attr('data-label', labels[i] || '');
                    if ($.trim($td.text()) === '' && $td.children().length === 0) $td.addClass('is-empty');
                    if (compact.indexOf(i) !== -1) $td.addClass('compact-cell');
                });
            }
        });
    }
});

function confirmAction(dataID, isApproved) {
    var actionText  = isApproved == 1 ? 'অনুমোদন' : 'বাতিল';
    var confirmColor = isApproved == 1 ? '#1a7e44' : '#dc3545';
    var confirmClass = isApproved == 1 ? 'btn btn-success me-3' : 'btn btn-danger me-3';

    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: 'এই পরিবর্তন ' + actionText + ' করতে চান?',
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
        $.ajax({
            url: '../../api/salary-increment/approve-change.php',
            type: 'POST',
            dataType: 'json',
            data: { dataID: dataID, isApproved: isApproved },
            beforeSend: function() {
                Swal.fire({
                    title: 'প্রক্রিয়াকরণ হচ্ছে...',
                    allowOutsideClick: false,
                    didOpen: function() { Swal.showLoading(); }
                });
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
