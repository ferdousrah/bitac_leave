<?php
include('header.php');

$getAllCoursesQ = mysqli_query($con, "select * from `courses`");

$getAllInstructorsQ = mysqli_query($con, "select * from `instructors`");

?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Create New Batch</h4>
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
	                    		<h4 class="form-section"><i class="ft-user"></i> Batch Information</h4>
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="courseID">Select Course: </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="courseID" required>
												<option value=''></option>
												<?php
												  while($cRow = mysqli_fetch_array($getAllCoursesQ)) {
												?>

												<option value='<?php echo $cRow['dataID']; ?>'><?php echo $cRow['courseCode'].' - '.$cRow['courseTitle']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="instructorID">Instructor: </label>
		                            <div class="col-md-9">
										
		                            		<select class="form-control" name="instructorID" required>
												<option value=''></option>
												<?php
												  while($insRow = mysqli_fetch_array($getAllInstructorsQ)) {
												?>

												<option value='<?php echo $insRow['dataID']; ?>'><?php echo $insRow['full_name']; ?></option>


												<?php } ?>
											</select>
										
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="batch_title">Batch Title: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="batch_title" class="form-control"  name="batch_title">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="total_seat">Total Seat: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="total_seat" class="form-control"  name="total_seat">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="venue_type">Venue Type </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="venue_type"><option value=''></option><option value='1'>Online Platform</option><option value='2'>Class Room</option></select>
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="venue_details">Venue Details </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="venue_details" class="form-control"  name="venue_details">
		                            </div>
		                        </div>

								


								<h4 class="form-section"><i class="ft-clock"></i> Class Schedule</h4>
								
									<table class="table table-bordered table-hover" id="tab_logic">
										<thead>
											<tr >
												<th class="text-center">
													#
												</th>
												<th class="text-center">
													Day
												</th>
												<th class="text-center">
													Start Time
												</th>
												<th class="text-center">
													End Time
												</th>
											</tr>
										</thead>
										<tbody>
											<tr id='addr0'>
												<td>
												1
												</td>
												<td>
												<select class="form-control" name="weekday[]"><option value=""></option>
												<option value="Sunday">Sunday</option><option value="Monday">Monday</option>
												<option value="Tuesday">Tuesday</option><option value="Wednesday">Wednesday</option>
												<option value="Thursday">Thursday</option><option value="Friday">Friday</option>
												<option value="Saturday">Saturday</option></select>
												</td>
												<td>
												<input type="text" name='start_time[]' placeholder='' class="form-control"/>
												</td>
												<td>
												<input type="text" name='end_time[]' placeholder='' class="form-control"/>
												</td>
											</tr>
											<tr id='addr1'></tr>
										</tbody>
									</table>
								

							

							<div class="form-group row">
	                            	<label class="col-md-8 label-control" for="venue_details">&nbsp;</label>
		                            <div class="col-md-4" style="text-align: right;">
		                            	<a id="add_row" class="btn btn-default"><i class="ft-plus"></i> Add Row</a><a id='delete_row' class="pull-right btn btn-default"><i class="ft-minus"></i> Delete Row</a>
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
			url: 'insert_batch_data.php', // form action url
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

					window.location='add_new_batch_form?menuslug=manage-batch';
				
				
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



 $(document).ready(function(){
      var i=1;
     $("#add_row").click(function(){
      $('#addr'+i).html("<td>"+ (i+1) +"</td><td><select class='form-control' name='weekday[]'><option value=''></option><option value='Sunday'>Sunday</option><option value='Monday'>Monday</option><option value='Tuesday'>Tuesday</option><option value='Wednesday'>Wednesday</option><option value='Thursday'>Thursday</option><option value='Friday'>Friday</option><option value='Saturday'>Saturday</option></select> </td><td><input  name='start_time[]' type='text' placeholder=''  class='form-control input-md'></td><td><input  name='end_time[]' type='text' placeholder=''  class='form-control input-md'></td>");

      $('#tab_logic').append('<tr id="addr'+(i+1)+'"></tr>');
      i++; 
  });
     $("#delete_row").click(function(){
    	 if(i>1){
		 $("#addr"+(i-1)).html('');
		 i--;
		 }
	 });

});

</script>