<?php
include(__DIR__ . '/includes/header_vuexy.php');

// Get user group ID from query string
$dataID = isset($_GET['dataID']) ? intval($_GET['dataID']) : 0;

if ($dataID === 0) {
    echo "<script>
        Swal.fire({
            title: 'ত্রুটি!',
            text: 'অবৈধ তথ্য আইডি!',
            icon: 'error',
            confirmButtonColor: '#696cff',
            customClass: {
                confirmButton: 'btn btn-danger'
            },
            buttonsStyling: false
        }).then(() => {
            window.location='manage_user_group.php?menuslug=" . htmlspecialchars($_GET['menuslug'] ?? 'manage-user-group') . "';
        });
    </script>";
    exit;
}

// Check if 'deleted' column exists in user_group table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM user_group LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

// Fetch user group details using prepared statement
if ($hasDeletedColumn) {
    $stmt = $con->prepare("SELECT * FROM user_group WHERE id = ? AND deleted = 0");
} else {
    $stmt = $con->prepare("SELECT * FROM user_group WHERE id = ?");
}

$stmt->bind_param("i", $dataID);
$stmt->execute();
$result = $stmt->get_result();
$userGroupData = $result->fetch_assoc();
$stmt->close();

if (!$userGroupData) {
    echo "<script>
        Swal.fire({
            title: 'ত্রুটি!',
            text: 'ব্যবহারকারী গ্রুপ খুঁজে পাওয়া যায়নি!',
            icon: 'error',
            confirmButtonColor: '#696cff',
            customClass: {
                confirmButton: 'btn btn-danger'
            },
            buttonsStyling: false
        }).then(() => {
            window.location='manage_user_group.php?menuslug=" . htmlspecialchars($_GET['menuslug'] ?? 'manage-user-group') . "';
        });
    </script>";
    exit;
}
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold">ব্যবহারকারী গ্রুপ সম্পাদনা করুন</h4>
    </div>
</div>

<!-- User Group Edit Form Card -->
<div class="card">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form">
            <input type="hidden" name="dataID" value="<?php echo $dataID; ?>">

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
                    <input type="text" id="group_name" class="form-control" value="<?php echo htmlspecialchars($userGroupData['group_name']); ?>" name="group_name" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="display_order">ক্রমিক নং</label>
                <div class="col-md-9">
                    <input type="number" id="display_order" class="form-control" placeholder="ক্রমিক নম্বর লিখুন" name="display_order" value="<?php echo htmlspecialchars($userGroupData['display_order']); ?>">
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
            url: 'edit_user_group_data.php',
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
