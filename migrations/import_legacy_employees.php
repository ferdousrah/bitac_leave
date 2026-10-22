<?php
/**
 * Cutover import: employees and user accounts from the old BITAC software.
 *
 * The old system stays live for pre-cutover leave; only people come across.
 * Ids are kept as they are — the new system's reference tables (designation,
 * section, organization, grade, salary group) share the old ids, and keeping
 * employee ids lets the old system's leave totals be matched without a map.
 *
 *  - employee_list: every old column, the old values winning. Columns the new
 *    schema lacks are added first. New-only columns are set on insert only.
 *    A value one of the table's foreign keys would reject (a designation,
 *    section, grade … the new system doesn't have) becomes NULL — never a
 *    stand-in that names a real post.
 *  - Reference rows missing here (designations ১২৮/১২৯ …) are added; existing
 *    rows are left alone.
 *  - user_list: every old account with its old id and password hash, so people
 *    keep logging in with what they have. Accounts that exist only here (the
 *    test accounts) are removed.
 *  - Roles start over: everyone is General User, the old administrator is
 *    Super Admin. Super Admin assigns the rest from the new system.
 *  - Transfer history is rebuilt as one initial posting per employee.
 *
 * Employees are never deleted: leave tables hold FKs to them.
 *
 * CLI only — it replaces data. A dry run (the default) changes nothing and
 * prints what --apply would do.
 *
 *   php migrations/import_legacy_employees.php            # dry run
 *   php migrations/import_legacy_employees.php --apply
 *
 * Target DB: the app's own env (DB_HOST, DB_NAME, …).
 * Source DB: LEGACY_DB_HOST, LEGACY_DB_PORT, LEGACY_DB_USER,
 *            LEGACY_DB_PASSWORD, LEGACY_DB_NAME (default: local `bitac_old`).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

require_once(__DIR__ . '/../config/connection.php');
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_set_charset($con, 'utf8mb4');

$apply = in_array('--apply', $argv, true);

// Old ids that must not come across. 1489 is the old "প্রশাসন বিভাগের কপি"
// notice-copy placeholder; the new system keeps its own (1403).
const SKIP_LEGACY_EMPLOYEE_IDS = [1489];

const GROUP_SUPER_ADMIN  = 1;
const GROUP_GENERAL_USER = 6;

// Reference tables copied by id when a row is missing here.
const REFERENCE_TABLES = ['organization', 'departments', 'grade', 'salary_group', 'job_title', 'sections'];

$src = mysqli_connect(
    getenv('LEGACY_DB_HOST') ?: 'localhost',
    getenv('LEGACY_DB_USER') ?: 'root',
    getenv('LEGACY_DB_PASSWORD') ?: '',
    getenv('LEGACY_DB_NAME') ?: 'bitac_old',
    (int)(getenv('LEGACY_DB_PORT') ?: 3306)
);
mysqli_set_charset($src, 'utf8mb4');

$out = function ($line = '') { echo $line, "\n"; };
$one = fn($db, $sql) => mysqli_fetch_row(mysqli_query($db, $sql))[0] ?? null;
$all = fn($db, $sql) => mysqli_fetch_all(mysqli_query($db, $sql), MYSQLI_ASSOC);
$q   = fn($db, $name) => '`' . str_replace('`', '``', $name) . '`';

$srcName = $one($src, 'SELECT DATABASE()');
$dstName = $one($con, 'SELECT DATABASE()');
$out(($apply ? 'APPLY' : 'DRY RUN') . ": $srcName (old) → $dstName (new)");
$out(str_repeat('─', 70));

// Signature blobs make single rows large; the target must accept the biggest.
$packet  = (int)$one($con, 'SELECT @@max_allowed_packet');
$biggest = (int)$one($src, "SELECT MAX(COALESCE(LENGTH(signature),0) + COALESCE(LENGTH(photo),0)) FROM employee_list");
$biggest = max($biggest, (int)$one($src, "SELECT MAX(COALESCE(LENGTH(signature),0) + COALESCE(LENGTH(photo),0)) FROM user_list"));
if ($biggest * 2 + 65536 > $packet) {
    exit("STOP: target max_allowed_packet is $packet bytes; a row is ~$biggest bytes. Raise it (SET GLOBAL max_allowed_packet=67108864) and rerun.\n");
}

$columnsOf = function ($db, $schema, $table) use ($all) {
    $rows = $all($db, "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                       FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = '" . mysqli_real_escape_string($db, $schema) . "'
                         AND TABLE_NAME = '" . mysqli_real_escape_string($db, $table) . "'
                       ORDER BY ORDINAL_POSITION");
    $cols = [];
    foreach ($rows as $r) { $cols[$r['COLUMN_NAME']] = $r; }
    return $cols;
};

// ── 1. Schema: old employee columns the new table lacks ───────────────
$srcEmpCols = $columnsOf($src, $srcName, 'employee_list');
$dstEmpCols = $columnsOf($con, $dstName, 'employee_list');
$addCols = array_diff_key($srcEmpCols, $dstEmpCols);

$out('1. employee_list columns to add: ' . ($addCols ? implode(', ', array_keys($addCols)) : 'none'));
if ($apply) {
    foreach ($addCols as $name => $c) {
        $def = $c['COLUMN_TYPE'] . ($c['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : ' NULL');
        if ($c['COLUMN_DEFAULT'] !== null) {
            // information_schema already quotes string defaults on MariaDB; MySQL 8 does not
            $d = $c['COLUMN_DEFAULT'];
            $def .= ' DEFAULT ' . (preg_match("/^'.*'$|^-?[0-9.]+$|^NULL$/i", $d) ? $d : "'" . mysqli_real_escape_string($con, $d) . "'");
        }
        mysqli_query($con, "ALTER TABLE employee_list ADD COLUMN {$q($con, $name)} $def");
    }
}

// ── 2. Reference rows missing in the new system ───────────────────────
$out('2. Reference rows to add (existing rows untouched):');
$refInserts = [];
foreach (REFERENCE_TABLES as $t) {
    $have = array_flip(array_column($all($con, "SELECT id FROM $t"), 'id'));
    $srcCols = $columnsOf($src, $srcName, $t);
    $cols = array_values(array_intersect(array_keys($srcCols), array_keys($columnsOf($con, $dstName, $t))));
    $missing = array_filter($all($src, "SELECT * FROM $t"), fn($r) => !isset($have[$r['id']]));
    $refInserts[$t] = [$cols, $missing];
    $label = $t === 'job_title' ? 'job_title_name' : ($t === 'sections' ? 'section_name' : null);
    $out(sprintf('   %-13s %d%s', $t, count($missing),
        $missing && $label ? '  (' . implode(', ', array_map(fn($r) => $r['id'] . ' ' . $r[$label], $missing)) . ')' : ''));
}

// Every FK on employee_list, and the ids each will accept once step 2 has run
$validIds = [];
foreach ($all($con, "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                     FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_list'
                       AND REFERENCED_TABLE_NAME IS NOT NULL") as $fk) {
    $t   = $fk['REFERENCED_TABLE_NAME'];
    $ids = array_column($all($con, "SELECT {$q($con, $fk['REFERENCED_COLUMN_NAME'])} AS id FROM {$q($con, $t)}"), 'id');
    foreach ($refInserts[$t][1] ?? [] as $r) { $ids[] = $r[$fk['REFERENCED_COLUMN_NAME']]; }
    $validIds[$fk['COLUMN_NAME']] = array_flip($ids);
}

// ── 3. Employees ──────────────────────────────────────────────────────
$srcEmps   = $all($src, 'SELECT * FROM employee_list ORDER BY id');
$dstEmps   = [];
foreach ($all($con, 'SELECT id, employee_name FROM employee_list') as $r) { $dstEmps[$r['id']] = $r['employee_name']; }

$skip = array_flip(SKIP_LEGACY_EMPLOYEE_IDS);
$empInsert = $empUpdate = $renamed = 0;
$nulled = array_fill_keys(array_keys($validIds), 0);
foreach ($srcEmps as $i => $e) {
    if (isset($skip[$e['id']])) { unset($srcEmps[$i]); continue; }
    foreach ($nulled as $col => $_) {
        $v = $e[$col];
        if ($v === null || $v === '' || !isset($validIds[$col][(int)$v])) {
            if ($v !== null) { $nulled[$col]++; }
            $srcEmps[$i][$col] = null;
        }
    }
    if (isset($dstEmps[$e['id']])) {
        $empUpdate++;
        if (trim($dstEmps[$e['id']]) !== trim($e['employee_name'])) { $renamed++; }
    } else {
        $empInsert++;
    }
}
$srcEmpIds = array_flip(array_column($all($src, 'SELECT id FROM employee_list'), 'id'));
$keptNewOnly = array_diff_key($dstEmps, $srcEmpIds);

$out('3. Employees:');
$out("   from old: " . count($srcEmps) . "  → insert $empInsert, update $empUpdate (name changes: $renamed)");
$out('   skipped old ids: ' . implode(', ', SKIP_LEGACY_EMPLOYEE_IDS));
$out('   new-only, kept as is: ' . ($keptNewOnly ? implode(', ', array_map(fn($id, $n) => "$id $n", array_keys($keptNewOnly), $keptNewOnly)) : 'none'));
$out('   unknown reference → NULL: ' . implode(', ', array_map(fn($c, $n) => "$c $n", array_keys($nulled), $nulled)));

// ── 4. Users ──────────────────────────────────────────────────────────
$srcUsers = $all($src, 'SELECT * FROM user_list ORDER BY dataID');
$dstUsers = [];
foreach ($all($con, 'SELECT dataID, user_id FROM user_list') as $r) { $dstUsers[$r['dataID']] = $r['user_id']; }
$srcUserIds = array_flip(array_column($srcUsers, 'dataID'));
$userDelete = array_diff_key($dstUsers, $srcUserIds);
$userUpdate = count(array_intersect_key($srcUserIds, $dstUsers));
$admins = array_values(array_filter($srcUsers, fn($u) => (int)$u['user_type'] === 1));

$out('4. Users:');
$out('   from old: ' . count($srcUsers) . ' → insert ' . (count($srcUsers) - $userUpdate) . ", update $userUpdate");
$out('   new-only accounts removed: ' . count($userDelete) . ($userDelete ? ' (' . implode(', ', $userDelete) . ')' : ''));
$out('   roles reset: ' . (count($srcUsers) - count($admins)) . ' General User, ' . count($admins) . ' Super Admin ('
    . implode(', ', array_column($admins, 'user_id')) . ')');

// ── 5. Things to reconfigure afterwards (pointed at test people) ──────
$out('5. Configuration to check after import:');
foreach ([
    'leave_approval_signatory'      => 'employeeID',
    'leave_edit_approval_signatory' => 'employeeID',
] as $t => $col) {
    $rows = $all($con, "SELECT $col AS ref FROM $t WHERE $col IS NOT NULL AND $col <> 0");
    $out(sprintf('   %-30s %d row(s) name an employee — confirm each still names the right person', $t, count($rows)));
}
$rac = (int)$one($con, 'SELECT COUNT(*) FROM role_approver_config');
$out("   role_approver_config           $rac row(s) name a user account — reassign after import");
$testApps = (int)$one($con, 'SELECT COUNT(*) FROM leave_applications');
$out("   leave_applications             $testApps existing row(s) — test data, clear before go-live");

if (!$apply) {
    $out(str_repeat('─', 70));
    $out('Dry run: nothing changed. Rerun with --apply.');
    exit(0);
}

// ── Apply ─────────────────────────────────────────────────────────────
// Bind every value as a string: mysqli sends NULL as NULL and lets the server
// convert, which also carries the longblob signatures intact.
$upsert = function ($table, $cols, $rows, $pk) use ($con, $q) {
    if (!$rows) return;
    $colSql = implode(', ', array_map(fn($c) => $q($con, $c), $cols));
    $ph     = implode(', ', array_fill(0, count($cols), '?'));
    $upd    = implode(', ', array_map(fn($c) => $q($con, $c) . ' = VALUES(' . $q($con, $c) . ')',
                array_filter($cols, fn($c) => $c !== $pk)));
    $stmt = mysqli_prepare($con, "INSERT INTO $table ($colSql) VALUES ($ph)"
        . ($upd !== '' ? " ON DUPLICATE KEY UPDATE $upd" : ''));
    foreach ($rows as $r) {
        $vals = array_map(fn($c) => $r[$c], $cols);
        mysqli_stmt_bind_param($stmt, str_repeat('s', count($cols)), ...$vals);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
};

mysqli_begin_transaction($con);
try {
    // Parents before children: sections reference organization.
    foreach (REFERENCE_TABLES as $t) {
        [$cols, $missing] = $refInserts[$t];
        $upsert($t, $cols, $missing, 'id');
    }

    $upsert('employee_list', array_keys($srcEmpCols), $srcEmps, 'id');

    // New-only columns for people arriving now; existing rows keep theirs.
    $newIds = array_diff(array_column($srcEmps, 'id'), array_keys($dstEmps));
    if ($newIds) {
        mysqli_query($con, "UPDATE employee_list
            SET employment_type = 'permanent',
                permanent_from_date = IF(joining_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$', joining_date, NULL),
                pending_section_assignment = 0
            WHERE id IN (" . implode(',', array_map('intval', $newIds)) . ")");
    }

    // Transfer history: one initial posting each, as the lifecycle migration seeds it.
    mysqli_query($con, 'DELETE FROM employee_transfer_history');
    mysqli_query($con, "INSERT INTO employee_transfer_history
            (employee_ref_id, from_organization_id, to_organization_id, transfer_date, reason, createdAt)
        SELECT id, NULL, organization_id,
               IF(joining_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$', joining_date, CURDATE()),
               'Initial posting (legacy import)', NOW()
        FROM employee_list WHERE organization_id > 0");

    // Users: drop the accounts the old system doesn't know, then upsert.
    if ($userDelete) {
        $ids = implode(',', array_map('intval', array_keys($userDelete)));
        mysqli_query($con, "DELETE FROM user_remember_tokens WHERE userID IN ($ids)");
        mysqli_query($con, "DELETE FROM user_list WHERE dataID IN ($ids)");
    }
    $upsert('user_list', array_keys($columnsOf($src, $srcName, 'user_list')), $srcUsers, 'dataID');

    mysqli_query($con, "UPDATE user_list u LEFT JOIN employee_list e ON e.id = u.employee_id
        SET u.organization_id = COALESCE(e.organization_id, 0), u.isCenterAdmin = 0,
            u.user_group_id = IF(u.user_type = 1, " . GROUP_SUPER_ADMIN . ", " . GROUP_GENERAL_USER . ")");

    mysqli_query($con, 'DELETE FROM user_group_assignment');
    mysqli_query($con, "INSERT INTO user_group_assignment (user_id, group_id, is_default, assigned_date)
        SELECT dataID, user_group_id, 1, NOW() FROM user_list");

    mysqli_commit($con);
} catch (Throwable $e) {
    mysqli_rollback($con);
    exit("FAILED, rolled back (schema changes in step 1 stay): " . $e->getMessage() . "\n");
}

$out(str_repeat('─', 70));
$out('Done. employees: ' . $one($con, 'SELECT COUNT(*) FROM employee_list')
    . ', users: ' . $one($con, 'SELECT COUNT(*) FROM user_list')
    . ', can log in: ' . $one($con, 'SELECT COUNT(*) FROM user_list u JOIN employee_list e ON e.id = u.employee_id WHERE e.employment_status = 1'));
