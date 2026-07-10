<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$centerId = intval($_GET['center_id'] ?? 0);
$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-settings');

if (!$centerId) {
    echo '<div class="alert alert-danger">কেন্দ্রের তথ্য পাওয়া যায়নি।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Get center name
$centerQ = mysqli_query($con, "SELECT organization_name FROM organization WHERE id = '$centerId'");
$centerRow = mysqli_fetch_assoc($centerQ);
$centerName = $centerRow['organization_name'] ?? '';

// Get existing signatory for this center from leave_edit_approval_signatory
$existing = null;
$checkColQ = mysqli_query($con, "SHOW COLUMNS FROM leave_edit_approval_signatory LIKE 'organization_id'");
if (mysqli_num_rows($checkColQ) === 0) {
    mysqli_query($con, "ALTER TABLE leave_edit_approval_signatory ADD COLUMN organization_id INT(11) DEFAULT 0 AFTER dataID");
}
$existingQ = mysqli_query($con, "SELECT * FROM leave_edit_approval_signatory WHERE organization_id = '$centerId' LIMIT 1");
if (mysqli_num_rows($existingQ) > 0) {
    $existing = mysqli_fetch_assoc($existingQ);
}

// Resolve current employee details (for display)
$currentEmp = null;
if ($existing) {
    $currentEmpQ = mysqli_query($con, "SELECT employee_name, employee_id FROM employee_list WHERE id = '{$existing['employeeID']}' LIMIT 1");
    $currentEmp  = mysqli_fetch_assoc($currentEmpQ);
}

// Get all active employees for this center
$employeesQ = mysqli_query($con, "SELECT id, employee_name, employee_id FROM employee_list WHERE organization_id = '$centerId' AND employment_status = 1 ORDER BY employee_name ASC");
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-pen me-2 text-primary"></i>অন্যান্য কাজের সিগনেটরি</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>পূর্ব ছুটি / কর্তন / যোজন / ছুটির সনদের জন্য সিগনেটরি নির্ধারণ</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="previous_leave_deduction_addition_certificate_main.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.signatory-card { border-radius: 0.75rem; }
.signatory-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .signatory-card .card-body { padding: 1rem; }
}

