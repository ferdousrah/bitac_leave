<?php
include('header.php');

$getAllEmployeeListQ = mysqli_query($con,"select * from employee_list");


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



	if(isset($_POST['submit'])){

		if(isset($_POST['employeeID']) && $_POST['employeeID']!=''){


			$empSQL = " and applicantID='$_POST[employeeID]'";
		
		
		}else{

			$empSQL = "";
		
		
		}



		if(isset($_POST['leaveTypeInTwo']) && $_POST['leaveTypeInTwo']!=''){
		
			$leaveTypeInTwoSQL = " and leaveTypeInTwo='$_POST[leaveTypeInTwo]'";
		
		}else{
		
			$leaveTypeInTwoSQL = "";
		
		}

		//echo $_POST['dateFrom'];

		$dateFromArray = explode('/', $_POST['dateFrom']);

		//$dateF=date_create($_POST['dateFrom']);

		$dateFrom = $dateFromArray[2].'-'.$dateFromArray[1].'-'.$dateFromArray[0];


		$dateToArray = explode('/', $_POST['dateTo']);

		$dateTo = $dateToArray[2].'-'.$dateToArray[1].'-'.$dateToArray[0];


		$getOfficeNoticesQ = mysqli_query($con, "select * from leave_applications where 1=1 $empSQL $leaveTypeInTwoSQL and (officeNoticeDate between '$dateFrom' and '$dateTo') and officeNoticeDate!='' order by officeNoticeNumber asc");

		//echo "select * from leave_applications where 1=1 $empSQL $leaveTypeInTwoSQL and (officeNoticeDate between '$dateFrom' and '$dateTo') and officeNoticeDate!='' order by officeNoticeNumber asc";

	
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
        <h4>অফিস আদেশ</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='add_new_instructor_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">


				<form action="" method="post" >
	                    	<div class="form-body">

								<div class="form-group row">
	                            	<label class="col-md-2 label-control" for="employeeID">কর্মকর্তা/ কর্মচারী নির্বাচন করুনঃ</label>
		                            <div class="col-md-3">
										
		                            		<select class="employeeID" style="width: 100%;" name="employeeID">
												<option value=''>All</option>
												<?php
												  while($empRow=mysqli_fetch_array($getAllEmployeeListQ)){
													  
												?>

												<option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-2 label-control" for="employeeID">ডিডাক্ট ফ্রম </label>
		                            <div class="col-md-3">
										
		                            		<select class="form-control" name="leaveTypeInTwo">
												<option value=''>All</option>												
												<option value="1">গড় বেতন </option>
												<option value="2">অর্ধ-গড় বেতন </option>
												<option value="3">নৈমিত্তিক (Casual Leave)</option>
												<option value="4">বিনা বেতনে ছুটি</option>
												<option value="10">অসাধারণ ছুটি</option>
												<option value="5">ঐচ্ছিক ছুটি</option>
												<option value="6">কর্তনহীন ছুটি</option>
											</select>
										
		                            </div>
		                     </div>


			                    
								<div class="form-group row">
	                            	<label class="col-md-2 label-control" for="dateFrom">Date From </label>
		                            <div class="col-md-3">
										
		                            		<input type="text" class="form-control" id="dateFrom" name="dateFrom" required autocomplete="off" />
										
		                            </div>
									<label class="col-md-1 label-control" for="dateTo">Date To </label>
		                            <div class="col-md-3">
										
		                            		<input type="text" class="form-control" id="dateTo" name="dateTo" required autocomplete="off" />
										
		                            </div>
		                        </div>


								


								
	                        <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Search
	                            </button>
	                        </div>
	                    </form>

						
						<?php 
							if(isset($_POST['submit'])){

								
						?>



							<section id="configuration">
								<div class="row">
									<div class="col-12">
										<div class="card">
											
											<div class="card-body collapse show">
												<div class="card-block card-dashboard">

													<table class="table table-striped table-bordered zero-configuration">
														<thead>
															<tr>
																<th>ক্রমিক</th>
																<th>আদেশ নং</th>
																<th>নাম ও পদবী</th>
																<th>ডিডাক্ট ফ্রম </th>
																<th>তারিখ</th>
																<th>অফিস আদেশ</th>
															</tr>
														</thead>
														<tbody>

															<?php $sl = 1; while($dataRow = mysqli_fetch_array($getOfficeNoticesQ)){ 


																$officeNoticeDateArray = explode('-', $dataRow['officeNoticeDate']);

																$officeNoticeDate = $officeNoticeDateArray['2'].'/'.$officeNoticeDateArray[1].'/'.$officeNoticeDateArray[0];

																//......
																$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$dataRow[applicantID]'");
																$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

																$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
																$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

																$getApplicgDetailsDesigQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQW[organization_id]'");
																$getApplicgDetailsDesigQRW = mysqli_fetch_assoc($getApplicgDetailsDesigQ);

																$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
																$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

																//....

																if($dataRow['leaveTypeInTwo'] == 1){
																
																	$leaveType = "গড় বেতন ";
																
																}else if($dataRow['leaveTypeInTwo'] == 2){
																
																	$leaveType = "অর্ধ-গড় বেতন ";
																
																}else if($dataRow['leaveTypeInTwo'] == 3){
																
																	$leaveType = "নৈমিত্তিক (Casual Leave)";
																
																}else if($dataRow['leaveTypeInTwo'] == 4){
																
																	$leaveType = "বিনা বেতনে ছুটি";
																
																}else if($dataRow['leaveTypeInTwo'] == 10){
																
																	$leaveType = "অসাধারণ ছুটি";
																
																}else if($dataRow['leaveTypeInTwo'] == 5){
																
																	$leaveType = "ঐচ্ছিক ছুটি";
																
																}else if($dataRow['leaveTypeInTwo'] == 6){
																
																	$leaveType = "কর্তনহীন ছুটি";
																
																}


															  
															?>
															<tr>
																<td><?php echo $sl; ?></td>
																<td><?php echo $dataRow['officeNoticeNumber']; ?></td>
																<td><?php echo $getEmployeeDetailsQW['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?>, 
	 <?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getApplicgDetailsDesigQRW['organization_name']; ?></td>

	 															<td><?php echo $leaveType; ?></td>

																<td><?php echo $officeNoticeDate; ?></td>
																<td><a href="leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=<?php echo $dataRow['dataID']; ?>" target="_blank">View</a></td>
																
															</tr>
															<?php $sl++; } ?>
															
														</tbody>
														
													</table>
												</div>
											</div>
										</div>
									</div>
								</div>
							</section>




						<?php } ?>
                
                
                
								

               
							
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
			$("#dateFrom").datepicker({
			  dateFormat: "dd/mm/yy"
			});
			$("#dateTo").datepicker({
			  dateFormat: "dd/mm/yy"
			});
		  });


		$('.employeeID').select2();
		
		$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'increment_report_view.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData:false,
			beforeSend: function() {
				
				submit.html('<i class="fa fa-spinner fa-spin"></i> Signing in, please wait'); // change submit button text
				setTimeout(200000000000000000);


			},
			success: function(data) {

				//alert(data);
				

                if(data==0)
				{
				
				    		
					toastr.error('Error!!');

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				   // toastr.success('Data Saved Successfully');

				   $('#formresult').html(data);


				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('Submit'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});
		

		function target_popup(form) {
    window.open('', 'formpopup', 'width=1000,height=1000,resizeable,scrollbars');
    form.target = 'formpopup';
}



		</script>