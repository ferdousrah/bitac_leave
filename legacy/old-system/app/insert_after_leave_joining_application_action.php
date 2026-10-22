<?php
include('connection.php');
include('bddate.php');

$leaveApplicationID = $_POST['leaveApplicationID'];

$dateT = $_POST['spentLeaveTo'];

$dateTArray = explode('/', $dateT);

$joiningDate = $dateTArray[2].'-'.$dateTArray[1].'-'.$dateTArray[0];

$reason = $_POST['reason'];

$approvedLeaveType = $_POST['approvedLeaveType'];

$supervisorID = $_POST['supervisorID'];


// get leave application details

$getLeaveApplicationDetailsQ = mysqli_query($con, "select * from leave_applications where dataID='$leaveApplicationID'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);


$submitDate = todayDate();

$getUserDetailsQ = mysqli_query($con, "select * from `user_list` where employee_id='$getLeaveApplicationDetailsQRW[applicantID]'");
$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

$signature = $con -> real_escape_string($getUserDetailsQRW['signature']);

$insertApplicationQ = mysqli_query($con, "insert into `leave_joining_application`(`leaveApplicationID`, `joiningType`, `submitDate`, `requestedJoiningDate`, `applicantID`, `applicantSignature`, `reason`, `approvedLeaveType`) values('$leaveApplicationID', '3', '$submitDate', '$joiningDate', '$getLeaveApplicationDetailsQRW[applicantID]', '$signature', '$reason', '$approvedLeaveType')");


if($insertApplicationQ == 1){

	// insert sugnatory

		$prevSignatory = 0;

		$getSignatoryQ = mysqli_query($con, "SELECT * FROM `leave_approval_signatory` order by approvalSL asc");	

		// insert first approval

		$insertForApprovalQbySupervisor = mysqli_query($con, "insert into leave_joining_data_for_approval(leaveApplicationID, signatory, isSupervisor, prevSignatory, isApproved, serial) values('$leaveApplicationID', '$supervisorID','1', '$prevSignatory', '0', '1')");

		if($insertForApprovalQbySupervisor == 1){

			$prevSignatory = $supervisorID;

			// insert notification
			$getApplicantDetailsQ = mysqli_query($con, "select * from employee_list where id='$getLeaveApplicationDetailsQRW[applicantID]'");
			$getApplicantDetailsQRW = mysqli_fetch_assoc($getApplicantDetailsQ);

			$getDesignationDetailsQ = mysqli_query($con, "select * from job_title where id='$getApplicantDetailsQRW[designation]'");
			$getDesignationDetailsQRW = mysqli_fetch_assoc($getDesignationDetailsQ);

			$getSupervisorDetailsQ = mysqli_query($con, "select * from user_list where employee_id='$supervisorID'");
			$getSupervisorDetailsQNumRows = mysqli_num_rows($getSupervisorDetailsQ);

			if($getSupervisorDetailsQNumRows > 0){

				$getSupervisorDetailsQRW = mysqli_fetch_assoc($getSupervisorDetailsQ);

				$message = $getApplicantDetailsQRW['employee_name'].", ".$getDesignationDetailsQRW['job_title_name']." কর্মস্থলে যোগদানের আবেদন করেছেন ।";

				$type= "<span class='badge badge-primary'>কর্মস্থলে যোগদান পত্র</span>";

				$escapedType = mysqli_real_escape_string($con, $type);

				$link = "leave_joining_application_details?menuslug=leave-joining-approval&leaveApplicationID=".$leaveApplicationID; // ned to change here

				$dateTime = ShowBangladeshTime();

				$insertNotiQuery = mysqli_query($con, "insert into notification(userID, message, notificationType, link, dateTime, isImportant) values('$getSupervisorDetailsQRW[dataID]', '$message', '$escapedType', '$link', '$dateTime', 1)");
			
			} // end of notification

		} // end of if statement

		while($sigRow = mysqli_fetch_array($getSignatoryQ)){


			$insertForApprovalQ = mysqli_query($con, "insert into leave_joining_data_for_approval(leaveApplicationID, signatory, prevSignatory, isApproved, serial) values('$leaveApplicationID', '$sigRow[employeeID]', '$prevSignatory', '0', '$sigRow[approvalSL]')");

			$prevSignatory = $sigRow['employeeID'];

		}

		echo "<div class='alert alert-success'><strong>Success!</strong> আপনার ছুটি থেকে যোগদানের আবেদনটি অনুমোদনের জন্য যথাযথ কর্তৃপক্ষের কাছে প্রেরণ করা হয়েছে ।</div>";

	// end of insert signatory



}else{


	echo 0;

}



?>