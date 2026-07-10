<?php
session_start();
include('connection.php');
include('bddate.php');

$dataID = $_POST['dataID'];

$full_name = $_POST['full_name'];

$employee_id = $_POST['employeeID'];

$designation = $_POST['designation'];

$mobile = $_POST['mobile'];

$email = $_POST['email'];

$dashboardType = $_POST['dashboardType'];

$user_id = $_POST['user_id'];

$user_group_id = isset($_POST['user_group_id']) && !empty($_POST['user_group_id']) ? $_POST['user_group_id'] : NULL;

// signature

if(!file_exists($_FILES['signature']['tmp_name']) || !is_uploaded_file($_FILES['signature']['tmp_name'])) {
   // echo 'No upload';
   $file_name = '';
}
else {

$file_name = $_FILES['signature']['name'];
$file_size =$_FILES['signature']['size'];
$file_tmp =$_FILES['signature']['tmp_name'];
$file_type=$_FILES['signature']['type'];
$file_extArray=explode('.',$_FILES['signature']['name']);
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
		$imgContent = addslashes(file_get_contents($file_tmp));

		$updateSignatureQ = mysqli_query($con, "update user_list set signature='$imgContent' where dataID='$dataID'");
		
		//echo "Success";
	}else{
		print_r($errors);
		$file_name = '';
	}
}

// end of signature







if(isset($_POST['password']) && $_POST['password']!='')
{
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

if ($user_group_id !== NULL) {
	$updateQuery = mysqli_query($con,"update `user_list` set `user_id`='$user_id',`password`='$password',full_name='$full_name',designation='$designation',mobile='$mobile',email='$email',employee_id='$employee_id', dashboardType='$dashboardType', user_group_id='$user_group_id' where `dataID`='$dataID'");
} else {
	$updateQuery = mysqli_query($con,"update `user_list` set `user_id`='$user_id',`password`='$password',full_name='$full_name',designation='$designation',mobile='$mobile',email='$email',employee_id='$employee_id', dashboardType='$dashboardType', user_group_id=NULL where `dataID`='$dataID'");
}

}
else
{
if ($user_group_id !== NULL) {
	$updateQuery = mysqli_query($con,"update `user_list` set `user_id`='$user_id',full_name='$full_name',designation='$designation',mobile='$mobile',email='$email',employee_id='$employee_id',dashboardType='$dashboardType', user_group_id='$user_group_id' where `dataID`='$dataID'");
} else {
	$updateQuery = mysqli_query($con,"update `user_list` set `user_id`='$user_id',full_name='$full_name',designation='$designation',mobile='$mobile',email='$email',employee_id='$employee_id',dashboardType='$dashboardType', user_group_id=NULL where `dataID`='$dataID'");
}
}



//echo "update `user_list` set `user_id`='$user_id',`password`='$password',full_name='$full_name',designation='$designation',mobile='$mobile',email='$email',user_type='$user_type',photo='$fileName',report_to='$report_to',employee_id='$employee_id',portfolioID='$portfolioID' where `id`='$dataID'";




if($updateQuery==1)
{
	echo 1;
}
else
{
	echo 0;
}

?>