<?php
include('header.php');

$dataID = $_GET['dataID'];

$getCourseInfoQ = mysqli_query($con, "select * from courses where dataID='$dataID'");
$getCourseInfoQRW = mysqli_fetch_assoc($getCourseInfoQ);

?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Edit Course Information</h4>
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
	                    <form name="form" id="form" enctype="multipart/form-data">

				 <input type="hidden" name="dataID" value="<?php echo $dataID; ?>" />


				 <div class="form-group row">
					<label class="col-sm-2 control-label">Course Code&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="courseCode" value="<?php echo $getCourseInfoQRW['courseCode']; ?>" required>
					</div>
				</div>
                
                <div class="form-group row">
					<label class="col-sm-2 control-label">Course Title&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="courseTitle" value="<?php echo $getCourseInfoQRW['courseTitle']; ?>" required>
					</div>
				</div>

				<div class="form-group row">
					<label class="col-sm-2 control-label">Total Session&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="no_of_classes" value="<?php echo $getCourseInfoQRW['no_of_classes']; ?>" required>
					</div>
				</div>

				<div class="form-group row">
					<label class="col-sm-2 control-label">Total Hour&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="total_hour" value="<?php echo $getCourseInfoQRW['total_hour']; ?>" required>
					</div>
				</div>

				<div class="form-group row">
					<label class="col-sm-2 control-label">Price&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="price" value="<?php echo $getCourseInfoQRW['price']; ?>" required>
					</div>
				</div>                            
                

                
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
			url: 'edit_course_data.php', // form action url
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
				

                if(data==1)
				{
				
				    toastr.success('Data Saved Successfully');


				    

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else
				{
				
				    toastr.error('Error!!');
				
				
				}




				//form.trigger('reset'); // reset form
				submit.html('Login Now'); // reset submit button text
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});



</script>