<?php
// Returns per-slug pending approval counts for the current signed-in user.
// Called by footer_vuexy.php to populate sidebar submenu badges.
// All counts are "signatory-wise" — only what THIS user needs to act on.

session_start();
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/connection.php');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0]); exit;
}

// Resolve actor's employee_id + org
$ul = mysqli_prepare($con,
    "SELECT ul.employee_id, ul.user_group_id, el.organization_id AS org_id
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$un = $_SESSION['username'];
mysqli_stmt_bind_param($ul, 's', $un);
mysqli_stmt_execute($ul);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($ul)) ?: [];
mysqli_stmt_close($ul);

$myEmpID = (int)($me['employee_id'] ?? 0);
$myOrgID = (int)($me['org_id'] ?? 0);
$isSuperAdmin = ((int)($me['user_group_id'] ?? 0) === 1);
$isHQ = ($myOrgID === 4);

$counts = [];

// Is this user the org's default signatory (for legacy office-order routing)?
$isOrgSignatory = false;
if ($myEmpID > 0 && $myOrgID > 0) {
    $sq = mysqli_prepare($con,
        "SELECT 1 FROM leave_edit_approval_signatory
         WHERE employeeID = ? AND organization_id = ? LIMIT 1");
    mysqli_stmt_bind_param($sq, 'ii', $myEmpID, $myOrgID);
    mysqli_stmt_execute($sq);
    $isOrgSignatory = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($sq));
    mysqli_stmt_close($sq);
}

// ── Helper: signatory-scope clause for leave_addition_history / leave_deduction_history
// Matches the logic in fetch-regular-leave-addition-approval.php / fetch-regular-leave-approval.php.
// NO Super Admin bypass — the approve endpoints only accept the actual assigned
// signatory, so the badge must count only rows the user can actually action.
$sigScope = function($tblAlias, $empAlias) use ($myEmpID, $myOrgID, $isOrgSignatory) {
    if ($myEmpID <= 0) return " AND 1=0"; // no emp_id → cannot be a signatory anywhere
    if ($isOrgSignatory) {
        return " AND ($tblAlias.override_signatory_id = $myEmpID
                     OR ($tblAlias.override_signatory_id IS NULL AND $empAlias.organization_id = $myOrgID))";
    }
    return " AND $tblAlias.override_signatory_id = $myEmpID";
};

