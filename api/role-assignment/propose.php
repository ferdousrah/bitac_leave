<?php
/**
 * Propose a new Regional Super Admin / Regional Op. Admin assignment.
 *
 * Body (POST, multipart):
 *   organization_id     — center being assigned to
 *   role_id             — 7 (Regional Super Admin) or 8 (Regional Op. Admin)
 *   employee_id         — employee_list.id chosen from this center's employees
 *   username            — proposed login
 *   password            — proposed login password (will be hashed)
 *   attachment          — office order file (PDF/JPG/PNG)
 *   note                — optional reason
 *
 * On success: inserts row into role_assignment_proposal (status=0) and
 * role_assignment_log. No user_list row created — that happens at approve time.
 *
 * Validation: blocks duplicate pending proposal for the same role+center,
 * checks employee belongs to that center, checks role_id is regional, ensures
 * approver is configured (no point creating a proposal nobody can approve).
 *
 * Returns JSON: { status: 0|1, message }
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'লগইন করা নেই']); exit;
}

$organizationID = (int)($_POST['organization_id'] ?? 0);
$roleID         = (int)($_POST['role_id'] ?? 0);
$employeeID     = (int)($_POST['employee_id'] ?? 0);
$username       = trim($_POST['username'] ?? '');
$password       = $_POST['password'] ?? '';
$note           = trim($_POST['note'] ?? '');

// Whitelist: only Regional Super Admin (7) and Regional Op. Admin (8) go through this flow.
if (!in_array($roleID, [7, 8], true)) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ role']); exit;
}
if ($organizationID <= 0 || $employeeID <= 0) {
    echo json_encode(['status' => 0, 'message' => 'কর্মকর্তা ও কেন্দ্র আবশ্যক']); exit;
}

// Two cases:
//  - Existing user: employee already has user_list row. We DON'T need new username/password,
//    proposal targets the existing user; approval just adds the role to user_group_assignment.
//  - New user: employee has no account; admin provides username + password, approval creates user.
$existingUser = $con->prepare("SELECT dataID, user_id FROM user_list WHERE employee_id = ? LIMIT 1");
$existingUser->bind_param("i", $employeeID);
$existingUser->execute();
$existingUserRow = $existingUser->get_result()->fetch_assoc();
$existingUser->close();
$targetUserID = $existingUserRow ? (int)$existingUserRow['dataID'] : 0;

if (!$targetUserID && ($username === '' || $password === '')) {
    echo json_encode(['status' => 0, 'message' => 'নতুন user এর জন্য ইউজারনেম ও পাসওয়ার্ড আবশ্যক']); exit;
}

// Approver must be configured — proposals with no approver would dangle
$apStmt = $con->prepare("SELECT approver_user_id FROM role_approver_config ORDER BY dataID DESC LIMIT 1");
$apStmt->execute();
$apRow = $apStmt->get_result()->fetch_assoc();
$apStmt->close();
$approverID = (int)($apRow['approver_user_id'] ?? 0);
if ($approverID <= 0) {
    echo json_encode(['status' => 0, 'message' => 'অনুমোদনকারী নির্ধারিত নেই — সেটিংস থেকে নির্ধারণ করুন']); exit;
}

// No duplicate pending proposal for the same role+center
$dup = $con->prepare(
    "SELECT dataID FROM role_assignment_proposal
     WHERE organization_id = ? AND role_id = ? AND status = 0 LIMIT 1"
);
$dup->bind_param("ii", $organizationID, $roleID);
$dup->execute();
if ($dup->get_result()->fetch_assoc()) {
    $dup->close();
    echo json_encode(['status' => 0, 'message' => 'এই কেন্দ্রের জন্য একই role এ ইতিমধ্যে একটি অপেক্ষমান প্রস্তাব আছে']); exit;
}
$dup->close();

// Employee must belong to this center + be active
$emp = $con->prepare(
    "SELECT id, employee_name FROM employee_list
     WHERE id = ? AND organization_id = ? AND employment_status = 1 LIMIT 1"
);
$emp->bind_param("ii", $employeeID, $organizationID);
$emp->execute();
$empRow = $emp->get_result()->fetch_assoc();
$emp->close();
if (!$empRow) {
    echo json_encode(['status' => 0, 'message' => 'কর্মকর্তা এই কেন্দ্রের active তালিকায় নেই']); exit;
}
$employeeName = $empRow['employee_name'];

// Username uniqueness — only relevant when creating a brand-new account
if (!$targetUserID) {
    $un = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
    $un->bind_param("s", $username);
    $un->execute();
    if ($un->get_result()->fetch_assoc()) {
        $un->close();
        echo json_encode(['status' => 0, 'message' => 'এই ইউজারনেম ইতিমধ্যে ব্যবহৃত হয়েছে']); exit;
    }
    $un->close();
}

// Also block if the existing user already holds this role for this center (currently active)
if ($targetUserID) {
    $dupRole = $con->prepare(
        "SELECT uga.dataID FROM user_group_assignment uga
         INNER JOIN user_list ul ON uga.user_id = ul.dataID
         INNER JOIN employee_list el ON ul.employee_id = el.id
         WHERE uga.user_id = ? AND uga.group_id = ? AND uga.effective_to IS NULL
           AND el.organization_id = ? LIMIT 1"
    );
    $dupRole->bind_param("iii", $targetUserID, $roleID, $organizationID);
    $dupRole->execute();
    if ($dupRole->get_result()->fetch_assoc()) {
        $dupRole->close();
        echo json_encode(['status' => 0, 'message' => 'এই কর্মকর্তা ইতিমধ্যে এই role এ সক্রিয় আছেন']); exit;
    }
    $dupRole->close();
}

// Resolve current admin's user_list.dataID
$me = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
$me->bind_param("s", $_SESSION['username']);
$me->execute();
$meRow = $me->get_result()->fetch_assoc();
$me->close();
$proposedBy = (int)($meRow['dataID'] ?? 0);
if ($proposedBy <= 0) {
    echo json_encode(['status' => 0, 'message' => 'বর্তমান ব্যবহারকারী খুঁজে পাওয়া যায়নি']); exit;
}

// Attachment is mandatory — store under uploads/role-attachments/
$attachmentName = '';
if (empty($_FILES['attachment']['tmp_name']) || !is_uploaded_file($_FILES['attachment']['tmp_name'])) {
    echo json_encode(['status' => 0, 'message' => 'সংযুক্তি (অফিস আদেশ) আবশ্যক']); exit;
}
$file = $_FILES['attachment'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
    echo json_encode(['status' => 0, 'message' => 'PDF/JPG/PNG শুধু গ্রহণযোগ্য']); exit;
}
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['status' => 0, 'message' => 'ফাইল 2MB এর বেশি']); exit;
}
$uploadDir = __DIR__ . '/../../uploads/role-attachments/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
$attachmentName = 'role-' . $roleID . '-' . time() . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $attachmentName)) {
    echo json_encode(['status' => 0, 'message' => 'সংযুক্তি upload ব্যর্থ']); exit;
}

// Hash password only when creating a new user. For existing-user case the
// password column stays NULL (no new account, no password to set).
$hashedPwd = null;
$usernameToStore = null;
if (!$targetUserID) {
    $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
    $usernameToStore = $username;
}

mysqli_begin_transaction($con);
try {
    $ins = $con->prepare(
        "INSERT INTO role_assignment_proposal
         (organization_id, role_id, employee_id, target_user_id, proposed_username,
          proposed_password, proposed_full_name, attachment, note, proposed_by, approver_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
    );
    // target_user_id is NULL for new-user case; bind a variable that's null
    $targetUserIDParam = $targetUserID > 0 ? $targetUserID : null;
    $ins->bind_param("iiiisssssii",
        $organizationID, $roleID, $employeeID, $targetUserIDParam,
        $usernameToStore, $hashedPwd,
        $employeeName, $attachmentName, $note, $proposedBy, $approverID
    );
    $ins->execute();
    $proposalID = $ins->insert_id;
    $ins->close();

    // Log entry
    $log = $con->prepare(
        "INSERT INTO role_assignment_log
         (proposal_id, action, actor_user_id, actor_name, target_user_id, target_employee_id,
          organization_id, role_id, note)
         VALUES (?, 'proposed', ?, ?, NULL, ?, ?, ?, ?)"
    );
    $actorName = $_SESSION['username'];
    $log->bind_param("iisiiis",
        $proposalID, $proposedBy, $actorName, $employeeID,
        $organizationID, $roleID, $note
    );
    $log->execute();
    $log->close();

    mysqli_commit($con);

    // Universal audit_log mirror
    if (function_exists('audit_log')) {
        audit_log('role_proposed', [
            'target_type'     => 'role_proposal',
            'target_id'       => (int)$proposalID,
            'organization_id' => $organizationID,
            'note'            => 'role_id=' . $roleID . '; employee_id=' . $employeeID . ($targetUserID ? '; existing_user_id=' . $targetUserID : ''),
        ]);
    }

    echo json_encode(['status' => 1, 'message' => 'প্রস্তাব অনুমোদনের জন্য পাঠানো হয়েছে']);
} catch (Throwable $e) {
    mysqli_rollback($con);
    // Clean up uploaded file on failure
    if ($attachmentName && file_exists($uploadDir . $attachmentName)) {
        @unlink($uploadDir . $attachmentName);
    }
    echo json_encode(['status' => 0, 'message' => 'প্রস্তাব সংরক্ষণ ব্যর্থ: ' . $e->getMessage()]);
}
