<?php
include(__DIR__ . '/includes/header_vuexy.php');

function Bengali_DTN($NRS){
	$englDTN = array
			('1','2','3','4','5','6','7','8','9','0',
			'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday',
			'Sat','Sun','Mon','Tue','Wed','Thu','Fri',
			'am','pm','at','st','nd','rd','th',
			'January','February','March','April','May','June','July','August','September','October','November','December',
			'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
			$bangDTN = array
			('১','২','৩','৪','৫','৬','৭','৮','৯','০',
			'শনিবার','রবিবার','সোমবার','মঙ্গলবার','বুধবার','বৃহস্পতিবার','শুক্রবার',
			'শনি','রবি','সোম','মঙ্গল','বুধ','বৃহঃ','শুক্র',
			'পূর্বাহ্ণ','অপরাহ্ণ','','','','','',
			'জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর',
			'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে');
			$converted = str_replace($bangDTN, $englDTN, $NRS);
			return $converted;
			}

$getEmployeeListQ = mysqli_query($con, "select * from employee_list where employment_status=1");

// Fetch user groups for dropdown
$getUserGroupsQ = mysqli_query($con, "SELECT * FROM user_group WHERE deleted = 0 ORDER BY display_order ASC");
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold">নতুন ব্যবহারকারী এন্ট্রি</h4>
    </div>
</div>

<!-- User Form Card -->
<div class="card">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form">
            <!-- Personal Information Section -->
            <div class="row mb-3">
                <div class="col-12">
                    <h5 class="card-title mb-4"><i class="ti tabler-user me-2"></i>ব্যক্তিগত তথ্য</h5>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="full_name">
                    পূর্ণ নাম <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <input type="text" id="full_name" class="form-control" placeholder="পূর্ণ নাম লিখুন" name="full_name" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    কর্মচারী আইডি <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single" style="width: 100%;" name="employeeID" required>
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                        <?php while($empRow = mysqli_fetch_array($getEmployeeListQ)){ ?>
                        <option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name']; ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="designation">পদবী</label>
                <div class="col-md-9">
                    <input type="text" id="designation" class="form-control" placeholder="পদবী লিখুন" name="designation">
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="email">ইমেইল</label>
                <div class="col-md-9">
                    <input type="email" id="email" class="form-control" placeholder="ইমেইল লিখুন" name="email">
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="mobile">মোবাইল</label>
                <div class="col-md-9">
                    <input type="text" id="mobile" class="form-control" placeholder="মোবাইল নম্বর লিখুন" name="mobile">
                </div>
            </div>

            <!-- Account Details Section -->
            <div class="row mb-3 mt-4">
                <div class="col-12">
                    <h5 class="card-title mb-4"><i class="ti tabler-lock me-2"></i>অ্যাকাউন্টের তথ্য</h5>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="user_id">
                    ইউজারনেম <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <input type="text" id="user_id" class="form-control" placeholder="ইউজারনেম লিখুন" name="user_id" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="password">
                    পাসওয়ার্ড <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <input type="password" id="password" class="form-control" placeholder="পাসওয়ার্ড লিখুন" name="password" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="user_group_id">
                    ব্যবহারকারী গ্রুপ <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select id="user_group_id" class="form-control select2" name="user_group_id" required>
                        <option value="">-- গ্রুপ নির্বাচন করুন --</option>
                        <?php while($groupRow = mysqli_fetch_array($getUserGroupsQ)){ ?>
                        <option value='<?php echo $groupRow['id']; ?>'><?php echo htmlspecialchars($groupRow['group_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 text-end">
                    <a href="manage_user.php?menuslug=<?php echo $_GET['menuslug'] ?? 'manage-user'; ?>" class="btn btn-label-secondary me-2" data-turbo="true">
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
// Initialize Select2
$('.js-example-basic-single').select2({
    placeholder: "-- কর্মচারী নির্বাচন করুন --",
    allowClear: true
});

$('#user_group_id').select2({
    placeholder: "-- গ্রুপ নির্বাচন করুন --",
    allowClear: true
});

$(document).ready(function(){
    $('#form').on("submit", function(e){
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: 'insert_user_data.php',
            data: new FormData(this),
            dataType: 'html',
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function(){
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>সংরক্ষণ হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(data){
                $('.statusMsg').html('');
                if(data == 0){
                    $('.statusMsg').html('<div class="alert alert-danger alert-dismissible" role="alert">একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    $('.statusMsg').show();

                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'ব্যবহারকারী সংরক্ষণ করতে ব্যর্থ হয়েছে!',
                        icon: 'error',
                        confirmButtonColor: '#696cff',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                } else {
                    $('.statusMsg').html('<div class="alert alert-success alert-dismissible" role="alert">ব্যবহারকারী সফলভাবে সংরক্ষণ করা হয়েছে<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
                    $('.statusMsg').show();

                    Swal.fire({
                        title: 'সফল!',
                        text: 'ব্যবহারকারী সফলভাবে সংরক্ষণ করা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#696cff',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        window.location.href = 'fetch_user_access_record?menuslug=user_management&rowid=' + data;
                    });
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
