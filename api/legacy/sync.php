<?php
/**
 * "পুরনো সিস্টেম সিঙ্ক" — pull the old BITAC software's used-leave totals.
 *
 * Calls legacy-api/leave-totals.php on the old system (LEGACY_API_URL, with
 * LEGACY_API_TOKEN) and replaces legacy_leave_totals with what comes back, in
 * one transaction: a failed or malformed pull leaves the previous copy intact.
 * Every attempt is written to legacy_leave_sync_log.
 *
 * Rows for employees this system doesn't know are dropped and counted — the
 * old system still holds people who left before the import.
 *
 * Super Admin only. POST.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

ob_start();
require_once(__DIR__ . '/../../config/connection.php');
ob_end_clean();
mysqli_set_charset($con, 'utf8mb4');

$reply = function ($status, $message, $extra = []) {
    echo json_encode(['status' => $status, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};

if (!isset($_SESSION['userID'])) $reply(0, 'আপনি লগইন করেননি!');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $reply(0, 'অবৈধ অনুরোধ');

$actorID = (int)$_SESSION['userID'];
$grp = mysqli_fetch_row(mysqli_query($con, "SELECT user_group_id FROM user_list WHERE dataID = $actorID LIMIT 1"));
if ((int)($grp[0] ?? 0) !== 1) $reply(0, 'শুধুমাত্র সুপার অ্যাডমিন সিঙ্ক করতে পারেন।');

$t = mysqli_query($con, "SHOW TABLES LIKE 'legacy_leave_totals'");
if (!$t || mysqli_num_rows($t) === 0) $reply(0, 'migrations/add_legacy_leave_totals.php এখনো চালানো হয়নি।');

$log = function ($ok, $message, $rows = 0, $employees = 0, $unknown = 0, $latest = null) use ($con, $actorID) {
    $stmt = mysqli_prepare($con,
        "INSERT INTO legacy_leave_sync_log (synced_at, synced_by, ok, row_count, employees, unknown_employees, latest_change, message)
         VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)");
    $okInt = $ok ? 1 : 0;
    mysqli_stmt_bind_param($stmt, 'iiiiiss', $actorID, $okInt, $rows, $employees, $unknown, $latest, $message);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
};
$fail = function ($message) use ($log, $reply) {
    $log(false, $message);
    $reply(0, $message);
};

// ── Fetch ─────────────────────────────────────────────────────────────
$url   = trim((string)getenv('LEGACY_API_URL'));
$token = (string)getenv('LEGACY_API_TOKEN');
if ($url === '' || $token === '') {
    $fail('পুরনো সিস্টেমের API কনফিগার করা হয়নি (LEGACY_API_URL / LEGACY_API_TOKEN)।');
}

if (!function_exists('curl_init')) {
    $fail('সার্ভারে PHP curl extension নেই।');
}
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Api-Token: ' . $token, 'Accept: application/json'],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 60,
]);
$body     = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$netError = curl_error($ch);

if ($body === false || $body === '') {
    $fail('পুরনো সিস্টেমে সংযোগ করা যায়নি' . ($netError !== '' ? " ($netError)" : '') . '।');
}
$data = json_decode($body, true);
if ($httpCode !== 200 || !is_array($data) || empty($data['ok']) || !isset($data['rows']) || !is_array($data['rows'])) {
    $why = is_array($data) && isset($data['error']) ? (string)$data['error'] : "HTTP $httpCode";
    $fail("পুরনো সিস্টেম থেকে সঠিক উত্তর আসেনি ($why)।");
}
if (!empty($data['employee_id'])) {
    // A filtered answer would wipe everyone else's figures.
    $fail('পুরনো সিস্টেম একজন কর্মচারীর তথ্য পাঠিয়েছে, সবার নয় — সিঙ্ক বাতিল।');
}

// ── Validate ──────────────────────────────────────────────────────────
$known = [];
$res = mysqli_query($con, "SELECT id FROM employee_list");
while ($r = mysqli_fetch_row($res)) $known[(int)$r[0]] = true;

$BUCKETS = [1, 2, 3, 4, 5, 6, 10];
$SOURCES = ['prev', 'application', 'deduction'];
$rows = []; $unknown = []; $employees = [];
foreach ($data['rows'] as $i => $r) {
    $emp = (int)($r['employee_id'] ?? 0);
    $bucket = (int)($r['bucket'] ?? 0);
    $year = (int)($r['year'] ?? -1);
    $source = (string)($r['source'] ?? '');
    $days = $r['days'] ?? null;
    $yearOk = in_array($bucket, [3, 5], true) ? ($year >= 1900 && $year <= 2200) : $year === 0;
    if ($emp <= 0 || !in_array($bucket, $BUCKETS, true) || !in_array($source, $SOURCES, true) || !$yearOk || !is_numeric($days)) {
        $fail("পুরনো সিস্টেমের তথ্যে অবৈধ সারি (#$i) — সিঙ্ক বাতিল।");
    }
    if (!isset($known[$emp])) { $unknown[$emp] = true; continue; }
    $key = "$emp:$bucket:$year:$source";
    if (isset($rows[$key])) $fail("পুরনো সিস্টেমের তথ্যে একই সারি দুবার (#$i) — সিঙ্ক বাতিল।");
    $rows[$key] = [$emp, $bucket, $year, $source, (int)$days];
    $employees[$emp] = true;
}

// ── Replace ───────────────────────────────────────────────────────────
$latest = isset($data['latest_change']) ? substr((string)$data['latest_change'], 0, 40) : null;
mysqli_begin_transaction($con);
try {
    mysqli_query($con, 'DELETE FROM legacy_leave_totals');
    $stmt = mysqli_prepare($con, "INSERT INTO legacy_leave_totals (employee_id, bucket, year, source, days) VALUES (?, ?, ?, ?, ?)");
    foreach ($rows as [$emp, $bucket, $year, $source, $days]) {
        mysqli_stmt_bind_param($stmt, 'iiisi', $emp, $bucket, $year, $source, $days);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($con);
} catch (Throwable $e) {
    mysqli_rollback($con);
    $fail('সংরক্ষণ ব্যর্থ, আগের তথ্য অপরিবর্তিত: ' . $e->getMessage());
}

$bn  = fn($n) => strtr((string)$n, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
$msg = $bn(count($employees)) . ' জন কর্মচারীর পুরনো ছুটির হিসাব হালনাগাদ হয়েছে।'
     . ($unknown ? ' ' . $bn(count($unknown)) . ' জন এই সিস্টেমে নেই, বাদ গেছে (আইডি: ' . implode(', ', array_keys($unknown)) . ')।' : '');
$log(true, $msg, count($rows), count($employees), count($unknown), $latest);

if (function_exists('audit_log')) {
    audit_log('legacy_leave_sync', [
        'target_type' => 'legacy_leave_totals',
        'note'        => "rows=" . count($rows) . "; employees=" . count($employees) . "; unknown=" . count($unknown),
    ]);
}

$reply(1, $msg, [
    'rows'          => count($rows),
    'employees'     => count($employees),
    'unknown'       => count($unknown),
    'latest_change' => $latest,
]);
