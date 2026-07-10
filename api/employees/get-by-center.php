<?php
require_once(__DIR__ . '/../../config/connection.php');

$orgId = intval($_GET['organization_id'] ?? 0);
$options = '<option value="0">-- কর্মকর্তা/কর্মচারী নির্বাচন করুন --</option>';

if ($orgId) {
    $stmt = mysqli_prepare($con,
        "SELECT el.id, el.employee_name, el.employee_id, jt.job_title_name
         FROM employee_list el
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE el.organization_id = ? AND el.employment_status = 1 AND el.pending_section_assignment = 0
         ORDER BY jt.job_title_name ASC, el.employee_name ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $orgId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $label = htmlspecialchars($row['employee_name']);
        if (!empty($row['job_title_name'])) $label .= ' — ' . htmlspecialchars($row['job_title_name']);
        if (!empty($row['employee_id']))    $label .= ' (' . htmlspecialchars($row['employee_id']) . ')';
        $options .= '<option value="' . $row['id'] . '">' . $label . '</option>';
    }
}

echo $options;
