<?php
/**
 * Save edits to leave application segments (approver-side editor).
 *
 * Permissions: caller must be the current pending signatory for this application.
 * Body params:
 *   applicationID — leave_applications.dataID
 *   approvalID    — leave_data_for_approval.dataID (the row representing CURRENT signatory)
 *   segments      — JSON array of {segmentID?, leaveType, dateFrom, dateTo, days}
 *
 * Behavior:
 *   - Validates BSR rules (per-segment + combined + overlap)
 *   - Diffs incoming segments vs DB
 *   - Inserts new, updates changed, removes deleted
 *   - Writes a leave_segment_history row for each delta (action: created/edited/removed)
 *   - Updates parent leave_applications.dateFrom/dateTo to envelope all segments
 *
 * Response:  JSON { ok: true|false, error?, updated?, removed?, added? }
 */

session_start();
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: application/json; charset=utf-8');

function reply($ok, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok], $extra));
    exit;
}

if (empty($_SESSION['username'])) reply(false, ['error' => 'Not logged in']);

$applicationID = (int)($_POST['applicationID'] ?? 0);
$approvalID    = (int)($_POST['approvalID']    ?? 0);
$segmentsJson  = $_POST['segments']            ?? '';
if (!$applicationID) reply(false, ['error' => 'Missing application ID']);

$incoming = json_decode($segmentsJson, true);
if (!is_array($incoming) || count($incoming) === 0) {
    reply(false, ['error' => 'Invalid segments']);
}

