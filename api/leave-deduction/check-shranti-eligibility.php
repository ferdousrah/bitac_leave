<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'অননুমোদিত']);
    exit;
}

$empId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($empId <= 0) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ কর্মচারী আইডি']);
    exit;
}

// Latest recreation entry — matters for eligibility check.
// Approval status doesn't gate here — even a pending shranti creates a maturity
// commitment we should warn about.
$stmt = mysqli_prepare($con,
    "SELECT deducted_on, next_maturity_date
     FROM recreation_leave_history
     WHERE employee_id = ?
     ORDER BY next_maturity_date DESC
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $empId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode([
        'status'    => 1,
        'eligible'  => true,
        'has_history' => false,
    ]);
    exit;
}

$maturity = $row['next_maturity_date'];
$today    = date('Y-m-d');
$eligible = ($maturity <= $today);
$daysUntil = (int)floor((strtotime($maturity) - strtotime($today)) / 86400);

echo json_encode([
    'status'             => 1,
    'eligible'           => $eligible,
    'has_history'        => true,
    'last_deducted_on'   => $row['deducted_on'],
    'next_maturity_date' => $maturity,
    'days_until_maturity'=> $daysUntil,
]);

mysqli_close($con);
