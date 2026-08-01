<?php
// Start session first
session_start();

// Set JSON header
header('Content-Type: application/json');

// Start output buffering
ob_start();
require_once(__DIR__ . '/../../connection.php');
ob_end_clean();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

// Validate input
if (!isset($_POST['section_name']) || empty(trim($_POST['section_name']))) {
    echo json_encode(['status' => 0, 'message' => 'শাখার নাম প্রদান করুন!']);
    exit;
}

$section_name = mysqli_real_escape_string($con, trim($_POST['section_name']));
$display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;

// Check if 'deleted' column exists in sections table
$columnCheck = mysqli_query($con, "SHOW COLUMNS FROM sections LIKE 'deleted'");
$hasDeletedColumn = mysqli_num_rows($columnCheck) > 0;

// Check for duplicate (only among non-deleted records if column exists)
if ($hasDeletedColumn) {
    $checkQuery = "SELECT id FROM sections WHERE section_name = ? AND deleted = 0";
} else {
    $checkQuery = "SELECT id FROM sections WHERE section_name = ?";
}

$checkStmt = mysqli_prepare($con, $checkQuery);

if (!$checkStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($checkStmt, "s", $section_name);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['status' => 0, 'message' => 'এই শাখা ইতিমধ্যে বিদ্যমান!']);
    mysqli_stmt_close($checkStmt);
    mysqli_close($con);
    exit;
}

mysqli_stmt_close($checkStmt);

// Insert new section
if ($hasDeletedColumn) {
    $insertQuery = "INSERT INTO sections (section_name, display_order, deleted) VALUES (?, ?, 0)";
} else {
    $insertQuery = "INSERT INTO sections (section_name, display_order) VALUES (?, ?)";
}

$insertStmt = mysqli_prepare($con, $insertQuery);

if (!$insertStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($insertStmt, "si", $section_name, $display_order);

if (mysqli_stmt_execute($insertStmt)) {
    if (function_exists('audit_log')) {
        audit_log('section_created', [
            'target_type' => 'sections',
            'target_id'   => mysqli_insert_id($con),
            'note'        => 'name=' . mb_substr($section_name, 0, 100),
        ]);
    }
    echo json_encode(['status' => 1, 'message' => 'শাখা সফলভাবে যোগ করা হয়েছে!']);
} else {
    echo json_encode(['status' => 0, 'message' => 'শাখা যোগ করতে ব্যর্থ হয়েছে!']);
}

mysqli_stmt_close($insertStmt);
mysqli_close($con);
?>
