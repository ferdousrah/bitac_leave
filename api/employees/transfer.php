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

// Authorization — Super Admin OR HQ-based user (organization_id = 4) only.
// Centralized HQ-driven workflow: receiving center cannot initiate transfers.
$actorQ = mysqli_query($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.dataID = " . (int)$_SESSION['userID'] . " LIMIT 1");
$actorRow  = $actorQ ? mysqli_fetch_assoc($actorQ) : null;
$actorGroup    = (int)($actorRow['user_group_id'] ?? 0);
$actorCenterID = (int)($actorRow['emp_org'] ?? 0);
$canTransfer   = ($actorGroup === 1) || ($actorCenterID === 4);
if (!$canTransfer) {
    echo json_encode(['status' => 0, 'message' => 'বদলি রেকর্ড করার অনুমতি নেই (Super Admin বা প্রধান কার্যালয় শুধু)']);
    exit;
}

$dataID          = (int)($_POST['dataID']            ?? 0);
$toOrgID         = (int)($_POST['to_organization_id'] ?? 0);
$transferDate    = trim($_POST['transfer_date']      ?? '');
$orderNumber     = trim($_POST['order_number']       ?? '');
$orderDate       = trim($_POST['order_date']         ?? '');
$reason          = trim($_POST['reason']             ?? '');

// Handle attachment upload
$attachment = null;
if (isset($_FILES['attachment']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(['status' => 0, 'message' => 'অনুমোদিত ফরম্যাট: JPG/PNG/PDF']);
        exit;
    }
    if ($_FILES['attachment']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 0, 'message' => 'ফাইলের আকার সর্বোচ্চ ২ MB']);
        exit;
    }
    $unique = 'transfer_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $dir = __DIR__ . '/../../uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $unique)) {
        $attachment = $unique;
    }
}

$opts = [
    'order_number' => $orderNumber !== '' ? $orderNumber : null,
    'order_date'   => $orderDate !== ''   ? $orderDate   : null,
    'reason'       => $reason !== ''      ? $reason      : null,
    'attachment'   => $attachment,
    'createdBy'    => (int)$_SESSION['userID'],
];

$result = bitac_record_transfer($con, $dataID, $toOrgID, $transferDate, $opts);
echo json_encode($result);
