<?php
/**
 * Migration: approval flow for the yearly leave certificate (ছুটির সনদ).
 *
 *  - yearly_leave_summary gains isApproved / approvedBy / approvedDate /
 *    rejectionReason. 0 = pending, 1 = approved, 2 = rejected.
 *  - Certificates issued before this existed are backfilled as approved:
 *    they were already handed out, and marking them pending would make signed
 *    paper look unapproved on screen.
 *  - Registers the ছুটি সনদ অনুমোদন queue under the ছুটি module, granted to the
 *    same groups that hold পূর্ববর্তী ছুটির তথ্য অনুমোদন — the sibling approval
 *    driven by the same leave_edit_approval_signatory table.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_certificate_approval.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
$log = [];

$LEAVE_MODULE_ID = 47;   // ছুটি
$SIBLING_SLUG    = 'previous-leave-info-approve';

// ── Step 1: approval columns ──────────────────────────────────────────
$cols = [
    'isApproved'      => "ADD COLUMN isApproved TINYINT NOT NULL DEFAULT 0",
    'approvedBy'      => "ADD COLUMN approvedBy INT NULL",
    'approvedDate'    => "ADD COLUMN approvedDate DATETIME NULL",
    'rejectionReason' => "ADD COLUMN rejectionReason TEXT NULL",
];
$added = [];
foreach ($cols as $col => $clause) {
    $res = mysqli_query($con, "SHOW COLUMNS FROM yearly_leave_summary LIKE '$col'");
    if ($res && mysqli_num_rows($res) > 0) { $log[] = "SKIP: $col already exists"; continue; }
    if (mysqli_query($con, "ALTER TABLE yearly_leave_summary $clause")) {
        $log[]   = "ADDED column: yearly_leave_summary.$col";
        $added[] = $col;
    } else {
        $log[] = "ERROR adding $col: " . mysqli_error($con);
    }
}

// Only backfill on the run that created the column, so a later re-run cannot
// silently approve certificates that a signatory has left pending on purpose.
if (in_array('isApproved', $added, true)) {
    mysqli_query($con, "UPDATE yearly_leave_summary SET isApproved = 1 WHERE isApproved = 0");
    $log[] = "Backfilled " . mysqli_affected_rows($con) . " pre-existing certificate(s) as approved";
}

// ── Step 2: the approval queue menu ───────────────────────────────────
$stmt = mysqli_prepare($con,
    "SELECT dataID FROM submodules WHERE slug = 'leave-certificate-approval' AND deleted = 0 LIMIT 1");
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
$queueId = (int)($row['dataID'] ?? 0);

if ($queueId) {
    $log[] = "SKIP: leave-certificate-approval already exists (id $queueId)";
} else {
    $hasParent = false;
    $res = mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
    if ($res && mysqli_num_rows($res) > 0) $hasParent = true;

    $sql = $hasParent
        ? "INSERT INTO submodules (submodule_name, module_id, parent_id, page_link, slug, display_order, deleted, create_date)
           VALUES ('ছুটি সনদ অনুমোদন', ?, 0, 'views/leave-certificate/approval.php', 'leave-certificate-approval', 13, 0, NOW())"
        : "INSERT INTO submodules (submodule_name, module_id, page_link, slug, display_order, deleted, create_date)
           VALUES ('ছুটি সনদ অনুমোদন', ?, 'views/leave-certificate/approval.php', 'leave-certificate-approval', 13, 0, NOW())";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $LEAVE_MODULE_ID);
    if (mysqli_stmt_execute($stmt)) {
        $queueId = mysqli_insert_id($con);
        $log[]   = "ADDED submodule: ছুটি সনদ অনুমোদন (id $queueId)";
    } else {
        $log[] = "ERROR inserting submodule: " . mysqli_error($con);
    }
    mysqli_stmt_close($stmt);
}

// ── Step 3: grant it wherever the sibling approval is granted ─────────
// The page gates by signatory itself, so a group that holds it but has no
// signatory assigned simply sees the "you are not a signatory" notice.
if ($queueId) {
    $groups = [];
    $gq = mysqli_prepare($con,
        "SELECT DISTINCT g.user_group_id
         FROM group_access_permission g
         INNER JOIN submodules s ON s.dataID = g.submodule_id
         WHERE s.slug = ? AND s.deleted = 0");
    mysqli_stmt_bind_param($gq, 's', $SIBLING_SLUG);
    mysqli_stmt_execute($gq);
    $gr = mysqli_stmt_get_result($gq);
    while ($g = mysqli_fetch_assoc($gr)) $groups[] = (int)$g['user_group_id'];
    mysqli_stmt_close($gq);

    if (!$groups) $groups = [1];   // fall back to super admin

    $granted = 0;
    foreach ($groups as $gid) {
        $chk = mysqli_prepare($con,
            "SELECT id FROM group_access_permission WHERE user_group_id = ? AND submodule_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, 'ii', $gid, $queueId);
        mysqli_stmt_execute($chk);
        $have = mysqli_stmt_get_result($chk)->fetch_assoc();
        mysqli_stmt_close($chk);
        if ($have) continue;

        $ins = mysqli_prepare($con,
            "INSERT INTO group_access_permission (user_group_id, module_id, submodule_id) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iii', $gid, $LEAVE_MODULE_ID, $queueId);
        if (mysqli_stmt_execute($ins)) $granted++;
        mysqli_stmt_close($ins);
    }
    $log[] = $granted
        ? "GRANTED leave-certificate-approval to $granted group(s): " . implode(', ', $groups)
        : "SKIP: all target groups already have leave-certificate-approval";
}

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "CERTIFICATE APPROVAL MIGRATION\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
