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
$action       = trim($_POST['action']     ?? '');
$joiningID    = (int)($_POST['joiningID'] ?? 0);
$joiningDate  = trim($_POST['joiningDate'] ?? '');
$extLeaveType = (int)($_POST['extensionLeaveType'] ?? 0);
$reason       = trim($_POST['reason']     ?? '');

if (!in_array($action, ['approve', 'reject', 'return'], true)) out(0, 'অবৈধ অ্যাকশন');
if ($joiningID <= 0) out(0, 'অবৈধ আইডি');
if (($action === 'reject' || $action === 'return') && $reason === '') out(0, 'কারণ আবশ্যক');

// Resolve actor employee_id
$meStmt = mysqli_prepare($con,
    "SELECT ul.dataID, ul.employee_id, ul.full_name, ul.signature,
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
$actorSig   = $me['signature'] ?? null;

// Load parent joining application
$ljaStmt = mysqli_prepare($con, "SELECT * FROM leave_joining_application WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($ljaStmt, 'i', $joiningID);
mysqli_stmt_execute($ljaStmt);
$lja = mysqli_fetch_assoc(mysqli_stmt_get_result($ljaStmt));
mysqli_stmt_close($ljaStmt);
if (!$lja) out(0, 'যোগদান পত্র পাওয়া যায়নি');
if ((int)$lja['status'] !== 0) out(0, 'এই যোগদান পত্র ইতিমধ্যে নিষ্পত্তি হয়েছে');

$leaveAppID  = (int)$lja['leaveApplicationID'];
$joiningType = (int)$lja['joiningType'];
$appOrgID    = (int)$lja['organization_id'];

// Nobody decides their own application, whatever the stored chain says.
// Chains written before self-exclusion existed can still name the applicant,
// and this also stops an applicant who named themselves supervisor.
if ($actorEmpId > 0 && $actorEmpId === (int)$lja['applicantID']) {
    out(0, 'নিজের যোগদান পত্রে সিদ্ধান্ত দেওয়া যাবে না — অ্যাডমিনকে জানান');
}

// Verify actor's chain row + turn
$rowStmt = mysqli_prepare($con,
    "SELECT * FROM leave_joining_data_for_approval
     WHERE leaveApplicationID = ? AND signatory = ? AND isApproved = 0 LIMIT 1");
mysqli_stmt_bind_param($rowStmt, 'ii', $leaveAppID, $actorEmpId);
mysqli_stmt_execute($rowStmt);
$myRow = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));
mysqli_stmt_close($rowStmt);
if (!$myRow) out(0, 'আপনি এই যোগদান পত্রের অনুমোদন চেইনে নেই');

$myRowID    = (int)$myRow['dataID'];
$mySerial   = (int)$myRow['serial'];
$isSupervisorRow = ((int)$myRow['isSupervisor'] === 1);

// For non-supervisor chain rows, must be admin-forwarded AND no earlier serial pending
if (!$isSupervisorRow) {
    if ((int)$myRow['isSentbyAdmin'] !== 1) out(0, 'কেন্দ্র অ্যাডমিন কর্তৃক forwarded হওয়ার অপেক্ষায়');
    $blockStmt = mysqli_prepare($con,
        "SELECT COUNT(*) c FROM leave_joining_data_for_approval
         WHERE leaveApplicationID = ? AND serial < ? AND isApproved = 0");
    mysqli_stmt_bind_param($blockStmt, 'ii', $leaveAppID, $mySerial);
    mysqli_stmt_execute($blockStmt);
    $blockers = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($blockStmt))['c'] ?? 0);
    mysqli_stmt_close($blockStmt);
    if ($blockers > 0) out(0, 'এই মুহূর্তে আপনার পালা নয়');
}

// Load leave application
$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveAppID);
mysqli_stmt_execute($appStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);
if (!$leaveApp) out(0, 'মূল ছুটির আবেদন পাওয়া যায়নি');

