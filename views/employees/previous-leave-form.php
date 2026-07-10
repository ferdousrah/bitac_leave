<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$dataID = base64_decode($_GET['dataID']);

$todayDate = ShowBangladeshDate();

$getEmployeeDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$dataID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$diff = abs(strtotime($todayDate) - strtotime($getEmployeeInfoQRW['joining_date']));
$days = round($diff / (60 * 60 * 24));

// Calculate entitlements
$fullAvgSalLeave = floor($days / 11);
if (fmod($days, 11) >= 6) $fullAvgSalLeave++;

$halfAvgSalLeave = floor($days / 12);
if (fmod($days, 12) >= 6) $halfAvgSalLeave++;

// Check previous data
$checkPrevDataQ = mysqli_query($con, "SELECT * FROM previous_leave_deduction WHERE employeeID='$dataID'");
$checkPrevDataQNumRows = mysqli_num_rows($checkPrevDataQ);
$prev = ($checkPrevDataQNumRows > 0) ? mysqli_fetch_assoc($checkPrevDataQ) : [];

// Configured signatory for this employee's center
$empOrgIDForSig = (int)($getEmployeeInfoQRW['organization_id'] ?? 0);
$configuredSignatory = null;
if ($empOrgIDForSig > 0) {
    $sigQ = mysqli_query($con,
        "SELECT el.employee_name, el.employee_id AS code, jt.job_title_name
         FROM leave_edit_approval_signatory las
         INNER JOIN employee_list el ON las.employeeID = el.id
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE las.organization_id = $empOrgIDForSig LIMIT 1");
    if ($sigQ && $sigR = mysqli_fetch_assoc($sigQ)) $configuredSignatory = $sigR;
}

// Previous values
$prevLastUpdate = $prev['lastUpdate'] ?? '';
$prevIsApproved = $prev['isApproved'] ?? null;

// Status
$statusInfo = null;
if ($checkPrevDataQNumRows > 0) {
    if ($prevIsApproved == 1) {
        $statusInfo = ['label' => 'অনুমোদিত', 'icon' => 'tabler-circle-check', 'bg' => '#e6f7ee', 'fg' => '#1a7e44', 'border' => '#c4ebd4'];
    } elseif ($prevIsApproved == 2) {
        $statusInfo = ['label' => 'অনুমোদিত নয়', 'icon' => 'tabler-circle-x',     'bg' => '#fff1f0', 'fg' => '#b13c3c', 'border' => '#f5c6c6'];
    } elseif ($prevIsApproved === 0 || $prevIsApproved === '0') {
        $statusInfo = ['label' => 'অপেক্ষমান', 'icon' => 'tabler-clock-hour-3', 'bg' => '#fff3e1', 'fg' => '#b8651a', 'border' => '#ffe4b8'];
    }
}

// Leave type definitions: label, sublabel, field names, previous values
$leaveTypes = [
    [
        'label' => 'গড় বেতন ছুটি', 'sublabel' => 'Full Average Salary',
        'usedName' => 'avgSalary', 'remainingName' => 'avgSalaryRemaining', 'fileName' => 'avgSalaryFile',
        'entitled' => $fullAvgSalLeave,
        'prevUsed' => $prev['avgSalary'] ?? '',
        'prevRemaining' => $prev['avgSalaryNote'] ?? '',
        'prevFile' => $prev['avgSalaryFile'] ?? '',
    ],
    [
        'label' => 'অর্ধ-গড় বেতন ছুটি', 'sublabel' => 'Half Average Salary',
        'usedName' => 'halfAvgSalary', 'remainingName' => 'halfAvgSalaryRemaining', 'fileName' => 'halfAvgSalaryFile',
        'entitled' => $halfAvgSalLeave,
        'prevUsed' => $prev['halfAvgSalary'] ?? '',
        'prevRemaining' => $prev['halfAvgSalaryNote'] ?? '',
        'prevFile' => $prev['halfAvgSalaryFile'] ?? '',
    ],
    [
        'label' => 'নৈমিত্তিক ছুটি', 'sublabel' => 'Casual Leave',
        'usedName' => 'casual', 'remainingName' => 'casualRemaining', 'fileName' => 'casualFile',
        'prevUsed' => $prev['casual'] ?? '',
        'prevRemaining' => $prev['casualNote'] ?? '',
        'prevFile' => $prev['casualFile'] ?? '',
    ],
    [
        'label' => 'অসাধারণ ছুটি', 'sublabel' => 'Leave Without Pay',
        'usedName' => 'leaveWithoutPay', 'remainingName' => 'leaveWithoutPayRemaining', 'fileName' => 'leaveWithoutPayFile',
        'prevUsed' => $prev['leaveWithoutPay'] ?? '',
        'prevRemaining' => $prev['leaveWithoutPayNote'] ?? '',
        'prevFile' => $prev['leaveWithoutPayFile'] ?? '',
    ],
    [
        'label' => 'কর্তনহীন ছুটি', 'sublabel' => 'Undeductible Leave',
        'usedName' => 'undeductibleLeave', 'remainingName' => 'undeductibleLeaveRemaining', 'fileName' => 'undeductibleLeaveFile',
        'prevUsed' => $prev['undeductibleLeave'] ?? '',
        'prevRemaining' => $prev['undeductibleLeaveRemaining'] ?? '',
        'prevFile' => $prev['undeductibleLeaveFile'] ?? '',
    ],
];

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-employee');
$empName  = htmlspecialchars($getEmployeeInfoQRW['employee_name'] ?? '');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-history me-2 text-primary"></i>পূর্ব ছুটির তথ্য ইনপুট</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>কর্মচারীর পূর্বের ভোগকৃত ছুটির হিসাব নিবন্ধন করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.prev-leave-card { border-radius: 0.75rem; }
.prev-leave-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .prev-leave-card .card-body { padding: 1rem; }
}

