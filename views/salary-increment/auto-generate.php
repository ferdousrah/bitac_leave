<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$incrementYear = date('Y');

$getisGenerateOrNotQ = mysqli_query($con, "SELECT * FROM yearly_salary_increment WHERE incrementYear='$incrementYear' LIMIT 0,1");
$getisGenerateOrNotQNumRows = $getisGenerateOrNotQ ? mysqli_num_rows($getisGenerateOrNotQ) : 0;
$alreadyGenerated = ($getisGenerateOrNotQNumRows > 0) ? mysqli_fetch_assoc($getisGenerateOrNotQ) : null;

$getAllEmployeeQ = mysqli_query($con, "SELECT * FROM employee_list WHERE employment_status=1 OR employment_status=2 ORDER BY employee_name");

function Bengali_DTN($NRS){
    $englDTN = array(
        '1','2','3','4','5','6','7','8','9','0',
        'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday',
        'Sat','Sun','Mon','Tue','Wed','Thu','Fri',
        'am','pm','at','st','nd','rd','th',
        'January','February','March','April','May','June','July','August','September','October','November','December',
        'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'
    );
    $bangDTN = array(
        '১','২','৩','৪','৫','৬','৭','৮','৯','০',
        'শনিবার','রবিবার','সোমবার','মঙ্গলবার','বুধবার','বৃহস্পতিবার','শুক্রবার',
        'শনি','রবি','সোম','মঙ্গল','বুধ','বৃহঃ','শুক্র',
        'পূর্বাহ্ণ','অপরাহ্ণ','','','','','',
        'জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর',
        'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে'
    );
    $converted = str_replace($bangDTN, $englDTN, $NRS);
    return $converted;
}

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'auto-generate-salary-increment-form');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-bolt me-2 text-primary"></i>স্যালারি ইনক্রিমেন্ট অটো জেনারেট</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>বছরভিত্তিক বেতন বৃদ্ধি স্বয়ংক্রিয়ভাবে তৈরি করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.autogen-form-card { border-radius: 0.75rem; }
.autogen-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .autogen-form-card .card-body { padding: 1rem; }
}

/* Warning banner — already generated */
.autogen-warning {
    background: #fff7ed;
    border: 1px solid #ffe4b8;
    border-left: 3px solid #d4a056;
    border-radius: 0.6rem;
    padding: 14px 18px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}
.autogen-warning .warn-icon {
    width: 38px; height: 38px;
    background: #fff;
    color: #b8651a;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    border: 1px solid #ffe4b8;
}
.autogen-warning .warn-title {
    color: #8b6f47;
    font-weight: 600;
    font-size: 0.92rem;
    margin: 0;
}
.autogen-warning .warn-body {
    color: #8b6f47;
    font-size: 0.86rem;
    margin-top: 0.25rem;
}

.autogen-form-card .form-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #eef0f5;
}
.autogen-form-card .section-icon-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.5rem;
    font-size: 1.05rem;
}
.autogen-form-card .section-title {
    margin: 0;
    color: #2c2e3a;
    font-size: 1rem;
    font-weight: 600;
}
.autogen-form-card .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.autogen-form-card .form-control:focus,
.autogen-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.autogen-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}
.autogen-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 0.5rem;
}
</style>

<!-- Warning if already generated -->
<?php if ($alreadyGenerated): ?>
<div class="autogen-warning">
    <span class="warn-icon"><i class="ti tabler-alert-triangle"></i></span>
    <div>
        <div class="warn-title">এই বছরের বেতন বৃদ্ধি ইতিমধ্যে তৈরি করা হয়েছে</div>
        <div class="warn-body">
            <i class="ti tabler-clock me-1"></i>সর্বশেষ জেনারেট:
            <strong><?= htmlspecialchars($alreadyGenerated['genDateTime']) ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Auto Generate Form Card -->
<div class="card autogen-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-horizontal" name="form" id="form" enctype="multipart/form-data">
            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-currency-taka"></i></span>
                <h6 class="section-title">বেতন বৃদ্ধি তথ্য</h6>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="incrementYear">
                    বেতন বৃদ্ধির বছর <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-calendar"></i></span>
                        <select class="form-select" id="incrementYear" name="incrementYear" required>
                            <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    কর্মচারী <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single" style="width: 100%;" name="employeeID" id="employeeID" required>
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                        <?php
                        if ($getAllEmployeeQ) {
                            while($empRow = mysqli_fetch_array($getAllEmployeeQ)){
                                $getSupervisorDesigQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='" . intval($empRow['designation']) . "'");
                                $getSupervisorDesigQRW = mysqli_fetch_assoc($getSupervisorDesigQ);
                        ?>
                        <option value='<?= $empRow['id'] ?>'>
                            <?= Bengali_DTN($empRow['employee_id']) . ' - ' . htmlspecialchars($empRow['employee_name']) . ', ' . htmlspecialchars($getSupervisorDesigQRW['job_title_name'] ?? '') ?>
                        </option>
                        <?php
                            }
                        }
                        ?>
                        <option value='0'>সকল কর্মচারী</option>
                    </select>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>একজন কর্মচারী বা সকল কর্মচারীর জন্য একসাথে তৈরি করুন</small>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="autogen-form-actions d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" name="submit" id="submit" class="btn btn-primary submitBtn px-4">
                    <i class="ti tabler-bolt me-1"></i>তৈরি করুন
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
    // Initialize Select2
    $('.js-example-basic-single').select2({
        placeholder: "-- কর্মচারী নির্বাচন করুন --",
        allowClear: true,
        width: '100%'
    });

    // Form submission
    $('#form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '../../api/salary-increment/auto-generate.php',
            type: 'POST',
            dataType: 'json',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>প্রক্রিয়াকরণ হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(response) {
                if(response.status == 1) {
                    Swal.fire({
                        title: 'সম্পন্ন',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-bolt me-1"></i>তৈরি করুন');
            },
            error: function(xhr, status, error) {
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-bolt me-1"></i>তৈরি করুন');

                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'বেতন বৃদ্ধি তৈরি করতে ব্যর্থ হয়েছে!',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
});
</script>
