<?php
session_start();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display to browser
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once(__DIR__ . '/../../config/connection.php');
include(__DIR__ . '/../../bddate.php');
require_once(__DIR__ . '/../../library/employee_helpers.php');

// Debug: Log received POST data
error_log("POST data received: " . print_r($_POST, true));

$todayDate = todayDate();

// Get and sanitize form data
$employee_name = trim($_POST['employee_name'] ?? '');
$designation = (int)($_POST['designation'] ?? 0);
$employee_id = trim($_POST['employee_id'] ?? '');
$memorialNo = trim($_POST['memorialNo'] ?? '');
$employee_type = (int)($_POST['employee_type'] ?? 1);
$dob = !empty($_POST['dob']) ? date('Y-m-d', strtotime($_POST['dob'])) : '';
$joining_date = !empty($_POST['joining_date']) ? date('Y-m-d', strtotime($_POST['joining_date'])) : '';

// Lifecycle entry type (probationary/permanent) — defaults to permanent for back-compat
$entry_type = trim($_POST['entry_type'] ?? 'permanent');
if (!in_array($entry_type, ['probationary', 'permanent'], true)) $entry_type = 'permanent';
$probation_start_date = !empty($_POST['probation_start_date']) ? date('Y-m-d', strtotime($_POST['probation_start_date'])) : null;
$permanent_from_date  = !empty($_POST['permanent_from_date'])  ? date('Y-m-d', strtotime($_POST['permanent_from_date']))  : null;

if ($entry_type === 'probationary') {
    // Auto-generate temp ID; joining_date = probation_start_date for legacy compat
    $employee_id = bitac_next_probationary_id($con);
    if (empty($probation_start_date)) $probation_start_date = $joining_date ?: date('Y-m-d');
    $joining_date = $probation_start_date;
    $permanent_from_date = null;
} else {
    // Permanent: BITAC ID required (existing employee_id field); permanent_from_date = joining_date if not provided
    if (empty($permanent_from_date)) $permanent_from_date = $joining_date;
    $probation_start_date = null;
}
$email = trim($_POST['email'] ?? '');
$mobileNo = trim($_POST['mobileNo'] ?? '');
$organization_id = (int)($_POST['organization_id'] ?? 0);
$section_id = (int)($_POST['section_id'] ?? 0);
$pay_scale = (int)($_POST['pay_scale'] ?? 0);
$basic_salary = trim($_POST['basic_salary'] ?? '');
$salary_group_id = (int)($_POST['salary_group_id'] ?? 0);
$nid = trim($_POST['nid'] ?? '');
$display_order = (int)($_POST['display_order'] ?? 1);
$department_id = 1; // Default department
$createdBy = $_SESSION['userID'] ?? 0;

// Validate required fields (memorialNo + basic_salary not required for probationary)
$requireMemorial = ($entry_type === 'permanent');
$requireSalary   = ($entry_type === 'permanent');
$missing = [];
if (empty($employee_name))                   $missing[] = 'employee_name';
if (empty($employee_id))                     $missing[] = 'employee_id';
if ($requireMemorial && empty($memorialNo))  $missing[] = 'memorialNo';
if ($designation == 0)                       $missing[] = 'designation';
if ($organization_id == 0)                   $missing[] = 'organization_id';
if ($section_id == 0)                        $missing[] = 'section_id';
if ($pay_scale == 0)                         $missing[] = 'pay_scale';
if ($requireSalary && empty($basic_salary))  $missing[] = 'basic_salary';

if (!empty($missing)) {
    error_log("Validation failed - missing: " . implode(',', $missing));
    echo 0;
    exit;
}

