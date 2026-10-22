<?php
session_start();
include('connection.php');
include('library/number_converter.php');
include('function.php');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo 0;
    exit;
}

// Get POST data
$incrementYear = isset($_POST['incrementYear']) ? mysqli_real_escape_string($con, $_POST['incrementYear']) : date('Y');
$certificateDate = isset($_POST['certificateDate']) ? mysqli_real_escape_string($con, $_POST['certificateDate']) : '';
$noticeDate = isset($_POST['noticeDate']) ? mysqli_real_escape_string($con, $_POST['noticeDate']) : '';
$employeeID = isset($_POST['employeeID']) ? mysqli_real_escape_string($con, $_POST['employeeID']) : '';
$signatoryID = isset($_POST['signatoryID']) ? mysqli_real_escape_string($con, $_POST['signatoryID']) : '';
$copyToArray = isset($_POST['copyTo']) ? $_POST['copyTo'] : array();
$serialArray = isset($_POST['serial']) ? $_POST['serial'] : array();

// Validate required fields
if (empty($certificateDate) || empty($noticeDate) || $employeeID === '' || empty($signatoryID)) {
    echo 0;
    exit;
}

// Convert certificateDate from dd/mm/yyyy to Y-m-d
$dateObj = DateTime::createFromFormat('d/m/Y', $certificateDate);
if ($dateObj === false) {
    echo 0;
    exit;
}
$certificateDateFormatted = $dateObj->format('Y-m-d');


// Convert noticeDate from dd/mm/yyyy to Y-m-d
$dateObjtwo = DateTime::createFromFormat('d/m/Y', $noticeDate);
if ($dateObjtwo === false) {
    echo 0;
    exit;
}
$noticeDateFormatted = $dateObjtwo->format('Y-m-d');

// Get signatory employee details
$getSignatoryQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$signatoryID'");
$signatoryDetails = mysqli_fetch_assoc($getSignatoryQ);

$signatory = $signatoryID;
$signatoryDesignation = $signatoryDetails['designation'];
$signatoryCenterID = $signatoryDetails['organization_id'];
$creationDate = date('Y-m-d H:i:s');

// Casual leave year range
$casualStart = $incrementYear . '-01-01';
$casualEnd = $incrementYear . '-12-31';

