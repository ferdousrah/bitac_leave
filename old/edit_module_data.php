<?php
include('connection.php');

$dataID = $_POST['dataID'];

$module_name = $_POST['module_name'];

$page_link = $_POST['page_link'];

$slug = $_POST['slug'];

$icon = $_POST['icon'];

$display_order = $_POST['display_order'];

$updateQuery = mysqli_query($con, "update modules set module_name='$module_name',page_link='$page_link',slug='$slug',icon='$icon',display_order='$display_order' where dataID='$dataID'");

if($updateQuery==1)
{
 echo 1;
}
else
{
 echo 0;
}


?>