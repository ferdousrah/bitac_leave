<?php
/**
 * signatory_route_helper.php
 *
 * Resolves which signatory route applies for a given employee + leave type.
 *
 * Usage:
 *   require_once __DIR__ . '/signatory_route_helper.php';
 *   $route = resolveSignatoryRoute($con, $employeeId, $leaveTypeId);
 *   // returns: 'center_only' | 'center_then_hq' | 'hq_only' | null (no rule found)
 *
 * Matching priority:
 *   1. grade match + specific leave_type match  (most specific - wins first)
 *   2. grade match + leave_type_id IS NULL       (fallback for that grade)
 */

// Ensure leave_signatory_rule table exists (safe to call multiple times)
if (isset($con)) {
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS leave_signatory_rule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grades TEXT NOT NULL COMMENT 'Comma-separated grade IDs',
        leave_type_id INT DEFAULT NULL COMMENT 'NULL = applies to all leave types',
        route ENUM('center_only','center_then_hq','hq_only') NOT NULL,
        description VARCHAR(255) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Auto-migrate: ensure hq_approval_required column exists in leave_signatory_rule
    $_snap_lsr = mysqli_query($con, "SHOW COLUMNS FROM leave_signatory_rule");
    $_snap_lsr_cols = [];
    while ($_r = mysqli_fetch_assoc($_snap_lsr)) { $_snap_lsr_cols[] = $_r['Field']; }
    if (!in_array('hq_approval_required', $_snap_lsr_cols))
        mysqli_query($con, "ALTER TABLE leave_signatory_rule ADD COLUMN `hq_approval_required` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=HQ must approve after center, 0=center approval is sufficient'");

    // Auto-migrate: ensure org snapshot columns exist in leave_applications
    $_snap_la = mysqli_query($con, "SHOW COLUMNS FROM leave_applications");
    $_snap_la_cols = [];
    while ($_r = mysqli_fetch_assoc($_snap_la)) { $_snap_la_cols[] = $_r['Field']; }
    $_snap_la_add = [];
    if (!in_array('department_id',  $_snap_la_cols)) $_snap_la_add[] = "ADD COLUMN `department_id` INT DEFAULT NULL";
    if (!in_array('section_id',     $_snap_la_cols)) $_snap_la_add[] = "ADD COLUMN `section_id` INT DEFAULT NULL";
    if (!in_array('designation_id', $_snap_la_cols)) $_snap_la_add[] = "ADD COLUMN `designation_id` INT DEFAULT NULL";
    if (!in_array('pay_scale',      $_snap_la_cols)) $_snap_la_add[] = "ADD COLUMN `pay_scale` VARCHAR(50) DEFAULT NULL";
    if (!empty($_snap_la_add)) mysqli_query($con, "ALTER TABLE leave_applications " . implode(', ', $_snap_la_add));

    // Auto-migrate: ensure org snapshot columns exist in leave_data_for_approval
    $_snap_lda = mysqli_query($con, "SHOW COLUMNS FROM leave_data_for_approval");
    $_snap_lda_cols = [];
    while ($_r = mysqli_fetch_assoc($_snap_lda)) { $_snap_lda_cols[] = $_r['Field']; }
    $_snap_lda_add = [];
    if (!in_array('organization_id', $_snap_lda_cols)) $_snap_lda_add[] = "ADD COLUMN `organization_id` INT DEFAULT NULL";
    if (!in_array('department_id',   $_snap_lda_cols)) $_snap_lda_add[] = "ADD COLUMN `department_id` INT DEFAULT NULL";
    if (!in_array('section_id',      $_snap_lda_cols)) $_snap_lda_add[] = "ADD COLUMN `section_id` INT DEFAULT NULL";
    if (!in_array('designation_id',  $_snap_lda_cols)) $_snap_lda_add[] = "ADD COLUMN `designation_id` INT DEFAULT NULL";
    if (!in_array('pay_scale',       $_snap_lda_cols)) $_snap_lda_add[] = "ADD COLUMN `pay_scale` VARCHAR(50) DEFAULT NULL";
    if (!empty($_snap_lda_add)) mysqli_query($con, "ALTER TABLE leave_data_for_approval " . implode(', ', $_snap_lda_add));
}

/**
 * Internal: returns the full matched rule row (array) or null.
 */
