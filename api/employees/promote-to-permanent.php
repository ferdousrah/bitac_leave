<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../library/employee_helpers.php');
ob_end_clean();

if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

$dataID            = (int)($_POST['dataID']            ?? 0);
$permanentEmpID    = trim($_POST['permanent_emp_id']   ?? '');
$permanentFromDate = trim($_POST['permanent_from_date'] ?? '');

$result = bitac_promote_to_permanent($con, $dataID, $permanentEmpID, $permanentFromDate, (int)$_SESSION['userID']);
echo json_encode($result);