$now = function_exists('ShowBangladeshTime') ? ShowBangladeshTime() : date('Y-m-d H:i:s');

mysqli_autocommit($con, false);

try {
    // ──────── APPROVE ────────
    if ($action === 'approve') {

        // Validate joining date (for type 2/3)
        $approvedDateFrom = $leaveApp['approvedDateFrom'] ?: $leaveApp['dateFrom'];
        $approvedDateTo   = $leaveApp['approvedDateTo']   ?: $leaveApp['dateTo'];
        if (!$approvedDateFrom || !$approvedDateTo) throw new Exception('অনুমোদিত তারিখ অনুপস্থিত');

        if ($joiningType === 1) {
            $joinIso = $approvedDateTo;
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $joiningDate)) throw new Exception('অবৈধ তারিখ ফরম্যাট');
            $joinIso = $joiningDate;
            $joinTs = strtotime($joinIso);
            $aFromTs = strtotime($approvedDateFrom);
            $aToTs   = strtotime($approvedDateTo);
            if ($joiningType === 2) {
                // Type 2 (অগ্রিম): joining is last leave day (inclusive), must be earlier than approved end
                if ($joinTs >= $aToTs) throw new Exception('Type 2: তারিখ অনুমোদিত শেষ তারিখের আগে হতে হবে');
                if ($joinTs < $aFromTs) throw new Exception('Type 2: তারিখ ছুটির শুরুর আগে হতে পারবে না');
            } else if ($joiningType === 3) {
                // Type 3 (বর্ধিত): joining is last extension day (inclusive), must be after approved end
                if ($joinTs <= $aToTs) throw new Exception('Type 3: তারিখ অনুমোদিত শেষ তারিখের পরে হতে হবে');
                // extensionSegmentsJson (multi-segment) takes precedence over legacy $extLeaveType.
                $hasSegs = !empty($lja['extensionSegmentsJson']);
                if (!$hasSegs && $extLeaveType <= 0) throw new Exception('Type 3: বর্ধিত অংশের ছুটির ধরন আবশ্যক');
            }
        }

        // Mark this row approved (with signature)
        $upd = mysqli_prepare($con,
            "UPDATE leave_joining_data_for_approval
             SET isApproved = 1, approvedDate = ?, signature = ?
             WHERE dataID = ?");
        $nullSig = null;
        mysqli_stmt_bind_param($upd, 'sbi', $now, $nullSig, $myRowID);
        if ($actorSig !== null) mysqli_stmt_send_long_data($upd, 1, $actorSig);
        if (!mysqli_stmt_execute($upd)) throw new Exception('Failed to mark approval');
        mysqli_stmt_close($upd);

        // Persist current joiningDate + extLeaveType to lja so subsequent stages see signatory's edits
        $ljaPatch = mysqli_prepare($con,
            "UPDATE leave_joining_application
             SET requestedJoiningDate = ?, approvedLeaveType = ?, lastUpdate = ?
             WHERE dataID = ?");
        mysqli_stmt_bind_param($ljaPatch, 'sisi', $joinIso, $extLeaveType, $now, $joiningID);
        if (!mysqli_stmt_execute($ljaPatch)) throw new Exception('Failed to persist joining date');
        mysqli_stmt_close($ljaPatch);

        // For Type 2/3, supervisor approval (সুপারিশ) does NOT auto-forward — waits for admin
        // review via views/leave/joining-update.php (which sets isSentbyAdmin=1 explicitly).
        // For Type 1 (intime), there's nothing for admin to edit, so supervisor approval
        // auto-forwards to the signatory chain — matching the legacy filter that excludes
        // Type 1 from manage-approved-leaves.
        if ($isSupervisorRow && $joiningType === 1) {
            $fwd = mysqli_prepare($con,
                "UPDATE leave_joining_data_for_approval
                 SET isSentbyAdmin = 1
                 WHERE leaveApplicationID = ? AND isSupervisor = 0");
            mysqli_stmt_bind_param($fwd, 'i', $leaveAppID);
            if (!mysqli_stmt_execute($fwd)) throw new Exception('Failed to auto-forward Type 1 chain');
            mysqli_stmt_close($fwd);
        }

        // Check whether any desk is still to act. Count every unapproved row,
        // including ones the admin hasn't forwarded yet: for Type 2/3 the chain
        // rows sit at isSentbyAdmin = 0 until admin review, so gating on the
        // "who can act right now" filter used elsewhere would read the
        // supervisor's সুপারিশ as the end of the workflow and finalize early.
        $remStmt = mysqli_prepare($con,
            "SELECT COUNT(*) c FROM leave_joining_data_for_approval
             WHERE leaveApplicationID = ?
               AND isApproved = 0");
        mysqli_stmt_bind_param($remStmt, 'i', $leaveAppID);
        mysqli_stmt_execute($remStmt);
        $remaining = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($remStmt))['c'] ?? 0);
        mysqli_stmt_close($remStmt);

        $isFinal = ($remaining === 0);

        if ($isFinal) {
            // ── Apply multi-segment finalize per joining type ──

            // Load original proposed segments
            $segs = [];
            $sq = mysqli_query($con,
                "SELECT * FROM leave_application_segments
                 WHERE applicationID = $leaveAppID AND kind = 'proposed'
                 ORDER BY serial ASC, dataID ASC");
            if ($sq) while ($r = mysqli_fetch_assoc($sq)) $segs[] = $r;

            // Fallback: create synthetic from leave_applications if no proposed segments exist (legacy)
            if (empty($segs) && !empty($leaveApp['approvedDateFrom'])) {
                $synthStmt = mysqli_prepare($con,
                    "INSERT INTO leave_application_segments
                     (applicationID, kind, leaveType, dateFrom, dateTo, days, approvedDays, serial, createdBy, createdAt)
                     VALUES (?, 'proposed', ?, ?, ?, ?, ?, 1, ?, NOW())");
                $synDays = (int)$leaveApp['approvedDays'];
                if ($synDays <= 0) $synDays = (int)((strtotime($leaveApp['approvedDateTo']) - strtotime($leaveApp['approvedDateFrom'])) / 86400) + 1;
                $synLT = (int)$leaveApp['approvedLeaveType'];
                $synF  = $leaveApp['approvedDateFrom'];
                $synT  = $leaveApp['approvedDateTo'];
                mysqli_stmt_bind_param($synthStmt, 'iissiii',
                    $leaveAppID, $synLT, $synF, $synT, $synDays, $synDays, $actorUserId);
                @mysqli_stmt_execute($synthStmt);
                mysqli_stmt_close($synthStmt);

                // Re-fetch
                $segs = [];
                $sq2 = mysqli_query($con,
                    "SELECT * FROM leave_application_segments
                     WHERE applicationID = $leaveAppID AND kind = 'proposed'
                     ORDER BY serial ASC, dataID ASC");
                if ($sq2) while ($r = mysqli_fetch_assoc($sq2)) $segs[] = $r;
            }

            // Apply segment mutations
            if ($joiningType === 1) {
                // No-op
            } elseif ($joiningType === 2) {
                // Convention: joiningDate = last leave day (inclusive). Truncate segments at joiningDate.
                $truncTo = $joinIso;
                foreach ($segs as $sg) {
                    $segID = (int)$sg['dataID'];
                    $segFrom = $sg['dateFrom'];
                    $segTo   = $sg['dateTo'];
                    if ($segTo <= $truncTo) {
                        // Kept as-is
                        continue;
                    } elseif ($segFrom <= $truncTo) {
                        // Truncate this segment
                        $newDays = (int)((strtotime($truncTo) - strtotime($segFrom)) / 86400) + 1;
                        $upSeg = mysqli_prepare($con,
                            "UPDATE leave_application_segments
                             SET dateTo = ?, days = ?, approvedDays = ?
                             WHERE dataID = ?");
                        mysqli_stmt_bind_param($upSeg, 'siii', $truncTo, $newDays, $newDays, $segID);
                        if (!mysqli_stmt_execute($upSeg)) throw new Exception('Failed to truncate segment');
                        mysqli_stmt_close($upSeg);
                    } else {
                        // Delete this segment (fully after truncation)
                        $delSeg = mysqli_prepare($con, "DELETE FROM leave_application_segments WHERE dataID = ?");
                        mysqli_stmt_bind_param($delSeg, 'i', $segID);
                        if (!mysqli_stmt_execute($delSeg)) throw new Exception('Failed to delete segment');
                        mysqli_stmt_close($delSeg);
                    }
                }
            } elseif ($joiningType === 3) {
                // Append new proposed segment(s) for extension.
                // Prefer stored extensionSegmentsJson (multi-segment); fall back to a
                // single-segment append using $extLeaveType (legacy path).
                $extFromAll = date('Y-m-d', strtotime($approvedDateTo . ' +1 day'));
                $extToAll   = $joinIso;
                $extTotalDays = (int)((strtotime($extToAll) - strtotime($extFromAll)) / 86400) + 1;

                // Determine current max serial once
                $maxSerial = 1;
                foreach ($segs as $sg) { if ((int)$sg['serial'] > $maxSerial) $maxSerial = (int)$sg['serial']; }
                $newSerial = $maxSerial + 1;

                $extSegs = [];
                if (!empty($lja['extensionSegmentsJson'])) {
                    $decoded = json_decode($lja['extensionSegmentsJson'], true);
                    if (is_array($decoded)) $extSegs = $decoded;
                }
                if (empty($extSegs) && $extTotalDays > 0 && $extLeaveType > 0) {
                    // Legacy single-segment
                    $extSegs = [[
                        'leaveType' => $extLeaveType,
                        'dateFrom'  => $extFromAll,
                        'dateTo'    => $extToAll,
                        'days'      => $extTotalDays,
                    ]];
                }

                if (!empty($extSegs)) {
                    $insSeg = mysqli_prepare($con,
                        "INSERT INTO leave_application_segments
                         (applicationID, kind, leaveType, dateFrom, dateTo, days, approvedDays, serial, createdBy, createdAt)
                         VALUES (?, 'proposed', ?, ?, ?, ?, ?, ?, ?, NOW())");
                    foreach ($extSegs as $es) {
                        $lt = (int)($es['leaveType'] ?? 0);
                        $df = (string)($es['dateFrom'] ?? '');
                        $dt = (string)($es['dateTo']   ?? '');
                        $dd = (int)($es['days'] ?? 0);
                        if ($lt <= 0 || $dd <= 0 || $df === '' || $dt === '') continue;
                        mysqli_stmt_bind_param($insSeg, 'iissiiii',
                            $leaveAppID, $lt, $df, $dt, $dd, $dd, $newSerial, $actorUserId);
                        if (!mysqli_stmt_execute($insSeg)) throw new Exception('Failed to append extension segment');
                        $newSerial++;
                    }
                    mysqli_stmt_close($insSeg);
                }
            }

            // Recompute leave_applications denormalized cols from final segment set
            $finalQ = mysqli_query($con,
                "SELECT leaveType, dateFrom, dateTo, days FROM leave_application_segments
                 WHERE applicationID = $leaveAppID AND kind = 'proposed'
                 ORDER BY serial ASC, dataID ASC");
            $totalDays = 0; $minFrom = null; $maxTo = null; $firstType = 0;
            if ($finalQ) {
                while ($fr = mysqli_fetch_assoc($finalQ)) {
                    $totalDays += (int)$fr['days'];
                    if ($minFrom === null || strtotime($fr['dateFrom']) < strtotime($minFrom)) $minFrom = $fr['dateFrom'];
                    if ($maxTo   === null || strtotime($fr['dateTo'])   > strtotime($maxTo))   $maxTo   = $fr['dateTo'];
                    if ($firstType === 0) $firstType = (int)$fr['leaveType'];
                }
            }

            if ($totalDays > 0 && $minFrom && $maxTo) {
                $appUpd = mysqli_prepare($con,
                    "UPDATE leave_applications
                     SET approvedDateFrom = ?, approvedDateTo = ?, approvedDays = ?, approvedLeaveType = ?, lastUpdate = ?, updatedBy = ?
                     WHERE dataID = ?");
                mysqli_stmt_bind_param($appUpd, 'ssiisii',
                    $minFrom, $maxTo, $totalDays, $firstType, $now, $actorUserId, $leaveAppID);
                if (!mysqli_stmt_execute($appUpd)) throw new Exception('Failed to sync leave_applications');
                mysqli_stmt_close($appUpd);
            }

            // Create office_notice_record for joining
            $year = date('Y');
            $month = date('m');
            $noticeStmt = mysqli_prepare($con,
                "INSERT INTO office_notice_record (year, month, noticeType, leaveApplicationID) VALUES (?, ?, 2, ?)");
            $noticeType = 2;
            mysqli_stmt_bind_param($noticeStmt, 'ssi', $year, $month, $leaveAppID);
            @mysqli_stmt_execute($noticeStmt);
            $officeNoticeNumber = mysqli_insert_id($con);
            mysqli_stmt_close($noticeStmt);

            // Finalize parent joining row
            $officeNoticeNumberStr = (string)$officeNoticeNumber;
            $ljaFin = mysqli_prepare($con,
                "UPDATE leave_joining_application
                 SET status = 1, approvedBy = ?, approvedDate = ?, officeNoticeNumber = ?, lastUpdate = ?
                 WHERE dataID = ?");
            mysqli_stmt_bind_param($ljaFin, 'isssi', $actorUserId, $now, $officeNoticeNumberStr, $now, $joiningID);
            if (!mysqli_stmt_execute($ljaFin)) throw new Exception('Failed to finalize joining');
            mysqli_stmt_close($ljaFin);
        }

        mysqli_commit($con);
        mysqli_autocommit($con, true);

        if (function_exists('audit_log')) {
            audit_log($isFinal ? 'joining_finalized' : ($isSupervisorRow ? 'joining_recommended' : 'joining_chain_approved'), [
                'target_type'     => 'leave_joining',
                'target_id'       => $joiningID,
                'organization_id' => $appOrgID ?: null,
                'note'            => 'serial=' . $mySerial
                                   . '; type=' . $joiningType
                                   . '; joiningDate=' . ($joinIso ?? '?')
                                   . ($isFinal ? '; applied_to_leave=' . $leaveAppID : ''),
            ]);
        }

        // ── Notifications ────────────────────────────────────────────
        try {
            $applicantID   = (int)$leaveApp['applicantID'];
            $applName      = 'কর্মচারী';
            $anQ = mysqli_prepare($con, "SELECT employee_name FROM employee_list WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($anQ, 'i', $applicantID);
            mysqli_stmt_execute($anQ);
            $applName = mysqli_fetch_assoc(mysqli_stmt_get_result($anQ))['employee_name'] ?? 'কর্মচারী';
            mysqli_stmt_close($anQ);

            if ($isFinal) {
                // Notify applicant + all chain members that joining is finalized
                $chQ = mysqli_query($con,
                    "SELECT DISTINCT signatory FROM leave_joining_data_for_approval
                     WHERE leaveApplicationID = $leaveAppID");
                $chainEmpIDs = [];
                if ($chQ) while ($r = mysqli_fetch_assoc($chQ)) $chainEmpIDs[] = (int)$r['signatory'];

                send_notification([user_id_for_employee($applicantID)],
                    "আপনার যোগদান পত্র চূড়ান্তভাবে অনুমোদিত হয়েছে",
                    ['type' => 'joining_approved',
                     'link' => "views/leave/all-applications.php?menuslug=all-leave-application",
                     'isImportant' => 1]);

                send_notification(user_ids_for_employees($chainEmpIDs),
                    "$applName-এর যোগদান পত্র চূড়ান্তভাবে অনুমোদিত",
                    ['type' => 'joining_approved',
                     'link' => "views/leave/all-applications.php?menuslug=all-leave-application"]);
            } elseif ($isSupervisorRow) {
                // Supervisor recommended → notify center admin(s) of applicant's org
                if ($appOrgID > 0) {
                    $caQ = mysqli_query($con,
                        "SELECT dataID FROM user_list
                         WHERE isCenterAdmin = 1 AND organization_id = $appOrgID");
                    $caIDs = [];
                    if ($caQ) while ($r = mysqli_fetch_assoc($caQ)) $caIDs[] = (int)$r['dataID'];
                    send_notification($caIDs,
                        "$applName-এর যোগদান পত্র সুপারভাইজার-সুপারিশপ্রাপ্ত — সম্পাদনার অপেক্ষায়",
                        ['type' => 'joining_supervisor_recommended',
                         'link' => "views/leave/approve-joining-application.php?menuslug=leave-joining-approval&joiningID=" . $joiningID]);
                }
            } else {
                // Mid-chain approve → notify next pending signatory
                $nxtQ = mysqli_prepare($con,
                    "SELECT signatory FROM leave_joining_data_for_approval
                     WHERE leaveApplicationID = ? AND isApproved = 0 AND serial > ?
                     ORDER BY serial ASC LIMIT 1");
                mysqli_stmt_bind_param($nxtQ, 'ii', $leaveAppID, $mySerial);
                mysqli_stmt_execute($nxtQ);
                $nx = mysqli_fetch_assoc(mysqli_stmt_get_result($nxtQ)) ?: [];
                mysqli_stmt_close($nxtQ);
                if (!empty($nx['signatory'])) {
                    send_notification([user_id_for_employee((int)$nx['signatory'])],
                        "$applName-এর যোগদান পত্র আপনার অনুমোদনের অপেক্ষায়",
                        ['type' => 'joining_pending',
                         'link' => "views/leave/approve-joining-application.php?menuslug=leave-joining-approval&joiningID=" . $joiningID]);
                }
            }
        } catch (\Throwable $e) { /* silent */ }

        out(1, $isFinal
            ? 'যোগদান চূড়ান্তভাবে অনুমোদিত — মূল ছুটির অংশসমূহ আপডেট হয়েছে'
            : ($isSupervisorRow ? 'সুপারিশ করা হয়েছে — পরবর্তী স্বাক্ষরকারীর অপেক্ষায়' : 'অনুমোদিত — পরবর্তী স্বাক্ষরকারীর অপেক্ষায়'),
            ['final' => $isFinal]);
    }

    // ──────── REJECT ────────
    if ($action === 'reject') {
        $rej = mysqli_prepare($con,
            "UPDATE leave_joining_data_for_approval
             SET isApproved = 2, approvedDate = ?, note = ?, rejectionReason = ?
             WHERE dataID = ?");
        mysqli_stmt_bind_param($rej, 'sssi', $now, $reason, $reason, $myRowID);
        if (!mysqli_stmt_execute($rej)) throw new Exception('Failed to mark rejection');
        mysqli_stmt_close($rej);

        $ljaRej = mysqli_prepare($con,
            "UPDATE leave_joining_application
             SET status = 2, rejectedBy = ?, rejectedDate = ?, rejectionReason = ?, lastUpdate = ?
             WHERE dataID = ?");
        mysqli_stmt_bind_param($ljaRej, 'isssi', $actorUserId, $now, $reason, $now, $joiningID);
        if (!mysqli_stmt_execute($ljaRej)) throw new Exception('Failed to update joining status');
        mysqli_stmt_close($ljaRej);

        mysqli_commit($con);
        mysqli_autocommit($con, true);

        if (function_exists('audit_log')) {
            audit_log('joining_rejected', [
                'target_type'     => 'leave_joining',
                'target_id'       => $joiningID,
                'organization_id' => $appOrgID ?: null,
                'note'            => 'reason=' . mb_substr($reason, 0, 200),
            ]);
        }

        // Notify applicant of rejection
        try {
            $applicantID = (int)$leaveApp['applicantID'];
            send_notification([user_id_for_employee($applicantID)],
                "আপনার যোগদান পত্র প্রত্যাখ্যাত হয়েছে। কারণ: " . mb_substr($reason, 0, 120),
                ['type' => 'joining_rejected',
                 'link' => "views/leave/all-applications.php?menuslug=all-leave-application",
                 'isImportant' => 1]);
        } catch (\Throwable $e) { /* silent */ }

        out(1, 'প্রত্যাখ্যাত');
    }

    // ──────── RETURN ────────
    if ($action === 'return') {
        // Mark parent as returned + clear pending chain rows below current serial
        $retUpd = mysqli_prepare($con,
            "UPDATE leave_joining_application SET lastUpdate = ? WHERE dataID = ?");
        mysqli_stmt_bind_param($retUpd, 'si', $now, $joiningID);
        if (!mysqli_stmt_execute($retUpd)) throw new Exception('Failed to mark return');
        mysqli_stmt_close($retUpd);

        // Reset all pending rows (so submitter can resubmit)
        $clearStmt = mysqli_prepare($con,
            "DELETE FROM leave_joining_data_for_approval
             WHERE leaveApplicationID = ? AND isApproved = 0");
        mysqli_stmt_bind_param($clearStmt, 'i', $leaveAppID);
        if (!mysqli_stmt_execute($clearStmt)) throw new Exception('Failed to clear pending chain rows');
        mysqli_stmt_close($clearStmt);

        // Mark parent application status=3 (returned)
        $statusUpd = mysqli_prepare($con,
            "UPDATE leave_joining_application SET status = 3, rejectionReason = ?, lastUpdate = ? WHERE dataID = ?");
        mysqli_stmt_bind_param($statusUpd, 'ssi', $reason, $now, $joiningID);
        if (!mysqli_stmt_execute($statusUpd)) throw new Exception('Failed to set returned status');
        mysqli_stmt_close($statusUpd);

        mysqli_commit($con);
        mysqli_autocommit($con, true);

        if (function_exists('audit_log')) {
            audit_log('joining_returned', [
                'target_type'     => 'leave_joining',
                'target_id'       => $joiningID,
                'organization_id' => $appOrgID ?: null,
                'note'            => 'returned_by=' . $actorEmpId . '; reason=' . mb_substr($reason, 0, 200),
            ]);
        }

        // Notify applicant so they can resubmit
        try {
            $applicantID = (int)$leaveApp['applicantID'];
            send_notification([user_id_for_employee($applicantID)],
                "আপনার যোগদান পত্র ফেরত পাঠানো হয়েছে। কারণ: " . mb_substr($reason, 0, 120),
                ['type' => 'joining_returned',
                 'link' => "views/leave/all-applications.php?menuslug=all-leave-application",
                 'isImportant' => 1]);
        } catch (\Throwable $e) { /* silent */ }

        out(1, 'আবেদনকারীর কাছে ফেরত পাঠানো হয়েছে');
    }

    throw new Exception('Unreachable');

} catch (Exception $e) {
    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    out(0, 'ব্যর্থ: ' . $e->getMessage());
}
