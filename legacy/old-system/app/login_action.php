<?php
session_start();
include("connection.php"); // Include database connection file

// Check if the form data is sent via POST and sanitize it
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize username input
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $password = $_POST['password']; // Password does not need sanitization as it will be hashed
    
    // Use prepared statements to prevent SQL injection
    $stmt = $con->prepare("SELECT u.password, u.dataID, u.employee_id, e.id, e.employment_status FROM `user_list` u INNER JOIN employee_list e ON u.employee_id=e.id WHERE e.employment_status=1 and u.`user_id` = ?");
    $stmt->bind_param("s", $username);  // Bind the username as a string
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Fetch the user details
        $user = $result->fetch_assoc();

        // Verify the password entered by the user against the hashed password in the database
        if (password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Store user data in session variables
            $_SESSION['userID'] = $user['dataID'];
            $_SESSION['employeeID'] = $user['employee_id'];
            $_SESSION['username'] = $username;

            // Remember Me functionality with Secure cookies
            if (isset($_POST['rememberme'])) {
                // Set cookies for 2 days if "Remember Me" is checked
                $twoDays = time() + (60 * 60 * 24 * 2); // 2 days expiry
                setcookie('username', $username, $twoDays, "/", "", true, true); // HttpOnly and Secure
                // Store hashed password (or token, not plaintext password)
                setcookie('password', password_hash($password, PASSWORD_DEFAULT), $twoDays, "/", "", true, true); // HttpOnly and Secure
            } else {
                // Clear any existing cookies if "Remember Me" is not checked
                setcookie('username', '', time() - 3600, "/", "", true, true);
                setcookie('password', '', time() - 3600, "/", "", true, true);
            }

            // Successful login
            echo 1;
        } else {
            // Incorrect password
            echo 0;
        }
    } else {
        // Username not found
        echo 0;
    }

    // Close the prepared statement
    $stmt->close();
}

// Close the database connection
mysqli_close($con);
?>
