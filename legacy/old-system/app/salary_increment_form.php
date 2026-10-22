<?php
include('header.php');

$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list`");


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
        <h4>বেতন বৃদ্ধির ফরম</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='add_new_instructor_form?menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> Add New</button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">


				<form action="increment_form_result.php" onsubmit="target_popup(this)" method="post" >
	                    	<div class="form-body">
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="courseID">কর্মকর্তা/ কর্মচারী সিলেক্ট করুন : </label>
		                            <div class="col-md-9">
										
		                            		<select class="employeeID" style="width: 100%;" name="employeeID" required>
												<option value=''></option>
												<?php
												  while($empRow=mysqli_fetch_array($getAllEmployeeListQ)){

													  $getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
													  $getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

													  $getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$empRow[section_id]'");
													  $getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

													  $getorgDetailsQ = mysqli_query($con, "select * from organization where id='$empRow[organization_id]'");
													  $getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);
												?>

												<option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name']; ?>, <?php echo $getDesignationDetailsQRW['job_title_name']; ?>, <?php echo $getSectionDetailsQRW['section_name']; ?>, <?php echo $getorgDetailsQRW['organization_name']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                        </div>


								
	                        <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Generate
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

		
		$('.employeeID').select2();

		
		$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'increment_form_result.php', // form action url
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