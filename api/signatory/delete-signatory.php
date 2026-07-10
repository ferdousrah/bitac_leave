<?php
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

$dataID = intval($_POST['dataID'] ?? 0);
if (!$dataID) {
    echo json_encode(['status' => 0, 'message' => 'Invalid ID']);
    exit;
}

// Get center before deleting
$centerRes = mysqli_query($con, "SELECT organization_id FROM leave_approval_signatory WHERE dataID='$dataID' LIMIT 1");
$centerRow = mysqli_fetch_assoc($centerRes);
$orgId = (int)($centerRow['organization_id'] ?? 0);

// Delete
$del = mysqli_query($con, "DELETE FROM leave_approval_signatory WHERE dataID='$dataID'");
if (!$del) {
    echo json_encode(['status' => 0, 'message' => mysqli_error($con)]);
    exit;
}

// Reshuffle approvalSL for remaining signatories of this center
if ($orgId) {
    reshuffleApprovalSL($con, $orgId);
}

echo json_encode(['status' => 1]);

// ── Helper ──────────────────────────────────────────────────────────────────
function reshuffleApprovalSL($con, $orgId) {
    $rows = mysqli_query($con,
        "SELECT dataID FROM leave_approval_signatory
         WHERE organization_id='$orgId'
         ORDER BY approvalSL ASC"
    );
    $sl = 2; // supervisor is always 1; signatory chain starts at 2
    while ($r = mysqli_fetch_assoc($rows)) {
        mysqli_query($con, "UPDATE leave_approval_signatory SET approvalSL='$sl' WHERE dataID='{$r['dataID']}'");
        $sl++;
    }
}
