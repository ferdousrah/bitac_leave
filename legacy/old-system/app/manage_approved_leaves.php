<?php
include('header.php');
include('library/number_converter.php');

?>

<style>


.wrapper2 {
  margin:0px auto;
  width:100%;
  font-family:sans-serif;
  color:#555;
  font-size:14px;
  line-height:24px;
  border-left: 1px solid #ccc;
  border-bottom: 1px solid #ccc;
  border-right: 1px solid #ccc;
  border-top: 2px solid #CC2B52;
  background-color: #fff;
  margin-top: -2px;

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
    color:#000;
    padding:20px 0;
    
    background:#f4cad6;
	border-bottom:2px solid #CC2B52;

	border-top-left-radius: 10px;
	border-top-right-radius: 10px;
	    
    &:hover {
	  color: #000;
	  border-bottom:2px solid #CC2B52;
	  border-top-left-radius: 10px;
	  border-top-right-radius: 10px;
    }
	&.active {
      background:#953952;
	  color: #fff;
	  border-bottom:2px solid #CC2B52;
	  border-top-left-radius: 10px;
	  border-top-right-radius: 10px;
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
        <h4>কর্মক্ষেত্রে যোগদানের আবেদন সম্পাদনা</h4>
    </div>
	<div class="col-md-2">
        <!--<button onClick="window.location='new_employee_form?menuslug=manage-employee'" type="button" class="btn mr-1 mb-1 btn-outline-success"><i class="fa fa-plus"></i> নতুন সংযোজন </button> -->
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
						<ul style="margin-left: 0px; padding-left: 0px;margin-bottom: 0px;" class="tabs clearfix" data-tabgroup="second-tab-group">					  
						  <li style="margin-bottom: 0px;"><a class="active" href="#tab6">প্রক্রিয়াধীন  ছুটি</a></li>
						  <li style="margin-bottom: 0px;"><a href="#tab7">সম্পাদিত ছুটি</a></li>
						  
						</ul>

						<div class="wrapper2">
						
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

									<th>সংশোধিত ছুটি </th>

									

									<th>স্টেটাস</th>
									
									<th width="220">নথি কার্যক্রম</th>

								</tr>
							</thead>
							<tbody>


							<?php



							$getAllAppApplicationQ = mysqli_query($con, "select * FROM leave_joining_data_for_approval lj
    LEFT JOIN leave_joining_application lja ON lj.leaveApplicationID = lja.leaveApplicationID
    WHERE lj.isSupervisor = 1 AND lj.isApproved = 1
    AND lj.isSentbyAdmin = 0
    AND lja.joiningType != 1");



										$sl = 0;
										while($empRow=mysqli_fetch_array($getAllAppApplicationQ))
										{
											$sl = $sl + 1;

											$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$empRow[leaveApplicationID]'");
											$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

											$getJoiningApplicationDetailsQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
											$getJoiningApplicationDetailsQRW = mysqli_fetch_assoc($getJoiningApplicationDetailsQ);

											
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


											$dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;


												$dateF=date_create($getLeaveApplicationDetailsQRW['dateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);

												// proposed

												

												//$getALeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveTypeInTwo]'");
												//$getALeaveTypeQRW = mysqli_fetch_assoc($getALeaveTypeQ);

												$getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
												$getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

												$joiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												//$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


											if($getApplicationTypeQRW['joiningType'] == 1 || $getApplicationTypeQRW['joiningType'] == 2 || $getApplicationTypeQRW['joiningType'] == 3){

												// প্রাথমিক অনুমোদিত ছুটি

												$adateF=date_create($getLeaveApplicationDetailsQRW['primaryLeaveDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($getLeaveApplicationDetailsQRW['primaryLeaveDateTo']);

												$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['primaryLeaveDateFrom'], $getLeaveApplicationDetailsQRW['primaryLeaveDateTo']) + 1;

												// ভোগকৃত ছুটি

												$leaveSpentDateFrom = date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);

												$leaveSpentDateTo = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


												// সংশোধিত ছুটি
												if($getApplicationTypeQRW['approvedDate'] != ''){

													$correctionJoiningDate = date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

													$correctedLeaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

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

												<?php echo banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")); ?>, <?php echo banglaNumber($adateDiff); ?> দিন <?php if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 4){ echo "অসাধারণ(বিনা বেতনে ছুটি)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; } ?>

											</td>

											<td><?php echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")); ?>, <?php echo banglaNumber($leaveSpent); ?> দিন <?php if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 4){ echo "অসাধারণ(বিনা বেতনে ছুটি)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; } ?></td>

											<td><?php if($getApplicationTypeQRW['approvedDate'] != ''){ echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($correctionJoiningDate,"d/m/Y")); ?>, <?php echo banglaNumber($correctedLeaveSpent); ?> দিন <?php if($getApplicationTypeQRW['approvedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 4){ echo "অসাধারণ(বিনা বেতনে ছুটি)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; } } ?></td>



											<td><?php 

											//if($getApplicationTypeQRW['joiningType'] == 1){

											//	echo "যোগদানপত্র গৃহীত হয়েছে।";

											//}else{
											
											if($getJoiningApplicationDetailsQRW['status'] == 1){ echo "<span class='badge badge-success'>অনুমোদন করা হয়েছে</span>"; }else if($getJoiningApplicationDetailsQRW['status'] == 2){ echo "<span class='badge badge-danger'>অনুমোদিত হয়নি</span>"; }else if($getJoiningApplicationDetailsQRW['status'] == 0){ echo "<span class='badge badge-primary'>অনুমোদনের জন্যে অপেক্ষমান</span>"; }
											
											//}
											
											?></td>

									
											
											<td>

												<a target="_blank" href="leave_office_notice?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">ছুটির অফিস আদেশ</a>&nbsp;<img src="period.png" />&nbsp;

												<?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>

												<a target="_blank" href="leave_joining_application_details?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;<img src="period.png" />&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 2){ ?>

												<a target="_blank" href="leave_joining_application_details_typetwo?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;<img src="period.png" />&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 3){ ?>


													<a target="_blank" href="leave_joining_application_details_typethree?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;<img src="period.png" />&nbsp;


												<?php } ?>

												
												<?php if($empRow['isSentbyAdmin'] == 0){ 

												if($getApplicationTypeQRW['joiningType'] != 0){
												
												
												?>
												
												<a href="allowed_leave_application_joining_update?menuslug=manage-approved-leaves&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1"><img src="dashboard-icons/arrow-right.png" height="32" /></a>&nbsp;<img src="period.png" />&nbsp;

												<?php } }else{ ?>

												<a href="updated_leave_note_by_admin?menuslug=manage-approved-leaves&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1" target="_blank">সংশোধিত নোট সম্পাদনা</a>&nbsp;<img src="period.png" />&nbsp;

												<?php if($getJoiningApplicationDetailsQRW['status'] == 1){ ?>

												<a href="updated_leave_office_notice?menuslug=manage-approved-leaves&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>" target="_blank">সংশোধিত অফিস আদেশ</a>
													

												<?php } } ?>

												
											</td>

										</tr>







									<?php
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

						<table id="employeeTable" class="table table-striped table-bordered scroll-vertical" style="width: 100%;" width="100%">
								<thead>
									<tr>
									<th>ক্র:ন:</th>

									<th>আবেদনকারীর নাম ও পদবী</th>

									<th>শাখা</th>

									<th>আবেদনের প্রকার</th>

									<th>প্রাথমিক অনুমোদিত ছুটি</th>

									<th>ভোগকৃত ছুটি</th>

									<th>সংশোধিত ছুটি </th>

									

									<th>স্টেটাস</th>
									
									<th width="220">নথি কার্যক্রম</th>
									</tr>
								</thead>


							<?php



							$getAllAppApplicationQ = mysqli_query($con, "select * FROM leave_joining_data_for_approval lj
    LEFT JOIN leave_joining_application lja ON lj.leaveApplicationID = lja.leaveApplicationID
    WHERE lj.isSupervisor = 1 AND lj.isApproved = 1
    AND lj.isSentbyAdmin = 1
    AND lja.joiningType != 1");



										$sl = 0;
										while($empRow=mysqli_fetch_array($getAllAppApplicationQ))
										{
											$sl = $sl + 1;

											$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$empRow[leaveApplicationID]'");
											$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

											$getJoiningApplicationDetailsQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
											$getJoiningApplicationDetailsQRW = mysqli_fetch_assoc($getJoiningApplicationDetailsQ);

											
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


											$dateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getLeaveApplicationDetailsQRW['dateTo']) + 1;


												$dateF=date_create($getLeaveApplicationDetailsQRW['dateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);

												// proposed

												

												//$getALeaveTypeQ = mysqli_query($con, "select * from leave_types where leaveID='$getLeaveApplicationDetailsQRW[leaveTypeInTwo]'");
												//$getALeaveTypeQRW = mysqli_fetch_assoc($getALeaveTypeQ);

												$getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
												$getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

												$joiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												//$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


											if($getApplicationTypeQRW['joiningType'] == 1 || $getApplicationTypeQRW['joiningType'] == 2 || $getApplicationTypeQRW['joiningType'] == 3){

												// প্রাথমিক অনুমোদিত ছুটি

												$adateF=date_create($getLeaveApplicationDetailsQRW['primaryLeaveDateFrom']);
												//echo date_format($dateF,"d/m/Y");
												$adateT=date_create($getLeaveApplicationDetailsQRW['primaryLeaveDateTo']);

												$adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['primaryLeaveDateFrom'], $getLeaveApplicationDetailsQRW['primaryLeaveDateTo']) + 1;

												// ভোগকৃত ছুটি

												$leaveSpentDateFrom = date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);

												$leaveSpentDateTo = date_create($getApplicationTypeQRW['requestedJoiningDate']);

												$leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;


												// সংশোধিত ছুটি
												if($getApplicationTypeQRW['approvedDate'] != ''){

													$correctionJoiningDate = date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

													$correctedLeaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

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

												<?php echo banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")); ?>, <?php echo banglaNumber($adateDiff); ?> দিন <?php if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 4){ echo "অসাধারণ(বিনা বেতনে ছুটি)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; } ?>

											</td>

											<td><?php echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")); ?>, <?php echo banglaNumber($leaveSpent); ?> দিন <?php if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 4){ echo "অসাধারণ(বিনা বেতনে ছুটি)"; }else if($getLeaveApplicationDetailsQRW['primaryApprovedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; } ?></td>

											<td><?php if($getApplicationTypeQRW['approvedDate'] != ''){ echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($correctionJoiningDate,"d/m/Y")); ?>, <?php echo banglaNumber($correctedLeaveSpent); ?> দিন <?php if($getApplicationTypeQRW['approvedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 4){ echo "অসাধারণ(বিনা বেতনে ছুটি)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; } } ?></td>



											<td><?php 

											//if($getApplicationTypeQRW['joiningType'] == 1){

											//	echo "যোগদানপত্র গৃহীত হয়েছে।";

											//}else{
											
											if($getJoiningApplicationDetailsQRW['status'] == 1){ echo "<span class='badge badge-success'>অনুমোদন করা হয়েছে</span>"; }else if($getJoiningApplicationDetailsQRW['status'] == 2){ echo "<span class='badge badge-danger'>অনুমোদিত হয়নি</span>"; }else if($getJoiningApplicationDetailsQRW['status'] == 0){ echo "<span class='badge badge-primary'>অনুমোদনের জন্যে অপেক্ষমান</span>"; }
											
											//}
											
											?></td>

									
											
											<td>

												<a target="_blank" href="leave_office_notice?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">ছুটির অফিস আদেশ</a>&nbsp;<img src="period.png" />&nbsp;

												<?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>

												<a target="_blank" href="leave_joining_application_details?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;<img src="period.png" />&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 2){ ?>

												<a target="_blank" href="leave_joining_application_details_typetwo?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;<img src="period.png" />&nbsp;

												<?php } else if($getApplicationTypeQRW['joiningType'] == 3){ ?>


													<a target="_blank" href="leave_joining_application_details_typethree?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">যোগদান পত্র</a>&nbsp;<img src="period.png" />&nbsp;


												<?php } ?>

												
												<?php if($empRow['isSentbyAdmin'] == 0){ 

												if($getApplicationTypeQRW['joiningType'] != 0){
												
												
												?>
												
												<a href="allowed_leave_application_joining_update?menuslug=manage-approved-leaves&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1"><img src="dashboard-icons/arrow-right.png" height="32" /></a>&nbsp;<img src="period.png" />&nbsp;

												<?php } }else{ ?>

												<a href="updated_leave_note_by_admin?menuslug=manage-approved-leaves&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1" target="_blank">সংশোধিত নোট সম্পাদনা</a>&nbsp;<img src="period.png" />&nbsp;

												<?php if($getJoiningApplicationDetailsQRW['status'] == 1){ ?>

												<a href="updated_leave_office_notice?menuslug=manage-approved-leaves&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>" target="_blank">সংশোধিত অফিস আদেশ</a>
													

												<?php } } ?>

												
											</td>

										</tr>







									<?php
											}
										
										}

										?>



							
							</tbody>
							
						</table>
							
							</p>

							<!-- end of 2nd tab -->
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


<script>


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

/*

$(document).ready(function() {
    // Initialize DataTable for active employees
    $('#employeeTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_forwardbyadmin_pending_data.php", // Server-side script to fetch active employee data
            "type": "POST"
        },
        "columns": [
            { "data": "sl" },
            { "data": "applicant_name" },
            { "data": "employee_id" },
            { "data": "section_name" },
            { "data": "application_date_time" },
            { "data": "requested_leave_type" },
			{ "data": "requested_leave_days" },
			{ "data": "proposed_leave_days" },
			{ "data": "proposed_leave_type" },
			{ "data": "status" },
            { "data": "action" }
        ]
    });

    // Initialize DataTable for inactive employees
    $('#inactiveEmployeeTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "fetch_forwardedbyadmin_data.php", // Server-side script to fetch inactive employee data
            "type": "POST"
        },
        "columns": [
            { "data": "sl" },
            { "data": "applicant_name" },
            { "data": "employee_id" },
            { "data": "section_name" },
            { "data": "application_date_time" },
            { "data": "requested_leave_type" },
			{ "data": "requested_leave_days" },
			{ "data": "proposed_leave_days" },
			{ "data": "proposed_leave_type" },
			{ "data": "status" },
            { "data": "action" }
        ]
    });
});

*/


</script>