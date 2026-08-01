<?php
/**
 * Mark ALL of the caller's unread notifications as read.
 *
 * Response JSON: { status: 1|0, marked, unreadCount, error? }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../config/connection.php');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'error' => 'Not logged in']);
    exit;
}
$userID = (int)$_SESSION['userID'];

$u = mysqli_prepare($con,
    "UPDATE notification SET isRead = 1
     WHERE userID = ? AND isRead = 0");
mysqli_stmt_bind_param($u, 'i', $userID);
mysqli_stmt_execute($u);
$marked = mysqli_stmt_affected_rows($u);
mysqli_stmt_close($u);

echo json_encode(['status' => 1, 'marked' => (int)$marked, 'unreadCount' => 0]);
