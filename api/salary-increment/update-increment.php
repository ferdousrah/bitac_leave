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
if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate inputs
if (!isset($_POST['employeeID']) || empty($_POST['employeeID'])) {
    echo json_encode(['status' => 0, 'message' => 'কর্মচারী আইডি প্রয়োজন!']);
    exit;
}

if (!isset($_POST['incrementAmount']) || !isset($_POST['incrementSalary'])) {
    echo json_encode(['status' => 0, 'message' => 'বেতন বৃদ্ধির তথ্য প্রয়োজন!']);
    exit;
}

if (!isset($_POST['note']) || empty(trim($_POST['note']))) {
    echo json_encode(['status' => 0, 'message' => 'মন্তব্য প্রয়োজন!']);
    exit;
}

$createdBy = intval($_SESSION['userID']);
$submitDate = todayDate();
$incrementYear = date('Y');

$employeeID = intval($_POST['employeeID']);
$incrementSalaryGrade = mysqli_real_escape_string($con, $_POST['incrementSalaryGrade'] ?? '');
$salary_increment_rate_current = floatval($_POST['salary_increment_rate_current'] ?? 0);
$salary_increment_basic_current = floatval($_POST['salary_increment_basic_current'] ?? 0);
$incrementAmount = floatval($_POST['incrementAmount']);
$incrementSalary = floatval($_POST['incrementSalary']);
$note = mysqli_real_escape_string($con, trim($_POST['note']));

// Handle file upload
$file_name = '';
$prevFile = isset($_POST['prevFile']) ? $_POST['prevFile'] : '';

if (!empty($_FILES['office_notice']['tmp_name']) && is_uploaded_file($_FILES['office_notice']['tmp_name'])) {
    $file_ext_array = explode('.', $_FILES['office_notice']['name']);
    $file_ext = strtolower(end($file_ext_array));

    $allowed_extensions = array("jpeg", "jpg", "png", "pdf");

    if (!in_array($file_ext, $allowed_extensions)) {
        echo json_encode(['status' => 0, 'message' => 'শুধুমাত্র JPEG, JPG, PNG, PDF ফাইল আপলোড করা যাবে!']);
        exit;
    }

    if ($_FILES['office_notice']['size'] > 2097152) {
        echo json_encode(['status' => 0, 'message' => 'ফাইলের সাইজ ২ MB এর বেশি হতে পারবে না!']);
        exit;
    }

    // Generate unique filename
    $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $_FILES['office_notice']['name']);
    $upload_path = __DIR__ . '/../../uploads/' . $file_name;

    if (!move_uploaded_file($_FILES['office_notice']['tmp_name'], $upload_path)) {
        echo json_encode(['status' => 0, 'message' => 'ফাইল আপলোড করতে ব্যর্থ হয়েছে!']);
        exit;
    }
} else {
    $file_name = $prevFile;
}

// Start transaction
mysqli_begin_transaction($con);

try {
    // Check for existing request
    $checkQuery = "SELECT * FROM increment_data_update_permission WHERE incrementYear = ? AND employeeID = ?";
    $checkStmt = mysqli_prepare($con, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "si", $incrementYear, $employeeID);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $existingRequest = mysqli_num_rows($checkResult) > 0;
    mysqli_stmt_close($checkStmt);

    if ($existingRequest) {
        // Update existing request
        $updateQuery = "UPDATE increment_data_update_permission SET
            salary_increment_rate_current = ?,
            salary_increment_rate_edited = ?,
            salary_increment_basic_current = ?,
            salary_increment_basic_edited = ?,
            submitBy = ?,
            submitDate = ?,
            note = ?,
            office_notice = ?
            WHERE incrementYear = ? AND employeeID = ?";

        $updateStmt = mysqli_prepare($con, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "ddddissssi",
            $salary_increment_rate_current,
            $incrementAmount,
            $salary_increment_basic_current,
            $incrementSalary,
            $createdBy,
            $submitDate,
            $note,
            $file_name,
            $incrementYear,
            $employeeID
        );

        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception('ডেটা হালনাগাদ করতে ব্যর্থ হয়েছে!');
        }
        mysqli_stmt_close($updateStmt);

    } else {
        // Insert new request
        $insertQuery = "INSERT INTO increment_data_update_permission
            (incrementYear, employeeID, salary_increment_rate_current, salary_increment_rate_edited,
             salary_increment_basic_current, salary_increment_basic_edited, submitBy, submitDate, note, office_notice)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertStmt = mysqli_prepare($con, $insertQuery);
        mysqli_stmt_bind_param($insertStmt, "siddddisss",
            $incrementYear,
            $employeeID,
            $salary_increment_rate_current,
            $incrementAmount,
            $salary_increment_basic_current,
            $incrementSalary,
            $createdBy,
            $submitDate,
            $note,
            $file_name
        );

        if (!mysqli_stmt_execute($insertStmt)) {
            throw new Exception('ডেটা সংরক্ষণ করতে ব্যর্থ হয়েছে!');
        }
        mysqli_stmt_close($insertStmt);

        // Get users who have approval permission for module 37
        $getUsersQuery = mysqli_query($con, "SELECT * FROM access_permission WHERE module_id='37'");

        // Get employee details for notification
        $getEmployeeQ = mysqli_query($con, "SELECT e.*, j.job_title_name FROM employee_list e
            LEFT JOIN job_title j ON e.designation = j.id
            WHERE e.id = '" . $employeeID . "'");
        $empDetails = mysqli_fetch_assoc($getEmployeeQ);

        if ($empDetails) {
            $message = "বেতন বৃদ্ধির তথ্য সংশোধিত, " . $empDetails['employee_name'] . ', ' . ($empDetails['job_title_name'] ?? '');
            $link = "views/salary-increment/approve-changes.php?menuslug=approve-increment-data-changes";
            $dateTime = ShowBangladeshTime();

            // Insert notifications
            while ($uRow = mysqli_fetch_array($getUsersQuery)) {
                $notifyQuery = "INSERT INTO notification (userID, message, link, dateTime) VALUES (?, ?, ?, ?)";
                $notifyStmt = mysqli_prepare($con, $notifyQuery);
                mysqli_stmt_bind_param($notifyStmt, "isss", $uRow['user_id'], $message, $link, $dateTime);
                mysqli_stmt_execute($notifyStmt);
                mysqli_stmt_close($notifyStmt);
            }
        }
    }

    // Commit transaction
    mysqli_commit($con);

    if (function_exists('audit_log')) {
        audit_log('increment_updated', [
            'target_type' => 'increment_data_update_permission',
            'target_id'   => (int)$employeeID,
            'note'        => "year=$incrementYear",
        ]);
    }

    echo json_encode([
        'status' => 1,
        'message' => 'ডেটা সফলভাবে সংরক্ষিত হয়েছে!'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);

    // Delete uploaded file if exists
    if (!empty($file_name) && $file_name != $prevFile && file_exists(__DIR__ . '/../../uploads/' . $file_name)) {
        unlink(__DIR__ . '/../../uploads/' . $file_name);
    }

    echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
}

mysqli_close($con);
?>