// Handle photo upload
$file_name = "";
if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
    $file_size = $_FILES['photo']['size'];
    $file_tmp = $_FILES['photo']['tmp_name'];
    $file_type = $_FILES['photo']['type'];
    $file_extArray = explode('.', $_FILES['photo']['name']);
    $file_ext = strtolower(end($file_extArray));

    $extensions = array("jpeg", "jpg", "png");

    if (!in_array($file_ext, $extensions)) {
        echo 0;
        exit;
    }

    // Generate a unique numeric name for the file
    $newFileName = time() . rand(1, 1000) . '.' . $file_ext;
    $targetFile = __DIR__ . "/../../uploads/" . $newFileName;

    if ($file_size > 1048576) { // Check for 1MB size - resize if needed
        $source = null;
        if ($file_ext == 'jpeg' || $file_ext == 'jpg') {
            $source = imagecreatefromjpeg($file_tmp);
        } elseif ($file_ext == 'png') {
            $source = imagecreatefrompng($file_tmp);
        }

        if ($source) {
            // Get image dimensions
            list($width, $height) = getimagesize($file_tmp);

            // Calculate new dimensions
            $newWidth = 420;
            $ratio = $height / $width;
            $newHeight = $newWidth * $ratio;

            // Resize
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save resized image
            if ($file_ext == 'jpeg' || $file_ext == 'jpg') {
                imagejpeg($resizedImage, $targetFile, 85);
            } elseif ($file_ext == 'png') {
                imagepng($resizedImage, $targetFile);
            }

            // Free up memory
            imagedestroy($source);
            imagedestroy($resizedImage);
            $file_name = $newFileName;
        } else {
            echo 0;
            exit;
        }
    } else {
        // Move file if size is within limit
        if (move_uploaded_file($file_tmp, $targetFile)) {
            $file_name = $newFileName;
        } else {
            echo 0;
            exit;
        }
    }
}

// Insert employee data using prepared statement
$stmt = $con->prepare("INSERT INTO `employee_list`
    (`employee_name`, `designation`, `employee_id`, `employee_type`, `date_of_birth`, `joining_date`,
     `email`, `mobileNo`, `organization_id`, `department_id`, `section_id`, `pay_scale`, `basic_salary`,
     `salary_group_id`, `photo`, `nationalID`, `created_by`, `create_date`, `memorialNo`, `display_order`,
     `employment_status`, `employment_type`, `probation_start_date`, `permanent_from_date`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");

if ($stmt) {
    $stmt->bind_param(
        "sisissssiiiisisisssisss",
        $employee_name,
        $designation,
        $employee_id,
        $employee_type,
        $dob,
        $joining_date,
        $email,
        $mobileNo,
        $organization_id,
        $department_id,
        $section_id,
        $pay_scale,
        $basic_salary,
        $salary_group_id,
        $file_name,
        $nid,
        $createdBy,
        $todayDate,
        $memorialNo,
        $display_order,
        $entry_type,
        $probation_start_date,
        $permanent_from_date
    );

    if ($stmt->execute()) {
        $newID = $con->insert_id;
        $stmt->close();

        // Insert initial posting in transfer history
        $hStmt = $con->prepare("INSERT INTO employee_transfer_history
            (employee_ref_id, from_organization_id, to_organization_id, transfer_date, reason, createdBy)
            VALUES (?, NULL, ?, ?, 'Initial posting', ?)");
        if ($hStmt) {
            $initDate = $entry_type === 'probationary' ? $probation_start_date : ($permanent_from_date ?: $joining_date);
            $hStmt->bind_param('iisi', $newID, $organization_id, $initDate, $createdBy);
            @$hStmt->execute();
            $hStmt->close();
        }

        if (function_exists('audit_log')) {
            audit_log('employee_created', [
                'target_type'     => 'employee_list',
                'target_id'       => $newID,
                'organization_id' => (int)$organization_id,
                'note'            => "name=" . mb_substr((string)$employee_name, 0, 80)
                                   . "; empID=" . mb_substr((string)$employee_id, 0, 30)
                                   . "; type=$entry_type",
            ]);
        }
        echo 1;
    } else {
        error_log("Employee insert error: " . $stmt->error);
        $stmt->close();
        echo 0;
    }
} else {
    error_log("Prepared statement error: " . $con->error);
    echo 0;
}

mysqli_close($con);
?>
