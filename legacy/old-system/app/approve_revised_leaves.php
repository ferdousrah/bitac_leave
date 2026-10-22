<?php
include('header.php');
include('library/number_converter.php');

$loggedUserID = $_SESSION['userID'];

$checkForAppSettingsQ = mysqli_query($con, "select * from leave_edit_approval_signatory where employeeID='$getUserInfoQRW[employee_id]'");
$checkForAppSettingsQNumRows = mysqli_num_rows($checkForAppSettingsQ);



if($checkForAppSettingsQNumRows > 0){

	$getAllApplicationQ = mysqli_query($con,"select * from `revised_leave_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isApproved=0");
	$getAllApplicationQNumRows = mysqli_num_rows($getAllApplicationQ);



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
        <h4>সংশোধিত ছুটির অনুমোদন</h4>
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
									<tr>

									<th>ক্রমিক</th>

									<th>আবেদনকারীর নাম ও পদবী</th>

									<th>শাখা</th>

									<th>ছুটির ধরণ</th>

									<th>অনুমোদিত ছুটির তারিখ </th>

									<th>অনুমোদিত ছুটির সময়কাল(দিন)</th>	
									
									<th>ভোগকৃত ছুটির তারিখ </th>

									<th>ভোগকৃত ছুটির সময়কাল(দিন)</th>
									
									<th>মন্তব্য</th>
									
									<th width="220">Action</th>

								</tr>
								</tr>
							</thead>
							<tbody>


							<?php
										$sl = 0;
										while($empRow=mysqli_fetch_array($getAllApplicationQ))
										{
											$sl = $sl + 1;

											$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$empRow[leaveApplicationID]'");
											$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

											$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getLeaveApplicationDetailsQRW[applicantID]'");
											$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

											$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
											$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

											$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmployeeDetailsQW[section_id]'");
											$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

											$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
											$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

											
											if($empRow['prevSignatory'] == 0){

												$proceed = 1;
											
											}else{
												


												$checkPrevSognatorySignedQ = mysqli_query($con, "select * from `revised_leave_data_for_approval` where leaveApplicationID='$empRow[leaveApplicationID]' and signatory='$empRow[prevSignatory]' and isApproved=1");
												$checkPrevSognatorySignedQNumRows = mysqli_num_rows($checkPrevSognatorySignedQ);

												if($checkPrevSognatorySignedQNumRows > 0){


													$proceed = 1;												
												
												}else{
												
													$proceed = 0;
												
												}
											
											}


											if($proceed == 1){


												$dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;


												$dateF=date_create($getLeaveApplicationDetailsQRW['dateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);


												// vughkrito

												$sdateF=date_create($empRow['leaveFrom']);
												//echo date_format($dateF,"d/m/Y");
												$sdateT=date_create($empRow['leaveTo']);


											
																																	
										?>



										<tr>
											<td><?php echo banglaNumber($sl); ?></td>

											<td><?php echo $getEmployeeDetailsQW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name']; ?></td>

											<td><?php echo $getSectionDetailsQRW['section_name']; ?></td>

											<td><?php echo $getLeaveTypeQRW['leaveTitle']; ?></td>

											<td><?php echo banglaNumber(date_format($dateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($dateT,"d/m/Y")); ?></td>

											<td><?php echo banglaNumber($dateDiff); ?></td>

											<td><?php echo banglaNumber(date_format($sdateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($sdateT,"d/m/Y")); ?></td>

											<td><?php echo banglaNumber($empRow['approvedDays']); ?></td>

											<td><?php echo $empRow['adminNote']; ?></td>



									
											
											<td>

												
												<a href="cancel_revised_leave_application_changes?menuslug=approve-revised-leaves&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=2"><img src="app-assets/cross.png" height="32" /></a>&nbsp;&nbsp;&nbsp;
												
												<a href="approve_revised_leave_application?menuslug=approve-revised-leaves&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1"><img height="32" src="app-assets/check-mark.png" /></a>

											</td>

										</tr>







									<?php
										
											$proceed = 0;

											}
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
            url : 'delete_data.php', //Here you will fetch records 
            data :  'dataID='+ dataID+'&tableName=modules', //Pass $id
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