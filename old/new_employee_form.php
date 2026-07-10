<?php
include(__DIR__ . '/includes/header_vuexy.php');

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

              <div class="row g-6 mb-6">
                <!-- FormValidation -->
                <div class="col-12">
                  <div class="card">
                    <div
                      class="card-header sticky-element bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
                      <h5 class="card-title mb-sm-0 me-2">নতুন কর্মকর্তা / কর্মচারীর  তথ্য সংযোজন</h5>
                      <div class="action-btns">
                        <button type="button" onclick="window.history.go(-1); return false;" class="btn btn-label-warning me-4">
                            <span class="align-middle"><i class="ti tabler-arrow-left me-1"></i> Back</span>
                        </button>
                        <button type="submit" name="submit" id="submit" class="btn btn-primary">Submit</button>
                      </div>
                    </div>
                    <div class="card-body" style="padding-top: 20px;">
                      <div class="row g-6">
                        <!-- Account Details -->

                        <div class="col-12">
                          <h6>১. ব্যক্তিগত তথ্য </h6>
                          <hr class="mt-0" />
                        </div>

                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="employee_name">নাম</label>
                          <input
                            type="text"
                            id="employee_name"
                            class="form-control"
                            placeholder=""
                            name="employee_name"
                            required />
                        </div>
                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="nid">জাতীয় পরিচয়পত্র নং</label>
                          <input
                            class="form-control"
                            type="text"
                            id="nid"
                            name="nid"
                            placeholder=""
                            required />
                        </div>


                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="designation">পদবি</label>
                            <select
                            data-placeholder="Choose a designation..." class="select2 form-select" name="designation" id="designation" required>
                                <option value="">--পদবি নির্বাচন করুন--</option>
                                    <?php
                                    while($degRow=mysqli_fetch_array($getAllDesig))
                                    {
                                                    
                                    ?>
                                    <option value="<?php echo $degRow['id']; ?>"><?php echo $degRow['job_title_name']; ?></option>
                                    <?php
                                    }
                                    ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="employee_id">আইডি</label>
                          <input
                            class="form-control"
                            type="text"
                            id="employee_id"
                            name="employee_id"
                            placeholder=""
                            required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="memorialNo">স্মারক নম্বর</label>
                          <input
                            class="form-control"
                            type="text"
                            id="memorialNo"
                            name="memorialNo"
                            placeholder=""
                            required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="employee_type">চাকরীর ধরন</label>
                          <select class="select2 form-select" name="employee_type" id="employee_type" required>
											<option value="">--ধরন নির্বাচন করুন--</option>
											<option value="1">কর্মকর্তা</option>
											<option value="2">কর্মচারী</option>
											<option value="3">পি আর এল</option>
										</select>
                        </div>

                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="dob">জন্ম তারিখ</label>
                          <input
                            type="text"
                            class="form-control flatpickr-validation"
                            id="bs-validation-dob"
                            name="dob"
                            autocomplete="off"
                            required />
                        </div>

                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="joining_date">চাকরিতে যোগদানের তারিখ</label>
                          <input
                            type="text"
                            class="form-control flatpickr-validation"
                            id="bs-validation-dob"
                            name="joining_date"
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
                            placeholder="" />
                        </div>

                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="mobileNo">মোবাইল নম্বর</label>
                          <input
                            class="form-control"
                            type="text"
                            id="mobileNo"
                            name="mobileNo"
                            placeholder="" />
                        </div>

                        <div class="col-md-12 form-control-validation">
                          <label for="formValidationFile" class="form-label">ফটো</label>
                          <input class="form-control" type="file" id="formValidationFile" name="photo" />
                        </div>

                        <!-- Personal Info -->

                        <div class="col-12">
                          <h6 class="mt-2">২. কর্মক্ষেত্র</h6>
                          <hr class="mt-0" />
                        </div>

                        <div class="col-md-12 form-control-validation">
                          <label class="form-label" for="organization_id">কেন্দ্র</label>
                          <select class="select2 form-select" name="organization_id" id="organization_id" required>
										<option value=''>--কেন্দ্র নির্বাচন করুন--</option>
											<?php while($orgRow = mysqli_fetch_array($getAllOrg)){ ?>
											<option value='<?php echo $orgRow['id']; ?>'><?php echo $orgRow['organization_name']; ?></option>
											<?php } ?>
										</select>
                        </div>

                        <div class="col-md-12 form-control-validation">
                          <label class="form-label" for="section_id">শাখা</label>
                          <select class="select2 form-select" name="section_id" id="section_id" required>
										<option value=''>--শাখা নির্বাচন করুন--</option>
										<?php while($secRow = mysqli_fetch_array($getAllSectionsQ)){ ?>
											<option value='<?php echo $secRow['id']; ?>'><?php echo $secRow['section_name']; ?></option>
										<?php } ?>
										</select>
                        </div>

                        
                        <div class="col-12">
                          <h6 class="mt-2">৩. বেতন সংক্রান্ত তথ্য</h6>
                          <hr class="mt-0" />
                        </div>


                        <div class="col-md-12 form-control-validation">
                          <label class="form-label" for="pay_scale">বর্তমান বেতন স্কেল</label>
                          <select class="select2 form-select" name="pay_scale" id="pay_scale" required>
										<option value="">--বেতন স্কেল নির্বাচন করুন--</option>
										<?php while($sRow = mysqli_fetch_array($getScalesQ)){ ?>
											<option value="<?php echo $sRow['id']; ?>"><?php echo $obj->engToBn($sRow['minimum_salary'])." - ".$obj->engToBn($sRow['maximum_salary'])."(".$sRow['grade_title'].")"; ?></option>
										<?php } ?>
										</select>
                        </div>


                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="basic_salary">বর্তমান মূল বেতন</label>
                          <input
                            class="form-control"
                            type="text"
                            id="basic_salary"
                            name="basic_salary"
                            placeholder=""
                            required />
                        </div>


                        <div class="col-md-12 form-control-validation">
                          <label class="form-label" for="salary_group_id">রিপোর্ট ক্যাটাগরি</label>
                          <select class="select2 form-select" name="salary_group_id" id="salary_group_id" required>
											<option value="">--ক্যাটাগরি নির্বাচন করুন--</option>
											<?php
											while($sgRow=mysqli_fetch_array($getAllGroupQ))
											{
											?>
											<option value="<?php echo $sgRow['id']; ?>"><?php echo $sgRow['head']."=>".$sgRow['sub_head']; ?></option>
											<?php
											}
											?>
										</select>
                        </div>


                        <div class="col-md-6 form-control-validation">
                          <label class="form-label" for="display_order">ডিসপ্লে অর্ডার</label>
                          <input
                            class="form-control"
                            type="number"
                            id="display_order"
                            name="display_order"
                            placeholder="" />
                        </div>
                        

                        
                        </div>
                    </div>
                  </div>
                </div>
                <!-- /FormValidation -->
                 </form>

