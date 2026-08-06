<?php
/**
 * Segment history rendered as a per-desk proposal timeline.
 *
 * leave_segment_history stores one row per individual segment operation
 * (created / edited / removed). Listing those raw rows tells you almost
 * nothing useful — what reviewers actually need is "what did each desk
 * propose?". So we replay the operations in order and snapshot the full
 * segment set after each desk's batch of changes.
 *
 * A "desk" is identified by (changedBy, signatoryLevel, changedAt) — a
 * single save writes all its rows with the same timestamp.
 *
 * signatoryLevel semantics (see api/leave/save-segments.php):
 *    0 + note='Applicant submission'  → the applicant's original submission
 *    0 + no note                      → center admin (ছুটি সম্পাদনা desk)
 *   >0                                → that serial in the approval chain;
 *                                       serial with isSupervisor=1 is the
 *                                       supervisor, the rest are signatories.
 */

if (!function_exists('render_segment_history_timeline')) {

/**
 * @param mysqli $con
 * @param int    $applicationID
 * @param array  $leaveTypeMap  leaveID => leaveTitle
 * @return string HTML ('' when there is no history)
 */
function render_segment_history_timeline($con, $applicationID, array $leaveTypeMap) {
    $applicationID = (int)$applicationID;

    // ── History rows, chronological ────────────────────────────────
    // actorEmpID is what identifies the desk — signatoryLevel alone is not
    // reliable (save-segments.php resolves the center-admin branch first, so
    // a supervisor who also carries isCenterAdmin gets logged with level 0).
    $stmt = mysqli_prepare($con,
        "SELECT h.*, el.employee_name, ul.employee_id AS actorEmpID
         FROM leave_segment_history h
         LEFT JOIN user_list     ul ON h.changedBy  = ul.dataID
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         WHERE h.applicationID = ?
         ORDER BY h.changedAt ASC, h.dataID ASC");
    mysqli_stmt_bind_param($stmt, 'i', $applicationID);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    if (empty($rows)) return '';

    // Applicant, so their own edits are never mistaken for a desk action
    $applicantID = 0;
    $aStmt = mysqli_prepare($con, "SELECT applicantID FROM leave_applications WHERE dataID = ? LIMIT 1");
    mysqli_stmt_bind_param($aStmt, 'i', $applicationID);
    mysqli_stmt_execute($aStmt);
    if ($aRow = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt))) {
        $applicantID = (int)$aRow['applicantID'];
    }
    mysqli_stmt_close($aStmt);

    // ── Chain maps: by serial AND by employee id ───────────────────
    $chain = [];      // serial      => meta
    $chainByEmp = []; // employee_id => meta
    $cStmt = mysqli_prepare($con,
        "SELECT ldfa.serial, ldfa.isSupervisor, ldfa.signatory, el.employee_name, jt.job_title_name
         FROM leave_data_for_approval ldfa
         LEFT JOIN employee_list el ON ldfa.signatory   = el.id
         LEFT JOIN job_title     jt ON el.designation   = jt.id
         WHERE ldfa.leaveApplicationID = ?
         ORDER BY ldfa.serial ASC");
    mysqli_stmt_bind_param($cStmt, 'i', $applicationID);
    mysqli_stmt_execute($cStmt);
    $cRes = mysqli_stmt_get_result($cStmt);
    while ($c = mysqli_fetch_assoc($cRes)) {
        $meta = [
            'serial'       => (int)$c['serial'],
            'isSupervisor' => (int)$c['isSupervisor'] === 1,
            'name'         => trim($c['employee_name'] ?? ''),
            'title'        => trim($c['job_title_name'] ?? ''),
        ];
        $chain[(int)$c['serial']] = $meta;
        $emp = (int)$c['signatory'];
        // Supervisor row wins if the same person appears twice in the chain
        if ($emp && (!isset($chainByEmp[$emp]) || $meta['isSupervisor'])) {
            $chainByEmp[$emp] = $meta;
        }
    }
    mysqli_stmt_close($cStmt);

    // Older rows were written with a different JSON key schema
    // ({"type","from","to"}) than the current one
    // ({"leaveType","dateFrom","dateTo"}). Normalise both shapes so legacy
    // history still renders instead of silently dropping to "no segments".
    $decodeSeg = function ($json) {
        if (empty($json)) return null;
        $d = json_decode($json, true);
        if (!is_array($d)) return null;
        $type = $d['leaveType'] ?? $d['type'] ?? null;
        if ($type === null) return null;
        return [
            'leaveType' => (int)$type,
            'dateFrom'  => $d['dateFrom'] ?? $d['from'] ?? '',
            'dateTo'    => $d['dateTo']   ?? $d['to']   ?? '',
            'days'      => (int)($d['days'] ?? 0),
        ];
    };

    // Some legacy rows stored changedByName as the literal string "0".
    $pickName = function ($r) {
        $n = trim((string)($r['changedByName'] ?? ''));
        if ($n !== '' && $n !== '0') return $n;
        $n = trim((string)($r['employee_name'] ?? ''));
        return $n !== '' ? $n : '—';
    };

    // ── Group rows into per-desk batches ───────────────────────────
    $batches = [];
    foreach ($rows as $r) {
        $key = ($r['changedBy'] ?? '0') . '|' . ($r['signatoryLevel'] ?? '0') . '|' . ($r['changedAt'] ?? '');
        if (!isset($batches[$key])) {
            $batches[$key] = [
                'level'   => (int)($r['signatoryLevel'] ?? 0),
                'actor'   => (int)($r['actorEmpID'] ?? 0),
                'name'    => $pickName($r),
                'when'    => $r['changedAt'] ?? '',
                'note'    => $r['note'] ?? '',
                'ops'     => [],
            ];
        }
        if (empty($batches[$key]['note']) && !empty($r['note'])) {
            $batches[$key]['note'] = $r['note'];
        }
        $batches[$key]['ops'][] = $r;
    }

    // ── Replay to snapshot the segment set after each batch ────────
    // The applicant's history rows reference the 'requested' segment ids while
    // every later desk edits the 'proposed' rows — different dataIDs for the
    // same logical segment. Keying purely on segmentID therefore left the
    // applicant's original entry alive forever, so a supervisor who split one
    // 2-day segment into two 1-day ones showed three chips totalling 4 days.
    // When the id is unknown we fall back to matching the operation's oldData
    // against the current state.
    $samePayload = function ($a, $b) {
        return $a && $b
            && (int)$a['leaveType'] === (int)$b['leaveType']
            && $a['dateFrom'] === $b['dateFrom']
            && $a['dateTo']   === $b['dateTo']
            && (int)$a['days'] === (int)$b['days'];
    };
    $findByPayload = function ($state, $needle) use ($samePayload) {
        foreach ($state as $k => $v) {
            if ($samePayload($v, $needle)) return $k;
        }
        return null;
    };

    $state = [];   // segmentID => ['leaveType','dateFrom','dateTo','days']
    $stages = [];
    foreach ($batches as $b) {
        $added = 0; $editedN = 0; $removedChips = [];
        foreach ($b['ops'] as $op) {
            $sid = (int)($op['segmentID'] ?? 0);
            $new = $decodeSeg($op['newData'] ?? null);
            $old = $decodeSeg($op['oldData'] ?? null);
            switch ($op['action']) {
                case 'created':
                    if ($new) { $state[$sid ?: ('n' . count($state))] = $new; $added++; }
                    break;
                case 'edited':
                    if ($new) {
                        if ($sid && isset($state[$sid])) {
                            $state[$sid] = $new;
                        } else {
                            $prev = $findByPayload($state, $old);
                            if ($prev !== null) unset($state[$prev]);
                            $state[$sid ?: ('n' . count($state))] = $new;
                        }
                        $editedN++;
                    }
                    break;
                case 'removed':
                    if ($sid && isset($state[$sid])) {
                        unset($state[$sid]);
                    } else {
                        $prev = $findByPayload($state, $old);
                        if ($prev !== null) unset($state[$prev]);
                    }
                    if ($old) $removedChips[] = $old;
                    break;
            }
        }
        $b['snapshot']     = array_values($state);
        $b['added']        = $added;
        $b['editedN']      = $editedN;
        $b['removedChips'] = $removedChips;
        $stages[] = $b;
    }

    // ── Helpers ────────────────────────────────────────────────────
    $chip = function ($seg, $strike = false) use ($leaveTypeMap) {
        if (!$seg || !isset($seg['leaveType'])) return '';
        $label = $leaveTypeMap[(int)$seg['leaveType']] ?? 'অজানা';
        $from  = !empty($seg['dateFrom']) ? banglaNumber(date('d/m/Y', strtotime($seg['dateFrom']))) : '';
        $to    = !empty($seg['dateTo'])   ? banglaNumber(date('d/m/Y', strtotime($seg['dateTo'])))   : '';
        $days  = banglaNumber((int)($seg['days'] ?? 0));
        $base  = 'display:inline-block;padding:3px 9px;border-radius:4px;font-size:0.76rem;line-height:1.55;margin:2px 3px 2px 0;';
        $style = $strike
            ? $base . 'background:#fdecec;color:#a52a2a;border:1px solid #f5c5c1;text-decoration:line-through;'
            : $base . 'background:#f9f5e8;color:#8a6d1a;border:1px solid #f0e7c8;';
        return '<span style="' . $style . '">' . htmlspecialchars($label)
             . ' · ' . $from . ' → ' . $to . ' (' . $days . ' দিন)</span>';
    };

    // Resolve the desk from WHO acted, falling back to signatoryLevel.
    // signatoryLevel is 0 both for the center admin and for a supervisor who
    // also holds isCenterAdmin (save-segments.php checks that branch first),
    // so trusting it alone mislabels supervisor edits as admin edits.
    $deskMeta = function ($level, $note, $actorEmp) use ($chain, $chainByEmp, $applicantID) {
        if (stripos((string)$note, 'applicant') !== false
            || ($actorEmp && $applicantID && $actorEmp === $applicantID)) {
            return ['আবেদনকারীর জমা', '#e8e5ff', '#5648c4', 'tabler-user'];
        }
        $meta = null;
        if ($actorEmp && isset($chainByEmp[$actorEmp])) {
            $meta = $chainByEmp[$actorEmp];
        } elseif ($level > 0 && isset($chain[$level])) {
            $meta = $chain[$level];
        }
        if ($meta) {
            if (!empty($meta['isSupervisor'])) {
                return ['সুপারিশকারীর প্রস্তাব', '#d1f4ff', '#0883a3', 'tabler-clipboard-check'];
            }
            return ['স্বাক্ষরকারীর প্রস্তাব (ধাপ ' . banglaNumber((int)$meta['serial']) . ')',
                    '#d8f5e3', '#1a7e44', 'tabler-circle-check'];
        }
        return ['প্রশাসনিক ডেস্ক (ছুটি সম্পাদনা)', '#ede5fa', '#5e3eaa', 'tabler-user-edit'];
    };

    // ── Render ─────────────────────────────────────────────────────
    $html = '<div class="seg-timeline">';
    foreach ($stages as $i => $s) {
        list($deskLabel, $bg, $fg, $icon) = $deskMeta($s['level'], $s['note'], (int)($s['actor'] ?? 0));
        $when  = $s['when'] ? banglaNumber(date('d/m/Y H:i', strtotime($s['when']))) : '';
        $total = 0;
        foreach ($s['snapshot'] as $sg) $total += (int)($sg['days'] ?? 0);

        $chips = '';
        foreach ($s['snapshot'] as $sg) $chips .= $chip($sg);
        if ($chips === '') $chips = '<span class="text-muted small">কোনো segment নেই</span>';

        $deltaBits = [];
        if ($s['added'])   $deltaBits[] = banglaNumber($s['added'])   . 'টি যোগ';
        if ($s['editedN']) $deltaBits[] = banglaNumber($s['editedN']) . 'টি সম্পাদনা';
        if ($s['removedChips']) $deltaBits[] = banglaNumber(count($s['removedChips'])) . 'টি অপসারণ';

        $isLast = ($i === count($stages) - 1);

        $html .= '<div class="seg-stage' . ($isLast ? ' is-last' : '') . '">'
               . '<div class="seg-stage-dot" style="background:' . $fg . ';"><i class="ti ' . $icon . '"></i></div>'
               . '<div class="seg-stage-body">'
               . '<div class="seg-stage-head">'
               . '<span class="seg-desk-badge" style="background:' . $bg . ';color:' . $fg . ';">' . htmlspecialchars($deskLabel) . '</span>'
               . '<span class="seg-stage-who">' . htmlspecialchars($s['name']) . '</span>'
               . ($when ? '<span class="seg-stage-when"><i class="ti tabler-clock me-1"></i>' . $when . '</span>' : '')
               . '</div>'
               . '<div class="seg-stage-label">প্রস্তাবিত ছুটি'
               . ($total > 0 ? ' <span class="seg-total-pill">মোট ' . banglaNumber($total) . ' দিন</span>' : '')
               . '</div>'
               . '<div class="seg-stage-chips">' . $chips . '</div>';

        if ($s['removedChips']) {
            $rem = '';
            foreach ($s['removedChips'] as $rc) $rem .= $chip($rc, true);
            $html .= '<div class="seg-stage-removed"><span class="seg-removed-label">অপসারিত:</span> ' . $rem . '</div>';
        }
        if ($deltaBits) {
            $html .= '<div class="seg-stage-delta">' . htmlspecialchars(implode(' · ', $deltaBits)) . '</div>';
        }

        $html .= '</div></div>';
    }
    $html .= '</div>';

    // Scoped styles — emitted once alongside the markup
    $html .= '<style>
        .seg-timeline { position: relative; padding: 4px 0; }
        .seg-stage { position: relative; display: flex; gap: 14px; padding-bottom: 20px; }
        .seg-stage:not(.is-last)::before {
            content: ""; position: absolute; left: 15px; top: 34px; bottom: 0;
            width: 2px; background: #eceef5;
        }
        .seg-stage-dot {
            flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 0.95rem; z-index: 1;
        }
        .seg-stage-body { flex: 1; min-width: 0; padding-top: 2px; }
        .seg-stage-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .seg-desk-badge { font-size: 0.72rem; font-weight: 600; padding: 3px 10px; border-radius: 999px; }
        .seg-stage-who { font-size: 0.86rem; font-weight: 600; color: #2c2e3a; }
        .seg-stage-when { font-size: 0.73rem; color: #9aa0b5; }
        .seg-stage-label { font-size: 0.76rem; color: #6b7280; margin-bottom: 4px; }
        .seg-total-pill {
            display: inline-block; background: #eef0ff; color: #5648c4;
            font-size: 0.72rem; font-weight: 600; padding: 2px 8px;
            border-radius: 4px; margin-left: 4px;
        }
        .seg-stage-chips { line-height: 1.9; }
        .seg-stage-removed { margin-top: 6px; }
        .seg-removed-label { font-size: 0.73rem; color: #a52a2a; font-weight: 600; }
        .seg-stage-delta { margin-top: 6px; font-size: 0.72rem; color: #9aa0b5; }
    </style>';

    return $html;
}

}
