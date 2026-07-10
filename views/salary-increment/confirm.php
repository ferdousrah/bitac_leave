<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Validate employee ID
if (!isset($_GET['employeeID']) || empty($_GET['employeeID'])) {
    echo '<div class="alert alert-danger">কর্মচারী আইডি প্রয়োজন!</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$employeeID = intval($_GET['employeeID']);
$incrementYear = date('Y');

// Get salary increment data
$getSalaryIncrementDataQ = mysqli_query($con, "SELECT * FROM yearly_salary_increment WHERE incrementYear='" . $incrementYear . "' AND employeeID='" . $employeeID . "'");
$getSalaryIncrementDataQRW = mysqli_fetch_assoc($getSalaryIncrementDataQ);

// Get employee details
$getEmployeeDetailsQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . $employeeID . "'");
$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

if (!$getEmployeeDetailsQRW) {
    echo '<div class="alert alert-danger">কর্মচারী পাওয়া যায়নি!</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Get pay scale info
$getPayScaleDetailsQ = mysqli_query($con, "SELECT * FROM grade WHERE id='" . intval($getEmployeeDetailsQRW['pay_scale']) . "'");
$getPayScaleDetailsQRW = mysqli_fetch_assoc($getPayScaleDetailsQ);

// Check for existing update request
$checkForUpdateReqQ = mysqli_query($con, "SELECT * FROM increment_data_update_permission WHERE incrementYear='" . $incrementYear . "' AND employeeID='" . $employeeID . "'");
$checkForUpdateReqQNumRows = $checkForUpdateReqQ ? mysqli_num_rows($checkForUpdateReqQ) : 0;

$checkForUpdateReqQRW = null;
if ($checkForUpdateReqQNumRows > 0) {
    $checkForUpdateReqQRW = mysqli_fetch_assoc($checkForUpdateReqQ);
}

$buttonText = ($checkForUpdateReqQNumRows > 0 && $checkForUpdateReqQRW['isApproved'] == 0) ? "অনুরোধ হালনাগাদ করুন" : "অনুরোধ পাঠান";

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-salary-increment');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-currency-taka me-2 text-primary"></i>বেতন বৃদ্ধি পরিচালনা</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i><strong class="text-dark"><?= htmlspecialchars($getEmployeeDetailsQRW['employee_name']) ?></strong> এর বেতন বৃদ্ধি অনুরোধ</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.inc-form-card { border-radius: 0.75rem; }
.inc-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .inc-form-card .card-body { padding: 1rem; }
}

/* Pending change request banner */
.pending-banner {
    background: #fff7ed;
    border: 1px solid #ffe4b8;
    border-left: 3px solid #d4a056;
    border-radius: 0.6rem;
    padding: 14px 18px;
    margin-bottom: 1.5rem;
}
.pending-banner .pending-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.6rem;
}
.pending-banner .pending-icon {
    width: 32px; height: 32px;
    background: #fff;
    color: #b8651a;
    border-radius: 0.45rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    border: 1px solid #ffe4b8;
}
.pending-banner .pending-title {
    color: #8b6f47;
    font-weight: 600;
    font-size: 0.92rem;
    margin: 0;
}
.pending-banner .pending-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
    font-size: 0.85rem;
    color: #5d6580;
}
.pending-banner .pending-meta strong {
    color: #b8651a;
    font-weight: 700;
}
.pending-banner .pending-note {
    margin-top: 0.5rem;
    font-size: 0.84rem;
    color: #5d6580;
}

