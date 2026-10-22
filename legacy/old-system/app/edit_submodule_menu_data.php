<?php
include('connection.php');

$dataID = $_POST['dataID'];

$module_name = $_POST['module_name'];

$module_id = $_POST['module_id'];

$page_link = $_POST['page_link'];

$slug = $_POST['slug'];


$display_order = $_POST['display_order'];

$group_title = $_POST['group_title'];

$updateQuery = mysqli_query($con, "update third_level_menu set module_name='$module_name',submodule_id='$module_id',page_link='$page_link',slug='$slug',display_order='$display_order',group_title='$group_title' where dataID='$dataID'");

if($updateQuery==1)
{
 echo 1;
}
else
{
 echo 0;
}


?>