<?php
// ───────────────────────────────────────────────────────────
// Signatory block — pending approvals (top + filter chips + list)
// Included from dashboard.php when $isSignatory && $_sigPendingCount > 0
// ───────────────────────────────────────────────────────────

$_sigEmpId = $getUserInfoQRW['employee_id'] ?? '';
$_bnNumS = function($n) { return function_exists('banglaNumber') ? banglaNumber((string)$n) : (string)$n; };
$_avatarInitS = function($name) {
    $name = trim($name);
    if (!$name) return '?';
    return mb_substr($name, 0, 1, 'UTF-8');
};
$_bnDateS = function($date) {
    if (!$date || $date === '0000-00-00') return '—';
    $months = ['', 'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে'];
    $d = (int)date('j', strtotime($date));
    $m = (int)date('n', strtotime($date));
    return (function_exists('banglaNumber') ? banglaNumber($d) : $d) . ' ' . $months[$m];
};
$_relTimeS = function($date) {
    $diff = floor((time() - strtotime($date)) / 86400);
    if ($diff <= 0) return 'আজ';
    if ($diff === 1.0) return 'কাল';
    return (function_exists('banglaNumber') ? banglaNumber((int)$diff) : (int)$diff) . ' দিন আগে';
};
$_badgeColorS = function($leaveID) {
    $map = [
        1  => ['bg'=>'#e8eef9','color'=>'#5b7396'],
        2  => ['bg'=>'#fbeded','color'=>'#c97777'],
        5  => ['bg'=>'#efeaf5','color'=>'#7c6ba4'],
        6  => ['bg'=>'#fbe7eb','color'=>'#b46578'],
        7  => ['bg'=>'#efeaf5','color'=>'#7c6ba4'],
        8  => ['bg'=>'#e8f5ee','color'=>'#5fa885'],
        9  => ['bg'=>'#e8eef9','color'=>'#5b7396'],
        10 => ['bg'=>'#faf2dc','color'=>'#a47b54'],
        19 => ['bg'=>'#fbeded','color'=>'#c97777'],
    ];
    return $map[(int)$leaveID] ?? ['bg'=>'#f3f4f6','color'=>'#4b5563'];
};

