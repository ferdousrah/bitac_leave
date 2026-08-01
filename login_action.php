<?php
session_start();
include("connection.php"); // Include database connection file

// ── Lockout config ──────────────────────────────────────────────────────
// Threshold and columns are intentionally simple — no timed unlock; only an
// admin can clear a lock. See views/users/manage.php + api/users/unlock.php.
define('LOGIN_MAX_ATTEMPTS', 3);

// Auto-migrate lockout columns on user_list (idempotent, runs once per request)
if (!defined('LOGIN_LOCKOUT_MIGRATED')) {
    define('LOGIN_LOCKOUT_MIGRATED', true);
    $_cols = [];
    $_r = @mysqli_query($con, "SHOW COLUMNS FROM user_list");
    while ($_r && ($_row = mysqli_fetch_assoc($_r))) $_cols[] = $_row['Field'];
    $_add = [];
    if (!in_array('failed_login_attempts', $_cols, true)) $_add[] = "ADD COLUMN `failed_login_attempts` INT NOT NULL DEFAULT 0";
    if (!in_array('is_locked',             $_cols, true)) $_add[] = "ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0";
    if (!in_array('locked_at',             $_cols, true)) $_add[] = "ADD COLUMN `locked_at` DATETIME NULL";
    if (!in_array('last_failed_login',     $_cols, true)) $_add[] = "ADD COLUMN `last_failed_login` DATETIME NULL";
    if (!empty($_add)) @mysqli_query($con, "ALTER TABLE user_list " . implode(', ', $_add));
}

// Reply helper — status codes align with the old echo values PLUS structured messages
function login_reply($ok, $msg = '') {
    // Legacy JS reads the response as plain text "1" or "0"; keep that behaviour
    // when there's no lockout message, so the current front-end keeps working.
    if ($ok) { echo 1; exit; }
    if ($msg === '') { echo 0; exit; }
    // Failure WITH a special message (e.g. account locked). Front-end can be
    // upgraded to check for this prefix.
    echo 'LOCKED:' . $msg;
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize username input
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $password = $_POST['password']; // Password does not need sanitization as it will be hashed

    // ── 0. Fetch the user's account row once — used for lockout check + auth ─
    // We pull lockout fields alongside password/dataID from user_list first so
    // one lookup covers both the normal-user and center-admin flows.
    $lockStmt = $con->prepare(
        "SELECT dataID, password, employee_id, organization_id, isCenterAdmin,
                failed_login_attempts, is_locked, locked_at
         FROM user_list WHERE user_id = ? LIMIT 1");
    $lockStmt->bind_param('s', $username);
    $lockStmt->execute();
    $accountRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    // ── 0a. Locked accounts refuse authentication outright ──────────────────
    if ($accountRow && (int)$accountRow['is_locked'] === 1) {
        if (function_exists('audit_log')) {
            audit_log('login_blocked_locked', [
                'actor_user_id'  => (int)$accountRow['dataID'],
                'actor_username' => $username,
                'target_type'    => 'user',
                'target_id'      => (int)$accountRow['dataID'],
                'note'           => 'attempted login on locked account',
            ]);
        }
        login_reply(false, 'অ্যাকাউন্ট লক করা হয়েছে। অনুগ্রহপূর্বক অ্যাডমিনের সাথে যোগাযোগ করুন।');
    }

    // ── 1. Try normal user (must have active employee record) ────────────────
    $stmt = $con->prepare("SELECT u.password, u.dataID, u.employee_id, e.id, e.employment_status FROM `user_list` u INNER JOIN employee_list e ON u.employee_id=e.id WHERE e.employment_status=1 and u.`user_id` = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    // ── 2. If not found, try center admin user (no employee record) ───────────
    $isCenterAdmin = false;
    if (!$user) {
        $stmt = $con->prepare("SELECT password, dataID, organization_id FROM user_list WHERE user_id = ? AND isCenterAdmin = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $isCenterAdmin = true;
        }
        $stmt->close();
    }

    if ($user) {
        // Verify the password entered by the user against the hashed password in the database
        if (password_verify($password, $user['password'])) {
            // ── SUCCESS: reset any failed-attempt counter ────────────────────
            $reset = $con->prepare(
                "UPDATE user_list
                 SET failed_login_attempts = 0, last_failed_login = NULL
                 WHERE dataID = ?");
            $uid = (int)$user['dataID'];
            $reset->bind_param('i', $uid);
            $reset->execute();
            $reset->close();

            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Store user data in session variables
            $_SESSION['userID']   = $user['dataID'];
            $_SESSION['username'] = $username;

            if ($isCenterAdmin) {
                $_SESSION['employeeID']        = 0;
                $_SESSION['isCenterAdmin']     = 1;
                $_SESSION['centerAdminOrgID']  = $user['organization_id'];
            } else {
                $_SESSION['employeeID']    = $user['employee_id'];
                $_SESSION['isCenterAdmin'] = 0;
            }

            // Audit: successful login
            if (function_exists('audit_log')) {
                audit_log('login_success', [
                    'actor_user_id'  => (int)$user['dataID'],
                    'actor_username' => $username,
                    'target_type'    => 'user',
                    'target_id'      => (int)$user['dataID'],
                    'note'           => $isCenterAdmin ? 'center-admin path' : 'normal path',
                ]);
            }

            // Successful login
            echo 1;
        } else {
            // ── FAILURE (wrong password on real account): increment + maybe lock
            $newCount = 0;
            $lockedNow = false;
            if ($accountRow) {
                $newCount = (int)$accountRow['failed_login_attempts'] + 1;
                if ($newCount >= LOGIN_MAX_ATTEMPTS) {
                    $lockedNow = true;
                    $up = $con->prepare(
                        "UPDATE user_list
                         SET failed_login_attempts = ?, is_locked = 1,
                             locked_at = NOW(), last_failed_login = NOW()
                         WHERE dataID = ?");
                    $rowId = (int)$accountRow['dataID'];
                    $up->bind_param('ii', $newCount, $rowId);
                    $up->execute();
                    $up->close();
                } else {
                    $up = $con->prepare(
                        "UPDATE user_list
                         SET failed_login_attempts = ?, last_failed_login = NOW()
                         WHERE dataID = ?");
                    $rowId = (int)$accountRow['dataID'];
                    $up->bind_param('ii', $newCount, $rowId);
                    $up->execute();
                    $up->close();
                }
            }

            if (function_exists('audit_log')) {
                audit_log($lockedNow ? 'login_locked' : 'login_failed', [
                    'actor_username' => $username,
                    'target_type'    => 'user',
                    'target_id'      => (int)($accountRow['dataID'] ?? 0),
                    'note'           => 'wrong password; attempts=' . $newCount
                                       . ($lockedNow ? '; account locked' : ''),
                ]);
            }

            if ($lockedNow) {
                login_reply(false, 'সর্বোচ্চ চেষ্টা অতিক্রম — অ্যাকাউন্ট লক করা হয়েছে। অ্যাডমিনের সাথে যোগাযোগ করুন।');
            }
            $remaining = LOGIN_MAX_ATTEMPTS - $newCount;
            login_reply(false, "ভুল পাসওয়ার্ড। আর $remaining বার চেষ্টার পর অ্যাকাউন্ট লক হবে।");
        }
    } else {
        // Unknown username — no per-account counter to increment. Still audit
        // for enumeration detection. Don't reveal whether username exists.
        if (function_exists('audit_log')) {
            audit_log('login_failed', [
                'actor_username' => $username,
                'note'           => 'username not found',
            ]);
        }
        echo 0;
    }
}

// Close the database connection
mysqli_close($con);
