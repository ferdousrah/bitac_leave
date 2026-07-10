<?php
session_start();
include("connection.php"); // Include database connection file

// Check if the form data is sent via POST and sanitize it
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize username input
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $password = $_POST['password']; // Password does not need sanitization as it will be hashed
    
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

            // Remember Me functionality with Secure cookies
            if (isset($_POST['rememberme'])) {
                $twoDays = time() + (60 * 60 * 24 * 2);
                setcookie('username', $username, $twoDays, "/", "", true, true);
                setcookie('password', password_hash($password, PASSWORD_DEFAULT), $twoDays, "/", "", true, true);
            } else {
                setcookie('username', '', time() - 3600, "/", "", true, true);
                setcookie('password', '', time() - 3600, "/", "", true, true);
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
            // Audit: wrong password
            if (function_exists('audit_log')) {
                audit_log('login_failed', [
                    'actor_username' => $username,
                    'note'           => 'wrong password',
                ]);
            }
            echo 0;
        }
    } else {
        // Audit: unknown username
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
?>
