<?php
// Include the connection file
include('connection.php');

// Start the session and check user authentication
session_start();
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Fetch unread notifications
$stmt = $con->prepare("SELECT * FROM notification WHERE userID = ? AND isRead = 0 AND isImportant = 0 ORDER BY notificationID DESC LIMIT 10");
$stmt->bind_param("s", $_SESSION['userID']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Return the notifications as JSON
echo json_encode($notifications);
?>
