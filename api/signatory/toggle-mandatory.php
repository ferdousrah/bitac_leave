<?php
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

$dataID = intval($_POST['dataID'] ?? 0);
$value  = intval($_POST['value']  ?? 0); // 1 or 0

if (!$dataID) {
    echo json_encode(['status' => 0, 'message' => 'Invalid ID']);
    exit;
}

// Get center_id before updating
$centerRes = mysqli_query($con, "SELECT organization_id FROM leave_approval_signatory WHERE dataID='$dataID' LIMIT 1");
$centerRow = mysqli_fetch_assoc($centerRes);
$orgId = (int)($centerRow['organization_id'] ?? 0);

// Update isMandatory
$stmt = mysqli_prepare($con, "UPDATE leave_approval_signatory SET isMandatory=? WHERE dataID=?");
mysqli_stmt_bind_param($stmt, 'ii', $value, $dataID);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 0, 'message' => mysqli_error($con)]);
    exit;
}

// Reshuffle approvalSL for this center
if ($orgId) {
    $rows = mysqli_query($con,
        "SELECT dataID FROM leave_approval_signatory
         WHERE organization_id='$orgId'
         ORDER BY approvalSL ASC"
    );
    $sl = 2; // supervisor is always serial 1; chain starts at 2
    while ($r = mysqli_fetch_assoc($rows)) {
        mysqli_query($con, "UPDATE leave_approval_signatory SET approvalSL='$sl' WHERE dataID='{$r['dataID']}'");
        $sl++;
    }
}

echo json_encode(['status' => 1]);