// Look up the current user. "Center admin" privilege here = anyone whose
// user group has been granted access to the `allowed-leave-applications`
// submodule (the ছুটি সম্পাদনা menu that owns this forward-to-approval page).
// That's the same permission that lets them see this page in the first place,
// so if they can open the page they can edit proposed segments here.
// Legacy fallbacks kept: isCenterAdmin=1 OR user_group_id=7.
$uStmt = mysqli_prepare($con, "SELECT dataID, employee_id, full_name, isCenterAdmin, user_group_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($uStmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
mysqli_stmt_close($uStmt);
if (!$user) reply(false, ['error' => 'User not found']);
$userDataID    = (int)$user['dataID'];
$empID         = (int)$user['employee_id'];
$userGroupID   = (int)($user['user_group_id'] ?? 0);

$isCenterAdmin = !empty($user['isCenterAdmin']) || $userGroupID === 7;
if (!$isCenterAdmin && $userGroupID > 0) {
    $permStmt = mysqli_prepare($con,
        "SELECT 1 FROM group_access_permission gap
         INNER JOIN submodules sm ON gap.submodule_id = sm.dataID
         WHERE gap.user_group_id = ? AND sm.slug = 'allowed-leave-applications'
         LIMIT 1");
    mysqli_stmt_bind_param($permStmt, 'i', $userGroupID);
    mysqli_stmt_execute($permStmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($permStmt))) {
        $isCenterAdmin = true;
    }
    mysqli_stmt_close($permStmt);
}

// Lookup full_name from employee_list (since user_list.full_name may not be the friendly name)
$enStmt = mysqli_prepare($con, "SELECT employee_name FROM employee_list WHERE id = ?");
mysqli_stmt_bind_param($enStmt, 'i', $empID);
mysqli_stmt_execute($enStmt);
$enRow = mysqli_fetch_assoc(mysqli_stmt_get_result($enStmt));
mysqli_stmt_close($enStmt);
$displayName = $enRow['employee_name'] ?? ($user['full_name'] ?? '—');

// Validate permission: signatory (approvalID required + ownership) OR center admin (no approvalID needed)
$signatoryLevel = 0; // 0 = center admin; >0 = signatory's serial in the chain
if ($isCenterAdmin) {
    // Center admin can edit proposed segments before forwarding (as long as application not finalized)
    $appCheck = mysqli_prepare($con, "SELECT status FROM leave_applications WHERE dataID = ? LIMIT 1");
    mysqli_stmt_bind_param($appCheck, 'i', $applicationID);
    mysqli_stmt_execute($appCheck);
    $appRow = mysqli_fetch_assoc(mysqli_stmt_get_result($appCheck));
    mysqli_stmt_close($appCheck);
    if (!$appRow) reply(false, ['error' => 'Application not found']);
    if ((int)$appRow['status'] === 1) reply(false, ['error' => 'Already approved — cannot edit']);
} else {
    if (!$approvalID) reply(false, ['error' => 'Missing approval ID']);
    $arStmt = mysqli_prepare($con,
        "SELECT signatory, isApproved, serial FROM leave_data_for_approval
         WHERE dataID = ? AND leaveApplicationID = ? LIMIT 1");
    mysqli_stmt_bind_param($arStmt, 'ii', $approvalID, $applicationID);
    mysqli_stmt_execute($arStmt);
    $ar = mysqli_fetch_assoc(mysqli_stmt_get_result($arStmt));
    mysqli_stmt_close($arStmt);
    if (!$ar) reply(false, ['error' => 'Approval row not found']);
    if ((int)$ar['signatory'] !== $empID) reply(false, ['error' => 'Permission denied — not your approval row']);
    if ((int)$ar['isApproved'] !== 0) reply(false, ['error' => 'Already actioned — cannot edit']);
    $signatoryLevel = (int)$ar['serial'];
}

// A signatory who ALSO holds isCenterAdmin takes the branch above and would
// otherwise be logged with signatoryLevel = 0, making their edit
// indistinguishable from a real center-admin edit in the history — and, when
// the same person sits in the chain twice (supervisor + approver), impossible
// to attribute to the right desk. Whenever the caller supplied an approvalID
// that genuinely belongs to them, record that row's serial as the level.
// Permission logic above is deliberately untouched.
if ($signatoryLevel === 0 && $approvalID) {
    $lvlStmt = mysqli_prepare($con,
        "SELECT serial FROM leave_data_for_approval
         WHERE dataID = ? AND leaveApplicationID = ? AND signatory = ? LIMIT 1");
    mysqli_stmt_bind_param($lvlStmt, 'iii', $approvalID, $applicationID, $empID);
    mysqli_stmt_execute($lvlStmt);
    if ($lvlRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lvlStmt))) {
        $signatoryLevel = (int)$lvlRow['serial'];
    }
    mysqli_stmt_close($lvlStmt);
}

// ── Validate incoming segments ────────────────────────────────────
$cleanSegs = [];
foreach ($incoming as $i => $s) {
    $lt = (int)($s['leaveType'] ?? 0);
    $df = $s['dateFrom'] ?? '';
    $dt = $s['dateTo']   ?? '';
    $sid = isset($s['segmentID']) && $s['segmentID'] !== '' ? (int)$s['segmentID'] : null;

    if (!$lt || !$df || !$dt) reply(false, ['error' => 'Segment ' . ($i + 1) . ' এ তথ্য অসম্পূর্ণ']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $df) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
        reply(false, ['error' => 'Segment ' . ($i + 1) . ' এর তারিখ ফরম্যাট ভুল']);
    }
    $dft = strtotime($df); $dtt = strtotime($dt);
    if (!$dft || !$dtt || $dtt < $dft) reply(false, ['error' => 'Segment ' . ($i + 1) . ' এর তারিখ পরিসর ভুল']);
    $days = (int)(($dtt - $dft) / 86400) + 1;
    $cleanSegs[] = ['segmentID' => $sid, 'leaveType' => $lt, 'dateFrom' => $df, 'dateTo' => $dt, 'days' => $days];
}

