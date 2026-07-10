<?php
include(__DIR__ . '/includes/header_vuexy.php');

$dataID = base64_decode($_GET['dataID']);

$getEmployeeInfoQ = mysqli_query($con, "SELECT * FROM `employee_list` WHERE `id`='$dataID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeInfoQ);

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

?>

<form class="form-login" name="form" id="form" enctype="multipart/form-data">
    <input type="hidden" name="dataID" value="<?php echo $dataID; ?>" />
    <input type="hidden" name="prevPhoto" value="<?php echo $getEmployeeInfoQRW['photo']; ?>" />
    <input type="hidden" name="prevsignature" value="<?php echo base64_encode($getEmployeeInfoQRW['signature']); ?>" />

    <div class="row g-6 mb-6">
        <!-- FormValidation -->
        <div class="col-12">
            <div class="card">
                <div class="card-header sticky-element bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
                    <h5 class="card-title mb-sm-0 me-2">কর্মকর্তা / কর্মচারীর তথ্য পরিবর্তন</h5>
                    <div class="action-btns">
                        <button type="button" onclick="window.history.go(-1); return false;" class="btn btn-label-warning me-4">
                            <span class="align-middle"><i class="ti tabler-arrow-left me-1"></i> Back</span>
                        </button>
                        <button type="submit" name="submit" id="submit" class="btn btn-primary">
                            <i class="ti tabler-device-floppy me-1"></i> Update
                        </button>
                    </div>
                </div>
                <div class="card-body" style="padding-top: 20px;">
                    <div class="row g-6">
                        <!-- Personal Info Section -->
                        <div class="col-12">
                            <h6>১. ব্যক্তিগত তথ্য</h6>
                            <hr class="mt-0" />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="employee_name">নাম <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="employee_name"
                                class="form-control"
                                placeholder=""
                                name="employee_name"
                                value="<?php echo htmlspecialchars($getEmployeeInfoQRW['employee_name']); ?>"
                                required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="nid">জাতীয় পরিচয়পত্র নং <span class="text-danger">*</span></label>
                            <input
                                class="form-control"
                                type="text"
                                id="nid"
                                name="nid"
                                placeholder=""
                                value="<?php echo htmlspecialchars($getEmployeeInfoQRW['nationalID']); ?>"
                                required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="designation">পদবি <span class="text-danger">*</span></label>
                            <select data-placeholder="Choose a designation..." class="select2 form-select" name="designation" id="designation" required>
                                <option value="">--পদবি নির্বাচন করুন--</option>
                                <?php
                                while($degRow = mysqli_fetch_array($getAllDesig)) {
                                ?>
                                <option <?php if($getEmployeeInfoQRW['designation'] == $degRow['id']) { echo "selected"; } ?> value="<?php echo $degRow['id']; ?>"><?php echo $degRow['job_title_name']; ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="employee_id">আইডি <span class="text-danger">*</span></label>
                            <input
                                class="form-control"
                                type="text"
                                id="employee_id"
                                name="employee_id"
                                placeholder=""
                                value="<?php echo htmlspecialchars($getEmployeeInfoQRW['employee_id']); ?>"
                                required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="memorialNo">স্মারক নম্বর <span class="text-danger">*</span></label>
                            <input
                                class="form-control"
                                type="text"
                                id="memorialNo"
                                name="memorialNo"
                                placeholder=""
                                value="<?php echo htmlspecialchars($getEmployeeInfoQRW['memorialNo']); ?>"
                                required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="employee_type">চাকরীর ধরন <span class="text-danger">*</span></label>
                            <select class="select2 form-select" name="employee_type" id="employee_type" required>
                                <option value="">--ধরন নির্বাচন করুন--</option>
                                <option <?php if($getEmployeeInfoQRW['employee_type'] == 1) { echo "selected"; } ?> value="1">কর্মকর্তা</option>
                                <option <?php if($getEmployeeInfoQRW['employee_type'] == 2) { echo "selected"; } ?> value="2">কর্মচারী</option>
                                <option <?php if($getEmployeeInfoQRW['employee_type'] == 3) { echo "selected"; } ?> value="3">পি আর এল</option>
                            </select>
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="dob">জন্ম তারিখ <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control flatpickr-validation"
                                id="dob"
                                name="dob"
                                value="<?php echo $getEmployeeInfoQRW['date_of_birth']; ?>"
                                autocomplete="off"
                                required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="joining_date">চাকরিতে যোগদানের তারিখ <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control flatpickr-validation"
                                id="joining_date"
                                name="joining_date"
                                value="<?php echo $getEmployeeInfoQRW['joining_date']; ?>"
                                autocomplete="off"
                                required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="email">ইমেইল</label>
                            <input
                                class="form-control"
                                type="email"
                                id="email"
                                name="email"
                                placeholder=""
                                value="<?php echo htmlspecialchars($getEmployeeInfoQRW['email']); ?>" />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="mobileNo">মোবাইল নম্বর</label>
                            <input
                                class="form-control"
                                type="text"
                                id="mobileNo"
                                name="mobileNo"
                                placeholder=""
                                value="<?php echo htmlspecialchars($getEmployeeInfoQRW['mobileNo']); ?>" />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label for="photo" class="form-label">ফটো</label>
                            <input class="form-control" type="file" id="photo" name="photo" />
                            <small class="text-muted">Please choose a JPEG or PNG file (max 1MB)</small>
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label">বর্তমান ফটো</label>
                            <div>
                                <?php if(!empty($getEmployeeInfoQRW['photo'])) { ?>
                                <img src="uploads/<?php echo $getEmployeeInfoQRW['photo']; ?>" height="80" class="rounded border" />
                                <?php } else { ?>
                                <span class="text-muted">কোন ফটো নেই</span>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label for="signature" class="form-label">স্বাক্ষর</label>
                            <input class="form-control" type="file" id="signature" name="signature" />
                            <small class="text-muted">Please choose a JPEG or PNG file</small>
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label">বর্তমান স্বাক্ষর</label>
                            <div>
                                <?php if(!empty($getEmployeeInfoQRW['signature'])) { ?>
                                <img height="60" src="data:image/jpg;charset=utf8;base64,<?php echo base64_encode($getEmployeeInfoQRW['signature']); ?>" class="rounded border" />
                                <?php } else { ?>
                                <span class="text-muted">কোন স্বাক্ষর নেই</span>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Workplace Section -->
                        <div class="col-12">
                            <h6 class="mt-2">২. কর্মক্ষেত্র</h6>
                            <hr class="mt-0" />
                        </div>

                        <div class="col-md-12 form-control-validation">
                            <label class="form-label" for="organization_id">কেন্দ্র <span class="text-danger">*</span></label>
                            <select class="select2 form-select" name="organization_id" id="organization_id" required>
                                <option value=''>--কেন্দ্র নির্বাচন করুন--</option>
                                <?php while($orgRow = mysqli_fetch_array($getAllOrg)) { ?>
                                <option <?php if($getEmployeeInfoQRW['organization_id'] == $orgRow['id']) { echo "selected"; } ?> value='<?php echo $orgRow['id']; ?>'><?php echo $orgRow['organization_name']; ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-12 form-control-validation">
                            <label class="form-label" for="section_id">শাখা <span class="text-danger">*</span></label>
                            <select class="select2 form-select" name="section_id" id="section_id" required>
                                <option value=''>--শাখা নির্বাচন করুন--</option>
                                <?php while($secRow = mysqli_fetch_array($getAllSectionsQ)) { ?>
                                <option <?php if($getEmployeeInfoQRW['section_id'] == $secRow['id']) { echo "selected"; } ?> value='<?php echo $secRow['id']; ?>'><?php echo $secRow['section_name']; ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- Salary Section -->
                        <div class="col-12">
                            <h6 class="mt-2">৩. বেতন সংক্রান্ত তথ্য</h6>
                            <hr class="mt-0" />
                        </div>

                        <div class="col-md-12 form-control-validation">
                            <label class="form-label" for="pay_scale">বর্তমান বেতন স্কেল <span class="text-danger">*</span></label>
                            <select class="select2 form-select" name="pay_scale" id="pay_scale" required>
                                <option value="">--বেতন স্কেল নির্বাচন করুন--</option>
                                <?php while($sRow = mysqli_fetch_array($getScalesQ)) { ?>
                                <option <?php if($getEmployeeInfoQRW['pay_scale'] == $sRow['id']) { echo "selected"; } ?> value="<?php echo $sRow['id']; ?>"><?php echo $obj->engToBn($sRow['minimum_salary'])." - ".$obj->engToBn($sRow['maximum_salary'])."(".$sRow['grade_title'].")"; ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="basic_salary">বর্তমান মূল বেতন <span class="text-danger">*</span></label>
                            <input
                                class="form-control"
                                type="text"
                                id="basic_salary"
                                name="basic_salary"
                                placeholder=""
                                value="<?php echo htmlspecialchars($getEmployeeInfoQRW['basic_salary']); ?>"
                                required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                            <label class="form-label" for="display_order">ডিসপ্লে অর্ডার</label>
                            <input
                                class="form-control"
                                type="number"
                                id="display_order"
                                name="display_order"
                                placeholder=""
                                value="<?php echo $getEmployeeInfoQRW['display_order']; ?>" />
                        </div>

                        <div class="col-md-12 form-control-validation">
                            <label class="form-label" for="salary_group_id">রিপোর্ট ক্যাটাগরি <span class="text-danger">*</span></label>
                            <select class="select2 form-select" name="salary_group_id" id="salary_group_id" required>
                                <option value="">--ক্যাটাগরি নির্বাচন করুন--</option>
                                <?php
                                while($sgRow = mysqli_fetch_array($getAllGroupQ)) {
                                ?>
                                <option <?php if($getEmployeeInfoQRW['salary_group_id'] == $sgRow['id']) { echo "selected"; } ?> value="<?php echo $sgRow['id']; ?>"><?php echo $sgRow['head']."=>".$sgRow['sub_head']; ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Employment Status Section -->
                        <div class="col-12">
                            <h6 class="mt-2">৪. কর্মসংস্থানের অবস্থা</h6>
                            <hr class="mt-0" />
                        </div>

                        <div class="col-md-12 form-control-validation">
                            <label class="form-label">কর্মসংস্থানের অবস্থা <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="employment_status" id="status_active" value="1" <?php if($getEmployeeInfoQRW['employment_status'] == 1) { echo "checked"; } ?> />
                                    <label class="form-check-label" for="status_active">চাকরিরত</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="employment_status" id="status_inactive" value="0" <?php if($getEmployeeInfoQRW['employment_status'] == 0) { echo "checked"; } ?> />
                                    <label class="form-check-label" for="status_inactive">কর্মরত নন</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="employment_status" id="status_prl" value="2" <?php if($getEmployeeInfoQRW['employment_status'] == 2) { echo "checked"; } ?> />
                                    <label class="form-check-label" for="status_prl">পি আর এল</label>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- /FormValidation -->
    </div>
</form>

<?php
// Define page-specific scripts
define('PAGE_SCRIPTS', '
<script>
jQuery(function($) {
    console.log("=== Edit Employee Page Init Start ===");

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
        console.log("Flatpickr initialized");
    } catch(e) {
        console.error("Flatpickr init error:", e);
    }

    console.log("=== Edit Employee Page Init End ===");
});
</script>
');

include(__DIR__ . '/includes/footer_vuexy.php');
?>

<script>
$(document).ready(function() {
    var form = $('#form');
    var submit = $('#submit');
    var originalButtonText = submit.html();

    // form submit event
    form.on('submit', function(e) {
        e.preventDefault();

        // Disable submit button to prevent double submission
        submit.prop('disabled', true);

        // sending ajax request through jQuery
        $.ajax({
            url: 'edit_employee_data.php',
            type: 'POST',
            dataType: 'text',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                submit.html('<i class="ti tabler-loader ti-spin me-1"></i> Updating...');
            },
            success: function(data) {
                console.log('Server response:', data);

                // Trim whitespace from response
                var response = $.trim(data);

                if(response == '0' || response == '' || response === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Please check all required fields and try again.',
                        confirmButtonColor: '#696cff'
                    });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Data Updated Successfully',
                        confirmButtonColor: '#696cff'
                    }).then(function() {
                        // Optionally redirect to employee list
                        // window.location.href = 'manage_employees.php?menuslug=manage-employees';
                    });
                }

                // Re-enable submit button and reset text
                submit.prop('disabled', false);
                submit.html(originalButtonText);
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', status, error);
                console.log('Response:', xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error!',
                    text: 'Please try again later.',
                    confirmButtonColor: '#696cff'
                });

                // Re-enable submit button and reset text
                submit.prop('disabled', false);
                submit.html(originalButtonText);
            }
        });
    });
});

// Photo file validation
const photoElement = document.getElementById('photo');
if (photoElement) {
    photoElement.addEventListener('change', function(e) {
        const allowedFileTypes = ['image/jpeg', 'image/png'];
        const maxFileSize = 1024 * 1024; // 1MB in bytes
        const file = e.target.files[0];

        if (file) {
            if (!allowedFileTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid File Type',
                    text: 'Please choose a JPEG or PNG file.',
                    confirmButtonColor: '#696cff'
                });
                e.target.value = '';
                return;
            }

            if (file.size > maxFileSize) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: 'Please choose a file smaller than 1MB.',
                    confirmButtonColor: '#696cff'
                });
                e.target.value = '';
                return;
            }

            console.log('Photo file is valid');
        }
    });
}

// Signature file validation
const signatureElement = document.getElementById('signature');
if (signatureElement) {
    signatureElement.addEventListener('change', function(e) {
        const allowedFileTypes = ['image/jpeg', 'image/png'];
        const maxFileSize = 1024 * 1024; // 1MB in bytes
        const file = e.target.files[0];

        if (file) {
            if (!allowedFileTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid File Type',
                    text: 'Please choose a JPEG or PNG file.',
                    confirmButtonColor: '#696cff'
                });
                e.target.value = '';
                return;
            }

            if (file.size > maxFileSize) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: 'Please choose a file smaller than 1MB.',
                    confirmButtonColor: '#696cff'
                });
                e.target.value = '';
                return;
            }

            console.log('Signature file is valid');
        }
    });
}
</script>
