<?php
include(__DIR__ . '/includes/header_vuexy.php');
include('library/number_converter.php');

$loggedUserID = $_SESSION['userID'];

$checkForAppSettingsQ = mysqli_query($con, "select * from leave_approval_signatory where employeeID='$getUserInfoQRW[employee_id]'");
$checkForAppSettingsQNumRows = mysqli_num_rows($checkForAppSettingsQ);

$getSupervisorApplication = mysqli_query($con, "select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSupervisor=1 and isApproved=0 order by leaveApplicationID desc");

$getApplicationFromAdmin = mysqli_query($con,"select * from `leave_joining_data_for_approval` where signatory='$getUserInfoQRW[employee_id]' and isSentbyAdmin=1 and isSupervisor!=1 and isApproved=0 order by leaveApplicationID desc");

?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">কর্মক্ষেত্রে যোগদানের আবেদন পত্রে সুপারিশ ও অনুমোদন</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Leave Joining Approval Card -->
<div class="card">
    <div class="card-body">
        <!-- Nav Tabs -->
        <ul class="nav nav-tabs nav-fill" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#supervision" role="tab">
                    <i class="ti tabler-clipboard-check me-2"></i>
                    <span class="d-none d-sm-inline">সুপারিশ</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#approval" role="tab">
                    <i class="ti tabler-circle-check me-2"></i>
                    <span class="d-none d-sm-inline">অনুমোদন</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content pt-4">
            <!-- Tab 1: Supervision -->
            <div class="tab-pane fade show active" id="supervision" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered zero-configuration">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্র:ন:</th>
                                <th>আবেদনকারীর নাম ও পদবী</th>
                                <th>শাখা</th>
                                <th>আবেদনের প্রকার</th>
                                <th>প্রাথমিক অনুমোদিত ছুটি</th>
                                <th>ভোগকৃত ছুটি</th>
                                <th>প্রস্তাবিত ছুটি</th>
                                <th>Action</th>
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
                                    $dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);

                                    // proposed
                                    $adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
                                    $adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

                                    $adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

                                    $getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
                                    $getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

                                    $joiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

                                    $leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;

                                    // প্রাথমিক অনুমোদিত ছুটি
                                    $adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
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
                                <td><?php echo htmlspecialchars($getEmployeeDetailsQW['employee_name']).', '.htmlspecialchars($getDesignationDetailsQRW['job_title_name']); ?></td>
                                <td><?php echo htmlspecialchars($getSectionDetailsQRW['section_name']).", ".htmlspecialchars($getorgDetailsQRW['organization_name']); ?></td>
                                <td><?php if($getApplicationTypeQRW['joiningType'] == 1){ echo "সঠিক সময়ে যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 2){ echo "অগ্রিম যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 3){ echo "বর্ধিত ছুটির আবেদন"; } ?></td>
                                <td>
                                    <?php echo banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")); ?>, <?php echo banglaNumber($adateDiff); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "কর্তনহীন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?>
                                </td>
                                <td><?php echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")); ?>, <?php echo banglaNumber($leaveSpent); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "কর্তনহীন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?></td>
                                <td><?php if($getApplicationTypeQRW['requestedJoiningDate'] != ''){ echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($correctionJoiningDate,"d/m/Y")); ?>, <?php echo banglaNumber($correctedLeaveSpent); ?> দিন <?php if($getApplicationTypeQRW['approvedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 6){ echo "কর্তনহীন ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 10){ echo "অসাধারণ ছুটি"; } } ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a class="btn btn-sm btn-info" target="_blank" href="leave_office_notice?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                            <i class="ti tabler-file-description me-1"></i>ছুটির অফিস আদেশ
                                        </a>

                                        <?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>
                                            <a class="btn btn-sm btn-secondary" target="_blank" href="leave_joining_application_details?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                                <i class="ti tabler-file-check me-1"></i>যোগদান পত্র
                                            </a>
                                        <?php } else if($getApplicationTypeQRW['joiningType'] == 2){ ?>
                                            <a class="btn btn-sm btn-secondary" target="_blank" href="leave_joining_application_details_typetwo?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                                <i class="ti tabler-file-check me-1"></i>যোগদান পত্র
                                            </a>
                                        <?php } else if($getApplicationTypeQRW['joiningType'] == 3){ ?>
                                            <a class="btn btn-sm btn-secondary" target="_blank" href="leave_joining_application_details_typethree?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                                <i class="ti tabler-file-check me-1"></i>যোগদান পত্র
                                            </a>
                                        <?php } ?>

                                        <?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>
                                            <a class="btn btn-sm btn-success" href="approve_leave_joining_application_typeone.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1">
                                                <i class="ti tabler-check me-1"></i>অনুমোদন
                                            </a>
                                        <?php } else{ ?>
                                            <a class="btn btn-sm btn-success" href="approve_leave_joining_application.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1">
                                                <i class="ti tabler-check me-1"></i>অনুমোদন
                                            </a>
                                        <?php } ?>
                                    </div>
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

            <!-- Tab 2: Approval -->
            <div class="tab-pane fade" id="approval" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered zero-configuration">
                        <thead>
                            <tr style="background-color: #435971 !important; color: #ffffff !important;">
                                <th>ক্র:ন:</th>
                                <th>আবেদনকারীর নাম ও পদবী</th>
                                <th>শাখা</th>
                                <th>আবেদনের প্রকার</th>
                                <th>প্রাথমিক অনুমোদিত ছুটি</th>
                                <th>ভোগকৃত ছুটি</th>
                                <th>প্রস্তাবিত ছুটি</th>
                                <th>Action</th>
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
                                    $dateT=date_create($getLeaveApplicationDetailsQRW['dateTo']);

                                    // proposed
                                    $adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
                                    $adateT=date_create($getLeaveApplicationDetailsQRW['approvedDateTo']);

                                    $adateDiff = dateDiffInDays($getLeaveApplicationDetailsQRW['approvedDateFrom'], $getLeaveApplicationDetailsQRW['approvedDateTo']) + 1;

                                    $getApplicationTypeQ = mysqli_query($con, "select * from leave_joining_application where leaveApplicationID='$empRow[leaveApplicationID]'");
                                    $getApplicationTypeQRW = mysqli_fetch_assoc($getApplicationTypeQ);

                                    $joiningDate = date_create($getApplicationTypeQRW['requestedJoiningDate']);

                                    $leaveSpent = dateDiffInDays($getLeaveApplicationDetailsQRW['dateFrom'], $getApplicationTypeQRW['requestedJoiningDate']) + 1;

                                    // প্রাথমিক অনুমোদিত ছুটি
                                    $adateF=date_create($getLeaveApplicationDetailsQRW['approvedDateFrom']);
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
                                <td><?php echo htmlspecialchars($getEmployeeDetailsQW['employee_name']).', '.htmlspecialchars($getDesignationDetailsQRW['job_title_name']); ?></td>
                                <td><?php echo htmlspecialchars($getSectionDetailsQRW['section_name']).", ".htmlspecialchars($getorgDetailsQRW['organization_name']); ?></td>
                                <td><?php if($getApplicationTypeQRW['joiningType'] == 1){ echo "সঠিক সময়ে যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 2){ echo "অগ্রিম যোগদান"; }else if($getApplicationTypeQRW['joiningType'] == 3){ echo "বর্ধিত ছুটির আবেদন"; } ?></td>
                                <td>
                                    <?php echo banglaNumber(date_format($adateF,"d/m/Y")) .' হইতে '. banglaNumber(date_format($adateT,"d/m/Y")); ?>, <?php echo banglaNumber($adateDiff); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "সংগনিরোধ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 7){ echo "প্রসূতি ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 8){ echo "অক্ষমতাজনিত বিশেষ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 9){ echo "অধ্যয়ন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?>
                                </td>
                                <td><?php echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($leaveSpentDateTo,"d/m/Y")); ?>, <?php echo banglaNumber($leaveSpent); ?> দিন <?php if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 1){ echo "গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 6){ echo "সংগনিরোধ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 7){ echo "প্রসূতি ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 8){ echo "অক্ষমতাজনিত বিশেষ ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 9){ echo "অধ্যয়ন ছুটি"; }else if($getLeaveApplicationDetailsQRW['leaveTypeInTwo'] == 10){ echo "অসাধারণ ছুটি"; } ?></td>
                                <td><?php if($getApplicationTypeQRW['requestedJoiningDate'] != ''){ echo banglaNumber(date_format($leaveSpentDateFrom,"d/m/Y")) .' হইতে '. banglaNumber(date_format($correctionJoiningDate,"d/m/Y")); ?>, <?php echo banglaNumber($correctedLeaveSpent); ?> দিন <?php if($getApplicationTypeQRW['approvedLeaveType'] == 1){ echo "গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 2){ echo "অর্ধ-গড় বেতন"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 3){ echo "নৈমিত্তিক (Casual Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 4){ echo "বিনা বেতনে ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 5){ echo "ঐচ্ছিক (Optional Leave)"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 6){ echo "সংগনিরোধ ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 7){ echo "প্রসূতি ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 8){ echo "অক্ষমতাজনিত বিশেষ ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 9){ echo "অধ্যয়ন ছুটি"; }else if($getApplicationTypeQRW['approvedLeaveType'] == 10){ echo "অসাধারণ ছুটি"; } } ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a class="btn btn-sm btn-info" target="_blank" href="leave_office_notice?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                            <i class="ti tabler-file-description me-1"></i>ছুটির অফিস আদেশ
                                        </a>

                                        <?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>
                                            <a class="btn btn-sm btn-secondary" target="_blank" href="leave_joining_application_details?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                                <i class="ti tabler-file-check me-1"></i>যোগদান পত্র
                                            </a>
                                        <?php } else if($getApplicationTypeQRW['joiningType'] == 2){ ?>
                                            <a class="btn btn-sm btn-secondary" target="_blank" href="leave_joining_application_details_typetwo?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                                <i class="ti tabler-file-check me-1"></i>যোগদান পত্র
                                            </a>
                                        <?php } else if($getApplicationTypeQRW['joiningType'] == 3){ ?>
                                            <a class="btn btn-sm btn-secondary" target="_blank" href="leave_joining_application_details_typethree?menuslug=leave-joining-approval&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>">
                                                <i class="ti tabler-file-check me-1"></i>যোগদান পত্র
                                            </a>
                                        <?php } ?>

                                        <?php if($getApplicationTypeQRW['joiningType'] == 1){ ?>
                                            <a class="btn btn-sm btn-success" href="approve_leave_joining_application_typeone.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1">
                                                <i class="ti tabler-check me-1"></i>অনুমোদন
                                            </a>
                                        <?php } else{ ?>
                                            <a class="btn btn-sm btn-success" href="approve_leave_joining_application.php?menuslug=leave-joining-approval&dataID=<?php echo $empRow['dataID']; ?>&leaveApplicationID=<?php echo $empRow['leaveApplicationID']; ?>&isApproved=1">
                                                <i class="ti tabler-check me-1"></i>অনুমোদন
                                            </a>
                                        <?php } ?>
                                    </div>
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
        </div>
    </div>
