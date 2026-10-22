<?php
include('connection.php');

$groupID = $_POST['groupID'];

$getAllAccountsQ = mysqli_query($con, "select * from `accounts_list` where `account_group`='$groupID'");

echo "<option value=''></option>";

while($aRow = mysqli_fetch_array($getAllAccountsQ)) {

	echo "<option value='$aRow[dataID]'>$aRow[account_name]</option>";

}

?>