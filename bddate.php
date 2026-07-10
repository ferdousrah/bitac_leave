<?php
/**
 * Legacy bddate.php — kept as a compatibility shim.
 *
 * All four functions below (ShowBangladeshTime, todayDate, logTime,
 * get_client_ip) are now canonically declared in function.php with the
 * SAME signatures. function.php is loaded early via config/connection.php.
 *
 * This file is still `include()`d from many legacy API endpoints. Without
 * function_exists guards each include would redeclare the functions and
 * fatal. Guards make every include here a no-op.
 */

if (!function_exists('ShowBangladeshTime')) {
    function ShowBangladeshTime() {
        $hour    = gmdate("H") + 6;
        $minute  = gmdate("i");
        $seconds = gmdate("s");
        $day     = gmdate("d");
        $month   = gmdate("m");
        $year    = gmdate("Y");
        return date("Y-m-d H:i:s", mktime($hour, $minute, $seconds, $month, $day, $year));
    }
}

if (!function_exists('todayDate')) {
    function todayDate() {
        $hour    = gmdate("H") + 6;
        $minute  = gmdate("i");
        $seconds = gmdate("s");
        $day     = gmdate("d");
        $month   = gmdate("m");
        $year    = gmdate("Y");
        return date("Y-m-d", mktime($hour, $minute, $seconds, $month, $day, $year));
    }
}

if (!function_exists('logTime')) {
    function logTime() {
        $hour    = gmdate("H") + 6;
        $minute  = gmdate("i");
        $seconds = gmdate("s");
        $day     = gmdate("d");
        $month   = gmdate("m");
        $year    = gmdate("Y");
        return date("H:i:s", mktime($hour, $minute, $seconds, $month, $day, $year));
    }
}

if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) return $_SERVER[$k];
        }
        return 'UNKNOWN';
    }
}
?>