/* Center banner */
.signatory-center-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, #f0edff 0%, #f5f6fa 100%);
    border: 1px solid #ddd5f6;
    border-radius: 0.6rem;
    padding: 14px 18px;
    margin-bottom: 1.25rem;
}
.signatory-center-banner .center-icon {
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
.signatory-center-banner .center-name {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 1rem;
}
.signatory-center-banner .center-sub {
    font-size: 0.78rem;
    color: #5d6580;
    margin-top: 2px;
}

/* Current signatory card */
.current-sig-card {
    background: #f0faf4;
    border: 1px solid #c4ebd4;
    border-left: 3px solid #1a7e44;
    border-radius: 0.5rem;
    padding: 12px 14px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.current-sig-card .sig-icon {
    width: 36px; height: 36px;
    border-radius: 0.45rem;
    background: #1a7e44;
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.current-sig-card .sig-label {
    font-size: 0.74rem;
    color: #1a7e44;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
}
.current-sig-card .sig-name {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 0.95rem;
}
.current-sig-card .sig-id {
    font-size: 0.78rem;
    color: #5d6580;
}

/* Form labels & focus polish */
.signatory-card .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.signatory-card .form-control:focus,
.signatory-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}

.sig-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}
</style>

<!-- Form Card -->
<div class="card signatory-card shadow-sm border-0">
    <div class="card-body">
        <div class="statusMsg" style="display:none;"></div>

        <!-- Center banner -->
        <div class="signatory-center-banner">
            <span class="center-icon"><i class="ti tabler-building"></i></span>
            <div>
                <div class="center-name"><?= htmlspecialchars($centerName) ?></div>
                <div class="center-sub">এই কেন্দ্রের জন্য একজন সিগনেটরি নির্ধারণ করুন</div>
            </div>
        </div>

        <?php if ($existing && $currentEmp): ?>
        <!-- Current signatory card -->
        <div class="current-sig-card">
            <span class="sig-icon"><i class="ti tabler-user-check"></i></span>
            <div>
                <div class="sig-label">বর্তমান সিগনেটরি</div>
                <div class="sig-name">
                    <?= htmlspecialchars($currentEmp['employee_name'] ?? 'অজানা') ?>
                    <?php if (!empty($currentEmp['employee_id'])): ?>
                        <span class="sig-id ms-1">(আইডি: <?= htmlspecialchars($currentEmp['employee_id']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form id="signatoryForm">
            <input type="hidden" name="center_id" value="<?= $centerId ?>">
            <?php if ($existing): ?>
                <input type="hidden" name="record_id" value="<?= $existing['dataID'] ?>">
            <?php endif; ?>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    সিগনেটরি <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select id="employeeID" name="employeeID" class="form-select select2" required>
                        <option value="">-- কর্মকর্তা/কর্মচারী নির্বাচন করুন --</option>
                        <?php while ($emp = mysqli_fetch_assoc($employeesQ)): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($existing && $existing['employeeID'] == $emp['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['employee_name']) ?><?= !empty($emp['employee_id']) ? ' (' . htmlspecialchars($emp['employee_id']) . ')' : '' ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="text-muted mt-1 d-block">
                        <i class="ti tabler-info-circle me-1"></i>শুধুমাত্র <strong><?= htmlspecialchars($centerName) ?></strong> কেন্দ্রের কর্মরত কর্মকর্তা/কর্মচারীরা দেখানো হচ্ছে
                    </small>
                </div>
            </div>

            <!-- Action row -->
            <div class="sig-form-actions d-flex gap-2 justify-content-end">
                <a href="previous_leave_deduction_addition_certificate_main.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </a>
                <button type="submit" class="btn btn-primary submitBtn px-4" id="submitBtn">
                    <i class="ti tabler-device-floppy me-1"></i><?= $existing ? 'আপডেট করুন' : 'সংরক্ষণ করুন' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function () {
    // Init Select2
    if ($.fn.select2) {
        $('#employeeID').select2({
            placeholder: '-- কর্মকর্তা/কর্মচারী নির্বাচন করুন --',
            allowClear: true
        });
    }

    var originalLabel = $('#submitBtn').html();

    $('#signatoryForm').on('submit', function (e) {
        e.preventDefault();

        var employeeID = $('#employeeID').val();
        if (!employeeID) {
            Swal.fire({
                icon: 'warning',
                title: 'সিগনেটরি নির্বাচন করুন',
                text: 'অনুগ্রহ করে একজন কর্মকর্তা/কর্মচারী নির্বাচন করুন।',
                confirmButtonColor: '#6c5ce7',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-primary' }
            });
            return;
        }

        $('#submitBtn').attr('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>সংরক্ষণ হচ্ছে...');
        $('#signatoryForm').css('opacity', '.6');

        $.ajax({
            type: 'POST',
            url: '../../api/signatory/save-other-task.php',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                $('#signatoryForm').css('opacity', '');
                if (res.status == 1) {
                    Swal.fire({
                        icon: 'success',
                        title: 'সম্পন্ন',
                        text: res.message,
                        confirmButtonColor: '#6c5ce7',
                        buttonsStyling: false,
                        customClass: { confirmButton: 'btn btn-primary' }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    $('#submitBtn').removeAttr('disabled').html(originalLabel);
                    Swal.fire({
                        icon: 'error',
                        title: 'ত্রুটি',
                        text: res.message,
                        confirmButtonColor: '#ff3e1d',
                        buttonsStyling: false,
                        customClass: { confirmButton: 'btn btn-danger' }
                    });
                }
            },
            error: function () {
                $('#signatoryForm').css('opacity', '');
                $('#submitBtn').removeAttr('disabled').html(originalLabel);
                Swal.fire({
                    icon: 'error',
                    title: 'ত্রুটি',
                    text: 'একটি সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                    confirmButtonColor: '#ff3e1d',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-danger' }
                });
            }
        });
    });
});
</script>
