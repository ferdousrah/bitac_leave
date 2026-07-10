<?php
session_start();

// Audit logout BEFORE destroying the session (so we can still resolve the actor)
if (!empty($_SESSION['username'])) {
    require_once(__DIR__ . '/config/connection.php');
    if (function_exists('audit_log')) {
        audit_log('logout', [
            'target_type' => 'user',
            'target_id'   => (int)($_SESSION['userID'] ?? 0),
        ]);
    }
}

setcookie(session_name(), '', 100);
session_unset();
session_destroy();
$_SESSION = array();
?>
