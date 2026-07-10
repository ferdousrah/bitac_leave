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

$getAllLeaveTypesQ2 = mysqli_query($con, "select * from leave_types");

// ── Multi-segment read-only display (চাহিত + প্রস্তাবিত) ──
$_corrAppID = (int)$leaveApplicationID;
$_corrHasKindCol = false;
$_corrColC = mysqli_query($con, "SHOW COLUMNS FROM leave_application_segments LIKE 'kind'");
if ($_corrColC && mysqli_num_rows($_corrColC) > 0) $_corrHasKindCol = true;
$_corrAllSegRes = mysqli_query($con, "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
    LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
    WHERE s.applicationID = $_corrAppID
    ORDER BY s.kind ASC, s.serial ASC, s.dataID ASC");
$_corrReqSegs = []; $_corrPropSegs = [];
while ($_sr = mysqli_fetch_assoc($_corrAllSegRes)) {
    $k = $_sr['kind'] ?? 'requested';
    if ($k === 'requested') $_corrReqSegs[] = $_sr;
    else                    $_corrPropSegs[] = $_sr;
}
if (empty($_corrReqSegs))  $_corrReqSegs  = $_corrPropSegs;
if (empty($_corrPropSegs)) $_corrPropSegs = $_corrReqSegs;

$getUserDetailsQ = mysqli_query($con, "select * from user_list where dataID = '$_SESSION[userID]'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);


$getCurrentEmpDetailsQ = mysqli_query($con, "select * from `employee_list` where id='$getUserDetailsQRW[employee_id]'");
$getCurrentEmpDetailsQRW = mysqli_fetch_assoc($getCurrentEmpDetailsQ);


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
        <h4>ছুটি সংশোধন</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='add_new_instructor_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> $getLeaveApplicationDetailsQRW -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">


				<form class="form-login" name="form" id="form" >
					<input type="hidden" name="leaveApplicationID" value="<?php echo $leaveApplicationID; ?>" />
					<input type="hidden" name="applicantID" value="<?php echo $getLeaveApplicationDetailsQRW['applicantID']; ?>" />
					<input type="hidden" name="organization_id" value="<?php echo $getCurrentEmpDetailsQRW['organization_id']; ?>" />

	                    	<div class="form-body">

								
			                    
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">আবেদনকারী: </label>
		                            <div class="col-md-9">
										
		                            		<?php echo $getEmployeeDetailsQW['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?>, 
	 <?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getApplicgDetailsDesigQRW['organization_name']; ?>
										
		                            </div>
		                        </div>






								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">অনুমোদনকৃত ছুটি </label>
		                            <div class="col-md-9">

											<?php echo convertDateTotrad($getLeaveApplicationDetailsQRW['approvedDateFrom']); ?> হইতে  <?php echo convertDateTotrad($getLeaveApplicationDetailsQRW['approvedDateTo']); ?> তারিখ পর্যন্ত , <?php echo $dateDiff; ?> দিন,
										
		                            		<?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "কর্তনহীন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?>

</div>
</div>

<?php if (count($_corrReqSegs) > 1 || count($_corrPropSegs) > 1): ?>
<div class="form-group row">
    <label class="col-md-3 label-control"></label>
    <div class="col-md-9">
        <div style="background:#fef3c7; border-left:3px solid #d4a056; padding:10px 14px; border-radius:6px; font-size:13px;">
            <strong>এই আবেদনে একাধিক ছুটির ধরণ আছে।</strong> মূল breakdown:
            <table style="width:100%; margin-top:8px; border-collapse:collapse; font-size:12px; background:#fff; border:1px solid #e5d49a;">
                <thead style="background:#fef9e6;">
                    <tr>
                        <th style="padding:5px 8px; border:1px solid #e5d49a; text-align:left;">ক্রম</th>
                        <th style="padding:5px 8px; border:1px solid #e5d49a; text-align:left;">চাহিত (মূল)</th>
                        <th style="padding:5px 8px; border:1px solid #e5d49a; text-align:left;">প্রস্তাবিত (অনুমোদিত)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $_maxSegs = max(count($_corrReqSegs), count($_corrPropSegs));
                    for ($i = 0; $i < $_maxSegs; $i++):
                        $req = $_corrReqSegs[$i] ?? null;
                        $prop = $_corrPropSegs[$i] ?? null;
                    ?>
                    <tr>
                        <td style="padding:5px 8px; border:1px solid #e5d49a;"><?= $i + 1 ?></td>
                        <td style="padding:5px 8px; border:1px solid #e5d49a;">
                            <?php if ($req): ?>
                                <?= htmlspecialchars($req['leaveTitle'] ?? 'অজানা') ?> · <?= date('d/m/Y', strtotime($req['dateFrom'])) ?> → <?= date('d/m/Y', strtotime($req['dateTo'])) ?> · <?= (int)$req['days'] ?> দিন
                            <?php else: ?> — <?php endif; ?>
                        </td>
                        <td style="padding:5px 8px; border:1px solid #e5d49a;">
                            <?php if ($prop): ?>
                                <?= htmlspecialchars($prop['leaveTitle'] ?? 'অজানা') ?> · <?= date('d/m/Y', strtotime($prop['dateFrom'])) ?> → <?= date('d/m/Y', strtotime($prop['dateTo'])) ?> · <?= (int)$prop['days'] ?> দিন
                            <?php else: ?> — <?php endif; ?>
                        </td>
                    </tr>
                    <?php endfor; ?>
                    <tr style="background:#fef9e6; font-weight:600;">
                        <td style="padding:5px 8px; border:1px solid #e5d49a;">মোট</td>
                        <td style="padding:5px 8px; border:1px solid #e5d49a;"><?= array_sum(array_column($_corrReqSegs, 'days')) ?> দিন</td>
                        <td style="padding:5px 8px; border:1px solid #e5d49a;"><?= array_sum(array_column($_corrPropSegs, 'days')) ?> দিন</td>
                    </tr>
                </tbody>
            </table>
            <small style="color:#8b6f47; display:block; margin-top:6px;">
                <strong>⚠️ Note:</strong> সংশোধনের সময় এই legacy form-এর single date/days fields ব্যবহার হবে। Multi-segment-এর full edit করতে আগে আবেদন decline করে, applicant নতুন আবেদন করুন।
            </small>
        </div>
    </div>
</div>
<?php endif; ?>




												



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveFrom">আবেদনকৃত সংশোধনযোগ্য ছুটি </label>
		                            <div class="col-md-2">
										
		                            	<input type="text" class="form-control" id="leaveFrom" name="leaveFrom" autocomplete="off" onchange="calculateDays()" value="<?php echo convertDateTotrad($aDateFrom); ?>" />
										
		                            </div>
									<label class="col-md-1 label-control" for="leaveTo">পর্যন্ত</label>
		                            <div class="col-md-2">
										
		                            	<input type="text" class="form-control" id="leaveTo" name="leaveTo" autocomplete="off" onchange="calculateDays()" value="<?php echo convertDateTotrad($aDateTo); ?>" />
										
		                            </div>
									<div class="col-md-4">
										
		                            		<input type="number" class="form-control" id="reqLeaveDays" name="approvedDays"  value="<?php echo $dateDiff; ?>" onChange="leaveDay(this.value)" />
										
		                            </div>
		                    </div>


							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID"> ছুটির ধরণ  </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="approvedLeaveType" required>
												<option value=''></option>
												
												<?php
												  while($lRow=mysqli_fetch_array($getAllLeaveTypesQ2)){
												?>

												<option <?php if($getLeaveApplicationDetailsQRW['approvedLeaveType'] == $lRow['leaveID']){ echo "selected"; } ?> value='<?php echo $lRow['leaveID']; ?>'><?php echo $lRow['leaveTitle']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                     </div>						



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">ডিডাক্ট ফ্রম <span style="color: red;">*</span></label>
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


							


							 <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">অফিস আদেশ <span style="color: red;">*</span></label>
		                            <div class="col-md-9">
										
		                            		<input type="file" name="leaveFile" required />
										
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


		function leaveDay(day){
			
			if(day == 0){
				
				//var leaveFrombackup = $('#leaveFrom').val();
				$('#leaveFrom').val('');

				$('#leaveTo').val('');

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


		}


		
		$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_leave_edit_data.php', // form action url
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
				
				//$('#letter').html(data);
				

                if(data==1)
				{
				
				   toastr.success('নোট টি অনুমোদনের জন্য কর্তৃপক্ষের নিকট পাঠানো হয়েছে ।');
				   //window.location='leave_edit_data?menuslug=allowed-leave-applications&leaveApplicationID=' + <?php echo $leaveApplicationID; ?>;
				    		
					

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				   
					toastr.error(data);
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