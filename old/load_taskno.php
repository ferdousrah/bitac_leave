<?php
// Clean any output buffer before starting
ob_start();

session_start();

// Record start time for performance profiling
$start = microtime(true);

// Include connection file
include('connection.php');

// Log errors instead of displaying them (for clean JSON output)
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't show errors on the screen
ini_set('log_errors', 1);  // Log errors to file
ini_set('error_log', 'php_errors.log');


$slug1 = 'leave-approval';

$slug2 = 'allowed-leave-applications'; 

$slug3 = 'leave-joining-approval';

$slug4 = 'manage-approved-leaves'; 

$slug5 = 'leave-edit-approval';


// Get user information securely using prepared statements to prevent SQL injection
$stmt = $con->prepare("SELECT * FROM user_list WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$getUserInfoQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();

$getCurrentEmpDetailsQ = mysqli_query($con, "select * from `employee_list` where id='$getUserInfoQRW[employee_id]'");
$getCurrentEmpDetailsQRW = mysqli_fetch_assoc($getCurrentEmpDetailsQ);

$loggedUserOrgID = $getCurrentEmpDetailsQRW['organization_id'];

// Initialize variables for both counts
$totalApplicationsToSupervise = 0;
$totalApplicationsToApprove = 0;

// Prepare query for pending leave applications
$getLeaveApplications = $con->prepare("
    SELECT la.prevSignatory, la.serial, la.leaveApplicationID, la.isSupervisor, la.isSentbyAdmin, l.organization_id
    FROM leave_data_for_approval la INNER JOIN leave_applications l on la.leaveApplicationID=l.dataID
    WHERE la.signatory = ? AND la.isApproved = 0 AND l.organization_id=? 
    ORDER BY la.leaveApplicationID DESC
");

$getLeaveApplications->bind_param("ii", $getUserInfoQRW['employee_id'], $loggedUserOrgID);
$getLeaveApplications->execute();
$leaveApplicationsResult = $getLeaveApplications->get_result();

// Loop through all pending leave applications
while ($empRow = $leaveApplicationsResult->fetch_assoc()) {
    $proceed = false; // Default to not proceed

    // If no previous signatory, this is the first signatory (supervisor or admin)
    if ($empRow['prevSignatory'] == 0) {
        // If supervisor role, add to supervise count
        if ($empRow['isSupervisor'] == 1) {
            $totalApplicationsToSupervise++;
        }

        // If admin role, add to approve count
        if ($empRow['isSentbyAdmin'] == 1 && $empRow['isSupervisor'] != 1) {
            $totalApplicationsToApprove++;
        }
    } else {
        // Check if the previous signatory has approved
        $prevSerial = $empRow['serial'] - 1;

        $checkPrevSignatoryQuery = $con->prepare("
            SELECT 1
            FROM leave_data_for_approval
            WHERE leaveApplicationID = ? 
            AND signatory = ? 
            AND isApproved = 1 
            AND serial = ?
        ");

        $checkPrevSignatoryQuery->bind_param("sss", $empRow['leaveApplicationID'], $empRow['prevSignatory'], $prevSerial);
        $checkPrevSignatoryQuery->execute();
        $checkPrevSignatoryQuery->store_result();

        // If the previous signatory has approved, proceed to the next step
        if ($checkPrevSignatoryQuery->num_rows > 0) {
            // If supervisor role, add to supervise count
            if ($empRow['isSupervisor'] == 1) {
                $totalApplicationsToSupervise++;
            }

            // If admin role, add to approve count
            if ($empRow['isSentbyAdmin'] == 1 && $empRow['isSupervisor'] != 1) {
                $totalApplicationsToApprove++;
            }
        }
    }
}







// end of slug 1


// start of slug 3

$totalJoiningLeaveApprove = 0;
$totalLeaveModTask = 0;

$queries = [
    "SELECT la.prevSignatory, la.leaveApplicationID FROM `leave_joining_data_for_approval` la INNER JOIN leave_applications l on la.leaveApplicationID=l.dataID WHERE la.signatory = ? AND ((la.isSupervisor = 1 AND la.isApproved = 0) OR (la.isSentbyAdmin = 1 AND la.isSupervisor != 1 AND la.isApproved = 0)) AND l.organization_id=? ORDER BY la.leaveApplicationID DESC"
];

// Prepare and execute the query once, combining both supervisor and admin conditions
$stmt2 = $con->prepare($queries[0]);
$stmt2->bind_param("ii", $getUserInfoQRW['employee_id'], $loggedUserOrgID);
$stmt2->execute();
$result2 = $stmt2->get_result();

// Loop through both supervisor and admin pending applications
while ($empRow2 = $result2->fetch_assoc()) {
    // Check if it's the first approval or if the previous signatory has approved
    if ($empRow2['prevSignatory'] == 0) {
        // If it's the first approval
        $proceed2 = 1;
    } else {
        // Check if the previous signatory has approved
        $checkPrevSognatorySignedQ2 = $con->prepare("
            SELECT 1 FROM `leave_joining_data_for_approval` 
            WHERE leaveApplicationID = ? 
            AND signatory = ? 
            AND isApproved = 1
        ");
        $checkPrevSognatorySignedQ2->bind_param("ss", $empRow2['leaveApplicationID'], $empRow2['prevSignatory']);
        $checkPrevSognatorySignedQ2->execute();
        $checkPrevSognatorySignedQ2->store_result();
        
        // If the previous signatory has approved, proceed
        $proceed2 = ($checkPrevSognatorySignedQ2->num_rows > 0) ? 1 : 0;
    }

    // If we should proceed with the application
    if ($proceed2 == 1) {
        $totalJoiningLeaveApprove++;
        $totalLeaveModTask++; // Increment the task count
        $proceed2 = 0; // Reset for the next iteration
    }
}





// end of slug 3

// start of slug2


// Check if current user's group has access to "ছুটি সম্পাদনা" (Leave Edit)
$checkIfCurUserHaveAccOnChutiSompadonaQ = $con->prepare("
	SELECT COUNT(*) as total
	FROM group_access_permission
	WHERE user_group_id = ? AND module_id = 47 AND submodule_id = 149
");
$checkIfCurUserHaveAccOnChutiSompadonaQ->bind_param("i", $getUserInfoQRW['user_group_id']);
$checkIfCurUserHaveAccOnChutiSompadonaQ->execute();
$checkIfCurUserHaveAccOnChutiSompadonaQRW = $checkIfCurUserHaveAccOnChutiSompadonaQ->get_result()->fetch_assoc();
$checkIfCurUserHaveAccOnChutiSompadonaQ->close();

if($checkIfCurUserHaveAccOnChutiSompadonaQRW['total'] > 0){

	//$getAllAppApplicationQ = mysqli_query($con, "SELECT COUNT(*) AS total FROM `leave_data_for_approval` WHERE isSupervisor = 1 AND isApproved = 1 AND isSentbyAdmin = 0");
	$getAllAppApplicationQ = mysqli_query($con, "select employee_list.employee_name as applicant_name, employee_list.employee_id, employee_list.designation, employee_list.section_id, leave_data_for_approval.leaveApplicationID, leave_data_for_approval.isSentbyAdmin from `leave_data_for_approval` inner join leave_applications on leave_data_for_approval.leaveApplicationID=leave_applications.dataID INNER JOIN employee_list on leave_applications.applicantID=employee_list.id where leave_data_for_approval.isSupervisor=1 and leave_data_for_approval.isApproved=1 and leave_applications.organization_id='$loggedUserOrgID' AND leave_data_for_approval.isSentbyAdmin = 0");

	//$row = mysqli_fetch_assoc($getAllAppApplicationQ);
	$getAllAppApplicationQNumRows = mysqli_num_rows($getAllAppApplicationQ);

}else{

	$getAllAppApplicationQNumRows = 0;

}

// end of slug2

// start slug 4

// Check if current user's group has access to "যোগদানের সম্পাদনা" (Joining Edit)
$checkIfCurUserHaveAccOnJugdanerSompadonaQ = $con->prepare("
	SELECT COUNT(*) as total
	FROM group_access_permission
	WHERE user_group_id = ? AND module_id = 47 AND submodule_id = 150
");
$checkIfCurUserHaveAccOnJugdanerSompadonaQ->bind_param("i", $getUserInfoQRW['user_group_id']);
$checkIfCurUserHaveAccOnJugdanerSompadonaQ->execute();
$checkIfCurUserHaveAccOnJugdanerSompadonaQRW = $checkIfCurUserHaveAccOnJugdanerSompadonaQ->get_result()->fetch_assoc();
$checkIfCurUserHaveAccOnJugdanerSompadonaQ->close();

if($checkIfCurUserHaveAccOnJugdanerSompadonaQRW['total'] > 0){

	$getAllAppApplicationQ2 = mysqli_query($con, "
		SELECT COUNT(*) AS total 
		FROM leave_joining_data_for_approval lj
		LEFT JOIN leave_joining_application lja ON lj.leaveApplicationID = lja.leaveApplicationID
		WHERE lj.isSupervisor = 1 AND lj.isApproved = 1
		AND lj.isSentbyAdmin = 0
		AND lja.joiningType != 1
	");

	$row = mysqli_fetch_assoc($getAllAppApplicationQ2);
	$manageLeaveJoiningApp = $row['total'];
	$totalLeaveModTask = $manageLeaveJoiningApp;

}else{

	$manageLeaveJoiningApp = 0;

}

// end of slug 4

// start slug 5

// Check if current user's group has access to "ছুটি কর্তন অনুমোদন" (Leave Deduction Approval)
$checkIfCurUserHaveAccOnChutiKortonOnumodonQ = $con->prepare("
	SELECT COUNT(*) as total
	FROM group_access_permission
	WHERE user_group_id = ? AND module_id = 47 AND submodule_id = 154
");
$checkIfCurUserHaveAccOnChutiKortonOnumodonQ->bind_param("i", $getUserInfoQRW['user_group_id']);
$checkIfCurUserHaveAccOnChutiKortonOnumodonQ->execute();
$checkIfCurUserHaveAccOnChutiKortonOnumodonQRW = $checkIfCurUserHaveAccOnChutiKortonOnumodonQ->get_result()->fetch_assoc();
$checkIfCurUserHaveAccOnChutiKortonOnumodonQ->close();

if($checkIfCurUserHaveAccOnChutiKortonOnumodonQRW['total'] > 0){

$getTotalFromLeaveDeductHistoryQ = mysqli_query($con, "select count(*) as total from leave_deduction_history where isApproved=0");
$getTotalFromLeaveDeductHistoryQRW = mysqli_fetch_assoc($getTotalFromLeaveDeductHistoryQ);

$leaveDeductHis = $getTotalFromLeaveDeductHistoryQRW['total'];



}else{

$leaveDeductHis = 0;

}

// end of slug 5





$totalTask = $totalApplicationsToSupervise + $totalApplicationsToApprove + $totalJoiningLeaveApprove + $getAllAppApplicationQNumRows + $manageLeaveJoiningApp + $leaveDeductHis;

// Clean output buffer and set JSON headers
ob_clean();
header('Content-Type: application/json');

// Return results as a JSON response
echo json_encode([
    'totalApplicationsToSupervise' => $totalApplicationsToSupervise,
    'totalApplicationsToApprove' => $totalApplicationsToApprove,
	'totalJoiningLeaveApprove' => $totalJoiningLeaveApprove,
	'totalAllowedLeaveApp' => $getAllAppApplicationQNumRows,
	'totalApprovedLeaveApp' => $manageLeaveJoiningApp,
	'totalTask' => $totalTask,
	'totalLeaveDeductHis' => $leaveDeductHis,
]);

// Flush and end output buffering
ob_end_flush();



?>