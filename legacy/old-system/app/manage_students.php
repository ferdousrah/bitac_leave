<?php
include('header.php');

$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list`");


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
        <h4>কর্মকর্তা/কর্মচারীদের তালিকা</h4>
    </div>
	<div class="col-md-2">
        <button onClick="window.location='admission_form?menuslug=admission-form'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> নতুন সংযোজন </button> 
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                
					



							                               

                                <table class="table table-striped table-bordered scroll-vertical">
                                    <thead>
                                    <tr>
                                        <th width="4%">SL</th>

										<th width="10%">Photo</th>

										<th width="20%">Full Name</th>

										<th width="20%">ID</th>

										<th width="20%">Designation</th>

										<th width="20%">Section</th>
																		
										<th width="18%">Action</th>
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
											
																																	
										?>
                                        <tr id="tr_<?php echo $sl; ?>">
				                            <td valign="baseline"><?php echo $sl; ?></td>		

											<td><img src="uploads/<?php if($empRow['photo'] != ''){ echo $empRow['photo']; }else{ echo 'default-avatar.png'; } ?>" style="border-radius: 50%;" height="60" /></td>

											<td><a href="student_profile_view?studentID=<?php echo base64_encode($empRow['id']); ?>"><?php echo $empRow['employee_name']; ?></a></td>
											
											<td><?php echo $empRow['employee_id']; ?></td>

											<td><?php echo $getDesignationDetailsQRW['job_title_name']; ?></td>
											
											<td><?php echo $getSectionDetailsQRW['section_name']; ?></td>
											

                                            <td>


											<div class="form-group">
											<!-- Simple Icon Button -->
											<button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location='edit_student_info_form?dataID=<?php echo base64_encode($empRow['id']); ?>&menuslug=<?php echo $_GET['menuslug']; ?>'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-edit"></i></button>
											<button data-toggle="tooltip" data-placement="top" data-original-title="Delete" onClick="removeData(<?php echo $sl; ?>,<?php echo $empRow['id']; ?>)" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-trash-o"></i></button>											
											
											</div>
											
											
											
											</td>
                                           
				                        </tr>
				                        
                                        <?php
										}
										?>
                                    
                                    </tbody>
                                </table>

                           



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
		
		</script>