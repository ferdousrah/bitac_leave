<?php
/**
 * Persistent "remember me" login tokens.
 *
 * Flow:
 *   - login_action.php calls remember_issue() when the checkbox was ticked →
 *     stores a selector + SHA-256(validator) row and sets a 30-day cookie
 *     "bitac_remember" with "selector:validator".
 *   - header_vuexy.php / header.php call remember_attempt() BEFORE their
 *     session guard → if the session is gone but the cookie validates, the
 *     session variables are silently re-created and the token is rotated
 *     (mitigates replay if the cookie ever leaks).
 *   - logout.php calls remember_clear() → token row + cookie removed.
 *
 * Table auto-creates on first use. Guarded with function_exists so the two
 * legacy/vuexy headers can both include this file safely.
 */

if (!function_exists('remember_issue')) {

    define('REMEMBER_COOKIE', 'bitac_remember');
    define('REMEMBER_DAYS', 30);

    function remember_ensure_table($con) {
        mysqli_query($con, "
            CREATE TABLE IF NOT EXISTS user_remember_tokens (
                dataID    INT AUTO_INCREMENT PRIMARY KEY,
                userID    INT NOT NULL,
                selector  CHAR(24)  NOT NULL,
                validator CHAR(64)  NOT NULL,
                expiresAt DATETIME  NOT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_selector (selector),
                INDEX idx_user (userID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    function remember_cookie_opts() {
        return [
            'expires'  => time() + REMEMBER_DAYS * 86400,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /** Issue a fresh token for the user and set the cookie. */
    function remember_issue($con, $userID) {
        remember_ensure_table($con);
        $selector  = bin2hex(random_bytes(12));           // 24 chars
        $validator = bin2hex(random_bytes(32));           // 64 chars
        $hash      = hash('sha256', $validator);
        $expires   = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);

        $stmt = mysqli_prepare($con,
            "INSERT INTO user_remember_tokens (userID, selector, validator, expiresAt)
             VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'isss', $userID, $selector, $hash, $expires);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, remember_cookie_opts());
    }

    /** Remove the current browser's token (logout). */
    function remember_clear($con) {
        if (!empty($_COOKIE[REMEMBER_COOKIE])) {
            $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
            if (count($parts) === 2) {
                remember_ensure_table($con);
                $stmt = mysqli_prepare($con, "DELETE FROM user_remember_tokens WHERE selector = ?");
                mysqli_stmt_bind_param($stmt, 's', $parts[0]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        $opts = remember_cookie_opts();
        $opts['expires'] = time() - 3600;
        setcookie(REMEMBER_COOKIE, '', $opts);
    }

    /**
     * Try to restore an expired session from the remember cookie.
     * Returns true when the session was rebuilt.
     */
    function remember_attempt($con) {
        if (isset($_SESSION['username'])) return true;            // session alive
        if (empty($_COOKIE[REMEMBER_COOKIE])) return false;

        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($parts) !== 2) return false;
        list($selector, $validator) = $parts;

        remember_ensure_table($con);

        $stmt = mysqli_prepare($con,
            "SELECT dataID, userID, validator FROM user_remember_tokens
             WHERE selector = ? AND expiresAt > NOW() LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $selector);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) return false;

        if (!hash_equals($row['validator'], hash('sha256', $validator))) {
            // Selector matched but validator didn't — possible theft; kill the token.
            $del = mysqli_prepare($con, "DELETE FROM user_remember_tokens WHERE dataID = ?");
            mysqli_stmt_bind_param($del, 'i', $row['dataID']);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            return false;
        }

        // Load the account. Locked / deleted accounts must not auto-login.
        $uid = (int)$row['userID'];
        $uStmt = mysqli_prepare($con,
            "SELECT dataID, user_id, employee_id, organization_id, isCenterAdmin, is_locked
             FROM user_list WHERE dataID = ? LIMIT 1");
        mysqli_stmt_bind_param($uStmt, 'i', $uid);
        mysqli_stmt_execute($uStmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
        mysqli_stmt_close($uStmt);
        if (!$user || (int)($user['is_locked'] ?? 0) === 1) return false;

        // Mirror login_action.php's session bootstrap
        session_regenerate_id(true);
        $_SESSION['userID']   = (int)$user['dataID'];
        $_SESSION['username'] = $user['user_id'];
        if ((int)($user['isCenterAdmin'] ?? 0) === 1 && empty($user['employee_id'])) {
            $_SESSION['employeeID']       = 0;
            $_SESSION['isCenterAdmin']    = 1;
            $_SESSION['centerAdminOrgID'] = $user['organization_id'];
        } else {
            $_SESSION['employeeID']    = $user['employee_id'];
            $_SESSION['isCenterAdmin'] = 0;
        }

        // Rotate: burn the used token, issue a fresh one.
        $del = mysqli_prepare($con, "DELETE FROM user_remember_tokens WHERE dataID = ?");
        $tid = (int)$row['dataID'];
        mysqli_stmt_bind_param($del, 'i', $tid);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        remember_issue($con, (int)$user['dataID']);

        if (function_exists('audit_log')) {
            audit_log('login_remembered', [
                'actor_user_id'  => (int)$user['dataID'],
                'actor_username' => $user['user_id'],
                'target_type'    => 'user',
                'target_id'      => (int)$user['dataID'],
                'note'           => 'session restored from remember-me cookie',
            ]);
        }
        return true;
    }
}
