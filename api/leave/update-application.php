<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');
require_once(__DIR__ . '/../../function.php');

header('Content-Type: text/plain');

if (!isset($_SESSION['username'])) { echo 0; exit; }

$editID = (int)($_POST['editID'] ?? 0);
if ($editID <= 0) { echo 0; exit; }

// Get current user's employee_id
$uStmt = mysqli_prepare($con, "SELECT employee_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
mysqli_stmt_close($uStmt);
$userEmpId = (int)($userRow['employee_id'] ?? 0);
if ($userEmpId <= 0) { echo 0; exit; }

// Verify ownership + editability. Allowed states:
//   0 = pending  (regular edit before any signatory acts)
//   3 = returned (applicant editing a "পুনঃ যাচাই" send-back for resubmit)
$aStmt = mysqli_prepare($con,
    "SELECT applicantID, status, attachment FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($aStmt, 'i', $editID);
mysqli_stmt_execute($aStmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));
mysqli_stmt_close($aStmt);
if (!$existing) { echo 0; exit; }
if ((int)$existing['applicantID'] !== $userEmpId) { echo 0; exit; }
if (!in_array((int)$existing['status'], [0, 3], true)) { echo 0; exit; }
$wasReturned = ((int)$existing['status'] === 3);

$cStmt = mysqli_prepare($con,
    "SELECT COUNT(*) c FROM leave_data_for_approval WHERE leaveApplicationID = ? AND isApproved = 1");
mysqli_stmt_bind_param($cStmt, 'i', $editID);
mysqli_stmt_execute($cStmt);
$cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['c'] ?? 0);
mysqli_stmt_close($cStmt);
if ($cnt > 0) { echo 0; exit; }

// ── Inputs ──
$applicationType  = (int)($_POST['applicationType'] ?? 1);
$applicationTo    = (int)($_POST['applicationTo']   ?? 0);
$isinformedValue  = (int)($_POST['isinformedValue'] ?? 0);
$onbehalf         = (int)($_POST['onbehalf']        ?? 0);
$subject          = trim($_POST['subject']          ?? '');
$leaveApplication = trim($_POST['leaveApplication'] ?? '');
$supervisorID     = (int)($_POST['supervisorID']    ?? 0);

$segmentTypes = $_POST['segment_leaveType'] ?? [];
$segmentFroms = $_POST['segment_dateFrom']  ?? [];
$segmentTos   = $_POST['segment_dateTo']    ?? [];
$segmentDays  = $_POST['segment_days']      ?? [];

if (!is_array($segmentTypes) || count($segmentTypes) === 0) { echo 0; exit; }