// ═══ 1. ছুটি সংযোজনের অনুমোদন (regular-leave-addition) ═══
$sc = $sigScope('lah', 'el');
$q = mysqli_query($con, "
    SELECT COUNT(DISTINCT COALESCE(lah.batch_id, CONCAT('_solo_', lah.dataID))) AS c
    FROM leave_addition_history lah
    INNER JOIN employee_list el ON lah.employeeID = el.id
    WHERE lah.isApproved = 0 $sc
");
$counts['regular-leave-addition'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);

// ═══ 2. ছুটি কর্তনের অনুমোদন (previous-leave-regular-info-approve) ═══
$sc = $sigScope('ldh', 'el');
$q = mysqli_query($con, "
    SELECT COUNT(DISTINCT COALESCE(ldh.batch_id, CONCAT('_solo_', ldh.dataID))) AS c
    FROM leave_deduction_history ldh
    INNER JOIN employee_list el ON ldh.employeeID = el.id
    WHERE ldh.isApproved = 0 $sc
");
$counts['previous-leave-regular-info-approve'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);

// ═══ 3a. ঐচ্ছিক ছুটি — সুপারিশ কিউ (optional-pre-approval-supervisor-queue) ═══
// Rows where I'm the supervisor row (isSupervisor=1) and haven't acted.
if ($myEmpID > 0) {
    $q = mysqli_query($con, "
        SELECT COUNT(*) AS c
        FROM optional_leave_pre_approval opa
        INNER JOIN optional_leave_pre_approval_signatory s ON s.preApprovalID = opa.id
        WHERE opa.status = 0
          AND s.isSupervisor = 1
          AND s.signatory = $myEmpID
          AND s.isApproved = 0
    ");
    $counts['optional-pre-approval-supervisor-queue'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
} else {
    $counts['optional-pre-approval-supervisor-queue'] = 0;
}

// ═══ 3b. ঐচ্ছিক ছুটি — অনুমোদনের জন্য প্রেরণ (optional-pre-approval-forward-queue) ═══
// Center-admin queue: supervisor has recommended, admin hasn't forwarded yet.
if ($myOrgID > 0) {
    // Check isCenterAdmin flag on user_list
    $isCA = 0;
    $caQ = mysqli_prepare($con, "SELECT isCenterAdmin FROM user_list WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($caQ, 's', $un);
    mysqli_stmt_execute($caQ);
    $caRow = mysqli_fetch_assoc(mysqli_stmt_get_result($caQ)) ?: [];
    mysqli_stmt_close($caQ);
    $isCA = (int)($caRow['isCenterAdmin'] ?? 0);

    // Broaden: also count if the user's group has been granted this submodule
    // (not just legacy isCenterAdmin=1). Matches the view/API-side gate.
    if (!$isCA) {
        $gid = (int)($me['user_group_id'] ?? 0);
        if ($gid > 0) {
            $gpq = mysqli_prepare($con,
                "SELECT 1 FROM group_access_permission gap
                 INNER JOIN submodules sm ON gap.submodule_id = sm.dataID
                 WHERE gap.user_group_id = ? AND sm.slug = 'optional-pre-approval-forward-queue'
                 LIMIT 1");
            mysqli_stmt_bind_param($gpq, 'i', $gid);
            mysqli_stmt_execute($gpq);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($gpq))) { $isCA = 1; }
            mysqli_stmt_close($gpq);
        }
    }

    if ($isCA) {
        $q = mysqli_query($con, "
            SELECT COUNT(*) AS c
            FROM optional_leave_pre_approval opa
            WHERE opa.status = 0
              AND opa.organization_id = $myOrgID
              AND EXISTS (
                    SELECT 1 FROM optional_leave_pre_approval_signatory sSup
                    WHERE sSup.preApprovalID = opa.id
                      AND sSup.isSupervisor = 1 AND sSup.isApproved = 1
              )
              AND NOT EXISTS (
                    SELECT 1 FROM optional_leave_pre_approval_signatory sChain
                    WHERE sChain.preApprovalID = opa.id
                      AND sChain.isSupervisor = 0 AND sChain.isSentbyAdmin = 1
              )
        ");
        $counts['optional-pre-approval-forward-queue'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
    } else {
        $counts['optional-pre-approval-forward-queue'] = 0;
    }
} else {
    $counts['optional-pre-approval-forward-queue'] = 0;
}

// ═══ 3c. ঐচ্ছিক ছুটি — অনুমোদন কিউ (optional-pre-approval-queue) ═══
// Signatory chain (non-supervisor rows) after admin has forwarded.
if ($myEmpID > 0) {
    $q = mysqli_query($con, "
        SELECT COUNT(*) AS c
        FROM optional_leave_pre_approval opa
        INNER JOIN optional_leave_pre_approval_signatory s ON s.preApprovalID = opa.id
        WHERE opa.status = 0
          AND s.signatory = $myEmpID
          AND s.isApproved = 0
          AND s.isSupervisor = 0
          AND s.isSentbyAdmin = 1
          AND (s.prevSignatory IS NULL
               OR EXISTS (
                   SELECT 1 FROM optional_leave_pre_approval_signatory s2
                   WHERE s2.preApprovalID = s.preApprovalID
                     AND s2.signatory = s.prevSignatory
                     AND s2.isApproved = 1
               ))
    ");
    $counts['optional-pre-approval-queue'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
} else {
    $counts['optional-pre-approval-queue'] = 0;
}

// ═══ 4. ছুটির সুপারিশ ও অনুমোদন (leave-approval) ═══
// Must match the actual page (views/leave/approval.php) which shows two tabs:
//   • সুপারিশ tab  → isSupervisor = 1 (fetch-waiting-supervise.php)
//   • অনুমোদন tab → isSentbyAdmin = 1 AND isSupervisor != 1 (fetch-waiting-approve.php)
// Mid-chain rows (isSupervisor=0 AND isSentbyAdmin=0) belong to a different
// list, so we exclude them from THIS submenu's count.
if ($myEmpID > 0) {
    // Match fetch-waiting-approve.php's actionable-row filter exactly:
    //   * exclude status=3 (returned to applicant)
    //   * for non-supervisor rows, require the previous signatory in
    //     the chain to have approved (serial = current - 1) — otherwise
    //     the badge over-counts by including rows the user cannot yet
    //     act on because the ball is still with someone earlier.
    $q = mysqli_query($con, "
        SELECT COUNT(*) AS c
        FROM leave_data_for_approval la
        INNER JOIN leave_applications l ON la.leaveApplicationID = l.dataID
        WHERE la.signatory = $myEmpID
          AND la.isApproved = 0
          AND (la.isSupervisor = 1 OR la.isSentbyAdmin = 1)
          AND l.status <> 3
          AND (
              la.isSupervisor = 1
              OR la.prevSignatory = 0
              OR la.prevSignatory IS NULL
              OR EXISTS (
                  SELECT 1 FROM leave_data_for_approval prev
                  WHERE prev.leaveApplicationID = la.leaveApplicationID
                    AND prev.signatory = la.prevSignatory
                    AND prev.isApproved = 1
                    AND prev.serial    = la.serial - 1
              )
          )
    ");
    $counts['leave-approval'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
} else {
    $counts['leave-approval'] = 0;
}

// ═══ 5. ছুটি সম্পাদনা (allowed-leave-applications) ═══
// Supervisor-recommended, not yet forwarded to admin. Center-scoped for
// regular users; Super Admin sees all centers.
if ($myOrgID > 0 || $isSuperAdmin) {
    $orgClause = $isSuperAdmin ? '' : "AND la.organization_id = $myOrgID";
    $q = mysqli_query($con, "
        SELECT COUNT(*) AS c
        FROM leave_data_for_approval lda
        INNER JOIN leave_applications la ON lda.leaveApplicationID = la.dataID
        WHERE lda.isSupervisor = 1
          AND lda.isApproved   = 1
          AND lda.isSentbyAdmin = 0
          $orgClause
    ");
    $counts['allowed-leave-applications'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
} else {
    $counts['allowed-leave-applications'] = 0;
}

// ═══ 5b. ছুটির ইতিহাস ও যোগদান (all-leave-application) ═══
// Applicant-side badge: how many of MY own applications have been sent
// back for পুনঃ যাচাই and need to be edited + resubmitted. Mirrors the
// filter used by the "পুনঃ যাচাই" chip on views/leave/all-applications.php.
$myUserID = (int)($_SESSION['userID'] ?? 0);
if ($myEmpID > 0 || $myUserID > 0) {
    $q = mysqli_query($con, "
        SELECT COUNT(*) AS c
        FROM leave_applications la
        WHERE la.status = 3
          AND (la.applicantID = $myEmpID OR la.submitBy = $myUserID)
    ");
    $counts['all-leave-application'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
} else {
    $counts['all-leave-application'] = 0;
}

// ═══ 6. যোগদানের সুপারিশ ও অনুমোদন (leave-joining-approval) — chain-based ═══
if ($myEmpID > 0) {
    $chk = mysqli_query($con, "SHOW TABLES LIKE 'leave_joining_data_for_approval'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $q = mysqli_query($con, "
            SELECT COUNT(*) AS c
            FROM leave_joining_data_for_approval lj
            WHERE lj.signatory = $myEmpID
              AND lj.isApproved = 0
              AND (lj.prevSignatory IS NULL
                   OR lj.prevSignatory = 0
                   OR EXISTS (
                       SELECT 1 FROM leave_joining_data_for_approval lj2
                       WHERE lj2.leaveApplicationID = lj.leaveApplicationID
                         AND lj2.signatory = lj.prevSignatory
                         AND lj2.isApproved = 1
                   ))
        ");
        $counts['leave-joining-approval'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
    } else {
        $counts['leave-joining-approval'] = 0;
    }
} else {
    $counts['leave-joining-approval'] = 0;
}

// ═══ 7. যোগদানের আবেদন সম্পাদনা (manage-approved-leaves) ═══
// Same predicate as the page's প্রক্রিয়াধীন tab: supervisor has recommended the
// joining, admin hasn't forwarded it on yet. Type 1 auto-forwards, so it never
// waits here. Center-scoped for regular users; Super Admin sees all centers.
if ($myOrgID > 0 || $isSuperAdmin) {
    $chk = mysqli_query($con, "SHOW TABLES LIKE 'leave_joining_data_for_approval'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $orgClause = $isSuperAdmin ? '' : "AND la.organization_id = $myOrgID";
        $q = mysqli_query($con, "
            SELECT COUNT(*) AS c
            FROM leave_joining_data_for_approval lj
            INNER JOIN leave_joining_application lja ON lj.leaveApplicationID = lja.leaveApplicationID
            INNER JOIN leave_applications la         ON lj.leaveApplicationID = la.dataID
            WHERE lj.isSupervisor  = 1
              AND lj.isApproved    = 1
              AND lj.isSentbyAdmin = 0
              AND lja.joiningType != 1
              $orgClause
        ");
        $counts['manage-approved-leaves'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
    } else {
        $counts['manage-approved-leaves'] = 0;
    }
} else {
    $counts['manage-approved-leaves'] = 0;
}

// ═══ ছুটি সনদ অনুমোদন (leave-certificate-approval) ═══
// Certificate signatories come from leave_edit_approval_signatory, the same
// table the সিগনেটরি সেটিংS page writes, and are scoped per centre. No Super
// Admin bypass here either — count only what this user can actually action.
if ($myEmpID > 0) {
    $certOrgs = [];
    $cq = mysqli_query($con,
        "SELECT organization_id FROM leave_edit_approval_signatory WHERE employeeID = $myEmpID");
    if ($cq) while ($cr = mysqli_fetch_assoc($cq)) $certOrgs[] = (int)$cr['organization_id'];

    if ($certOrgs) {
        $orgList = implode(',', $certOrgs);
        $q = mysqli_query($con, "
            SELECT COUNT(*) AS c
            FROM yearly_leave_summary yls
            INNER JOIN employee_list el ON yls.employeeID = el.id
            WHERE yls.isApproved = 0
              AND el.organization_id IN ($orgList)
        ");
        $counts['leave-certificate-approval'] = (int)(mysqli_fetch_assoc($q)['c'] ?? 0);
    } else {
        $counts['leave-certificate-approval'] = 0;
    }
} else {
    $counts['leave-certificate-approval'] = 0;
}

// `total` = sum of slugs under the Leave module ONLY (that's the module whose
// parent shows #totalTask). Other-module slugs (like allowed-leave-applications
// under Admin Panel) are returned so their individual badges work, but they
// don't roll up into the Leave-module counter.
$leaveModuleSlugs = [
    'regular-leave-addition',
    'previous-leave-regular-info-approve',
    'optional-pre-approval-supervisor-queue',
    'optional-pre-approval-queue',
    'leave-approval',
    'leave-joining-approval',
    'all-leave-application',
];
$adminPanelSlugs = [
    'allowed-leave-applications',
    'optional-pre-approval-forward-queue',
    'manage-approved-leaves',
    'leave-certificate-approval',
];

$total = 0;
foreach ($leaveModuleSlugs as $s) $total += (int)($counts[$s] ?? 0);
$adminTotal = 0;
foreach ($adminPanelSlugs as $s) $adminTotal += (int)($counts[$s] ?? 0);

$counts['status']       = 1;
$counts['total']        = $total;
$counts['admin_total']  = $adminTotal;

echo json_encode($counts);
mysqli_close($con);
