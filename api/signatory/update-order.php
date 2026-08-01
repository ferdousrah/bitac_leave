<?php
require_once(__DIR__ . '/../../config/connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order = $_POST['order']; // Receive the order array from the request

    foreach ($order as $row) {
        $dataID = intval($row['id']);
        $approvalSL = intval($row['approvalSL']);

        // Update the approvalSL in the database
        $stmt = $con->prepare("UPDATE leave_approval_signatory SET approvalSL = ? WHERE dataID = ? and isMandatory=1");
        $stmt->bind_param("ii", $approvalSL, $dataID);
        $stmt->execute();
    }

    if (function_exists('audit_log')) {
        audit_log('signatory_order_reshuffled', [
            'target_type' => 'leave_approval_signatory',
            'target_id'   => 0,
            'note'        => 'rows=' . count($order),
        ]);
    }
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
}
?>
