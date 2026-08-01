<?php
/**
 * Mark a single notification as read.
 *
 * POST params:
 *   id=<notificationID>
 *
 * Response JSON: { status: 1|0, unreadCount, error? }
 *
 * Idempotent — if already read (or belongs to another user), status is still 1
 * with unreadCount refreshed. Only the current user's rows are ever mutated.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../config/connection.php');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'error' => 'Not logged in']);
    exit;
}
$userID = (int)$_SESSION['userID'];
$notifID = (int)($_POST['id'] ?? 0);

if ($notifID <= 0) {
    echo json_encode(['status' => 0, 'error' => 'Invalid notification id']);
    exit;
}

// Update — restricted to the caller's own row
$u = mysqli_prepare($con,
    "UPDATE notification SET isRead = 1
     WHERE notificationID = ? AND userID = ?");
mysqli_stmt_bind_param($u, 'ii', $notifID, $userID);
mysqli_stmt_execute($u);
mysqli_stmt_close($u);

// Refreshed unread count
$c = mysqli_prepare($con,
    "SELECT COUNT(*) AS c FROM notification WHERE userID = ? AND isRead = 0");
mysqli_stmt_bind_param($c, 'i', $userID);
mysqli_stmt_execute($c);
$unread = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($c))['c'] ?? 0);
mysqli_stmt_close($c);

echo json_encode(['status' => 1, 'unreadCount' => $unread]);
