<?php
include('header.php');

$getAllModuleQ = mysqli_query($con,"select * from modules");



?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>New Sub-Module</h4>
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
	                    <form class="form-login" name="form" id="form">
	                    	<div class="form-body">
	                    		<h4 class="form-section"><i class="ft-file-text"></i> Module Information</h4>
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Enter Sub-Module Name: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="projectinput1" class="form-control"  name="submodule_name">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Select Module: </label>
		                            <div class="col-md-9">
		                            	<select name="module_id" class="form-control">
										<option value=""></option>
										<?php
										  while($mRow=mysqli_fetch_array($getAllModuleQ))
										  {
										?>
											<option value="<?php echo $mRow['dataID']; ?>"><?php echo $mRow['module_name']; ?></option>

											<?php
										  }
												?>
										</select>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="page_link">Page Link: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="page_link" class="form-control"  name="page_link">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="slug">Slug: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="slug" class="form-control"  name="slug">
		                            </div>
		                        </div>

								
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="display_order">Display Order </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="display_order" class="form-control"  name="display_order">
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
			url: 'insert_submodule_data.php', // form action url
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