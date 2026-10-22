<?php
include('header.php');


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






$getIncrementSettings = mysqli_query($con,"select * from increment_settings where dataID=1");
$getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

$getEmployeeListQ = mysqli_query($con, "select * from employee_list where employment_status=1");

$getEmployeeListQ2 = mysqli_query($con, "select * from employee_list where employment_status=1");

$getEditEmployeeListQ = mysqli_query($con, "select * from employee_list where employment_status=1");

$getEmployeeListQ4 = mysqli_query($con, "select user_list.dataID, user_list.employee_id, user_list.user_type, employee_list.id, employee_list.employee_name, employee_list.employee_id from user_list inner join employee_list on user_list.employee_id=employee_list.id where user_list.user_type=2");


$getApprovalPersonsQ = mysqli_query($con, "select * from leave_approval_signatory order by approvalSL asc");


$getAllJobTitleQ2 = mysqli_query($con, "select * from job_title");

$getAllSectionQ2 = mysqli_query($con, "select * from sections");

$getEditApprovalPersonsQ = mysqli_query($con, "select * from leave_edit_approval_signatory order by approvalSL asc");

$getEditApprovalPersonsQ2 = mysqli_query($con, "select * from leave_edit_approval_signatory order by approvalSL asc");


?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Leave Settings</h4>
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

						
	                    <form class="form form-horizontal"  name="form" id="form" enctype="multipart/form-data">

                        					 
	                    	

								<h4 class="form-section"><i class="ft-file-text"></i>অনুমোদনকারী কর্মকর্তাগণ</h4>

								<table id="tbl" border="1" width="100%">
									<thead>
										<tr >
											<th class="text-center" height="50" width="20">
												ক্রমিক নং
											</th>
											
																						
											<th class="text-center" height="50" width="150">
												পদবী
											</th>

											<th class="text-center" height="50" width="150">
												অনুমতির ক্রমিক নং 
											</th>											
											
																				
											
										</tr>
									</thead>
									<tbody>

										<?php 
										  $m = 1;
										  while($dataRow = mysqli_fetch_array($getApprovalPersonsQ)){

											  $getEmployeeListQ3 = mysqli_query($con, "select * from employee_list where employment_status=1");

											  $getAllJobTitleQ = mysqli_query($con, "select * from job_title");


										?>
										<tr id='addr0'>

											<td height='40' style="text-align:center">
											 <?php echo $m; ?>				
											</td>

											
											<td height='40' style="text-align:center">
											 <select class="js-example-basic-single" style="width: 100%;" name="signatory[]">
												<option value=''></option>
												<?php while($empRow = mysqli_fetch_array($getAllJobTitleQ)){ 											
											
												
												?>

												<option <?php if($dataRow['designationID'] == $empRow['id']){ echo "selected"; } ?> value='<?php echo $empRow['id']; ?>'><?php echo $empRow['job_title_name']; ?></option>

												<?php } ?>
											 </select>				
											</td>

											<td height='40' style="text-align:center">
											 <input type="number" class="form-control" name="serial[]" value="<?php echo $dataRow['approvalSL']; ?>" />				
											</td>

										</tr>

										<?php $m++; } ?>

										<tr id='addr2'></tr>


									</tbody>
								</table>

										

								<div class="row" style="margin-top: 20px;">
								<div class="col-md-4">
								&nbsp;
								</div>
								<div class="col-md-6">
								&nbsp;</div>

								<div class="col-md-2" style="text-align: right;">
								<a id="add_row" class="btn btn-success  btn-fab"><i class="material-icons">add</i></a>&nbsp;&nbsp;&nbsp;&nbsp;<a id='delete_row' class="btn btn-danger  btn-fab"><i class="material-icons">clear</i></a>
								</div>
								</div>



								<!-- edit approval -->

								<h4 class="form-section"><i class="ft-file-text"></i>ছুটি সংশোধন অনুমোদনকারী কর্মকর্তাগণ</h4>

								<table id="tbll" border="1" width="100%">
									<thead>
										<tr >
											<th class="text-center" height="50" width="20">
												ক্রমিক নং
											</th>
											
																						
											<th class="text-center" height="50" width="150">
												পদবী
											</th>

											<th class="text-center" height="50" width="150">
												অনুমতির ক্রমিক নং 
											</th>											
											
																				
											
										</tr>
									</thead>
									<tbody>

										<?php 
										  $m = 1;
										  while($dataRow = mysqli_fetch_array($getEditApprovalPersonsQ)){

											  $getEmployeeListQ3 = mysqli_query($con, "select * from employee_list where employment_status=1");

											  $getAllJobTitleQ = mysqli_query($con, "select * from job_title");


										?>
										<tr id='addrr0'>

											<td height='40' style="text-align:center">
											 <?php echo $m; ?>				
											</td>

											
											<td height='40' style="text-align:center">
											 <select class="js-example-basic-single" style="width: 100%;" name="editsignatory[]">
												<option value=''></option>
												<?php while($empRow = mysqli_fetch_array($getEmployeeListQ3)){ 											
											
												
												?>

												<option <?php if($dataRow['employeeID'] == $empRow['id']){ echo "selected"; } ?> value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).'-'.$empRow['employee_name']; ?></option>

												<?php } ?>
											 </select>				
											</td>

											<td height='40' style="text-align:center">
											 <input type="number" class="form-control" name="editserial[]" value="<?php echo $dataRow['approvalSL']; ?>" />				
											</td>

										</tr>

										<?php $m++; } ?>

										<tr id='addrr2'></tr>


									</tbody>
								</table>

										

								<div class="row" style="margin-top: 20px;">
								<div class="col-md-4">
								&nbsp;
								</div>
								<div class="col-md-6">
								&nbsp;</div>

								<div class="col-md-2" style="text-align: right;">
								<a id="add_roww" class="btn btn-success  btn-fab"><i class="material-icons">add</i></a>&nbsp;&nbsp;&nbsp;&nbsp;<a id='delete_roww' class="btn btn-danger  btn-fab"><i class="material-icons">clear</i></a>
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




