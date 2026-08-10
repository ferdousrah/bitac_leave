<?php
/**
 * Projects what a leave's segments become once a joining letter is finalized.
 *
 * The admin queues have to show ভোগকৃত ছুটি while the joining is still moving
 * through its chain, so they can't just read the stored segments — those still
 * describe the leave as it was approved. Mirroring the rules that
 * api/leave/joining-approval-action.php applies on finalize keeps the queue,
 * the approval preview and the eventual result telling the same story.
 *
 * Spanning approvedDateFrom → requestedJoiningDate instead (the old approach)
 * silently counts the gaps between segments as leave.
 *
 * Convention (locked): joiningDate is the last leave day, inclusive.
 *   Type 1 (সঠিক সময়ে) — unchanged
 *   Type 2 (অগ্রিম)     — truncate at joiningDate; drop segments starting after it
 *   Type 3 (বর্ধিত)     — append the extension segments
 */

if (!function_exists('joining_effective_segments')) {

/**
 * @param array  $segs        Stored 'proposed' segments (dateFrom, dateTo, days, …)
 * @param int    $joiningType 1 | 2 | 3
 * @param string $joinIso     Requested joining date, Y-m-d
 * @param array  $opts        For type 3: extensionSegmentsJson, approvedDateTo, extLeaveType
 * @return array Segments as they would stand after finalize
 */
function joining_effective_segments(array $segs, $joiningType, $joinIso, array $opts = [])
{
    $joiningType = (int)$joiningType;
    $joinIso     = (string)$joinIso;
    if ($joinIso === '') return $segs;

    // Y-m-d strings compare correctly as strings.
    if ($joiningType === 2) {
        $out = [];
        foreach ($segs as $sg) {
            $from = (string)$sg['dateFrom'];
            $to   = (string)$sg['dateTo'];
            if ($from > $joinIso) continue;              // starts after joining — dropped
            if ($to > $joinIso) {                        // straddles joining — truncated
                $sg['dateTo'] = $joinIso;
                $sg['days']   = (int)((strtotime($joinIso) - strtotime($from)) / 86400) + 1;
            }
            $out[] = $sg;
        }
        return $out;
    }

    if ($joiningType !== 3) return $segs;

    $ext = [];
    if (!empty($opts['extensionSegmentsJson'])) {
        $decoded = json_decode($opts['extensionSegmentsJson'], true);
        if (is_array($decoded)) $ext = $decoded;
    }
    if (empty($ext)) {
        // Legacy single-segment extension, same fallback the finalize uses
        $approvedTo = (string)($opts['approvedDateTo'] ?? '');
        $extType    = (int)($opts['extLeaveType'] ?? 0);
        if ($approvedTo !== '' && $extType > 0) {
            $extFrom = date('Y-m-d', strtotime($approvedTo . ' +1 day'));
            $extDays = (int)((strtotime($joinIso) - strtotime($extFrom)) / 86400) + 1;
            if ($extDays > 0) {
                $ext = [[
                    'leaveType' => $extType,
                    'dateFrom'  => $extFrom,
                    'dateTo'    => $joinIso,
                    'days'      => $extDays,
                ]];
            }
        }
    }

    // extensionSegmentsJson stores leaveType ids only, so a caller that wants
    // readable chips has to hand over the id → title map (joining_leave_titles).
    $titles = is_array($opts['leaveTitles'] ?? null) ? $opts['leaveTitles'] : [];

    $out = $segs;
    foreach ($ext as $es) {
        $days = (int)($es['days'] ?? 0);
        if ($days <= 0) continue;
        $lt = (int)($es['leaveType'] ?? 0);
        $out[] = [
            'leaveType'  => $lt,
            'leaveTitle' => $es['leaveTitle'] ?? ($titles[$lt] ?? null),
            'dateFrom'   => (string)($es['dateFrom'] ?? ''),
            'dateTo'     => (string)($es['dateTo'] ?? ''),
            'days'       => $days,
        ];
    }
    return $out;
}

/**
 * leaveID => leaveTitle, read once per request.
 */
function joining_leave_titles($con)
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    $q = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $map[(int)$r['leaveID']] = $r['leaveTitle'];
    return $map;
}

/**
 * True when the segments run back to back, so the first→last date range
 * describes them honestly. Multi-segment leave often has gaps, and a bare
 * range then reads as far more days than the total beside it.
 */
function joining_segments_contiguous(array $segs)
{
    if (count($segs) < 2) return true;
    $sorted = $segs;
    usort($sorted, function ($a, $b) { return strcmp((string)$a['dateFrom'], (string)$b['dateFrom']); });
    for ($i = 1, $n = count($sorted); $i < $n; $i++) {
        $expected = date('Y-m-d', strtotime($sorted[$i - 1]['dateTo'] . ' +1 day'));
        if ((string)$sorted[$i]['dateFrom'] !== $expected) return false;
    }
    return true;
}

/**
 * "০৮/০৮" for a single day, "০৮/০৮–১১/০৮" for a span. Year is dropped — the
 * chip sits directly under a full date range that already carries it.
 */
function joining_segment_dates($sg)
{
    $from = (string)$sg['dateFrom'];
    $to   = (string)$sg['dateTo'];
    if ($from === '') return '';
    $f = banglaNumber(date('d/m', strtotime($from)));
    if ($to === '' || $to === $from) return $f;
    return $f . '–' . banglaNumber(date('d/m', strtotime($to)));
}

/**
 * Total days, first date and last date across a segment list.
 *
 * @return array{days:int, from:?string, to:?string}
 */
function joining_segments_span(array $segs)
{
    $days = 0; $from = null; $to = null;
    foreach ($segs as $sg) {
        $days += (int)$sg['days'];
        $f = (string)$sg['dateFrom'];
        $t = (string)$sg['dateTo'];
        if ($f !== '' && ($from === null || $f < $from)) $from = $f;
        if ($t !== '' && ($to   === null || $t > $to))   $to   = $t;
    }
    return ['days' => $days, 'from' => $from, 'to' => $to];
}

}
