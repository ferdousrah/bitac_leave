<?php
// Create group_access_permission table
include('connection.php');

// Create table SQL
$createTableSQL = "
CREATE TABLE IF NOT EXISTS `group_access_permission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_group_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `submodule_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_group_id` (`user_group_id`),
  KEY `module_id` (`module_id`),
  KEY `submodule_id` (`submodule_id`),
  UNIQUE KEY `unique_permission` (`user_group_id`, `module_id`, `submodule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if (mysqli_query($con, $createTableSQL)) {
    echo "Table 'group_access_permission' created successfully!<br>";
} else {
    echo "Error creating table: " . mysqli_error($con) . "<br>";
}

mysqli_close($con);

echo "<br><a href='manage_user_group?menuslug=manage-user-group'>Go back to User Groups</a>";
?>
