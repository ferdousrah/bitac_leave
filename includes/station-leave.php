<?php
/**
 * Station leave (স্টেশন লিভ): the applicant's declaration that they will travel
 * beyond 25 km of the workplace during the leave, with where they will stay.
 *
 * Only the regular leave application (applicationType = 1) carries it, and only
 * the applicant sets it — it is their declaration, so no desk edits it. It shows
 * on the application letter and nowhere else.
 *
 * Everything that reads or writes it goes through here, so the new-application
 * and resubmission endpoints cannot drift apart on the rules.
 */

if (!function_exists('station_leave_ready')) {

/** True once migrations/add_station_leave.php has run. */
function station_leave_ready($con)
{
    static $ready = null;
    if ($ready !== null) return $ready;
    $a = @mysqli_query($con, "SHOW TABLES LIKE 'leave_station_addresses'");
    $b = @mysqli_query($con, "SHOW COLUMNS FROM leave_applications LIKE 'stationLeave'");
    $ready = ($a && mysqli_num_rows($a) > 0) && ($b && mysqli_num_rows($b) > 0);
    return $ready;
}

/**
 * The whole picker tree in one compact structure — 8 + 64 + 594 rows is small
 * enough to ship with the form, so choosing a district never waits on a request.
 */
function station_geo_tree($con)
{
    $tree = ['divisions' => [], 'districts' => [], 'thanas' => []];
    if (!station_leave_ready($con)) return $tree;

    $q = mysqli_query($con, "SELECT id, name_bn FROM geo_divisions ORDER BY name_bn");
    while ($r = mysqli_fetch_assoc($q)) $tree['divisions'][] = [(int)$r['id'], $r['name_bn']];

    $q = mysqli_query($con, "SELECT id, division_id, name_bn FROM geo_districts ORDER BY name_bn");
    while ($r = mysqli_fetch_assoc($q)) $tree['districts'][] = [(int)$r['id'], (int)$r['division_id'], $r['name_bn']];

    $q = mysqli_query($con, "SELECT id, district_id, name_bn FROM geo_thanas ORDER BY name_bn");
    while ($r = mysqli_fetch_assoc($q)) $tree['thanas'][] = [(int)$r['id'], (int)$r['district_id'], $r['name_bn']];

    return $tree;
}

/**
 * Validate and persist the declaration for one application, replacing whatever
 * was saved before. Called inside the caller's flow after the application row
 * exists.
 *
 * @return array [bool ok, string message]
 */
function station_leave_save($con, $applicationID, $applicationType, array $post)
{
    $applicationID = (int)$applicationID;
    if ($applicationID <= 0 || !station_leave_ready($con)) return [true, ''];

    $wants = ((int)($post['stationLeave'] ?? 0) === 1) && ((int)$applicationType === 1);

    $divs  = (array)($post['stationDivision'] ?? []);
    $dists = (array)($post['stationDistrict'] ?? []);
    $thns  = (array)($post['stationThana']    ?? []);
    $dets  = (array)($post['stationDetail']   ?? []);

    $rows = [];
    if ($wants) {
        // The picker cascades in the browser, but a hand-built POST can pair any
        // thana with any district — so the chain is re-checked against the tables
        // and the names are taken from them, never from the request.
        $chk = mysqli_prepare($con,
            "SELECT t.name_bn AS thana_bn, d.name_bn AS district_bn, v.name_bn AS division_bn
             FROM geo_thanas t
             JOIN geo_districts d ON d.id = t.district_id
             JOIN geo_divisions v ON v.id = d.division_id
             WHERE t.id = ? AND d.id = ? AND v.id = ? LIMIT 1");

        foreach ($thns as $i => $tid) {
            $tid = (int)$tid;
            $did = (int)($dists[$i] ?? 0);
            $vid = (int)($divs[$i]  ?? 0);
            $det = trim((string)($dets[$i] ?? ''));

            // A row left completely blank is just an unused row, not an error.
            if ($tid === 0 && $did === 0 && $vid === 0 && $det === '') continue;

            if ($vid === 0 || $did === 0 || $tid === 0) {
                mysqli_stmt_close($chk);
                return [false, 'ছুটিকালীন অবস্থানের ঠিকানায় বিভাগ, জেলা ও থানা — তিনটিই নির্বাচন করুন'];
            }
            if (mb_strlen($det) > 255) {
                mysqli_stmt_close($chk);
                return [false, 'বাড়ি/গ্রামের বিবরণ ২৫৫ অক্ষরের বেশি হতে পারবে না'];
            }

            mysqli_stmt_bind_param($chk, 'iii', $tid, $did, $vid);
            mysqli_stmt_execute($chk);
            $names = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
            if (!$names) {
                mysqli_stmt_close($chk);
                return [false, 'নির্বাচিত থানা, জেলা ও বিভাগ একে অপরের সঙ্গে মেলে না'];
            }
            $rows[] = [$vid, $did, $tid, $names['division_bn'], $names['district_bn'], $names['thana_bn'], $det];
        }
        mysqli_stmt_close($chk);

        if (!$rows) {
            return [false, 'কর্মস্থলের ২৫ কিলোমিটারের বাইরে গেলে অন্তত একটি ছুটিকালীন অবস্থানের ঠিকানা দিন'];
        }
    }

    $flag = $rows ? 1 : 0;
    $u = mysqli_prepare($con, "UPDATE leave_applications SET stationLeave = ? WHERE dataID = ?");
    mysqli_stmt_bind_param($u, 'ii', $flag, $applicationID);
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);

    // Replace, so a resubmission that removes an address really removes it.
    $d = mysqli_prepare($con, "DELETE FROM leave_station_addresses WHERE applicationID = ?");
    mysqli_stmt_bind_param($d, 'i', $applicationID);
    mysqli_stmt_execute($d);
    mysqli_stmt_close($d);

    if ($rows) {
        $ins = mysqli_prepare($con,
            "INSERT INTO leave_station_addresses
             (applicationID, serial, division_id, district_id, thana_id, division_bn, district_bn, thana_bn, detail)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $n => $r) {
            $serial = $n + 1;
            $detail = $r[6] !== '' ? $r[6] : null;
            mysqli_stmt_bind_param($ins, 'iiiiissss',
                $applicationID, $serial, $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $detail);
            mysqli_stmt_execute($ins);
        }
        mysqli_stmt_close($ins);
    }

    return [true, ''];
}

/** Saved addresses for one application, in order. */
function station_leave_addresses($con, $applicationID)
{
    $out = [];
    if (!station_leave_ready($con)) return $out;
    $s = mysqli_prepare($con,
        "SELECT * FROM leave_station_addresses WHERE applicationID = ? ORDER BY serial ASC, dataID ASC");
    $id = (int)$applicationID;
    mysqli_stmt_bind_param($s, 'i', $id);
    mysqli_stmt_execute($s);
    $res = mysqli_stmt_get_result($s);
    while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
    mysqli_stmt_close($s);
    return $out;
}

/** "বাড়ি/গ্রাম- …, থানা- …, জেলা- …, বিভাগ- …।" — the line the letter prints. */
function station_leave_address_line(array $a)
{
    $parts = [];
    $detail = trim((string)($a['detail'] ?? ''));
    if ($detail !== '') $parts[] = 'বাড়ি/গ্রাম- ' . $detail;
    $parts[] = 'থানা- '  . $a['thana_bn'];
    $parts[] = 'জেলা- '  . $a['district_bn'];
    $parts[] = 'বিভাগ- ' . $a['division_bn'];
    return implode(', ', $parts) . '।';
}

}
