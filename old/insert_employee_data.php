<?php
session_start();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display to browser
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

include('connection.php');
include('bddate.php');

// Debug: Log received POST data
error_log("POST data received: " . print_r($_POST, true));

$todayDate = todayDate();

// Get and sanitize form data
$employee_name = trim($_POST['employee_name'] ?? '');
$designation = (int)($_POST['designation'] ?? 0);
$employee_id = trim($_POST['employee_id'] ?? '');
$memorialNo = trim($_POST['memorialNo'] ?? '');
$employee_type = (int)($_POST['employee_type'] ?? 1);
$dob = $_POST['dob'] ?? '';
$joining_date = $_POST['joining_date'] ?? '';
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

// Validate required fields
if (empty($employee_name) || empty($employee_id) || empty($memorialNo) || $designation == 0 ||
    $organization_id == 0 || $section_id == 0 || $pay_scale == 0 || empty($basic_salary)) {
    // Debug: Log which fields are failing validation
    error_log("Validation failed - employee_name: '$employee_name', employee_id: '$employee_id', memorialNo: '$memorialNo', designation: $designation, organization_id: $organization_id, section_id: $section_id, pay_scale: $pay_scale, basic_salary: '$basic_salary'");
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
    $targetFile = "uploads/" . $newFileName;

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
     `salary_group_id`, `photo`, `nationalID`, `created_by`, `create_date`, `memorialNo`, `display_order`, `employment_status`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

if ($stmt) {
    // Type string: s=string, i=integer
    // 1.employee_name(s) 2.designation(i) 3.employee_id(s) 4.employee_type(i) 5.dob(s) 6.joining_date(s)
    // 7.email(s) 8.mobileNo(s) 9.organization_id(i) 10.department_id(i) 11.section_id(i) 12.pay_scale(i)
    // 13.basic_salary(s) 14.salary_group_id(i) 15.photo(s) 16.nid(s) 17.createdBy(i) 18.todayDate(s)
    // 19.memorialNo(s) 20.display_order(i)
    $stmt->bind_param(
        "sisissssiiiisisisssi",
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
        $display_order
    );

    if ($stmt->execute()) {
        echo 1;
    } else {
        // Log error but don't expose it
        error_log("Employee insert error: " . $stmt->error);
        echo 0;
    }

    $stmt->close();
} else {
    error_log("Prepared statement error: " . $con->error);
    echo 0;
}

mysqli_close($con);
?>
