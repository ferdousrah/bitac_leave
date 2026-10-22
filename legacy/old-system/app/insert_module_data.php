<?php
include('connection.php');

$module_name = $_POST['module_name'];

$page_link = $_POST['page_link'];

$slug = $_POST['slug'];

$icon = $_POST['icon'];

$display_order = $_POST['display_order'];

$insertQuery = mysqli_query($con, "insert into modules(`module_name`,`page_link`,`slug`,`icon`,`display_order`) values('$module_name','$page_link','$slug','$icon','$display_order')");

if($insertQuery==1)
{
 echo 1;
}
else
{
echo 0;
}

?>