<?php
include('connection.php');

$submodule_name = $_POST['submodule_name'];

$module_id = $_POST['module_id'];

$page_link = $_POST['page_link'];

$slug = $_POST['slug'];


$display_order = $_POST['display_order'];

$insertQuery = mysqli_query($con, "insert into submodules(`submodule_name`, `module_id`,`page_link`,`slug`,`display_order`) values('$submodule_name', '$module_id','$page_link','$slug','$display_order')");

if($insertQuery==1)
{
 echo 1;
}
else
{
echo 0;
}

?>