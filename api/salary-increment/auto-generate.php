<?php
// Start session first
session_start();

// Set JSON header
header('Content-Type: application/json');

// Start output buffering
ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../bddate.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate inputs
if (!isset($_POST['incrementYear']) || empty($_POST['incrementYear'])) {
    echo json_encode(['status' => 0, 'message' => 'বেতন বৃদ্ধির বছর নির্বাচন করুন!']);
    exit;
}

if (!isset($_POST['employeeID'])) {
    echo json_encode(['status' => 0, 'message' => 'কর্মচারী নির্বাচন করুন!']);
    exit;
}

$todayDateTime = ShowBangladeshTime();
$incrementYear = intval($_POST['incrementYear']);
$employeeID = intval($_POST['employeeID']);
$generateBy = 1;

// Start transaction
mysqli_begin_transaction($con);

try {
    // Delete existing records based on selection
    if ($employeeID == 0) {
        // Delete all for this year
        $deleteQuery = "DELETE FROM yearly_salary_increment WHERE incrementYear = ?";
        $deleteStmt = mysqli_prepare($con, $deleteQuery);
        mysqli_stmt_bind_param($deleteStmt, "i", $incrementYear);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        // Get all active employees
        $getAllEmployeeQ = mysqli_query($con, "SELECT * FROM employee_list WHERE employment_status=1 OR employment_status=2");
    } else {
        // Delete only for specific employee
        $deleteQuery = "DELETE FROM yearly_salary_increment WHERE employeeID = ? AND incrementYear = ?";
        $deleteStmt = mysqli_prepare($con, $deleteQuery);
        mysqli_stmt_bind_param($deleteStmt, "ii", $employeeID, $incrementYear);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        // Get specific employee
        $getAllEmployeeQ = mysqli_query($con, "SELECT * FROM employee_list WHERE id = " . $employeeID);
    }

    if (!$getAllEmployeeQ || mysqli_num_rows($getAllEmployeeQ) == 0) {
        throw new Exception('কোন কর্মচারী পাওয়া যায়নি!');
    }

    // Get increment settings
    $getIncrementSettings = mysqli_query($con, "SELECT * FROM increment_settings WHERE dataID=1");
    $getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

    $successCount = 0;

    while ($empRow = mysqli_fetch_array($getAllEmployeeQ)) {
        $currentBasic = floatval($empRow['basic_salary']);
        $pay_scale = $empRow['pay_scale'];

        // Get current pay scale distribution
        $getIncremntSalaryQ = mysqli_query($con, "SELECT * FROM payscale_distribution WHERE pay_scale='" . mysqli_real_escape_string($con, $pay_scale) . "' AND basic_salary='" . $currentBasic . "'");
        $getIncremntSalaryQRW = mysqli_fetch_assoc($getIncremntSalaryQ);

        $incrementSalary = 0;
        $newPayscaleID = 0;
        $incrementAmount = 0;

        if ($getIncremntSalaryQRW) {
            // Get linked row for next increment
            $getLinkedRowQ = mysqli_query($con, "SELECT * FROM payscale_distribution WHERE linkedWith='" . intval($getIncremntSalaryQRW['id']) . "'");
            $getLinkedRowQRW = mysqli_fetch_assoc($getLinkedRowQ);

            if ($getLinkedRowQRW) {
                $incrementSalary = floatval($getLinkedRowQRW['basic_salary']);
                $newPayscaleID = $getLinkedRowQRW['pay_scale'];
                $incrementAmount = $incrementSalary - $currentBasic;

                if ($incrementAmount < 0) {
                    $incrementAmount = 0;
                }
            }
        }

        // Insert increment record
        $insertQuery = "INSERT INTO yearly_salary_increment (
            incrementYear, employeeID, designation, section_id, organization_id,
            display_order, presentSalaryGrade, presentSalary, incrementSalaryGrade,
            incrementAmount, incrementSalary, genDateTime, salary_group_id,
            generateBy, salary_increment_date, fileNo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertStmt = mysqli_prepare($con, $insertQuery);

        if (!$insertStmt) {
            throw new Exception('ডাটাবেস ত্রুটি!');
        }

        $salaryIncrementDate = $getIncrementSettingsRW['salary_increment_date'] ?? '';
        $fileNo = $getIncrementSettingsRW['fileNo'] ?? '';

        mysqli_stmt_bind_param($insertStmt, "iiiiiisdiddsisss",
            $incrementYear,
            $empRow['id'],
            $empRow['designation'],
            $empRow['section_id'],
            $empRow['organization_id'],
            $empRow['display_order'],
            $pay_scale,
            $currentBasic,
            $newPayscaleID,
            $incrementAmount,
            $incrementSalary,
            $todayDateTime,
            $empRow['salary_group_id'],
            $generateBy,
            $salaryIncrementDate,
            $fileNo
        );

        if (mysqli_stmt_execute($insertStmt)) {
            $successCount++;
        }

        mysqli_stmt_close($insertStmt);
    }

    // Commit transaction
    mysqli_commit($con);

    if ($successCount > 0) {
        echo json_encode([
            'status' => 1,
            'message' => 'বেতন বৃদ্ধি সফলভাবে তৈরি করা হয়েছে! মোট ' . $successCount . ' জন কর্মচারীর জন্য।'
        ]);
    } else {
        echo json_encode(['status' => 0, 'message' => 'কোন রেকর্ড তৈরি করা যায়নি!']);
    }

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
}

mysqli_close($con);
?>
