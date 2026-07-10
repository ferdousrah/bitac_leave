<?php
include('header.php');

$dataID = base64_decode($_GET['applicationID']);

$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$dataID'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list` where employment_status=1");

$getAllEmployeeListQ2 = mysqli_query($con,"select * from `employee_list` where employment_status=1");

$getAllEmployeeListQ3 = mysqli_query($con,"select * from `employee_list` where employment_status=1");

$getAllLeaveTypesQ = mysqli_query($con, "select * from leave_types where leaveID!=22");

$getUserDetailsQ = mysqli_query($con, "select * from user_list where dataID = '$_SESSION[userID]'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);


$getLeaveTemplatesQ = mysqli_query($con, "select * from leave_templates where templateType=1");


$getCurrentEmpDetailsQ = mysqli_query($con, "select * from `employee_list` where id='$getUserDetailsQRW[employee_id]'");
$getCurrentEmpDetailsQRW = mysqli_fetch_assoc($getCurrentEmpDetailsQ);


$leaveFrom = $getLeaveApplicationDetailsQRW['dateFrom'];

$dateFArray = explode('-', $leaveFrom);

$dateFrom = $dateFArray[2].'/'.$dateFArray[1].'/'.$dateFArray[0];


$leaveTo = $getLeaveApplicationDetailsQRW['dateTo'];

$dateTArray = explode('-', $leaveTo);

$dateTo = $dateTArray[2].'/'.$dateTArray[1].'/'.$dateTArray[0];


$totalReqDays = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;


$getSupervisorQ = mysqli_query($con, "select signatory as supervisorID from leave_data_for_approval where leaveApplicationID='$getLeaveApplicationDetailsQRW[dataID]' and isSupervisor=1");
$getSupervisorQRW = mysqli_fetch_assoc($getSupervisorQ);

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



          <div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper">
		  <!--Invoice template starts-->
    <div class="row">
    <div class="col-md-12">

		
	</div>
	</div>

    <div class="row">
    <div class="col-md-10">
        <h4>ছুটির আবেদনপত্র সংশোধন</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='add_new_instructor_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">


				<form class="form-login" name="form" id="form" >
					<input type="hidden" name="dataID" value="<?php echo $dataID; ?>" />
					<input type="hidden" name="prevAttachment" value="<?php echo $getLeaveApplicationDetailsQRW['attachment']; ?>" />
					<div class="form-body">
						<div class="form-group row">
							<label class="col-md-3 label-control" for="">আবেদনের প্রকার <span style="color: red;">*</span></label>
							<div class="col-md-9">
								<select class="form-control" name="applicationType" id="applicationType" onChange="generateSubject()" required>
									<option value=''></option>
									<option <?php if($getLeaveApplicationDetailsQRW['applicationType'] == 1){ echo "selected='selected'"; } ?> value='1'>নিয়মিত ছুটির আবেদন</option>
									<option <?php if($getLeaveApplicationDetailsQRW['applicationType'] == 2){ echo "selected='selected'"; } ?> value='2'>অনুপস্থিতকালের জন্য ছুটির আবেদন</option>
								</select>
							</div>
						</div>

						<div class="form-group row" id="isinformed" style="display: none;">
							<label class="col-md-3 label-control" for="isinformed">কর্তৃপক্ষকে পূর্বে অবগত <span style="color: red;">*</span></label>
							<div class="col-md-9">
								<input type="radio" name="isinformedValue" value="1" <?php if($getLeaveApplicationDetailsQRW['isinformed'] == 1){ echo "checked"; } ?> />&nbsp;&nbsp;করেছি&nbsp;&nbsp;<input type="radio" name="isinformedValue" value="0" <?php if($getLeaveApplicationDetailsQRW['isinformed'] == 0){ echo "checked"; } ?> />&nbsp;&nbsp;করতে পারিনি
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="supervisorID">বরাবর <span style="color: red;">*</span></label>
							<div class="col-md-9">
								<select class="js-example-basic-single-2" style="width: 100%;" name="to" id="to" required>
									<option value=''></option>
									<option <?php if($getLeaveApplicationDetailsQRW['applicationTo'] == 1){ echo "selected='selected'"; } ?> value="1">পরিচালক (প্রশাসন)</option>
									<option <?php if($getLeaveApplicationDetailsQRW['applicationTo'] == 2){ echo "selected='selected'"; } ?> value="2">মহাপরিচালক</option>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="employeeID">কর্মকর্তা/ কর্মচারী সিলেক্ট করুন : <span style="color: red;">*</span></label>
							<div class="col-md-9">
								<select class="form-control" name="employeeID" id="employeeID" onChange="getEmployees(this.value)" required>
									<option value='<?php echo $getUserDetailsQRW['employee_id']; ?>'>নিজ</option>
									<option value='0'>পক্ষে</option>                                                
								</select>
							</div>
						</div>

						<div class="form-group row" id="employeeIDOnbehalfDiv" style="display: none;">
							<label class="col-md-3 label-control" for="employeeIDOnbehalf">&nbsp;</label>
							<div class="col-md-9">
								<select class="form-control employeeIDOnbehalf" style="width: 100%;" name="employeeIDOnbehalf" id="employeeIDOnbehalf">
									<option value=''></option>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="leaveType">ছুটির ধরণ  <span style="color: red;">*</span></label>
							<div class="col-md-9">
								<select class="form-control" name="leaveType" id="leaveType" onChange="generateSubject()" required>
									<option value=''></option>
									<?php while($lRow=mysqli_fetch_array($getAllLeaveTypesQ)){ ?>
										<option <?php if($getLeaveApplicationDetailsQRW['leaveType'] == $lRow['leaveID']){ echo "selected='selected'"; } ?> value='<?php echo $lRow['leaveID']; ?>'><?php echo $lRow['leaveTitle']; ?></option>
									<?php } ?>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="leaveFrom">চাহিত ছুটি(দিন)<span style="color: red;">*</span></label>
							<div class="col-md-2">
								<input type="text" class="form-control" id="leaveFrom" name="leaveFrom" value="<?php echo $dateFrom; ?>" onchange="calculateDays()" required autocomplete="off" readonly />
							</div>
							<label class="col-md-1 label-control" for="leaveTo">হইতে</label>
							<div class="col-md-2">
								<input type="text" class="form-control" id="leaveTo" name="leaveTo" value="<?php echo $dateTo; ?>"  onchange="calculateDays()" required autocomplete="off" readonly />
							</div>
							<div class="col-md-2">
								<input type="text" class="form-control" id="reqLeaveDays" name="reqLeaveDays" value="<?php echo $totalReqDays; ?>" autocomplete="off" readonly />
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="leaveApplication">বিষয়<span style="color: red;">*</span></label>
							<div class="col-md-9">
								<input type="text" class="form-control" name="subject" id="subject" value="<?php echo $getLeaveApplicationDetailsQRW['subject']; ?>" />
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="leaveApplication">লিভ টেম্পলেট</label>
							<div class="col-md-9">
								<select class="form-control" onchange="insertTemplate(this.value)">
									<option value=''></option>
									<?php while($ltRow = mysqli_fetch_array($getLeaveTemplatesQ)){ ?>
										<option value='<?php echo $ltRow['templateData']; ?>'><?php echo $ltRow['templateData']; ?></option>
									<?php } ?>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="leaveApplication">ছুটির দরখাস্ত<span style="color: red;">*</span></label>
							<div class="col-md-9">
								<textarea class="form-control" name="leaveApplication" id="leaveApplication" required><?php echo $getLeaveApplicationDetailsQRW['leaveApplication']; ?></textarea>
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="leaveFile">সংযুক্তি</label>
							<div class="col-md-9">
								<input type="file" name="leaveFile" id="leaveFile" class="form-control" />
							</div>
						</div>

						<div class="form-group row">
							<label class="col-md-3 label-control" for="supervisorID">সুপারভাইজার/ঊর্ধ্বতন কর্মকর্তা  <span style="color: red;">*</span></label>
							<div class="col-md-9">
								<select class="js-example-basic-single" style="width: 100%;" name="supervisorID" id="supervisorID" required>
									<option value=''></option>
									<?php while($empRow=mysqli_fetch_array($getAllEmployeeListQ2)){
										$getSupervisorDesigQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
										$getSupervisorDesigQRW = mysqli_fetch_assoc($getSupervisorDesigQ); ?>
										<option value='<?php echo $empRow['id']; ?>' <?php if($getSupervisorQRW['supervisorID'] == $empRow['id']){ echo "selected='selected'"; } ?> ><?php echo Bengali_DTN($empRow['employee_id']).'-'.$empRow['employee_name'].', '.$getSupervisorDesigQRW['job_title_name']; ?></option>
									<?php } ?>
								</select>
							</div>
						</div>

						<div id="formresult"></div>

						<div class="form-actions">
							<button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
								<i class="ft-x"></i> বাতিল করুন
							</button>
							<button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
								<i class="fa fa-check-square-o"></i> প্রেরণ করুন
							</button>
						</div>
					</div>
				</form>
                
                
                
								

                
							
            </div>
        </div>
    </div>
</section>
<!--Invoice template ends-->
          </div>
        </div>

        
		
</div>





<?php
include('footer.php');
?>


		<script>

		$(document).ready(function() {
			$("#leaveFrom").datepicker({
			  dateFormat: "dd/mm/yy"
			});
			$("#leaveTo").datepicker({
			  dateFormat: "dd/mm/yy"
			});
		  });


		$('.js-example-basic-single').select2();

		$('.js-example-basic-single-2').select2();

		
		$('.employeeID').select2();


		function insertTemplate(str){

			$('#leaveApplication').val(str);
		
		
		}


		function getEmployees(option){

			if(option == 0){
				
			  
			  var dataString = 'option='+ option;
  
			  $.ajax
			  ({
				   type: "POST",
				   url: "get_employees.php",
				   data: dataString,
				   cache: false,
				   success: function(html)
				   {

					  $("#employeeIDOnbehalf").html(html);

					  $('.employeeIDOnbehalf').select2();


				   } 
			   });

			   $('#employeeIDOnbehalfDiv').show();

			} else{
				
				$('#employeeIDOnbehalfDiv').hide();

			}			

		}


		function calculateDays(){
			
				var date1Str = $('#leaveFrom').val();

				var date2Str = $('#leaveTo').val();

			// Extract day, month, and year from date strings
			  const [day1, month1, year1] = date1Str.split('/');
			  const [day2, month2, year2] = date2Str.split('/');

			  // Create Date objects
			  const date1 = new Date(`${year1}-${month1}-${day1}`);
			  const date2 = new Date(`${year2}-${month2}-${day2}`);

			  // Check if the Date objects are valid
			  if (isNaN(date1) || isNaN(date2)) {
				throw new Error('Invalid Date format');
			  }

			  // Calculate the difference in milliseconds
			  const differenceMs = Math.abs(date2 - date1);

			  // Convert the difference to days
			  const days = Math.floor(differenceMs / (1000 * 60 * 60 * 24));

			  $('#reqLeaveDays').val(days + 1);

			  generateSubject();


		}


		function generateSubject(){

			var applicationType = $('#applicationType').val();

			//alert(applicationType); 

			if(applicationType == 2){

				$('#isinformed').show();

				$("input[name='isinformedValue']").prop('required', true);
			
			}else{
			
				$('#isinformed').hide();

				$("input[name='isinformedValue']").prop('required', false);
			
			}
		
			var reqLeaveDays = $('#reqLeaveDays').val();

			var leaveType = $('#leaveType').val();

			var dataString = 'reqLeaveDays='+ reqLeaveDays + '&leaveType='+leaveType+'&applicationType='+applicationType;

			//alert(dataString);
  
			  $.ajax
			  ({
			   type: "POST",
			   url: "generate_leave_subject.php",
			   data: dataString,
			   cache: false,
			   success: function(html)
			   {
				  $("#subject").val(html);

			   } 
			   });


		
		
		}


		
$(document).ready(function() {
    var form = $('#form'); // Contact form
    var submit = $('#submit'); // Submit button

    // Form submit event
    form.on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        // Form validation
        if (!validateForm()) {
            return false; // If validation fails, prevent form submission
        }

        // SweetAlert confirmation dialog
        swal({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0CC27E',
            cancelButtonColor: '#FF586B',
            confirmButtonText: 'Yes, proceed!',
            cancelButtonText: 'No, cancel!',
            confirmButtonClass: 'btn btn-success btn-raised mr-5',
            cancelButtonClass: 'btn btn-danger btn-raised',
            buttonsStyling: false
        }).then(function () {
            // AJAX request to decline leave application
            $.ajax({
                url: 'edit_my_leave_application_action.php', // Form action URL
                type: 'POST', // Form submission method GET/POST
                dataType: 'html', // Expected response type HTML/JSON/XML
                data: new FormData(form[0]), // Serialize form data
                contentType: false,
                cache: false,
                processData: false,
                beforeSend: function() {
                    submit.html('<i class="fa fa-spinner fa-spin"></i> Processing, please wait'); // Change submit button text
                },
                success: function(data) {
                    if (data == 0) {
                        toastr.error('Error!!');
                    } else {
                        form.trigger('reset'); // Reset form
                        $('#formresult').html(data); // Display data in a specific element
                    }
                    submit.html('Submit'); // Reset submit button text
                },
                error: function(e) {
                    console.log(e); // Log error in console
                    toastr.error('Failed to process the request.'); // Inform user about AJAX request failure
                }
            });
        }, function (dismiss) {
            // Handle cancel
            if (dismiss === 'cancel') {
                // do nothing
            }
        });
    });
});

// Form validation function
function validateForm() {
    var isValid = true;
    $('#form').find('[required]').each(function() {
        if ($(this).val() === '') {
            isValid = false;
            $(this).addClass('is-invalid'); // Add Bootstrap's is-invalid class for invalid fields
        } else {
            $(this).removeClass('is-invalid'); // Remove invalid class for valid fields
        }
    });
    return isValid;
}


		
		</script>