function _resolveSignatoryRule($con, $employeeId, $leaveTypeId) {
    $empStmt = mysqli_prepare($con, "SELECT pay_scale FROM employee_list WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($empStmt, 'i', $employeeId);
    mysqli_stmt_execute($empStmt);
    $empResult = mysqli_stmt_get_result($empStmt);
    $emp = mysqli_fetch_assoc($empResult);

    if (!$emp || !$emp['pay_scale']) return null;
    $payScale = (int)$emp['pay_scale'];

    $rulesQ = mysqli_query($con, "SELECT * FROM leave_signatory_rule ORDER BY id ASC");
    $specificMatch = null;
    $fallbackMatch = null;
    while ($rule = mysqli_fetch_assoc($rulesQ)) {
        $ruleGrades = array_map('intval', array_filter(explode(',', $rule['grades'])));
        if (!in_array($payScale, $ruleGrades)) continue;

        if (!is_null($rule['leave_type_id']) && (int)$rule['leave_type_id'] === (int)$leaveTypeId) {
            $specificMatch = $rule;
        } elseif (is_null($rule['leave_type_id']) && $fallbackMatch === null) {
            $fallbackMatch = $rule;
        }
    }
    return $specificMatch ?? $fallbackMatch;
}

/** Public: returns just the route string (or null) — kept for backward compatibility. */
function resolveSignatoryRoute($con, $employeeId, $leaveTypeId) {
    $rule = _resolveSignatoryRule($con, $employeeId, $leaveTypeId);
    return $rule ? $rule['route'] : null;
}


/**
 * Returns the ordered list of signatory employee IDs for an application.
 *
 * @param mysqli $con
 * @param int    $applicantId   employee_list.id of the applicant
 * @param int    $leaveTypeId   leave_types.leaveID
 * @return array  [ ['employeeID' => X, 'isMandatory' => 1, 'scope' => 'center'|'hq'], ... ]
 */
function buildSignatoryChain($con, $applicantId, $leaveTypeId) {
    $rule = _resolveSignatoryRule($con, $applicantId, $leaveTypeId);
    if (!$rule) return [];

    $route         = $rule['route'];
    // hq_approval_required: 1 = HQ signatories must approve after center (default)
    //                        0 = center approval is sufficient; skip HQ signatories
    $hqRequired    = (int)($rule['hq_approval_required'] ?? 1);

    // Get applicant's center
    $empStmt = mysqli_prepare($con, "SELECT organization_id FROM employee_list WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($empStmt, 'i', $applicantId);
    mysqli_stmt_execute($empStmt);
    $emp      = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));
    $centerId = (int)($emp['organization_id'] ?? 0);

    $chain = [];

    // Fetch center signatories
    if (in_array($route, ['center_only', 'center_then_hq'])) {
        // Decide whether to restrict to employees whose org = center:
        //   center_only              → always restrict (no HQ employees, even if manually added)
        //   center_then_hq + hq=1   → restrict; the full HQ list is appended separately below
        //   center_then_hq + hq=0   → do NOT restrict; HQ employees added to the center's chain
        //                             are the explicitly chosen HQ contacts (custom chain mode)
        $restrictToCenter = ($route === 'center_only') || ($route === 'center_then_hq' && $hqRequired === 1);

        $cSQL = "SELECT las.employeeID, las.isMandatory
                 FROM leave_approval_signatory las
                 INNER JOIN employee_list el ON las.employeeID = el.id
                 WHERE las.organization_id = ?";
        if ($restrictToCenter) {
            $cSQL .= " AND el.organization_id = ?";
        }
        $cSQL .= " ORDER BY las.approvalSL ASC";

        $cStmt = mysqli_prepare($con, $cSQL);
        if ($restrictToCenter) {
            mysqli_stmt_bind_param($cStmt, 'ii', $centerId, $centerId);
        } else {
            mysqli_stmt_bind_param($cStmt, 'i', $centerId);
        }
        mysqli_stmt_execute($cStmt);
        $cResult = mysqli_stmt_get_result($cStmt);
        while ($row = mysqli_fetch_assoc($cResult)) {
            $chain[] = [
                'employeeID'  => (int)$row['employeeID'],
                'isMandatory' => (int)$row['isMandatory'],
                'scope'       => 'center',
            ];
        }
    }

    // Fetch HQ signatories (organization_id = 4 = HQ)
    // For center_then_hq: only include HQ if hq_approval_required = 1
    // For hq_only: always include HQ regardless
    $includeHQ = ($route === 'hq_only') || ($route === 'center_then_hq' && $hqRequired === 1);
    if ($includeHQ) {
        $hqStmt = mysqli_prepare($con,
            "SELECT las.employeeID, las.isMandatory
             FROM leave_approval_signatory las
             INNER JOIN employee_list el ON las.employeeID = el.id
             WHERE las.organization_id = 4
             ORDER BY las.approvalSL ASC"
        );
        mysqli_stmt_execute($hqStmt);
        $hqResult = mysqli_stmt_get_result($hqStmt);
        while ($row = mysqli_fetch_assoc($hqResult)) {
            $chain[] = [
                'employeeID'  => (int)$row['employeeID'],
                'isMandatory' => (int)$row['isMandatory'],
                'scope'       => 'hq',
            ];
        }
    }

    // Nobody approves their own application. A signatory who applies would
    // otherwise land in their own chain, since the configured list says nothing
    // about who is applying. Filtering here covers every flow at once — leave,
    // joining, edit-approval and optional pre-approval all call this with the
    // applicant id. Note the test is against the *applicant*, never the
    // supervisor: one person legitimately holds both the supervisor seat and a
    // chain seat, and that is exactly the intended route for a signatory's own
    // leave (কেন্দ্র প্রধান recommends, then approves as first signatory).
    if ($applicantId > 0) {
        $chain = array_values(array_filter($chain, function ($entry) use ($applicantId) {
            return (int)$entry['employeeID'] !== (int)$applicantId;
        }));
    }

    return $chain;
}

