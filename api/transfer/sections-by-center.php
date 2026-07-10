<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'items' => []]);
    exit;
}

$orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
if ($orgId <= 0) {
    echo json_encode(['status' => 0, 'items' => [], 'message' => 'অবৈধ কেন্দ্র']);
    exit;
}

// Sections in this system are mostly global (organization_id = 0). We accept
// both global sections and sections explicitly scoped to the requested org.
$stmt = mysqli_prepare($con,
    "SELECT id, section_name FROM sections
     WHERE (organization_id = ? OR organization_id = 0) AND deleted = 0
     ORDER BY display_order ASC, section_name ASC");
mysqli_stmt_bind_param($stmt, 'i', $orgId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$items = [];
while ($r = mysqli_fetch_assoc($res)) {
    $items[] = ['id' => (int)$r['id'], 'name' => $r['section_name']];
}
mysqli_stmt_close($stmt);

echo json_encode(['status' => 1, 'items' => $items]);
mysqli_close($con);