// Convert dd/mm/yyyy → yyyy-mm-dd
$toYmd = function($dmy) {
    $p = explode('/', $dmy);
    if (count($p) !== 3) return null;
    return $p[2] . '-' . str_pad($p[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($p[0], 2, '0', STR_PAD_LEFT);
};

$normalizedSegs = [];
$earliestFrom = null; $latestTo = null;
foreach ($segmentTypes as $i => $tid) {
    $tid = (int)$tid;
    $from = $toYmd($segmentFroms[$i] ?? '');
    $to   = $toYmd($segmentTos[$i]   ?? '');
    $days = (int)($segmentDays[$i]   ?? 0);
    if (!$tid || !$from || !$to || $days <= 0) continue;
    $normalizedSegs[] = ['type' => $tid, 'from' => $from, 'to' => $to, 'days' => $days];
    if (!$earliestFrom || $from < $earliestFrom) $earliestFrom = $from;
    if (!$latestTo    || $to   > $latestTo)     $latestTo     = $to;
}
if (count($normalizedSegs) === 0) { echo 0; exit; }

$leaveType = $normalizedSegs[0]['type']; // primary type = first segment

// ── Handle new attachment (optional — keep old if no new file) ──
$attachment = $existing['attachment'] ?? '';
if (!empty($_FILES['leaveFile']['tmp_name']) && is_uploaded_file($_FILES['leaveFile']['tmp_name'])) {
    $newName = $_FILES['leaveFile']['name'];
    $ext = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','pdf']) && $_FILES['leaveFile']['size'] <= 2097152) {
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        if (move_uploaded_file($_FILES['leaveFile']['tmp_name'], $uploadDir . $newName)) {
            // Remove old file
            if ($attachment && $attachment !== $newName) {
                $old = $uploadDir . $attachment;
                if (file_exists($old)) @unlink($old);
            }
            $attachment = $newName;
        }
    }
}

// ── Transactional update ──
mysqli_begin_transaction($con);
try {
    // Update main row — also reset status to 0 (pending) so a ফেরত-returned
    // application re-enters the approval chain on resubmit.
    $u = mysqli_prepare($con,
        "UPDATE leave_applications
         SET applicationType=?, applicationTo=?, isinformed=?, onbehalf=?,
             leaveType=?, dateFrom=?, dateTo=?, subject=?, leaveApplication=?,
             attachment=?, status=0
         WHERE dataID=?");
    mysqli_stmt_bind_param($u, 'iiiiisssssi',
        $applicationType, $applicationTo, $isinformedValue, $onbehalf,
        $leaveType, $earliestFrom, $latestTo, $subject, $leaveApplication,
        $attachment, $editID);
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);

    // Replace segments (delete old, insert new)
    $del1 = mysqli_prepare($con, "DELETE FROM leave_segment_history WHERE applicationID = ?");
    mysqli_stmt_bind_param($del1, 'i', $editID);
    mysqli_stmt_execute($del1);
    mysqli_stmt_close($del1);

    $del2 = mysqli_prepare($con, "DELETE FROM leave_application_segments WHERE applicationID = ?");
    mysqli_stmt_bind_param($del2, 'i', $editID);
    mysqli_stmt_execute($del2);
    mysqli_stmt_close($del2);

    // Insert both 'requested' (frozen) and 'proposed' (mutable) for each segment
    $sStmt = mysqli_prepare($con,
        "INSERT INTO leave_application_segments
         (applicationID, kind, leaveType, dateFrom, dateTo, days, serial, createdBy, createdAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $hStmt = mysqli_prepare($con,
        "INSERT INTO leave_segment_history
         (applicationID, segmentID, action, signatoryLevel, changedBy, changedByName, newData, changedAt)
         VALUES (?, ?, 'edited', 0, ?, ?, ?, NOW())");
    $createdBy = $userEmpId;
    $changedByName = '';
    foreach ($normalizedSegs as $idx => $s) {
        $serial = $idx + 1;
        // requested
        $kindReq = 'requested';
        mysqli_stmt_bind_param($sStmt, 'isissiii', $editID, $kindReq, $s['type'], $s['from'], $s['to'], $s['days'], $serial, $createdBy);
        mysqli_stmt_execute($sStmt);
        $reqSegID = mysqli_insert_id($con);
        // proposed
        $kindProp = 'proposed';
        mysqli_stmt_bind_param($sStmt, 'isissiii', $editID, $kindProp, $s['type'], $s['from'], $s['to'], $s['days'], $serial, $createdBy);
        mysqli_stmt_execute($sStmt);

        $newJson = json_encode($s);
        mysqli_stmt_bind_param($hStmt, 'iiiis', $editID, $reqSegID, $createdBy, $changedByName, $newJson);
        mysqli_stmt_execute($hStmt);
    }
    mysqli_stmt_close($sStmt);
    mysqli_stmt_close($hStmt);

    // Update supervisor in approval chain (if changed)
    if ($supervisorID > 0) {
        $supU = mysqli_prepare($con,
            "UPDATE leave_data_for_approval SET signatory=?, isRead=0
             WHERE leaveApplicationID=? AND isSupervisor=1");
        mysqli_stmt_bind_param($supU, 'ii', $supervisorID, $editID);
        mysqli_stmt_execute($supU);
        mysqli_stmt_close($supU);
    } elseif ($wasReturned) {
        // Resubmitting a returned application without changing supervisor —
        // still reset isRead so the supervisor sees it fresh in their queue.
        $supU = mysqli_prepare($con,
            "UPDATE leave_data_for_approval SET isRead=0
             WHERE leaveApplicationID=? AND isSupervisor=1");
        mysqli_stmt_bind_param($supU, 'i', $editID);
        mysqli_stmt_execute($supU);
        mysqli_stmt_close($supU);
    }

    mysqli_commit($con);

    // On a returned-application resubmit, notify the current supervisor so
    // they know it's back in their queue. Best-effort — never fails the save.
    if ($wasReturned) {
        try {
            if (function_exists('send_notification')) {
                $sqQ = mysqli_prepare($con,
                    "SELECT ul.dataID
                     FROM leave_data_for_approval ldfa
                     INNER JOIN user_list ul ON ul.employee_id = ldfa.signatory
                     WHERE ldfa.leaveApplicationID = ? AND ldfa.isSupervisor = 1
                     LIMIT 1");
                mysqli_stmt_bind_param($sqQ, 'i', $editID);
                mysqli_stmt_execute($sqQ);
                $supRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sqQ)) ?: [];
                mysqli_stmt_close($sqQ);
                $supUserID = (int)($supRow['dataID'] ?? 0);
                if ($supUserID > 0) {
                    $nameQ = mysqli_prepare($con,
                        "SELECT el.employee_name FROM employee_list el WHERE el.id = ? LIMIT 1");
                    mysqli_stmt_bind_param($nameQ, 'i', $userEmpId);
                    mysqli_stmt_execute($nameQ);
                    $meRow = mysqli_fetch_assoc(mysqli_stmt_get_result($nameQ)) ?: [];
                    mysqli_stmt_close($nameQ);
                    $appNo = 'BITAC/' . date('Y') . '/' . (int)$editID;
                    send_notification([$supUserID],
                        ($meRow['employee_name'] ?? 'আবেদনকারী') . " পুনঃ যাচাইয়ের পরে আবেদন ($appNo) সংশোধন করে পুনরায় জমা দিয়েছেন",
                        ['type' => 'leave_resubmitted',
                         'link' => 'views/leave/approval.php?menuslug=leave-approval',
                         'isImportant' => 1]);
                }
            }
        } catch (\Throwable $e) { /* silent */ }
    }

    if (function_exists('audit_log')) {
        audit_log('leave_application_resubmitted', [
            'target_type' => 'leave_application',
            'target_id'   => (int)$editID,
            'note'        => 'segments=' . count($normalizedSegs),
        ]);
    }

    echo 1;
} catch (Exception $e) {
    mysqli_rollback($con);
    echo 0;
}
