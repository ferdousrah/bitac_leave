<?php
include('header.php');
include('library/number_converter.php');


$getLeaveApplicationsQ = mysqli_query($con,"select * from leave_applications where status!=3 and (applicantID='$getUserInfoQRW[employee_id]' or submitBy='$_SESSION[userID]') order by dataID desc");

/* $getLeaveApplicationsQ = mysqli_query($con,"SELECT la.*
FROM leave_applications la
LEFT JOIN leave_edit_data led ON la.dataID = led.leaveApplicationID
WHERE la.applicantID='$getUserInfoQRW[employee_id]' or la.submitBy='$_SESSION[userID]' and led.leaveApplicationID IS NULL order by dataID desc");

*/

$getLeaveDeductionHQ = mysqli_query($con,"select * from leave_deduction_history where employeeID='$getUserInfoQRW[employee_id]' and isApproved=1 order by createDate desc");


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
        <h4>ছুটির ইতিহাস ও যোগদান</h4>
    </div>
	<div class="col-md-2">
        
    </div>
</div>
<section class="invoice-template">
    <div class="card">
        <div class="card-body p-3">
            <div id="invoice-template" class="card-block">



			<div class="row match-height">
				<div class="col-xl-12 col-lg-12">
				  <div class="card">
					<div class="card-header">

					</div>
					<div class="card-body">
					  <div class="card-block">
						<ul class="nav nav-tabs nav-justified">

						  <li class="nav-item">
							<a class="nav-link active" id="active-tab" data-toggle="tab" href="#active" aria-controls="active" aria-expanded="true" style="background-color: #68B984; color: #fff;">আবেদনের ভিত্তিতে</a>
						  </li>
						  <li class="nav-item">
							<a class="nav-link" id="link-tab" data-toggle="tab" href="#link" aria-controls="link" aria-expanded="false" style="background-color: #E94560; color: #fff;">অফিস আদেশের ভিত্তিতে</a>
						  </li>
						  
						</ul>
						<p>&nbsp;</p>
						<div class="tab-content px-1 pt-1">

						  <div role="tabpanel" class="tab-pane active show" id="active" aria-labelledby="active-tab" aria-expanded="true">
							<!-- 1st tab -->

							<table class="table table-striped table-bordered zero-configuration">
							<thead>
								<tr>
									<th>ক্রমিক</th>

									<th>আবেদনকারীর নাম ও পদবী</th>

									<th>চাহিত ছুটি</th>

									<th>প্রাথমিক অনুমোদিত ছুটি</th>

									<th>ভোগকৃত ছুটি</th>

									<th>যোগদানের প্রকার</th>

									<th>সংশোধিত ছুটি </th>

									<th width="80">স্টেটাস </th>

									<th width="80">প্রয়োজনীয় পত্রসমূহ</th>
								</tr>
							</thead>
							<tbody>

							<?php 
							$sl = 0;	
							while($lRow = mysqli_fetch_array($getLeaveApplicationsQ)){ 
								$sl = $sl + 1;

								$getEmpDetailsQ = mysqli_query($con, "select * from employee_list where id='$lRow[applicantID]'");
								$getEmpDetailsQRW = mysqli_fetch_assoc($getEmpDetailsQ);

								$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmpDetailsQRW[designation]'");
								$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

								$getSectionDetailsQ = mysqli_query($con, "select * from sections where id='$getEmpDetailsQRW[section_id]'");
								$getSectionDetailsQRW = mysqli_fetch_assoc($getSectionDetailsQ);

								$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$lRow[leaveType]'");
								$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

								$getAppLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$lRow[approvedLeaveType]'");
								$getAppLeaveTypeQRW = mysqli_fetch_assoc($getAppLeaveTypeQ);

								$approvedLType = "";


								if($lRow['leaveTypeInTwo'] == 1){
								
									$approvedLType = "গড় বেতন";
								
								}else if($lRow['leaveTypeInTwo'] == 2){
								
									$approvedLType = "অর্ধ-গড় বেতন";
								
								}else if($lRow['leaveTypeInTwo'] == 3){
								
									$approvedLType = "নৈমিত্তিক (Casual Leave)";
								
								}else if($lRow['leaveTypeInTwo'] == 4){
								
									$approvedLType = "বিনা বেতনে ছুটি";
								
								}else if($lRow['leaveTypeInTwo'] == 5){

									$approvedLType = "ঐচ্ছিক ছুটি";

								}else if($lRow['leaveTypeInTwo'] == 6){

									$approvedLType = "কর্তনহীন ছুটি ";

								}else if($lRow['leaveTypeInTwo'] == 10){

									$approvedLType = "অসাধারণ ছুটি";

								}


								
								$getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$lRow[dataID]'");
								$getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

								$getLeaveJoiningApplicationQNumRows = mysqli_num_rows($getApplicationTypeQ);

								// চাহিত ছুটি 

								$leaveApplicationDateF=date_create($lRow['dateFrom']);
								//echo date_format($dateF,"d/m/Y");
								$leaveApplicationDateT=date_create($lRow['dateTo']);

								$totalReqDays = dateDiffInDays($lRow['dateFrom'], $lRow['dateTo']) + 1;


								// প্রাথমিক অনুমোদিত ছুটি

												$adateF=date_create($lRow['primaryLeaveDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($lRow['primaryLeaveDateTo']);

												$adateDiff = dateDiffInDays($lRow['primaryLeaveDateFrom'], $lRow['primaryLeaveDateTo']) + 1;

												if($getLeaveJoiningApplicationQNumRows > 0){


													// ভোগকৃত ছুটি

													$leaveSpentDateFrom = date_create($lRow['primaryLeaveDateFrom']);

													$leaveSpentDateTo = date_create($getApplicationTypeQRW['requestedJoiningDate']);

													$leaveSpent = dateDiffInDays($lRow['primaryLeaveDateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


													// সংশোধিত ছুটি
													if($getApplicationTypeQRW['approvedDate'] != ''){

														$correctionJoiningDate = date_create($lRow['approvedDateTo']);

														$correctedLeaveSpent = dateDiffInDays($lRow['primaryLeaveDateFrom'], $lRow['approvedDateTo']) + 1;

													}else{
													
														$correctionJoiningDate = "";

														$correctedLeaveSpent= "";
													
													}

												}


									$checkIsSupervisorApproveQ = mysqli_query($con, "select count(*) as totalApBySRow from leave_data_for_approval where leaveApplicationID='$lRow[dataID]' and isSupervisor=1 and isApproved=0 and isRead=0");
									$checkIsSupervisorApproveQRW = mysqli_fetch_assoc($checkIsSupervisorApproveQ);


																				

							?>


							<tr id="trr_<?php echo $sl; ?>">
									<td><?php echo $sl; ?></td>

									<td><?php echo $getEmpDetailsQRW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name']; ?>, <?php echo $getSectionDetailsQRW['section_name']; ?></td>

									<td>
										
										<?php echo banglaNumber(date_format($leaveApplicationDateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveApplicationDateT,"d/m/Y")); ?>, <?php echo banglaNumber($totalReqDays); ?> দিন <?php echo $getLeaveTypeQRW['leaveTitle']; ?>

									</td>

									<td>

									<?php if($lRow['status'] == 1){ 
										
										echo $getAppLeaveTypeQRW['leaveTitle'].'-';
										
										?>
										
										<?php echo banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")); ?>, <?php echo banglaNumber($adateDiff); ?> দিন <?php if($lRow['primaryApprovedLeaveType'] == 1){ echo "গড় বেতন"; }else if($lRow['primaryApprovedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($lRow['primaryApprovedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($lRow['primaryApprovedLeaveType'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($lRow['primaryApprovedLeaveType'] == 5){ echo "ঐচ্ছিক ছুটি"; }else if($lRow['primaryApprovedLeaveType'] == 6){ echo "কর্তনহীন ছুটি "; }else if($lRow['primaryApprovedLeaveType'] == 10){ echo "অসাধারণ ছুটি"; } ?>

									<?php } ?>

									</td>


									<td>
									<?php if($getLeaveJoiningApplicationQNumRows > 0){ ?>
									<?php echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")); ?>, <?php echo banglaNumber($leaveSpent); ?> দিন <?php if($lRow['primaryApprovedLeaveType'] == 1){ echo "গড় বেতন"; }else if($lRow['primaryApprovedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($lRow['primaryApprovedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($lRow['primaryApprovedLeaveType'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($lRow['primaryApprovedLeaveType'] == 5){ echo "ঐচ্ছিক ছুটি"; }else if($lRow['primaryApprovedLeaveType'] == 6){ echo "কর্তনহীন ছুটি "; }else if($lRow['primaryApprovedLeaveType'] == 10){ echo "অসাধারণ ছুটি"; } ?>
									
									<?php } ?>
									</td>

									<td>
									<?php if($getLeaveJoiningApplicationQNumRows > 0){ ?>

									<?php if($getApplicationTypeQRW['joiningType'] == 1){ echo "সঠিক সময়ে যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 2){ echo "অগ্রিম যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 3){ echo "বর্ধিত ছুটির আবেদন"; } ?>
									
									<?php } ?>
									
									</td>


									<td>
									
									<?php if($getLeaveJoiningApplicationQNumRows > 0){ ?>
										
									<?php if($getApplicationTypeQRW['approvedDate'] != ''){ echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($correctionJoiningDate,"d/m/Y")); ?>, <?php echo banglaNumber($correctedLeaveSpent); ?> দিন <?php if($getApplicationTypeQRW['approvedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 5){ echo "ঐচ্ছিক ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 6){ echo "কর্তনহীন ছুটি "; }else if($getApplicationTypeQRW['approvedLeaveType'] == 10){ echo "অসাধারণ ছুটি"; } } ?>
									
									<?php } ?>
									
									</td>


									

									
									
									
									<td width="80"><?php if($lRow['status'] == 1 && $getLeaveJoiningApplicationQNumRows > 0){ 


									if($getApplicationTypeQRW['status'] == 0){ echo "<span class='badge badge-primary'>যোগদানপত্র অনুমোদনের জন্য অপেক্ষমান</span>"; }else if($getApplicationTypeQRW['status'] == 2){ echo "<span class='badge badge-danger'>অনুমোদিত হয়নি</span>"; }else if($getApplicationTypeQRW['status'] == 1){
									
										echo "<span class='badge badge-success'>যোগদান পত্র অনুমোদন করা হয়েছে ।</span>";
									
									}

									}else if($lRow['status'] == 1 && $getLeaveJoiningApplicationQNumRows <= 0){

										if($lRow['applicationType'] == 1){
									?>
									
										<div class="btn-group mr-1 mb-1">
											<button type="button" class="btn btn-raised btn-success dropdown-toggle btn-sm" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-toggle-on"></i> কর্মক্ষেত্রে যোগদান</button>
											<div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 31px, 0px); top: 0px; left: 0px; will-change: transform;">
												<a class="dropdown-item" href="join_at_work_intime?applicationID=<?php echo $lRow['dataID']; ?>&menuslug=all-leave-application">সঠিক সময়ে যোগদান</a>
												<a class="dropdown-item" href="join_at_work_advance_joining?applicationID=<?php echo $lRow['dataID']; ?>&menuslug=all-leave-application">ছুটি পূর্ণ ভোগ না করে অগ্রিম যোগদান</a>
												<a class="dropdown-item" href="join_at_work_after_joining_date?applicationID=<?php echo $lRow['dataID']; ?>&menuslug=all-leave-application">বর্ধিত ছুটি মঞ্জুর ও কর্মস্থলে যোগদানের অনুমতি</a>
												
											</div>
										</div>

									<?php }else if($lRow['applicationType'] == 2){ echo "<span class='badge badge-success'>যোগদান পত্র ও ছুটি<br><br> অনুমোদন করা হয়েছে ।</span>"; } }else if($lRow['status'] == 2 && $getLeaveJoiningApplicationQNumRows <= 0){
									
										echo "<span class='badge badge-danger'>অনুমোদিত হয়নি</span>";
									
									} ?>
									
									</td>

									
									<td align="center">
									
									<div class="btn-group mr-1 mb-1">
											<button type="button" class="btn btn-raised btn-success dropdown-toggle btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"><i class="fa fa-folder"></i></button>
											<div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 31px, 0px); top: 0px; left: 0px; will-change: transform;">
									
									
									<a class="dropdown-item" href="leave_application_details.php?menuslug=all-leave-application&leaveApplicationID=<?php echo $lRow['dataID']; ?>" target="_blank">আবেদনপত্র</a>

									<?php if($checkIsSupervisorApproveQRW['totalApBySRow'] == 1){ ?>

										<a class="dropdown-item" href="edit_my_application?applicationID=<?php echo base64_encode($lRow['dataID']); ?>&menuslug=<?php echo $_GET['menuslug']; ?>">এডিট করুন</a>

										<a class="dropdown-item" onClick="cancelApplication(<?php echo $lRow['dataID']; ?>, <?php echo $sl; ?>)">ডিলিট</a>


									<?php } ?>
									
									<?php if($lRow['status'] == 1){ ?>
									<a class="dropdown-item" href="leave_office_notice.php?menuslug=all-leave-application&leaveApplicationID=<?php echo $lRow['dataID']; ?>" target="_blank">অফিস আদেশ</a>
									
									<?php } ?>


									<?php if($getLeaveJoiningApplicationQNumRows > 0){ ?>

									<?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>

												<a class="dropdown-item" target="_blank" href="leave_joining_application_details?menuslug=all-leave-application&leaveApplicationID=<?php echo $lRow['dataID']; ?>">যোগদান পত্র</a>

												<?php } else if($getApplicationTypeQRW['joiningType'] == 2){ ?>

												<a class="dropdown-item" target="_blank" href="leave_joining_application_details_typetwo?menuslug=all-leave-application&leaveApplicationID=<?php echo $lRow['dataID']; ?>">যোগদান পত্র</a>

												<?php } else if($getApplicationTypeQRW['joiningType'] == 3){ ?>


												<a class="dropdown-item" target="_blank" href="leave_joining_application_details_typethree?menuslug=all-leave-application&leaveApplicationID=<?php echo $lRow['dataID']; ?>">যোগদান পত্র</a>

												<?php } ?>

									<?php if($getApplicationTypeQRW['status'] == 1){ ?>


										<a class="dropdown-item" href="updated_leave_office_notice?menuslug=all-leave-application&leaveApplicationID=<?php echo $lRow['dataID']; ?>" target="_blank">সংশোধিত অফিস আদেশ</a>


									<?php } ?>


									
											


									<?php } ?>


									</div>
										</div>
									
									</td>

								</tr>


							<?php } ?>

							
								</tbody>
							
							</table>


							<!-- end of 1st tab -->
						  </div>
						  <div class="tab-pane" id="link" role="tabpanel" aria-labelledby="link-tab" aria-expanded="false">
							<!-- 2nd tab -->




							<table class="table table-striped table-bordered zero-configuration">
							<thead>
								<tr>
									<th>ক্রমিক</th>
									
									<th>ছুটির ধরণ</th>

									<th>কর্তন(দিন)</th>

									<th>নোট </th>

									<th>Submit Date </th>

									<th>সংযুক্তি </th>



									
									

								</tr>
							</thead>
							<tbody>
							<?php
							$sl = 0;
								while($dataRow=mysqli_fetch_array($getLeaveDeductionHQ))
								{
									$sl = $sl + 1;

									$getEmployeeDetailsQ = mysqli_query($con, "select * from employee_list where id='$dataRow[employeeID]'");
									$getEmployeeDetailsQW = mysqli_fetch_assoc($getEmployeeDetailsQ);

									$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getEmployeeDetailsQW[designation]'");
									$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);


									if($dataRow['leaveID'] == 1){

									$approvedLeaveType = "গড় বেতন ";

									}else if($dataRow['leaveID'] == 2){

									$approvedLeaveType = "অর্ধ-গড় বেতন ";

									}else if($dataRow['leaveID'] == 3){

									$approvedLeaveType = "নৈমিত্তিক (Casual Leave)";

									}else if($dataRow['leaveID'] == 4){

									$approvedLeaveType = "অসাধারণ(বিনা বেতনে ছুটি)";

									}else if($dataRow['leaveID'] == 5){

									$approvedLeaveType = "ঐচ্ছিক ছুটি";

									}



							?>
								<tr id="tr_<?php echo $sl; ?>">
									<td><?php echo $sl; ?></td>
									
									<td><?php echo $approvedLeaveType; ?></td>

									<td><?php echo $dataRow['leaveDeduction']; ?></td>

									<td><?php echo $dataRow['note']; ?></td>

									<td><?php echo $dataRow['createDate']; ?></td>

									<td><?php if($dataRow['attachment'] != ''){ ?><a href="uploads/<?php echo $dataRow['attachment']; ?>" target="_blank">View</a><?php } ?></td>



									
																		
									
								</tr>

								<?php
								}
									?>
                    </tbody>
							
						</table>


							


							<!-- end of 2nd tab -->
						  </div>
						  
						</div>
					  </div>
					</div>
				  </div>
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
	
function cancelApplication(applicationID, sl)
{
	
	

//

swal({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0CC27E',
            cancelButtonColor: '#FF586B',
            confirmButtonText: 'Yes, proceed!',
            cancelButtonText: 'No, cancel!',
            confirmButtonClass: 'btn btn-success btn-raised mr-5',
            cancelButtonClass: 'btn btn-danger btn-raised',
            buttonsStyling: false
        }).then(function () {
            // AJAX request to decline leave application
            $.ajax({
                type : 'post',
				url : 'cancelLeaveApplicationBySelf.php', //Here you will fetch records 
				data :  'applicationID='+ applicationID, //Pass $id,
                success: function(data) {
                    if (data == 0) {
                        toastr.error('Error!!');
                    } else {

						toastr.success('Success!!');

						$("#trr_"+sl).hide(1000);
                        
                    }
                    
                },
                error: function(e) {
                    console.log(e); // Log error in console
                    toastr.error('Failed to process the request.'); // Inform user about AJAX request failure
                }
            });
        }, function (dismiss) {
            // Handle cancel
            if (dismiss === 'cancel') {
                // do nothing
            }
        });
	
	
}



</script>

		