<?php
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 0, 'message' => 'আইডি পাওয়া যায়নি।']);
    exit;
}

$stmt = mysqli_prepare($con, "DELETE FROM leave_signatory_rule WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);

echo mysqli_stmt_execute($stmt)
    ? json_encode(['status' => 1, 'message' => 'নিয়ম মুছে ফেলা হয়েছে।'])
    : json_encode(['status' => 0, 'message' => 'মুছতে সমস্যা: ' . mysqli_error($con)]);