// BSR rules
$hasCL = false; $hasNonCL = false;
foreach ($cleanSegs as $s) {
    if ($s['leaveType'] === 8)        $hasCL = true;
    elseif ($s['leaveType'] !== 22)   $hasNonCL = true;
}
if ($hasCL && $hasNonCL) reply(false, ['error' => 'নৈমিত্তিক ছুটি অন্য ধরনের ছুটির সাথে মিশানো যাবে না (সরকারি চাকরি বিধিমালা)।']);

foreach ($cleanSegs as $i => $s) {
    if ($s['leaveType'] === 1 && $s['days'] > 120) reply(false, ['error' => 'পূর্ণ গড় বেতনে একটানা সর্বোচ্চ ১২০ দিন (segment ' . ($i + 1) . ')']);
    if ($s['leaveType'] === 8 && $s['days'] > 10)  reply(false, ['error' => 'নৈমিত্তিক একটানা সর্বোচ্চ ১০ দিন (segment ' . ($i + 1) . ')']);
}

// Overlap
for ($a = 0; $a < count($cleanSegs); $a++) {
    for ($b = $a + 1; $b < count($cleanSegs); $b++) {
        $A = $cleanSegs[$a]; $B = $cleanSegs[$b];
        if ($A['dateFrom'] <= $B['dateTo'] && $B['dateFrom'] <= $A['dateTo']) {
            reply(false, ['error' => 'Segment ' . ($a + 1) . ' ও ' . ($b + 1) . ' এর তারিখ overlap']);
        }
    }
}

// ── Diff against existing DB segments (only 'proposed' — supervisor edits proposed) ──
$existing = [];
$exStmt = mysqli_prepare($con, "SELECT * FROM leave_application_segments WHERE applicationID = ? AND kind = 'proposed'");
mysqli_stmt_bind_param($exStmt, 'i', $applicationID);
mysqli_stmt_execute($exStmt);
$exRes = mysqli_stmt_get_result($exStmt);
while ($row = mysqli_fetch_assoc($exRes)) {
    $existing[(int)$row['dataID']] = $row;
}
mysqli_stmt_close($exStmt);

mysqli_begin_transaction($con);
$updated = 0; $added = 0; $removed = 0;

