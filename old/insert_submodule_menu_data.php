<?php
include('connection.php');

$module_name = $_POST['module_name'];

$module_id = $_POST['module_id'];

$page_link = $_POST['page_link'];

$slug = $_POST['slug'];

$group_title = $_POST['group_title'];

$display_order = $_POST['display_order'];

$insertQuery = mysqli_query($con, "insert into third_level_menu(`module_name`, `submodule_id`,`page_link`,`slug`,`display_order`,`group_title`) values('$module_name', '$module_id','$page_link','$slug','$display_order','$group_title')");

if($insertQuery==1)
{
 echo 1;
}
else
{
echo 0;
}

?>