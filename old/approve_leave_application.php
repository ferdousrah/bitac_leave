<?php
include('header.php');

$dataID = $_GET['dataID'];

$isApproved = $_GET['isApproved'];

$leaveApplicationID = $_GET['leaveApplicationID'];

$currentSignatory = $_SESSION['employeeID'];


$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$leaveApplicationID'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getLeaveApplicationDetailsQRW[applicantID]' and employment_status=1");
$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getApplicgDetailsDesigQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQW[organization_id]'");
$getApplicgDetailsDesigQRW = mysqli_fetch_assoc($getApplicgDetailsDesigQ);

$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);


$getAllEmployeeListQ2 = mysqli_query($con,"select * from `employee_list` where employment_status=1");





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

$getLeaveApplicationSettingsLastApprQ = mysqli_query($con, "select * from leave_approval_signatory order by approvalSL desc LIMIT 0,1");
$getLeaveApplicationSettingsLastApprQRW = mysqli_fetch_assoc($getLeaveApplicationSettingsLastApprQ);

$getSerialQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$currentSignatory'");
$getSerialQRW = mysqli_fetch_assoc($getSerialQ);


$getLeaveTemplatesQ = mysqli_query($con, "select * from leave_templates where templateType=2");


if($getLeaveApplicationDetailsQRW['approvedDateFrom'] != ''){

	$aDateFrom = $getLeaveApplicationDetailsQRW['approvedDateFrom'];

}else{

	$aDateFrom = $getLeaveApplicationDetailsQRW['dateFrom'];

}


if($getLeaveApplicationDetailsQRW['approvedDateTo'] != ''){

	$aDateTo = $getLeaveApplicationDetailsQRW['approvedDateTo'];

}else{

	$aDateTo = $getLeaveApplicationDetailsQRW['dateTo'];

}


$dateDiff = dateDiffInDays($aDateFrom, $aDateTo) + 1;

$reqDateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;



$getSecInchargeNoteQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and isSupervisor='1'");
$getSecInchargeNoteQRW = mysqli_fetch_assoc($getSecInchargeNoteQ);

$getAdminNoteQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and isSupervisor='0' and serial=2");
$getAdminNoteQRW = mysqli_fetch_assoc($getAdminNoteQ);

$getAdminNoteQ2 = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and isSupervisor='0' and serial=3");
$getAdminNoteQ2RW = mysqli_fetch_assoc($getAdminNoteQ2);


$getAllLeaveTypesQ = mysqli_query($con, "select * from leave_types");

$checkisSupervisorQ = mysqli_query($con, "select * from leave_data_for_approval where leaveApplicationID='$leaveApplicationID' and signatory='$currentSignatory' and isSupervisor=1 and isApproved=0");
$checkisSupervisorQNumRows = mysqli_num_rows($checkisSupervisorQ);


