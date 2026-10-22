<?php
include('header.php');



$dataID = base64_decode($_GET['dataID']);

$todayDate = ShowBangladeshDate();

$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$dataID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);


$diff = abs(strtotime($todayDate)-strtotime($getEmployeeInfoQRW['joining_date']));

$days = round($diff/(60*60*24));

//$days = $days - ($getTotalLWioutPayLeaveInCurrentYearQRW['totalLWithoutPay'] + $getPrevLeaveHistoryRW['leaveWithoutPay']);

// deduct বিনা বেতনে ছুটি and প্রাপ্যতাবিহীন ছুটি from days

$fullAvgSalLeave = floor($days/11);

$fullAvgSalLeaveRemainder = fmod($days, 11);

if($fullAvgSalLeaveRemainder >= 6){

$fullAvgSalLeave = $fullAvgSalLeave + 1;
								
}

//.....

$halfAvgSalLeave = floor($days/12);

$halfAvgSalLeaveRemainder = fmod($days, 12);

if($halfAvgSalLeaveRemainder >= 6){

	$halfAvgSalLeave = $halfAvgSalLeave + 1;
								
}



//echo $dataID;

$checkPrevDataQ = mysqli_query($con, "select * from previous_leave_deduction where employeeID='$dataID'");
$checkPrevDataQNumRows = mysqli_num_rows($checkPrevDataQ);
$checkPrevDataQRW = mysqli_fetch_assoc($checkPrevDataQ);

?>

