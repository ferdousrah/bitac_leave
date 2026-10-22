<?php
/**
 * অ্যাডমিন প্যানেল → পুরনো সিস্টেম সিঙ্ক
 *
 * Leave approved or adjusted in the old BITAC software after cutover reaches
 * this system only when a Super Admin presses the button here
 * (api/legacy/sync.php). The page shows what the current copy holds and when
 * it was taken.
 */
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Re-query: the sidebar overwrites $getUserInfoQRW with three columns
$_uStmt = mysqli_prepare($con, "SELECT user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_uStmt, 's', $_un);
mysqli_stmt_execute($_uStmt);
$_u = mysqli_fetch_assoc(mysqli_stmt_get_result($_uStmt)) ?: [];
mysqli_stmt_close($_uStmt);

if ((int)($_u['user_group_id'] ?? 0) !== 1) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-shield-x me-1"></i>এই পেজ শুধুমাত্র সুপার অ্যাডমিনের জন্য।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$bn = fn($n) => strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);

$_tbl = mysqli_query($con, "SHOW TABLES LIKE 'legacy_leave_totals'");
$migrated = $_tbl && mysqli_num_rows($_tbl) > 0;

$apiUrl    = trim((string)getenv('LEGACY_API_URL'));
$apiHost   = $apiUrl !== '' ? (parse_url($apiUrl, PHP_URL_HOST) ?: $apiUrl) : '';
$hasToken  = (string)getenv('LEGACY_API_TOKEN') !== '';
$configured = $apiUrl !== '' && $hasToken;

$lastOk = null; $history = []; $bucketTotals = []; $empCount = 0;
if ($migrated) {
    $lastOk = mysqli_fetch_assoc(mysqli_query($con,
        "SELECT l.*, COALESCE(e.employee_name, u.full_name, u.user_id) AS who
         FROM legacy_leave_sync_log l
         LEFT JOIN user_list u ON u.dataID = l.synced_by
         LEFT JOIN employee_list e ON e.id = u.employee_id
         WHERE l.ok = 1 ORDER BY l.synced_at DESC, l.dataID DESC LIMIT 1"));
    $history = mysqli_fetch_all(mysqli_query($con,
        "SELECT l.*, COALESCE(e.employee_name, u.full_name, u.user_id) AS who
         FROM legacy_leave_sync_log l
         LEFT JOIN user_list u ON u.dataID = l.synced_by
         LEFT JOIN employee_list e ON e.id = u.employee_id
         ORDER BY l.synced_at DESC, l.dataID DESC LIMIT 10"), MYSQLI_ASSOC);
    $empCount = (int)mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(DISTINCT employee_id) FROM legacy_leave_totals"))[0];
    $res = mysqli_query($con, "SELECT bucket, source, SUM(days) d FROM legacy_leave_totals GROUP BY bucket, source");
    while ($r = mysqli_fetch_assoc($res)) $bucketTotals[(int)$r['bucket']][$r['source']] = (int)$r['d'];
}

$BUCKET_NAMES = [
    1 => 'পূর্ণ গড় বেতনে', 2 => 'অর্ধ-গড় বেতনে', 3 => 'নৈমিত্তিক (সব বছর)', 4 => 'বিনা বেতনে',
    5 => 'ঐচ্ছিক (সব বছর)', 6 => 'অ-কর্তনযোগ্য', 10 => 'অসাধারণ',
];
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-8">
        <h4 class="fw-bold mb-0"><i class="ti tabler-refresh me-2 text-primary"></i>পুরনো সিস্টেম সিঙ্ক</h4>
        <div class="text-muted small mt-1">পুরনো সফটওয়্যারে অনুমোদিত বা সংশোধিত ছুটি এখানকার ব্যালেন্সে আনতে সিঙ্ক করুন</div>
    </div>
    <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
        <button type="button" id="btnLegacySync" class="btn btn-primary"
            <?= ($migrated && $configured) ? '' : 'disabled' ?>>
            <i class="ti tabler-refresh me-1"></i>এখন সিঙ্ক করুন
        </button>
    </div>
</div>

<?php if (!$migrated): ?>
    <div class="alert alert-warning">
        <i class="ti tabler-alert-triangle me-1"></i>
        <code>migrations/add_legacy_leave_totals.php</code> এখনো চালানো হয়নি।
    </div>
<?php elseif (!$configured): ?>
    <div class="alert alert-warning">
        <i class="ti tabler-plug-x me-1"></i>
        পুরনো সিস্টেমের সংযোগ কনফিগার করা হয়নি — সার্ভারে
        <code>LEGACY_API_URL</code><?= $apiUrl === '' ? '' : ' (আছে)' ?> ও
        <code>LEGACY_API_TOKEN</code><?= $hasToken ? ' (আছে)' : '' ?> সেট করুন।
        সিঙ্ক না হওয়া পর্যন্ত ব্যালেন্সে পুরনো সিস্টেমের কোনো হিসাব যোগ হয় না।
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small mb-1">সর্বশেষ সফল সিঙ্ক</div>
            <?php if ($lastOk): ?>
                <div class="fs-5 fw-semibold"><?= htmlspecialchars($bn(date('d/m/Y h:i A', strtotime($lastOk['synced_at'])))) ?></div>
                <div class="small text-muted mt-1"><?= htmlspecialchars($lastOk['who'] ?? '') ?></div>
            <?php else: ?>
                <div class="fs-5 fw-semibold text-muted">এখনো হয়নি</div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small mb-1">পুরনো হিসাব আছে</div>
            <div class="fs-5 fw-semibold"><?= $bn($empCount) ?> জন কর্মচারী</div>
            <?php if ($lastOk && $lastOk['latest_change']): ?>
                <div class="small text-muted mt-1">পুরনো সিস্টেমের সর্বশেষ পরিবর্তন: <?= htmlspecialchars($bn($lastOk['latest_change'])) ?></div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small mb-1">সংযোগ</div>
            <div class="fs-5 fw-semibold"><?= $configured ? htmlspecialchars($apiHost) : '<span class="text-muted">কনফিগার হয়নি</span>' ?></div>
        </div></div>
    </div>
</div>

<?php if ($bucketTotals): ?>
<div class="card mb-4">
    <h5 class="card-header">বর্তমান কপিতে পুরনো সিস্টেমের ভোগকৃত ছুটি (দিন)</h5>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>ছুটির ধরন</th><th class="text-end">পূর্বের (সফটওয়্যার-পূর্ব)</th><th class="text-end">আবেদন</th><th class="text-end">কর্তন</th></tr></thead>
            <tbody>
            <?php foreach ($BUCKET_NAMES as $b => $name): if (empty($bucketTotals[$b])) continue; ?>
                <tr>
                    <td><?= $name ?></td>
                    <td class="text-end"><?= $bn($bucketTotals[$b]['prev'] ?? 0) ?></td>
                    <td class="text-end"><?= $bn($bucketTotals[$b]['application'] ?? 0) ?></td>
                    <td class="text-end"><?= $bn($bucketTotals[$b]['deduction'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($history): ?>
<div class="card">
    <h5 class="card-header">সিঙ্কের ইতিহাস</h5>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>সময়</th><th>যিনি করেছেন</th><th>ফলাফল</th><th>বিবরণ</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td class="text-nowrap"><?= htmlspecialchars($bn(date('d/m/Y h:i A', strtotime($h['synced_at'])))) ?></td>
                    <td><?= htmlspecialchars($h['who'] ?? '') ?></td>
                    <td><?= $h['ok'] ? '<span class="badge bg-label-success">সফল</span>' : '<span class="badge bg-label-danger">ব্যর্থ</span>' ?></td>
                    <td class="small"><?= htmlspecialchars($h['message'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script type="text/javascript">
(function bootLegacySync() {
    if (typeof jQuery === 'undefined' || typeof Swal === 'undefined') {
        return setTimeout(bootLegacySync, 20);
    }
    var $ = jQuery;
    var $btn = $('#btnLegacySync');
    if (!$btn.length || $btn.data('bound')) return;
    $btn.data('bound', true);

    $btn.on('click', function () {
        Swal.fire({
            title: 'পুরনো সিস্টেম থেকে সিঙ্ক করবেন?',
            text: 'সব কর্মচারীর পুরনো ছুটির হিসাব নতুন করে আনা হবে। ব্যালেন্স সঙ্গে সঙ্গে বদলাবে।',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'হ্যাঁ, সিঙ্ক করুন',
            cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>সিঙ্ক হচ্ছে...');
            $.ajax({
                type: 'POST',
                url: '../../api/legacy/sync.php',
                dataType: 'json',
                success: function (resp) {
                    var ok = resp && resp.status === 1;
                    Swal.fire({
                        title: ok ? 'সফল' : 'ত্রুটি',
                        text: (resp && resp.message) || 'সিঙ্ক ব্যর্থ',
                        icon: ok ? 'success' : 'error',
                        customClass: { confirmButton: ok ? 'btn btn-primary' : 'btn btn-danger' },
                        buttonsStyling: false
                    }).then(function () { window.location.reload(); });
                },
                error: function () {
                    Swal.fire({
                        title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ', icon: 'error',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                    $btn.prop('disabled', false).html('<i class="ti tabler-refresh me-1"></i>এখন সিঙ্ক করুন');
                }
            });
        });
    });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
