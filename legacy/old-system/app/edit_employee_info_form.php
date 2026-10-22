<?php
include('header.php');


$dataID = base64_decode($_GET['dataID']);


$getEmployeeInfoQ = mysqli_query($con, "select * from `employee_list` where `id`='$dataID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeInfoQ);


$getAllDept = mysqli_query($con, "select * from `departments` where `deleted`=0");

$getAllDesig = mysqli_query($con, "select * from `job_title` where `deleted`=0");



$getAllGrade = mysqli_query($con, "select * from `grade` where `deleted`=0");


$getAllOrg = mysqli_query($con, "select * from `organization` where `deleted`=0");


$getAllDesignations = mysqli_query($con, "select distinct job_title_name from job_title where deleted=0");

$getAllSectionsQ = mysqli_query($con, "SELECT * FROM `sections` where deleted=0");

$getAllGrade2 = mysqli_query($con, "SELECT * FROM `grade` where deleted=0");

$getAllGroupQ = mysqli_query($con, "select * from salary_group");

$getScalesQ = mysqli_query($con, "select * from grade where deleted=0");

?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4> কর্মকর্তা / কর্মচারীর  তথ্য পরিবর্তন</h4>
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
							<input type="hidden" name="dataID" value="<?php echo $dataID; ?>" />
							<input type="hidden" name="prevPhoto" value="<?php echo $getEmployeeInfoQRW['photo']; ?>" />
							<input type="hidden" name="prevsignature" value="<?php echo base64_encode($getEmployeeInfoQRW['signature']); ?>" />

	                    	<div class="form-body">
	                    		<h4 class="form-section"><i class="ft-user"></i> ব্যক্তিগত তথ্য </h4>
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employee_name">নাম: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="employee_name" class="form-control"  name="employee_name"  value="<?php echo $getEmployeeInfoQRW['employee_name']; ?>" required>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employee_name">জাতীয় পরিচয়পত্র নং: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="nid" class="form-control"  value="<?php echo $getEmployeeInfoQRW['nationalID']; ?>" name="nid" required>
		                            </div>
		                        </div>



								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="designation">পদবি: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<select data-placeholder="Choose a designation..." class="form-control designation" name="designation" id="designation" required>
											<option value="0">--পদবি নির্বাচন করুন--</option>
											<?php
											while($degRow=mysqli_fetch_array($getAllDesig))
											{
											?>
											<option <?php if($getEmployeeInfoQRW['designation'] == $degRow['id']) { echo "selected"; } ?> value="<?php echo $degRow['id']; ?>"><?php echo $degRow['job_title_name']; ?></option>
											<?php
											}
											?>
										</select>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employee_id">আইডি: <span style="color: red;">*</span> </label>
		                            <div class="col-md-3">
		                            	<input type="text" id="employee_id" class="form-control"  name="employee_id" value="<?php echo $getEmployeeInfoQRW['employee_id']; ?>" required>
		                            </div>
									<label class="col-md-3 label-control" for="memorialNo">স্মারক নম্বর: <span style="color: red;">*</span> </label>
		                            <div class="col-md-3">
		                            	<input type="text" id="memorialNo" class="form-control"  name="memorialNo" value="<?php echo $getEmployeeInfoQRW['memorialNo']; ?>" required>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="employee_type">চাকরীর ধরন: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="employee_type" required>
											<option <?php if($getEmployeeInfoQRW['employee_type'] == 1) { echo "selected"; } ?> value="1">কর্মকর্তা</option>
											<option <?php if($getEmployeeInfoQRW['employee_type'] == 2) { echo "selected"; } ?> value="2">কর্মচারী</option>
											<option <?php if($getEmployeeInfoQRW['employee_type'] == 3) { echo "selected"; } ?> value="3">পি আর এল</option>
										</select>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="dob">জন্ম তারিখ: <span style="color: red;">*</span> </label>
		                            <div class="col-md-4">
		                            	<div class="position-relative has-icon-left">
										<input type="date" id="timesheetinput3" class="form-control" name="dob" value="<?php echo $getEmployeeInfoQRW['date_of_birth']; ?>" required>
										<div class="form-control-position">
											<i class="ft-message-square"></i>
										</div>
										</div>
		                            </div>
									<label class="col-md-2 label-control" for="joining_date">চাকরিতে যোগদানের তারিখ  : <span style="color: red;">*</span> </label>
		                            <div class="col-md-3">
		                            	<div class="position-relative has-icon-left">
										<input type="date" id="timesheetinput3" class="form-control" name="joining_date" value="<?php echo $getEmployeeInfoQRW['joining_date']; ?>" required>
										<div class="form-control-position">
											<i class="ft-message-square"></i>
										</div>
										</div>
		                            </div>
		                        </div>
								


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="email">ইমেইল: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="email" class="form-control"  name="email" value="<?php echo $getEmployeeInfoQRW['email']; ?>" >
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="mobileNo">মোবাইল নম্বর: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="mobileNo" class="form-control"  name="mobileNo" value="<?php echo $getEmployeeInfoQRW['mobileNo']; ?>" required>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="photo">ফটো: </label>
		                            <div class="col-md-8">
		                            	<input type="file" id="photo" class="form-control"  name="photo"><span style="color: red; font-size: 11px;">Please choose a JPEG or PNG file.</span>
		                            </div>
									<div class="col-md-1">

										<img src="uploads/<?php echo $getEmployeeInfoQRW['photo']; ?>" height="60" />

									</div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="signature">স্বাক্ষর: </label>
		                            <div class="col-md-6">
		                            	<input type="file" id="signature" class="form-control"  name="signature"><span style="color: red; font-size: 11px;">Please choose a JPEG or PNG file.</span>
		                            </div>
									<div class="col-md-3">
										<?php if($getEmployeeInfoQRW['signature'] != ''){ ?>
										<img height="80" src="data:image/jpg;charset=utf8;base64,<?php echo base64_encode($getEmployeeInfoQRW['signature']); ?>" />
										<?php } ?>

									</div>
		                        </div>


								<h4 class="form-section"><i class="fa fa-money"></i>কর্মক্ষেত্র</h4>
								
								
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="section_id">শাখা : <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<select class="form-control section_id" name="section_id" id="section_id" required>

										<option value=''></option>
											<?php while($secRow = mysqli_fetch_array($getAllSectionsQ)){ ?>

											<option <?php if($getEmployeeInfoQRW['section_id'] == $secRow['id']) { echo "selected"; } ?> value='<?php echo $secRow['id']; ?>'><?php echo $secRow['section_name']; ?></option>

											<?php } ?>
                                
										</select>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="section_id">কেন্দ্র : <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="organization_id" id="organization_id" required>

										<option value=''></option>
											<?php while($orgRow = mysqli_fetch_array($getAllOrg)){ ?>

											<option <?php if($getEmployeeInfoQRW['organization_id'] == $orgRow['id']) { echo "selected"; } ?> value='<?php echo $orgRow['id']; ?>'><?php echo $orgRow['organization_name']; ?></option>

											<?php } ?>
                                
										</select>
		                            </div>
		                        </div>


								

								

								

								<h4 class="form-section"><i class="fa fa-money"></i> বেতন সংক্রান্ত তথ্য</h4>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="pay_scale">বর্তমান বেতন স্কেল : <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<select class="form-control pay_scale" name="pay_scale" id="pay_scale" required>
										<option value="">--বেতন স্কেল নির্বাচন করুন--</option>
										<?php while($sRow = mysqli_fetch_array($getScalesQ)){ ?>
											
											<option <?php if($getEmployeeInfoQRW['pay_scale'] == $sRow['id']){ echo "selected"; } ?> value="<?php echo $sRow['id']; ?>"><?php echo $obj->engToBn($sRow['minimum_salary'])." - ".$obj->engToBn($sRow['maximum_salary'])."(".$sRow['grade_title'].")"; ?></option>

										
										<?php } ?>
										</select>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="basic_salary">বর্তমান মূল বেতন : <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="basic_salary" class="form-control"  name="basic_salary" value="<?php echo $getEmployeeInfoQRW['basic_salary']; ?>" required>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="salary_group_id">রিপোর্ট ক্যাটাগরি: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="salary_group_id" required>
											<option value="0">--Select--</option>
											<?php
											while($sgRow=mysqli_fetch_array($getAllGroupQ))
											{
											?>
											<option <?php if($getEmployeeInfoQRW['salary_group_id'] == $sgRow['id']) { echo "selected"; } ?> value="<?php echo $sgRow['id']; ?>"><?php echo $sgRow['head']."=>".$sgRow['sub_head']; ?></option>
											<?php
											}
											?>
										</select>
		                            </div>
		                        </div>

							
							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="basic_salary">ডিসপ্লে অর্ডার: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="number" id="display_order" class="form-control"  name="display_order" value="<?php echo $getEmployeeInfoQRW['display_order']; ?>" required>
		                            </div>
		                        </div>



							<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="section_id">কর্মসংস্থানের অবস্থা : <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="radio" <?php if($getEmployeeInfoQRW['employment_status'] == 1) { echo "checked"; } ?> name="employment_status" value="1" /> চাকরিরত &nbsp;&nbsp;<input type="radio" name="employment_status" <?php if($getEmployeeInfoQRW['employment_status'] == 0) { echo "checked"; } ?> value="0" /> কর্মরত নন&nbsp;&nbsp;<input type="radio" name="employment_status" <?php if($getEmployeeInfoQRW['employment_status'] == 2) { echo "checked"; } ?> value="2" /> পি আর এল
		                            </div>
		                      </div>

								
							
							<div id="msg"></div>	

	                        <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Update Now
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


$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'edit_employee_data.php', // form action url
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

					//window.location='print_receipt?menuslug=admission-form&receiptno='+data;
				
				
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




// photo and signature

document.getElementById('photo').addEventListener('change', function(e) {
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



document.getElementById('signature').addEventListener('change', function(e) {
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


</script>