/* Section headers */
.inc-section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    margin: 28px 0 20px;
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-left: 3px solid var(--sec-accent, #6c5ce7);
    border-radius: 0.6rem;
}
.inc-section-header:first-of-type { margin-top: 0; }
.inc-section-header[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.inc-section-header[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }
.inc-section-header[data-color="amber"]  { --sec-bg: #fff3e1; --sec-accent: #b8651a; }

.inc-section-header .section-num {
    width: 30px; height: 30px;
    border-radius: 0.5rem;
    background: var(--sec-bg, #f0edff);
    color: var(--sec-accent, #6c5ce7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.inc-section-header .section-text { flex: 1; min-width: 0; }
.inc-section-header .section-title {
    font-size: 0.98rem;
    font-weight: 600;
    color: #2c2e3a;
    margin: 0;
    line-height: 1.3;
}
.inc-section-header .section-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 2px;
    display: block;
}
.inc-section-header .section-icon {
    width: 38px; height: 38px;
    border-radius: 0.55rem;
    background: var(--sec-bg, #f0edff);
    color: var(--sec-accent, #6c5ce7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.inc-form-card .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.inc-form-card .form-control:focus,
.inc-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.inc-form-card .form-control[disabled] {
    background-color: #fafbfd;
    color: #5d6580;
}
.inc-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}

.inc-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

@media (max-width: 575px) {
    .inc-section-header { padding: 12px 14px; gap: 10px; }
    .inc-section-header .section-icon { display: none; }
    .inc-section-header .section-num { width: 26px; height: 26px; font-size: 0.8rem; }
    .inc-section-header .section-title { font-size: 0.92rem; }
}
</style>

<?php if ($checkForUpdateReqQNumRows > 0 && $checkForUpdateReqQRW['isApproved'] == 0): ?>
<!-- Pending Change Request Banner -->
<div class="pending-banner">
    <div class="pending-head">
        <span class="pending-icon"><i class="ti tabler-clock-hour-3"></i></span>
        <h6 class="pending-title">পরিবর্তনের অনুরোধ অপেক্ষমাণ</h6>
    </div>
    <div class="pending-meta">
        <span><i class="ti tabler-arrow-up me-1"></i>অনুরোধকৃত বৃদ্ধির হার: <strong><?= $obj->engToBn(number_format($checkForUpdateReqQRW['salary_increment_rate_edited'], 2)) ?></strong></span>
        <span><i class="ti tabler-cash me-1"></i>অনুরোধকৃত নতুন মূল বেতন: <strong><?= $obj->engToBn(number_format($checkForUpdateReqQRW['salary_increment_basic_edited'], 2)) ?></strong></span>
    </div>
    <?php if (!empty($checkForUpdateReqQRW['note'])): ?>
        <div class="pending-note"><i class="ti tabler-message me-1"></i><strong>মন্তব্য:</strong> <?= htmlspecialchars($checkForUpdateReqQRW['note']) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Salary Increment Form Card -->
<div class="card inc-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form name="form" id="form" enctype="multipart/form-data">
            <input type="hidden" name="employeeID" value="<?= $employeeID ?>">
            <input type="hidden" name="incrementSalaryGrade" value="<?= htmlspecialchars($getSalaryIncrementDataQRW['incrementSalaryGrade'] ?? '') ?>">
            <input type="hidden" name="currentBasic" id="currentBasic" value="<?= floatval($getEmployeeDetailsQRW['basic_salary']) ?>">
            <input type="hidden" name="salary_increment_rate_current" id="salary_increment_rate_current" value="<?= floatval($getSalaryIncrementDataQRW['incrementAmount'] ?? 0) ?>">
            <input type="hidden" name="salary_increment_basic_current" id="salary_increment_basic_current" value="<?= floatval($getSalaryIncrementDataQRW['incrementSalary'] ?? 0) ?>">
            <input type="hidden" name="prevFile" value="<?= ($checkForUpdateReqQNumRows > 0) ? htmlspecialchars($checkForUpdateReqQRW['office_notice'] ?? '') : '' ?>">

            <!-- ───── Section 1: Employee Info (read-only) ───── -->
            <div class="inc-section-header" data-color="indigo">
                <div class="section-num">১</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মচারী তথ্য</h6>
                    <span class="section-sub">কর্মচারীর বর্তমান বেতন স্কেল ও মূল বেতন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-user"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label">কর্মচারীর নাম</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-user"></i></span>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($getEmployeeDetailsQRW['employee_name']) ?>" disabled>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label">বেতন স্কেল</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-stack"></i></span>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($getPayScaleDetailsQRW['grade_title'] ?? '') ?> (<?= $obj->engToBn(number_format($getPayScaleDetailsQRW['minimum_salary'] ?? 0)) ?> - <?= $obj->engToBn(number_format($getPayScaleDetailsQRW['maximum_salary'] ?? 0)) ?>)" disabled>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label">বর্তমান মূল বেতন</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-cash"></i></span>
                        <input type="text" class="form-control" value="<?= $obj->engToBn(number_format($getEmployeeDetailsQRW['basic_salary'], 2)) ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- ───── Section 2: Increment Calculation ───── -->
            <div class="inc-section-header" data-color="green">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">বেতন বৃদ্ধির হিসাব</h6>
                    <span class="section-sub">যেকোনো একটি ফিল্ড পূরণ করলে অন্যটি স্বয়ংক্রিয় হিসাব হবে</span>
                </div>
                <span class="section-icon"><i class="ti tabler-calculator"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="incrementAmount">
                    বেতন বৃদ্ধির হার <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-arrow-up"></i></span>
                        <input type="number" step="0.01" class="form-control" name="incrementAmount" id="incrementAmount" value="<?= floatval($getSalaryIncrementDataQRW['incrementAmount'] ?? 0) ?>" required>
                    </div>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>বৃদ্ধির পরিমাণ লিখুন — নতুন বেতন স্বয়ংক্রিয়ভাবে হিসাব হবে</small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="incrementSalary">
                    বৃদ্ধির পর মূল বেতন <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-cash-banknote"></i></span>
                        <input type="number" step="0.01" class="form-control" name="incrementSalary" id="incrementSalary" value="<?= floatval($getSalaryIncrementDataQRW['incrementSalary'] ?? 0) ?>" required>
                    </div>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>অথবা সরাসরি নতুন বেতন লিখুন — বৃদ্ধির হার স্বয়ংক্রিয়ভাবে হিসাব হবে</small>
                </div>
            </div>

            <!-- ───── Section 3: Additional Info ───── -->
            <div class="inc-section-header" data-color="amber">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">অতিরিক্ত তথ্য</h6>
                    <span class="section-sub">মন্তব্য ও অফিস আদেশের সংযুক্তি</span>
                </div>
                <span class="section-icon"><i class="ti tabler-file-description"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="note">
                    মন্তব্য <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <textarea class="form-control" name="note" id="note" rows="3" placeholder="মন্তব্য লিখুন" required><?= ($checkForUpdateReqQNumRows > 0) ? htmlspecialchars($checkForUpdateReqQRW['note'] ?? '') : '' ?></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="office_notice">
                    অফিস আদেশ <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <input type="file" name="office_notice" id="office_notice" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?= ($checkForUpdateReqQNumRows == 0) ? 'required' : '' ?>>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>PDF, JPG বা PNG ফরম্যাট সমর্থিত</small>

                    <?php if ($checkForUpdateReqQNumRows > 0 && !empty($checkForUpdateReqQRW['office_notice'])): ?>
                        <a href="../../uploads/<?= htmlspecialchars($checkForUpdateReqQRW['office_notice']) ?>" target="_blank" class="btn btn-sm btn-label-info mt-2">
                            <i class="ti tabler-download me-1"></i>পূর্বের আপলোডকৃত ফাইল
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="inc-form-actions d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" name="submit" id="submit" class="btn btn-primary submitBtn px-4">
                    <i class="ti tabler-send me-1"></i><?= $buttonText ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>

<script type="text/javascript">
$(document).ready(function() {
    var form = $('#form');
    var submit = $('#submit');
    var buttonText = '<?= $buttonText ?>';

    // Auto-calculate increment salary when increment amount changes
    $('#incrementAmount').on("input", function() {
        var incrementAmount = parseFloat(this.value) || 0;
        var currentBasic = parseFloat($('#currentBasic').val()) || 0;
        var incrementSalary = currentBasic + incrementAmount;
        $('#incrementSalary').val(incrementSalary.toFixed(2));
    });

    // Auto-calculate increment amount when increment salary changes
    $('#incrementSalary').on("input", function() {
        var incrementSalary = parseFloat(this.value) || 0;
        var currentBasic = parseFloat($('#currentBasic').val()) || 0;
        var incrementAmount = incrementSalary - currentBasic;
        $('#incrementAmount').val(incrementAmount.toFixed(2));
    });

    // Form submit event
    form.on('submit', function(e) {
        e.preventDefault();

        // Validation
        var incrementAmount = parseFloat($('#incrementAmount').val());
        var incrementSalary = parseFloat($('#incrementSalary').val());
        var currentBasic = parseFloat($('#currentBasic').val());

        if (incrementAmount <= 0) {
            Swal.fire({
                title: 'সতর্কতা',
                text: 'বেতন বৃদ্ধির হার ০ বা ঋণাত্মক হতে পারবে না',
                icon: 'warning',
                confirmButtonColor: '#6c5ce7',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return false;
        }

        if (incrementSalary <= currentBasic) {
            Swal.fire({
                title: 'সতর্কতা',
                text: 'বৃদ্ধির পর মূল বেতন বর্তমান মূল বেতনের চেয়ে বেশি হতে হবে',
                icon: 'warning',
                confirmButtonColor: '#6c5ce7',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return false;
        }

        $.ajax({
            url: '../../api/salary-increment/update-increment.php',
            type: 'POST',
            dataType: 'json',
            data: new FormData(this),
            processData: false,
            contentType: false,
            beforeSend: function() {
                submit.attr("disabled", "disabled");
                submit.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>প্রক্রিয়াকরণ হচ্ছে...');

                Swal.fire({
                    title: 'অপেক্ষা করুন',
                    text: 'প্রক্রিয়াকরণ হচ্ছে...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => { Swal.showLoading(); }
                });
            },
            success: function(response) {
                if (response.status == 1) {
                    Swal.fire({
                        title: 'সম্পন্ন',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.history.go(-1);
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি',
                        text: response.message || 'কিছু ভুল হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });

                    submit.removeAttr("disabled");
                    submit.html('<i class="ti tabler-send me-1"></i>' + buttonText);
                }
            },
            error: function(e) {
                console.log(e);

                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });

                submit.removeAttr("disabled");
                submit.html('<i class="ti tabler-send me-1"></i>' + buttonText);
            }
        });
    });
});
</script>
