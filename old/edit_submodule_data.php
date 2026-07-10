<?php
include('connection.php');

$dataID = $_POST['dataID'];

$submodule_name = $_POST['submodule_name'];

$module_id = $_POST['module_id'];

$page_link = $_POST['page_link'];

$slug = $_POST['slug'];


$display_order = $_POST['display_order'];

$updateQuery = mysqli_query($con, "update submodules set submodule_name='$submodule_name',module_id='$module_id',page_link='$page_link',slug='$slug',display_order='$display_order' where dataID='$dataID'");

if($updateQuery==1)
{
 echo 1;
}
else
{
 echo 0;
}


?>