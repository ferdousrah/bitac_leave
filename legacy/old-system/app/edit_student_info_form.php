<?php
include('header.php');

$studentID = base64_decode($_GET['dataID']);


$getStudentInfoQ = mysqli_query($con, "select * from `students` where `dataID`='$studentID'");
$getStudentInfoQRW = mysqli_fetch_assoc($getStudentInfoQ);

$getAllCourseQ = mysqli_query($con, "select * from `courses`");

$getLastMoneyReceiptQ = mysqli_query($con, "select * from `money_receipt` order by `dataID` desc LIMIT 0,1");

$getLastMoneyReceiptQNumRows = mysqli_num_rows($getLastMoneyReceiptQ);


if($getLastMoneyReceiptQNumRows > 0) {

	$getLastMoneyReceiptQRW = mysqli_fetch_assoc($getLastMoneyReceiptQ);

	$newReceiptNo = $getLastMoneyReceiptQRW['dataID'] + 1;

	$newReceiptNoFormatted = str_pad($newReceiptNo, 5, '0', STR_PAD_LEFT);

}
else {

	$newReceiptNo = 1;

	$newReceiptNoFormatted = str_pad($newReceiptNo, 5, '0', STR_PAD_LEFT);

}


?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Edit Student Information</h4>
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
							<input type="hidden" name="studentID" value="<?php echo $studentID; ?>" />
							<input type="hidden" name="prevPhoto" value="<?php echo $getStudentInfoQRW['photo']; ?>" />
	                    	<div class="form-body">
	                    		<h4 class="form-section"><i class="ft-user"></i> Student Information</h4>
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="full_name">Name: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="full_name" class="form-control"  name="full_name" value="<?php echo $getStudentInfoQRW['full_name']; ?>" required>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="father_name">Father's/Spouse Name: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="father_name" class="form-control"  name="father_name" value="<?php echo $getStudentInfoQRW['father_name']; ?>" required>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="designation">Mother's Name: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="mother_name" class="form-control"  name="mother_name" value="<?php echo $getStudentInfoQRW['mother_name']; ?>" required>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="dob">Date of Birth: <span style="color: red;">*</span> </label>
		                            <div class="col-md-4">
		                            	<div class="position-relative has-icon-left">
										<input type="date" id="timesheetinput3" class="form-control" name="dob" value="<?php echo $getStudentInfoQRW['date_of_birth']; ?>" required>
										<div class="form-control-position">
											<i class="ft-message-square"></i>
										</div>
										</div>
		                            </div>
									<label class="col-md-2 label-control" for="gender">Gender: <span style="color: red;">*</span> </label>
		                            <div class="col-md-3">
		                            	<select class="form-control" id="gender" name="gender" required>
										<option value=""></option>
										<option <?php if($getStudentInfoQRW['gender'] == 'Male') { echo "selected"; } ?> value="Male">Male</option>
										<option <?php if($getStudentInfoQRW['gender'] == 'Female') { echo "selected"; } ?> value="Female">Female</option>
										</select>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="nationality">Nationality: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="nationality" class="form-control"  name="nationality" value="<?php echo $getStudentInfoQRW['nationality']; ?>" >
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="academic_qualification">Academic Qualification: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="academic_qualification" class="form-control"  name="academic_qualification"  value="<?php echo $getStudentInfoQRW['academic_qualification']; ?>" required>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="academic_institute">Academic Institute: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="academic_institute" class="form-control"  name="academic_institute" value="<?php echo $getStudentInfoQRW['academic_institute']; ?>" required>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="present_address">Present Address: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="present_address" class="form-control"  name="present_address" value="<?php echo $getStudentInfoQRW['present_address']; ?>" required>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="guardians_cell">Guardian's Cell: <span style="color: red;">*</span> </label>
		                            <div class="col-md-3">
		                            	<input type="text" id="guardians_cell" class="form-control"  name="guardians_cell" value="<?php echo $getStudentInfoQRW['guardians_cell']; ?>" required>
		                            </div>
									<label class="col-md-3 label-control" for="residence_tele">Residence Tele: </label>
		                            <div class="col-md-3">
		                            	<input type="text" id="residence_tele" class="form-control"  name="residence_tele" value="<?php echo $getStudentInfoQRW['residence_tele']; ?>" >
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="students_cell">Student's Cell: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="students_cell" class="form-control"  name="students_cell" value="<?php echo $getStudentInfoQRW['students_cell']; ?>" required>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="passport_no">Passport No: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="passport_no" class="form-control"  name="passport_no" value="<?php echo $getStudentInfoQRW['passport_no']; ?>" required>
		                            </div>
		                        </div>


								

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="study_abroad">Study Abroad: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="study_abroad" class="form-control"  name="study_abroad" value="<?php echo $getStudentInfoQRW['study_abroad']; ?>" required>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="email">E-mail: <span style="color: red;">*</span> </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="email" class="form-control"  name="email" value="<?php echo $getStudentInfoQRW['email']; ?>" required>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="photo">Photo: </label>
		                            <div class="col-md-9">
		                            	<input type="file" id="photo" class="form-control"  name="photo">
		                            </div>
		                        </div>



								
							
							<div id="msg"></div>	

	                        <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> Save
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
			url: 'edit_student_data.php', // form action url
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
				
				    toastr.success('Data Saved Successfully');

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


$(".courseID").change(function()
 {
  var id=$(this).val();
  var dataString = 'courseID='+ id;
  
  //alert(dataString);
 
  $.ajax
  ({
   type: "POST",
   url: "get_batch_course.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $(".batchID").html(html);

	  getCoursePrice(id);
   } 
   });
  });


// end of on course event






});


function getCoursePrice (courseID) {

var dataString = 'courseID='+ courseID;


$.ajax
  ({
   type: "POST",
   url: "get_course_price.php",
   data: dataString,
   cache: false,
   success: function(html)
   {
      $("#fees").val(html);
   } 
   });


}





$(document).on('change keyup blur','.paid',function(){

	var paid = parseFloat($('#paid').val());

	if (paid == '')
	{
		paid = 0;
	}
	
	var fees = parseFloat($('#fees').val());

	dues = parseFloat(fees - paid);

	if(Number.isNaN(dues) == true) { dues = fees; }

	$('#dues').val(dues);



});






</script>