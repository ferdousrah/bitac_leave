<?php
/**
 * The whole case in one table: every action on a leave application, in the
 * order it happened.
 *
 * Two records were kept apart and had to be read side by side to follow a case:
 *   পূর্ববর্তী মন্তব্য   — who said what (leave_data_for_approval notes, admin
 *                          forward notes, ফেরত reasons, resubmissions)
 *   পরিবর্তন ইতিহাস     — what each desk proposed (leave_segment_history)
 *
 * Both are keyed on "a desk acting at a time", so they interleave into one
 * chronological table shaped like the printed সম্পাদনার নোট: role, name, the
 * leave proposed, the remark.
 *
 * Strictly one row per event, sorted by timestamp — a desk that edits the
 * segments and later approves with a note gets two rows, because that is two
 * things it did at two times, and collapsing them hides the sequence.
 *
 * The one exception is the applicant's submission: writing the application and
 * setting its segments is a single act, recorded in two tables within the same
 * minute, so those fold into one row.
 */

require_once(__DIR__ . '/segment-history-timeline.php');

if (!function_exists('render_case_file_table')) {

/**
 * The ভূমিকা column speaks the printed সম্পাদনার নোট's vocabulary, so a desk
 * reads the same whichever record its row came from. Without this the same
 * person shows as "বিভাগীয় প্রধান" on the row carrying their note and
 * "সুপারিশকারীর প্রস্তাব" on the row carrying their segment edit — which is
 * exactly the column the note has and this table appeared to be missing.
 *
 * Returns [label, background, foreground]. Anything unrecognised — a ফেরত, a
 * resubmission — passes through untouched, because those are events the note
 * has no row for and inventing a role for them would mislead.
 */
function cf_note_role($label, $bg, $fg, $key = '', $serial = 0)
{
    $step = $serial > 0 ? ' (ধাপ ' . banglaNumber($serial) . ')' : '';

    $byKey = [
        'applicant'  => ['আবেদনকারী',        '#e8e5ff', '#5648c4'],
        'supervisor' => ['বিভাগীয় প্রধান',   '#d1f4ff', '#0883a3'],
        'admin'      => ['নথি উপস্থাপক',      '#ede5fa', '#5e3eaa'],
        'signatory'  => ['নথি অনুমোদনকারী' . $step, '#d8f5e3', '#1a7e44'],
    ];
    if ($key !== '' && isset($byKey[$key])) return $byKey[$key];

    // Thread events carry no key — they are built inline on the pages — so they
    // are matched on the label they were written with.
    $byLabel = [
        'আবেদনকারী'                  => 'applicant',
        'পুনঃ যাচাইয়ের পর জমা'        => 'applicant',
        'বিভাগীয় প্রধান'             => 'supervisor',
        'বিভাগীয় প্রধান (পূর্ববর্তী)' => 'supervisor',
        'নোট উপস্থাপনকারী'            => 'admin',
        'অনুমোদনকারী'                => 'signatory',
        'অনুমোদনকারী (পূর্ববর্তী)'    => 'signatory',
    ];
    if (isset($byLabel[$label])) return $byKey[$byLabel[$label]];

    return [$label, $bg, $fg];
}

/**
 * @param mysqli $con
 * @param int    $applicationID
 * @param array  $threadEvents  the page's already-built comment thread; each
 *                              entry needs ts/name/title/badge/body and may
 *                              carry `extra`
 * @param array  $leaveTypeMap  leaveID => leaveTitle
 * @return string HTML ('' when there is nothing to show)
 */
function render_case_file_table($con, $applicationID, array $threadEvents, array $leaveTypeMap)
{
    $segData = segment_history_stages($con, (int)$applicationID, $leaveTypeMap);
    $stages  = $segData['stages'];
    $chip    = $segData['chip'];

    $events = [];

    // ── Proposal events, from the segment history ─────────────────────
    foreach ($stages as $s) {
        $ts = !empty($s['when']) ? strtotime($s['when']) : 0;

        $chips = '';
        foreach ($s['snapshot'] as $sg) $chips .= $chip($sg);
        if ($chips === '') $chips = '<span class="text-muted small">কোনো segment নেই</span>';

        $proposal = '<div class="cf-total">মোট ' . banglaNumber((int)$s['total']) . ' দিন</div>'
                  . '<div class="cf-chips">' . $chips . '</div>';

        if (!empty($s['removedChips'])) {
            $rem = '';
            foreach ($s['removedChips'] as $rc) $rem .= $chip($rc, true);
            $proposal .= '<div class="cf-removed"><span class="cf-removed-label">অপসারিত:</span> ' . $rem . '</div>';
        }

        $delta = [];
        if (!empty($s['added']))        $delta[] = banglaNumber($s['added'])   . 'টি যোগ';
        if (!empty($s['editedN']))      $delta[] = banglaNumber($s['editedN']) . 'টি সম্পাদনা';
        if (!empty($s['removedChips'])) $delta[] = banglaNumber(count($s['removedChips'])) . 'টি অপসারণ';
        if ($delta) $proposal .= '<div class="cf-delta">' . htmlspecialchars(implode(' · ', $delta)) . '</div>';

        list($__role, $__rbg, $__rfg) = cf_note_role(
            $s['deskLabel'], $s['deskBg'], $s['deskFg'],
            $s['deskKey'] ?? '', (int)($s['deskSerial'] ?? 0));

        $events[] = [
            'ts'       => $ts,
            'order'    => 0,          // a proposal precedes the note written about it
            'actor'    => trim((string)$s['name']),
            'role'     => $__role,
            'roleBg'   => $__rbg,
            'roleFg'   => $__rfg,
            'icon'     => $s['deskIcon'],
            'name'     => trim((string)$s['name']),
            'title'    => '',
            'proposal' => $proposal,
            'comment'  => '',
            'isSeg'    => true,
        ];
    }

    // ── Comment events, from the page's thread ────────────────────────
    foreach ($threadEvents as $ev) {
        $badge = $ev['badge'] ?? ['', '#eee', '#555'];
        $body  = (string)($ev['body'] ?? '');
        if (!empty($ev['extra'])) {
            $body .= '<div class="cf-extra">' . $ev['extra'] . '</div>';
        }
        list($__role, $__rbg, $__rfg) = cf_note_role(
            $badge[0] ?? '', $badge[1] ?? '#eee', $badge[2] ?? '#555');

        $events[] = [
            'ts'       => (int)($ev['ts'] ?? 0),
            'order'    => 1,
            'actor'    => trim((string)($ev['name'] ?? '')),
            'role'     => $__role,
            'roleBg'   => $__rbg,
            'roleFg'   => $__rfg,
            'icon'     => $ev['icon'] ?? 'tabler-message',
            'name'     => trim((string)($ev['name'] ?? '')),
            'title'    => trim((string)($ev['title'] ?? '')),
            'proposal' => '',
            'comment'  => $body,
            'isSeg'    => false,
        ];
    }

    if (empty($events)) return '';

    // The application's submitDate is a date with no time, so the applicant's
    // comment would sort to 00:00 — hours before their own segments. Pull it
    // onto the segment timestamp so the two halves of one act line up.
    $firstSegTs = null;
    foreach ($events as $e) {
        if ($e['isSeg'] && $e['ts'] > 0) { $firstSegTs = $e['ts']; break; }
    }
    if ($firstSegTs !== null) {
        foreach ($events as &$e) {
            if (!$e['isSeg'] && $e['ts'] > 0 && date('H:i:s', $e['ts']) === '00:00:00'
                && date('Y-m-d', $e['ts']) === date('Y-m-d', $firstSegTs)) {
                $e['ts'] = $firstSegTs;
            }
        }
        unset($e);
    }

    usort($events, function ($a, $b) {
        if ($a['ts'] === $b['ts']) return $a['order'] <=> $b['order'];
        return $a['ts'] <=> $b['ts'];
    });

    // One act recorded in two tables — same person, same minute, one side
    // holding only a proposal and the other only a remark — is one row.
    // Anything else stays a row of its own, which is the whole point.
    $merged = [];
    foreach ($events as $e) {
        $prev = $merged ? $merged[count($merged) - 1] : null;
        $canFold = $prev
            && $prev['actor'] !== '' && $prev['actor'] === $e['actor']
            && $prev['ts'] > 0 && $e['ts'] > 0
            && abs($prev['ts'] - $e['ts']) < 60
            && $prev['proposal'] !== '' && $prev['comment'] === ''
            && $e['proposal'] === ''    && $e['comment'] !== '';
        if ($canFold) {
            $merged[count($merged) - 1]['comment'] = $e['comment'];
            if ($merged[count($merged) - 1]['title'] === '') {
                $merged[count($merged) - 1]['title'] = $e['title'];
            }
            continue;
        }
        $merged[] = $e;
    }

    // ── Render ────────────────────────────────────────────────────────
    $html = '<div class="table-responsive"><table class="table cf-table align-middle mb-0">'
          . '<thead><tr>'
          . '<th style="width:60px;" class="text-center">ক্রম</th>'
          . '<th style="width:120px;">সময়</th>'
          . '<th style="width:150px;">ভূমিকা</th>'
          . '<th style="width:180px;">নাম ও পদবী</th>'
          . '<th>ছুটির প্রস্তাবনা</th>'
          . '<th>মন্তব্য</th>'
          . '</tr></thead><tbody>';

    foreach ($merged as $i => $e) {
        $when = $e['ts'] > 0
            ? '<div class="cf-date">' . banglaNumber(date('d/m/Y', $e['ts'])) . '</div>'
              . '<div class="cf-time">' . banglaNumber(date('H:i', $e['ts'])) . '</div>'
            : '<span class="text-muted small">—</span>';

        $html .= '<tr>'
               . '<td class="text-center cf-sl">' . banglaNumber($i + 1) . '</td>'
               . '<td>' . $when . '</td>'
               . '<td><span class="cf-role" style="background:' . $e['roleBg'] . ';color:' . $e['roleFg'] . ';">'
               . '<i class="ti ' . htmlspecialchars($e['icon']) . ' me-1"></i>' . htmlspecialchars($e['role']) . '</span></td>'
               . '<td><div class="cf-name">' . htmlspecialchars($e['name']) . '</div>'
               . ($e['title'] !== '' ? '<div class="cf-title">' . htmlspecialchars($e['title']) . '</div>' : '')
               . '</td>'
               . '<td>' . ($e['proposal'] !== '' ? $e['proposal'] : '<span class="cf-none">—</span>') . '</td>'
               . '<td class="cf-comment">' . ($e['comment'] !== '' ? $e['comment'] : '<span class="cf-none">—</span>') . '</td>'
               . '</tr>';
    }

    $html .= '</tbody></table></div>';

    $html .= '<style>
        .cf-table { font-size: 0.84rem; }
        .cf-table > thead th {
            background: #f7f7fb; border-bottom: 1px solid #e6e6ef;
            font-size: 0.76rem; font-weight: 600; color: #6b7280; white-space: nowrap;
        }
        .cf-table > tbody > tr > td { vertical-align: top; padding: 10px 12px; }
        .cf-table > tbody > tr + tr > td { border-top: 1px solid #f0f0f6; }
        .cf-sl { color: #9aa0b5; font-weight: 600; }
        .cf-date { font-weight: 600; color: #2c2e3a; white-space: nowrap; }
        .cf-time { font-size: 0.74rem; color: #9aa0b5; }
        .cf-role {
            display: inline-block; font-size: 0.71rem; font-weight: 600;
            padding: 3px 9px; border-radius: 999px; white-space: nowrap;
        }
        .cf-name { font-weight: 600; color: #2c2e3a; }
        .cf-title { font-size: 0.74rem; color: #8a90a6; }
        .cf-total {
            display: inline-block; background: #eef0ff; color: #5648c4;
            font-size: 0.72rem; font-weight: 600; padding: 2px 8px;
            border-radius: 4px; margin-bottom: 4px;
        }
        .cf-chips { line-height: 1.9; }
        .cf-removed { margin-top: 5px; }
        .cf-removed-label { font-size: 0.72rem; color: #a52a2a; font-weight: 600; }
        .cf-delta { margin-top: 5px; font-size: 0.72rem; color: #9aa0b5; }
        .cf-extra { margin-top: 5px; font-size: 0.74rem; color: #6b7280; }
        .cf-comment { color: #3c4257; line-height: 1.7; }
        .cf-none { color: #c8ccd8; }
    </style>';

    return $html;
}

}
