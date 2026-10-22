<?php
include('header.php');
include('library/number_converter.php');

$loggedUserID = $_SESSION['userID'];

$checkForAppSettingsQ = mysqli_query($con, "select * from leave_approval_signatory where employeeID='$getUserInfoQRW[employee_id]'");
$checkForAppSettingsQNumRows = mysqli_num_rows($checkForAppSettingsQ);

/*

if($checkForAppSettingsQNumRows > 0){

	$checkHowManyTimesInAppQ = mysqli_query($con, "select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSupervisor=1 and isApproved=0  order by leaveApplicationID desc");
	$checkHowManyTimesInAppQNumRows = mysqli_num_rows($checkHowManyTimesInAppQ);

	if($checkHowManyTimesInAppQNumRows > 0){

	$getAllApplicationQ = mysqli_query($con,"select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSupervisor=1 and isApproved=0 order by leaveApplicationID desc");
	
	}else{

	$getAllApplicationQ = mysqli_query($con,"select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSentbyAdmin=1 and isApproved=0 order by leaveApplicationID desc");
	$getAllApplicationQNumRows = mysqli_num_rows($getAllApplicationQ);

	}



}else{


	$getAllApplicationQ = mysqli_query($con,"select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSupervisor=1 and isApproved=0 order by leaveApplicationID desc");
	$getAllApplicationQNumRows = mysqli_num_rows($getAllApplicationQ);

	if($getAllApplicationQNumRows <= 0){

		$getAllApplicationQ = mysqli_query($con,"select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSentbyAdmin=1 and isApproved=0 order by leaveApplicationID desc");
	
	
	}





}

*/

$getSupervisorApplication = mysqli_query($con, "select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSupervisor=1 and isApproved=0 order by leaveApplicationID desc");

$getApplicationFromAdmin = mysqli_query($con,"select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSentbyAdmin=1 and isSupervisor!=1 and isApproved=0 order by leaveApplicationID desc");

?>


<style>


.wrapper2 {
  margin:30px auto;
  width:100%;
  font-family:sans-serif;
  color:#555;
  font-size:14px;
  line-height:24px;
}
h1 {
  font-size:20px;
  font-weight:bold;
  text-align:center;
  text-transform:uppercase;
}
h1 + p {
  text-align:center;
  margin:20px 0;
  font-size:16px;
}
.tabs {
  li {
    float:left;
    width:20%;
	list-style-type: none;
  }
  a {
    display:block;
    text-align:center;
    text-decoration:none;
    text-transform:uppercase;
    color:#888;
    padding:20px 0;
    border-bottom:2px solid #888;
    background:#f7f7f7;
    
    &:hover, &.active {
      background:#ddd;
    }
  }
}