// ── Fetch pending list (current signatory only — previous serials all approved) ──
// "Current signatory" = lda has isApproved=0 AND no row with smaller serial is still unapproved
$_q = mysqli_prepare($con,
    "SELECT la.dataID AS applicationID, la.dateFrom, la.dateTo, la.submitDate, la.approvedDays,
            lda.dataID AS approvalID, lda.isSupervisor,
            el.employee_name, el.id AS emp_id,
            lt.leaveTitle, lt.leaveID,
            org.organization_name, org.id AS org_id
     FROM leave_data_for_approval lda
     INNER JOIN leave_applications la ON la.dataID = lda.leaveApplicationID
     INNER JOIN employee_list el ON el.id = la.applicantID
     LEFT JOIN leave_types lt ON lt.leaveID = la.leaveTypeInTwo
     LEFT JOIN organization org ON org.id = el.organization_id
     WHERE lda.signatory = ? AND lda.isApproved = 0 AND la.status IN (0, 2)
       AND (lda.isSupervisor = 1 OR lda.isSentbyAdmin = 1)
       AND NOT EXISTS (
           SELECT 1 FROM leave_data_for_approval prev
           WHERE prev.leaveApplicationID = lda.leaveApplicationID
             AND prev.serial < lda.serial
             AND prev.isApproved = 0
       )
     ORDER BY la.submitDate ASC
     LIMIT 5");
mysqli_stmt_bind_param($_q, 's', $_sigEmpId);
mysqli_stmt_execute($_q);
$_sigListRes = mysqli_stmt_get_result($_q);
$_sigList = [];
while ($r = mysqli_fetch_assoc($_sigListRes)) $_sigList[] = $r;
mysqli_stmt_close($_q);

// ── Enrich each row with multi-segment info (uses 'requested' kind) ──────
foreach ($_sigList as $idx => $r) {
    $appID = (int)$r['applicationID'];
    $segR = mysqli_query($con, "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
        LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
        WHERE s.applicationID = $appID AND (s.kind = 'requested' OR s.kind IS NULL)
        ORDER BY s.serial ASC, s.dataID ASC");
    $segs = [];
    while ($sr = mysqli_fetch_assoc($segR)) $segs[] = $sr;
    $_sigList[$idx]['segments'] = $segs;
    if (count($segs) > 1) {
        $_sigList[$idx]['totalDays']      = array_sum(array_column($segs, 'days'));
        $_sigList[$idx]['multiTypesText'] = implode(' + ', array_unique(array_column($segs, 'leaveTitle')));
    }
}

// ── Center-wise pending count for filter chips (same current-signatory filter) ──
$_q = mysqli_prepare($con,
    "SELECT org.id, org.organization_name, COUNT(*) cnt
     FROM leave_data_for_approval lda
     INNER JOIN leave_applications la ON la.dataID = lda.leaveApplicationID
     INNER JOIN employee_list el ON el.id = la.applicantID
     INNER JOIN organization org ON org.id = el.organization_id
     WHERE lda.signatory = ? AND lda.isApproved = 0 AND la.status IN (0, 2)
       AND (lda.isSupervisor = 1 OR lda.isSentbyAdmin = 1)
       AND NOT EXISTS (
           SELECT 1 FROM leave_data_for_approval prev
           WHERE prev.leaveApplicationID = lda.leaveApplicationID
             AND prev.serial < lda.serial
             AND prev.isApproved = 0
       )
     GROUP BY org.id
     ORDER BY cnt DESC");
mysqli_stmt_bind_param($_q, 's', $_sigEmpId);
mysqli_stmt_execute($_q);
$_centerCountsRes = mysqli_stmt_get_result($_q);
$_centerCounts = [];
while ($r = mysqli_fetch_assoc($_centerCountsRes)) $_centerCounts[] = $r;
mysqli_stmt_close($_q);

// ── Oldest pending age ───────────────────────────────────────────────
$_oldestDays = 0;
if (!empty($_sigList)) {
    $_oldestDate = $_sigList[0]['submitDate'];
    $_oldestDays = max(0, floor((time() - strtotime($_oldestDate)) / 86400));
}
?>

<style>
/* ── Signatory block (above employee dashboard) ── */
.sig-action-card {
    background: linear-gradient(135deg, #fbf6dd 0%, #faf2dc 100%);
    border: 1px solid #e8d99c;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 18px;
    text-decoration: none;
    transition: all .25s ease;
}
.sig-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(212,160,86,0.15);
    text-decoration: none;
}
.sig-action-card .ac-icon {
    width: 64px; height: 64px;
    background: #fff; border-radius: 16px;
    display:flex; align-items:center; justify-content:center;
    font-size: 2rem; color: #c89060;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(200,144,96,0.15);
}
.sig-action-card .ac-content { flex:1; }
.sig-action-card .ac-title {
    font-size: 1.1rem; font-weight: 700; color: #8b6f47;
    margin-bottom: 4px;
}
.sig-action-card .ac-num {
    font-size: 2.4rem; font-weight: 800; color: #a47b54;
    line-height: 1; display:inline-block;
}
.sig-action-card .ac-meta { font-size: 0.84rem; color: #8b6f47; margin-top:6px; }
.sig-action-card .ac-arrow {
    font-size: 1.5rem; color: #c89060;
    flex-shrink: 0; transition: transform .25s ease;
}
.sig-action-card:hover .ac-arrow { transform: translateX(4px); }

.sig-pending-card {
    background:#fff; border-radius:12px; padding:18px;
    box-shadow:0 2px 8px rgba(0,0,0,0.06);
    margin-bottom:18px;
}
.sig-pending-card .sc-title {
    font-weight:600; color:#1f2937;
    margin-bottom:14px;
    display:flex; align-items:center; justify-content:space-between;
    gap:8px; font-size:0.95rem;
}
.sig-pending-card .sc-title .sc-link {
    font-size:0.78rem; color:#7d9bc5; text-decoration:none; font-weight:500;
}

/* Center filter chips */
.center-chips {
    display: flex; gap: 8px; flex-wrap: wrap;
    margin-bottom: 14px; padding-bottom: 12px;
    border-bottom: 1px dashed #e5e7eb;
}
.center-chip {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    padding: 5px 12px;
    border-radius: 99px;
    font-size: 0.78rem;
    color: #4b5563;
    cursor: pointer;
    transition: all .15s ease;
    user-select: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.center-chip:hover { border-color: #a89cc4; color: #7c6ba4; }
.center-chip.active {
    background: linear-gradient(135deg, #a89cc4 0%, #b9aed1 100%);
    border-color: transparent;
    color: #fff;
}
.center-chip .cc-count {
    background: rgba(0,0,0,0.08);
    padding: 1px 7px;
    border-radius: 99px;
    font-size: 0.7rem;
    font-weight: 600;
}
.center-chip.active .cc-count { background: rgba(255,255,255,0.25); }

.pending-row {
    display:flex; align-items:center; gap:12px;
    padding:12px 8px;
    border:1px solid #e5e7eb; border-radius:10px;
    margin-bottom:8px; background: #fff;
    transition: all .15s ease;
}
.pending-row:last-child { margin-bottom:0; }
.pending-row:hover { border-color:#a89cc4; background:#faf7fc; }
.pending-row .p-avatar {
    width:42px; height:42px; border-radius:50%;
    background: linear-gradient(135deg, #c7d2fe 0%, #a5b4fc 100%);
    color:#3730a3;
    display:flex; align-items:center; justify-content:center;
    font-weight:700; flex-shrink:0;
}
.pending-row .p-info { flex:1; min-width:0; }
.pending-row .p-name { font-weight:600; color:#1f2937; font-size:0.92rem; }
.pending-row .p-detail { font-size:0.78rem; color:#6b7280; margin-top:2px; }
.pending-row .p-center {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 0.72rem; color: #7c6ba4; background: #efeaf5;
    padding: 1px 7px; border-radius: 4px; margin-top: 4px;
    font-weight: 500;
}
.pending-row .p-center i { font-size: 0.8rem; }
.pending-row .p-pill {
    font-size:0.72rem; padding:3px 9px; border-radius:5px;
    font-weight:600; flex-shrink:0;
}
.pending-row .p-actions { display:flex; gap:6px; flex-shrink:0; }
.pending-row .p-btn {
    width:34px; height:34px; border:none;
    border-radius:8px;
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; font-size:1rem;
    transition: all .15s ease;
    background:#e8eef9; color:#6c8cc4;
    text-decoration: none;
}
.pending-row .p-btn:hover { transform:scale(1.08); color:#5b7396; }
</style>

<!-- BIG action card -->
<a href="<?= BASE_URL ?>/views/leave/approval.php?menuslug=leave-approval" class="sig-action-card">
    <span class="ac-icon"><i class="ti tabler-clipboard-list"></i></span>
    <div class="ac-content">
        <div class="ac-title">আপনার অনুমোদনের অপেক্ষায়</div>
        <div><span class="ac-num"><?= $_bnNumS($_sigPendingCount) ?></span> <span style="font-size:0.95rem;color:#92400e;font-weight:500;margin-left:6px;">টি ছুটির আবেদন</span></div>
        <?php if ($_oldestDays > 0): ?>
            <div class="ac-meta"><i class="ti tabler-clock me-1"></i>সবচেয়ে পুরাতনটি <?= $_bnNumS((int)$_oldestDays) ?> দিন আগে জমা হয়েছে</div>
        <?php endif; ?>
    </div>
    <i class="ti tabler-arrow-right ac-arrow"></i>
</a>

<!-- Pending list with center filter chips -->
<div class="sig-pending-card">
    <div class="sc-title">
        <span><i class="ti tabler-clipboard-list me-1" style="color:#c89060;"></i>অনুমোদনের জন্য অপেক্ষমান</span>
        <a href="<?= BASE_URL ?>/views/leave/approval.php?menuslug=leave-approval" class="sc-link">সব <?= $_bnNumS($_sigPendingCount) ?> টি দেখুন →</a>
    </div>

    <?php if (count($_centerCounts) > 1): ?>
    <div class="center-chips">
        <span class="center-chip active" data-org="all"><i class="ti tabler-asterisk"></i> সব <span class="cc-count"><?= $_bnNumS($_sigPendingCount) ?></span></span>
        <?php foreach ($_centerCounts as $cc): ?>
            <span class="center-chip" data-org="<?= (int)$cc['id'] ?>"><i class="ti tabler-building"></i> <?= htmlspecialchars($cc['organization_name']) ?> <span class="cc-count"><?= $_bnNumS($cc['cnt']) ?></span></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($_sigList as $row):
        $segs = $row['segments'] ?? [];
        $isMulti = count($segs) > 1;

        // Days + range derived from segments if multi, else fallback to lda/la fields
        if ($isMulti) {
            $days     = (int)$row['totalDays'];
            $minFrom  = min(array_column($segs, 'dateFrom'));
            $maxTo    = max(array_column($segs, 'dateTo'));
            $rangeStr = $_bnDateS($minFrom) . ' → ' . $_bnDateS($maxTo);
            $typeText = $row['multiTypesText'];
            $color    = ['bg'=>'#efeaf5','color'=>'#7c6ba4']; // multi-segment uses neutral purple
        } else {
            $days     = !empty($row['approvedDays']) ? (int)$row['approvedDays']
                      : (max(1, (int)floor((strtotime($row['dateTo']) - strtotime($row['dateFrom'])) / 86400) + 1));
            $rangeStr = $_bnDateS($row['dateFrom']) . ' → ' . $_bnDateS($row['dateTo']);
            $typeText = $row['leaveTitle'] ?? '';
            $color    = $_badgeColorS($row['leaveID']);
        }

        $isSup = (int)($row['isSupervisor'] ?? 0) === 1;
        $stageLabel = $isSup ? 'সুপারিশ' : 'অনুমোদন';
        $stageColor = $isSup
            ? ['bg'=>'#faf2dc','color'=>'#9c8055']
            : ['bg'=>'#e8eef9','color'=>'#5b7396'];
    ?>
        <div class="pending-row" data-org="<?= (int)$row['org_id'] ?>">
            <span class="p-avatar"><?= htmlspecialchars($_avatarInitS($row['employee_name'])) ?></span>
            <div class="p-info">
                <div class="p-name"><?= htmlspecialchars($row['employee_name']) ?>
                    <span class="p-pill ms-1" style="background:<?= $stageColor['bg'] ?>;color:<?= $stageColor['color'] ?>;font-size:0.68rem;padding:1px 7px;"><?= $stageLabel ?></span>
                </div>
                <div class="p-detail"><?= $rangeStr ?> · <?= $_bnNumS($days) ?> দিন · জমা: <?= $_relTimeS($row['submitDate']) ?></div>
                <?php if ($isMulti): ?>
                    <div style="font-size:0.72rem;color:#7c6ba4;margin-top:2px;">
                        <?php foreach ($segs as $sg): ?>
                            <span style="background:#efeaf5;padding:0 5px;border-radius:3px;margin-right:3px;">
                                <?= $_bnNumS((int)$sg['days']) ?> দিন <?= htmlspecialchars($sg['leaveTitle'] ?? '') ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <span class="p-center"><i class="ti tabler-building"></i> <?= htmlspecialchars($row['organization_name'] ?? '—') ?></span>
            </div>
            <span class="p-pill" style="background:<?= $color['bg'] ?>;color:<?= $color['color'] ?>;"><?= htmlspecialchars($typeText) ?></span>
            <div class="p-actions">
                <a href="<?= BASE_URL ?>/views/leave/approve-application.php?menuslug=leave-approval&dataID=<?= (int)$row['approvalID'] ?>&leaveApplicationID=<?= (int)$row['applicationID'] ?>" class="p-btn" title="পর্যালোচনা করুন"><i class="ti tabler-eye"></i></a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Center filter chip click — toggle .pending-row visibility
(function() {
    document.querySelectorAll('.sig-pending-card .center-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            document.querySelectorAll('.sig-pending-card .center-chip').forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');
            var org = this.getAttribute('data-org');
            document.querySelectorAll('.sig-pending-card .pending-row').forEach(function(row) {
                if (org === 'all' || row.getAttribute('data-org') === org) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
})();
</script>
