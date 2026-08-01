<?php
session_start();
error_reporting(0);
require_once(__DIR__ . '/../../config/connection.php');
include(__DIR__ . '/../../bddate.php');

$todayDate = todayDate();

$dataID = $_POST['dataID'];

$prevPhoto = $_POST['prevPhoto'];

$employee_name = $_POST['employee_name'];

$designation = $_POST['designation'];

$employee_id = $_POST['employee_id'];

$memorialNo = $_POST['memorialNo'];

$employee_type = $_POST['employee_type'];

$dob = !empty($_POST['dob']) ? date('Y-m-d', strtotime($_POST['dob'])) : '';

$joining_date = !empty($_POST['joining_date']) ? date('Y-m-d', strtotime($_POST['joining_date'])) : '';

$email = $_POST['email'];

$mobileNo = $_POST['mobileNo'];

//$organization_id = $_POST['organization_id'];

$organization_id = $_POST['organization_id'];

//$department_id = $_POST['department_id'];

$department_id = 1;

$section_id = $_POST['section_id'];

$pay_scale = $_POST['pay_scale'];

$basic_salary = $_POST['basic_salary'];

$salary_group_id = $_POST['salary_group_id'];

$employment_status = $_POST['employment_status'];

$nid = $_POST['nid'];

$display_order = $_POST['display_order'];




// end of receipt

if (!file_exists($_FILES['photo']['tmp_name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $file_name = $prevPhoto;
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
        $file_name = $prevPhoto;
    }
}


$createdBy = $_SESSION['userID'];



// signature

if (!file_exists($_FILES['signature']['tmp_name']) || !is_uploaded_file($_FILES['signature']['tmp_name'])) {
    // No upload
    //$imgContent = base64_decode(mysqli_real_escape_string($con, $_POST['prevsignature']));
    $sigval = 0;
} else {
    $file_size = $_FILES['signature']['size'];
    $file_tmp = $_FILES['signature']['tmp_name'];
    $file_extArray = explode('.', $_FILES['signature']['name']);
    $file_ext = strtolower(end($file_extArray)); // Get the last element after explode

    $extensions = array("jpeg", "jpg", "png", "JPG", "JPEG");
    
    if (!in_array($file_ext, $extensions)) {
        $errors[] = $file_ext . " extension not allowed, please choose a JPEG or PNG file.";
    }

    // Check if file size exceeds 1MB
    if ($file_size > 1048576) {
        // Resize logic here
        // Assuming GD library is installed
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
            $newWidth = 400; // Adjust the width as needed
            $ratio = $height / $width;
            $newHeight = $newWidth * $ratio;

            // Create resized image
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Temporarily save image to get its content
            $tempPath = 'temp_image.' . $file_ext;
            if ($file_ext == 'jpeg' || $file_ext == 'jpg') {
                imagejpeg($resizedImage, $tempPath);
            } elseif ($file_ext == 'png') {
                imagepng($resizedImage, $tempPath);
            }

            // Get content of resized image
            $imgContent = addslashes(file_get_contents($tempPath));

            // Clean up
            imagedestroy($resizedImage);
            unlink($tempPath);
        } else {
            $errors[] = 'Unable to resize image';
        }
    } else {
        // If file size is within limit
        $imgContent = addslashes(file_get_contents($file_tmp));
    }

    if (empty($errors)) {
        // Database update logic
        $checkUserQ = mysqli_query($con, "SELECT * FROM user_list WHERE employee_id = '$dataID'");
        $checkUserQNumRows = mysqli_num_rows($checkUserQ);
        
        if ($checkUserQNumRows > 0) {
            $updateSignatureQ = mysqli_query($con, "UPDATE user_list SET signature = '$imgContent' WHERE employee_id = '$dataID'");
        }

        $sigval = 1;
        // Success
    } else {
        print_r($errors);
        //$imgContent = base64_decode(mysqli_real_escape_string($con, $_POST['prevsignature']));
        $sigval = 0;
    }
}



if($sigval == 1){

$insertEmployeeInfoQ = mysqli_query($con, "update `employee_list` set `employee_name`='$employee_name', `designation`='$designation', `employee_id`='$employee_id', `employee_type`='$employee_type', `date_of_birth`='$dob', `joining_date`='$joining_date', `email`='$email', `mobileNo`='$mobileNo', `organization_id`='$organization_id', `department_id`='$department_id', `section_id`='$section_id', `pay_scale`='$pay_scale', `basic_salary`='$basic_salary', `salary_group_id`='$salary_group_id', `photo`='$file_name', `employment_status`='$employment_status', `nationalID`='$nid', `updated_by`='$createdBy', `last_update_date`='$todayDate', `display_order`='$display_order', `memorialNo`='$memorialNo', `signature`='$imgContent' where id='$dataID'");

}else{

$insertEmployeeInfoQ = mysqli_query($con, "update `employee_list` set `employee_name`='$employee_name', `designation`='$designation', `employee_id`='$employee_id', `employee_type`='$employee_type', `date_of_birth`='$dob', `joining_date`='$joining_date', `email`='$email', `mobileNo`='$mobileNo', `organization_id`='$organization_id', `department_id`='$department_id', `section_id`='$section_id', `pay_scale`='$pay_scale', `basic_salary`='$basic_salary', `salary_group_id`='$salary_group_id', `photo`='$file_name', `employment_status`='$employment_status', `nationalID`='$nid', `updated_by`='$createdBy', `last_update_date`='$todayDate', `display_order`='$display_order', `memorialNo`='$memorialNo' where id='$dataID'");

}

if($insertEmployeeInfoQ == 1) {

	if (function_exists('audit_log')) {
		audit_log('employee_updated', [
			'target_type'     => 'employee_list',
			'target_id'       => (int)$dataID,
			'organization_id' => (int)$organization_id,
			'note'            => 'name=' . mb_substr((string)$employee_name, 0, 80)
			                    . '; empID=' . mb_substr((string)$employee_id, 0, 30)
			                    . '; status=' . $employment_status,
		]);
	}
	echo 1;

} else {

echo 0;

}









?>