</style>



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
        <h4>কর্মক্ষেত্রে যোগদানের  আবেদন পত্রে সুপারিশ ও অনুমোদন</h4>
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




					<div class="wrapper2">
					<ul style="margin-left: 0px; padding-left: 0px;" class="tabs clearfix" data-tabgroup="second-tab-group">
					  <li><a href="#tab6" class="active">সুপারিশ</a></li>
					  <li><a href="#tab7">অনুমোদন</a></li>
					  
					</ul>
					<section id="second-tab-group" class="tabgroup">
					  <div id="tab6">
						<!-- tab 6 -->
						<p>
						<table class="table table-striped table-bordered zero-configuration">
							<thead>

									<tr>

									<th>ক্র:ন:</th>

									<th>আবেদনকারীর নাম ও পদবী</th>

									<th>শাখা</th>

									<th>আবেদনের প্রকার</th>

									<th>প্রাথমিক অনুমোদিত ছুটি</th>

									<th>ভোগকৃত ছুটি</th>

									<th>প্রস্তাবিত ছুটি </th>
									
									<th width="220">Action</th>

								</tr>

							</thead>
							<tbody>


							<?php
										$sl = 0;
										while($empRow=mysqli_fetch_array($getSupervisorApplication))
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

											$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQW[organization_id]'");
											$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

											$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
											$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

											
											if($empRow['prevSignatory'] == 0){

												$proceed = 1;
											
											}else{
												


												$checkPrevSognatorySignedQ = mysqli_query($con, "select * from `leave_joining_data_for_approval` where leaveApplicationID='$empRow[leaveApplicationID]' and signatory='$empRow[prevSignatory]' and isApproved=1");
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

												// proposed

												$adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

												$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

												//$getALeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveTypeInTwo]'");
												//$getALeaveTypeQRW = mysqli_fetch_assoc($getALeaveTypeQ);

												$getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
												$getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

												$joiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


												// প্রাথমিক অনুমোদিত ছুটি

												$adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

												$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

												// ভোগকৃত ছুটি

												$leaveSpentDateFrom = date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);

												$leaveSpentDateTo = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


												// সংশোধিত ছুটি
												if($getApplicationTypeQRW['requestedJoiningDate'] != ''){

													$correctionJoiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

													$correctedLeaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;

												}else{
												
													$correctionJoiningDate = "";

													$correctedLeaveSpent= "";
												
												}
											
																																	
										?>



										<tr>
											<td><?php echo $sl; ?></td>

											<td><?php echo $getEmployeeDetailsQW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name']; ?></td>

											<td><?php echo $getSectionDetailsQRW['section_name'].", ".$getorgDetailsQRW['organization_name']; ?></td>

											<td><?php if($getApplicationTypeQRW['joiningType'] == 1){ echo "সঠিক সময়ে যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 2){ echo "অগ্রিম যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 3){ echo "বর্ধিত ছুটির আবেদন"; } ?></td>

											

											<td>

												<?php echo banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")); ?>, <?php echo banglaNumber($adateDiff); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "কর্তনহীন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?>

											</td>

											<td><?php echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")); ?>, <?php echo banglaNumber($leaveSpent); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "কর্তনহীন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?></td>

											<td><?php if($getApplicationTypeQRW['requestedJoiningDate'] != ''){ echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($correctionJoiningDate,"d/m/Y")); ?>, <?php echo banglaNumber($correctedLeaveSpent); ?> দিন <?php if($getApplicationTypeQRW['approvedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 6){ echo "কর্তনহীন ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 10){ echo "অসাধারণ ছুটি"; } } ?></td>

									
											
											<td>

												
												<a target="_blank" href="leave_office_notice?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">ছুটির অফিস আদেশ</a>&nbsp;&nbsp;&nbsp;

												<?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>

												<a target="_blank" href="leave_joining_application_details?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;&nbsp;&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 2){ ?>

												<a target="_blank" href="leave_joining_application_details_typetwo?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;&nbsp;&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 3){ ?>


													<a target="_blank" href="leave_joining_application_details_typethree?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;&nbsp;&nbsp;


												<?php } ?>

												<!--<a href="approve_increment_changes.php?menuslug=leave-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=2"><img src="app-assets/cross.png" height="32" /></a>&nbsp;&nbsp;&nbsp;-->
												
												<?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>

												<a href="approve_leave_joining_application_typeone.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1"><img height="32" src="app-assets/check-mark.png" /></a>

												<?php } else{ ?>


												<a href="approve_leave_joining_application.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1"><img height="32" src="app-assets/check-mark.png" /></a>


												<?php } ?>

											</td>

										</tr>







									<?php
										
											$proceed = 0;

											}
										}
										?>



							
							</tbody>
							
						</table>
						</p>

						<!-- end of tab 6 -->
					  </div>
					  <div id="tab7">
						<!-- tab 6 -->
						<p>
						<table class="table table-striped table-bordered zero-configuration">
							<thead>

									<tr>

									<th>ক্র:ন:</th>

									<th>আবেদনকারীর নাম ও পদবী</th>

									<th>শাখা</th>

									<th>আবেদনের প্রকার</th>

									<th>প্রাথমিক অনুমোদিত ছুটি</th>

									<th>ভোগকৃত ছুটি</th>

									<th>প্রস্তাবিত ছুটি </th>
									
									<th width="220">Action</th>

								</tr>

							</thead>
							<tbody>


							<?php
										$sl = 0;
										while($empRow=mysqli_fetch_array($getApplicationFromAdmin))
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

											$getorgDetailsQ = mysqli_query($con, "select * from organization where id='$getEmployeeDetailsQW[organization_id]'");
											$getorgDetailsQRW = mysqli_fetch_assoc($getorgDetailsQ);

											$getLeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveType]'");
											$getLeaveTypeQRW = mysqli_fetch_assoc($getLeaveTypeQ);

											
											if($empRow['prevSignatory'] == 0){

												$proceed = 1;
											
											}else{
												


												$checkPrevSognatorySignedQ = mysqli_query($con, "select * from `leave_joining_data_for_approval` where leaveApplicationID='$empRow[leaveApplicationID]' and signatory='$empRow[prevSignatory]' and isApproved=1");
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

												// proposed

												$adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

												$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

												//$getALeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveTypeInTwo]'");
												//$getALeaveTypeQRW = mysqli_fetch_assoc($getALeaveTypeQ);

												$getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
												$getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

												$joiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


												// প্রাথমিক অনুমোদিত ছুটি

												$adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

												$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

												// ভোগকৃত ছুটি

												$leaveSpentDateFrom = date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);

												$leaveSpentDateTo = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


												// সংশোধিত ছুটি
												if($getApplicationTypeQRW['requestedJoiningDate'] != ''){

													$correctionJoiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

													$correctedLeaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;

												}else{
												
													$correctionJoiningDate = "";

													$correctedLeaveSpent= "";
												
												}
											
																																	
										?>



										<tr>
											<td><?php echo $sl; ?></td>

											<td><?php echo $getEmployeeDetailsQW['employee_name'].', '.$getDesignationDetailsQRW['job_title_name']; ?></td>

											<td><?php echo $getSectionDetailsQRW['section_name'].", ".$getorgDetailsQRW['organization_name']; ?></td>

											<td><?php if($getApplicationTypeQRW['joiningType'] == 1){ echo "সঠিক সময়ে যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 2){ echo "অগ্রিম যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 3){ echo "বর্ধিত ছুটির আবেদন"; } ?></td>

											

											<td>

												<?php echo banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")); ?>, <?php echo banglaNumber($adateDiff); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "সংগনিরোধ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 7){ echo "প্রসূতি ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 8){ echo "অক্ষমতাজনিত বিশেষ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 9){ echo "অধ্যয়ন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?>

											</td>

											<td><?php echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")); ?>, <?php echo banglaNumber($leaveSpent); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "সংগনিরোধ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 7){ echo "প্রসূতি ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 8){ echo "অক্ষমতাজনিত বিশেষ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 9){ echo "অধ্যয়ন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?></td>

											<td><?php if($getApplicationTypeQRW['requestedJoiningDate'] != ''){ echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($correctionJoiningDate,"d/m/Y")); ?>, <?php echo banglaNumber($correctedLeaveSpent); ?> দিন <?php if($getApplicationTypeQRW['approvedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 6){ echo "সংগনিরোধ ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 7){ echo "প্রসূতি ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 8){ echo "অক্ষমতাজনিত বিশেষ ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 9){ echo "অধ্যয়ন ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 10){ echo "অসাধারণ ছুটি"; } } ?></td>

									
											
											<td>

												
												<a target="_blank" href="leave_office_notice?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">ছুটির অফিস আদেশ</a>&nbsp;&nbsp;&nbsp;

												<?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>

												<a target="_blank" href="leave_joining_application_details?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;&nbsp;&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 2){ ?>

												<a target="_blank" href="leave_joining_application_details_typetwo?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;&nbsp;&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 3){ ?>


													<a target="_blank" href="leave_joining_application_details_typethree?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;&nbsp;&nbsp;


												<?php } ?>

												<!--<a href="approve_increment_changes.php?menuslug=leave-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=2"><img src="app-assets/cross.png" height="32" /></a>&nbsp;&nbsp;&nbsp;-->
												
												<?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>

												<a href="approve_leave_joining_application_typeone.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1"><img height="32" src="app-assets/check-mark.png" /></a>

												<?php } else{ ?>


												<a href="approve_leave_joining_application.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1"><img height="32" src="app-assets/check-mark.png" /></a>


												<?php } ?>

											</td>

										</tr>







									<?php
										
											$proceed = 0;

											}
										}
										?>



							
							</tbody>
							
						</table>
						</p>

						<!-- end of tab 6 -->
					  </div>
					  
					</section>
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


$('.tabgroup > div').hide();
$('.tabgroup > div:first-of-type').show();
$('.tabs a').click(function(e){
  e.preventDefault();
    var $this = $(this),
        tabgroup = '#'+$this.parents('.tabs').data('tabgroup'),
        others = $this.closest('li').siblings().children('a'),
        target = $this.attr('href');
    others.removeClass('active');
    $this.addClass('active');
    $(tabgroup).children('div').hide();
    $(target).show();
  
})


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