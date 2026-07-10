<?php
include(__DIR__ . '/includes/header_vuexy.php');

$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list` where employment_status=1");

$getAllEmployeeListQ3 = mysqli_query($con,"select * from `employee_list` where employment_status=1");

$getAllLeaveTypesQ = mysqli_query($con, "select * from leave_types where leaveID!=22 order by leaveTitle asc");

$getUserDetailsQ = mysqli_query($con, "select * from user_list where dataID = '$_SESSION[userID]'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$getLeaveTemplatesQ = mysqli_query($con, "select * from leave_templates where templateType=1 order by templateData asc");

$getCurrentEmpDetailsQ = mysqli_query($con, "select * from `employee_list` where id='$getUserDetailsQRW[employee_id]'");
$getCurrentEmpDetailsQRW = mysqli_fetch_assoc($getCurrentEmpDetailsQ);

$getAllEmployeeListQ2 = mysqli_query($con,"select id, employee_id, employee_name, designation from `employee_list` where employment_status=1 and organization_id='$getCurrentEmpDetailsQRW[organization_id]' order by display_order asc");

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
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ছুটির আবেদনপত্র</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Leave Application Form Card -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="ti tabler-file-text me-2"></i>নতুন ছুটির আবেদন
        </h5>
    </div>
    <div class="card-body">
        <form class="form-login" name="form" id="form">
            <input type="hidden" name="organization_id" value="<?php echo $getCurrentEmpDetailsQRW['organization_id']; ?>" />

            <!-- Application Type Section -->
            <div class="row mb-3">
                <div class="col-12">
                    <h6 class="card-title mb-3"><i class="ti tabler-clipboard me-2"></i>আবেদনের তথ্য</h6>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="applicationType">
                    আবেদনের প্রকার <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="form-select" name="applicationType" id="applicationType" onChange="generateSubject()" required>
                        <option value=''>-- আবেদনের প্রকার নির্বাচন করুন --</option>
                        <option value='1'>নিয়মিত ছুটির আবেদন</option>
                        <option value='2'>অনুপস্থিতকালের জন্য ছুটির আবেদন</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3" id="isinformed" style="display: none;">
                <label class="col-md-3 col-form-label">
                    কর্তৃপক্ষকে পূর্বে অবগত <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="isinformedValue" id="informed_yes" value="1" />
                        <label class="form-check-label" for="informed_yes">করেছি</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="isinformedValue" id="informed_no" value="0" />
                        <label class="form-check-label" for="informed_no">করতে পারিনি</label>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="to">
                    বরাবর <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single-2" style="width: 100%;" name="to" id="to" required>
                        <option value=''>-- নির্বাচন করুন --</option>
                        <?php if($getCurrentEmpDetailsQRW['organization_id'] == 4){ ?>
                        <option selected value="1">পরিচালক (প্রশাসন ও অর্থ)</option>
                        <option value="2">মহাপরিচালক</option>
                        <?php }else if($getCurrentEmpDetailsQRW['organization_id'] == 5 || $getCurrentEmpDetailsQRW['organization_id'] == 6 || $getCurrentEmpDetailsQRW['organization_id'] == 7 || $getCurrentEmpDetailsQRW['organization_id'] == 8 || $getCurrentEmpDetailsQRW['organization_id'] == 9){ ?>
                        <option selected value="1">অতিরিক্ত পরিচালক (কেন্দ্র প্রধান)</option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    কর্মকর্তা/কর্মচারী নির্বাচন করুন <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="form-select" name="employeeID" id="employeeID" onChange="getEmployees(this.value)" required>
                        <option value='<?php echo $getUserDetailsQRW['employee_id']; ?>'>নিজ</option>
                        <option value='0'>পক্ষে</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3" id="employeeIDOnbehalfDiv" style="display: none;">
                <label class="col-md-3 col-form-label" for="employeeIDOnbehalf">&nbsp;</label>
                <div class="col-md-9">
                    <select class="employeeIDOnbehalf" style="width: 100%;" name="employeeIDOnbehalf" id="employeeIDOnbehalf">
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                    </select>
                </div>
            </div>

            <!-- Leave Details Section -->
            <div class="row mb-3 mt-4">
                <div class="col-12">
                    <h6 class="card-title mb-3"><i class="ti tabler-calendar me-2"></i>ছুটির বিবরণ</h6>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="leaveType">
                    ছুটির ধরণ <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="form-select" name="leaveType" id="leaveType" onChange="generateSubject()" required>
                        <option value=''>-- ছুটির ধরণ নির্বাচন করুন --</option>
                        <?php while($lRow=mysqli_fetch_array($getAllLeaveTypesQ)){ ?>
                        <option value='<?php echo $lRow['leaveID']; ?>'><?php echo htmlspecialchars($lRow['leaveTitle']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label">
                    চাহিত ছুটি (দিন) <span class="text-danger">*</span>
                </label>
                <div class="col-md-3">
                    <label class="form-label" for="leaveFrom">শুরু</label>
                    <input type="text" class="form-control" id="leaveFrom" name="leaveFrom" onchange="calculateDays()" placeholder="dd/mm/yyyy" required autocomplete="off" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="leaveTo">শেষ</label>
                    <input type="text" class="form-control" id="leaveTo" name="leaveTo" onchange="calculateDays()" placeholder="dd/mm/yyyy" required autocomplete="off" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reqLeaveDays">মোট দিন</label>
                    <input type="text" class="form-control" id="reqLeaveDays" name="reqLeaveDays" placeholder="স্বয়ংক্রিয়" autocomplete="off" readonly />
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="subject">
                    বিষয় <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <input type="text" class="form-control" name="subject" id="subject" placeholder="স্বয়ংক্রিয়ভাবে তৈরি হবে" readonly />
                    <small class="text-muted">
                        <i class="ti tabler-info-circle me-1"></i>আবেদনের প্রকার এবং ছুটির ধরণ নির্বাচন করলে স্বয়ংক্রিয়ভাবে তৈরি হবে
                    </small>
                </div>
            </div>

            <!-- Application Content Section -->
            <div class="row mb-3 mt-4">
                <div class="col-12">
                    <h6 class="card-title mb-3"><i class="ti tabler-text me-2"></i>আবেদনের বিষয়বস্তু</h6>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="templateSelector">
                    লিভ টেম্পলেট <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="form-select" id="templateSelector" onchange="insertTemplate(this.value)" required>
                        <option value=''>-- টেম্পলেট নির্বাচন করুন --</option>
                        <?php while($ltRow = mysqli_fetch_array($getLeaveTemplatesQ)){ ?>
                        <option value='<?php echo htmlspecialchars($ltRow['templateData']); ?>'><?php echo htmlspecialchars($ltRow['templateData']); ?></option>
                        <?php } ?>
                    </select>
                    <small class="text-muted">
                        <i class="ti tabler-info-circle me-1"></i>টেম্পলেট নির্বাচন করলে নিচের বক্সে স্বয়ংক্রিয়ভাবে যুক্ত হবে
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="leaveApplication">
                    ছুটির দরখাস্ত <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <textarea class="form-control" name="leaveApplication" id="leaveApplication" rows="6" placeholder="আবেদনপত্র লিখুন..." required></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="leaveFile">
                    সংযুক্তি
                </label>
                <div class="col-md-9">
                    <input type="file" name="leaveFile" id="leaveFile" class="form-control" />
                    <small class="text-muted">
                        <i class="ti tabler-info-circle me-1"></i>প্রয়োজনে সংশ্লিষ্ট ডকুমেন্ট সংযুক্ত করুন
                    </small>
                </div>
            </div>

            <!-- Supervisor Section -->
            <div class="row mb-3 mt-4">
                <div class="col-12">
                    <h6 class="card-title mb-3"><i class="ti tabler-user-check me-2"></i>সুপারভাইজার তথ্য</h6>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="supervisorID">
                    সুপারভাইজার/ঊর্ধ্বতন কর্মকর্তা <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single" style="width: 100%;" name="supervisorID" id="supervisorID" required>
                        <option value=''>-- সুপারভাইজার নির্বাচন করুন --</option>
                        <?php while($empRow=mysqli_fetch_array($getAllEmployeeListQ2)){
                            $getSupervisorDesigQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
                            $getSupervisorDesigQRW = mysqli_fetch_assoc($getSupervisorDesigQ); ?>
                        <option value='<?php echo $empRow['id']; ?>'>
                            <?php echo Bengali_DTN($empRow['employee_id']).' - '.htmlspecialchars($empRow['employee_name']).', '.htmlspecialchars($getSupervisorDesigQRW['job_title_name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div id="formresult"></div>

            <!-- Form Actions -->
            <div class="row mt-4">
                <div class="col-12 text-end">
                    <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary me-2">
                        <i class="ti tabler-arrow-left me-1"></i>বাতিল করুন
                    </button>
                    <button type="submit" name="submit" id="submit" class="btn btn-primary">
                        <i class="ti tabler-send me-1"></i>প্রেরণ করুন
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<script type="text/javascript">
$(document).ready(function() {
    // Initialize datepickers
    $("#leaveFrom").datepicker({
        dateFormat: "dd/mm/yy"
    });
    $("#leaveTo").datepicker({
        dateFormat: "dd/mm/yy"
    });

    // Initialize Select2
    $('.js-example-basic-single').select2({
        placeholder: "-- সুপারভাইজার নির্বাচন করুন --",
        allowClear: true,
        width: '100%'
    });

    $('.js-example-basic-single-2').select2({
        placeholder: "-- নির্বাচন করুন --",
        allowClear: true,
        width: '100%'
    });
});

// Insert template into textarea
function insertTemplate(str){
    $('#leaveApplication').val(str);
}

// Get employees for "on behalf" option
function getEmployees(option){
    if(option == 0){
        var dataString = 'option='+ option;

        $.ajax({
            type: "POST",
            url: "get_employees.php",
            data: dataString,
            cache: false,
            success: function(html){
                $("#employeeIDOnbehalf").html(html);
                $('.employeeIDOnbehalf').select2({
                    placeholder: "-- কর্মচারী নির্বাচন করুন --",
                    allowClear: true,
                    width: '100%'
                });
            }
        });
        $('#employeeIDOnbehalfDiv').show();
    } else{
        $('#employeeIDOnbehalfDiv').hide();
    }
}

// Calculate days between two dates
function calculateDays(){
    var date1Str = $('#leaveFrom').val();
    var date2Str = $('#leaveTo').val();

    if(!date1Str || !date2Str){
        return;
    }

    // Extract day, month, and year from date strings
    const [day1, month1, year1] = date1Str.split('/');
    const [day2, month2, year2] = date2Str.split('/');

    // Create Date objects
    const date1 = new Date(`${year1}-${month1}-${day1}`);
    const date2 = new Date(`${year2}-${month2}-${day2}`);

    // Check if the Date objects are valid
    if (isNaN(date1) || isNaN(date2)) {
        return;
    }

    // Calculate the difference in milliseconds
    const differenceMs = Math.abs(date2 - date1);

    // Convert the difference to days
    const days = Math.floor(differenceMs / (1000 * 60 * 60 * 24));

    $('#reqLeaveDays').val(days + 1);

    generateSubject();
}

// Generate subject based on selections
function generateSubject(){
    var applicationType = $('#applicationType').val();

    if(applicationType == 2){
        $('#isinformed').show();
        $("input[name='isinformedValue']").prop('required', true);
    }else{
        $('#isinformed').hide();
        $("input[name='isinformedValue']").prop('required', false);
    }

    var reqLeaveDays = $('#reqLeaveDays').val();
    var leaveType = $('#leaveType').val();

    if(!reqLeaveDays || !leaveType || !applicationType){
        return;
    }

    var dataString = 'reqLeaveDays='+ reqLeaveDays + '&leaveType='+leaveType+'&applicationType='+applicationType;

    $.ajax({
        type: "POST",
        url: "generate_leave_subject.php",
        data: dataString,
        cache: false,
        success: function(html){
            $("#subject").val(html);
        }
    });
}

// Form submission with validation and confirmation
$(document).ready(function() {
    var form = $('#form');
    var submit = $('#submit');

    form.on('submit', function(e) {
        e.preventDefault();

        // Form validation
        if (!validateForm()) {
            return false;
        }

        // SweetAlert2 confirmation dialog
        Swal.fire({
            title: 'আপনি কি নিশ্চিত?',
            text: "আবেদনটি প্রেরণ করতে চান?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28c76f',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, প্রেরণ করুন!',
            cancelButtonText: 'বাতিল',
            customClass: {
                confirmButton: 'btn btn-success me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (result.isConfirmed) {
                // AJAX request to submit leave application
                $.ajax({
                    url: 'insert_leave_application.php',
                    type: 'POST',
                    dataType: 'html',
                    data: new FormData(form[0]),
                    contentType: false,
                    cache: false,
                    processData: false,
                    beforeSend: function() {
                        submit.attr("disabled", "disabled");
                        submit.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>প্রক্রিয়াকরণ হচ্ছে...');
                    },
                    success: function(data) {
                        if (data == 0) {
                            Swal.fire({
                                title: 'ত্রুটি!',
                                text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                                icon: 'error',
                                confirmButtonColor: '#ff3e1d',
                                customClass: {
                                    confirmButton: 'btn btn-danger'
                                },
                                buttonsStyling: false
                            });
                        } else {
                            form.trigger('reset');
                            $('#formresult').html(data);

                            Swal.fire({
                                title: 'সফল!',
                                text: 'আবেদনটি সফলভাবে প্রেরণ করা হয়েছে',
                                icon: 'success',
                                confirmButtonColor: '#28c76f',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            });
                        }
                        submit.removeAttr("disabled");
                        submit.html('<i class="ti tabler-send me-1"></i>প্রেরণ করুন');
                    },
                    error: function(e) {
                        console.log(e);

                        Swal.fire({
                            title: 'ত্রুটি!',
                            text: 'আবেদন প্রেরণ করতে ব্যর্থ হয়েছে',
                            icon: 'error',
                            confirmButtonColor: '#ff3e1d',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });

                        submit.removeAttr("disabled");
                        submit.html('<i class="ti tabler-send me-1"></i>প্রেরণ করুন');
                    }
                });
            }
        });
    });
});

// Form validation function
function validateForm() {
    var isValid = true;
    $('#form').find('[required]').each(function() {
        if ($(this).val() === '' || $(this).val() === null) {
            isValid = false;
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    if(!isValid){
        Swal.fire({
            title: 'সতর্কতা!',
            text: 'অনুগ্রহ করে সকল প্রয়োজনীয় তথ্য পূরণ করুন',
            icon: 'warning',
            confirmButtonColor: '#ff9f43',
            customClass: {
                confirmButton: 'btn btn-warning'
            },
            buttonsStyling: false
        });
    }

    return isValid;
}
</script>
