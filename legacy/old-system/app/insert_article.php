<?php
include('connection.php');

$ordinance = $_POST['ordinance'];

$articleNo = $_POST['articleNo'];

$articleDetails = $_POST['articleDetails'];


$insertArticleQ = mysqli_query($con,"insert into articles(ordinance,articleNo,articleDetails) values('$ordinance','$articleNo','$articleDetails')");

if($insertArticleQ==1)
{
  echo 1;
}
else
{
  echo 0;
}



?>