/* Employee summary banner */
.emp-summary-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, #f0edff 0%, #f5f6fa 100%);
    border: 1px solid #ddd5f6;
    border-radius: 0.6rem;
    padding: 14px 18px;
    margin-bottom: 1.5rem;
}
.emp-summary-banner .emp-icon {
    width: 44px; height: 44px;
    border-radius: 0.55rem;
    background: #fff;
    color: #5648c4;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    border: 1px solid #ddd5f6;
}
.emp-summary-banner .emp-name {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 0.98rem;
}
.emp-summary-banner .emp-sub {
    font-size: 0.78rem;
    color: #5d6580;
    margin-top: 2px;
}

/* Status pill */
.prev-status-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}
.prev-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.4em 0.85em;
    border-radius: 0.4rem;
    border: 1px solid;
}

/* Table */
.prev-leave-table {
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    background: #fafbfd;
    padding: 0.5rem;
    overflow: hidden;
}
.prev-leave-table .table {
    margin-bottom: 0;
    background: #fff;
    border-radius: 0.5rem;
    overflow: hidden;
}
.prev-leave-table thead th {
    background: #fafbfd !important;
    font-size: 0.78rem;
    color: #5d6580;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #eef0f5;
    padding: 0.7rem 0.8rem;
}
.prev-leave-table tbody td {
    vertical-align: middle;
    padding: 0.7rem 0.8rem;
    border-bottom: 1px solid #f3f4fa;
}
.prev-leave-table tbody tr:last-child td { border-bottom: 0; }
.prev-leave-table .leave-name {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 0.92rem;
}
.prev-leave-table .leave-sub {
    font-size: 0.74rem;
    color: #8a90a6;
    margin-top: 2px;
}
.prev-leave-table .entitled-tag {
    display: inline-block;
    margin-top: 4px;
    font-size: 0.72rem;
    color: #5648c4;
    background: #f0edff;
    padding: 1px 8px;
    border-radius: 0.3rem;
    font-weight: 500;
}
.prev-leave-table .form-control:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.prev-leave-table .file-link {
    color: #1a7e44;
    text-decoration: none;
    font-size: 0.78rem;
}
.prev-leave-table .file-link:hover { text-decoration: underline; }

/* Action row */
.prev-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

