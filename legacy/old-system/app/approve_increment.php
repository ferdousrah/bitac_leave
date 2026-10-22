<?php
include('header.php');

$incrementYear = date('Y');

$loggedUserID = $_SESSION['userID'];

$getAllDataQ = mysqli_query($con,"select * from `increment_data_for_approval` where incrementYear='$incrementYear' and signatory='$loggedUserID' and isApproved=0");


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
        <h4>বেতন বৃদ্ধি অনুমোদন</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='admission_form?menuslug=admission-form'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> নতুন সংযোজন </button> -->
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                
					
							<form name="form" id="form" enctype="multipart/form-data">
                
                
					

								<p style="text-align: right;"><input type="submit" name="submit" id="submit" class="btn btn-raised btn-primary" value="একাদিক অনুমোদন" /></p>


							                               

                                <table class="table table-striped table-bordered scroll-vertical">
                                    <thead>
                                    <tr>
                                        <th width="4%"><input type="checkbox" id="select_all" /></th>

										<th width="60">&nbsp;</th>

										<th  width="200">নাম ও পদবি</th>

										<th width="40">আইডি</th>

										<th width="200">শাখা ও কেন্দ্র</th>

										<th>বর্তমান বেতন স্কেল</th>

										<th width="120">বর্তমান মূল বেতন</th>

										<th width="120">বেতন বৃদ্ধির হার</th>
																		
										<th>বেতন বৃদ্ধির পর মূল বেতন</th>

										<th width="180">&nbsp;</th>

                                    </tr>
                                    </thead>


                                    <tbody>

                                    <?php
										$sl = 0;
										while($empRow=mysqli_fetch_array($getAllDataQ))
										{
											$sl = $sl + 1;

											$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$empRow[employeeID]'");
											$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

											$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
											$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

											$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
											$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

											$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQW[organization_id]'");
											$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

											$getIncrementDetailsQ = mysqli_query($con, "select * from yearly_salary_increment where dataID='$empRow[dataRef]'");
											$getIncrementDetailsQRW = mysqli_fetch_assoc($getIncrementDetailsQ);

											$getPresentSGradeQ = mysqli_query($con, "select * from grade where id='$getIncrementDetailsQRW[presentSalaryGrade]'");
											$getPresentSGradeQRW = mysqli_fetch_assoc($getPresentSGradeQ);


											if($empRow['prevSignatory'] == 0){

												$proceed = 1;
											
											}else{
												


												$checkPrevSognatorySignedQ = mysqli_query($con, "select * from increment_data_for_approval where dataRef='$empRow[dataRef]' and signatory='$empRow[prevSignatory]' and isApproved=1");
												$checkPrevSognatorySignedQNumRows = mysqli_num_rows($checkPrevSognatorySignedQ);

												if($checkPrevSognatorySignedQNumRows > 0){


													$proceed = 1;												
												
												}else{
												
													$proceed = 0;
												
												}
											
											}


											if($proceed == 1){
											
																																	
										?>
                                        <tr id="tr_<?php echo $sl; ?>">
				                            <td valign="baseline"><input type="checkbox" class="checkbox" name="listedemp[]" value="<?php echo $empRow['dataID']; ?>" /></td>		

											<td><img src="uploads/<?php if($getEmployeeDetailsQW['photo'] != ''){ echo $getEmployeeDetailsQW['photo']; }else{ echo 'default-avatar.png'; } ?>" style="border-radius: 50%;" height="60" /></td>

											<td><?php echo $getEmployeeDetailsQW['employee_name']."<br>".$getDesignationDetailsQRW['job_title_name']; ?></td>

											<td><?php echo $getEmployeeDetailsQW['employee_id']; ?></td>

											<td><?php echo $getSectionDetailsQRW['section_name']; ?><br><?php echo $getorgDetailsQRW['organization_name']; ?></td>

											<td style="text-align: center;border-right: 1px solid #000;"><?php echo $obj->engToBn($getPresentSGradeQRW['minimum_salary']).'-'.$obj->engToBn($getPresentSGradeQRW['maximum_salary']); ?></td>
											
											<td><?php echo $getEmployeeDetailsQW['basic_salary']; ?></td>

											<td><?php echo $getIncrementDetailsQRW['incrementAmount']; ?></td>

											
											<td><?php echo $getIncrementDetailsQRW['incrementSalary']; ?></td>


											
											<td>
											
											<a target="_blank" href="increment_form_getmethod_result.php?employeeID=<?php echo $getEmployeeDetailsQW['id']; ?>" style="border: 0px;padding: 0;margin:0;"><img src="app-assets/form.png" height="32" /></a>	
											
											<a href="approve_increment_data.php?dataID=<?php echo $empRow['dataID']; ?>&dataRef=<?php echo $getIncrementDetailsQRW['dataID']; ?>&isApproved=2"><img src="app-assets/cross.png" height="32" /></a>&nbsp;&nbsp;&nbsp;<a href="approve_increment_data.php?dataID=<?php echo $empRow['dataID']; ?>&dataRef=<?php echo $getIncrementDetailsQRW['dataID']; ?>&isApproved=1"><img height="32" src="app-assets/check-mark.png" /></a>

											</td>
                                            
                                           
				                        </tr>
				                        
                                        <?php
										
											$proceed = 0;

											}
										}
										?>
                                    
                                    </tbody>
                                </table>


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
		});




		
		$(document).ready(function() {
	var form = $('#form'); // contact form
	var submit = $('#submit');	// submit button

	// form submit event
	form.on('submit', function(e) {
		e.preventDefault(); // prevent default form submit
		// sending ajax request through jQuery
		$.ajax({
			url: 'approve_multiple_increment_data.php', // form action url
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
});






		
		function removeData(sl,dataID)
{
	

	swal({
  title: "Are you sure to delete this data?",
  text: "",
  icon: "warning",
  buttons: true,
  dangerMode: true,
})
.then((willDelete) => {
  if (willDelete) {
	  
	$.ajax({
            type : 'post',
            url : 'delete_data.php', //Here you will fetch records 
            data :  'dataID='+ dataID +'&tableName=students', //Pass $id
            success : function(data){
            $("#tr_"+sl).hide(1000);
            }
        });	  
	  
    swal("Deleted!", {
      icon: "success",
    });
	//window.location= 'index.html';
  } else {
    swal("Your data is safe!");
  }
});
	
	

}



function target_popup(form) {
    window.open('', 'formpopup', 'width=1000,height=1000,resizeable,scrollbars');
    form.target = 'formpopup';
}
		
		</script>