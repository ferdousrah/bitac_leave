<?php
session_start();
require_once(__DIR__ . '/../../connection.php');

// Validate session
if (!isset($_SESSION['userID'])) {
    echo 0;
    exit;
}

$dataID = isset($_POST['dataID']) ? intval($_POST['dataID']) : 0;

// Verify user is updating their own profile
if ($dataID != $_SESSION['userID']) {
    echo 0;
    exit;
}


if(!file_exists($_FILES['photo']['tmp_name']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
   // echo 'No upload';
   $file_name = $_POST['prevPhoto'];
}
else {

$file_name = $_FILES['photo']['name'];
$file_size =$_FILES['photo']['size'];
$file_tmp =$_FILES['photo']['tmp_name'];
$file_type=$_FILES['photo']['type'];
$file_extArray=explode('.',$_FILES['photo']['name']);
//echo $file_extArray[1];
$file_ext = strtolower($file_extArray[1]);	  

$extensions= array("jpeg","jpg","png");
      
if(in_array($file_ext,$extensions)== false){
	$errors[]="extension not allowed, please choose a JPEG or PNG file.";
}
      
	if($file_size > 2097152){
		$errors[]='File size must be excately 2 MB';
	}
      
	if(empty($errors)==true){
		move_uploaded_file($file_tmp, __DIR__ . "/../../uploads/" . $file_name);

		$getUserDetailsQ = mysqli_query($con, "select * from user_list where dataID='$dataID'");
		$getUserDetailsQRW = mysqli_fetch_assoc($getUserDetailsQ);

		$updateEmployeeQ = mysqli_query($con, "update employee_list set photo='$file_name' where id='$getUserDetailsQRW[employee_id]'");
		//echo "Success";
	}else{
		print_r($errors);
		$file_name = $_POST['prevPhoto'];
	}
}



if(isset($_POST['password']) && $_POST['password']!='')
{
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$updateQuery = mysqli_query($con,"update `user_list` set `password`='$password' where `dataID`='$dataID'");


}
else
{
//$updateQuery = mysqli_query($con,"update `user_list` set photo='$file_name' where `dataID`='$dataID'");
$updateQuery = 1;
}



//echo "update `user_list` set `user_id`='$user_id',`password`='$password',full_name='$full_name',designation='$designation',mobile='$mobile',email='$email',user_type='$user_type',photo='$fileName',report_to='$report_to',employee_id='$employee_id',portfolioID='$portfolioID' where `id`='$dataID'";




if($updateQuery==1)
{
	if (function_exists('audit_log')) {
		audit_log('account_updated', [
			'target_type' => 'user_list',
			'target_id'   => (int)$dataID,
			'note'        => 'password / photo / signature via account page',
		]);
	}
	echo 1;
}
else
{
	echo 0;
}

?>