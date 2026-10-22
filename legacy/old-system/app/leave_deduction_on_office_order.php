<?php
include('header.php');



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


?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Leave Deduction on Office Order</h4>
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


						<form class="form-login" name="form" id="form" >
					

	                    	<div class="form-body">
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">কর্মকর্তা/কর্মচারী  :<span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
										
		                            	<select class="js-example-basic-single" style="width: 100%;" name="employeeID" required>
												<option value=''></option>
												<?php while($empRow = mysqli_fetch_array($getEmployeeListQ)){				
																						
												?>

												<option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).'-'.$empRow['employee_name']; ?></option>

												<?php } ?>
										</select>
										
		                            </div>
		                        </div>



							



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="leaveType">ডিডাক্ট ফ্রম <span style="color: red;">*</span>  </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="leaveType" required>
												<option value=''></option>
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
	                            	<label class="col-md-3 label-control" for="leaveDeduct">ছুটি কর্তন (দিন) <span style="color: red;">*</span></label>
		                            <div class="col-md-9">
										
		                            		<input type="text" class="form-control" name="leaveDeduct" id="leaveDeduct" required />
										
		                            </div>
		                    </div>



							
							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="note">মন্তব্য <span style="color: red;">*</span></label>
		                            <div class="col-md-9">
										
		                            		<textarea class="form-control" name="note" id="note" required></textarea>
										
		                            </div>
		                    </div>


							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="officeAdesh">অফিস আদেশ <span style="color: red;">*</span></label>
		                            <div class="col-md-9">
										
		                            		<input type="file" class="form-control" name="officeAdesh" id="officeAdesh" required />
										
		                            </div>
		                    </div>

							
														


							<div id="formresult"></div>		


								
	                        <div class="form-actions">
	                             <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-arrow-left"></i> Back
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="ft-check-square"></i> Submit
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




<script type="text/javascript">

//table.row( 100 ).scrollTo();

$('.js-example-basic-single').select2();





$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'save_leave_deduction_by_office_data.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: new FormData(this), // serialize form data
			contentType: false,
            cache: false,
            processData:false,
			beforeSend: function() {
				
				submit.html('<i class="fa fa-spinner fa-spin"></i> processing, please wait'); // change submit button text
				setTimeout(200000000000000000);


			},
			success: function(data) {

				//alert(data);
				

                if(data==1)
				{
				
				    toastr.success('Data Saved Successfully');


				    

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else if(data==0)
				{
				
				    toastr.error('Error!!');
				
				
				}else
				{
				
				    toastr.error(data);
				
				
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






</script>