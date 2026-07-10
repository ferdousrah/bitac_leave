<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-center');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-building-plus me-2 text-primary"></i>কেন্দ্র যোগ করুন</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>নতুন কেন্দ্রের তথ্য প্রবেশ করান</div>
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

<!-- Center Form Card -->
<div class="card simple-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form">
            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-building"></i></span>
                <h6 class="section-title">কেন্দ্রের তথ্য</h6>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="organization_name">
                    কেন্দ্রের নাম <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-building"></i></span>
                        <input type="text" id="organization_name" class="form-control" placeholder="কেন্দ্রের নাম লিখুন" name="organization_name" required>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="address">ঠিকানা</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-map-pin"></i></span>
                        <textarea id="address" class="form-control" placeholder="ঠিকানা লিখুন" name="address" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="phone">ফোন নম্বর</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-phone"></i></span>
                        <input type="text" id="phone" class="form-control" placeholder="ফোন নম্বর লিখুন" name="phone">
                    </div>
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
            url: '../../api/center/insert.php',
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
                $('.statusMsg').html('');
                if(response.status == 1){
                    $('#form')[0].reset();
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
