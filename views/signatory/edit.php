<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$id = mysqli_real_escape_string($con, $_GET['dataID']);

$getSigInfoQ = mysqli_query($con, "SELECT * FROM leave_approval_signatory WHERE dataID='$id'");
$getSigInfoQRW = mysqli_fetch_assoc($getSigInfoQ);

if (!$getSigInfoQRW) {
    echo "<script>
        Swal.fire({
            title: 'Error!',
            text: 'Signatory not found!',
            icon: 'error'
        }).then(() => {
            window.history.go(-1);
        });
    </script>";
    exit();
}

$getAllOrgQ = mysqli_query($con, "SELECT * FROM organization ORDER BY organization_name ASC");
$getAllJobTitleQ = mysqli_query($con, "SELECT * FROM job_title ORDER BY job_title_name ASC");
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">সিগনেটরি আপডেট</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Edit Signatory Form Card -->
<div class="card">
    <div class="card-body">
        <form class="form-login" name="form" id="form">
            <input type="hidden" name="dataID" value="<?php echo htmlspecialchars($id); ?>" />

            <div class="row">
                <!-- Organization Selection -->
                <div class="col-12 mb-3">
                    <label class="form-label" for="organization_id">
                        কেন্দ্র সিলেক্ট করুন <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" name="organization_id" id="organization_id" required>
                        <option value=''>-- সিলেক্ট করুন --</option>
                        <?php while($orgRow = mysqli_fetch_array($getAllOrgQ)){ ?>
                        <option value='<?php echo $orgRow['id']; ?>' <?php if($orgRow['id'] == $getSigInfoQRW['organization_id']){ echo "selected"; } ?>>
                            <?php echo htmlspecialchars($orgRow['organization_name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Designation Selection -->
                <div class="col-12 mb-3">
                    <label class="form-label" for="designationID">
                        পদবী <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" name="designationID" id="designationID" required>
                        <option value=''>-- সিলেক্ট করুন --</option>
                        <?php while($jobRow = mysqli_fetch_array($getAllJobTitleQ)){ ?>
                        <option value='<?php echo $jobRow['id']; ?>' <?php if($jobRow['id'] == $getSigInfoQRW['designationID']){ echo "selected"; } ?>>
                            <?php echo htmlspecialchars($jobRow['job_title_name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Mandatory Field -->
                <div class="col-12 mb-4">
                    <label class="form-label">
                        বাধ্যতামূলক <span class="text-danger">*</span>
                    </label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="isMandatory" id="isMandatoryYes" value="1" <?php if($getSigInfoQRW['isMandatory'] == 1){ echo "checked"; } ?> required>
                            <label class="form-check-label" for="isMandatoryYes">
                                হ্যাঁ
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="isMandatory" id="isMandatoryNo" value="0" <?php if($getSigInfoQRW['isMandatory'] == 0){ echo "checked"; } ?> required>
                            <label class="form-check-label" for="isMandatoryNo">
                                না
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="col-12">
                    <button type="submit" name="submit" id="submit" class="btn btn-primary me-2">
                        <i class="ti tabler-device-floppy me-1"></i>Save
                    </button>
                    <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                        <i class="ti tabler-x me-1"></i>Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function() {
    var form = $('#form');
    var submit = $('#submit');

    // form submit event
    form.on('submit', function(e) {
        e.preventDefault(); // prevent default form submit

        // Validate form
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return false;
        }

        // sending ajax request through jQuery
        $.ajax({
            url: '../../api/signatory/update.php',
            type: 'POST',
            dataType: 'html',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                submit.html('<i class="spinner-border spinner-border-sm me-1"></i>Processing, please wait');
                submit.prop('disabled', true);
            },
            success: function(data) {
                if(data == 1) {
                    Swal.fire({
                        title: 'সফল!',
                        text: 'ডেটা সফলভাবে সংরক্ষণ করা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#28c76f',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        window.history.go(-1);
                    });
                } else if(data == 0) {
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'ডেটা সংরক্ষণ করতে ব্যর্থ হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: data,
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }

                submit.html('<i class="ti tabler-device-floppy me-1"></i>Save');
                submit.prop('disabled', false);
            },
            error: function(e) {
                console.log(e);
                Swal.fire({
                    title: 'ত্রুটি!',
                    text: 'ডেটা সংরক্ষণ করতে ব্যর্থ হয়েছে',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
                submit.html('<i class="ti tabler-device-floppy me-1"></i>Save');
                submit.prop('disabled', false);
            }
        });
    });
});
</script>
