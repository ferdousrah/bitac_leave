<?php
require_once(__DIR__ . '/../../config/connection.php');

$centerId = intval($_POST['center_id'] ?? 0);
$request  = $_REQUEST;

$baseSQL = "
    SELECT las.dataID, las.approvalSL, las.isMandatory, las.employeeID,
           el.employee_name AS signatory,
           el.organization_id AS emp_org_id,
           jt.job_title_name AS designation,
           org.organization_name AS emp_org_name
    FROM leave_approval_signatory las
    LEFT JOIN employee_list el  ON las.employeeID = el.id
    LEFT JOIN job_title jt      ON el.designation = jt.id
    LEFT JOIN organization org  ON el.organization_id = org.id
    WHERE las.organization_id = '$centerId'
";

$totalData     = mysqli_num_rows(mysqli_query($con, $baseSQL));
$totalFiltered = $totalData;

$sql = $baseSQL . " ORDER BY las.approvalSL ASC
    LIMIT " . intval($request['start']) . ", " . intval($request['length']);

$query = mysqli_query($con, $sql);

$data = [];
$sl   = intval($request['start']);

while ($row = mysqli_fetch_assoc($query)) {
    $sl++;

    $checked     = $row['isMandatory'] == 1 ? 'checked' : '';
    $isMandatory = "<div class='form-check form-switch mb-0'>
        <input class='form-check-input toggle-mandatory' type='checkbox' role='switch'
            data-id='{$row['dataID']}' {$checked} style='cursor:pointer;width:2.5em;height:1.3em;'>
    </div>";

    // Show org badge if employee belongs to a different org (e.g. HQ employee in a center's chain)
    $orgBadge = '';
    if (!empty($row['emp_org_id']) && (int)$row['emp_org_id'] !== $centerId) {
        $orgBadge = ' <span class="badge bg-label-info ms-1">'
            . htmlspecialchars($row['emp_org_name'] ?? 'অন্য প্রতিষ্ঠান')
            . '</span>';
    }

    $signatory   = htmlspecialchars($row['signatory']   ?? '—') . $orgBadge;
    $designation = htmlspecialchars($row['designation'] ?? '—');
    $empOrgId    = (int)($row['emp_org_id'] ?? $centerId);

    $action = "
        <button onclick=\"editSignatory({$row['dataID']}, {$row['employeeID']}, {$row['isMandatory']}, {$empOrgId})\"
            class=\"btn btn-sm btn-icon btn-label-primary me-1\" title=\"সম্পাদনা\">
            <i class=\"ti tabler-edit\"></i>
        </button>
        <button onclick=\"removeSignatory({$row['dataID']})\" data-id=\"{$row['dataID']}\"
            class=\"btn btn-sm btn-icon btn-label-danger\" title=\"মুছুন\">
            <i class=\"ti tabler-trash\"></i>
        </button>";

    $data[] = [
        'serial'      => $sl,
        'designation' => $designation,
        'signatory'   => $signatory,
        'approvalSL'  => $row['approvalSL'],
        'isMandatory' => $isMandatory,
        'action'      => $action,
        'DT_RowAttr'  => ['data-id' => $row['dataID']],
    ];
}

echo json_encode([
    'draw'            => intval($request['draw']),
    'recordsTotal'    => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data'            => $data,
]);
