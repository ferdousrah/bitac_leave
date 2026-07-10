<?php
/**
 * Applicant Balance Viewer Modal — read-only balance display for approvers.
 *
 * Usage:
 *   $applicantEmpID = (int)$app['applicantID'];   // employee_list.id
 *   $applicantName  = $emp['employee_name'] ?? '';
 *   include(__DIR__ . '/applicant_balance_modal.php');
 *
 * Renders:
 *   - Trigger button (returns nothing — caller embeds where needed)
 *   - Modal HTML with bars (used red + remaining green)
 *   - Tooltip on hover
 *
 * Outputs a single <button> with `data-bs-target="#applicantBalanceModal"` —
 * caller can place it wherever they want.
 */

require_once(__DIR__ . '/../function.php');

$_balViewerEmpID = isset($applicantEmpID) ? (int)$applicantEmpID : 0;
$_balViewerName  = $applicantName ?? '';

$_bv = ['fullAvg'=>['earned'=>0,'used'=>0,'balance'=>0],
        'fullAvgAvailable'=>0, 'fullAvgReserve'=>0,
        'halfAvg'=>['earned'=>0,'used'=>0,'balance'=>0],
        'casual'=>['earned'=>0,'used'=>0,'balance'=>0],
        'optional'=>['earned'=>0,'used'=>0,'balance'=>0]];

if ($_balViewerEmpID > 0 && function_exists('getEmployeeLeaveInfo')) {
    $li = @getEmployeeLeaveInfo($_balViewerEmpID);
    if (is_array($li)) {
        $_bv['fullAvgAvailable'] = (int)($li['fullAvgAvailable']['total'] ?? 0);
        $_bv['fullAvgReserve']   = (int)($li['fullAvgReserve']['total']   ?? 0);
        $_bv['fullAvg']  = ['earned' => (int)($li['fullAvgEarned']['total'] ?? 0),
                            'used'   => (int)($li['fullAvgUsed']['total']   ?? 0),
                            'balance'=> (int)($li['fullAvgBalance']['total']?? 0)];
        $_bv['halfAvg']  = ['earned' => (int)($li['halfAvgEarned']['total'] ?? 0),
                            'used'   => (int)($li['halfAvgUsed']['total']   ?? 0),
                            'balance'=> (int)($li['halfAvgBalance']['total']?? 0)];
        $cBal = (int)($li['casual']['balance']  ?? 0); $cUsed = (int)($li['casual']['spent']  ?? 0);
        $_bv['casual']  = ['earned' => $cBal + $cUsed, 'used' => $cUsed, 'balance' => $cBal];
        $oBal = (int)($li['optional']['balance']?? 0); $oUsed = (int)($li['optional']['spent']?? 0);
        $_bv['optional'] = ['earned' => $oBal + $oUsed, 'used' => $oUsed, 'balance' => $oBal];
    }
}
?>

