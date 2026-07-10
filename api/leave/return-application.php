<?php
/**
 * Return (ফেরত পাঠান) a leave application.
 *
 * Supervisor   → returns to applicant     (status = 3)
 * Signatory    → returns to previous non-supervisor signatory  (resets both)
 * 1st signatory (no prev sig in chain) → returns to admin       (status = 3)
 *
 * All returns recorded in `leave_return_history` with a mandatory note.
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'লগইন করা নেই']);
    exit;
}

$leaveApplicationID = intval($_POST['leaveApplicationID'] ?? 0);
$dataID             = intval($_POST['dataID'] ?? 0); // approval row id
$note               = trim($_POST['note'] ?? '');

if (!$leaveApplicationID || !$dataID) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ অনুরোধ']);
    exit;
}
if ($note === '') {
    echo json_encode(['status' => 0, 'message' => 'ফেরতের কারণ অবশ্যই লিখতে হবে']);
    exit;
}

// Ensure return history table exists
mysqli_query($con, "
    CREATE TABLE IF NOT EXISTS leave_return_history (
        dataID INT AUTO_INCREMENT PRIMARY KEY,
        leaveApplicationID INT NOT NULL,
        returnedBy INT NOT NULL,
        returnedByName VARCHAR(255),
        returnedByTitle VARCHAR(255),
        returnedTo INT DEFAULT 0,
        returnedToName VARCHAR(255),
        returnType ENUM('to_applicant', 'to_previous_signatory', 'to_admin') NOT NULL,
        note TEXT NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app (leaveApplicationID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Fetch the approval row
$rowStmt = mysqli_prepare($con, "SELECT * FROM leave_data_for_approval WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($rowStmt, 'i', $dataID);
mysqli_stmt_execute($rowStmt);
$currentRow = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));
mysqli_stmt_close($rowStmt);

if (!$currentRow || (int)$currentRow['leaveApplicationID'] !== $leaveApplicationID) {
    echo json_encode(['status' => 0, 'message' => 'অনুমোদন এন্ট্রি পাওয়া যায়নি']);
    exit;
}

// Verify the logged-in user is the signatory
$userStmt = mysqli_prepare($con, "SELECT employee_id FROM user_list WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($userStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($userStmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
mysqli_stmt_close($userStmt);

if (!$userRow || (int)$userRow['employee_id'] !== (int)$currentRow['signatory']) {
    echo json_encode(['status' => 0, 'message' => 'অনুমতি নেই']);
    exit;
}
$currentEmpID = (int)$userRow['employee_id'];

// Fetch current user's name + title for history
$nameStmt = mysqli_prepare($con,
    "SELECT el.employee_name, jt.job_title_name
     FROM employee_list el LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE el.id = ? LIMIT 1");
mysqli_stmt_bind_param($nameStmt, 'i', $currentEmpID);
mysqli_stmt_execute($nameStmt);
$currentEmp = mysqli_fetch_assoc(mysqli_stmt_get_result($nameStmt));
mysqli_stmt_close($nameStmt);
$byName  = $currentEmp['employee_name'] ?? '';
$byTitle = $currentEmp['job_title_name'] ?? '';

// Fetch application
$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($appStmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);

if (!$app) {
    echo json_encode(['status' => 0, 'message' => 'আবেদন পাওয়া যায়নি']);
    exit;
}

// Determine return type + target
$returnType  = '';
$targetEmpID = 0;
$targetName  = '';
$prevDataID  = 0;   // for previous-signatory rewind

if ((int)$currentRow['isSupervisor'] === 1) {
    // Supervisor → applicant
    $returnType = 'to_applicant';
    $targetEmpID = (int)$app['applicantID'];

    $aStmt = mysqli_prepare($con, "SELECT employee_name FROM employee_list WHERE id = ?");
    mysqli_stmt_bind_param($aStmt, 'i', $targetEmpID);
    mysqli_stmt_execute($aStmt);
    $aRow = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));
    mysqli_stmt_close($aStmt);
    $targetName = $aRow['employee_name'] ?? 'আবেদনকারী';
} else {
    // Look for previous APPROVED non-supervisor signatory (lower serial)
    $prevStmt = mysqli_prepare($con,
        "SELECT ldfa.dataID, ldfa.signatory, ldfa.serial,
                el.employee_name, jt.job_title_name
         FROM leave_data_for_approval ldfa
         LEFT JOIN employee_list el ON ldfa.signatory = el.id
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE ldfa.leaveApplicationID = ?
           AND ldfa.serial < ?
           AND ldfa.isApproved = 1
           AND ldfa.isSupervisor = 0
         ORDER BY ldfa.serial DESC LIMIT 1");
    $curSerial = (int)$currentRow['serial'];
    mysqli_stmt_bind_param($prevStmt, 'ii', $leaveApplicationID, $curSerial);
    mysqli_stmt_execute($prevStmt);
    $prevRow = mysqli_fetch_assoc(mysqli_stmt_get_result($prevStmt));
    mysqli_stmt_close($prevStmt);

    if ($prevRow) {
        $returnType  = 'to_previous_signatory';
        $targetEmpID = (int)$prevRow['signatory'];
        $targetName  = $prevRow['employee_name'] ?? '—';
        $prevDataID  = (int)$prevRow['dataID'];
    } else {
        // First signatory after admin forward → return to admin
        $returnType  = 'to_admin';
        $targetEmpID = 0;
        $targetName  = 'প্রশাসনিক কর্মকর্তা';
    }
}

mysqli_begin_transaction($con);
try {
    // 1) Insert return history entry
    $insertStmt = mysqli_prepare($con,
        "INSERT INTO leave_return_history
         (leaveApplicationID, returnedBy, returnedByName, returnedByTitle,
          returnedTo, returnedToName, returnType, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insertStmt, 'iississs',
        $leaveApplicationID, $currentEmpID, $byName, $byTitle,
        $targetEmpID, $targetName, $returnType, $note);
    mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);

    // 2) Reset current signatory's approval state
    $resetStmt = mysqli_prepare($con,
        "UPDATE leave_data_for_approval
         SET isApproved = 0, approvedDate = NULL, note = ''
         WHERE dataID = ?");
    mysqli_stmt_bind_param($resetStmt, 'i', $dataID);
    mysqli_stmt_execute($resetStmt);
    mysqli_stmt_close($resetStmt);

    // 3) Type-specific state changes
    if ($returnType === 'to_applicant') {
        // Status 3 = ফেরত — applicant can edit + resubmit
        mysqli_query($con, "UPDATE leave_applications SET status = 3 WHERE dataID = $leaveApplicationID");
    } elseif ($returnType === 'to_previous_signatory') {
        // Rewind previous signatory so they review again
        $rwStmt = mysqli_prepare($con,
            "UPDATE leave_data_for_approval
             SET isApproved = 0, approvedDate = NULL
             WHERE dataID = ?");
        mysqli_stmt_bind_param($rwStmt, 'i', $prevDataID);
        mysqli_stmt_execute($rwStmt);
        mysqli_stmt_close($rwStmt);
        // Status stays 0 (still in chain, just rewound)
    } elseif ($returnType === 'to_admin') {
        // Admin needs to re-handle — status 3 (so it surfaces in admin's queue)
        mysqli_query($con, "UPDATE leave_applications SET status = 3 WHERE dataID = $leaveApplicationID");

        // Reset isSentbyAdmin=0 on ALL approval rows so the application reappears
        // in admin's "প্রক্রিয়াধীন" tab (which filters isSentbyAdmin=0).
        // Supervisor row keeps isApproved=1 (no need to re-supervise); only admin
        // forwarding state is rewound. Non-supervisor rows already have isApproved=0
        // (current row was reset above; later rows never approved).
        $rewindAdminStmt = mysqli_prepare($con,
            "UPDATE leave_data_for_approval
             SET isSentbyAdmin = 0
             WHERE leaveApplicationID = ?");
        mysqli_stmt_bind_param($rewindAdminStmt, 'i', $leaveApplicationID);
        mysqli_stmt_execute($rewindAdminStmt);
        mysqli_stmt_close($rewindAdminStmt);
    }

    mysqli_commit($con);

    if (function_exists('audit_log')) {
        audit_log('leave_returned', [
            'target_type' => 'leave_application',
            'target_id'   => (int)$leaveApplicationID,
            'note'        => 'returnType=' . $returnType . '; to=' . $targetName,
        ]);
    }

    echo json_encode([
        'status'     => 1,
        'message'    => 'আবেদনটি ' . $targetName . ' এর কাছে ফেরত পাঠানো হয়েছে',
        'returnType' => $returnType,
        'targetName' => $targetName,
    ]);
} catch (Exception $e) {
    mysqli_rollback($con);
    echo json_encode([
        'status'  => 0,
        'message' => 'ত্রুটি: ' . $e->getMessage(),
    ]);
}
