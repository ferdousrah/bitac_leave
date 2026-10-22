<?php
include('header.php');

$dataID = $_GET['dataID'];

$getFinDetailsQ = mysqli_query($con,"select * from financial_year where dataID='$dataID'");
$getFinDetailsQRW = mysqli_fetch_assoc($getFinDetailsQ);

$explodeFinYR = explode('-',$getFinDetailsQRW['financial_year']);


?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->

          <div class="row">
    <div class="col-md-12">
	&nbsp;
	</div>
	</div>


<div class="row">
    <div class="col-md-12">
        <h4>Edit Financial Year</h4>
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
	                    <form name="second" id="second" class="form form-horizontal">
						<input type="hidden" name="dataID" value="<?php echo $dataID; ?>" />
	                    	<div class="form-body">
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Financial Year: </label>
		                            <div class="col-md-3">
		                            	    <select id="projectinput5" name="year1" class="form-control" required>
												<option value="none" selected="" disabled=""></option>
												<?php
												  for($i=2018;$i<=2025;$i++)
												  {
												?>
												<option <?php if($explodeFinYR[0]==$i){ echo "selected='selected'";} ?> value="<?php echo $i; ?>"><?php echo $i; ?></option>
												<?php
												  }
													?>
												
											</select>
		                            </div>
									<label class="col-md-1 label-control" for="projectinput1">-</label>
									<div class="col-md-3">
		                            	<select id="projectinput5" name="year2" class="form-control" required>
												<option value="none" selected="" disabled=""></option>
												<?php
												  for($j=2018;$j<=2025;$j++)
												  {
												?>
												<option <?php if($explodeFinYR[1]==$j){ echo "selected='selected'";} ?> value="<?php echo $j; ?>"><?php echo $j; ?></option>
												<?php
												  }
													?>
												
											</select>
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Date </label>
		                            <div class="col-md-3">
		                            	<input type="date" id="issueinput3" value="<?php echo $getFinDetailsQRW['dateFrom']; ?>" class="form-control" name="dateFrom" data-toggle="tooltip" data-trigger="hover" data-placement="top" data-title="Date From" required>
		                            </div>
									<label class="col-md-1 label-control" for="projectinput1">to</label>
									<div class="col-md-3">
		                            	<input type="date" id="issueinput3" value="<?php echo $getFinDetailsQRW['dateTo']; ?>" class="form-control" name="dateTo" data-toggle="tooltip" data-trigger="hover" data-placement="top" data-title="Date To" required>
		                            </div>

		                        </div>
								
		                        	                        						

		                        

	                        <div class="form-actions">
	                            <button type="reset" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button name="submit" id="submit" type="submit" class="btn btn-raised btn-primary">
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
	var form = $('#second'); // contact form
	var submit = $('#submit');	// submit button
	

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'update_financial_year.php', // form action url
			type: 'POST', // form submit method get/post
			dataType: 'html', // request type html/json/xml
			data: form.serialize(), // serialize form data 
			beforeSend: function() {
				
                swal({
                title: '',
                text: 'Please wait...',
                onOpen: function () {
                    swal.showLoading()
                }
                }).then(
                function () {},
                // handling the promise rejection
                function (dismiss) {
                    
                }
                )
				//$('#processing-modal').modal('show');
				//submit.html('<i class="icon-busy"></i>Processing....'); // change submit button text
			},
			success: function(data) {
				//alert(data);
				//alert.html("Process Done").fadeIn(); // fade in response data
				//form.trigger('reset'); // reset form
				//submit.html('<i class="icon-checkmark3"></i>Done'); // reset submit button text
				//$('#processing-modal').modal('hide');
				//alert.fadeOut(3000);
                swal.close()
                // notify
                
                if(data==1)
				{
				
				    toastr.success('Data Saved Successfully');


				    

					
				
				}
				else
				{
				
				    toastr.error('Error on Process');

				
				
				}

                //........
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});

</script>