// Function to calculate leave for an employee
function calculateEmployeeLeave($con, $empID, $todayDate, $casualStart, $casualEnd) {
    
    $getEmployeeQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$empID'");
    $empInfo = mysqli_fetch_assoc($getEmployeeQ);
    
    $getPrevLeaveHistory = mysqli_query($con, "SELECT * FROM previous_leave_deduction WHERE employeeID='$empID' AND isApproved=1");
    $prevLeaveHistory = mysqli_fetch_assoc($getPrevLeaveHistory);
    
    $calculateFullAvgQ = mysqli_query($con, "SELECT SUM(leaveDeduction) as totalFullLDeduction FROM leave_deduction_history WHERE employeeID='$empID' AND leaveID='1' AND isApproved=1");
    $fullAvgDeduction = mysqli_fetch_assoc($calculateFullAvgQ);
    
    $calculateHalfAvgQ = mysqli_query($con, "SELECT SUM(leaveDeduction) as totalLHalfDeduction FROM leave_deduction_history WHERE employeeID='$empID' AND leaveID='2' AND isApproved=1");
    $halfAvgDeduction = mysqli_fetch_assoc($calculateHalfAvgQ);
    
    $calculateWPAvgQ = mysqli_query($con, "SELECT SUM(leaveDeduction) as totalWPDeduction FROM leave_deduction_history WHERE employeeID='$empID' AND leaveID='4' AND isApproved=1");
    $wpDeduction = mysqli_fetch_assoc($calculateWPAvgQ);
    
    $calculateExOrLManualQ = mysqli_query($con, "SELECT SUM(leaveDeduction) as totalExODeduction FROM leave_deduction_history WHERE employeeID='$empID' AND leaveID='10' AND isApproved=1");
    $exOrDeduction = mysqli_fetch_assoc($calculateExOrLManualQ);
    
    $getTotalLWioutPayQ = mysqli_query($con, "SELECT SUM(approvedDays) as totalLWithoutPay FROM leave_applications WHERE status=1 AND applicantID='$empID' AND leaveTypeInTwo='4'");
    $totalWithoutPayApps = mysqli_fetch_assoc($getTotalLWioutPayQ);
    
    $getTotalExtraOrdinaryQ = mysqli_query($con, "SELECT SUM(approvedDays) as totalExorLeave FROM leave_applications WHERE status=1 AND applicantID='$empID' AND leaveTypeInTwo='10'");
    $totalExtraOrdinary = mysqli_fetch_assoc($getTotalExtraOrdinaryQ);
    
    $totalWithoutPay = ($totalWithoutPayApps['totalLWithoutPay'] + $prevLeaveHistory['leaveWithoutPay'] + $wpDeduction['totalWPDeduction']);
    
    $totalExtraOrdinaryLeave = ($totalExtraOrdinary['totalExorLeave'] + $prevLeaveHistory['extraOrdinaryLeave'] + $exOrDeduction['totalExODeduction']);
    
    $diff = abs(strtotime($todayDate) - strtotime($empInfo['joining_date']));
    $days = round($diff / (60 * 60 * 24));
    
    $days = $days - ($totalWithoutPayApps['totalLWithoutPay'] + $prevLeaveHistory['leaveWithoutPay'] + $wpDeduction['totalWPDeduction'] + $totalExtraOrdinary['totalExorLeave'] + $prevLeaveHistory['extraOrdinaryLeave'] + $exOrDeduction['totalExODeduction']);
    
    // Full average salary leave
    $fullAvgSalLeave = floor($days / 11);
    if (fmod($days, 11) >= 6) {
        $fullAvgSalLeave++;
    }
    
    $getTotalFullAvgUsedQ = mysqli_query($con, "SELECT SUM(approvedDays) as totalAvgSal FROM leave_applications WHERE status=1 AND applicantID='$empID' AND leaveTypeInTwo='1'");
    $totalFullAvgUsed = mysqli_fetch_assoc($getTotalFullAvgUsedQ);
    
    $totalAvgSalVugkrito = $prevLeaveHistory['avgSalary'] + $totalFullAvgUsed['totalAvgSal'] + $fullAvgDeduction['totalFullLDeduction'];
    $restAvgSalLeave = $fullAvgSalLeave - $totalAvgSalVugkrito;
    
    // Half average salary leave
    $halfAvgSalLeave = floor($days / 12);
    if (fmod($days, 12) >= 6) {
        $halfAvgSalLeave++;
    }
    
    $getTotalHalfAvgUsedQ = mysqli_query($con, "SELECT SUM(approvedDays)*2 as totalHalfAvgSal FROM leave_applications WHERE status=1 AND applicantID='$empID' AND leaveTypeInTwo='2'");
    $totalHalfAvgUsed = mysqli_fetch_assoc($getTotalHalfAvgUsedQ);
    
    $totalHalfAvgSalVugkrito = $prevLeaveHistory['halfAvgSalary'] + $totalHalfAvgUsed['totalHalfAvgSal'] + $halfAvgDeduction['totalLHalfDeduction'];
    $restHalfAvgSalLeave = $halfAvgSalLeave - $totalHalfAvgSalVugkrito;
    
    return array(
        'fullAvgSalaryInDays' => $restAvgSalLeave,
        'halfSalaryInDays' => $restHalfAvgSalLeave,
        'withoutSalaryInDays' => $totalWithoutPay
    );
}

