<?php
/**
 * Approve or reject a role-assignment proposal.
 *
 * Body (POST):
 *   proposal_id — role_assignment_proposal.dataID
 *   action      — 'approve' or 'reject'
 *   note        — required for reject, optional for approve
 *
 * On approve:
 *   1. Create user_list row with the proposed employee/username/password
 *   2. End previous active tenure for this role+org (effective_to = NOW)
 *   3. Insert new user_group_assignment row (effective_from = NOW)
 *   4. Mark proposal as approved
 *   5. Log entry
 *
 * On reject:
 *   1. Mark proposal as rejected
 *   2. Log entry
 *   3. (Attachment stays — audit trail)
 *
 * Returns JSON: { status: 0|1, message }
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'লগইন করা নেই']); exit;
}

$proposalID = (int)($_POST['proposal_id'] ?? 0);
$action     = trim($_POST['action'] ?? '');
$note       = trim($_POST['note'] ?? '');

if ($proposalID <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    echo json_encode(['status' => 0, 'message' => 'অবৈধ অনুরোধ']); exit;
}
if ($action === 'reject' && $note === '') {
    echo json_encode(['status' => 0, 'message' => 'প্রত্যাখ্যানের জন্য কারণ আবশ্যক']); exit;
}

// Resolve current user
$me = $con->prepare("SELECT dataID, full_name FROM user_list WHERE user_id = ? LIMIT 1");
$me->bind_param("s", $_SESSION['username']);
$me->execute();
$meRow = $me->get_result()->fetch_assoc();
$me->close();
$currentUserID = (int)($meRow['dataID'] ?? 0);
$actorName     = $meRow['full_name'] ?? $_SESSION['username'];

// Must be the configured approver
$ap = $con->prepare("SELECT approver_user_id FROM role_approver_config ORDER BY dataID DESC LIMIT 1");
$ap->execute();
$apRow = $ap->get_result()->fetch_assoc();
$ap->close();
if ($currentUserID <= 0 || $currentUserID !== (int)($apRow['approver_user_id'] ?? 0)) {
    echo json_encode(['status' => 0, 'message' => 'অনুমতি নেই']); exit;
}

// Load proposal — must be pending
$pStmt = $con->prepare("SELECT * FROM role_assignment_proposal WHERE dataID = ? LIMIT 1");
$pStmt->bind_param("i", $proposalID);
$pStmt->execute();
$prop = $pStmt->get_result()->fetch_assoc();
$pStmt->close();
if (!$prop) {
    echo json_encode(['status' => 0, 'message' => 'প্রস্তাব পাওয়া যায়নি']); exit;
}
if ((int)$prop['status'] !== 0) {
    echo json_encode(['status' => 0, 'message' => 'এই প্রস্তাব ইতিমধ্যে সিদ্ধান্ত নেওয়া হয়েছে']); exit;
}

if ($action === 'reject') {
    // Mark rejected + log
    $u = $con->prepare(
        "UPDATE role_assignment_proposal
         SET status = 2, approver_note = ?, decided_at = NOW()
         WHERE dataID = ?"
    );
    $u->bind_param("si", $note, $proposalID);
    $u->execute();
    $u->close();

    $log = $con->prepare(
        "INSERT INTO role_assignment_log
         (proposal_id, action, actor_user_id, actor_name, target_employee_id,
          organization_id, role_id, note)
         VALUES (?, 'rejected', ?, ?, ?, ?, ?, ?)"
    );
    $orgID = (int)$prop['organization_id'];
    $roleID = (int)$prop['role_id'];
    $empID = (int)$prop['employee_id'];
    $log->bind_param("iisiiis", $proposalID, $currentUserID, $actorName, $empID, $orgID, $roleID, $note);
    $log->execute();
    $log->close();

    if (function_exists('audit_log')) {
        audit_log('role_rejected', [
            'target_type'     => 'role_proposal',
            'target_id'       => $proposalID,
            'organization_id' => $orgID,
            'note'            => 'role_id=' . $roleID . '; employee_id=' . $empID . '; reason=' . mb_substr($note, 0, 200),
        ]);
    }

    echo json_encode(['status' => 1, 'message' => 'প্রস্তাব প্রত্যাখ্যান করা হয়েছে']);
    exit;
}

// ── action === 'approve' ─────────────────────────────────────────────
mysqli_begin_transaction($con);
try {
    $employeeID = (int)$prop['employee_id'];
    $roleID     = (int)$prop['role_id'];
    $orgID      = (int)$prop['organization_id'];
    $targetUID  = (int)($prop['target_user_id'] ?? 0);

    if ($targetUID > 0) {
        // ── EXISTING-USER PATH: just add the role to user_group_assignment ──
        // Verify the user still exists
        $vu = $con->prepare("SELECT dataID FROM user_list WHERE dataID = ? LIMIT 1");
        $vu->bind_param("i", $targetUID);
        $vu->execute();
        if (!$vu->get_result()->fetch_assoc()) {
            $vu->close();
            throw new RuntimeException('লক্ষ্যকৃত user আর সক্রিয় নেই');
        }
        $vu->close();
        $newUserID = $targetUID;
    } else {
        // ── NEW-USER PATH: create user_list row, then assign role ──
        $usernameToCreate    = $prop['proposed_username'];
        $newPwdAlreadyHashed = $prop['proposed_password']; // hashed at proposal time
        $employeeName        = $prop['proposed_full_name'];

        // Username re-check (might have been claimed between proposal + approval)
        $un = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
        $un->bind_param("s", $usernameToCreate);
        $un->execute();
        if ($un->get_result()->fetch_assoc()) {
            $un->close();
            throw new RuntimeException('প্রস্তাবিত ইউজারনেম ইতিমধ্যে অন্য কেউ ব্যবহার করছে — প্রস্তাব আপডেট করতে হবে');
        }
        $un->close();

        // Fetch designation for parity with legacy form
        $emp = $con->prepare(
            "SELECT jt.job_title_name FROM employee_list el
             LEFT JOIN job_title jt ON el.designation = jt.id
             WHERE el.id = ? LIMIT 1"
        );
        $emp->bind_param("i", $employeeID);
        $emp->execute();
        $empRow = $emp->get_result()->fetch_assoc();
        $emp->close();
        $designation = $empRow['job_title_name'] ?? '';

        // NOT setting isCenterAdmin — Regional Super/Op Admin have employee records,
        // they log in via the normal path. user_group_id = role_id (active group).
        // 7 placeholders → types "sisssii": s full_name, i employee_id, s designation,
        // s user_id, s password, i organization_id, i user_group_id
        $createUser = $con->prepare(
            "INSERT INTO user_list
             (full_name, employee_id, designation, user_id, password, user_type, dashboardType, organization_id, user_group_id, isCenterAdmin)
             VALUES (?, ?, ?, ?, ?, 2, 1, ?, ?, 0)"
        );
        $createUser->bind_param("sisssii",
            $employeeName, $employeeID, $designation, $usernameToCreate, $newPwdAlreadyHashed, $orgID, $roleID
        );
        $createUser->execute();
        $newUserID = $createUser->insert_id;
        $createUser->close();
        if ($newUserID <= 0) {
            throw new RuntimeException('User তৈরি করা যায়নি');
        }
    }

    // End previous active tenure for this role+center (effective_to = NOW).
    // Skip the row we're about to insert/match by user — handled via != $newUserID.
    $endPrev = $con->prepare(
        "UPDATE user_group_assignment uga
         INNER JOIN user_list ul ON uga.user_id = ul.dataID
         INNER JOIN employee_list el ON ul.employee_id = el.id
         SET uga.effective_to = NOW()
         WHERE uga.group_id = ?
           AND uga.effective_to IS NULL
           AND el.organization_id = ?
           AND uga.user_id != ?"
    );
    $endPrev->bind_param("iii", $roleID, $orgID, $newUserID);
    $endPrev->execute();
    $endPrev->close();

    // Insert new assignment row (active tenure begins).
    // is_default=1 for newly created user (this is their only role).
    // is_default=0 for existing user — preserve their existing default role.
    $isDefault = ($targetUID > 0) ? 0 : 1;
    $assign = $con->prepare(
        "INSERT INTO user_group_assignment
         (user_id, group_id, is_default, effective_from, proposal_id, attachment)
         VALUES (?, ?, ?, NOW(), ?, ?)"
    );
    $assign->bind_param("iiiis", $newUserID, $roleID, $isDefault, $proposalID, $prop['attachment']);
    $assign->execute();
    $assign->close();

    // 6. Mark proposal approved
    $upd = $con->prepare(
        "UPDATE role_assignment_proposal
         SET status = 1, approver_note = ?, decided_at = NOW()
         WHERE dataID = ?"
    );
    $upd->bind_param("si", $note, $proposalID);
    $upd->execute();
    $upd->close();

    // 7. Log entry
    $log = $con->prepare(
        "INSERT INTO role_assignment_log
         (proposal_id, action, actor_user_id, actor_name, target_user_id, target_employee_id,
          organization_id, role_id, note)
         VALUES (?, 'approved', ?, ?, ?, ?, ?, ?, ?)"
    );
    $log->bind_param("iisiiiis",
        $proposalID, $currentUserID, $actorName, $newUserID, $employeeID, $orgID, $roleID, $note
    );
    $log->execute();
    $log->close();

    mysqli_commit($con);

    if (function_exists('audit_log')) {
        audit_log('role_approved', [
            'target_type'     => 'role_proposal',
            'target_id'       => $proposalID,
            'organization_id' => $orgID,
            'note'            => 'role_id=' . $roleID . '; employee_id=' . $employeeID . '; user_id=' . $newUserID . ($targetUID > 0 ? ' (existing user)' : ' (new user created)'),
        ]);
    }

    echo json_encode(['status' => 1, 'message' => 'প্রস্তাব অনুমোদিত হয়েছে — নতুন অ্যাকাউন্ট সক্রিয়']);
} catch (Throwable $e) {
    mysqli_rollback($con);
    echo json_encode(['status' => 0, 'message' => 'অনুমোদন ব্যর্থ: ' . $e->getMessage()]);
}
