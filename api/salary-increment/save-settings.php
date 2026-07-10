<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
ob_end_clean();

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

$organization_id = intval($_POST['organization_id'] ?? 0);
if ($organization_id <= 0) {
    echo json_encode(['status' => 0, 'message' => 'কেন্দ্র নির্বাচন করুন!']);
    exit;
}

// Resolve user's allowed org and validate ownership
$uStmt = $con->prepare("SELECT isCenterAdmin, organization_id, employee_id FROM user_list WHERE user_id = ?");
$uStmt->bind_param("s", $_SESSION['username']);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!empty($uRow['isCenterAdmin'])) {
    $allowedOrgID = intval($uRow['organization_id']);
} elseif (!empty($uRow['employee_id'])) {
    $eStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $eStmt->bind_param("i", $uRow['employee_id']);
    $eStmt->execute();
    $eRow = $eStmt->get_result()->fetch_assoc();
    $eStmt->close();
    $allowedOrgID = intval($eRow['organization_id'] ?? 0);
} else {
    $allowedOrgID = 0;
}

// If user has a specific center, enforce it
if ($allowedOrgID > 0 && $organization_id !== $allowedOrgID) {
    echo json_encode(['status' => 0, 'message' => 'আপনার এই কেন্দ্রের সেটিংস পরিবর্তন করার অনুমতি নেই!']);
    exit;
}

if (empty($_POST['salary_increment_date'])) {
    echo json_encode(['status' => 0, 'message' => 'বেতন বৃদ্ধির তারিখ প্রদান করুন!']);
    exit;
}

if (empty($_POST['applicationTo'])) {
    echo json_encode(['status' => 0, 'message' => 'প্রতি নির্বাচন করুন!']);
    exit;
}

$salary_increment_date = mysqli_real_escape_string($con, $_POST['salary_increment_date']);
$applicationTo         = intval($_POST['applicationTo']);
$fileNo                = mysqli_real_escape_string($con, $_POST['fileNo'] ?? '');
$notice_content        = mysqli_real_escape_string($con, $_POST['notice_content'] ?? '');

// INSERT new row or UPDATE existing row for this center
$upsertQuery = "INSERT INTO increment_settings (organization_id, salary_increment_date, applicationTo, fileNo, copyTo, notice_content)
                VALUES (?, ?, ?, ?, 0, ?)
                ON DUPLICATE KEY UPDATE
                    salary_increment_date = VALUES(salary_increment_date),
                    applicationTo         = VALUES(applicationTo),
                    fileNo                = VALUES(fileNo),
                    notice_content        = VALUES(notice_content)";

$upsertStmt = mysqli_prepare($con, $upsertQuery);
if (!$upsertStmt) {
    echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি!']);
    exit;
}

mysqli_stmt_bind_param($upsertStmt, "isiss", $organization_id, $salary_increment_date, $applicationTo, $fileNo, $notice_content);

if (!mysqli_stmt_execute($upsertStmt)) {
    mysqli_stmt_close($upsertStmt);
    echo json_encode(['status' => 0, 'message' => 'সেটিংস সংরক্ষণ করতে ব্যর্থ হয়েছে!']);
    exit;
}
mysqli_stmt_close($upsertStmt);

// Clear and re-insert approval officers for this center
mysqli_query($con, "DELETE FROM salary_increment_approvals WHERE organization_id = '$organization_id'");

if (isset($_POST['signatory']) && is_array($_POST['signatory'])) {
    $insStmt = mysqli_prepare($con, "INSERT INTO salary_increment_approvals (organization_id, employeeID, approvalSL) VALUES (?, ?, ?)");
    if ($insStmt) {
        $sl = 0;
        foreach ($_POST['signatory'] as $signatory) {
            if (!empty($signatory)) {
                $serial     = isset($_POST['serial'][$sl]) ? intval($_POST['serial'][$sl]) : $sl + 1;
                $signatoryId = intval($signatory);
                mysqli_stmt_bind_param($insStmt, "iii", $organization_id, $signatoryId, $serial);
                mysqli_stmt_execute($insStmt);
            }
            $sl++;
        }
        mysqli_stmt_close($insStmt);
    }
}

// Clear and re-insert copy-to recipients for this center
mysqli_query($con, "DELETE FROM salary_notice_copy WHERE organization_id = '$organization_id' AND refFor = 0");

if (isset($_POST['copyTo']) && is_array($_POST['copyTo'])) {
    $copyStmt = mysqli_prepare($con, "INSERT INTO salary_notice_copy (organization_id, employeeID, refFor) VALUES (?, ?, 0)");
    if ($copyStmt) {
        foreach ($_POST['copyTo'] as $copyToEmployee) {
            if (!empty($copyToEmployee)) {
                $copyToId = intval($copyToEmployee);
                mysqli_stmt_bind_param($copyStmt, "ii", $organization_id, $copyToId);
                mysqli_stmt_execute($copyStmt);
            }
        }
        mysqli_stmt_close($copyStmt);
    }
}

echo json_encode(['status' => 1, 'message' => 'বেতন বৃদ্ধির সেটিংস সফলভাবে সংরক্ষণ করা হয়েছে!']);
mysqli_close($con);
?>