<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <!--<h4> Leave Deduction on Special Case</h4>-->
		<h4> পূর্ব ছুটির তথ্য ইনপুট</h4>
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">
                        <div class="col-md-12 col-sm-12">
                            




                        <div class="px-3">
	                    <form class="form-login" name="form" id="form" enctype="multipart/form-data">
							
							<input type="hidden" name="employeeID" value="<?php echo $dataID; ?>" />

							<input type="hidden" name="fullAvgSalLeave" id="fullAvgSalLeave" value="<?php echo $fullAvgSalLeave; ?>" />

							<input type="hidden" name="halfAvgSalLeave" id="halfAvgSalLeave" value="<?php echo $halfAvgSalLeave; ?>" />


	                    	<div class="form-body">
			                    
								<div class="form-group row">

									<label class="col-md-2 label-control" for="avgSalaryRest">গড় বেতন অবশিষ্ট পাওনা (দিন):  </label>
		                            <div class="col-md-2">
		                            	<input type="text" id="avgSalaryRest" class="form-control" name="avgSalaryRest" value="" required>
		                            </div>

	                            	<label class="col-md-2 label-control" for="avgSalary">গড় বেতন  ভোগকৃত(দিন):  </label>
		                            <div class="col-md-2">
		                            	<input type="text" id="avgSalary" class="form-control"  name="avgSalary"  value="<?php echo $checkPrevDataQRW['avgSalary']; ?>">
		                            </div>
									<label class="col-md-1 label-control" for="avgSalaryFile">সংযুক্তি: <span id="avgSalaryReq" style="color: red; display: none;">*</span> </label>
		                            <div class="col-md-3">
		                            	<input type="file" id="avgSalaryFile" class="form-control"  name="avgSalaryFile" >
		                            </div>
		                        </div>

								<div class="form-group row">

									<label class="col-md-2 label-control" for="avgSalaryRest">অর্ধ-গড় অবশিষ্ট পাওনা (দিন):  </label>
		                            <div class="col-md-2">
		                            	<input type="text" id="halfAvgSalaryRest" class="form-control" name="halfAvgSalaryRest" value="" required>
		                            </div>

	                            	<label class="col-md-2 label-control" for="halfAvgSalary">অর্ধ-গড় বেতন (দিন):  </label>
		                            <div class="col-md-2">
		                            	<input type="text" id="halfAvgSalary" class="form-control"  name="halfAvgSalary"  value="<?php echo $checkPrevDataQRW['halfAvgSalary']; ?>" >
		                            </div>
									<label class="col-md-1 label-control" for="halfAvgSalaryFile">সংযুক্তি: <span id="halfAvgSalaryReq" style="color: red; display: none;">*</span> </label>
		                            <div class="col-md-3">
		                            	<input type="file" id="halfAvgSalaryFile" class="form-control"  name="halfAvgSalaryFile">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="casual">নৈমিত্তিক (Casual Leave)(দিন) - ভোগকৃত:  </label>
		                            <div class="col-md-3">
		                            	<input type="text" id="casual" class="form-control"  name="casual"  value="<?php echo $checkPrevDataQRW['casual']; ?>" >
		                            </div>
									<label class="col-md-1 label-control" for="casualFile">সংযুক্তি: <span id="casualReq" style="color: red; display: none;">*</span> </label>
		                            <div class="col-md-5">
		                            	<input type="file" id="casualFile" class="form-control"  name="casualFile">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveWithoutPay">অসাধারণ(বিনা বেতনে ছুটি)(দিন) - ভোগকৃত:  </label>
		                            <div class="col-md-3">
		                            	<input type="text" id="leaveWithoutPay" class="form-control"  name="leaveWithoutPay"  value="<?php echo $checkPrevDataQRW['leaveWithoutPay']; ?>" >
		                            </div>
									<label class="col-md-1 label-control" for="leaveWithoutPayFile">সংযুক্তি: <span id="leaveWithoutPayReq" style="color: red; display: none;">*</span> </label>
		                            <div class="col-md-5">
		                            	<input type="file" id="leaveWithoutPayFile" class="form-control"  name="leaveWithoutPayFile">
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="undeductibleLeave">কর্তনহীন ছুটি(দিন) - ভোগকৃত:  </label>
		                            <div class="col-md-3">
		                            	<input type="text" id="undeductibleLeave" class="form-control"  name="undeductibleLeave"  value="<?php echo $checkPrevDataQRW['undeductibleLeave']; ?>" >
		                            </div>
									<label class="col-md-1 label-control" for="undeductibleLeaveFile">সংযুক্তি: <span id="undeductibleReq" style="color: red; display: none;">*</span> </label>
		                            <div class="col-md-5">
		                            	<input type="file" id="undeductibleLeaveFile" class="form-control"  name="undeductibleLeaveFile">
		                            </div>
		                        </div>


								

							
							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveFile">Status</label>
		                            <div class="col-md-9">
										
		                            		<?php 


											
												if($checkPrevDataQNumRows > 0){

													if($checkPrevDataQRW['isApproved'] == 1){

														echo "Approved";
													
													}else if($checkPrevDataQRW['isApproved'] == 2){

														echo "Not Approved";													
													
													}else if($checkPrevDataQRW['isApproved'] == 0){

														echo "Pending";													
													
													}
												
												}
											
											?>
										
		                            </div>
								</div>
							
							<?php
							  if($checkPrevDataQRW['lastUpdate'] != ''){
							?>

							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="">Last Update</label>
		                            <div class="col-md-9">
										
		                            		<?php 


											
												echo $checkPrevDataQRW['lastUpdate'];
											
											?>
										
		                            </div>
								</div>
							
							<?php } ?>

							
							<div id="msg"></div>	

	                        <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Back
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Send for approval
	                            </button>
	                        </div>
	                    </form>
	                </div>





                        </div>
                        
                    </div>
                </div>
                <!--/ Invoice Footer -->
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


$('#avgSalaryRest').keyup(function(){

	fullAvgSalLeave = parseFloat($('#fullAvgSalLeave').val());

	avgSalaryRest = parseFloat($('#avgSalaryRest').val());

	avgSalaryUsed = parseFloat(fullAvgSalLeave - avgSalaryRest);

	$('#avgSalary').val(avgSalaryUsed);

	//..........

	avgSalary = $('#avgSalary').val();

	if(avgSalary > 0){

		$('#avgSalaryReq').show();
		
		$('#avgSalaryFile').prop('required',true);

	}else{
		
		$('#avgSalaryReq').hide();

		$('#avgSalaryFile').prop('required',false);

	}

	//..........

	   

});


