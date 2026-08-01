<?php
require_once(__DIR__ . '/../../config/connection.php');

$dataID = $_POST['dataID'];

$tableName = $_POST['tableName'];

$deltQ = mysqli_query($con,"delete from `$tableName` where dataID='$dataID'");

if($deltQ==1)
{
 if (function_exists('audit_log')) {
    audit_log('signatory_deleted_legacy', [
       'target_type' => (string)$tableName,
       'target_id'   => (int)$dataID,
    ]);
 }
 echo 1;
}
else
{
 echo 0;
}
?>