/**
 * Builds a joining letter's chain from the chain the leave actually used.
 *
 * Rebuilding from today's config would throw away every decision taken for
 * that specific application — a DG escalation, a desk the admin added, the
 * applicant's own seat being dropped — so the joining could travel a different
 * route than the leave did. Copying alone has the opposite problem: months can
 * pass between taking leave and joining, and by then the কেন্দ্র প্রধান may be
 * someone else entirely.
 *
 * So: copy the shape, refresh the people. leave_data_for_approval snapshots the
 * seat (organization_id, designation_id) next to the person, which is what makes
 * "same post, current holder" resolvable.
 *
 * @return array{chain: array, unresolved: array} chain entries carry
 *         `substituted` (person changed) and `seatDesignation` for reporting.
 */
function buildJoiningChainFromLeave($con, $leaveApplicationID, $applicantId)
{
    $leaveApplicationID = (int)$leaveApplicationID;
    $applicantId        = (int)$applicantId;

    $rows = [];
    $q = mysqli_query($con,
        "SELECT signatory, serial, organization_id, designation_id
         FROM leave_data_for_approval
         WHERE leaveApplicationID = $leaveApplicationID
           AND isSupervisor = 0
         ORDER BY serial ASC, dataID ASC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;

    $chain      = [];
    $unresolved = [];
    $seen       = [];

    foreach ($rows as $r) {
        $storedId  = (int)$r['signatory'];
        $seatOrg   = (int)$r['organization_id'];
        $seatDesig = (int)$r['designation_id'];

        $useId       = 0;
        $substituted = false;

        // Is the person who approved the leave still able to act here?
        if ($storedId > 0) {
            $chk = mysqli_query($con,
                "SELECT id FROM employee_list
                 WHERE id = $storedId
                   AND employment_status = 1
                   AND pending_section_assignment = 0"
                . ($seatOrg > 0 ? " AND organization_id = $seatOrg" : '')
                . " LIMIT 1");
            if ($chk && mysqli_num_rows($chk) > 0) $useId = $storedId;
        }

        // Gone or moved — hand the seat to whoever holds that post now.
        if ($useId === 0 && $seatOrg > 0 && $seatDesig > 0) {
            $sub = mysqli_query($con,
                "SELECT id FROM employee_list
                 WHERE organization_id = $seatOrg
                   AND designation     = $seatDesig
                   AND employment_status = 1
                   AND pending_section_assignment = 0
                 ORDER BY display_order ASC
                 LIMIT 1");
            if ($sub && $subRow = mysqli_fetch_assoc($sub)) {
                $useId       = (int)$subRow['id'];
                $substituted = true;
            }
        }

        if ($useId === 0) {
            $unresolved[] = ['seatOrg' => $seatOrg, 'seatDesignation' => $seatDesig];
            continue;
        }
        // Belt and braces — the leave chain already excluded the applicant, but a
        // substitution could land on them if they now hold the post.
        if ($useId === $applicantId) continue;
        if (isset($seen[$useId])) continue;   // substitution collapsed onto an existing step
        $seen[$useId] = true;

        $chain[] = [
            'employeeID'      => $useId,
            'isMandatory'     => 0,
            'scope'           => 'inherited',
            'substituted'     => $substituted,
            'seatDesignation' => $seatDesig,
        ];
    }

    return ['chain' => $chain, 'unresolved' => $unresolved];
}
