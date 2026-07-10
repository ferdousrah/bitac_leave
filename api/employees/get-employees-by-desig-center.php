<?php
require_once(__DIR__ . '/../../config/connection.php');

$orgId   = intval($_GET['organization_id'] ?? 0);
$desigId = intval($_GET['designation_id']  ?? 0);

$options = '<option value="0">-- নির্বাচন করুন --</option>';

if ($orgId && $desigId) {
    $stmt = mysqli_prepare($con, "SELECT id, employee_name, employee_id FROM employee_list WHERE organization_id=? AND designation=? AND employment_status=1 AND pending_section_assignment=0 ORDER BY employee_name ASC");
    mysqli_stmt_bind_param($stmt, 'ii', $orgId, $desigId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $found = false;
    while ($row = mysqli_fetch_assoc($result)) {
        $found = true;
        $label = htmlspecialchars($row['employee_name']);
        if (!empty($row['employee_id'])) $label .= ' (' . htmlspecialchars($row['employee_id']) . ')';
        $options .= '<option value="' . $row['id'] . '">' . $label . '</option>';
    }

    if (!$found) {
        $options = '<option value="0">এই কেন্দ্রে উক্ত পদে কেউ নেই</option>';
    }
}

echo $options;
