<?php
include('header.php');

$incrementYear = date('Y');

$getisGenerateOrNotQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='$incrementYear' limit 0,1");
$getisGenerateOrNotQNumRows = mysqli_num_rows($getisGenerateOrNotQ);

$getAllEmployeeQ = mysqli_query($con, "select * from employee_list where employment_status=1 or employment_status=2");

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
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>স্যালারি ইনক্রিমেন্ট অটো জেনারেট</h4>
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
	                    <form class="form form-horizontal"  name="form" id="form" enctype="multipart/form-data">

                        					 
	                    	<div class="form-body">

							<?php if($getisGenerateOrNotQNumRows > 0){ $getisGenerateOrNotQRW = mysqli_fetch_assoc($getisGenerateOrNotQ); ?><p style="color: red;text-align: center;">
							Salary increment already generated on <?php echo $getisGenerateOrNotQRW['genDateTime']; ?>
							</p> <?php } ?>

			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="software_title">বেতন বৃদ্ধির বছর: </label>
		                            <div class="col-md-9">

										<select class="form-control" name="incrementYear">

											<option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
											
										</select>

		                            </div>
		                        </div>



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employeeID">কর্মচারী </label>
		                            <div class="col-md-9">
										
		                            		<select class="js-example-basic-single" style="width: 100%;" name="employeeID" id="employeeID" required>
												<option value=''></option>												
												<?php
												  while($empRow=mysqli_fetch_array($getAllEmployeeQ)){

													  $getSupervisorDesigQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
													  $getSupervisorDesigQRW = mysqli_fetch_assoc($getSupervisorDesigQ);
												?>

												<option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).'-'.$empRow['employee_name'].', '.$getSupervisorDesigQRW['job_title_name']; ?></option>


												<?php } ?>
												<option value='0'>সকল</option>
											</select>
										
		                            </div>
		                        </div>

						

	                        <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Generate Now
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
			url: 'auto_generate_increment_action.php', // form action url
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
				

                if(data==1)
				{
				
				    toastr.success('Data Generated Successfully');


				    

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				    toastr.error('Error!!');
				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('Generate Again'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});






</script>