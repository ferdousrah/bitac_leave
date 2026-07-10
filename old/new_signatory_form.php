<?php
include('header.php');

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $organization_id = htmlspecialchars(trim($_GET['id']), ENT_QUOTES, 'UTF-8');

    $stmt = $con->prepare("SELECT organization_name FROM organization WHERE id = ?");
    $stmt->bind_param("s", $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $getOrgDetailsQRW = $result->fetch_assoc();

	$orgName = "(";
    $orgName = $orgName.$getOrgDetailsQRW['organization_name'] ?? '';
	$orgName = $orgName.")";
	
}

$getAllOrgQ = mysqli_query($con, "SELECT id, organization_name FROM organization");
$getAllJobTitleQ = mysqli_query($con, "SELECT id, job_title_name FROM job_title");



?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>সিগনেটরি এন্ট্রি  <?php echo $orgName; ?></h4>
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

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="organization_id">কেন্দ্র সিলেক্ট করুন: </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="organization_id" id="organization_id">
											<option value=''></option>
											<?php while($orgRow = mysqli_fetch_array($getAllOrgQ)){ ?> <option value='<?php echo $orgRow['id']; ?>'><?php echo $orgRow['organization_name']; ?></option> <?php } ?>
										</select>
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="designationID">পদবী: </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="designationID" name="designationID">
											<option value=''></option>
											<?php while($jobRow = mysqli_fetch_array($getAllJobTitleQ)){ ?> <option value='<?php echo $jobRow['id']; ?>'><?php echo $jobRow['job_title_name']; ?></option> <?php } ?>
										</select>
		                            </div>
		                        </div>


								<div class="form-group row">
									<label class="col-md-3 label-control">&nbsp;</label>
	                            	<label class="col-md-9 label-control" id="empdetails"></label>
								</div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="display_order">অনুমতির ক্রমিক নং</label>
		                            <div class="col-md-9">
		                            	<input type="number" id="approvalSL" class="form-control"  name="approvalSL" value="" min="2">
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


// 


// Trigger search when either organization or designation is changed
    $('#organization_id, select[name="designationID"]').on('change', function () {
        const orgID = $('#organization_id').val();
        const designationID = $('select[name="designationID"]').val();

        if (orgID && designationID) {
            $.ajax({
                url: 'getempbyorgdesig.php',
                type: 'GET',
                data: {
                    organization_id: orgID,
                    designation_id: designationID
                },
                beforeSend: function () {
                    // Optional: show a loader
                    $('#empdetails').html('Loading...');
                },
                success: function (data) {
                    // You can return HTML <option> elements from PHP
                    $('#empdetails').html(data);
                },
                error: function () {
                    $('#empdetails').html('Error loading data');
                }
            });
        } else {
           // $('#empdetails').html('Select organization and designation');
        }
    });


///.......






	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_signatory_data.php', // form action url
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


				    form.trigger('reset'); // reset form
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