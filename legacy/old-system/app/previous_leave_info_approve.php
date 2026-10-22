<?php
include('header.php');

$getModuleListQ = mysqli_query($con,"select * from previous_leave_deduction where isApproved=0");


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
        <h4>পূর্ববর্তী ছুটির তথ্য অনুমোদন</h4>
    </div>
	<div class="col-md-2">
        
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">
                
                
                
                <!-- Invoice Footer -->
                <div id="invoice-footer">
                    <div class="row">

                        

                    
					<table class="table table-striped table-bordered zero-configuration">
							<thead>
								<tr>

									<th>ক্রমিক</th>
									
									<th>আবেদনকারীর নাম ও পদবী</th>

									<th>গড়-বেতনে ছুটি</th>

									<th>অর্ধ-গড় বেতনে</th>

									<th>নৈমিত্তিক</th>

									<th>অসাধারণ(বিনা বেতনে ছুটি)</th>								
									
									<th width="100">Action</th>

								</tr>
							</thead>
							<tbody>
							<?php
							$sl = 0;
								while($dataRow=mysqli_fetch_array($getModuleListQ))
								{
									$sl = $sl + 1;

									$getEmpDetailsQ = mysqli_query($con, "select * from employee_list where id='$dataRow[employeeID]'");
									$getEmpDetailsQRW = mysqli_fetch_assoc($getEmpDetailsQ);

									$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmpDetailsQRW[designation]'");
									$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

									$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmpDetailsQRW[section_id]'");
									$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);



							?>
								<tr id="tr_<?php echo $sl; ?>">
									<td><?php echo $sl; ?></td>

									<td><?php echo $getEmpDetailsQRW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name']; ?>, <?php echo $getSectionDetailsQRW['section_name']; ?></td>

									<td><?php echo $dataRow['avgSalary']; ?>

									<?php if($dataRow['avgSalaryFile'] != ''){ ?>
									
									&nbsp;<a href="uploads/<?php echo $dataRow['avgSalaryFile']; ?>" target="_blank"><i class="icon-paper-clip"></i></a>

									<?php } ?>

									</td>

									<td>									
									

									<?php echo $dataRow['halfAvgSalary']; ?>
									
									<?php if($dataRow['halfAvgSalaryFile'] != ''){ ?>
									
									&nbsp;<a href="uploads/<?php echo $dataRow['halfAvgSalaryFile']; ?>" target="_blank"><i class="icon-paper-clip"></i></a>
									
									<?php } ?>
									
									</td>

									<td>						
									
									
									<?php echo $dataRow['casual']; ?>
									
									<?php if($dataRow['casualFile'] != ''){ ?>
									
									&nbsp;<a href="uploads/<?php echo $dataRow['casualFile']; ?>" target="_blank"><i class="icon-paper-clip"></i></a>
									
									<?php } ?>
									
									</td>

									<td><?php echo $dataRow['leaveWithoutPay']; ?>
									
									<?php if($dataRow['leaveWithoutPayFile'] != ''){ ?>

									&nbsp;<a href="uploads/<?php echo $dataRow['leaveWithoutPayFile']; ?>" target="_blank"><i class="icon-paper-clip"></i></a>
									
									<?php } ?>
									
									</td>

									
																		
									<td>
									<div class="form-group">

                                    <!-- Simple Icon Button -->
                                    <button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location='approve_leave_info_action?dataID=<?php echo $dataRow['dataID']; ?>&isApproved=1'" type="button" class="btn btn-raised btn-icon btn-secondary mr-1"><i class="fa fa-check"></i></button>&nbsp;&nbsp;

									<button data-toggle="tooltip" data-placement="top" data-original-title="Edit" onclick="window.location='approve_leave_info_action?dataID=<?php echo $dataRow['dataID']; ?>&isApproved=2'" type="button" class="btn btn-raised btn-icon btn-danger mr-1"><i class="fa fa-times"></i></button>
                                    
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
            url : 'delete_section.php', //Here you will fetch records 
            data :  'dataID='+ dataID+'&tableName=sections', //Pass $id
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