try {
    // Track which existing IDs survived in incoming
    $survivors = [];
    foreach ($cleanSegs as $idx => $s) {
        $sid = $s['segmentID'];
        if ($sid && isset($existing[$sid])) {
            $survivors[$sid] = true;
            $old = $existing[$sid];
            // Detect change
            $changed = ((int)$old['leaveType'] !== $s['leaveType']
                     || $old['dateFrom']      !== $s['dateFrom']
                     || $old['dateTo']        !== $s['dateTo']
                     || (int)$old['days']     !== $s['days']);
            if ($changed) {
                $u = mysqli_prepare($con, "UPDATE leave_application_segments SET leaveType=?, dateFrom=?, dateTo=?, days=?, serial=?, updatedAt=NOW() WHERE dataID=?");
                $serialIdx = $idx + 1;
                mysqli_stmt_bind_param($u, 'issiii', $s['leaveType'], $s['dateFrom'], $s['dateTo'], $s['days'], $serialIdx, $sid);
                mysqli_stmt_execute($u);
                mysqli_stmt_close($u);

                $h = mysqli_prepare($con, "INSERT INTO leave_segment_history
                    (applicationID, segmentID, action, signatoryLevel, changedBy, changedByName, oldData, newData, changedAt)
                    VALUES (?, ?, 'edited', ?, ?, ?, ?, ?, NOW())");
                $oldJson = json_encode([
                    'leaveType' => (int)$old['leaveType'],
                    'dateFrom'  => $old['dateFrom'],
                    'dateTo'    => $old['dateTo'],
                    'days'      => (int)$old['days']
                ], JSON_UNESCAPED_UNICODE);
                $newJson = json_encode([
                    'leaveType' => $s['leaveType'],
                    'dateFrom'  => $s['dateFrom'],
                    'dateTo'    => $s['dateTo'],
                    'days'      => $s['days']
                ], JSON_UNESCAPED_UNICODE);
                mysqli_stmt_bind_param($h, 'iiiisss', $applicationID, $sid, $signatoryLevel, $userDataID, $displayName, $oldJson, $newJson);
                mysqli_stmt_execute($h);
                mysqli_stmt_close($h);
                $updated++;
            }
        } else {
            // New segment (added by signatory) — kind='proposed' only
            $ins = mysqli_prepare($con, "INSERT INTO leave_application_segments
                (applicationID, kind, leaveType, dateFrom, dateTo, days, serial, createdBy, createdAt)
                VALUES (?, 'proposed', ?, ?, ?, ?, ?, ?, NOW())");
            $serialIdx = $idx + 1;
            mysqli_stmt_bind_param($ins, 'iissiii', $applicationID, $s['leaveType'], $s['dateFrom'], $s['dateTo'], $s['days'], $serialIdx, $userDataID);
            mysqli_stmt_execute($ins);
            $newID = mysqli_insert_id($con);
            mysqli_stmt_close($ins);

            $newJson = json_encode([
                'leaveType' => $s['leaveType'],
                'dateFrom'  => $s['dateFrom'],
                'dateTo'    => $s['dateTo'],
                'days'      => $s['days']
            ], JSON_UNESCAPED_UNICODE);
            $h = mysqli_prepare($con, "INSERT INTO leave_segment_history
                (applicationID, segmentID, action, signatoryLevel, changedBy, changedByName, newData, changedAt)
                VALUES (?, ?, 'created', ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($h, 'iiiiss', $applicationID, $newID, $signatoryLevel, $userDataID, $displayName, $newJson);
            mysqli_stmt_execute($h);
            mysqli_stmt_close($h);

            $added++;
        }
    }

    // Remove segments not in incoming
    foreach ($existing as $sid => $old) {
        if (!isset($survivors[$sid])) {
            $del = mysqli_prepare($con, "DELETE FROM leave_application_segments WHERE dataID = ?");
            mysqli_stmt_bind_param($del, 'i', $sid);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            $oldJson = json_encode([
                'leaveType' => (int)$old['leaveType'],
                'dateFrom'  => $old['dateFrom'],
                'dateTo'    => $old['dateTo'],
                'days'      => (int)$old['days']
            ], JSON_UNESCAPED_UNICODE);
            $h = mysqli_prepare($con, "INSERT INTO leave_segment_history
                (applicationID, segmentID, action, signatoryLevel, changedBy, changedByName, oldData, changedAt)
                VALUES (?, ?, 'removed', ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($h, 'iiiiss', $applicationID, $sid, $signatoryLevel, $userDataID, $displayName, $oldJson);
            mysqli_stmt_execute($h);
            mysqli_stmt_close($h);
            $removed++;
        }
    }

    // Update parent leave_applications dateFrom/dateTo as envelope of all segments
    $minFrom = min(array_column($cleanSegs, 'dateFrom'));
    $maxTo   = max(array_column($cleanSegs, 'dateTo'));
    $up = mysqli_prepare($con, "UPDATE leave_applications SET dateFrom = ?, dateTo = ? WHERE dataID = ?");
    mysqli_stmt_bind_param($up, 'ssi', $minFrom, $maxTo, $applicationID);
    mysqli_stmt_execute($up);
    mysqli_stmt_close($up);

    mysqli_commit($con);

    if (function_exists('audit_log')) {
        audit_log('leave_segments_edited', [
            'target_type' => 'leave_application',
            'target_id'   => (int)$applicationID,
            'note'        => "added=$added; updated=$updated; removed=$removed; sigLevel=$signatoryLevel",
        ]);
    }

    reply(true, ['updated' => $updated, 'added' => $added, 'removed' => $removed]);

} catch (Exception $e) {
    mysqli_rollback($con);
    reply(false, ['error' => 'DB error: ' . $e->getMessage()]);
}
