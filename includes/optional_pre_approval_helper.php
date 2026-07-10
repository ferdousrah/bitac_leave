<?php
/**
 * optional_pre_approval_helper.php
 *
 * Helpers for the optional-pre-approval chain that mirrors the regular leave
 * approval flow. Loaded by all api/optional-pre-approval/*.php endpoints.
 *
 * Provides:
 *   - Auto-migration of parity columns on optional_leave_pre_approval[_signatory]
 *   - insertOptionalPreApprovalChain($con, $preApprovalID, $applicantID, $leaveTypeId)
 *     -> uses buildSignatoryChain() from signatory_route_helper.php to build
 *        the non-supervisor chain rows (isSentbyAdmin=0, waiting on admin
 *        forward before they become actionable).
 */

require_once __DIR__ . '/signatory_route_helper.php';

// --- Auto-migration -------------------------------------------------------
// Runs once per request; idempotent.
if (isset($con) && !defined('OPA_HELPER_MIGRATED')) {
    define('OPA_HELPER_MIGRATED', true);

    // Parity columns on the signatory table
    $_opaSigCols = [];
    $_r = mysqli_query($con, "SHOW COLUMNS FROM optional_leave_pre_approval_signatory");
    while ($_r && ($_row = mysqli_fetch_assoc($_r))) { $_opaSigCols[] = $_row['Field']; }
    $_opaSigAdd = [];
    if (!in_array('isSentbyAdmin', $_opaSigCols)) $_opaSigAdd[] = "ADD COLUMN `isSentbyAdmin` TINYINT(1) NOT NULL DEFAULT 0";
    if (!in_array('isForwarded',   $_opaSigCols)) $_opaSigAdd[] = "ADD COLUMN `isForwarded` TINYINT(1) NOT NULL DEFAULT 0";
    if (!in_array('isDG',          $_opaSigCols)) $_opaSigAdd[] = "ADD COLUMN `isDG` TINYINT(1) NOT NULL DEFAULT 0";
    if (!empty($_opaSigAdd)) {
        mysqli_query($con, "ALTER TABLE optional_leave_pre_approval_signatory " . implode(', ', $_opaSigAdd));
    }

    // Parent-side admin-forward columns
    $_opaCols = [];
    $_r = mysqli_query($con, "SHOW COLUMNS FROM optional_leave_pre_approval");
    while ($_r && ($_row = mysqli_fetch_assoc($_r))) { $_opaCols[] = $_row['Field']; }
    $_opaAdd = [];
    if (!in_array('admin_note',         $_opaCols)) $_opaAdd[] = "ADD COLUMN `admin_note` TEXT DEFAULT NULL";
    if (!in_array('admin_initiator',    $_opaCols)) $_opaAdd[] = "ADD COLUMN `admin_initiator` INT DEFAULT NULL";
    if (!in_array('admin_forward_date', $_opaCols)) $_opaAdd[] = "ADD COLUMN `admin_forward_date` DATETIME DEFAULT NULL";
    if (!in_array('approved_days',      $_opaCols)) $_opaAdd[] = "ADD COLUMN `approved_days` DECIMAL(4,1) DEFAULT NULL";
    if (!empty($_opaAdd)) {
        mysqli_query($con, "ALTER TABLE optional_leave_pre_approval " . implode(', ', $_opaAdd));
    }
}

/**
 * Build & persist the non-supervisor signatory chain for an optional pre-approval.
 *
 * Precondition: the supervisor row (isSupervisor=1, serial=1, prevSignatory=NULL)
 * has already been inserted by the caller. This function appends chain rows
 * starting from serial=2, chaining prevSignatory through the returned list.
 *
 * All rows are inserted with isSentbyAdmin=0 — the chain is gated until center
 * admin runs forward-to-approval.
 *
 * @param mysqli $con
 * @param int    $preApprovalID     optional_leave_pre_approval.id
 * @param int    $applicantID       employee_list.id
 * @param int    $prevSignatory     employee_list.id of the last row already
 *                                  inserted (supervisor); NULL if none.
 * @param int|null $leaveTypeId     leave_types.leaveID; NULL = default rule
 * @return int  count of rows inserted
 */
function insertOptionalPreApprovalChain($con, $preApprovalID, $applicantID, $prevSignatory, $leaveTypeId = null) {
    // Resolve the ordered chain (uses grade-based routing rules)
    $chain = buildSignatoryChain($con, (int)$applicantID, (int)$leaveTypeId);

    if (empty($chain)) {
        // Fallback: designation-based rows from the applicant's own center
        $applQ = mysqli_prepare($con, "SELECT organization_id FROM employee_list WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($applQ, 'i', $applicantID);
        mysqli_stmt_execute($applQ);
        $appl = mysqli_fetch_assoc(mysqli_stmt_get_result($applQ)) ?: [];
        mysqli_stmt_close($applQ);
        $applOrg = (int)($appl['organization_id'] ?? 0);

        $fallbackQ = mysqli_prepare($con,
            "SELECT las.employeeID, las.isMandatory
             FROM leave_approval_signatory las
             INNER JOIN employee_list el ON las.employeeID = el.id
             WHERE las.organization_id = ? AND el.employment_status = 1
             ORDER BY las.approvalSL ASC");
        mysqli_stmt_bind_param($fallbackQ, 'i', $applOrg);
        mysqli_stmt_execute($fallbackQ);
        $fbRes = mysqli_stmt_get_result($fallbackQ);
        while ($row = mysqli_fetch_assoc($fbRes)) {
            $chain[] = [
                'employeeID'  => (int)$row['employeeID'],
                'isMandatory' => (int)$row['isMandatory'],
                'scope'       => 'center',
            ];
        }
        mysqli_stmt_close($fallbackQ);
    }

    if (empty($chain)) return 0;

    // Serial starts at 2 (supervisor row was serial=1)
    $serial = 2;
    $inserted = 0;

    $sigIns = mysqli_prepare($con,
        "INSERT INTO optional_leave_pre_approval_signatory
         (preApprovalID, signatory, prevSignatory, isSupervisor, isSentbyAdmin, serial,
          organization_id, department_id, section_id, designation_id, pay_scale)
         VALUES (?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?)");

    foreach ($chain as $node) {
        $sigEmpID = (int)$node['employeeID'];
        if ($sigEmpID <= 0) continue;

        // Snapshot the signatory's current org fields
        $snapQ = mysqli_prepare($con,
            "SELECT organization_id, department_id, section_id, designation, pay_scale
             FROM employee_list WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($snapQ, 'i', $sigEmpID);
        mysqli_stmt_execute($snapQ);
        $snap = mysqli_fetch_assoc(mysqli_stmt_get_result($snapQ)) ?: [];
        mysqli_stmt_close($snapQ);

        $sOrg  = (int)($snap['organization_id'] ?? 0);
        $sDept = (int)($snap['department_id']   ?? 0);
        $sSec  = (int)($snap['section_id']      ?? 0);
        $sDes  = (int)($snap['designation']     ?? 0);
        $sPay  = (string)($snap['pay_scale']    ?? '');

        mysqli_stmt_bind_param($sigIns, 'iiiiiiiis',
            $preApprovalID, $sigEmpID, $prevSignatory, $serial,
            $sOrg, $sDept, $sSec, $sDes, $sPay);

        if (mysqli_stmt_execute($sigIns)) {
            $prevSignatory = $sigEmpID;
            $serial++;
            $inserted++;
        }
    }
    mysqli_stmt_close($sigIns);

    return $inserted;
}
