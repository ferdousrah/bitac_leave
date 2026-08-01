<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    echo json_encode(['status'=>0,'message'=>'অননুমোদিত']);
    exit;
}

$pid    = (int)($_POST['id']     ?? 0);
$action = (int)($_POST['action'] ?? 0);  // 1=approve, 2=reject
$reason = trim($_POST['reason']  ?? '');

if ($pid <= 0)                    { echo json_encode(['status'=>0,'message'=>'অবৈধ আইডি']); exit; }
if (!in_array($action, [1,2], true)) { echo json_encode(['status'=>0,'message'=>'অবৈধ কার্যক্রম']); exit; }
if ($action === 2 && $reason === '') { echo json_encode(['status'=>0,'message'=>'প্রত্যাখ্যানের কারণ আবশ্যক']); exit; }

// Actor's employee_id (signatory key)
$actorStmt = mysqli_prepare($con, "SELECT employee_id FROM user_list WHERE user_id = ? LIMIT 1");
$un = $_SESSION['username'];
mysqli_stmt_bind_param($actorStmt, 's', $un);
mysqli_stmt_execute($actorStmt);
$actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actorStmt)) ?: [];
mysqli_stmt_close($actorStmt);
$myEmpID = (int)($actor['employee_id'] ?? 0);
if ($myEmpID <= 0) { echo json_encode(['status'=>0,'message'=>'signatory identity resolve করা যায়নি']); exit; }