</div>

<?php
include(__DIR__ . '/includes/footer_vuexy.php');
?>

<style>
.table thead th {
    color: #ffffff !important;
    background-color: #435971 !important;
}
</style>

<script type="text/javascript">
$(document).ready(function() {
    // Initialize DataTable for both tables
    $('.zero-configuration').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "সকল"]],
        language: {
            search: "খুঁজুন:",
            lengthMenu: "প্রদর্শন করুন _MENU_ টি এন্ট্রি",
            info: "প্রদর্শন করা হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ টি এন্ট্রি",
            infoEmpty: "কোন এন্ট্রি নেই",
            infoFiltered: "(মোট _MAX_ টি এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            zeroRecords: "কোন মিল খুঁজে পাওয়া যায়নি",
            emptyTable: "টেবিলে কোন ডেটা নেই",
            paginate: {
                first: "প্রথম",
                previous: "পূর্ববর্তী",
                next: "পরবর্তী",
                last: "শেষ"
            }
        }
    });
});

function removeData(sl, dataID) {
    Swal.fire({
        title: 'আপনি কি নিশ্চিত?',
        text: "এই ডেটা মুছে ফেলতে চান?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28c76f',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, মুছে ফেলুন!',
        cancelButtonText: 'বাতিল',
        customClass: {
            confirmButton: 'btn btn-success me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'post',
                url: 'delete_data.php',
                data: 'dataID=' + dataID + '&tableName=modules',
                success: function(data) {
                    $("#tr_" + sl).fadeOut(1000);

                    Swal.fire({
                        title: 'মুছে ফেলা হয়েছে!',
                        text: 'ডেটা সফলভাবে মুছে ফেলা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#28c76f',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    });
                },
                error: function(e) {
                    console.log(e);
                    Swal.fire({
                        title: 'ত্রুটি!',
                        text: 'ডেটা মুছতে ব্যর্থ হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}
</script>
