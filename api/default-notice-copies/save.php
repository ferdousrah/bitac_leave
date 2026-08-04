<?php
/**
 * Insert or update a row in default_notice_copies.
 * POST: dataID (0 for new), label, isActive
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

function reply($ok, $msg = '') {
    echo json_encode(['status' => $ok ? 1 : 0, 'message' => $msg]);
    exit;
}

if (empty($_SESSION['username'])) reply(false, 'লগইন করা নেই');

// Auto-migrate (defensive — page usually creates first, but if API hit directly)
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS default_notice_copies (
        dataID    INT AUTO_INCREMENT PRIMARY KEY,
        label     VARCHAR(255) NOT NULL,
        serial    INT NOT NULL DEFAULT 0,
        isActive  TINYINT(1) NOT NULL DEFAULT 1,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$dataID   = (int)($_POST['dataID']   ?? 0);
$label    = trim((string)($_POST['label']    ?? ''));
$isActive = (int)!!($_POST['isActive'] ?? 0);

if ($label === '') reply(false, 'লেবেল খালি রাখা যাবে না');
if (mb_strlen($label) > 255) reply(false, 'লেবেল ২৫৫ অক্ষরের বেশি হতে পারবে না');

if ($dataID > 0) {
    $stmt = mysqli_prepare($con, "UPDATE default_notice_copies SET label = ?, isActive = ? WHERE dataID = ?");
    mysqli_stmt_bind_param($stmt, 'sii', $label, $isActive, $dataID);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) >= 0;
    mysqli_stmt_close($stmt);
    if (function_exists('audit_log')) {
        audit_log('default_notice_copy_updated', [
            'target_type' => 'default_notice_copy', 'target_id' => $dataID,
            'note' => 'label=' . mb_substr($label, 0, 120),
        ]);
    }
} else {
    // Append at the end
    $maxQ = mysqli_query($con, "SELECT COALESCE(MAX(serial), 0) AS m FROM default_notice_copies");
    $nextSerial = (int)(mysqli_fetch_assoc($maxQ)['m'] ?? 0) + 1;
    $stmt = mysqli_prepare($con, "INSERT INTO default_notice_copies (label, serial, isActive) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sii', $label, $nextSerial, $isActive);
    $ok = mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);
    if ($ok && function_exists('audit_log')) {
        audit_log('default_notice_copy_created', [
            'target_type' => 'default_notice_copy', 'target_id' => (int)$newId,
            'note' => 'label=' . mb_substr($label, 0, 120),
        ]);
    }
}

reply($ok, $ok ? 'সফল' : 'ডাটাবেস ত্রুটি');
