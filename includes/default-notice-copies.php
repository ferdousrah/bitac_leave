<?php
/**
 * Fixed-text অনুলিপি recipients configured per document type in
 * কনফিগারেশন → ডিফল্ট অনুলিপি.
 *
 * The leave office order already prepends these; the salary increment notice and
 * the leave certificate list only employees, so they read the labels through
 * here and print them ahead of the per-employee entries.
 */

if (!function_exists('default_notice_labels')) {

/**
 * @param string $context  leave | increment | certificate
 * @param string $center   organization name substituted into {center}
 * @return string[] labels in configured order; empty when none/unavailable
 */
function default_notice_labels($con, $context, $center = '')
{
    if (!in_array($context, ['leave', 'increment', 'certificate'], true)) return [];

    // The table and its context column are created lazily by the config page.
    $chk = mysqli_query($con, "SHOW TABLES LIKE 'default_notice_copies'");
    if (!$chk || mysqli_num_rows($chk) === 0) return [];
    $colChk = mysqli_query($con, "SHOW COLUMNS FROM default_notice_copies LIKE 'context'");
    $hasContext = ($colChk && mysqli_num_rows($colChk) > 0);

    $sql = "SELECT label FROM default_notice_copies WHERE isActive = 1";
    if ($hasContext) {
        // Rows predating the column belong to the leave list.
        $sql .= ($context === 'leave')
            ? " AND (context = 'leave' OR context IS NULL OR context = '')"
            : " AND context = '" . mysqli_real_escape_string($con, $context) . "'";
    } elseif ($context !== 'leave') {
        return [];
    }
    $sql .= " ORDER BY serial ASC, dataID ASC";

    $out = [];
    $q = mysqli_query($con, $sql);
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $out[] = str_replace('{center}', $center, $r['label']);
        }
    }
    return $out;
}

}
