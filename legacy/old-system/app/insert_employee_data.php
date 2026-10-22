<?php
session_start();
error_reporting(0);
include('connection.php');
include('bddate.php');

$todayDate = todayDate();

$employee_name = $_POST['employee_name'];

$designation = $_POST['designation'];

$employee_id = $_POST['employee_id'];

$memorialNo = $_POST['memorialNo'];

$employee_type = $_POST['employee_type'];

$dob = $_POST['dob'];

$joining_date = $_POST['joining_date'];

$email = $_POST['email'];

$mobileNo = $_POST['mobileNo'];

$organization_id = $_POST['organization_id'];

$department_id = 1;

$section_id = $_POST['section_id'];

$pay_scale = $_POST['pay_scale'];

$basic_salary = $_POST['basic_salary'];

$salary_group_id = $_POST['salary_group_id'];

$nid = $_POST['nid'];




// end of receipt

if (!file_exists($_FILES['photo']['tmp_name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $file_name = "";
} else {
    $file_size = $_FILES['photo']['size'];
    $file_tmp = $_FILES['photo']['tmp_name'];
    $file_type = $_FILES['photo']['type'];
    $file_extArray = explode('.', $_FILES['photo']['name']);
    $file_ext = strtolower(end($file_extArray));

    $extensions = array("jpeg", "jpg", "png", "JPG", "JPEG");
    
    if (in_array($file_ext, $extensions) === false) {
        $errors[] = "Extension not allowed, please choose a JPEG or PNG file.";
    }

    // Generate a unique numeric name for the file
    $newFileName = time() . rand(1, 1000) . '.' . $file_ext;
    $targetFile = "uploads/" . $newFileName;

    if ($file_size > 1048576) { // Check for 1MB size
        // Resize image
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
            $newWidth = 420; // Adjust width as needed
            $ratio = $height / $width;
            $newHeight = $newWidth * $ratio;

            // Resize
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save resized image
            if ($file_ext == 'jpeg' || $file_ext == 'jpg') {
                imagejpeg($resizedImage, $targetFile);
            } elseif ($file_ext == 'png') {
                imagepng($resizedImage, $targetFile);
            }

            // Free up memory
            imagedestroy($resizedImage);
        } else {
            $errors[] = 'Unable to resize image';
        }
    } else {
        // Move file if size is within limit
        move_uploaded_file($file_tmp, $targetFile);
    }

    if (empty($errors) == true) {
        $file_name = $newFileName;
        //echo "Success";
    } else {
        print_r($errors);
        $file_name = "";
    }
}

$createdBy = $_SESSION['userID'];


$insertEmployeeInfoQ = mysqli_query($con, "insert into `employee_list`(`employee_name`, `designation`, `employee_id`, `employee_type`, `date_of_birth`, `joining_date`, `email`, `mobileNo`, `organization_id`, `department_id`, `section_id`, `pay_scale`, `basic_salary`, `salary_group_id`, `photo`, `nationalID`, `created_by`, `create_date`, `memorialNo`) values('$employee_name', '$designation', '$employee_id', '$employee_type', '$dob', '$joining_date', '$email', '$mobileNo', '$organization_id', '$department_id', '$section_id', '$pay_scale', '$basic_salary', '$salary_group_id', '$file_name', '$nid', '$createdBy', '$todayDate', '$memorialNo')");


if($insertEmployeeInfoQ == 1) {

	echo 1;

} else {

echo 0;

}









?>