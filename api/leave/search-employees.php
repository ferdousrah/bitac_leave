<?php
/**
 * Employee search endpoint for Select2 AJAX.
 *
 * Query params:
 *   - q       : search term (matches employee_name OR employee_id)
 *   - org     : optional organization_id to scope results
 *   - page    : Select2 pagination page (1-based)
 *
 * Response: { results: [{id, text}], pagination: {more} }
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');

if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

header('Content-Type: application/json');

$q     = trim($_GET['q'] ?? '');
$org   = (int)($_GET['org'] ?? 0);
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 30;
$offset = ($page - 1) * $per;

$where  = '1=1';
$types  = '';
$params = [];

if ($q !== '') {
    $where .= ' AND (employee_name LIKE ? OR employee_id LIKE ?)';
    $types .= 'ss';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($org > 0) {
    $where .= ' AND organization_id = ?';
    $types .= 'i';
    $params[] = $org;
}

$sql = "SELECT id, employee_name, employee_id
        FROM employee_list
        WHERE $where
        ORDER BY employee_name ASC
        LIMIT $offset, " . ($per + 1);

$stmt = mysqli_prepare($con, $sql);
if ($types) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
mysqli_stmt_close($stmt);

$more = count($rows) > $per;
if ($more) array_pop($rows);

$results = array_map(function($r) {
    $name = htmlspecialchars($r['employee_name'] ?? '');
    $code = htmlspecialchars($r['employee_id'] ?? '');
    return [
        'id'   => (int)$r['id'],
        'text' => $name . ($code ? " ({$code})" : ''),
    ];
}, $rows);

echo json_encode(['results' => $results, 'pagination' => ['more' => $more]]);
