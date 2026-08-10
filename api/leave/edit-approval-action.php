<?php
session_start();
header('Content-Type: application/json');

ob_start();
require_once(__DIR__ . '/../../connection.php');
require_once(__DIR__ . '/../../bddate.php');
ob_end_clean();

function out($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if (!isset($_SESSION['username']) || !isset($_SESSION['userID'])) {
    out(0, 'আপনি লগইন করেননি!');
}

$actorUserId = (int)$_SESSION['userID'];
$action  = trim($_POST['action']  ?? '');
$editID  = (int)($_POST['editID'] ?? 0);
$reason  = trim($_POST['reason']  ?? '');
$segJson = $_POST['segments']     ?? '';

if (!in_array($action, ['approve', 'reject', 'return'], true)) out(0, 'অবৈধ অ্যাকশন');
if ($editID <= 0) out(0, 'অবৈধ আইডি');
if (($action === 'reject' || $action === 'return') && $reason === '') {
    out(0, 'কারণ আবশ্যক');
}

$segments = [];
if ($segJson !== '') {
    $decoded = json_decode($segJson, true);
    if (is_array($decoded)) $segments = $decoded;
}

// ── Resolve actor's employee_id ──
$meStmt = mysqli_prepare($con,
    "SELECT ul.dataID, ul.employee_id, ul.full_name,
            el.id AS emp_id, el.employee_name, jt.job_title_name
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     LEFT JOIN job_title jt     ON el.designation  = jt.id
     WHERE ul.dataID = ? LIMIT 1");
mysqli_stmt_bind_param($meStmt, 'i', $actorUserId);
mysqli_stmt_execute($meStmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($meStmt));
mysqli_stmt_close($meStmt);
if (!$me) out(0, 'ব্যবহারকারী তথ্য পাওয়া যায়নি');

$actorEmpId = (int)$me['emp_id'];
$actorName  = $me['employee_name'] ?? $me['full_name'] ?? '';
$actorTitle = $me['job_title_name'] ?? '';

// ── Load parent edit-request ──
$ledStmt = mysqli_prepare($con, "SELECT * FROM leave_edit_data WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($ledStmt, 'i', $editID);
mysqli_stmt_execute($ledStmt);
$led = mysqli_fetch_assoc(mysqli_stmt_get_result($ledStmt));
mysqli_stmt_close($ledStmt);
if (!$led) out(0, 'সংশোধন প্রস্তাব পাওয়া যায়নি');
if ((int)$led['status'] !== 0) out(0, 'এই প্রস্তাব ইতিমধ্যে নিষ্পত্তি হয়েছে');

$leaveAppID = (int)$led['leaveApplicationID'];

// ── Load actor's chain row + verify it's their turn ──
$rowStmt = mysqli_prepare($con,
    "SELECT * FROM leave_edit_data_for_approval
     WHERE editRequestID = ? AND signatory = ? AND isApproved = 0 LIMIT 1");
mysqli_stmt_bind_param($rowStmt, 'ii', $editID, $actorEmpId);
mysqli_stmt_execute($rowStmt);
$myRow = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));
mysqli_stmt_close($rowStmt);
if (!$myRow) out(0, 'আপনি এই সংশোধনের অনুমোদন চেইনে নেই');

// Block if earlier-serial row is still pending
$blockStmt = mysqli_prepare($con,
    "SELECT COUNT(*) c FROM leave_edit_data_for_approval
     WHERE editRequestID = ? AND serial < ? AND isApproved = 0");
mysqli_stmt_bind_param($blockStmt, 'ii', $editID, $myRow['serial']);
mysqli_stmt_execute($blockStmt);
$blockers = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($blockStmt))['c'] ?? 0);
mysqli_stmt_close($blockStmt);
if ($blockers > 0) out(0, 'এই মুহূর্তে আপনার পালা নয়');

$now = function_exists('ShowBangladeshTime') ? ShowBangladeshTime() : date('Y-m-d H:i:s');

mysqli_autocommit($con, false);

try {
    // ──────── APPROVE ────────
    if ($action === 'approve') {

        if (empty($segments)) throw new Exception('সংশোধিত অংশ পাওয়া যায়নি');
        $normSegs = [];
        $totalDays = 0;
        foreach ($segments as $i => $s) {
            $id = (int)($s['id'] ?? 0);
            $lt = (int)($s['leaveType'] ?? 0);
            $df = trim($s['dateFrom'] ?? '');
            $dt = trim($s['dateTo']   ?? '');
            if ($id <= 0 || $lt <= 0 || $df === '' || $dt === '') {
                throw new Exception('অসম্পূর্ণ অংশ (ক্রমিক ' . ($i + 1) . ')');
            }
            $fp = explode('/', $df); $tp = explode('/', $dt);
            if (count($fp) !== 3 || count($tp) !== 3) throw new Exception('অবৈধ তারিখ ফরম্যাট');
            $fromIso = sprintf('%04d-%02d-%02d', (int)$fp[2], (int)$fp[1], (int)$fp[0]);
            $toIso   = sprintf('%04d-%02d-%02d', (int)$tp[2], (int)$tp[1], (int)$tp[0]);
            $fromTs = strtotime($fromIso); $toTs = strtotime($toIso);
            if (!$fromTs || !$toTs || $toTs < $fromTs) throw new Exception('অবৈধ তারিখ পরিসর (ক্রমিক ' . ($i + 1) . ')');
            $days = (int)($s['days'] ?? 0);
            if ($days <= 0) $days = (int)(($toTs - $fromTs) / 86400) + 1;
            if ($days <= 0) throw new Exception('অবৈধ দিন সংখ্যা');
            $normSegs[] = ['id' => $id, 'leaveType' => $lt, 'dateFrom' => $fromIso, 'dateTo' => $toIso, 'days' => $days];
            $totalDays += $days;
        }

        // 1. Update proposed segments with signatory's edits
        $updSegStmt = mysqli_prepare($con,
            "UPDATE leave_edit_application_segments
             SET leaveType = ?, dateFrom = ?, dateTo = ?, days = ?
             WHERE dataID = ? AND editRequestID = ? AND kind = 'proposed'");
        foreach ($normSegs as $sg) {
            mysqli_stmt_bind_param($updSegStmt, 'issiii',
                $sg['leaveType'], $sg['dateFrom'], $sg['dateTo'], $sg['days'], $sg['id'], $editID);
            if (!mysqli_stmt_execute($updSegStmt)) {
                throw new Exception('Failed to update proposed segment ' . $sg['id']);
            }
        }
        mysqli_stmt_close($updSegStmt);

        // 2. Mark this chain row approved
        $myRowID = (int)$myRow['dataID'];
        $upd = mysqli_prepare($con,
            "UPDATE leave_edit_data_for_approval
             SET isApproved = 1, approvedDate = ?, approvedDays = ?
             WHERE dataID = ?");
        mysqli_stmt_bind_param($upd, 'sii', $now, $totalDays, $myRowID);
        if (!mysqli_stmt_execute($upd)) throw new Exception('Failed to mark approval');
        mysqli_stmt_close($upd);

        // 3. Check if any chain rows still pending
        $remStmt = mysqli_prepare($con,
            "SELECT COUNT(*) c FROM leave_edit_data_for_approval
             WHERE editRequestID = ? AND isApproved = 0");
        mysqli_stmt_bind_param($remStmt, 'i', $editID);
        mysqli_stmt_execute($remStmt);
        $remaining = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($remStmt))['c'] ?? 0);
        mysqli_stmt_close($remStmt);

        $isFinal = ($remaining === 0);

        if ($isFinal) {
            // 1. Mark parent edit-request approved
            $ledUpd = mysqli_prepare($con,
                "UPDATE leave_edit_data
                 SET status = 1, approvedBy = ?, approvedDate = ?, lastUpdate = ?
                 WHERE dataID = ?");
            mysqli_stmt_bind_param($ledUpd, 'issi', $actorUserId, $now, $now, $editID);
            if (!mysqli_stmt_execute($ledUpd)) throw new Exception('Failed to finalize edit-request');
            mysqli_stmt_close($ledUpd);

            // 2. Replace leave_application_segments kind='proposed' with edit's proposed segments
            $finalSegStmt = mysqli_prepare($con,
                "SELECT * FROM leave_edit_application_segments
                 WHERE editRequestID = ? AND kind = 'proposed'
                 ORDER BY serial ASC, dataID ASC");
            mysqli_stmt_bind_param($finalSegStmt, 'i', $editID);
            mysqli_stmt_execute($finalSegStmt);
            $finalSegRes = mysqli_stmt_get_result($finalSegStmt);
            $finalSegs = [];
            while ($r = mysqli_fetch_assoc($finalSegRes)) $finalSegs[] = $r;
            mysqli_stmt_close($finalSegStmt);

            $delStmt = mysqli_prepare($con,
                "DELETE FROM leave_application_segments
                 WHERE applicationID = ? AND kind = 'proposed'");
            mysqli_stmt_bind_param($delStmt, 'i', $leaveAppID);
            if (!mysqli_stmt_execute($delStmt)) throw new Exception('Failed to clear old proposed segments');
            mysqli_stmt_close($delStmt);

            $insStmt = mysqli_prepare($con,
                "INSERT INTO leave_application_segments
                 (applicationID, kind, leaveType, leaveTypeInTwo, dateFrom, dateTo, days, approvedDays, serial, createdBy, createdAt)
                 VALUES (?, 'proposed', ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $serial = 1; $totalApprovedDays = 0;
            $minFrom = null; $maxTo = null; $firstType = 0;
            foreach ($finalSegs as $fs) {
                $lt   = (int)$fs['leaveType'];
                $lt2  = $fs['leaveTypeInTwo'] !== null ? (int)$fs['leaveTypeInTwo'] : null;
                $df   = $fs['dateFrom'];
                $dt   = $fs['dateTo'];
                $days = (int)$fs['days'];
                $appr = $days;
                mysqli_stmt_bind_param($insStmt, 'iiissiiii',
                    $leaveAppID, $lt, $lt2, $df, $dt, $days, $appr, $serial, $actorUserId);
                if (!mysqli_stmt_execute($insStmt)) throw new Exception('Failed to insert new proposed segment');
                $totalApprovedDays += $days;
                if ($minFrom === null || strtotime($df) < strtotime($minFrom)) $minFrom = $df;
                if ($maxTo   === null || strtotime($dt) > strtotime($maxTo))   $maxTo   = $dt;
                if ($firstType === 0) $firstType = $lt;
                $serial++;
            }
            mysqli_stmt_close($insStmt);

            // 3. Sync leave_applications denormalized columns
            $appUpdStmt = mysqli_prepare($con,
                "UPDATE leave_applications
                 SET approvedLeaveType = ?, approvedDateFrom = ?, approvedDateTo = ?, approvedDays = ?, lastUpdate = ?, updatedBy = ?
                 WHERE dataID = ?");
            mysqli_stmt_bind_param($appUpdStmt, 'issisii',
                $firstType, $minFrom, $maxTo, $totalApprovedDays, $now, $actorUserId, $leaveAppID);
            if (!mysqli_stmt_execute($appUpdStmt)) throw new Exception('Failed to update leave_applications');
            mysqli_stmt_close($appUpdStmt);
        }

        mysqli_commit($con);
        mysqli_autocommit($con, true);

        if (function_exists('audit_log')) {
            audit_log($isFinal ? 'leave_edit_finalized' : 'leave_edit_chain_approved', [
                'target_type'     => 'leave_edit',
                'target_id'       => $editID,
                'organization_id' => (int)$led['organization_id'] ?: null,
                'note'            => 'serial=' . $myRow['serial']
                                   . '; days=' . $totalDays
                                   . ($isFinal ? '; applied_to_leave=' . $leaveAppID : ''),
            ]);
        }

        out(1, $isFinal ? 'সংশোধন চূড়ান্তভাবে অনুমোদিত — মূল ছুটিতে প্রয়োগ করা হয়েছে' : 'অনুমোদিত — পরবর্তী স্বাক্ষরকারীর অপেক্ষায়',
            ['final' => $isFinal]);
    }

    // ──────── REJECT ────────
    if ($action === 'reject') {
        $myRowID = (int)$myRow['dataID'];

        $rej = mysqli_prepare($con,
            "UPDATE leave_edit_data_for_approval
             SET isApproved = 2, approvedDate = ?, note = ?, rejectionReason = ?
             WHERE dataID = ?");
        mysqli_stmt_bind_param($rej, 'sssi', $now, $reason, $reason, $myRowID);
        if (!mysqli_stmt_execute($rej)) throw new Exception('Failed to mark rejection');
        mysqli_stmt_close($rej);

        $ledRej = mysqli_prepare($con,
            "UPDATE leave_edit_data
             SET status = 2, rejectedBy = ?, rejectedDate = ?, rejectionReason = ?, lastUpdate = ?
             WHERE dataID = ?");
        mysqli_stmt_bind_param($ledRej, 'isssi', $actorUserId, $now, $reason, $now, $editID);
        if (!mysqli_stmt_execute($ledRej)) throw new Exception('Failed to update edit-request status');
        mysqli_stmt_close($ledRej);

        mysqli_commit($con);
        mysqli_autocommit($con, true);

        if (function_exists('audit_log')) {
            audit_log('leave_edit_rejected', [
                'target_type'     => 'leave_edit',
                'target_id'       => $editID,
                'organization_id' => (int)$led['organization_id'] ?: null,
                'note'            => 'reason=' . mb_substr($reason, 0, 200),
            ]);
        }

        out(1, 'প্রত্যাখ্যাত');
    }

    // ──────── RETURN (back to admin initiator) ────────
    if ($action === 'return') {
        $initStmt = mysqli_prepare($con,
            "SELECT ul.full_name, el.employee_name FROM user_list ul
             LEFT JOIN employee_list el ON ul.employee_id = el.id
             WHERE ul.dataID = ? LIMIT 1");
        mysqli_stmt_bind_param($initStmt, 'i', $led['adminInitiator']);
        mysqli_stmt_execute($initStmt);
        $initRow = mysqli_fetch_assoc(mysqli_stmt_get_result($initStmt));
        mysqli_stmt_close($initStmt);
        $returnedToName = $initRow['employee_name'] ?? $initRow['full_name'] ?? '';

        $retUpd = mysqli_prepare($con,
            "UPDATE leave_edit_data SET status = 3, lastUpdate = ? WHERE dataID = ?");
        mysqli_stmt_bind_param($retUpd, 'si', $now, $editID);
        if (!mysqli_stmt_execute($retUpd)) throw new Exception('Failed to mark return status');
        mysqli_stmt_close($retUpd);

        $clearStmt = mysqli_prepare($con,
            "DELETE FROM leave_edit_data_for_approval
             WHERE editRequestID = ? AND isApproved = 0");
        mysqli_stmt_bind_param($clearStmt, 'i', $editID);
        if (!mysqli_stmt_execute($clearStmt)) throw new Exception('Failed to clear pending chain rows');
        mysqli_stmt_close($clearStmt);

        $rhStmt = mysqli_prepare($con,
            "INSERT INTO leave_edit_return_history
             (editRequestID, returnedBy, returnedByName, returnedByTitle, returnedTo, returnedToName, returnType, note)
             VALUES (?, ?, ?, ?, ?, ?, 'to_admin', ?)");
        $returnedTo = (int)$led['adminInitiator'];
        mysqli_stmt_bind_param($rhStmt, 'iississ',
            $editID, $actorEmpId, $actorName, $actorTitle, $returnedTo, $returnedToName, $reason);
        if (!mysqli_stmt_execute($rhStmt)) throw new Exception('Failed to record return history');
        mysqli_stmt_close($rhStmt);

        mysqli_commit($con);
        mysqli_autocommit($con, true);

        if (function_exists('audit_log')) {
            audit_log('leave_edit_returned', [
                'target_type'     => 'leave_edit',
                'target_id'       => $editID,
                'organization_id' => (int)$led['organization_id'] ?: null,
                'note'            => 'returned_to=' . $returnedTo . '; reason=' . mb_substr($reason, 0, 200),
            ]);
        }

        out(1, 'প্রস্তাবকের কাছে ফেরত পাঠানো হয়েছে');
    }

    throw new Exception('Unreachable');

} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    out(0, 'ব্যর্থ: ' . $e->getMessage());
}
