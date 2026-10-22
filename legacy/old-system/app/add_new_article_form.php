<?php
include('header.php');

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
        <h4>New Article Entry</h4>
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
	                    	<div class="form-body">
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Ordinance No. : </label>
		                            <div class="col-md-4">
		                            	    
								    <input type="text" class="form-control" name="ordinance" />

		                            </div>
									
		                        </div>
								
								
		                        <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Article No. : </label>
		                            <div class="col-md-4">
		                            	    
								    <input type="text" class="form-control" name="articleNo" />

		                            </div>
									
		                        </div>	
								

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="projectinput1">Details : </label>
		                            <div class="col-md-6">
		                            	    
								    <textarea class="form-control" name="articleDetails"></textarea>

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
			url: 'insert_article.php', // form action url
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


