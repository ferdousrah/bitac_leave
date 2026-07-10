<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Fetch all designations
$getAllDesig = mysqli_query($con, "SELECT * FROM `job_title` WHERE `deleted`=0 ORDER BY job_title_name ASC");

// Fetch all organizations
$getAllOrg = mysqli_query($con, "SELECT * FROM `organization` WHERE `deleted`=0 ORDER BY display_order ASC");

// Fetch all sections
$getAllSectionsQ = mysqli_query($con, "SELECT * FROM `sections` WHERE deleted=0 ORDER BY section_name ASC");

// Fetch all salary groups
$getAllGroupQ = mysqli_query($con, "SELECT * FROM salary_group ORDER BY id ASC");

// Fetch all grades/scales
$getScalesQ = mysqli_query($con, "SELECT * FROM grade WHERE deleted=0 ORDER BY minimum_salary ASC");

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-employee');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-plus me-2 text-primary"></i>নতুন কর্মকর্তা / কর্মচারী</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>কর্মকর্তা / কর্মচারীর সম্পূর্ণ তথ্য প্রবেশ করান</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.emp-form-card { border-radius: 0.75rem; }
.emp-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .emp-form-card .card-body { padding: 1rem; }
}

.emp-section-header {
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
.emp-section-header:first-of-type { margin-top: 0; }
.emp-section-header[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.emp-section-header[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }
.emp-section-header[data-color="amber"]  { --sec-bg: #fff3e1; --sec-accent: #b8651a; }

.emp-section-header .section-num {
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
.emp-section-header .section-text { flex: 1; min-width: 0; }
.emp-section-header .section-title {
    font-size: 0.98rem;
    font-weight: 600;
    color: #2c2e3a;
    margin: 0;
    line-height: 1.3;
}
.emp-section-header .section-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 2px;
    display: block;
}
.emp-section-header .section-icon {
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

.emp-form-card .form-label {
    font-size: 0.82rem;
    color: #3a3d53;
    font-weight: 500;
    margin-bottom: 0.4rem;
}
.emp-form-card .form-control,
.emp-form-card .form-select {
    font-size: 0.9rem;
    border-color: #e0e4ee;
}
.emp-form-card .form-control:focus,
.emp-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.emp-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}
.emp-form-card .required-mark { color: #dc3545; font-weight: 700; }

.emp-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

@media (max-width: 575px) {
    .emp-section-header { padding: 12px 14px; gap: 10px; }
    .emp-section-header .section-icon { display: none; }
    .emp-section-header .section-num { width: 26px; height: 26px; font-size: 0.8rem; }
    .emp-section-header .section-title { font-size: 0.92rem; }
}
</style>

<form class="form-login" name="form" id="form" enctype="multipart/form-data">
    <div class="card emp-form-card shadow-sm border-0">
        <div class="card-body">

            <!-- ───── Section 0: Entry Type Selector ───── -->
            <div class="emp-section-header" data-color="amber">
                <div class="section-num">০</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মচারীর ধরন (এন্ট্রি)</h6>
                    <span class="section-sub">শিক্ষানবিশ — নতুন এন্ট্রি (অস্থায়ী আইডি অটো-জেনারেট হবে)। স্থায়ী — BITAC আইডিসহ ইতিমধ্যে স্থায়ী।</span>
                </div>
                <span class="section-icon"><i class="ti tabler-id-badge-2"></i></span>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="form-check form-check-inline" style="background:#fff7ed; padding:0.75rem 1rem; border-radius:0.5rem; border:1px solid #fde0a8;">
                        <input class="form-check-input" type="radio" name="entry_type" id="entry_permanent" value="permanent" checked>
                        <label class="form-check-label" for="entry_permanent" style="cursor:pointer;">
                            <strong><i class="ti tabler-id me-1"></i>স্থায়ী কর্মচারী</strong>
                            <div class="text-muted small">BITAC কর্তৃক আইডি প্রদান করা হয়েছে</div>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-check-inline" style="background:#f0edff; padding:0.75rem 1rem; border-radius:0.5rem; border:1px solid #ddd5f6;">
                        <input class="form-check-input" type="radio" name="entry_type" id="entry_probationary" value="probationary">
                        <label class="form-check-label" for="entry_probationary" style="cursor:pointer;">
                            <strong><i class="ti tabler-clock-hour-4 me-1"></i>শিক্ষানবিশ</strong>
                            <div class="text-muted small">নতুন এন্ট্রি — অস্থায়ী আইডি (P-YYYY-NNN) অটো</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- ───── Section 1: Personal Info ───── -->
            <div class="emp-section-header" data-color="indigo">
                <div class="section-num">১</div>
                <div class="section-text">
                    <h6 class="section-title">ব্যক্তিগত তথ্য</h6>
                    <span class="section-sub">নাম, পরিচয়পত্র, পদবি ও যোগাযোগের তথ্য</span>
                </div>
                <span class="section-icon"><i class="ti tabler-user"></i></span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="employee_name">নাম <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-user"></i></span>
                        <input type="text" id="employee_name" class="form-control" placeholder="পূর্ণ নাম লিখুন" name="employee_name" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="nid">জাতীয় পরিচয়পত্র নং <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-id"></i></span>
                        <input class="form-control" type="text" id="nid" name="nid" placeholder="NID নম্বর" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="designation">পদবি <span class="required-mark">*</span></label>
                    <select data-placeholder="পদবি নির্বাচন করুন" class="select2 form-select" name="designation" id="designation" required>
                        <option value="">-- পদবি নির্বাচন করুন --</option>
                        <?php while($degRow = mysqli_fetch_array($getAllDesig)): ?>
                            <option value="<?= $degRow['id'] ?>"><?= htmlspecialchars($degRow['job_title_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6" id="employeeIdField">
                    <label class="form-label" for="employee_id">
                        <span data-label-permanent>BITAC আইডি</span>
                        <span data-label-probationary style="display:none;">অস্থায়ী আইডি (অটো জেনারেট হবে)</span>
                        <span class="required-mark" data-required-mark>*</span>
                    </label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-hash"></i></span>
                        <input class="form-control" type="text" id="employee_id" name="employee_id" placeholder="কর্মচারী আইডি" required>
                    </div>
                    <small class="text-muted d-block mt-1" data-probationary-only style="display:none;">
                        <i class="ti tabler-info-circle me-1"></i>সংরক্ষণের সময় <strong>P-<?= date('Y') ?>-NNN</strong> ফরম্যাটে অটো অ্যাসাইন হবে
                    </small>
                </div>

                <div class="col-md-6" id="memorialNoField">
                    <label class="form-label" for="memorialNo">স্মারক নম্বর <span class="required-mark" data-required-mark>*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-bookmark"></i></span>
                        <input class="form-control" type="text" id="memorialNo" name="memorialNo" placeholder="স্মারক নং" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="employee_type">চাকরীর ধরন <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="employee_type" id="employee_type" required>
                        <option value="">-- ধরন নির্বাচন করুন --</option>
                        <option value="1">কর্মকর্তা</option>
                        <option value="2">কর্মচারী</option>
                        <option value="3">পি আর এল</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="emp-dob">জন্ম তারিখ <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-cake"></i></span>
                        <input type="text" class="form-control flatpickr-validation" id="emp-dob" name="dob" placeholder="YYYY-MM-DD" autocomplete="off" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="emp-joining-date">
                        <span data-label-permanent>চাকরিতে যোগদানের তারিখ (স্থায়ী হিসেবে)</span>
                        <span data-label-probationary style="display:none;">শিক্ষানবিশ শুরুর তারিখ</span>
                        <span class="required-mark">*</span>
                    </label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-calendar-event"></i></span>
                        <input type="text" class="form-control flatpickr-validation" id="emp-joining-date" name="joining_date" placeholder="YYYY-MM-DD" autocomplete="off" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="email">ইমেইল</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-mail"></i></span>
                        <input class="form-control" type="email" id="email" name="email" placeholder="example@bitac.gov.bd">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mobileNo">মোবাইল নম্বর</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-phone"></i></span>
                        <input class="form-control" type="text" id="mobileNo" name="mobileNo" placeholder="01XXXXXXXXX">
                    </div>
                </div>

                <div class="col-12">
                    <label for="photo" class="form-label">ফটো</label>
                    <input class="form-control" type="file" id="photo" name="photo" accept="image/jpeg,image/png">
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>JPEG বা PNG ফরম্যাট সমর্থিত</small>
                </div>
            </div>

            <!-- ───── Section 2: Workplace ───── -->
            <div class="emp-section-header" data-color="green">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মক্ষেত্র</h6>
                    <span class="section-sub">কেন্দ্র এবং শাখা নির্বাচন করুন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-building"></i></span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="organization_id">কেন্দ্র <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="organization_id" id="organization_id" required>
                        <option value="">-- কেন্দ্র নির্বাচন করুন --</option>
                        <?php while($orgRow = mysqli_fetch_array($getAllOrg)): ?>
                            <option value="<?= $orgRow['id'] ?>"><?= htmlspecialchars($orgRow['organization_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="section_id">শাখা <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="section_id" id="section_id" required>
                        <option value="">-- শাখা নির্বাচন করুন --</option>
                        <?php while($secRow = mysqli_fetch_array($getAllSectionsQ)): ?>
                            <option value="<?= $secRow['id'] ?>"><?= htmlspecialchars($secRow['section_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- ───── Section 3: Salary ───── -->
            <div class="emp-section-header" data-color="amber">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">বেতন সংক্রান্ত তথ্য</h6>
                    <span class="section-sub">বেতন স্কেল, মূল বেতন ও রিপোর্ট ক্যাটাগরি</span>
                </div>
                <span class="section-icon"><i class="ti tabler-currency-taka"></i></span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="pay_scale">বর্তমান বেতন স্কেল <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="pay_scale" id="pay_scale" required>
                        <option value="">-- বেতন স্কেল নির্বাচন করুন --</option>
                        <?php while($sRow = mysqli_fetch_array($getScalesQ)): ?>
                            <option value="<?= $sRow['id'] ?>"><?= $obj->engToBn($sRow['minimum_salary']) ." - ". $obj->engToBn($sRow['maximum_salary']) ." (". htmlspecialchars($sRow['grade_title']) .")" ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="basic_salary">বর্তমান মূল বেতন <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-cash"></i></span>
                        <input class="form-control" type="text" id="basic_salary" name="basic_salary" placeholder="মূল বেতন" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="salary_group_id">রিপোর্ট ক্যাটাগরি <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="salary_group_id" id="salary_group_id" required>
                        <option value="">-- ক্যাটাগরি নির্বাচন করুন --</option>
                        <?php while($sgRow = mysqli_fetch_array($getAllGroupQ)): ?>
                            <option value="<?= $sgRow['id'] ?>"><?= htmlspecialchars($sgRow['head']) ." => ". htmlspecialchars($sgRow['sub_head']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="display_order">ডিসপ্লে অর্ডার</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-list-numbers"></i></span>
                        <input class="form-control" type="number" id="display_order" name="display_order" placeholder="তালিকায় অবস্থান">
                    </div>
                </div>
            </div>

            <!-- Submit Actions -->
            <div class="emp-form-actions d-flex gap-2 justify-content-end flex-wrap">
                <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </a>
                <button type="submit" name="submit" id="submit" class="btn btn-primary px-4">
                    <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                </button>
            </div>

        </div>
    </div>
</form>

<?php
// Capture page scripts using output buffer to avoid quote escaping issues
ob_start();
?>
<script>
jQuery(function($) {
    // Flatpickr for date fields
    try {
        $(".flatpickr-validation").each(function() {
            if (typeof flatpickr !== "undefined" && !$(this).hasClass("flatpickr-input")) {
                $(this).flatpickr({
                    enableTime: false,
                    dateFormat: "Y-m-d",
                    allowInput: true
                });
            }
        });
    } catch(e) {
        console.error("Flatpickr init error:", e);
    }

    // Entry type toggle (probationary vs permanent)
    function applyEntryType() {
        var t = $('input[name="entry_type"]:checked').val() || 'permanent';
        var isProb = (t === 'probationary');

        // Show appropriate labels
        $('[data-label-permanent]').toggle(!isProb);
        $('[data-label-probationary]').toggle(isProb);
        $('[data-probationary-only]').toggle(isProb);

        // employee_id: not required for probationary (auto-generated server-side); make read-only
        var $eid = $('#employee_id');
        if (isProb) {
            $eid.prop('required', false).prop('readonly', true)
                .val('').attr('placeholder', 'সংরক্ষণে অটো জেনারেট হবে');
        } else {
            $eid.prop('required', true).prop('readonly', false)
                .attr('placeholder', 'কর্মচারী আইডি');
        }

        // memorialNo not required for probationary
        $('#memorialNo').prop('required', !isProb);
        // basic_salary not required for probationary
        $('#basic_salary').prop('required', !isProb);
    }
    $(document).on('change', 'input[name="entry_type"]', applyEntryType);
    applyEntryType();

    // Form submission
    var form = $('#form');
    var submit = $('#submit');
    var originalButtonText = '<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন';

    form.on('submit', function(e) {
        e.preventDefault();
        submit.prop('disabled', true);

        $.ajax({
            url: '../../api/employees/insert.php',
            type: 'POST',
            dataType: 'text',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                submit.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>সংরক্ষণ হচ্ছে...');
            },
            success: function(data) {
                var response = $.trim(data);

                if (response == '0' || response == '' || response === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ত্রুটি',
                        text: 'সব আবশ্যক ফিল্ড পূরণ করুন এবং আবার চেষ্টা করুন।',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'সম্পন্ন',
                        text: 'কর্মকর্তা/কর্মচারীর তথ্য সফলভাবে সংরক্ষণ হয়েছে',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(function() {
                        form.trigger('reset');
                        $('.select2').each(function() {
                            $(this).val('').trigger('change');
                        });
                    });
                }

                submit.prop('disabled', false);
                submit.html(originalButtonText);
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', status, error);
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

    // Photo file validation — restrict to JPEG/PNG
    var photoElement = document.getElementById('photo');
    if (photoElement) {
        photoElement.addEventListener('change', function(e) {
            var allowedFileTypes = ['image/jpeg', 'image/png'];
            var file = e.target.files[0];
            if (file && !allowedFileTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'অবৈধ ফাইল',
                    text: 'অনুগ্রহ করে JPEG বা PNG ফাইল নির্বাচন করুন।',
                    confirmButtonColor: '#6c5ce7',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                photoElement.value = '';
            }
        });
    }
});
</script>
<?php
define('PAGE_SCRIPTS', ob_get_clean());

require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>
