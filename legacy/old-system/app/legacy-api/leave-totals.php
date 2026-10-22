<?php
/**
 * Read-only API for the OLD BITAC software — used-leave totals per employee.
 *
 * Deployed INTO the old system (bitac.technocratsbd.com/legacy-api/), not the
 * new one. The new system's "পুরনো সিস্টেম থেকে সিঙ্ক" button pulls this and
 * adds the figures to its own, so leave approved or adjusted in the old system
 * keeps counting after cutover.
 *
 * It counts exactly what the old dashboard counts, split by where the days
 * came from, so the new system can feed each part into the matching term of
 * its own formula:
 *   prev        previous_leave_deduction (approved)  — pre-software history
 *   application leave_applications, status=1, approvedDays by leaveTypeInTwo
 *   deduction   leave_deduction_history (approved), leaveDeduction by leaveID
 *
 * Buckets (old leaveTypeInTwo = deduction leaveID = new system's codes):
 *   1 পূর্ণ গড়  2 অর্ধ-গড় (actual days, not doubled)  3 নৈমিত্তিক  4 বিনা বেতনে
 *   5 ঐচ্ছিক  6 অ-কর্তনযোগ্য  10 অসাধারণ
 * নৈমিত্তিক and ঐচ্ছিক are yearly allowances, so they come per year; the rest
 * carry year 0. The old dashboard ignores the casual/optional columns of
 * previous_leave_deduction, so they are left out here too.
 *
 *   GET leave-totals.php                  all employees
 *   GET leave-totals.php?employee_id=872  one employee
 *   Header: X-Api-Token: <LEGACY_API_TOKEN>
 *
 * Config (env of the old container): LEGACY_API_TOKEN — required; the API
 * refuses every call until it is set. DB: the old system's own connection.php,
 * or LEGACY_API_DB_HOST/_USER/_PASSWORD/_NAME when set (local testing).
 *
 * Written for PHP 7.0+ — the old server's PHP version.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Dhaka');

function lt_fail($code, $message) {
    http_response_code($code);
    echo json_encode(array('ok' => false, 'error' => $message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    lt_fail(405, 'GET only');
}

$expected = (string)getenv('LEGACY_API_TOKEN');
if ($expected === '') {
    lt_fail(503, 'LEGACY_API_TOKEN is not configured on the old system');
}
$given = isset($_SERVER['HTTP_X_API_TOKEN']) ? (string)$_SERVER['HTTP_X_API_TOKEN'] : '';
if (!hash_equals($expected, $given)) {
    lt_fail(401, 'bad token');
}

if (getenv('LEGACY_API_DB_NAME')) {
    $con = mysqli_connect(
        getenv('LEGACY_API_DB_HOST') ?: 'localhost',
        getenv('LEGACY_API_DB_USER') ?: 'root',
        (string)getenv('LEGACY_API_DB_PASSWORD'),
        getenv('LEGACY_API_DB_NAME')
    );
} else {
    // connection.php prints on failure and loads unrelated settings; keep its
    // output out of the JSON.
    ob_start();
    require __DIR__ . '/../connection.php';
    ob_end_clean();
}
if (!$con || mysqli_connect_errno()) {
    lt_fail(500, 'database connection failed');
}
mysqli_set_charset($con, 'utf8mb4');

$employeeFilter = '';
if (isset($_GET['employee_id']) && $_GET['employee_id'] !== '') {
    $employeeFilter = (int)$_GET['employee_id'];
}
$where = function ($col) use ($employeeFilter) {
    return $employeeFilter === '' ? '' : " AND $col = " . (int)$employeeFilter;
};

// Year of an approved leave. A handful of old rows carry '2023-9-24', '23-10-31'
// or '--'; fall back to the requested start date for those.
$appYear = "CASE
    WHEN approvedDateFrom REGEXP '^[0-9]{4}-' THEN CAST(LEFT(approvedDateFrom, 4) AS UNSIGNED)
    WHEN approvedDateFrom REGEXP '^[0-9]{2}-[0-9]' THEN 2000 + CAST(LEFT(approvedDateFrom, 2) AS UNSIGNED)
    WHEN dateFrom REGEXP '^[0-9]{4}-' THEN CAST(LEFT(dateFrom, 4) AS UNSIGNED)
    ELSE 0 END";

$queries = array(
    'prev' => "
        SELECT employeeID AS employee_id, 1 AS bucket, 0 AS year, SUM(avgSalary) AS days
          FROM previous_leave_deduction WHERE isApproved = 1" . $where('employeeID') . " GROUP BY employeeID
        UNION ALL
        SELECT employeeID, 2, 0, SUM(halfAvgSalary)
          FROM previous_leave_deduction WHERE isApproved = 1" . $where('employeeID') . " GROUP BY employeeID
        UNION ALL
        SELECT employeeID, 4, 0, SUM(leaveWithoutPay)
          FROM previous_leave_deduction WHERE isApproved = 1" . $where('employeeID') . " GROUP BY employeeID
        UNION ALL
        SELECT employeeID, 6, 0, SUM(undeductibleLeave)
          FROM previous_leave_deduction WHERE isApproved = 1" . $where('employeeID') . " GROUP BY employeeID
        UNION ALL
        SELECT employeeID, 10, 0, SUM(extraOrdinaryLeave)
          FROM previous_leave_deduction WHERE isApproved = 1" . $where('employeeID') . " GROUP BY employeeID",

    'application' => "
        SELECT applicantID AS employee_id, leaveTypeInTwo AS bucket,
               IF(leaveTypeInTwo IN (3, 5), $appYear, 0) AS year, SUM(approvedDays) AS days
          FROM leave_applications
         WHERE status = 1 AND leaveTypeInTwo IN (1, 2, 3, 4, 5, 6, 10)" . $where('applicantID') . "
         GROUP BY 1, 2, 3",

    'deduction' => "
        SELECT employeeID AS employee_id, leaveID AS bucket,
               IF(leaveID IN (3, 5), YEAR(createDate), 0) AS year, SUM(leaveDeduction) AS days
          FROM leave_deduction_history
         WHERE isApproved = 1 AND leaveID IN (1, 2, 3, 4, 5, 6, 10)" . $where('employeeID') . "
         GROUP BY 1, 2, 3",
);

$rows = array();
foreach ($queries as $source => $sql) {
    $res = mysqli_query($con, $sql);
    if (!$res) {
        lt_fail(500, "query failed ($source)");
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $days = (int)$r['days'];
        if ($days === 0) {
            continue;
        }
        $rows[] = array(
            'employee_id' => (int)$r['employee_id'],
            'bucket'      => (int)$r['bucket'],
            'year'        => (int)$r['year'],
            'source'      => $source,
            'days'        => $days,
        );
    }
}

// The newest approval or deduction the totals include, so the new system can
// show what its copy reflects.
$latest = mysqli_fetch_row(mysqli_query($con,
    "SELECT GREATEST(
        COALESCE((SELECT MAX(lastUpdate) FROM leave_applications), ''),
        COALESCE((SELECT MAX(createDate) FROM leave_deduction_history), ''),
        COALESCE((SELECT MAX(lastUpdate) FROM previous_leave_deduction), ''))"));

echo json_encode(array(
    'ok'            => true,
    'generated_at'  => date('c'),
    'latest_change' => $latest ? $latest[0] : null,
    'employee_id'   => $employeeFilter === '' ? null : $employeeFilter,
    'rows'          => $rows,
), JSON_UNESCAPED_UNICODE);