/* Helper note */
.prev-helper-note {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
</style>

<form class="form-login" name="form" id="form" enctype="multipart/form-data">
    <input type="hidden" name="employeeID" value="<?= $dataID ?>" />

    <div class="card prev-leave-card shadow-sm border-0">
        <div class="card-body">

            <!-- Employee summary -->
            <div class="emp-summary-banner">
                <span class="emp-icon"><i class="ti tabler-user"></i></span>
                <div>
                    <div class="emp-name"><?= $empName ?></div>
                    <div class="emp-sub">কর্মচারীর পূর্বের ছুটি ব্যবহার ও অবশিষ্ট সংখ্যা</div>
                </div>
            </div>

            <?php if ($statusInfo): ?>
            <div class="prev-status-row">
                <span class="prev-status-pill" style="background:<?= $statusInfo['bg'] ?>;color:<?= $statusInfo['fg'] ?>;border-color:<?= $statusInfo['border'] ?>;">
                    <i class="ti <?= $statusInfo['icon'] ?>"></i><?= $statusInfo['label'] ?>
                </span>
                <?php if (!empty($prevLastUpdate)): ?>
                    <small class="text-muted"><i class="ti tabler-clock me-1"></i>সর্বশেষ আপডেট: <?= htmlspecialchars($prevLastUpdate) ?></small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Approval signatory info -->
            <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded" style="background:#f0edff; border:1px solid #ddd5f6;">
                <i class="ti tabler-user-check" style="color:#5648c4; font-size:1.1rem;"></i>
                <?php if ($configuredSignatory): ?>
                    <div style="font-size: 0.84rem; color: #3a3d53;">
                        অনুমোদনকারী:
                        <strong><?= htmlspecialchars($configuredSignatory['employee_name']) ?></strong>
                        <?php if (!empty($configuredSignatory['job_title_name'])): ?>
                            <span style="color:#8a90a6;"> — <?= htmlspecialchars($configuredSignatory['job_title_name']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="font-size: 0.84rem; color: #8b5a1a;">
                        <i class="ti tabler-alert-triangle me-1"></i>
                        এই কেন্দ্রের জন্য কোনো অনুমোদনকারী নির্ধারিত নেই —
                        <a href="../../views/signatory/previous_leave_deduction_addition_certificate_main.php?menuslug=leave-settings" style="color:#5648c4; font-weight:600;">সেটিংস</a>
                        থেকে নির্ধারণ করুন
                    </div>
                <?php endif; ?>
            </div>

            <!-- Leave Table -->
            <div class="prev-leave-table">
                <div class="table-responsive">
                    <table class="table align-middle" id="leaveTable">
                        <thead>
                            <tr>
                                <th style="width:30%;">ছুটির ধরন</th>
                                <th class="text-center" style="width:18%;">ভোগকৃত (দিন)</th>
                                <th class="text-center" style="width:18%;">অবশিষ্ট (দিন)</th>
                                <th style="width:34%;">সংযুক্তি</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaveTypes as $lt): ?>
                            <tr data-original-used="<?= htmlspecialchars($lt['prevUsed']) ?>"
                                data-original-remaining="<?= htmlspecialchars($lt['prevRemaining']) ?>"
                                data-has-file="<?= (!empty($lt['prevFile']) && file_exists(__DIR__ . '/../../uploads/' . $lt['prevFile'])) ? '1' : '0' ?>"
                                data-entitled="<?= $lt['entitled'] ?? '' ?>">
                                <td>
                                    <div class="leave-name"><?= htmlspecialchars($lt['label']) ?></div>
                                    <div class="leave-sub"><?= htmlspecialchars($lt['sublabel']) ?></div>
                                    <?php if (!empty($lt['entitled'])): ?>
                                        <span class="entitled-tag">প্রাপ্য: <?= htmlspecialchars($lt['entitled']) ?> দিন</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" class="form-control text-center leave-input" name="<?= $lt['usedName'] ?>" value="<?= htmlspecialchars($lt['prevUsed']) ?>" min="0" placeholder="0">
                                </td>
                                <td>
                                    <input type="number" class="form-control text-center leave-input" name="<?= $lt['remainingName'] ?>" value="<?= htmlspecialchars($lt['prevRemaining']) ?>" min="0" placeholder="0">
                                </td>
                                <td>
                                    <input type="file" class="form-control form-control-sm file-input" name="<?= $lt['fileName'] ?>" accept=".jpg,.jpeg,.png,.pdf">
                                    <?php if (!empty($lt['prevFile']) && file_exists(__DIR__ . '/../../uploads/' . $lt['prevFile'])): ?>
                                        <a href="../../uploads/<?= htmlspecialchars($lt['prevFile']) ?>" target="_blank" class="file-link mt-1 d-block">
                                            <i class="ti tabler-paperclip me-1"></i>সংযুক্ত ফাইল দেখুন
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="prev-helper-note">
                <i class="ti tabler-info-circle"></i>
                ভোগকৃত বা অবশিষ্ট দিন পরিবর্তন করলে সংযুক্তি দিতে হবে। প্রাপ্যতা সহ row-গুলোতে অটো-হিসাব হবে।
            </div>

            <!-- Action row -->
            <div class="prev-form-actions d-flex gap-2 justify-content-end flex-wrap">
                <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </a>
                <button type="submit" name="submit" id="submit" class="btn btn-primary px-4">
                    <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান
                </button>
            </div>

        </div>
    </div>
</form>

<?php
ob_start();
?>
<script>
jQuery(function($) {
    // Auto-calculate: when user enters ভোগকৃত, calculate অবশিষ্ট and vice versa
    $('#leaveTable .leave-input').on('input', function() {
        var $row = $(this).closest('tr');
        var entitled = parseInt($row.data('entitled'));
        if (!entitled || isNaN(entitled)) return; // no auto-calc for rows without entitlement

        var $used = $row.find('.leave-input').eq(0);
        var $remaining = $row.find('.leave-input').eq(1);
        var isUsedField = $(this).is($used);

        if (isUsedField) {
            var usedVal = parseInt($used.val());
            if (!isNaN(usedVal)) {
                $remaining.val(Math.max(0, entitled - usedVal));
            } else {
                $remaining.val('');
            }
        } else {
            var remVal = parseInt($remaining.val());
            if (!isNaN(remVal)) {
                $used.val(Math.max(0, entitled - remVal));
            } else {
                $used.val('');
            }
        }
    });

    var form = $('#form');
    var submit = $('#submit');
    var originalButtonText = '<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান';

    form.on('submit', function(e) {
        e.preventDefault();

        // Validate: attachment required only for rows where data changed and no existing file
        var valid = true;
        var errorRows = [];

        $('#leaveTable tbody tr').each(function() {
            var $row = $(this);
            var origUsed = $row.attr('data-original-used') || '';
            var origRemaining = $row.attr('data-original-remaining') || '';
            var currentUsed = $row.find('.leave-input').eq(0).val() || '';
            var currentRemaining = $row.find('.leave-input').eq(1).val() || '';
            var hasExistingFile = $row.data('has-file') == '1';
            var hasNewFile = $row.find('.file-input')[0].files.length > 0;

            // Row has data entered
            var hasData = currentUsed !== '' || currentRemaining !== '';
            // Row values changed from original
            var changed = (currentUsed !== origUsed) || (currentRemaining !== origRemaining);

            if (hasData && changed && !hasExistingFile && !hasNewFile) {
                valid = false;
                $row.find('.file-input').addClass('is-invalid');
                errorRows.push($row.find('.leave-name').text());
            } else {
                $row.find('.file-input').removeClass('is-invalid');
            }
        });

        if (!valid) {
            Swal.fire({
                icon: 'warning',
                title: 'সংযুক্তি প্রয়োজন',
                html: 'নিম্নলিখিত ছুটির ধরনে সংযুক্তি দিন:<br><strong>' + errorRows.join(', ') + '</strong>',
                confirmButtonColor: '#6c5ce7',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return;
        }

        submit.prop('disabled', true);

        $.ajax({
            url: '../../api/employees/insert-previous-leave.php',
            type: 'POST',
            dataType: 'json',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                submit.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>প্রক্রিয়াকরণ হচ্ছে...');
            },
            success: function(resp) {
                if (resp.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'সম্পন্ন',
                        text: resp.message || 'তথ্য সফলভাবে সংরক্ষণ হয়েছে',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(function() {
                        window.location = '../../views/employees/manage.php?menuslug=manage-employee';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ত্রুটি',
                        text: resp.message || 'সব আবশ্যক ফিল্ড পূরণ করুন এবং আবার চেষ্টা করুন।',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
                submit.prop('disabled', false);
                submit.html(originalButtonText);
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'সার্ভার ত্রুটি',
                    text: 'অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
                submit.prop('disabled', false);
                submit.html(originalButtonText);
            }
        });
    });
});
</script>
<?php
define('PAGE_SCRIPTS', ob_get_clean());
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>
