<?php
session_start();

require_once(__DIR__ . '/config/connection.php');

// Audit logout BEFORE destroying the session (so we can still resolve the actor)
if (!empty($_SESSION['username'])) {
    if (function_exists('audit_log')) {
        audit_log('logout', [
            'target_type' => 'user',
            'target_id'   => (int)($_SESSION['userID'] ?? 0),
        ]);
    }
}

// Remove this browser's remember-me token + cookie so an explicit logout
// really logs out (otherwise the next page-load would silently re-login).
require_once(__DIR__ . '/includes/remember-me.php');
remember_clear($con);

setcookie(session_name(), '', 100);
session_unset();
session_destroy();
$_SESSION = array();
?>