// update isRead Status
if($checkisSupervisorQNumRows > 0){

$updateApplicationReadStatusQ = mysqli_query($con, "update leave_data_for_approval set isRead=1 where leaveApplicationID='$leaveApplicationID' and signatory='$currentSignatory' and isSupervisor=1");

$defaultComments = "সুপারিশ করা হলো।";

}else{

$updateApplicationReadStatusQ = mysqli_query($con, "update leave_data_for_approval set isRead=1 where leaveApplicationID='$leaveApplicationID' and signatory='$currentSignatory'");

$defaultComments = "";

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
        <h4>ছুটির সুপারিশ ও অনুমোদন</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='add_new_instructor_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">


				

				<?php
				  if($getSecInchargeNoteQRW['note']!=''){
				?>

				<div class="alert alert-info">
				  <strong>বিভাগীয় প্রধানের মন্তব্য: </strong> <?php echo $getSecInchargeNoteQRW['note']; ?>
				</div>

				<?php } ?>

				<?php
				  if($getLeaveApplicationDetailsQRW['adminNote']!=''){
				?>

				<div class="alert alert-info">
				  <strong>নোট উপস্থাপনকারীর মন্তব্য: </strong> <?php echo $getLeaveApplicationDetailsQRW['adminNote']; ?>
				</div>

				<?php } ?>

				<?php
				  if($getAdminNoteQRW['note']!=''){
				?>

				<div class="alert alert-info">
				  <strong>সহকারী পরিচালক(প্রশাসন) এর মন্তব্য: </strong> <?php echo $getAdminNoteQRW['note']; ?>
				</div>

				<?php } ?>

				<?php
				  if($getAdminNoteQ2RW['note']!=''){
				?>

				<div class="alert alert-info">
				  <strong>পরিচালক(প্রশাসন) এর মন্তব্য: </strong> <?php echo $getAdminNoteQ2RW['note']; ?>
				</div>

				<?php } ?>


				

				<p>&nbsp;</p>


				<form class="form-login" name="form" id="form" >
					<input type="hidden" name="dataID" id="dataID" value="<?php echo $dataID; ?>" />
					<input type="hidden" name="isApproved" value="<?php echo $isApproved; ?>" />
					<input type="hidden" name="leaveApplicationID" id="leaveApplicationID" value="<?php echo $leaveApplicationID; ?>" />


							<div class="form-group row">
	                            	<label class="col-md-3 label-control"> </label>
		                            <div class="col-md-9">
										
										<?php if($getLeaveApplicationDetailsQRW['applicationType'] == 1){ ?>

										<span class="badge badge-success">নিয়মিত ছুটির আবেদন</span>

										<?php }else if($getLeaveApplicationDetailsQRW['applicationType'] == 2){ ?>

										<span class="badge badge-danger" style="font-size: 18px;">অনুপস্থিতকালের জন্য ছুটির আবেদন</span>

										<?php } ?>
										
		                            </div>
		                        </div>
					

	                    	<div class="form-body">
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">আবেদনকারী  : </label>
		                            <div class="col-md-9">
										
		                            		<?php echo $getEmployeeDetailsQW['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?>, 
	 <?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getApplicgDetailsDesigQRW['organization_name']; ?>&nbsp;&nbsp;<a href="employee_leave_history?employeeID=<?php echo $getLeaveApplicationDetailsQRW['applicantID']; ?>" target="_blank"><img height="40" src="dashboard-icons/history.png" /></a>
										
		                            </div>
		                        </div>



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">চাহিত ছুটির ধরণ </label>
		                            <div class="col-md-9">
										
		                            		<?php echo $getLeaveTypeQRW['leaveTitle']; ?>
										
		                            </div>
		                    </div>


							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">চাহিত ছুটি(দিন)</label>
									<div class="col-md-9">
										
		                            		<?php echo convertDateTotrad($getLeaveApplicationDetailsQRW['dateFrom']); ?> হইতে  <?php echo convertDateTotrad($getLeaveApplicationDetailsQRW['dateTo']); ?> তারিখ পর্যন্ত , <?php echo $reqDateDiff; ?> দিন
										
		                            </div>
		                           
		                    </div>


							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveFrom">প্রস্তাবিত ছুটি(দিন)</label>
		                            <div class="col-md-2">
										
		                            	<input type="text" class="form-control" id="leaveFrom" name="leaveFrom" autocomplete="off" onchange="calculateDays()" value="<?php echo convertDateTotrad($aDateFrom); ?>" />
										
		                            </div>
									<label class="col-md-1 label-control" for="leaveTo">পর্যন্ত</label>
		                            <div class="col-md-2">
										
		                            	<input type="text" class="form-control" id="leaveTo" name="leaveTo" autocomplete="off"  onchange="calculateDays()" value="<?php echo convertDateTotrad($aDateTo); ?>" />
										
		                            </div>
									<div class="col-md-4">
										
		                            		<input type="number" class="form-control" id="reqLeaveDays" name="approvedDays"  value="<?php echo $dateDiff; ?>" readonly />
										
		                            </div>
		                    </div>


							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">প্রস্তাবিত ছুটির ধরণ  </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="approvedLeaveType" required>
												<option value=''></option>
												
												<?php
												  while($lRow=mysqli_fetch_array($getAllLeaveTypesQ)){
												?>

												<option <?php if($getLeaveApplicationDetailsQRW['approvedLeaveType'] == $lRow['leaveID']){ echo "selected"; } ?> value='<?php echo $lRow['leaveID']; ?>'><?php echo $lRow['leaveTitle']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                     </div>

							 <?php if($checkisSupervisorQNumRows <= 0){ ?>

							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">ডিডাক্ট ফ্রম </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="leaveTypeInTwo" required>
												<option value=''></option>
												
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "selected"; } ?> value="1">গড় বেতন </option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "selected"; } ?> value="2">অর্ধ-গড় বেতন </option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "selected"; } ?> value="3">নৈমিত্তিক (Casual Leave)</option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "selected"; } ?> value="4">বিনা বেতনে ছুটি</option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "selected"; } ?> value="10">অসাধারণ ছুটি</option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "selected"; } ?> value="5">ঐচ্ছিক ছুটি</option>											<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "selected"; } ?> value="6">কর্তনহীন ছুটি</option>
											</select>
										
		                            </div>
		                     </div>


							 <?php } ?>


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
	                            	<label class="col-md-3 label-control" for="leaveType">মন্তব্য</label>
		                            <div class="col-md-9">

											<textarea class="form-control" name="note" id="note"><?php echo $defaultComments; ?></textarea>
										
		                            </div>
		                    </div>


							<?php 
							  if($_SESSION['userID'] == 88 || $_SESSION['userID'] == 91){
							?>



								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="forwardTo">ফরওয়ার্ড </label>
		                            <div class="col-md-9">
										
		                            		<select class="js-example-basic-single" style="width: 100%;" name="forwardTo" id="forwardTo">
												<option value=''></option>
												<?php
												  while($empRow=mysqli_fetch_array($getAllEmployeeListQ2)){

													  $getSupervisorDesigQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
													  $getSupervisorDesigQRW = mysqli_fetch_assoc($getSupervisorDesigQ);
												?>

												<option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).'-'.$empRow['employee_name'].', '.$getSupervisorDesigQRW['job_title_name']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                        </div>




							<?php } ?>

							
														


							<div id="formresult"></div>		


								
	                        <div class="form-actions">
	                             <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-arrow-left"></i> Back
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="ft-check-square"></i> Approve
	                            </button>

								<button type="button" onClick="declineLeaveJoining()" class="btn btn-raised btn-danger mr-1">
	                            	<i class="ft-x"></i> Not Approve
	                            </button>
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

		
		$('.employeeID').select2();


		function insertTemplate(str){

			$('#note').val(str);
		
		
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


		}


		function declineLeaveJoining(){
			
			


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
				

				// start ajax

					var dataID = $('#dataID').val();

					var leaveApplicationID = $('#leaveApplicationID').val();

					var note = $('#note').val();


					$.ajax({
					type : 'post',
					url : 'decline_leave_application.php', //Here you will fetch records 
					data :  'dataID='+ dataID +'&leaveApplicationID='+leaveApplicationID+'&note='+note, //Pass $id
					success : function(data){

					
						if(data == 1){
							
							toastr.success('Success!!');
							window.location='leave_approval?menuslug=leave-approval';

						}else{
							
							toastr.error('Error!!');

						}


					}
					});



					// end of ajax




			}, function (dismiss) {
				// dismiss can be 'overlay', 'cancel', 'close', 'esc', 'timer'
				if (dismiss === 'cancel') {
					// do nothing
				}
			})

				  
	  
			

		}


		


//.....................


$(document).ready(function() {
    var form = $('#form'); // Contact form
    var submit = $('#submit'); // Submit button

    // Form submit event
    form.on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

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
        // Retrieve data from inputs
        
        // AJAX request to decline leave application
        
		$.ajax({
                    url: 'approve_leave_application_action.php', // Form action URL
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
                        //alert(data); // Show response data in an alert
                        if (data == 0) {
                            toastr.error('Error!!');
                            // Optionally redirect: window.location = 'dashboard?mainslug=dashboard';
                        } else {
                            // toastr.success('Data Saved Successfully'); // Uncomment for success toast
                            form.trigger('reset'); // Reset form
                            window.location='leave_approval?menuslug=leave-approval';
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




		
</script>