// Find my PENDING signatory row for this pre-approval.
// The same person can appear multiple times in the chain (e.g. as supervisor
// AND later as a signatory). Order by serial ASC so we always act on the
// earliest still-pending step.
$sigQ = mysqli_prepare($con,
    "SELECT s.dataID, s.serial, s.isApproved, s.prevSignatory, s.isSupervisor, s.isSentbyAdmin
     FROM optional_leave_pre_approval_signatory s
     WHERE s.preApprovalID = ? AND s.signatory = ? AND s.isApproved = 0
     ORDER BY s.serial ASC
     LIMIT 1");
mysqli_stmt_bind_param($sigQ, 'ii', $pid, $myEmpID);
mysqli_stmt_execute($sigQ);
$mySig = mysqli_fetch_assoc(mysqli_stmt_get_result($sigQ));
mysqli_stmt_close($sigQ);
if (!$mySig) {
    // Either not a signatory at all, or all their rows are already actioned
    $anyChk = mysqli_prepare($con,
        "SELECT 1 FROM optional_leave_pre_approval_signatory
         WHERE preApprovalID = ? AND signatory = ? LIMIT 1");
    mysqli_stmt_bind_param($anyChk, 'ii', $pid, $myEmpID);
    mysqli_stmt_execute($anyChk);
    $exists = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($anyChk));
    mysqli_stmt_close($anyChk);
    if ($exists) {
        echo json_encode(['status'=>0,'message'=>'আপনি ইতিমধ্যে এই আবেদনে সিদ্ধান্ত দিয়েছেন']);
    } else {
        echo json_encode(['status'=>0,'message'=>'আপনার এই আবেদনে signatory অধিকার নেই']);
    }
    exit;
}

// Gate: non-supervisor rows can only act after center admin has forwarded
if ((int)$mySig['isSupervisor'] !== 1 && (int)$mySig['isSentbyAdmin'] !== 1) {
    echo json_encode(['status'=>0,'message'=>'এই আবেদন এখনো কেন্দ্র প্রশাসন থেকে অনুমোদনের জন্য প্রেরিত হয়নি']);
    exit;
}

// Verify previous signatory has approved (if any)
$prevSig = $mySig['prevSignatory'];
if ($prevSig !== null) {
    $prevChk = mysqli_prepare($con,
        "SELECT isApproved FROM optional_leave_pre_approval_signatory
         WHERE preApprovalID = ? AND signatory = ?
         LIMIT 1");
    mysqli_stmt_bind_param($prevChk, 'ii', $pid, $prevSig);
    mysqli_stmt_execute($prevChk);
    $prev = mysqli_fetch_assoc(mysqli_stmt_get_result($prevChk));
    mysqli_stmt_close($prevChk);
    if (!$prev || (int)$prev['isApproved'] !== 1) {
        echo json_encode(['status'=>0,'message'=>'পূর্ববর্তী signatory-র সিদ্ধান্ত এখনো পেন্ডিং']);
        exit;
    }
}

mysqli_autocommit($con, false);
try {
    // Update my signatory row
    if ($action === 1) {
        $up = mysqli_prepare($con,
            "UPDATE optional_leave_pre_approval_signatory
             SET isApproved = 1, approvedDate = NOW(),
                 rejectedDate = NULL, rejectionReason = NULL
             WHERE dataID = ?");
        mysqli_stmt_bind_param($up, 'i', $mySig['dataID']);
    } else {
        $up = mysqli_prepare($con,
            "UPDATE optional_leave_pre_approval_signatory
             SET isApproved = 2, rejectedDate = NOW(), rejectionReason = ?,
                 approvedDate = NULL
             WHERE dataID = ?");
        mysqli_stmt_bind_param($up, 'si', $reason, $mySig['dataID']);
    }
    if (!mysqli_stmt_execute($up)) throw new Exception('signatory row update failed');
    mysqli_stmt_close($up);

    // If rejected → mark pre-approval as rejected immediately
    if ($action === 2) {
        $rejUp = mysqli_prepare($con,
            "UPDATE optional_leave_pre_approval
             SET status = 2, final_rejected_date = NOW(), final_rejection_reason = ?
             WHERE id = ?");
        mysqli_stmt_bind_param($rejUp, 'si', $reason, $pid);
        mysqli_stmt_execute($rejUp);
        mysqli_stmt_close($rejUp);
    } else {
        // Approved → check if this was the LAST signatory. If yes, finalize.
        $remChk = mysqli_prepare($con,
            "SELECT COUNT(*) AS pending_rows
             FROM optional_leave_pre_approval_signatory
             WHERE preApprovalID = ? AND isApproved = 0");
        mysqli_stmt_bind_param($remChk, 'i', $pid);
        mysqli_stmt_execute($remChk);
        $rem = mysqli_fetch_assoc(mysqli_stmt_get_result($remChk));
        mysqli_stmt_close($remChk);
        if ((int)$rem['pending_rows'] === 0) {
            $appUp = mysqli_prepare($con,
                "UPDATE optional_leave_pre_approval
                 SET status = 1, final_approved_date = NOW()
                 WHERE id = ?");
            mysqli_stmt_bind_param($appUp, 'i', $pid);
            mysqli_stmt_execute($appUp);
            mysqli_stmt_close($appUp);
        }
    }

    mysqli_commit($con);
    mysqli_autocommit($con, true);

    if (function_exists('audit_log')) {
        audit_log($action === 1 ? 'optional_pre_approval_step_approved' : 'optional_pre_approval_step_rejected', [
            'target_type' => 'optional_pre_approval',
            'target_id'   => $pid,
            'note'        => 'signatory=' . $myEmpID . ($action === 2 ? '; reason=' . mb_substr($reason, 0, 200) : ''),
        ]);
    }

    // ── Notifications ──────────────────────────────────────────────────
    // Load applicant + org for message context (best-effort, silent on failure)
    try {
        $ctxQ = mysqli_prepare($con,
            "SELECT opa.employee_id, opa.organization_id, opa.year, opa.requested_days,
                    el.employee_name
             FROM optional_leave_pre_approval opa
             INNER JOIN employee_list el ON opa.employee_id = el.id
             WHERE opa.id = ? LIMIT 1");
        mysqli_stmt_bind_param($ctxQ, 'i', $pid);
        mysqli_stmt_execute($ctxQ);
        $ctx = mysqli_fetch_assoc(mysqli_stmt_get_result($ctxQ)) ?: [];
        mysqli_stmt_close($ctxQ);

        $applicantEmpID = (int)($ctx['employee_id']    ?? 0);
        $applicantOrgID = (int)($ctx['organization_id']?? 0);
        $applicantName  = $ctx['employee_name']        ?? 'কর্মচারী';
        $yearBn         = strtr((string)($ctx['year'] ?? ''), ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
        $applicantUserID = user_id_for_employee($applicantEmpID);

        if ($action === 2) {
            // REJECT — notify applicant
            $reasonShort = mb_substr($reason, 0, 120);
            send_notification([$applicantUserID],
                "আপনার $yearBn সালের ঐচ্ছিক ছুটির পূর্বানুমোদন প্রত্যাখ্যাত হয়েছে। কারণ: $reasonShort",
                ['type' => 'opa_rejected',
                 'link' => 'views/optional-pre-approval/manage.php?menuslug=optional-pre-approval',
                 'isImportant' => 1]);
        } elseif ((int)$mySig['isSupervisor'] === 1) {
            // Supervisor recommended → notify center admin(s) of applicant's org
            if ($applicantOrgID > 0) {
                $caQ = mysqli_query($con,
                    "SELECT dataID FROM user_list
                     WHERE isCenterAdmin = 1 AND organization_id = $applicantOrgID");
                $caIDs = [];
                if ($caQ) while ($r = mysqli_fetch_assoc($caQ)) $caIDs[] = (int)$r['dataID'];
                send_notification($caIDs,
                    "$applicantName-এর ঐচ্ছিক ছুটির পূর্বানুমোদন সুপারভাইজার-সুপারিশপ্রাপ্ত — সম্পাদনার অপেক্ষায়",
                    ['type' => 'opa_supervisor_recommended',
                     'link' => 'views/optional-pre-approval/forward-queue.php?menuslug=optional-pre-approval-forward-queue']);
            }
        } elseif ((int)$rem['pending_rows'] === 0) {
            // Final approve — notify applicant + all chain members (supervisor + signatories)
            $chainQ = mysqli_query($con,
                "SELECT signatory FROM optional_leave_pre_approval_signatory WHERE preApprovalID = $pid");
            $chainEmpIDs = [];
            if ($chainQ) while ($r = mysqli_fetch_assoc($chainQ)) $chainEmpIDs[] = (int)$r['signatory'];
            $chainUserIDs = user_ids_for_employees($chainEmpIDs);

            send_notification([$applicantUserID],
                "আপনার $yearBn সালের ঐচ্ছিক ছুটির পূর্বানুমোদন চূড়ান্তভাবে অনুমোদিত হয়েছে",
                ['type' => 'opa_approved',
                 'link' => 'views/optional-pre-approval/manage.php?menuslug=optional-pre-approval',
                 'isImportant' => 1]);

            send_notification($chainUserIDs,
                "$applicantName-এর ঐচ্ছিক ছুটির পূর্বানুমোদন চূড়ান্তভাবে অনুমোদিত",
                ['type' => 'opa_approved',
                 'link' => 'views/optional-pre-approval/queue.php?menuslug=optional-pre-approval-queue']);
        } else {
            // Mid-chain approve — notify next pending signatory whose prev row we just approved
            $nextQ = mysqli_prepare($con,
                "SELECT signatory FROM optional_leave_pre_approval_signatory
                 WHERE preApprovalID = ? AND isApproved = 0 AND prevSignatory = ?
                 ORDER BY serial ASC LIMIT 1");
            mysqli_stmt_bind_param($nextQ, 'ii', $pid, $myEmpID);
            mysqli_stmt_execute($nextQ);
            $nextRow = mysqli_fetch_assoc(mysqli_stmt_get_result($nextQ)) ?: [];
            mysqli_stmt_close($nextQ);
            $nextEmpID = (int)($nextRow['signatory'] ?? 0);
            if ($nextEmpID > 0) {
                $nextUserID = user_id_for_employee($nextEmpID);
                send_notification([$nextUserID],
                    "$applicantName-এর ঐচ্ছিক ছুটির পূর্বানুমোদন আপনার অনুমোদনের অপেক্ষায়",
                    ['type' => 'opa_pending',
                     'link' => 'views/optional-pre-approval/queue.php?menuslug=optional-pre-approval-queue']);
            }
        }
    } catch (\Throwable $e) { /* silent — notif failure never breaks the approval */ }

    echo json_encode([
        'status'  => 1,
        'message' => $action === 1 ? 'সফলভাবে অনুমোদন করা হয়েছে' : 'সফলভাবে প্রত্যাখ্যান করা হয়েছে'
    ]);
} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    echo json_encode(['status'=>0,'message'=>$e->getMessage()]);
}
