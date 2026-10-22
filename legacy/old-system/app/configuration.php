<?php
include('header.php');

$getAllFinYearQ = mysqli_query($con,"select * from financial_year");

$getAllFinYearQ2 = mysqli_query($con,"select * from financial_year");



?>





<div class="main-panel">
        <div class="main-content" style="clear: left;">
          <div class="content-wrapper"><!--Invoice template starts-->
<div class="row">
    <div class="col-md-12">
        <h4>Settings</h4>
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

                        <input type="hidden" name="prev_login_logo" value="<?php echo $getSettingsDetailsQRW['login_logo']; ?>" />
						
						<input type="hidden" name="prev_header_logo" value="<?php echo $getSettingsDetailsQRW['header_logo']; ?>" />

						<input type="hidden" name="prev_sidebarbg" value="<?php echo $getSettingsDetailsQRW['sidebar_bg_img']; ?>" />

						<input type="hidden" name="prev_companyLogo" value="<?php echo $getSettingsDetailsQRW['companyLogo']; ?>" />
						 
	                    	<div class="form-body">
	                    		<h4 class="form-section"><i class="ft-droplet"></i> Theme Settings</h4>
			                    <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="software_title">Software Title: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="software_title" class="form-control"  name="software_title" value="<?php echo $getSettingsDetailsQRW['software_title']; ?>">
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="login_logo">Login Logo: </label>
		                            <div class="col-md-9">
		                            	<div class="input-group mb-3">
											<div class="input-group-prepend">
												<span class="input-group-text">Upload</span>
											</div>
											<div class="custom-file">
												<input type="file" class="custom-file-input" id="login_logo" name="login_logo">
												<label class="custom-file-label" for="login_logo">Choose file</label>
											</div>
										</div>
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="header_logo">Header Logo: </label>
		                            <div class="col-md-9">
		                            	<div class="input-group mb-3">
											<div class="input-group-prepend">
												<span class="input-group-text">Upload</span>
											</div>
											<div class="custom-file">
												<input type="file" class="custom-file-input" id="inputGroupFile01" name="header_logo">
												<label class="custom-file-label" for="header_logo">Choose file</label>
											</div>
										</div>
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="scolor">Sidebar Color: </label>
		                            <div class="col-md-9">
		                            	

										<div class="cz-bg-color">
										  <div class="row p-1">
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="pomegranate" class="gradient-pomegranate d-block rounded-circle"><input <?php if($getSettingsDetailsQRW['sidebar_color']=='pomegranate'){ echo "checked";} ?> type="radio" name="scolor" value="pomegranate" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="king-yna" class="gradient-king-yna d-block rounded-circle"><input <?php if($getSettingsDetailsQRW['sidebar_color']=='king-yna'){ echo "checked";} ?> type="radio" name="scolor" value="king-yna" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="ibiza-sunset" class="gradient-ibiza-sunset d-block rounded-circle"><input <?php if($getSettingsDetailsQRW['sidebar_color']=='ibiza-sunset'){ echo "checked";} ?> type="radio" name="scolor" value="ibiza-sunset" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="flickr" class="gradient-flickr d-block rounded-circle"><input <?php if($getSettingsDetailsQRW['sidebar_color']=='flickr'){ echo "checked";} ?> type="radio" name="scolor" value="flickr" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="purple-bliss" class="gradient-purple-bliss d-block rounded-circle"><input <?php if($getSettingsDetailsQRW['sidebar_color']=='purple-bliss'){ echo "checked";} ?> type="radio" name="scolor" value="purple-bliss" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="man-of-steel" class="gradient-man-of-steel d-block rounded-circle"><input <?php if($getSettingsDetailsQRW['sidebar_color']=='man-of-steel'){ echo "checked";} ?> type="radio" name="scolor" value="man-of-steel" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="purple-love" class="gradient-purple-love d-block rounded-circle"><input <?php if($getSettingsDetailsQRW['sidebar_color']=='purple-love'){ echo "checked";} ?> type="radio" name="scolor" value="purple-love" /></span></div>
										  </div>
										  <div class="row p-1">
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="black" class="bg-black d-block rounded-circle"><input type="radio" name="scolor" <?php if($getSettingsDetailsQRW['sidebar_color']=='black'){ echo "checked";} ?> value="black" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="white" class="bg-grey d-block rounded-circle"><input type="radio" name="scolor" <?php if($getSettingsDetailsQRW['sidebar_color']=='white'){ echo "checked";} ?> value="white" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="primary" class="bg-primary d-block rounded-circle"><input type="radio" name="scolor" <?php if($getSettingsDetailsQRW['sidebar_color']=='primary'){ echo "checked";} ?> value="primary" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="success" class="bg-success d-block rounded-circle"><input type="radio" name="scolor" <?php if($getSettingsDetailsQRW['sidebar_color']=='success'){ echo "checked";} ?> value="success" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="warning" class="bg-warning d-block rounded-circle"><input type="radio" name="scolor" <?php if($getSettingsDetailsQRW['sidebar_color']=='warning'){ echo "checked";} ?> value="warning" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="info" class="bg-info d-block rounded-circle"><input type="radio" name="scolor" <?php if($getSettingsDetailsQRW['sidebar_color']=='info'){ echo "checked";} ?> value="info" /></span></div>
											<div class="col"><span style="width:20px; height:20px;" data-bg-color="danger" class="bg-danger d-block rounded-circle"><input type="radio" name="scolor" <?php if($getSettingsDetailsQRW['sidebar_color']=='danger'){ echo "checked";} ?> value="danger" /></span></div>
										  </div>
										</div>


		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="sidebarbg">Sidebar Background Image </label>
		                            <div class="col-md-9">
		                            	<div class="input-group mb-3">
											<div class="input-group-prepend">
												<span class="input-group-text">Upload</span>
											</div>
											<div class="custom-file">
												<input type="file" class="custom-file-input" id="sidebarbg" name="sidebarbg">
												<label class="custom-file-label" for="sidebarbg">Choose file</label>
											</div>
										</div>
		                            </div>
		                        </div>
								

								<h4 class="form-section"><i class="ft-file-text"></i> Company Information</h4>

		                        <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="company_name">Company Name: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="company_name" class="form-control"  name="company_name" value="<?php echo $getSettingsDetailsQRW['company_name']; ?>">
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="address">Address: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="address" class="form-control"  name="address" value="<?php echo $getSettingsDetailsQRW['address']; ?>">
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="phone">Phone/Mobile: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="phone" class="form-control"  name="phone" value="<?php echo $getSettingsDetailsQRW['phone']; ?>">
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="email">Email: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="email" class="form-control"  name="email" value="<?php echo $getSettingsDetailsQRW['email']; ?>">
		                            </div>
		                        </div>
								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="companyLogo">Logo: </label>
		                            <div class="col-md-9">
		                            	<div class="input-group mb-3">
											<div class="input-group-prepend">
												<span class="input-group-text">Upload</span>
											</div>
											<div class="custom-file">
												<input type="file" class="custom-file-input" id="companyLogo" name="companyLogo">
												<label class="custom-file-label" for="companyLogo">Choose file</label>
											</div>
										</div>
		                            </div>
		                        </div>


								<h4 class="form-section"><i class="ft-file-text"></i> Table Settings</h4>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="default_page_size">Default Page Size: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="default_page_size" class="form-control"  name="default_page_size" value="<?php echo $getSettingsDetailsQRW['default_page_size']; ?>">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="form_heading_text_transform">Heading Text Transform: </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="form_heading_text_transform">
										<option value=""></option>
										<option <?php if($getSettingsDetailsQRW['form_heading_text_transform']=='uppercase'){ echo "selected='selected'";} ?>>uppercase</option>
										<option <?php if($getSettingsDetailsQRW['form_heading_text_transform']=='lowercase'){ echo "selected='selected'";} ?>>lowercase</option>
										<option <?php if($getSettingsDetailsQRW['form_heading_text_transform']=='capitalize'){ echo "selected='selected'";} ?>>capitalize</option>
										</select>
		                            </div>
		                        </div>

		                        <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="table_heading_font_size">Table Heading Font Size: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="table_heading_font_size" class="form-control"  name="table_heading_font_size" value="<?php echo $getSettingsDetailsQRW['table_heading_font_size']; ?>">
		                            </div>
		                        </div>

								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="table_data_font_size">Table Data Font Size: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="table_data_font_size" class="form-control"  name="table_data_font_size" value="<?php echo $getSettingsDetailsQRW['table_data_font_size']; ?>">
		                            </div>
		                        </div>



								<h4 class="form-section"><i class="ft-file-text"></i> Form Settings</h4>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="form_label_text_transform">Label Text Transform: </label>
		                            <div class="col-md-9">
		                            	<select class="form-control" name="form_label_text_transform">
										<option value=""></option>
										<option <?php if($getSettingsDetailsQRW['form_label_text_transform']=='uppercase'){ echo "selected='selected'";} ?>>uppercase</option>
										<option <?php if($getSettingsDetailsQRW['form_label_text_transform']=='lowercase'){ echo "selected='selected'";} ?>>lowercase</option>
										<option <?php if($getSettingsDetailsQRW['form_label_text_transform']=='capitalize'){ echo "selected='selected'";} ?>>capitalize</option>
										</select>
		                            </div>
		                        </div>

								
		                        <div class="form-group row">
	                            	<label class="col-md-3 label-control" for="form_label_font_size">Label Font Size: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="form_label_font_size" class="form-control"  name="form_label_font_size" value="<?php echo $getSettingsDetailsQRW['form_label_font_size']; ?>">
		                            </div>
		                        </div>


								<div class="form-group row">
	                            	<label class="col-md-3 label-control" for="form_input_font_size">Input Text Font Size: </label>
		                            <div class="col-md-9">
		                            	<input type="text" id="form_input_font_size" class="form-control"  name="form_input_font_size" value="<?php echo $getSettingsDetailsQRW['form_input_font_size']; ?>">
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


$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'save_configuration_data.php', // form action url
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