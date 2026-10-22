<?php
include('header.php');

	$employeeID = $_GET['employeeID'];

	$incrementYear = date('Y');

	$getSalaryIncrementDataQ = mysqli_query($con, "select * from yearly_salary_increment where incrementYear='$incrementYear' and employeeID='$employeeID'");
	$getSalaryIncrementDataQRW = mysqli_fetch_assoc($getSalaryIncrementDataQ);

	//$newProductID = "2000"+($getLastProductIDQRW['dataID'] + 1);
	$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$employeeID'");
	$getEmployeeDetailsQRW = mysqli_fetch_assoc($getEmployeeDetailsQ);

	// get designation info
	
	$getPayScaleDetailsQ = mysqli_query($con, "select * from grade where id='$getEmployeeDetailsQRW[pay_scale]'");
	$getPayScaleDetailsQRW = mysqli_fetch_assoc($getPayScaleDetailsQ);


	$checkForUpdateReqQ = mysqli_query($con, "SELECT * FROM `increment_data_update_permission` where incrementYear='$incrementYear' and employeeID='$employeeID'");
	$checkForUpdateReqQNumRows = mysqli_num_rows($checkForUpdateReqQ);

	if($checkForUpdateReqQNumRows > 0){ 
		$checkForUpdateReqQRW = mysqli_fetch_assoc($checkForUpdateReqQ);
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
         
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div class="card-block">
                
                
                
               


					

                 <form name="form" id="form" enctype="multipart/form-data">

				 <input type="hidden" name="employeeID" value="<?php echo $employeeID; ?>" />

				 <input type="hidden" name="incrementSalaryGrade" value="<?php echo $getSalaryIncrementDataQRW['incrementSalaryGrade']; ?>" />

				 <input type="hidden" name="currentBasic" id="currentBasic" value="<?php echo $getEmployeeDetailsQRW['basic_salary']; ?>" />

				 <input type="hidden" name="salary_increment_rate_current" id="salary_increment_rate_current" value="<?php echo $getSalaryIncrementDataQRW['incrementAmount']; ?>" />

				 <input type="hidden" name="salary_increment_basic_current" id="salary_increment_basic_current" value="<?php echo $getSalaryIncrementDataQRW['incrementSalary']; ?>" />

				 <input type="hidden" name="prevFile" value="<?php if($checkForUpdateReqQNumRows > 0){ echo $checkForUpdateReqQRW['office_notice']; } ?>" />


				 <div class="form-group row">
					<label class="col-sm-2 control-label">নাম&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<?php echo $getEmployeeDetailsQRW['employee_name']; ?>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-2 control-label">বেতন স্কেল&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<?php echo $getPayScaleDetailsQRW['grade_title']; ?> (<?php echo $obj->engToBn(number_format($getPayScaleDetailsQRW['minimum_salary'])); ?> - <?php echo $obj->engToBn(number_format($getPayScaleDetailsQRW['maximum_salary'])); ?>)
					</div>
				</div>

				<div class="form-group row">
					<label class="col-sm-2 control-label">বর্তমান মূল বেতন&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<?php echo $obj->engToBn(number_format($getEmployeeDetailsQRW['basic_salary'])); ?>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-2 control-label">বেতন বৃদ্ধির হার&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="incrementAmount" id="incrementAmount" value="<?php echo $getSalaryIncrementDataQRW['incrementAmount']; ?>" />
						<?php if($checkForUpdateReqQNumRows > 0){ if($checkForUpdateReqQRW['isApproved'] == 0){ ?>
						<span style='font-size: 10px;color: red;'>পরিবর্তনের জন্য অনুরোধকৃত টাকা : <?php echo $checkForUpdateReqQRW['salary_increment_rate_edited']; ?></span>
						<?php } } ?>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-2 control-label">বেতন বৃদ্ধির পর মূল বেতন &nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="text" class="form-control" name="incrementSalary" id="incrementSalary" value="<?php echo $getSalaryIncrementDataQRW['incrementSalary']; ?>" />
						<?php if($checkForUpdateReqQNumRows > 0){ if($checkForUpdateReqQRW['isApproved'] == 0){ ?>
						<span style='font-size: 10px;color: red;'>পরিবর্তনের জন্য অনুরোধকৃত টাকা : <?php echo $checkForUpdateReqQRW['salary_increment_basic_edited']; ?></span>
						<?php } } ?>
					</div>
				</div>



				<div class="form-group row">
					<label class="col-sm-2 control-label">নোট&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<textarea class='form-control' name='note'><?php if($checkForUpdateReqQNumRows > 0){ echo $checkForUpdateReqQRW['note']; } ?></textarea>
					</div>
				</div>


				<div class="form-group row">
					<label class="col-sm-2 control-label">এটাচমেন্ট(অফিস আদেশ)&nbsp;<span style="color:#F00">*</span></label>
					<div class="col-sm-5">
						<input type="file" name="office_notice" class="form-control" required />
						<?php if($checkForUpdateReqQNumRows > 0){ ?> <span>Download previous uploaded file, <a href="uploads/<?php echo $checkForUpdateReqQRW['office_notice']; ?>" target="_blank">Download</a></span> <?php } ?>
					</div>
				</div>
                
                                         
                

                
                <div class="form-actions">
	                            <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-raised btn-warning mr-1">
	                            	<i class="ft-x"></i> Cancel
	                            </button>
	                            <button type="submit" name="submit" id="submit" class="btn btn-raised btn-primary">
	                                <i class="fa fa-check-square-o"></i> <?php if($checkForUpdateReqQNumRows > 0){ $checkForUpdateReqQRW = mysqli_fetch_assoc($checkForUpdateReqQ); if($checkForUpdateReqQRW['isApproved'] == 0){ ?>Update Request<?php } }else{ echo "Send Request"; } ?>
	                            </button>
	                        </div>


</form>














                            
                
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
			url: 'update_increment_data.php', // form action url
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

				console.log(data);

		
				
				if(data==1)
{
swal(
  'Data Successfully Saved...',
  '',
  'success'
)

//$("#my-form").reset();

//window.location='central_row_material?submslug=manage-rowmaterials&slug=central-depo';

}
else
{
	swal(
  'Oops...',
  'Something went wrong!',
  'error'
)

}
				
				
				
			},
			error: function(e) {
				console.log(e)
			}
		});
	});
});



$('#incrementAmount').on("input", function() {
  var incrementAmount = parseFloat(this.value);

  var currentBasic = parseFloat($('#currentBasic').val());

  var incrementSalary = parseFloat(currentBasic + incrementAmount);

  $('#incrementSalary').val(incrementSalary);
  



});


$('#incrementSalary').on("input", function() {

  var incrementSalary = parseFloat(this.value);

  var currentBasic = parseFloat($('#currentBasic').val());

  var incrementAmount = parseFloat(incrementSalary - currentBasic);

  $('#incrementAmount').val(incrementAmount);
  



});


</script>