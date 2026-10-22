<?php
include('header.php');


$id = $_GET['dataID'];

$getSigInfoQ = mysqli_query($con, "select * from leave_approval_signatory where dataID='$id'");
$getSigInfoQRW = mysqli_fetch_assoc($getSigInfoQ);

$getAllOrgQ = mysqli_query($con, "select * from organization");

$getAllJobTitleQ = mysqli_query($con, "select * from job_title");


?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>সিগনেটরি আপডেট</h4>
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
							<input type="hidden" name="dataID" value="<?php echo $id; ?>" />
	                    	<div class="form-body">
	                    		
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="organization_id">কেন্দ্র সিলেক্ট করুন: </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="organization_id">
											<option value=''></option>
											<?php while($orgRow = mysqli_fetch_array($getAllOrgQ)){ ?> <option <?php if($orgRow['id'] == $getSigInfoQRW['organization_id']){ echo "selected"; } ?> value='<?php echo $orgRow['id']; ?>'><?php echo $orgRow['organization_name']; ?></option> <?php } ?>
										</select>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="designationID">পদবী: </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="designationID">
											<option value=''></option>
											<?php while($jobRow = mysqli_fetch_array($getAllJobTitleQ)){ ?> <option <?php if($jobRow['id'] == $getSigInfoQRW['designationID']){ echo "selected"; } ?> value='<?php echo $jobRow['id']; ?>'><?php echo $jobRow['job_title_name']; ?></option> <?php } ?>
										</select>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="isMandatory">বাধ্যতামূলক</label>
		                            <div class="col-md-9">
		                            	<input type="radio" id="isMandatory" name="isMandatory" value="1" <?php if($getSigInfoQRW['isMandatory'] == 1){ echo "checked"; } ?> >&nbsp;হ্যাঁ &nbsp;&nbsp;<input type="radio" id="isMandatory" name="isMandatory" value="0" <?php if($getSigInfoQRW['isMandatory'] == 0){ echo "checked"; } ?> >&nbsp;না
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
			url: 'edit_signatory_data.php', // form action url
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
				
				    toastr.success('Data Saved Successfully');


				    //form.trigger('reset'); // reset form
					submit.html('Save'); // reset submit button text

					//window.location='dashboard?mainslug=dashboard';
				
				}
				else if(data==0)
				{
				
				    toastr.error('Error!!');
				
				
				}else{
					
					toastr.error(data);

				}




				
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});



</script>