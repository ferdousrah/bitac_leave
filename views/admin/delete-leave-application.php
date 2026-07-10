<?php
/**
 * Admin tool: delete a leave application + all related rows.
 * Cascade tables:
 *   - leave_applications (main)
 *   - leave_data_for_approval (signatory chain)
 *   - leave_application_segments
 *   - leave_segment_history
 *   - office_notice_record
 *
 * Access: super admin only (user_group_id = 1).
 */
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Re-query full user info (sidebar overwrites $getUserInfoQRW with 3 cols)
$_uStmt = mysqli_prepare($con,
    "SELECT user_id, full_name, isCenterAdmin, user_group_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($_uStmt, 's', $_SESSION['username']);
mysqli_stmt_execute($_uStmt);
$_userFull = mysqli_fetch_assoc(mysqli_stmt_get_result($_uStmt));
mysqli_stmt_close($_uStmt);

$isSuperAdmin = ((int)($_userFull['user_group_id'] ?? 0) === 1);
if (!$isSuperAdmin) {
    echo '<div class="alert alert-danger mt-4"><i class="ti tabler-shield-x me-1"></i>এই পেজ শুধুমাত্র সুপার অ্যাডমিনের জন্য।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// ── Resolve application ID from input (accepts dataID or BITAC/year/N format) ──
$mode    = $_POST['mode']   ?? ($_GET['mode'] ?? '');
$rawInput = trim($_POST['leaveInput'] ?? ($_GET['leaveInput'] ?? ''));
$resolvedID = 0;
$appData = null;
$relatedCounts = [];
$resultMessage = '';
$resultClass = '';

if ($rawInput !== '') {
    // Try to extract integer dataID — either bare number or last segment of "BITAC/Y/N"
    if (preg_match('/(\d+)\s*$/', $rawInput, $m)) {
        $resolvedID = (int)$m[1];
    }
}

// Fetch application + related counts
if ($resolvedID > 0) {
    // Check if application_no column exists (migration may not have run yet)
    $_hasAppNo = false;
    $_colCheck = mysqli_query($con, "SHOW COLUMNS FROM leave_applications LIKE 'application_no'");
    if ($_colCheck && mysqli_num_rows($_colCheck) > 0) $_hasAppNo = true;

    $appNoSelect = $_hasAppNo ? "la.application_no," : "'' AS application_no,";
    $aStmt = mysqli_prepare($con,
        "SELECT la.dataID, $appNoSelect la.subject, la.dateFrom, la.dateTo,
                la.submitDate, la.status, la.attachment,
                el.employee_name, el.employee_id, o.organization_name
         FROM leave_applications la
         LEFT JOIN employee_list el  ON la.applicantID    = el.id
         LEFT JOIN organization  o   ON la.organization_id= o.id
         WHERE la.dataID = ? LIMIT 1");
    mysqli_stmt_bind_param($aStmt, 'i', $resolvedID);
    mysqli_stmt_execute($aStmt);
    $appData = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));
    mysqli_stmt_close($aStmt);

    if ($appData) {
        // Count related rows in each table
        $countQ = function($sql, $type, $val) use ($con) {
            $s = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($s, $type, $val);
            mysqli_stmt_execute($s);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
            mysqli_stmt_close($s);
            return (int)($r['c'] ?? 0);
        };
        $relatedCounts = [
            'leave_data_for_approval'    => $countQ("SELECT COUNT(*) c FROM leave_data_for_approval WHERE leaveApplicationID=?", 'i', $resolvedID),
            'leave_application_segments' => $countQ("SELECT COUNT(*) c FROM leave_application_segments WHERE applicationID=?", 'i', $resolvedID),
            'leave_segment_history'      => $countQ("SELECT COUNT(*) c FROM leave_segment_history WHERE applicationID=?", 'i', $resolvedID),
            'office_notice_record'       => $countQ("SELECT COUNT(*) c FROM office_notice_record WHERE leaveApplicationID=?", 'i', $resolvedID),
        ];
    }
}

// ── Handle DELETE confirmation ──
if ($mode === 'delete' && $resolvedID > 0 && $appData && !empty($_POST['confirm']) && $_POST['confirm'] === 'YES_DELETE') {
    mysqli_begin_transaction($con);
    try {
        $stmts = [
            "DELETE FROM leave_segment_history    WHERE applicationID = ?",
            "DELETE FROM leave_application_segments WHERE applicationID = ?",
            "DELETE FROM leave_data_for_approval  WHERE leaveApplicationID = ?",
            "DELETE FROM office_notice_record     WHERE leaveApplicationID = ?",
            "DELETE FROM leave_applications       WHERE dataID = ?",
        ];
        foreach ($stmts as $sql) {
            $s = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($s, 'i', $resolvedID);
            mysqli_stmt_execute($s);
            mysqli_stmt_close($s);
        }
        mysqli_commit($con);

        // Optional: also remove attachment file
        if (!empty($appData['attachment'])) {
            $attachPath = __DIR__ . '/../../uploads/' . $appData['attachment'];
            if (file_exists($attachPath)) @unlink($attachPath);
        }

        $resultMessage = 'আবেদন #' . htmlspecialchars($appData['application_no'] ?: $resolvedID) . ' এবং সংশ্লিষ্ট সব ডেটা সফলভাবে delete হয়েছে।';
        $resultClass = 'alert-success';
        $appData = null;       // clear so form resets
        $relatedCounts = [];
        $resolvedID = 0;
    } catch (Exception $e) {
        mysqli_rollback($con);
        $resultMessage = 'Delete failed: ' . htmlspecialchars($e->getMessage());
        $resultClass = 'alert-danger';
    }
}

$_bnDate = function($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $months = ['', 'জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];
    $day  = (int)date('j', strtotime($d));
    $mon  = (int)date('n', strtotime($d));
    $yr   = (int)date('Y', strtotime($d));
    $bn = function($n) { return function_exists('banglaNumber') ? banglaNumber((string)$n) : (string)$n; };
    return $bn($day) . ' ' . $months[$mon] . ' ' . $bn($yr);
};
?>

<style>
.del-card { background:#fff; border-radius:12px; padding:22px; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:18px; }
.del-card h5 { margin:0 0 14px; font-weight:600; color:#1f2937; display:flex; align-items:center; gap:8px; }
.del-card .danger-pill { background:#fbeded; color:#c97777; padding:3px 10px; border-radius:99px; font-size:0.74rem; font-weight:600; }
.detail-row { display:flex; padding:8px 0; border-bottom:1px solid #f3f4f6; font-size:0.88rem; gap:14px; }
.detail-row:last-child { border-bottom:none; }
.detail-row .dr-label { flex:0 0 180px; color:#6b7280; font-weight:500; }
.detail-row .dr-val { flex:1; color:#1f2937; font-weight:500; }
.cascade-list { background:#fef0f0; border-left:3px solid #c97777; padding:12px 16px; border-radius:6px; margin:14px 0; font-size:0.86rem; color:#7d3a3a; }
.cascade-list .cl-row { display:flex; justify-content:space-between; padding:3px 0; }
.cascade-list .cl-row strong { color:#a06262; }
</style>

<div class="row mb-3">
    <div class="col-12">
        <h4 class="fw-bold"><i class="ti tabler-trash me-1" style="color:#c97777;"></i>ছুটির আবেদন ডিলিট</h4>
        <div class="text-muted small">আবেদন আইডি অথবা BITAC নম্বর দিয়ে আবেদন delete করুন। সংশ্লিষ্ট সব ডেটা একসাথে মুছে যাবে।</div>
    </div>
</div>

<?php if ($resultMessage): ?>
    <div class="alert <?= $resultClass ?>"><?= $resultMessage ?></div>
<?php endif; ?>

<!-- Search/lookup form -->
<div class="del-card">
    <h5><i class="ti tabler-search" style="color:#7d9bc5;"></i>আবেদন খুঁজুন</h5>
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="menuslug" value="<?= htmlspecialchars($_GET['menuslug'] ?? 'delete-leave') ?>">
        <div class="col-md-9">
            <label class="form-label small">আবেদন আইডি বা BITAC নম্বর</label>
            <input type="text" name="leaveInput" class="form-control" placeholder="যেমন: 15  অথবা  BITAC/2026/15" value="<?= htmlspecialchars($rawInput) ?>" required>
            <small class="text-muted">ID অথবা সম্পূর্ণ BITAC/বছর/N format দিতে পারেন</small>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100"><i class="ti tabler-search me-1"></i>খুঁজুন</button>
        </div>
    </form>
</div>

<?php if ($rawInput !== '' && !$appData && !$resultMessage): ?>
    <div class="alert alert-warning">এই আইডি-তে কোনো আবেদন পাওয়া যায়নি।</div>
<?php endif; ?>

<?php if ($appData): ?>
<!-- Application preview -->
<div class="del-card">
    <h5>
        <i class="ti tabler-file-text" style="color:#7d9bc5;"></i>
        আবেদনের বিস্তারিত
        <span class="danger-pill ms-2"><i class="ti tabler-alert-triangle me-1"></i>delete-এর জন্য নির্বাচিত</span>
    </h5>

    <div class="detail-row"><div class="dr-label">আবেদন নং</div><div class="dr-val"><strong><?= htmlspecialchars($appData['application_no'] ?: 'BITAC/' . date('Y', strtotime($appData['submitDate'])) . '/' . $appData['dataID']) ?></strong></div></div>
    <div class="detail-row"><div class="dr-label">dataID (Internal)</div><div class="dr-val"><?= (int)$appData['dataID'] ?></div></div>
    <div class="detail-row"><div class="dr-label">আবেদনকারী</div><div class="dr-val"><?= htmlspecialchars($appData['employee_name'] ?? '—') ?> (<?= htmlspecialchars($appData['employee_id'] ?? '—') ?>)</div></div>
    <div class="detail-row"><div class="dr-label">কেন্দ্র</div><div class="dr-val"><?= htmlspecialchars($appData['organization_name'] ?? '—') ?></div></div>
    <div class="detail-row"><div class="dr-label">বিষয়</div><div class="dr-val"><?= htmlspecialchars($appData['subject'] ?? '—') ?></div></div>
    <div class="detail-row"><div class="dr-label">তারিখ</div><div class="dr-val"><?= $_bnDate($appData['dateFrom']) ?> → <?= $_bnDate($appData['dateTo']) ?></div></div>
    <div class="detail-row"><div class="dr-label">জমা</div><div class="dr-val"><?= $_bnDate($appData['submitDate']) ?></div></div>
    <div class="detail-row"><div class="dr-label">Status</div><div class="dr-val">
        <?php
        $statusMap = [0=>'Pending', 1=>'Approved', 2=>'Forwarded', 3=>'Cancelled', 4=>'Declined'];
        echo $statusMap[(int)$appData['status']] ?? $appData['status'];
        ?>
    </div></div>
    <?php if (!empty($appData['attachment'])): ?>
    <div class="detail-row"><div class="dr-label">সংযুক্তি</div><div class="dr-val"><?= htmlspecialchars($appData['attachment']) ?></div></div>
    <?php endif; ?>

    <div class="cascade-list">
        <div style="font-weight:600;margin-bottom:6px;"><i class="ti tabler-alert-triangle me-1"></i>এই row-গুলোও cascade-এ delete হবে:</div>
        <?php foreach ($relatedCounts as $tbl => $cnt): ?>
            <div class="cl-row"><span><code><?= $tbl ?></code></span><strong><?= function_exists('banglaNumber') ? banglaNumber($cnt) : $cnt ?> টি row</strong></div>
        <?php endforeach; ?>
        <?php if (!empty($appData['attachment'])): ?>
            <div class="cl-row"><span>📎 attachment file</span><strong><?= htmlspecialchars($appData['attachment']) ?></strong></div>
        <?php endif; ?>
    </div>

    <form method="post" onsubmit="return confirmDelete()">
        <input type="hidden" name="mode" value="delete">
        <input type="hidden" name="leaveInput" value="<?= htmlspecialchars($rawInput) ?>">
        <input type="hidden" name="confirm" value="YES_DELETE">
        <div class="d-flex gap-2 justify-content-end">
            <a href="?menuslug=<?= htmlspecialchars($_GET['menuslug'] ?? 'delete-leave') ?>" class="btn btn-label-secondary">বাতিল</a>
            <button type="submit" class="btn" style="background:#c97777;color:#fff;border:none;">
                <i class="ti tabler-trash me-1"></i>স্থায়ীভাবে Delete করুন
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
function confirmDelete() {
    var msg = "এই আবেদন এবং সংশ্লিষ্ট সব ডেটা স্থায়ীভাবে delete হবে।\nএই কাজ undo করা যাবে না।\n\nনিশ্চিত করতে \"DELETE\" লিখুন:";
    var input = prompt(msg);
    return input === 'DELETE';
}
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
