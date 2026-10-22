<?php
include('header.php');

$incrementYear = date('Y');

$getAllDataQ = mysqli_query($con,"select * from `increment_data_update_permission` where incrementYear='$incrementYear' and isApproved=0");


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
                
                
                
                
					



							                               

                                <table class="table table-striped table-bordered scroll-vertical">
                                    <thead>
                                    <tr>
                                        <th width="10">SL</th>

										<th width="60">&nbsp;</th>

										<th  width="300">নাম ও পদবি</th>

										<th width="40">আইডি</th>

										<th width="200">শাখা ও কেন্দ্র</th>

										<th width="120">বর্তমান মূল বেতন</th>

										<th width="120">বেতন বৃদ্ধির হার</th>

										<th width="120">অনুরোধকৃত</th>
																		
										<th>বেতন বৃদ্ধির পর মূল বেতন</th>

										<th width="120">অনুরোধকৃত</th>

										<th width="60">অনুরোধকারী</th>

										<th width="60">মন্তব্য</th>

										<th width="140">&nbsp;</th>

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

											
											$getRequestorDetailsQ = mysqli_query($con, "select * from user_list where dataID='$empRow[submitBy]'");
											$getRequestorDetailsQRW = mysqli_fetch_assoc($getRequestorDetailsQ);


											// get increment data details

											$getIncrementDataQ = mysqli_query($con, "SELECT * FROM `yearly_salary_increment` where incrementYear='$incrementYear' and employeeID='$empRow[employeeID]'");
											$getIncrementDataQRW = mysqli_fetch_assoc($getIncrementDataQ);


											$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getIncrementDataQRW[section_id]'");
											$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

											$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getIncrementDataQRW[organization_id]'");
											$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);
											
																																	
										?>
                                        <tr id="tr_<?php echo $sl; ?>">
				                            <td valign="baseline"><?php echo $sl; ?></td>		

											<td><img src="uploads/<?php if($getEmployeeDetailsQW['photo'] != ''){ echo $getEmployeeDetailsQW['photo']; }else{ echo 'default-avatar.png'; } ?>" style="border-radius: 50%;" height="60" /></td>

											<td><?php echo $getEmployeeDetailsQW['employee_name']."<br>".$getDesignationDetailsQRW['job_title_name']; ?></td>

											<td><?php echo $getEmployeeDetailsQW['employee_id']; ?></td>

											<td><?php echo $getSectionDetailsQRW['section_name']; ?><br><?php echo $getorgDetailsQRW['organization_name']; ?></td>
											
											<td><?php echo $getEmployeeDetailsQW['basic_salary']; ?></td>

											<td><?php echo $empRow['salary_increment_rate_current']; ?></td>

											<td><span style="color: red;"><?php echo $empRow['salary_increment_rate_edited']; ?></span></td>
											
											<td><?php echo $empRow['salary_increment_basic_current']; ?></td>

											<td><span style="color: red;"><?php echo $empRow['salary_increment_basic_edited']; ?></span></td>

											<td><?php echo $getRequestorDetailsQRW['full_name']; ?></td>

											<td><?php echo $empRow['note']; ?></td>
											
											<td>
											<a href="uploads/<?php echo $empRow['office_notice']; ?>" target="_blank"><img src="app-assets/cloud.png" height="32" /></a>&nbsp;&nbsp;&nbsp;	
											<a href="approve_increment_changes.php?dataID=<?php echo $empRow['dataID']; ?>&isApproved=2"><img src="app-assets/cross.png" height="32" /></a>&nbsp;&nbsp;&nbsp;<a href="approve_increment_changes.php?dataID=<?php echo $empRow['dataID']; ?>&isApproved=1"><img height="32" src="app-assets/check-mark.png" /></a>

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