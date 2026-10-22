<?php
include('header.php');

$incrementYear = 2024;

if(isset($_POST['submit'])){

	$soption = $_POST['soption'];

	if($soption == 1){

		$sqlq = "select * from `employee_list` where employment_status=1 and (organization_id=4 or organization_id=5) order by display_order asc";

		$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list` where employment_status=1 and (organization_id=4 or organization_id=5) order by display_order asc");

		$title = "চাকরিরত কর্মচারীগণ";
	
	}else if($soption == 2){

		$sqlq = "select * from `employee_list` where employment_status=2  order by display_order asc";
	
		$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list` where employment_status=2  order by display_order asc");

		$title = "পি আর এল";
	
	}else if($soption == 3){

		$sqlq = "select * from `employee_list` where (organization_id=6 or organization_id=7 or organization_id=8 or organization_id=9) order by display_order asc";

		$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list` where (organization_id=6 or organization_id=7 or organization_id=8 or organization_id=9) order by display_order asc");

		$title = "আঞ্চলিক কেন্দ্র";

	}

}else{

	$sqlq = "select * from `employee_list` where employment_status=1 and (organization_id=4 or organization_id=5) order by display_order asc";

$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list` where employment_status=1 and (organization_id=4 or organization_id=5) order by display_order asc");

$title = "চাকরিরত কর্মচারীগণ";

}



?>



          <div class="main-panel">
        <div class="main-content">
          <div class="content-wrapper">
		  <!--Invoice template starts-->
    <div class="row">
    <div class="col-md-12">
	&nbsp;
	</div>
	</div>

    <div class="row">
    <div class="col-md-10">
        <h4>বেতন বৃদ্ধি পরিচালনা</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='admission_form?menuslug=admission-form'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> নতুন সংযোজন </button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">

				<form action="" method="post">
					<div class="form-group">
					  <select class="form-control" name="soption" required>
						<option <?php if(isset($_POST['soption']) && $_POST['soption'] == 1){ echo "selected"; } ?> value="1">চাকরিরত কর্মচারীগণ</option>
						<option <?php if(isset($_POST['soption']) && $_POST['soption'] == 2){ echo "selected"; } ?> value="2">পি আর এল
</option>
						<option <?php if(isset($_POST['soption']) && $_POST['soption'] == 3){ echo "selected"; } ?> value="3">আঞ্চলিক কেন্দ্র</option>
					  </select>
					</div>
					
					<button type="submit" name="submit" class="btn btn-primary">Get Result</button>
				  </form>
				  
				  <p>&nbsp;</p>

				  <h4 style="text-align: center;color: #F24C3D;"><?php echo $title; ?></h4>
                
                
						<div class="tab-content px-1 pt-1">

						  <div role="tabpanel" class="tab-pane active show" id="active" aria-labelledby="active-tab" aria-expanded="true">
							<!-- 1st tab -->

							<form name="form" id="form" enctype="multipart/form-data">
                
                
					

								<p style="text-align: right;"><input type="submit" name="submit" id="submit" class="btn btn-raised btn-primary" value="অনুমোদনের জন্য পাঠান" /></p>

							                               

                                <table class="table table-striped table-bordered scroll-vertical">
                                    <thead>
                                    <tr>
                                        <th width="4%"><input type="checkbox" id="select_all" /></th>

										<th width="10%">ফটো</th>

										<th width="20%">নাম ও পদবি </th>

										<th width="20%">আইডি</th>

										<th width="20%">শাখা ও কেন্দ্র</th>

										<th width="20%">বর্তমান বেতন স্কেল</th>

										<th width="20%">বর্তমান মূল বেতন</th>

										<th width="20%">পরিবর্তিত বেতন স্কেল</th>

										<th width="20%">বেতন বৃদ্ধির হার</th>

										<th width="20%">বেতন বৃদ্ধির পর মূল বেতন</th>
																		
										<th width="200">Action</th>
                                    </tr>
                                    </thead>


                                    <tbody>

                                    <?php
										$sl = 0;
										while($empRow=mysqli_fetch_array($getAllEmployeeListQ))
										{
											$sl = $sl + 1;

											$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$empRow[designation]'");
											$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

											$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$empRow[section_id]'");
											$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

											$checkForUpdateReqQ = mysqli_query($con, "SELECT * FROM `increment_data_update_permission` where incrementYear='$incrementYear' and employeeID='$empRow[id]'");
											$checkForUpdateReqQNumRows = mysqli_num_rows($checkForUpdateReqQ);

											$checkforpermissionQ = mysqli_query($con, "select * from increment_data_for_approval where incrementYear='$incrementYear' and employeeID='$empRow[id]'");
											$checkforpermissionQNumRows = mysqli_num_rows($checkforpermissionQ);

											$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$empRow[organization_id]'");
											$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

											$getIncrementDetailsQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='$incrementYear' and employeeID='$empRow[id]'");
											$getIncrementDetailsQRW = mysqli_fetch_assoc($getIncrementDetailsQ);

											$getPresentSGradeQ = mysqli_query($con, "select * from grade where id='$getIncrementDetailsQRW[presentSalaryGrade]'");
											$getPresentSGradeQRW = mysqli_fetch_assoc($getPresentSGradeQ);

											$getPresentSGradeQ2 = mysqli_query($con, "select * from grade where id='$getIncrementDetailsQRW[presentSalaryGrade]'");
											$getPresentSGradeQRW2 = mysqli_fetch_assoc($getPresentSGradeQ2);


											
																																	
										?>
                                        <tr id="tr_<?php echo $sl; ?>" <?php if($checkForUpdateReqQNumRows > 0 && $checkforpermissionQNumRows <= 0){?> style="background-color: #fc4e4e;color: #fff;" <?php }else if($checkforpermissionQNumRows > 0){ ?> style="background-color: #32cd32;color: #fff;" <?php } ?> >
				                            
											<td valign="baseline"><input type="checkbox" class="checkbox" name="listedemp[]" value="<?php echo $empRow['id']; ?>" /></td>		

											<td><img src="uploads/<?php if($empRow['photo'] != ''){ echo $empRow['photo']; }else{ echo 'default-avatar.png'; } ?>" style="border-radius: 50%;" height="60" /></td>

											<td><a <?php if($checkForUpdateReqQNumRows > 0){?> style="color: #fff;" <?php } ?> href="student_profile_view?studentID=<?php echo base64_encode($empRow['id']); ?>"><?php echo $empRow['employee_name']; ?></a><br><?php echo $getDesignationDetailsQRW['job_title_name']; ?></td>
											
											<td><?php echo $empRow['employee_id']; ?></td>											
											
											<td><?php echo $getSectionDetailsQRW['section_name']; ?><br><?php echo $getorgDetailsQRW['organization_name']; ?></td>


											<td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn($getPresentSGradeQRW['minimum_salary']).'-'.$obj->engToBn($getPresentSGradeQRW['maximum_salary']); ?></td>


											<td><?php echo $obj->engToBn($getIncrementDetailsQRW['presentSalary']); ?></td>

											<td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn($getPresentSGradeQRW2['minimum_salary']).'-'.$obj->engToBn($getPresentSGradeQRW2['maximum_salary']); ?></td>

											<td><?php echo $obj->engToBn($getIncrementDetailsQRW['incrementAmount']); ?></td>

											<td><?php echo $obj->engToBn($getIncrementDetailsQRW['incrementSalary']); ?></td>
											

                                            <td>
											
											<a href="increment_office_notice_copyto_form?menuslug=manage-salary-increment&employeeID=<?php echo $empRow['id']; ?>"><img width="32" src="app-assets/files.png" />&nbsp;&nbsp;&nbsp;

											<a href="confirm_salary_increment_form?menuslug=manage-salary-increment&employeeID=<?php echo $empRow['id']; ?>"><img width="32" src="app-assets/edit.png" /></a>
											
											
											
											</td>
                                           
				                        </tr>
				                        
                                        <?php
										}
										?>
                                    
                                    </tbody>
                                </table>

                           
					</form>


							<!-- end of 1st tab -->
						  </div>
						  
						  
						</div>


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


		$(document).ready(function(){
			$('#select_all').on('click',function(){
				if(this.checked){
					$('.checkbox').each(function(){
						this.checked = true;
					});
				}else{
					 $('.checkbox').each(function(){
						this.checked = false;
					});
				}
			});

			$('.checkbox').on('click',function(){
				if($('.checkbox:checked').length == $('.checkbox').length){
					$('#select_all').prop('checked',true);
				}else{
					$('#select_all').prop('checked',false);
				}
			});


			$('#select_all2').on('click',function(){
				if(this.checked){
					$('.checkbox2').each(function(){
						this.checked = true;
					});
				}else{
					 $('.checkbox2').each(function(){
						this.checked = false;
					});
				}
			});

			$('.checkbox2').on('click',function(){
				if($('.checkbox2:checked').length == $('.checkbox2').length){
					$('#select_all2').prop('checked',true);
				}else{
					$('#select_all2').prop('checked',false);
				}
			});


			$('#select_all3').on('click',function(){
				if(this.checked){
					$('.checkbox3').each(function(){
						this.checked = true;
					});
				}else{
					 $('.checkbox3').each(function(){
						this.checked = false;
					});
				}
			});

			
			$('.checkbox3').on('click',function(){
				if($('.checkbox3:checked').length == $('.checkbox3').length){
					$('#select_all3').prop('checked',true);
				}else{
					$('#select_all3').prop('checked',false);
				}
			});
		});




		
		$(document).ready(function() {
			// form 1
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_increment_data_for_approval.php', // form action url
			type: 'POST', // form submit method get/post
			data: new FormData( this ),
      processData: false,
      contentType: false,
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
				
				
				
			},
			success: function(data) {

				//alert(data);
				
				
				swal(
  'Data Successfully Saved...',
  '',
  'success'
)
				
				
				
			},
			error: function(e) {
				console.log(e)
			}
		});
	});

// end of form 1

// form 2
	var form2 = $('#form2'); // contact form
	var submit2 = $('#submit2');	// submit button

	// form submit event
	form2.on('submit2', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_increment_data_for_approval.php', // form action url
			type: 'POST', // form submit method get/post
			data: new FormData( this ),
      processData: false,
      contentType: false,
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
				
				
				
			},
			success: function(data) {

				//alert(data);
				
				
				swal(
  'Data Successfully Saved...',
  '',
  'success'
)
				
				
				
			},
			error: function(e) {
				console.log(e)
			}
		});
	});

// end of form 2


// form 3

	var form3 = $('#form3'); // contact form
	var submit3 = $('#submit3');	// submit button

	// form submit event
	form3.on('submit3', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'insert_increment_data_for_approval.php', // form action url
			type: 'POST', // form submit method get/post
			data: new FormData( this ),
      processData: false,
      contentType: false,
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
				
				
				
			},
			success: function(data) {

				//alert(data);
				
				
				swal(
  'Data Successfully Saved...',
  '',
  'success'
)
				
				
				
			},
			error: function(e) {
				console.log(e)
			}
		});
	});

// end of form 3





});
		
		</script>