<?php
// Don't include form-validation.js - we handle initialization in page script to avoid conflicts
// define('INCLUDE_FORM_VALIDATION_JS', true);

// Define page-specific scripts - Select2 is now handled by footer's initializePageComponents()
define('PAGE_SCRIPTS', '
<script>
jQuery(function($) {
    console.log("=== Page Init Start ===");

    // Flatpickr for date fields
    try {
        $(".flatpickr-validation").each(function() {
            if (typeof flatpickr !== "undefined" && !$(this).hasClass("flatpickr-input")) {
                $(this).flatpickr({
                    enableTime: false,
                    dateFormat: "Y/m/d",
                    allowInput: true
                });
            }
        });
        console.log("Flatpickr initialized");
    } catch(e) {
        console.error("Flatpickr init error:", e);
    }

    // Bootstrap Select for Hobbies
    try {
        var $hobbiesSelect = $(".selectpicker");
        if ($hobbiesSelect.length && typeof $.fn.selectpicker !== "undefined") {
            $hobbiesSelect.selectpicker();
            console.log("Bootstrap Select initialized");
        }
    } catch(e) {
        console.error("Bootstrap Select init error:", e);
    }

    console.log("=== Page Init End ===");
});
</script>
');

include(__DIR__ . '/includes/footer_vuexy.php');
?>


<script>



$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button
	var originalButtonText = submit.html(); // store original button text

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit

		// Disable submit button to prevent double submission
		submit.prop('disabled', true);

		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_employee_data.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'text', // request type - use text to see raw response
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData: false,
			beforeSend: function() {
				submit.html('<i class="ti tabler-loader ti-spin me-1"></i> Processing...');
			},
			success: function(data) {
				console.log('Server response:', data); // Debug: log response

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
						text: 'Data Saved Successfully',
						confirmButtonColor: '#696cff'
					}).then(function() {
						form.trigger('reset'); // reset form

						// Re-initialize Select2 after form reset
						$('.select2').each(function() {
							$(this).val('').trigger('change');
						});
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






const photoElement = document.getElementById('photo');
if (photoElement) {
    photoElement.addEventListener('change', function(e) {
        // Define the allowed file types and max file size (1MB)
        const allowedFileTypes = ['image/jpeg', 'image/png'];
        const maxFileSize = 1024 * 1024; // 1MB in bytes

        // Get the file from the input
        const file = e.target.files[0];

        // Check if file is selected
        if (file) {
            // Check the file type
            if (!allowedFileTypes.includes(file.type)) {
                alert('Please choose a JPEG or PNG file.');
                return;
            }


            // File is valid, you can proceed with further actions
            console.log('File is valid');
        }
    });
}


</script>