<?php
/**
 * Migration: let a certificate's অনুলিপি list hold fixed-text recipients and an
 * explicit order, the way the leave office order's list already does.
 *
 *  - leaveSummary_copy.label  — a recipient with no employee behind it, e.g.
 *    "প্রশাসন বিভাগ, বিটাক, ঢাকা". Until now the table could only hold an
 *    employee id, so the defaults from কনফিগারেশন → ডিফল্ট অনুলিপি had to be
 *    bolted on at print time and could not be reordered or removed.
 *  - leaveSummary_copy.serial — the order the recipients print in. The form has
 *    always posted a serial, and it was thrown away.
 *
 * Existing rows are numbered in their current insertion order, so certificates
 * issued before this print exactly as they did.
 *
 * Safe to run multiple times.
 * Usage: open http://localhost/bitac_leave/migrations/add_certificate_copy_labels.php once.
 */
require_once(__DIR__ . '/../config/connection.php');
$log = [];

$cols = [
    'label'  => "ADD COLUMN label VARCHAR(255) NULL AFTER copyTo",
    'serial' => "ADD COLUMN serial INT NOT NULL DEFAULT 0",
];
$added = [];
foreach ($cols as $col => $clause) {
    $res = mysqli_query($con, "SHOW COLUMNS FROM leaveSummary_copy LIKE '$col'");
    if ($res && mysqli_num_rows($res) > 0) { $log[] = "SKIP: $col already exists"; continue; }
    if (mysqli_query($con, "ALTER TABLE leaveSummary_copy $clause")) {
        $log[]   = "ADDED column: leaveSummary_copy.$col";
        $added[] = $col;
    } else {
        $log[] = "ERROR adding $col: " . mysqli_error($con);
    }
}

// Number what is already there, once, on the run that created the column.
if (in_array('serial', $added, true)) {
    $fixed = 0;
    $q = mysqli_query($con, "SELECT DISTINCT leaveSummaryID FROM leaveSummary_copy");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $sid = (int)$r['leaveSummaryID'];
            $rows = mysqli_query($con,
                "SELECT dataID FROM leaveSummary_copy WHERE leaveSummaryID = $sid ORDER BY dataID ASC");
            $n = 0;
            while ($row = mysqli_fetch_assoc($rows)) {
                $n++;
                mysqli_query($con, "UPDATE leaveSummary_copy SET serial = $n WHERE dataID = " . (int)$row['dataID']);
                $fixed++;
            }
        }
    }
    $log[] = "Numbered $fixed existing copy row(s) in insertion order";
}

header('Content-Type: text/plain; charset=utf-8');
echo "=================================\n";
echo "CERTIFICATE COPY LABELS MIGRATION\n";
echo "=================================\n\n";
foreach ($log as $line) echo "  " . $line . "\n";
echo "\nDone.\n";
