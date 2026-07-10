<?php
/**
 * Employee lifecycle helpers
 * - Probationary temp ID generation (P-YYYY-NNN pattern)
 * - Transfer recording with history side-effects
 * - Promotion to permanent
 *
 * See memory: [[employee_lifecycle_design]]
 */

if (!function_exists('bitac_next_probationary_id')) {
    /**
     * Generate the next available probationary temp ID: P-YYYY-NNN.
     * Uses the current Bangladesh year (which we treat as $bnYear via date('Y')).
     *
     * Returns string like "P-2026-007". Uniqueness is checked against
     * employee_list.employee_id; retries if collision.
     */
    function bitac_next_probationary_id(mysqli $con) {
        $year = date('Y');
        $prefix = "P-$year-";

        // Find the highest existing suffix for this year
        $stmt = mysqli_prepare($con,
            "SELECT employee_id FROM employee_list
             WHERE employee_id LIKE ?
             ORDER BY employee_id DESC LIMIT 1");
        $likePattern = $prefix . '%';
        mysqli_stmt_bind_param($stmt, 's', $likePattern);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $next = 1;
        if ($row && preg_match('/P-\d{4}-(\d+)$/', $row['employee_id'], $m)) {
            $next = (int)$m[1] + 1;
        }

        // Generate + ensure uniqueness (defensive against parallel inserts)
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $prefix . str_pad((string)($next + $attempt), 3, '0', STR_PAD_LEFT);
            $chk = mysqli_prepare($con, "SELECT id FROM employee_list WHERE employee_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $candidate);
            mysqli_stmt_execute($chk);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
            mysqli_stmt_close($chk);
            if (!$exists) return $candidate;
        }

        // Fallback if all 20 candidates collided (unlikely)
        return $prefix . 'X' . dechex(time() % 0xFFFF);
    }
}

if (!function_exists('bitac_record_transfer')) {
    /**
     * Record an employee transfer and update employee_list.organization_id.
     *
     * @param mysqli   $con
     * @param int      $employeeRefID    employee_list.id
     * @param int      $newOrgID         organization.id (target center)
     * @param string   $transferDate     'YYYY-MM-DD'
     * @param array    $opts             { order_number, order_date, reason, attachment, createdBy }
     * @return array   ['status' => 1|0, 'message' => string, 'history_id' => int|null]
     */
    function bitac_record_transfer(mysqli $con, int $employeeRefID, int $newOrgID, string $transferDate, array $opts = []) {
        if ($employeeRefID <= 0)  return ['status' => 0, 'message' => 'অবৈধ কর্মচারী আইডি'];
        if ($newOrgID <= 0)       return ['status' => 0, 'message' => 'অবৈধ কেন্দ্র আইডি'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transferDate)) {
            return ['status' => 0, 'message' => 'অবৈধ তারিখ ফরম্যাট'];
        }

        // Load current org
        $cur = mysqli_prepare($con, "SELECT organization_id FROM employee_list WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($cur, 'i', $employeeRefID);
        mysqli_stmt_execute($cur);
        $curRow = mysqli_fetch_assoc(mysqli_stmt_get_result($cur));
        mysqli_stmt_close($cur);
        if (!$curRow) return ['status' => 0, 'message' => 'কর্মচারী পাওয়া যায়নি'];

        $fromOrg = (int)$curRow['organization_id'];
        if ($fromOrg === $newOrgID) {
            return ['status' => 0, 'message' => 'কর্মচারী ইতিমধ্যে এই কেন্দ্রে আছেন'];
        }

        $orderNo   = $opts['order_number'] ?? null;
        $orderDate = $opts['order_date']   ?? null;
        $reason    = $opts['reason']       ?? null;
        $attach    = $opts['attachment']   ?? null;
        $by        = (int)($opts['createdBy'] ?? 0);

        mysqli_autocommit($con, false);
        try {
            // 1. Close out the most recent open posting (effective_to = transferDate - 1)
            $closeStmt = mysqli_prepare($con,
                "UPDATE employee_transfer_history
                 SET effective_to = DATE_SUB(?, INTERVAL 1 DAY)
                 WHERE employee_ref_id = ? AND effective_to IS NULL
                 ORDER BY transfer_date DESC LIMIT 1");
            mysqli_stmt_bind_param($closeStmt, 'si', $transferDate, $employeeRefID);
            mysqli_stmt_execute($closeStmt);
            mysqli_stmt_close($closeStmt);

            // 2. Insert new posting row
            $insStmt = mysqli_prepare($con,
                "INSERT INTO employee_transfer_history
                 (employee_ref_id, from_organization_id, to_organization_id, transfer_date,
                  order_number, order_date, reason, attachment, createdBy)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insStmt, 'iiisssssi',
                $employeeRefID, $fromOrg, $newOrgID, $transferDate,
                $orderNo, $orderDate, $reason, $attach, $by);
            if (!mysqli_stmt_execute($insStmt)) {
                throw new Exception('History insert failed: ' . mysqli_error($con));
            }
            $historyID = mysqli_insert_id($con);
            mysqli_stmt_close($insStmt);

            // 3. Update employee_list: new org + clear section + mark pending assignment
            // section_id is cleared because the receiving center decides the section.
            // pending_section_assignment=1 keeps the employee out of active workflows
            // until the receiving center assigns a section.
            $updStmt = mysqli_prepare($con,
                "UPDATE employee_list
                 SET organization_id = ?,
                     section_id = NULL,
                     pending_section_assignment = 1
                 WHERE id = ?");
            mysqli_stmt_bind_param($updStmt, 'ii', $newOrgID, $employeeRefID);
            if (!mysqli_stmt_execute($updStmt)) {
                throw new Exception('employee_list update failed');
            }
            mysqli_stmt_close($updStmt);

            mysqli_commit($con);
            mysqli_autocommit($con, true);

            if (function_exists('audit_log')) {
                audit_log('employee_transferred', [
                    'target_type'     => 'employee',
                    'target_id'       => $employeeRefID,
                    'organization_id' => $newOrgID,
                    'note'            => "from=$fromOrg; to=$newOrgID; date=$transferDate"
                                       . ($orderNo ? "; order=$orderNo" : ''),
                ]);
            }

            return ['status' => 1, 'message' => 'বদলি সফলভাবে রেকর্ড হয়েছে', 'history_id' => $historyID];

        } catch (Exception $e) {
            mysqli_rollback($con);
            mysqli_autocommit($con, true);
            return ['status' => 0, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('bitac_assign_section_after_transfer')) {
    /**
     * Receiving-center workflow: assign section + actual joining date to an
     * employee whose pending_section_assignment flag is set after a transfer.
     *
     * @param mysqli $con
     * @param int    $employeeRefID         employee_list.id
     * @param int    $sectionID             sections.id (within the new center)
     * @param string $actualJoiningDate     'YYYY-MM-DD' (may equal transfer_date)
     * @param int    $actorUserID           user_list.dataID performing the assignment
     * @return array ['status' => 1|0, 'message' => string]
     */
    function bitac_assign_section_after_transfer(mysqli $con, int $employeeRefID, int $sectionID, string $actualJoiningDate, int $actorUserID = 0) {
        if ($employeeRefID <= 0) return ['status' => 0, 'message' => 'অবৈধ কর্মচারী আইডি'];
        if ($sectionID <= 0)     return ['status' => 0, 'message' => 'অবৈধ সেকশন'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $actualJoiningDate)) {
            return ['status' => 0, 'message' => 'অবৈধ তারিখ ফরম্যাট'];
        }

        // Verify employee + pending state + section belongs to employee's current center
        $empStmt = mysqli_prepare($con,
            "SELECT e.id, e.organization_id, e.pending_section_assignment, s.organization_id AS sec_org
             FROM employee_list e
             LEFT JOIN sections s ON s.id = ?
             WHERE e.id = ? LIMIT 1");
        mysqli_stmt_bind_param($empStmt, 'ii', $sectionID, $employeeRefID);
        mysqli_stmt_execute($empStmt);
        $emp = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));
        mysqli_stmt_close($empStmt);
        if (!$emp) return ['status' => 0, 'message' => 'কর্মচারী পাওয়া যায়নি'];
        if ((int)$emp['pending_section_assignment'] !== 1) {
            return ['status' => 0, 'message' => 'এই কর্মচারীর সেকশন বরাদ্দ অপেক্ষমান নয়'];
        }
        // Allow global sections (organization_id = 0) or sections explicitly scoped to this org
        $secOrg = (int)$emp['sec_org'];
        $empOrg = (int)$emp['organization_id'];
        if ($secOrg !== 0 && $secOrg !== $empOrg) {
            return ['status' => 0, 'message' => 'নির্বাচিত সেকশন এই কেন্দ্রের নয়'];
        }

        mysqli_autocommit($con, false);
        try {
            // 1. Update employee_list: assign section + clear pending flag
            $upd = mysqli_prepare($con,
                "UPDATE employee_list
                 SET section_id = ?, pending_section_assignment = 0
                 WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'ii', $sectionID, $employeeRefID);
            if (!mysqli_stmt_execute($upd)) throw new Exception('employee update failed');
            mysqli_stmt_close($upd);

            // 2. Update the latest open transfer_history row with assignment metadata
            $upd2 = mysqli_prepare($con,
                "UPDATE employee_transfer_history
                 SET section_id_at_join = ?,
                     actual_joining_date = ?,
                     section_assigned_at = NOW(),
                     section_assigned_by = ?
                 WHERE employee_ref_id = ? AND effective_to IS NULL
                 ORDER BY transfer_date DESC LIMIT 1");
            mysqli_stmt_bind_param($upd2, 'isii', $sectionID, $actualJoiningDate, $actorUserID, $employeeRefID);
            if (!mysqli_stmt_execute($upd2)) throw new Exception('history update failed');
            mysqli_stmt_close($upd2);

            mysqli_commit($con);
            mysqli_autocommit($con, true);

            if (function_exists('audit_log')) {
                audit_log('employee_section_assigned_after_transfer', [
                    'target_type'     => 'employee',
                    'target_id'       => $employeeRefID,
                    'organization_id' => (int)$emp['organization_id'],
                    'note'            => "section=$sectionID; joining=$actualJoiningDate",
                ]);
            }

            return ['status' => 1, 'message' => 'সেকশন বরাদ্দ সম্পন্ন হয়েছে'];

        } catch (Exception $e) {
            mysqli_rollback($con);
            mysqli_autocommit($con, true);
            return ['status' => 0, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('bitac_promote_to_permanent')) {
    /**
     * Promote a probationary employee to permanent.
     *
     * @param mysqli $con
     * @param int    $employeeRefID
     * @param string $permanentEmpID  BITAC permanent ID (string)
     * @param string $permanentDate   'YYYY-MM-DD'
     * @param int    $actorUserID
     * @return array ['status' => 1|0, 'message' => string]
     */
    function bitac_promote_to_permanent(mysqli $con, int $employeeRefID, string $permanentEmpID, string $permanentDate, int $actorUserID = 0) {
        if ($employeeRefID <= 0) return ['status' => 0, 'message' => 'অবৈধ কর্মচারী আইডি'];
        $permanentEmpID = trim($permanentEmpID);
        if ($permanentEmpID === '') return ['status' => 0, 'message' => 'Permanent ID আবশ্যক'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $permanentDate)) {
            return ['status' => 0, 'message' => 'অবৈধ তারিখ ফরম্যাট'];
        }

        // Verify employee exists + is probationary
        $stmt = mysqli_prepare($con,
            "SELECT id, employment_type, employee_id FROM employee_list WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $employeeRefID);
        mysqli_stmt_execute($stmt);
        $emp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$emp) return ['status' => 0, 'message' => 'কর্মচারী পাওয়া যায়নি'];
        if ($emp['employment_type'] !== 'probationary') {
            return ['status' => 0, 'message' => 'এই কর্মচারী ইতিমধ্যে স্থায়ী'];
        }

        // Ensure the new permanent ID isn't already taken
        $dupStmt = mysqli_prepare($con,
            "SELECT id FROM employee_list WHERE employee_id = ? AND id <> ? LIMIT 1");
        mysqli_stmt_bind_param($dupStmt, 'si', $permanentEmpID, $employeeRefID);
        mysqli_stmt_execute($dupStmt);
        $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));
        mysqli_stmt_close($dupStmt);
        if ($dup) return ['status' => 0, 'message' => 'এই Permanent ID ইতিমধ্যে ব্যবহার হয়েছে'];

        $tempID = $emp['employee_id']; // archive old temp ID

        $upd = mysqli_prepare($con,
            "UPDATE employee_list
             SET employment_type = 'permanent',
                 employee_id = ?,
                 permanent_emp_id = ?,
                 permanent_from_date = ?
             WHERE id = ?");
        mysqli_stmt_bind_param($upd, 'sssi', $permanentEmpID, $permanentEmpID, $permanentDate, $employeeRefID);
        if (!mysqli_stmt_execute($upd)) {
            mysqli_stmt_close($upd);
            return ['status' => 0, 'message' => 'আপডেট ব্যর্থ'];
        }
        mysqli_stmt_close($upd);

        if (function_exists('audit_log')) {
            audit_log('employee_promoted_to_permanent', [
                'target_type' => 'employee',
                'target_id'   => $employeeRefID,
                'note'        => "temp_id=$tempID; permanent_id=$permanentEmpID; date=$permanentDate",
            ]);
        }

        return ['status' => 1, 'message' => 'কর্মচারী স্থায়ী হিসেবে নিবন্ধিত হয়েছেন'];
    }
}