$('#halfAvgSalaryRest').keyup(function(){

	halfAvgSalLeave = parseFloat($('#halfAvgSalLeave').val());

	halfAvgSalaryRest = parseFloat($('#halfAvgSalaryRest').val());

	halfAvgSalaryUsed = parseFloat(halfAvgSalLeave - halfAvgSalaryRest);

	$('#halfAvgSalary').val(halfAvgSalaryUsed);


	//.................

	halfAvgSalary = $('#halfAvgSalary').val();

	if(halfAvgSalary > 0){

		$('#halfAvgSalaryReq').show();
		
		$('#halfAvgSalaryFile').prop('required',true);

	}else{
		
		$('#halfAvgSalaryReq').hide();

		$('#halfAvgSalaryFile').prop('required',false);

	}

	//.................

	   

});



$('#avgSalary').keyup(function(){

	avgSalary = $('#avgSalary').val();

	if(avgSalary > 0){

		$('#avgSalaryReq').show();
		
		$('#avgSalaryFile').prop('required',true);

	}else{
		
		$('#avgSalaryReq').hide();

		$('#avgSalaryFile').prop('required',false);

	}

   

});



$('#halfAvgSalary').keyup(function(){

	halfAvgSalary = $('#halfAvgSalary').val();

	if(halfAvgSalary > 0){

		$('#halfAvgSalaryReq').show();
		
		$('#halfAvgSalaryFile').prop('required',true);

	}else{
		
		$('#halfAvgSalaryReq').hide();

		$('#halfAvgSalaryFile').prop('required',false);

	}

   

});



$('#casual').keyup(function(){

	casual = $('#casual').val();

	if(casual > 0){

		$('#casualReq').show();
		
		$('#casualFile').prop('required',true);

	}else{
		
		$('#casualReq').hide();

		$('#casualFile').prop('required',false);

	}

   

});


$('#leaveWithoutPay').keyup(function(){

	leaveWithoutPay = $('#leaveWithoutPay').val();

	if(leaveWithoutPay > 0){

		$('#leaveWithoutPayReq').show();
		
		$('#leaveWithoutPayFile').prop('required',true);

	}else{
		
		$('#leaveWithoutPayReq').hide();

		$('#leaveWithoutPayFile').prop('required',false);

	}

   

});




$('#undeductibleLeave').keyup(function(){

	undeductibleLeave = $('#undeductibleLeave').val();

	if(undeductibleLeave > 0){

		$('#undeductibleReq').show();
		
		$('#undeductibleLeaveFile').prop('required',true);

	}else{
		
		$('#undeductibleReq').hide();

		$('#undeductibleLeaveFile').prop('required',false);

	}

   

});




$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_previous_leave.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData:false,
			beforeSend: function() {
				
				submit.html('<i class="fa fa-spinner fa-spin"></i> Updating, please wait'); // change submit button text
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
				
				    toastr.success('Data Saved Successfully');
					//form.trigger('reset'); // reset form

					window.location='manage_employees?menuslug=manage-employee';
				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('Submit'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});





// on course select



// end of on course event






});


 $(".organization_id").change(function() {

	  var id=$(this).val();
	  var dataString = 'id='+ id;
	  
	  $.ajax
	  ({
	   type: "POST",
	   url: "get_departments.php",
	   data: dataString,
	   cache: false,
	   success: function(html)
	   {
		  $(".department_id").html(html);
	   } 
	   });

  });
  

	 
	 
	 
	 // get sections by dept id
	 
	  $(".department_id").change(function() {

	  var id=$(this).val();
	  var dataString = 'id='+ id;
	  
	 
	  $.ajax
	  ({
	   type: "POST",
	   url: "get_sections.php",
	   data: dataString,
	   cache: false,
	   success: function(html)
	   {
		  $(".section_id").html(html);
	   } 
	   });

  });



</script>