<script type="text/javascript">

//table.row( 100 ).scrollTo();

$('.js-example-basic-single').select2();



var i=$('table tr').length;
$("#add_row").on('click',function(){

	i++;
	
	html = '<tr>';

	html += "<td style='text-align:center;'>"+(i - 2)+"</td>";

	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single"+i+"' style='width:100%;' name='signatory[]' id='signatory_"+i+"' required><option value=''></option><?php while($cRow2=mysqli_fetch_array($getEmployeeListQ2)){?><option value='<?php echo $cRow2['id']; ?>'><?php echo Bengali_DTN($cRow2['employee_id']).' - '.$cRow2['employee_name']; ?></option><?php } ?></select></td>";
		
	html += "<td style='text-align:center'><input type='number' name='serial[]' id='serial_"+i+"' placeholder='' class='form-control' value='"+(i - 1)+"' required /></td>";
	
	html += '</tr>';
	$('table').append(html);
	

	$('.js-example-basic-single'+i).select2();

	
});


$("#delete_row").click(function(){
	var i=$('table tr').length;
    	 
		 $("#tbl tr:last").remove();

           


	 });


// edit signatory

var j=$('#tbll tr').length;
$("#add_roww").on('click',function(){

	//alert(j);

	j++;
	
	html = '<tr>';

	html += "<td style='text-align:center;'>"+(j - 2)+"</td>";

	html += "<td height='40' style='text-align:center'><select class='js-example-basic-single"+j+"' style='width:100%;' name='editsignatory[]' id='signatory_"+j+"' required><option value=''></option><?php while($cRow2=mysqli_fetch_array($getEditEmployeeListQ)){?><option value='<?php echo $cRow2['id']; ?>'><?php echo Bengali_DTN($cRow2['employee_id']).' - '.$cRow2['employee_name']; ?></option><?php } ?></select></td>";
		
	html += "<td style='text-align:center'><input type='number' name='editserial[]' id='serial_"+j+"' placeholder='' class='form-control' value='"+(j - 1)+"' required /></td>";
	
	html += '</tr>';
	$('#tbll').append(html);
	

	$('.js-example-basic-single'+j).select2();

	
});


$("#delete_roww").click(function(){
	var j=$('#tbll tr').length;
    	 
		 $("#tbll tr:last").remove();

           


	 });



$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'save_leave_configuration_data.php', // form action url
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