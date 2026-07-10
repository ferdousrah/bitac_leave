<?php
/**
 * Save the single global "role-assignment approver" — the HQ user who
 * approves Regional Super Admin / Regional Op. Admin proposals.
 *
 * Stored in role_approver_config (single conceptual row, we keep history
 * by inserting new rows; the most recent row is the active config).
 */
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'লগইন করা নেই']);
    exit;
}

$approverUserID = (int)($_POST['approver_user_id'] ?? 0);
if ($approverUserID <= 0) {
    echo json_encode(['status' => 0, 'message' => 'অনুমোদনকারী নির্বাচন করুন']);
    exit;
}

// HQ = বিটাক, প্রধান কার্যালয় = organization.id 4. Hard-coded constant —
// see views/signatory/role-approver.php for context. Update if HQ id changes.
const HQ_ORG_ID = 4;

$check = $con->prepare(
    "SELECT ul.dataID
     FROM user_list ul
     INNER JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.dataID = ? AND el.organization_id = ? AND el.employment_status = 1 AND el.pending_section_assignment = 0"
);
$hqOrgID = HQ_ORG_ID;
$check->bind_param("ii", $approverUserID, $hqOrgID);
$check->execute();
$ok = $check->get_result()->fetch_assoc();
$check->close();
if (!$ok) {
    echo json_encode(['status' => 0, 'message' => 'নির্বাচিত ব্যক্তি HQ (প্রধান কার্যালয়) এর সক্রিয় user নন']);
    exit;
}

// Resolve current admin's user_list.dataID for the audit field
$me = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
$me->bind_param("s", $_SESSION['username']);
$me->execute();
$meRow = $me->get_result()->fetch_assoc();
$me->close();
$updatedBy = (int)($meRow['dataID'] ?? 0);

// Insert a new config row — "most recent row is the active config" semantics.
// Keeps a paper trail of who changed the approver and when.
$ins = $con->prepare("INSERT INTO role_approver_config (approver_user_id, updated_by) VALUES (?, ?)");
$ins->bind_param("ii", $approverUserID, $updatedBy);
if (!$ins->execute()) {
    $ins->close();
    echo json_encode(['status' => 0, 'message' => 'সংরক্ষণ ব্যর্থ হয়েছে']);
    exit;
}
$ins->close();

echo json_encode(['status' => 1, 'message' => 'অনুমোদনকারী সংরক্ষণ করা হয়েছে']);
