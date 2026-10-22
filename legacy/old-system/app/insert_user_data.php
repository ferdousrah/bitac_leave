<?php
include('connection.php');

$full_name = $_POST['full_name'];

$employee_id = $_POST['employeeID'];

$designation = $_POST['designation'];

$email = $_POST['email'];

$mobile = $_POST['mobile'];

$user_id = $_POST['user_id'];

$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$checkForDuplicateuserQ = mysqli_query($con, "select * from user_list where `user_id`='$user_id'");
$checkForDuplicateuserQNumRows = mysqli_num_rows($checkForDuplicateuserQ);

if($checkForDuplicateuserQNumRows > 0){

	echo "User Already Exist!";

}else{

$insertQuery = mysqli_query($con, "insert into user_list(`full_name`,`employee_id`,`designation`,`email`,`mobile`,`user_id`,`password`,`user_type`) values('$full_name','$employee_id','$designation','$email','$mobile','$user_id','$password','2')");

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