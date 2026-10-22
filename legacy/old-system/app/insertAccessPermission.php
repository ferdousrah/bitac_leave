<?php
include('connection.php');

$user_id = $_POST['user_id'];

$mid = $_POST['mid'];

$smid = $_POST['smid'];

$permission = $_POST['permission'];

$option = $_POST['option'];

if($option==1)
{

$checkForDataExistOrNot = mysqli_query($con,"select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");
$checkForDataExistOrNotNumRows = mysqli_num_rows($checkForDataExistOrNot);

if($checkForDataExistOrNotNumRows>0)
{
	// update
	$updateQuery = mysqli_query($con,"update access_permission set view='$permission' where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");

	if($updateQuery == 1){

		$checkDataQ = mysqli_query($con, "select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		$checkDataQNumRows = mysqli_num_rows($checkDataQ);
		if($checkDataQNumRows > 0){

			$deltQ = mysqli_query($con, "delete from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		
		}
	
	}
	
}
else
{
	// new entry
	$insertQ = mysqli_query($con,"insert into access_permission(user_id,module_id,submodule_id,add_p,update_p,delete_p,view) values('$user_id','$mid','$smid','0','0','0','$permission')");
}

}
else if($option==2)
{
	
	$checkForDataExistOrNot = mysqli_query($con,"select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");
$checkForDataExistOrNotNumRows = mysqli_num_rows($checkForDataExistOrNot);

if($checkForDataExistOrNotNumRows>0)
{
	// update
	$updateQuery = mysqli_query($con,"update access_permission set add_p='$permission' where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");
	
	if($updateQuery == 1){

		$checkDataQ = mysqli_query($con, "select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		$checkDataQNumRows = mysqli_num_rows($checkDataQ);
		if($checkDataQNumRows > 0){

			$deltQ = mysqli_query($con, "delete from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		
		}
	
	}




}
else
{
	// new entry
	$insertQ = mysqli_query($con,"insert into access_permission(user_id,module_id,submodule_id,add_p,update_p,delete_p,view) values('$user_id','$mid','$smid','$permission','0','0','0')");
}
	
}
else if($option==3)
{
	
	$checkForDataExistOrNot = mysqli_query($con,"select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");
$checkForDataExistOrNotNumRows = mysqli_num_rows($checkForDataExistOrNot);

if($checkForDataExistOrNotNumRows>0)
{
	// update
	$updateQuery = mysqli_query($con,"update access_permission set update_p='$permission' where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");

	if($updateQuery == 1){

		$checkDataQ = mysqli_query($con, "select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		$checkDataQNumRows = mysqli_num_rows($checkDataQ);
		if($checkDataQNumRows > 0){

			$deltQ = mysqli_query($con, "delete from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		
		}
	
	}






}
else
{
	// new entry
	$insertQ = mysqli_query($con,"insert into access_permission(user_id,module_id,submodule_id,add_p,update_p,delete_p,view) values('$user_id','$mid','$smid','0','$permission','0','0')");
}
	
}
else if($option==4)
{
	
	$checkForDataExistOrNot = mysqli_query($con,"select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");
$checkForDataExistOrNotNumRows = mysqli_num_rows($checkForDataExistOrNot);

if($checkForDataExistOrNotNumRows>0)
{
	// update
	$updateQuery = mysqli_query($con,"update access_permission set delete_p='$permission' where user_id='$user_id' and module_id='$mid' and submodule_id='$smid'");

	if($updateQuery == 1){

		$checkDataQ = mysqli_query($con, "select * from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		$checkDataQNumRows = mysqli_num_rows($checkDataQ);
		if($checkDataQNumRows > 0){

			$deltQ = mysqli_query($con, "delete from access_permission where user_id='$user_id' and module_id='$mid' and submodule_id='$smid' and add_p=0 and update_p=0 and delete_p=0 and view=0");
		
		}
	
	}




}
else
{
	// new entry
	$insertQ = mysqli_query($con,"insert into access_permission(user_id,module_id,submodule_id,add_p,update_p,delete_p,view) values('$user_id','$mid','$smid','0','0','$permission','0')");
}
	
}

?>