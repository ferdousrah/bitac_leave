<?php
/**
 * Notifications — list fetch endpoint.
 *
 * GET params:
 *   scope=unread|all|important     (default: unread)
 *   limit=<int>                    (default: 15, max: 100)
 *   offset=<int>                   (default: 0)
 *
 * Response JSON:
 *   { status: 1,
 *     items: [
 *       { id, message, type, link, dateTime, isRead, isImportant, dateHuman }, ...
 *     ],
 *     unreadCount: <int>,
 *     total: <int>
 *   }
 *
 * Called by:
 *   - includes/footer_vuexy.php (dropdown polling, default scope=unread)
 *   - views/notifications/*.php  (view-all page — future)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../config/connection.php');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'error' => 'Not logged in']);
    exit;
}
$userID = (int)$_SESSION['userID'];

$scope  = strtolower(trim($_GET['scope'] ?? 'unread'));
if (!in_array($scope, ['unread', 'all', 'important'], true)) $scope = 'unread';

$limit  = max(1, min(100, (int)($_GET['limit']  ?? 15)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$where = "WHERE userID = ?";
if     ($scope === 'unread')    $where .= " AND isRead = 0";
elseif ($scope === 'important') $where .= " AND isImportant = 1";

// List query
$sql = "SELECT notificationID, message, notificationType, link, dateTime, isRead, isImportant
        FROM notification $where
        ORDER BY notificationID DESC
        LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, 'iii', $userID, $limit, $offset);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$items = [];
while ($r = mysqli_fetch_assoc($res)) {
    $items[] = [
        'id'          => (int)$r['notificationID'],
        'message'     => $r['message'],
        'type'        => $r['notificationType'],
        'link'        => $r['link'],
        'dateTime'    => $r['dateTime'],
        'dateHuman'   => humanTime($r['dateTime']),
        'isRead'      => (int)$r['isRead'],
        'isImportant' => (int)$r['isImportant'],
    ];
}
mysqli_stmt_close($stmt);

// Unread count (always the same regardless of scope, drives the badge)
$unreadCount = 0;
$c = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM notification WHERE userID = ? AND isRead = 0");
mysqli_stmt_bind_param($c, 'i', $userID);
mysqli_stmt_execute($c);
$unreadCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($c))['c'] ?? 0);
mysqli_stmt_close($c);

// Total for current scope (used by view-all pagination)
$t = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM notification $where");
mysqli_stmt_bind_param($t, 'i', $userID);
mysqli_stmt_execute($t);
$total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($t))['c'] ?? 0);
mysqli_stmt_close($t);

echo json_encode([
    'status'      => 1,
    'items'       => $items,
    'unreadCount' => $unreadCount,
    'total'       => $total,
]);


/**
 * Convert a datetime-ish string into a friendly Bangla relative time
 * (e.g. "৫ মিনিট আগে", "২ ঘণ্টা আগে", "গতকাল", or full date if older).
 */
function humanTime($dateStr) {
    if (!$dateStr) return '';
    $t = strtotime($dateStr);
    if (!$t) return htmlspecialchars($dateStr);
    $diff = time() - $t;
    if ($diff < 60)         return 'এইমাত্র';
    if ($diff < 3600)       return toBanglaDigits((int)($diff / 60)) . ' মিনিট আগে';
    if ($diff < 86400)      return toBanglaDigits((int)($diff / 3600)) . ' ঘণ্টা আগে';
    if ($diff < 86400 * 2)  return 'গতকাল';
    if ($diff < 86400 * 7)  return toBanglaDigits((int)($diff / 86400)) . ' দিন আগে';
    return toBanglaDigits(date('d-m-Y', $t));
}
function toBanglaDigits($str) {
    return strtr((string)$str, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}