<!-- Applicant Balance Modal (reusable for supervisor / center admin / signatory) -->
<div class="modal fade" id="applicantBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none; border-radius:14px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background:linear-gradient(135deg,#5b7396 0%,#7d9bc5 100%); color:#fff; border:none; padding:14px 20px;">
                <h5 class="modal-title" style="font-weight:600; color:#fff !important;">
                    <i class="ti tabler-wallet me-1"></i>আবেদনকারীর ছুটির ব্যালেন্স
                </h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"
                        style="background:transparent; border:none; color:#fff; width:32px; height:32px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; opacity:0.9; margin-left:auto; flex-shrink:0;">
                    <i class="ti tabler-x"></i>
                </button>
            </div>
            <div class="modal-body" style="padding:20px 22px;">
                <?php if (!empty($_balViewerName)): ?>
                    <div class="text-muted small mb-3" style="display:flex; align-items:center; gap:6px;">
                        <i class="ti tabler-user-circle"></i>
                        <strong style="color:#1f2937;"><?= htmlspecialchars($_balViewerName) ?></strong>
                    </div>
                <?php endif; ?>
                <div class="text-muted small mb-2">
                    বর্তমান ব্যালেন্স <span style="font-size:0.78rem;">(<span style="color:#c97777;">■</span> ব্যবহৃত · <span style="color:#5fa885;">■</span> অবশিষ্ট)</span>:
                </div>

                <?php
                $_bnNum = function($n){ return function_exists('banglaNumber') ? banglaNumber((string)$n) : (string)$n; };
                $maxEarned = max($_bv['fullAvg']['earned'], $_bv['halfAvg']['earned'], $_bv['casual']['earned'], $_bv['optional']['earned'], 1);
                $renderRow = function($label, $earned, $used, $available, $isLow) use ($_bnNum, $maxEarned) {
                    $totalPct = max(2, (int)round(($earned / $maxEarned) * 100));
                    $usedPct  = $earned > 0 ? (int)round(($used / $earned) * 100) : 0;
                    $availPct = 100 - $usedPct;
                    $availClass = $isLow ? 'is-low' : '';
                    return '<div class="bv-row ' . $availClass . '" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; margin-bottom:8px; background:#f9fafb; font-size:0.88rem;">'
                        .  '<div class="bv-label" style="flex:0 0 140px; font-weight:500; color:#4b5563;">' . htmlspecialchars($label) . '</div>'
                        .  '<div class="bv-bar" style="flex:1; height:10px; background:#e5e7eb; border-radius:99px; position:relative; display:flex; min-width:0; width:' . $totalPct . '%;">'
                        .    '<div class="bv-bar-used"  data-tip="ব্যবহৃত: ' . $_bnNum($used) . ' দিন" style="height:100%; width:' . $usedPct . '%; background:linear-gradient(90deg,#c97777 0%,#e0a0a0 100%); border-top-left-radius:99px; border-bottom-left-radius:99px; cursor:help; position:relative;"></div>'
                        .    '<div class="bv-bar-avail" data-tip="অবশিষ্ট: ' . $_bnNum($available) . ' দিন" style="height:100%; width:' . $availPct . '%; background:linear-gradient(90deg,#5fa885 0%,#7fb59c 100%); border-top-right-radius:99px; border-bottom-right-radius:99px; cursor:help; position:relative;"></div>'
                        .  '</div>'
                        .  '<div class="bv-num" style="flex:0 0 110px; text-align:right; color:' . ($isLow ? '#a06262' : '#3a6d4f') . '; font-weight:600; font-size:0.82rem;">' . $_bnNum($available) . ' / ' . $_bnNum($earned) . ' দিন</div>'
                        .  '</div>';
                };
                echo $renderRow('পূর্ণ গড় বেতনে', $_bv['fullAvg']['earned'], $_bv['fullAvg']['used'], $_bv['fullAvg']['balance'], $_bv['fullAvgAvailable'] < 5);
                if ($_bv['fullAvgReserve'] > 0):
                    echo '<div class="bv-row" style="background:#fbf6dd; display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:8px; margin-bottom:8px; font-size:0.84rem;">'
                       . '<div style="flex:0 0 140px; color:#9c8055;">↳ রিজার্ভ (encashable)</div>'
                       . '<div style="flex:1;"></div>'
                       . '<div style="flex:0 0 110px; text-align:right; color:#9c8055; font-weight:600;">' . $_bnNum($_bv['fullAvgReserve']) . ' দিন</div>'
                       . '</div>';
                endif;
                echo $renderRow('অর্ধ-গড় বেতনে', $_bv['halfAvg']['earned'], $_bv['halfAvg']['used'], $_bv['halfAvg']['balance'], $_bv['halfAvg']['balance'] < 5);
                echo $renderRow('নৈমিত্তিক', $_bv['casual']['earned'], $_bv['casual']['used'], $_bv['casual']['balance'], $_bv['casual']['balance'] < 1);
                echo $renderRow('ঐচ্ছিক', $_bv['optional']['earned'], $_bv['optional']['used'], $_bv['optional']['balance'], $_bv['optional']['balance'] < 1);
                ?>

                <div class="text-muted small mt-3" style="font-size:0.78rem;">
                    <i class="ti tabler-info-circle me-1"></i>
                    Hover করলে exact দিন সংখ্যা দেখাবে
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#applicantBalanceModal .bv-row { overflow:visible; }
#applicantBalanceModal .bv-bar { overflow:visible; }
#applicantBalanceModal [data-tip]:hover::after {
    content: attr(data-tip);
    position:absolute; bottom:calc(100% + 8px); left:50%;
    transform:translateX(-50%);
    background:#1f2937; color:#fff; padding:5px 10px; border-radius:6px;
    font-size:0.78rem; font-weight:500; white-space:nowrap;
    z-index:1090; box-shadow:0 4px 12px rgba(0,0,0,0.18); pointer-events:none;
}
#applicantBalanceModal [data-tip]:hover::before {
    content:""; position:absolute; bottom:calc(100% + 2px); left:50%;
    transform:translateX(-50%);
    border:5px solid transparent; border-top-color:#1f2937;
    z-index:1090; pointer-events:none;
}
#applicantBalanceModal .bv-bar-used:hover, #applicantBalanceModal .bv-bar-avail:hover {
    transform:scaleY(1.5);
}
</style>
