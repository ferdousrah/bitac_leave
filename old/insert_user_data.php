<?php
include('connection.php');

$full_name = $_POST['full_name'];

$employee_id = $_POST['employeeID'];

$designation = $_POST['designation'];

$email = $_POST['email'];

$mobile = $_POST['mobile'];

$user_id = $_POST['user_id'];

$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$user_group_id = isset($_POST['user_group_id']) && !empty($_POST['user_group_id']) ? $_POST['user_group_id'] : NULL;

$checkForDuplicateuserQ = mysqli_query($con, "select * from user_list where `user_id`='$user_id'");
$checkForDuplicateuserQNumRows = mysqli_num_rows($checkForDuplicateuserQ);

if($checkForDuplicateuserQNumRows > 0){

	echo "User Already Exist!";

}else{

if ($user_group_id !== NULL) {
	$insertQuery = mysqli_query($con, "insert into user_list(`full_name`,`employee_id`,`designation`,`email`,`mobile`,`user_id`,`password`,`user_type`,`user_group_id`) values('$full_name','$employee_id','$designation','$email','$mobile','$user_id','$password','2','$user_group_id')");
} else {
	$insertQuery = mysqli_query($con, "insert into user_list(`full_name`,`employee_id`,`designation`,`email`,`mobile`,`user_id`,`password`,`user_type`) values('$full_name','$employee_id','$designation','$email','$mobile','$user_id','$password','2')");
}

if($insertQuery==1)
{

 $getLastUserIDQ = mysqli_query($con, "select * from user_list where user_id='$user_id'");
 $getLastUserIDQRW = mysqli_fetch_assoc($getLastUserIDQ);

 echo $getLastUserIDQRW['dataID'];
}
else
{
echo 0;
}


}

?>