// Function to generate certificate
function generateCertificate($con, $empID, $incrementYear, $certificateDateFormatted, $noticeDateFormatted, $signatory, $signatoryDesignation, $signatoryCenterID, $creationDate, $copyToArray, $casualStart, $casualEnd) {
    
    $getEmpQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$empID'");
    $empInfo = mysqli_fetch_assoc($getEmpQ);
    
    $designation = $empInfo['designation'];
    $centerID = $empInfo['organization_id'];
    $memorialNo = $empInfo['memorialNo'];
    
    $leaveData = calculateEmployeeLeave($con, $empID, $certificateDateFormatted, $noticeDateFormatted, $casualStart, $casualEnd);
    
    $checkExistQ = mysqli_query($con, "SELECT leaveSummaryID FROM yearly_leave_summary WHERE employeeID='$empID' AND year='$incrementYear'");
    
    if (mysqli_num_rows($checkExistQ) > 0) {
        $existingRow = mysqli_fetch_assoc($checkExistQ);
        $leaveSummaryID = $existingRow['leaveSummaryID'];
        
        $updateQ = mysqli_query($con, "UPDATE yearly_leave_summary SET 
            designation = '$designation',
            centerID = '$centerID',
            memorial_number = '$memorialNo',
            date = '$certificateDateFormatted',
			noticeDate = '$noticeDateFormatted',
            fullHalfSalaryInDays = '{$leaveData['fullAvgSalaryInDays']}',
            HalfSalaryInDays = '{$leaveData['halfSalaryInDays']}',
            withoutSalaryInDays = '{$leaveData['withoutSalaryInDays']}',
            signatory = '$signatory',
            signatoryDesignation = '$signatoryDesignation',
            signatoryCenterID = '$signatoryCenterID'
            WHERE leaveSummaryID = '$leaveSummaryID'");
        
        if (!$updateQ) {
            return false;
        }
        
        mysqli_query($con, "DELETE FROM leaveSummary_copy WHERE leaveSummaryID='$leaveSummaryID'");
        
    } else {
        $insertQ = mysqli_query($con, "INSERT INTO yearly_leave_summary 
            (employeeID, designation, centerID, memorial_number, date, noticeDate, year, fullHalfSalaryInDays, HalfSalaryInDays, withoutSalaryInDays, creationDate, signatory, signatoryDesignation, signatoryCenterID) 
            VALUES 
            ('$empID', '$designation', '$centerID', '$memorialNo', '$certificateDateFormatted', '$noticeDateFormatted', '$incrementYear', '{$leaveData['fullAvgSalaryInDays']}', '{$leaveData['halfSalaryInDays']}', '{$leaveData['withoutSalaryInDays']}', '$creationDate', '$signatory', '$signatoryDesignation', '$signatoryCenterID')");
        
        if (!$insertQ) {
            return false;
        }
        
        $leaveSummaryID = mysqli_insert_id($con);
    }
    
    // Insert copy-to records
    if (!empty($copyToArray) && array_filter($copyToArray)) {
        foreach ($copyToArray as $copyToEmpID) {
            if (!empty($copyToEmpID)) {
                $getCopyToQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id='$copyToEmpID'");
                $copyToInfo = mysqli_fetch_assoc($getCopyToQ);
                
                mysqli_query($con, "INSERT INTO leaveSummary_copy 
                    (leaveSummaryID, copyTo, designation, centerID) 
                    VALUES 
                    ('$leaveSummaryID', '$copyToEmpID', '{$copyToInfo['designation']}', '{$copyToInfo['organization_id']}')");
            }
        }
    }
    
    return true;
}

try {
    if ($employeeID == '0') {
        $getAllEmployeeQ = mysqli_query($con, "SELECT id FROM employee_list WHERE employment_status=1 OR employment_status=2");
        $successCount = 0;
        
        while ($empRow = mysqli_fetch_array($getAllEmployeeQ)) {
            if (generateCertificate($con, $empRow['id'], $incrementYear, $certificateDateFormatted, $noticeDateFormatted, $signatory, $signatoryDesignation, $signatoryCenterID, $creationDate, $copyToArray, $casualStart, $casualEnd)) {
                $successCount++;
            }
        }
        
        echo ($successCount > 0) ? 1 : 0;
    } else {
        echo generateCertificate($con, $employeeID, $incrementYear, $certificateDateFormatted, $noticeDateFormatted, $signatory, $signatoryDesignation, $signatoryCenterID, $creationDate, $copyToArray, $casualStart, $casualEnd) ? 1 : 0;
    }
} catch (Exception $e) {
    echo 0;
}

mysqli_close($con);
?>