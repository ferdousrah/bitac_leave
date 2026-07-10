<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Get designation ID from query string
$dataID   = isset($_GET['dataID']) ? intval($_GET['dataID']) : 0;
$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-designations');

if ($dataID === 0) {
    echo "<script>
        Swal.fire({
            title: 'ত্রুটি',
            text: 'অবৈধ তথ্য আইডি!',
            icon: 'error',
            confirmButtonColor: '#ff3e1d',
            customClass: { confirmButton: 'btn btn-danger' },
            buttonsStyling: false
        }).then(() => {
            window.location='manage.php?menuslug={$menuslug}';
        });
    </script>";
    exit;
}

// Check if 'deleted' column exists in job_title table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM job_title LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

// Fetch designation details using prepared statement
if ($hasDeletedColumn) {
    $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ? AND deleted = 0");
} else {
    $stmt = $con->prepare("SELECT * FROM job_title WHERE id = ?");
}

$stmt->bind_param("i", $dataID);
$stmt->execute();
$result = $stmt->get_result();
$designationData = $result->fetch_assoc();
$stmt->close();

if (!$designationData) {
    echo "<script>
        Swal.fire({
            title: 'ত্রুটি',
            text: 'পদবী খুঁজে পাওয়া যায়নি!',
            icon: 'error',
            confirmButtonColor: '#ff3e1d',
            customClass: { confirmButton: 'btn btn-danger' },
            buttonsStyling: false
        }).then(() => {
            window.location='manage.php?menuslug={$menuslug}';
        });
    </script>";
    exit;
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-edit me-2 text-primary"></i>পদবী সম্পাদনা</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i><strong class="text-dark"><?= htmlspecialchars($designationData['job_title_name']) ?></strong> এর তথ্য সম্পাদনা</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.simple-form-card { border-radius: 0.75rem; }
.simple-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .simple-form-card .card-body { padding: 1rem; }
}
.simple-form-card .form-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #eef0f5;
}
.simple-form-card .section-icon-tile {
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
.simple-form-card .section-title {
    margin: 0;
    color: #2c2e3a;
    font-size: 1rem;
    font-weight: 600;
}
.simple-form-card .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.simple-form-card .form-control:focus,
.simple-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.simple-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}
.simple-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 0.5rem;
}
</style>

<!-- Designation Edit Form Card -->
<div class="card simple-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form">
            <input type="hidden" name="dataID" value="<?= $dataID ?>">

            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-id-badge-2"></i></span>
                <h6 class="section-title">পদবীর তথ্য</h6>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="job_title_name">
                    পদবীর নাম <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-id-badge-2"></i></span>
                        <input type="text" id="job_title_name" class="form-control" value="<?= htmlspecialchars($designationData['job_title_name']) ?>" name="job_title_name" required>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="display_order">ক্রমিক নং</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-list-numbers"></i></span>
                        <input type="number" id="display_order" class="form-control" placeholder="ক্রমিক নম্বর লিখুন" name="display_order" value="<?= htmlspecialchars($designationData['display_order']) ?>">
                    </div>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>তালিকায় পদবীর অবস্থান নির্ধারণ করে</small>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="simple-form-actions d-flex gap-2 justify-content-end">
                <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </a>
                <button type="submit" class="btn btn-primary submitBtn px-4">
                    <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>

<script>
$(document).ready(function(){
    $('#form').on("submit", function(e){
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: '../../api/designation/update.php',
            data: new FormData(this),
            dataType: 'json',
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function(){
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>সংরক্ষণ হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(response){
                if(response.status == 1){
                    Swal.fire({
                        title: 'সম্পন্ন',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(() => {
                        window.location.href = 'manage.php?menuslug=<?= $menuslug ?>';
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
                $('.submitBtn').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
            }
        });
    });
});
</script>
