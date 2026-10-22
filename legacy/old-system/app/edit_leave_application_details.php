<?php
include('header.php');

$leaveApplicationID = $_GET['leaveApplicationID'];



$getAllLeaveTypesQ = mysqli_query($con, "select * from leave_types");

$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$leaveApplicationID'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getLeaveApplicationDetailsQRW[applicantID]'");
$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

$getApplicgDetailsDesigQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQW[organization_id]'");
$getApplicgDetailsDesigQRW = mysqli_fetch_assoc($getApplicgDetailsDesigQ);

$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);


//$dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;


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

$reqDateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;


// copy to

$getEmployeeListQ4 = mysqli_query($con, "select * from employee_list where employment_status=1");


$getApprovalPersonsQ = mysqli_query($con, "select * from leave_notice_copy where applicationID='$leaveApplicationID' order by serial asc");


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
        <h4>অনুমোদিত ছুটি সম্পাদনা</h4>
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

					<input type="hidden" name="leaveApplicationID" value="<?php echo $leaveApplicationID; ?>" />

	                    	<div class="form-body">
			                    
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">আবেদনকারী: </label>
		                            <div class="col-md-9">
										
		                            		<?php echo $getEmployeeDetailsQW['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?>, 
	 <?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getApplicgDetailsDesigQRW['organization_name']; ?>&nbsp;&nbsp;<a href="employee_leave_history?employeeID=<?php echo $getLeaveApplicationDetailsQRW['applicantID']; ?>" target="_blank"><img height="40" src="dashboard-icons/history.png" /></a>
										
		                            </div>
		                        </div>



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">চাহিত ছুটির ধরণ </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="leaveType" id="leaveType" required>
												<option value=''></option>
												<?php
												  while($lRow=mysqli_fetch_array($getAllLeaveTypesQ)){
												?>

												<option <?php if($getLeaveApplicationDetailsQRW['leaveType'] == $lRow['leaveID']){ echo "selected='selected'"; } ?> value='<?php echo $lRow['leaveID']; ?>'><?php echo $lRow['leaveTitle']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                    </div>


							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">অনুমোদিত ছুটি(দিন)</label>
									<div class="col-md-9">
										

											<?php echo convertDateTotrad($getLeaveApplicationDetailsQRW['approvedDateFrom']); ?> হইতে  <?php echo convertDateTotrad($getLeaveApplicationDetailsQRW['approvedDateTo']); ?> তারিখ পর্যন্ত , <?php echo $reqDateDiff; ?> দিন
										
		                            </div>
		                           
		                    </div>



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveFrom">ভোগকৃত ছুটি(দিন)</label>
		                            <div class="col-md-2">
										
		                            	<input type="text" class="form-control" id="leaveFrom" name="leaveFrom" autocomplete="off" onchange="calculateDays()" value="<?php echo convertDateTotrad($aDateFrom); ?>" />
										
		                            </div>
									<label class="col-md-1 label-control" for="leaveTo">পর্যন্ত</label>
		                            <div class="col-md-2">
										
		                            	<input type="text" class="form-control" id="leaveTo" name="leaveTo" autocomplete="off" onchange="calculateDays()" value="<?php echo convertDateTotrad($aDateTo); ?>" />
										
		                            </div>
									<div class="col-md-4">
										
		                            		<input type="number" class="form-control" id="reqLeaveDays" name="approvedDays"  value="<?php echo $dateDiff; ?>" />
										
		                            </div>
		                    </div>


							



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">ভোগকৃত ছুটির ধরণ  </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="leaveTypeInTwo" required>
												<option value=''></option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "selected"; } ?> value="1">গড় বেতন </option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "selected"; } ?> value="2">অর্ধ-গড় বেতন </option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "selected"; } ?> value="3">নৈমিত্তিক (Casual Leave)</option>
												<option <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "selected"; } ?> value="4">অসাধারণ(বিনা বেতনে ছুটি)</option>
											</select>
										
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
												<option>আবেদনকারী <?php echo Bengali_DTN($dateDiff); ?> দিনের <?php echo $getLeaveTypeQRW['leaveTitle']; ?> চেয়েছেন । সদয় অনুমোদনের জন্য পেশ করা হল।</option>
											</select>
										
		                            </div>
		                    </div>



							 <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">মন্তব্য</label>
		                            <div class="col-md-9">
										
		                            		<textarea class="form-control" name="note" id="note"></textarea>
										
		                            </div>
		                    </div>




							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">সংযুক্তি</label>
		                            <div class="col-md-9">
										
		                            		<input type="file" class="form-control" name="fileattachment" required />
										
		                            </div>
		                    </div>

							


							<div id="formresult"></div>		


								
	                        <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> বাতিল করুন
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> অনুমোদনের জন্য পাঠান
	                            </button>
	                        </div>
	                    </form>
                
                
						<p>&nbsp;</p>
						<p>&nbsp;</p>

						<div id="letter" style="width: 100%;"></div>
								

                
							
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


		$('.copyTo').select2();


$('.js-example-basic-single').select2();



var i=$('table tr').length;
$("#add_row").on('click',function(){

	i++;
	
	html = '<tr>';

	html += "<td style='text-align:center;'>"+(i - 2)+"</td>";

	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single"+i+"' style='width:100%;' name='copyTo[]' id='copyTo_"+i+"' required><option value=''></option><?php while($cRow2=mysqli_fetch_array($getEmployeeListQ4)){?><option value='<?php echo $cRow2['id']; ?>'><?php echo Bengali_DTN($cRow2['employee_id']).' - '.$cRow2['employee_name']; ?></option><?php } ?></select></td>";
		
	html += "<td style='text-align:center'><input type='number' name='serial[]' id='serial_"+i+"' placeholder='' class='form-control' value='"+(i - 2)+"' required /></td>";
	
	html += '</tr>';
	$('table').append(html);
	

	$('.js-example-basic-single'+i).select2();

	
});


$("#delete_row").click(function(){
	var i=$('table tr').length;
    	 
		 $("#tbl tr:last").remove();

           


	 });


	 //...................


		$(document).ready(function() {
			$("#leaveFrom").datepicker({
			  dateFormat: "dd/mm/yy"
			});
			$("#leaveTo").datepicker({
			  dateFormat: "dd/mm/yy"
			});
		  });


		//$('.js-example-basic-single').select2();

		
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


		
		$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'approve_revised_leave_application_action.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData:false,
			beforeSend: function() {
				
				submit.html('<i class="fa fa-spinner fa-spin"></i> Processing, please wait'); // change submit button text
				setTimeout(200000000000000000);


			},
			success: function(data) {

				//alert(data);
				
				$('#letter').html(data);
				

                if(data==0)
				{
				
				    		
					toastr.error('Error!!');

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				   toastr.success('নোট টি অনুমোদনের জন্য কর্তৃপক্ষের নিকট পাঠানো হয়েছে ।');
				   window.location='allowed_leave_applications?menuslug=allowed-leave-applications';

				  // form.trigger('reset'); // reset form

				  // $('#formresult').html(data);				
				
				}




				
				submit.html('Submit'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});
		
		</script>