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

// Validate inputs
if (!isset($_POST['employeeID']) || empty($_POST['employeeID'])) {
    echo json_encode(['status' => 0, 'message' => 'কর্মচারী আইডি প্রয়োজন!']);
    exit;
}

$employeeID = intval($_POST['employeeID']);

// Start transaction
mysqli_begin_transaction($con);

try {
    // Delete existing copy recipients for this employee
    $deleteQuery = "DELETE FROM salary_notice_copy WHERE refFor = ?";
    $deleteStmt = mysqli_prepare($con, $deleteQuery);
    mysqli_stmt_bind_param($deleteStmt, "i", $employeeID);

    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception('পূর্বের ডেটা মুছতে ব্যর্থ হয়েছে!');
    }
    mysqli_stmt_close($deleteStmt);

    // Insert new copy recipients if provided
    $insertCount = 0;
    if (isset($_POST['copyTo']) && is_array($_POST['copyTo'])) {
        $insertQuery = "INSERT INTO salary_notice_copy (employeeID, refFor, serial) VALUES (?, ?, ?)";
        $insertStmt = mysqli_prepare($con, $insertQuery);

        if (!$insertStmt) {
            throw new Exception('ডাটাবেস ত্রুটি!');
        }

        foreach ($_POST['copyTo'] as $index => $copyTo) {
            // Skip empty selections
            if (empty($copyTo)) {
                continue;
            }

            $copyToInt = intval($copyTo);
            $serial = isset($_POST['serial'][$index]) ? intval($_POST['serial'][$index]) : ($index + 1);

            mysqli_stmt_bind_param($insertStmt, "iii", $copyToInt, $employeeID, $serial);

            if (mysqli_stmt_execute($insertStmt)) {
                $insertCount++;
            }
        }

        mysqli_stmt_close($insertStmt);
    }

    // Commit transaction
    mysqli_commit($con);

    echo json_encode([
        'status' => 1,
        'message' => 'অনুলিপি সেটিংস সফলভাবে সংরক্ষিত হয়েছে! মোট ' . $insertCount . ' জন যুক্ত করা হয়েছে।'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
}

mysqli_close($con);
?>
