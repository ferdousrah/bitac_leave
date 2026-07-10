<?php
include(__DIR__ . '/includes/header_vuexy.php');

$getLastDisplayOrderQ = mysqli_query($con, "select * from user_group order by display_order desc limit 0,1");
$getLastDisplayOrderQRW = mysqli_fetch_assoc($getLastDisplayOrderQ);
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold">নতুন ব্যবহারকারী গ্রুপ এন্ট্রি</h4>
    </div>
</div>

<!-- User Group Form Card -->
<div class="card">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form">
            <div class="row mb-3">
                <div class="col-12">
                    <h5 class="card-title mb-4"><i class="ti tabler-users-group me-2"></i>ব্যবহারকারী গ্রুপের তথ্য</h5>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="group_name">
                    গ্রুপের নাম <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <input type="text" id="group_name" class="form-control" placeholder="গ্রুপের নাম লিখুন" name="group_name" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="display_order">ক্রমিক নং</label>
                <div class="col-md-9">
                    <input type="number" id="display_order" class="form-control" placeholder="ক্রমিক নম্বর লিখুন" name="display_order" value="<?php echo ($getLastDisplayOrderQRW['display_order'] + 1); ?>">
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 text-end">
                    <a href="manage_user_group.php?menuslug=<?php echo $_GET['menuslug'] ?? 'manage-user-group'; ?>" class="btn btn-label-secondary me-2" data-turbo="true">
                        <i class="ti tabler-x me-1"></i>বাতিল করুন
                    </a>
                    <button type="submit" class="btn btn-primary submitBtn">
                        <i class="ti tabler-check me-1"></i>সংরক্ষণ করুন
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<script>
$(document).ready(function(){
    $('#form').on("submit", function(e){
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: 'insert_user_group_data.php',
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
                    $('.statusMsg').html('<div class="alert alert-success alert-dismissible" role="alert">' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    $('.statusMsg').show();

                    // Show success message with SweetAlert2
                    Swal.fire({
                        title: 'সফল!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#696cff',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        window.location.href = 'manage_user_group.php?menuslug=<?php echo $_GET['menuslug'] ?? 'manage-user-group'; ?>';
                    });
                } else {
                    $('.statusMsg').html('<div class="alert alert-danger alert-dismissible" role="alert">' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    $('.statusMsg').show();
                }
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-check me-1"></i>সংরক্ষণ করুন');
            },
            error: function(xhr, status, error) {
                $('.statusMsg').html('<div class="alert alert-danger alert-dismissible" role="alert">একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                $('.statusMsg').show();
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-check me-1"></i>সংরক্ষণ করুন');
            }
        });
    });
});
</script>
