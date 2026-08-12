<?php
/**
 * Read-only view of an approval chain — who has acted, whose desk it is on now,
 * and who it still has to reach.
 *
 * The two forward pages needed this and had nothing: joining-update.php showed
 * no chain at all, and forward-to-approval.php listed only signatories who had
 * already approved, so the admin forwarded without seeing where it was going.
 *
 * Both leave and joining chains share a column shape, so one renderer covers
 * `leave_data_for_approval` and `leave_joining_data_for_approval` alike.
 */

if (!function_exists('approval_chain_rows')) {

/**
 * @param string $table leave_data_for_approval | leave_joining_data_for_approval
 * @return array rows in serial order, with employee_name and job_title_name
 */
function approval_chain_rows($con, $table, $leaveApplicationID)
{
    $allowed = ['leave_data_for_approval', 'leave_joining_data_for_approval'];
    if (!in_array($table, $allowed, true)) return [];

    $id = (int)$leaveApplicationID;
    $rows = [];
    $q = mysqli_query($con,
        "SELECT c.dataID, c.signatory, c.serial, c.isSupervisor, c.isSentbyAdmin,
                c.isApproved, c.approvedDate, c.note,
                el.employee_name, jt.job_title_name
         FROM `$table` c
         LEFT JOIN employee_list el ON c.signatory   = el.id
         LEFT JOIN job_title     jt ON el.designation = jt.id
         WHERE c.leaveApplicationID = $id
         ORDER BY c.serial ASC, c.dataID ASC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
    return $rows;
}

/** Emits the chain-line styles once per page. */
function approval_chain_styles()
{
    static $done = false;
    if ($done) return;
    $done = true;
    ?>
<style>
.chain-line {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; margin-bottom: 6px;
    background: #fff; border: 1px solid #eef0f5;
    border-radius: 0.45rem; font-size: 0.86rem;
}
.chain-line.is-done      { background: #f0fdf4; border-color: #bbf7d0; }
.chain-line.is-current   { background: #f0edff; border-color: #ddd5f6; border-left: 3px solid #6c5ce7; }
.chain-line.is-rejected  { background: #fff1f0; border-color: #f5c6c6; border-left: 3px solid #dc3545; }
.chain-line.is-blocked   { opacity: 0.72; }
.chain-line .chain-serial { background:#6c5ce7; color:#fff; min-width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:0.78rem; }
.chain-line.is-done .chain-serial     { background:#1a7e44; }
.chain-line.is-rejected .chain-serial { background:#dc3545; }
.chain-line.is-blocked .chain-serial  { background:#a9adc4; }
.chain-line .chain-name { font-weight: 600; color: #2c2e3a; }
.chain-line .chain-sub  { font-size: 0.74rem; color: #8a90a6; }
.chain-line .chain-status { margin-left: auto; font-size: 0.74rem; font-weight: 600; text-align: right; }
.chain-line.is-done .chain-status     { color: #1a7e44; }
.chain-line.is-current .chain-status  { color: #6c5ce7; }
.chain-line.is-rejected .chain-status { color: #dc3545; }
.chain-note {
    margin: -2px 0 6px 38px; padding: 6px 10px;
    background: #f9f9fb; border-left: 2px solid #ddd5f6;
    font-size: 0.78rem; color: #5d6580; border-radius: 0 0.4rem 0.4rem 0;
}
</style>
    <?php
}

/**
 * @param array $opts  'pendingForward' => true renders the not-yet-forwarded
 *                     steps as blocked, which is the state on a forward page:
 *                     the admin is looking at where it will go once they send it.
 */
function render_approval_chain($con, $table, $leaveApplicationID, array $opts = [])
{
    $rows = approval_chain_rows($con, $table, $leaveApplicationID);
    if (empty($rows)) {
        echo '<div class="alert alert-warning mb-0"><i class="ti tabler-alert-triangle me-2"></i>এই আবেদনের কোনো অনুমোদন চেইন পাওয়া যায়নি।</div>';
        return;
    }
    approval_chain_styles();

    $pendingForward = !empty($opts['pendingForward']);

    // The desk it sits on now: first unapproved row. On a forward page the chain
    // rows are still isSentbyAdmin = 0, so nothing is actionable yet — say so
    // rather than pointing at a signatory who cannot act.
    $currentID = 0;
    foreach ($rows as $r) {
        if ((int)$r['isApproved'] === 0) { $currentID = (int)$r['dataID']; break; }
    }

    foreach ($rows as $r) {
        $approved = (int)$r['isApproved'];
        $isSup    = ((int)$r['isSupervisor'] === 1);
        $blocked  = $pendingForward && !$isSup && (int)$r['isSentbyAdmin'] === 0 && $approved === 0;

        $cls = '';
        if     ($approved === 1) $cls = 'is-done';
        elseif ($approved === 2) $cls = 'is-rejected';
        elseif ($blocked)        $cls = 'is-blocked';
        elseif ((int)$r['dataID'] === $currentID) $cls = 'is-current';

        if ($approved === 1) {
            $status = '✓ অনুমোদিত' . (!empty($r['approvedDate'])
                ? ' — ' . banglaNumber(date('d/m/Y', strtotime($r['approvedDate'])))
                : '');
        } elseif ($approved === 2) {
            $status = '✗ প্রত্যাখ্যাত';
        } elseif ($blocked) {
            $status = 'প্রেরণের পর';
        } elseif ((int)$r['dataID'] === $currentID) {
            $status = 'অপেক্ষমান';
        } else {
            $status = 'পরবর্তী';
        }

        $role = $isSup ? ' <small class="text-muted">(সুপারিশ)</small>' : '';
        ?>
        <div class="chain-line <?= $cls ?>">
            <span class="chain-serial"><?= banglaNumber((int)$r['serial']) ?></span>
            <div>
                <div class="chain-name"><?= htmlspecialchars($r['employee_name'] ?? '—') ?><?= $role ?></div>
                <div class="chain-sub"><?= htmlspecialchars($r['job_title_name'] ?? '') ?></div>
            </div>
            <span class="chain-status"><?= $status ?></span>
        </div>
        <?php if (!empty($r['note'])): ?>
            <div class="chain-note"><i class="ti tabler-message me-1"></i><?= nl2br(htmlspecialchars($r['note'])) ?></div>
        <?php endif;
    }
}

}
