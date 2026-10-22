<?php
include('header.php');
include('library/number_converter.php');

$getLeaveApplicationsQNumRows = 0;

if($getEmployeeDetailsQRW['organization_id'] == 4){

if($_SESSION['username'] == 'Saifullah' || $_SESSION['username'] == 'saifullah'){


	$getLeaveApplicationsQ = mysqli_query($con, "select * from leave_edit_data_for_approval where signatory='$getUserInfoQRW[employee_id]' and isApproved=0");
	$getLeaveApplicationsQNumRows = mysqli_num_rows($getLeaveApplicationsQ);

}else if($_SESSION['username'] == '1661'){


	$getLeaveApplicationsQ = mysqli_query($con,"select * from leave_edit_data_for_approval where prevSignatory='872' and signatory='$getUserInfoQRW[employee_id]' and isApproved=0");
	$getLeaveApplicationsQNumRows = mysqli_num_rows($getLeaveApplicationsQ);

}

}else if($getEmployeeDetailsQRW['organization_id'] == 5){



	if($_SESSION['username'] == '1529'){

	
	//echo "select * from leave_edit_data_for_approval where signatory='$getUserInfoQRW[employee_id]' and isApproved=0";


	$getLeaveApplicationsQ = mysqli_query($con, "select * from leave_edit_data_for_approval where signatory='$getUserInfoQRW[employee_id]' and isApproved=0");
	$getLeaveApplicationsQNumRows = mysqli_num_rows($getLeaveApplicationsQ);

}else if($_SESSION['username'] == '1697'){


	$getLeaveApplicationsQ = mysqli_query($con,"select * from leave_edit_data_for_approval where prevSignatory='1383' and signatory='$getUserInfoQRW[employee_id]' and isApproved=0");
	$getLeaveApplicationsQNumRows = mysqli_num_rows($getLeaveApplicationsQ); 

}




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
        <h4>ছুটি সংশোধন অনুমোদন</h4>
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
								  <th>কর্মচারীর নাম ও পদবি</th>
								  <th>আইডি</th>
								  <th>অনুমোদিত ছুটি</th>								  
								  <th>পূর্বের অফিস আদেশ</th>
								  <th>সংশোধনের জন্য<br>অনুমোদিত ছুটি</th>
								  <th>সংশোধিত ছুটির অফিস আদেশ</th>
								  <th>ডিডাক্ট ফ্রম</th>
								  <th>সাবমিশন ডেট টাইম</th>
								  <th width="80">Action</th>

								</tr>
								
							</thead>
							<tbody>

							<?php
							
							if($getLeaveApplicationsQNumRows > 0){

							$sl = 0;	
							while($lRow = mysqli_fetch_array($getLeaveApplicationsQ)){ 
								
								$sl = $sl + 1;

								$getApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$lRow[leaveApplicationID]'");
								$getApplicationDetailsQRW = mysqli_fetch_assoc($getApplicationDetailsQ);

								$getleaveEditDataQ = mysqli_query($con, "select * from leave_edit_data where leaveApplicationID='$lRow[leaveApplicationID]'");
								$getleaveEditDataQRW = mysqli_fetch_assoc($getleaveEditDataQ);


								
								
								

								// চাহিত ছুটি 

								$leaveApplicationDateF=date_create($getApplicationDetailsQRW['approvedDateFrom']);
								//echo date_format($dateF,"d/m/Y");
								$leaveApplicationDateT=date_create($getApplicationDetailsQRW['approvedDateTo']);

								$totalReqDays = dateDiffInDays($getApplicationDetailsQRW['approvedDateFrom'], $getApplicationDetailsQRW['approvedDateTo']) + 1;


								//.....

								
								$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$getApplicationDetailsQRW[applicantID]'");
								$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

								$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
								$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);


								$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getApplicationDetailsQRW[approvedLeaveType]'");
								$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);


								// revised

								if($getleaveEditDataQRW['revisedLeaveFrom'] != '--'){

								$revisedLeaveF=date_create($getleaveEditDataQRW['revisedLeaveFrom']);
								//echo date_format($dateF,"d/m/Y");
								$revisedLeaveT=date_create($getleaveEditDataQRW['revisedLeaveTo']);

								}

								$totalRevDays = $getleaveEditDataQRW['revisedLeaveDay'];


								$getRevLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getleaveEditDataQRW[leaveType]'");
								$getRevLeaveTypeQRW = mysqli_fetch_assoc($getRevLeaveTypeQ);


								if($getleaveEditDataQRW['deductFrom'] == 1){
								
									$approvedLType = "গড় বেতন";
								
								}else if($getleaveEditDataQRW['deductFrom'] == 2){
								
									$approvedLType = "অর্ধ-গড় বেতন";
								
								}else if($getleaveEditDataQRW['deductFrom'] == 3){
								
									$approvedLType = "নৈমিত্তিক (Casual Leave)";
								
								}else if($getleaveEditDataQRW['deductFrom'] == 4){
								
									$approvedLType = "বিনা বেতনে ছুটি";
								
								}else if($getleaveEditDataQRW['deductFrom'] == 5){

									$approvedLType = "ঐচ্ছিক ছুটি";

								}else if($getleaveEditDataQRW['deductFrom'] == 6){

									$approvedLType = "কর্তনহীন ছুটি ";

								}else if($getleaveEditDataQRW['deductFrom'] == 10){

									$approvedLType = "অসাধারণ ছুটি";

								}


																				

							?>


							<tr id="tr_<?php echo $sl; ?>">
									<td><?php echo $sl; ?></td>

									<td><?php echo $getEmployeeDetailsQW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name']; ?></td>

									<td><?php echo $getEmployeeDetailsQW['employee_id']; ?></td>

									<td><?php echo banglaNumber(date_format($leaveApplicationDateF,"d/m/Y")); ?> হইতে <?php echo banglaNumber(date_format($leaveApplicationDateT,"d/m/Y")); ?>, <?php echo banglaNumber($totalReqDays); ?> দি , <?php echo $getLeaveTypeQRW['leaveTitle']; ?></td>

									

									<td><a target="_blank" href="leave_office_notice.php?menuslug=allowed-leave-applications&leaveApplicationID=<?php echo $lRow['leaveApplicationID']; ?>">অফিস আদেশ</a></td>

									<td><?php if($getleaveEditDataQRW['revisedLeaveFrom'] != '--'){ echo banglaNumber(date_format($revisedLeaveF,"d/m/Y")); ?> হইতে <?php echo banglaNumber(date_format($revisedLeaveT,"d/m/Y")); ?>, <?php } echo banglaNumber($totalRevDays); ?> দিন , <?php echo $getRevLeaveTypeQRW['leaveTitle']; ?></td>

									<td>
										
										<a href="uploads/<?php echo $getleaveEditDataQRW['attachment']; ?>" target="_blank">View</a>

									</td>

									<td>
										
										<?php echo $approvedLType; ?>

									</td>


									<td>
										
										<?php echo $getleaveEditDataQRW['submitDateTime']; ?>

									</td>

									<td>
										
										<div class="btn-group">
										  <button type="button" class="btn btn-success" onClick="processapplication(<?php echo $lRow['leaveApplicationID']; ?>, 1, <?php echo $sl; ?>, <?php echo $getleaveEditDataQRW['dataID']; ?>)">Approve</button>
										  <button type="button" class="btn btn-warning" onClick="processapplication(<?php echo $lRow['leaveApplicationID']; ?>, 2, <?php echo $sl; ?>, <?php echo $getleaveEditDataQRW['dataID']; ?>)">Reject</button>
										</div>
										
									</td>



									

								</tr>


							<?php } }else{ echo "<tr><td colspan='10'>No data to display</td></tr>"; } ?>

							
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


function processapplication(leaveApplicationID, ptype, sl, dataID){
	

	if(ptype == 1){

		title = "Are you sure to approve this request?";

		icon = "success";
		
	}else if(ptype == 2){

		title = "Are you sure to reject this request?";

		icon = "warning";
		
	}else if(ptype == 3){

		title = "Are you sure to delete this data?";

		icon = "danger";
		
	}


	swal({
	  title: title,
	  text: "",
	  icon: icon,
	  buttons: true,
	  dangerMode: true,
	})
	.then((willDelete) => {
	  if (willDelete) {
		  
		$.ajax({
				type : 'post',
				url : 'leave_edit_approval_action.php', //Here you will fetch records 
				data :  'leaveApplicationID='+ leaveApplicationID+'&ptype='+ptype+'&dataID='+dataID, //Pass $id
				success : function(data){

					console.log(data);

					
					if(data == 1){
					$("#tr_"+sl).hide(1000);
					}else{


						swal("Error!", {
						  icon: "danger",
						});
						


					}

				}
			});	  
		  
		
		//window.location= 'index.html';
	  } else {
		swal("Your data is safe!");
	  }
	});



}



</script>