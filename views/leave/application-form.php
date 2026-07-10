<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$getAllEmployeeListQ = mysqli_query($con,"select * from `employee_list` where employment_status=1");

$getAllEmployeeListQ3 = mysqli_query($con,"select * from `employee_list` where employment_status=1");

$leaveTypesArr = [];
$ltQ = mysqli_query($con, "select leaveID, leaveTitle from leave_types where leaveID!=22 order by leaveTitle asc");
while ($r = mysqli_fetch_assoc($ltQ)) { $leaveTypesArr[] = $r; }
// Keep variable for any other references in this file (legacy)
$getAllLeaveTypesQ = $leaveTypesArr;

$sessionUserID = $_SESSION['userID'] ?? '';
$userStmt = mysqli_prepare($con, "SELECT * FROM user_list WHERE dataID = ?");
mysqli_stmt_bind_param($userStmt, 's', $sessionUserID);
mysqli_stmt_execute($userStmt);
$getUserDetailsQRW = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
mysqli_stmt_close($userStmt);

$getLeaveTemplatesQ = mysqli_query($con, "select * from leave_templates where templateType=1 order by templateData asc");

$currentEmpId = $getUserDetailsQRW['employee_id'] ?? '';

// AI সাজেশন — fetch user's leave balance for balance optimizer
$_aiBalance = [
    'fullAvgAvailable' => 0, 'fullAvgReserve' => 0,
    'halfAvg' => 0, 'casual' => 0, 'optional' => 0,
    // detailed: each entry is [earned/allowed, used, balance]
    'detail' => [
        'fullAvg'  => ['earned' => 0, 'used' => 0, 'balance' => 0],
        'halfAvg'  => ['earned' => 0, 'used' => 0, 'balance' => 0],
        'casual'   => ['earned' => 0, 'used' => 0, 'balance' => 0],
        'optional' => ['earned' => 0, 'used' => 0, 'balance' => 0],
    ],
];
if (!empty($currentEmpId) && function_exists('getEmployeeLeaveInfo')) {
    $_li = @getEmployeeLeaveInfo($currentEmpId);
    if (is_array($_li)) {
        $_aiBalance['fullAvgAvailable'] = (int)($_li['fullAvgAvailable']['total'] ?? 0);
        $_aiBalance['fullAvgReserve']   = (int)($_li['fullAvgReserve']['total']   ?? 0);
        $_aiBalance['halfAvg']          = (int)($_li['halfAvgBalance']['total']   ?? 0);
        $_aiBalance['casual']           = (int)($_li['casual']['balance']         ?? 0);
        $_aiBalance['optional']         = (int)($_li['optional']['balance']       ?? 0);
        // earned / used totals
        $_aiBalance['detail']['fullAvg']  = [
            'earned'  => (int)($_li['fullAvgEarned']['total']  ?? 0),
            'used'    => (int)($_li['fullAvgUsed']['total']    ?? 0),
            'balance' => (int)($_li['fullAvgBalance']['total'] ?? 0),
        ];
        $_aiBalance['detail']['halfAvg']  = [
            'earned'  => (int)($_li['halfAvgEarned']['total']  ?? 0),
            'used'    => (int)($_li['halfAvgUsed']['total']    ?? 0),
            'balance' => (int)($_li['halfAvgBalance']['total'] ?? 0),
        ];
        $cBal = (int)($_li['casual']['balance'] ?? 0);
        $cUsed = (int)($_li['casual']['spent']  ?? 0);
        $_aiBalance['detail']['casual']   = ['earned' => $cBal + $cUsed, 'used' => $cUsed, 'balance' => $cBal];
        $oBal = (int)($_li['optional']['balance'] ?? 0);
        $oUsed = (int)($_li['optional']['spent']  ?? 0);
        $_aiBalance['detail']['optional'] = ['earned' => $oBal + $oUsed, 'used' => $oUsed, 'balance' => $oBal];
    }
}
$empStmt = mysqli_prepare($con, "SELECT * FROM `employee_list` WHERE id = ?");
mysqli_stmt_bind_param($empStmt, 's', $currentEmpId);
mysqli_stmt_execute($empStmt);
$getCurrentEmpDetailsQRW = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));
mysqli_stmt_close($empStmt);

// ── Edit mode: load existing application + segments if editID present ──
$editMode = false;
$editApp = null;
$editSegments = [];
$editSupervisorID = 0;
$_editIDRaw = $_GET['editID'] ?? '';
if ($_editIDRaw !== '') {
    // Accept either base64-encoded or plain int
    $maybe = is_numeric($_editIDRaw) ? (int)$_editIDRaw : (int)base64_decode($_editIDRaw);
    if ($maybe > 0) {
        $eStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
        mysqli_stmt_bind_param($eStmt, 'i', $maybe);
        mysqli_stmt_execute($eStmt);
        $editApp = mysqli_fetch_assoc(mysqli_stmt_get_result($eStmt));
        mysqli_stmt_close($eStmt);

        // Verify ownership + editability (status: 0 = pending, 3 = ফেরত-returned-to-applicant)
        if ($editApp
            && (int)$editApp['applicantID'] === (int)$currentEmpId
            && in_array((int)$editApp['status'], [0, 3], true)) {
            // Ensure no signatory approved
            $cStmt = mysqli_prepare($con, "SELECT COUNT(*) c FROM leave_data_for_approval WHERE leaveApplicationID = ? AND isApproved = 1");
            mysqli_stmt_bind_param($cStmt, 'i', $maybe);
            mysqli_stmt_execute($cStmt);
            $cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['c'] ?? 0);
            mysqli_stmt_close($cStmt);
            if ($cnt === 0) {
                $editMode = true;
                // Load segments
                $sStmt = mysqli_prepare($con,
                    "SELECT * FROM leave_application_segments WHERE applicationID = ? ORDER BY serial ASC, dataID ASC");
                mysqli_stmt_bind_param($sStmt, 'i', $maybe);
                mysqli_stmt_execute($sStmt);
                $sRes = mysqli_stmt_get_result($sStmt);
                while ($r = mysqli_fetch_assoc($sRes)) $editSegments[] = $r;
                mysqli_stmt_close($sStmt);
                // Load supervisor (the first signatory row marked isSupervisor=1)
                $sup = mysqli_prepare($con, "SELECT signatory FROM leave_data_for_approval WHERE leaveApplicationID = ? AND isSupervisor = 1 LIMIT 1");
                mysqli_stmt_bind_param($sup, 'i', $maybe);
                mysqli_stmt_execute($sup);
                $supRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sup));
                $editSupervisorID = (int)($supRow['signatory'] ?? 0);
                mysqli_stmt_close($sup);
            } else {
                $editApp = null;
            }
        } else {
            $editApp = null;
        }
    }
}

$currentOrgId = $getCurrentEmpDetailsQRW['organization_id'] ?? 0;
$listStmt = mysqli_prepare($con, "SELECT id, employee_id, employee_name, designation FROM `employee_list` WHERE employment_status = 1 AND organization_id = ? ORDER BY display_order ASC");
mysqli_stmt_bind_param($listStmt, 's', $currentOrgId);
mysqli_stmt_execute($listStmt);
$getAllEmployeeListQ2 = mysqli_stmt_get_result($listStmt);

function Bengali_DTN($NRS){
	$englDTN = array
			('1','2','3','4','5','6','7','8','9','0',
			'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday',
			'Sat','Sun','Mon','Tue','Wed','Thu','Fri',
			'am','pm','at','st','nd','rd','th',
			'January','February','March','April','May','June','July','August','September','October','November','December',
			'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
			$bangDTN = array
			('১','২','৩','৪','৫','৬','৭','৮','৯','০',
			'শনিবার','রবিবার','সোমবার','মঙ্গলবার','বুধবার','বৃহস্পতিবার','শুক্রবার',
			'শনি','রবি','সোম','মঙ্গল','বুধ','বৃহঃ','শুক্র',
			'পূর্বাহ্ণ','অপরাহ্ণ','','','','','',
			'জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর',
			'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে');
			$converted = str_replace($bangDTN, $englDTN, $NRS);
			return $converted;
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0">
            <i class="ti <?= $editMode ? 'tabler-edit' : 'tabler-file-plus' ?> me-2 text-primary"></i>
            <?= $editMode ? 'ছুটির আবেদন সম্পাদনা' : 'ছুটির আবেদনপত্র' ?>
        </h4>
        <?php if ($editMode): ?>
            <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>আবেদন #<?= (int)$editApp['dataID'] ?> সম্পাদনা মোডে</div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
/* ─── Form section headers ─────────────────────────────────────── */
.form-section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    margin: 28px 0 20px;
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-left: 3px solid var(--sec-accent, #6c5ce7);
    border-radius: 0.6rem;
    position: relative;
}
.form-section-header[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.form-section-header[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }
.form-section-header[data-color="amber"]  { --sec-bg: #fff3e1; --sec-accent: #b8651a; }
.form-section-header[data-color="purple"] { --sec-bg: #f5e9ff; --sec-accent: #7c3aed; }

.form-section-header .section-num {
    width: 30px; height: 30px;
    border-radius: 0.5rem;
    background: var(--sec-bg, #f0edff);
    color: var(--sec-accent, #6c5ce7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
}
.form-section-header .section-text { flex: 1; min-width: 0; }
.form-section-header .section-title {
    font-size: 0.98rem;
    font-weight: 600;
    color: #2c2e3a;
    margin: 0;
    line-height: 1.3;
}
.form-section-header .section-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 2px;
    display: block;
    line-height: 1.4;
}
.form-section-header .section-icon {
    width: 38px; height: 38px;
    border-radius: 0.55rem;
    background: var(--sec-bg, #f0edff);
    color: var(--sec-accent, #6c5ce7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

/* First section header inside the form has minimal top margin */
.card-body > form > .form-section-header:first-of-type,
.card-body > form > input[type="hidden"] + .form-section-header {
    margin-top: 0;
}

@media (max-width: 575px) {
    .form-section-header { padding: 12px 14px; gap: 10px; }
    .form-section-header .section-num { width: 26px; height: 26px; font-size: 0.8rem; }
    .form-section-header .section-icon { display: none; }
    .form-section-header .section-title { font-size: 0.92rem; }
}

/* ─── Form labels & controls polish ────────────────────────────── */
.form-login .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.form-login .col-form-label .text-danger {
    font-weight: 700;
}
.form-login .form-control,
.form-login .form-select {
    font-size: 0.9rem;
    border-color: #e0e4ee;
}
.form-login .form-control:focus,
.form-login .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.form-login .form-control[readonly] {
    background-color: #fafbfd;
    color: #5d6580;
}

/* Leave segment cards */
.leave-segment {
    background: #fafbfd !important;
    border: 1px solid #eef0f5 !important;
    border-radius: 0.6rem !important;
    padding: 14px !important;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.leave-segment:hover {
    border-color: #ddd5f6 !important;
    background: #fdfcff !important;
}
.leave-segment .segment-badge {
    background: #f0edff !important;
    color: #5648c4 !important;
    font-weight: 600;
    font-size: 0.78rem;
    padding: 0.35em 0.7em;
    border-radius: 0.4rem;
}
.leave-segment .form-label.small {
    font-size: 0.78rem;
    font-weight: 500;
    color: #5d6580;
    margin-bottom: 0.3rem;
}

/* Total + range display */
#totalDaysDisplay {
    color: #5648c4;
    font-weight: 700;
}

/* BSR warning alert refinement */
#bsrWarning.alert-danger {
    background: #fff1f0;
    color: #b13c3c;
    border: 1px solid #f5c6c6;
    border-left: 3px solid #dc3545;
    font-size: 0.85rem;
}

/* ── AI সাজেশন (auto-split) ── */
.suggest-btn {
    background: transparent;
    border: 1px solid #e0e4ee;
    color: #9ca3af;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .25s ease;
    font-size: 0.9rem;
    padding: 0;
    margin-left: 8px;
    position: relative;
}
.suggest-btn:hover { color: #6c5ce7; border-color: #b9b0f4; background: #f8f7ff; }
.suggest-btn.has-suggestion {
    color: #fff;
    background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
    border-color: transparent;
    box-shadow: 0 0 0 0 rgba(108, 92, 231, 0.45);
    animation: suggestPulse 1.8s ease-out infinite;
}
@keyframes suggestPulse {
    0%   { box-shadow: 0 0 0 0 rgba(108, 92, 231, 0.45); }
    70%  { box-shadow: 0 0 0 9px rgba(108, 92, 231, 0); }
    100% { box-shadow: 0 0 0 0 rgba(108, 92, 231, 0); }
}
.suggest-btn.has-suggestion::after {
    content: "";
    position: absolute;
    top: -2px; right: -2px;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #ffc83d;
    border: 1.5px solid #fff;
}

/* Modal — suggestion preview */
#suggestModal .modal-content {
    border: none;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
#suggestModal .modal-header {
    background: linear-gradient(135deg, #6c5ce7 0%, #5648c4 100%);
    color: #fff;
    border: none;
    padding: 16px 22px;
}
#suggestModal .modal-header .modal-title {
    font-weight: 600;
    color: #fff !important;
    display: flex;
    align-items: center;
    gap: 8px;
}
/* Custom close button for AI modals (bypasses Vuexy/Bootstrap .btn-close override) */
.ai-modal-close {
    background: transparent;
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    cursor: pointer;
    opacity: 0.85;
    transition: all .2s ease;
    padding: 0;
    line-height: 1;
    margin-left: auto;
    flex-shrink: 0;
}
.ai-modal-close:hover {
    background: rgba(255,255,255,0.18);
    opacity: 1;
}
.ai-modal-close i { color: #fff; }
#suggestModal .modal-body { padding: 20px 22px; }
#suggestModal .suggest-detect {
    background: #fff7ed;
    border-left: 3px solid #d4a056;
    padding: 10px 14px;
    border-radius: 0.4rem;
    font-size: 0.86rem;
    color: #8b6f47;
    margin-bottom: 14px;
}
#suggestModal .suggest-rule {
    background: #f5f6fa;
    padding: 8px 12px;
    border-radius: 0.4rem;
    font-size: 0.8rem;
    color: #4b5563;
    margin-bottom: 16px;
}
#suggestModal .suggest-preview {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 0.5rem;
    padding: 14px;
}
#suggestModal .suggest-preview-title {
    font-weight: 600;
    color: #1a7e44;
    margin-bottom: 10px;
    font-size: 0.86rem;
    display: flex;
    align-items: center;
    gap: 6px;
}
#suggestModal .preview-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    background: #fff;
    border-radius: 0.4rem;
    margin-bottom: 6px;
    font-size: 0.84rem;
    border: 1px solid #d1fae5;
}
#suggestModal .preview-row:last-child { margin-bottom: 0; }
#suggestModal .preview-row.is-new {
    background: #ecfdf5;
    border-style: dashed;
    border-color: #7fb59c;
}
#suggestModal .preview-badge {
    background: #1a7e44;
    color: #fff;
    font-size: 0.68rem;
    padding: 2px 7px;
    border-radius: 0.3rem;
    flex-shrink: 0;
    font-weight: 600;
}
#suggestModal .preview-row.is-new .preview-badge { background: #b8651a; }
#suggestModal .preview-text { flex: 1; color: #2c2e3a; }
#suggestModal .preview-days { color: #1a7e44; font-weight: 700; }
#suggestModal .modal-footer {
    border: none;
    padding: 12px 22px 18px;
}

/* Shared modal styling for reason-tpl and balance-opt */
#reasonTplModal .modal-content,
#balanceOptModal .modal-content {
    border: none;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
#reasonTplModal .modal-header,
#balanceOptModal .modal-header {
    background: linear-gradient(135deg, #6c5ce7 0%, #5648c4 100%);
    color: #fff;
    border: none;
    padding: 16px 22px;
}
#reasonTplModal .modal-title,
#balanceOptModal .modal-title {
    font-weight: 600;
    color: #fff !important;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Reason templates grid */
.reason-card {
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    padding: 14px;
    cursor: pointer;
    transition: all .2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.reason-card:hover {
    border-color: #b9b0f4;
    box-shadow: 0 4px 14px rgba(108, 92, 231, 0.12);
    transform: translateY(-2px);
}
.reason-card .rc-icon {
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    background: var(--rc-bg, #f0edff);
    color: var(--rc-color, #5648c4);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    margin-bottom: 10px;
}
.reason-card .rc-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: #2c2e3a;
    margin-bottom: 6px;
}
.reason-card .rc-type {
    font-size: 0.72rem;
    color: #5648c4;
    background: #f0edff;
    padding: 2px 8px;
    border-radius: 0.3rem;
    display: inline-block;
    margin-top: auto;
    align-self: flex-start;
    font-weight: 500;
}

/* Balance optimizer */
.bal-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    background: #f9fafb;
    font-size: 0.88rem;
}
.bal-row .bl-label { flex: 0 0 140px; font-weight: 500; color: #4b5563; }
.bal-row .bl-bar {
    flex: 1;
    height: 10px;
    background: #e5e7eb;
    border-radius: 99px;
    /* overflow hidden removed so tooltip can escape */
    position: relative;
    display: flex;
    min-width: 0;
}
.bal-row .bl-bar-used,
.bal-row .bl-bar-avail {
    height: 100%;
    transition: width .3s ease, transform .15s ease;
    position: relative;
    cursor: help;
}
.bal-row .bl-bar-used {
    background: linear-gradient(90deg, #c97777 0%, #f87171 100%);
    border-top-left-radius: 99px;
    border-bottom-left-radius: 99px;
}
.bal-row .bl-bar-avail {
    background: linear-gradient(90deg, #5fa885 0%, #7fb59c 100%);
    border-top-right-radius: 99px;
    border-bottom-right-radius: 99px;
}
.bal-row .bl-bar-used:hover,
.bal-row .bl-bar-avail:hover { transform: scaleY(1.6); }

/* Custom CSS tooltip on bar segments */
.bal-row [data-tip] { position: relative; }
.bal-row [data-tip]:hover::after {
    content: attr(data-tip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: #fff;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 500;
    white-space: nowrap;
    z-index: 1090;
    box-shadow: 0 4px 12px rgba(0,0,0,0.18);
    pointer-events: none;
}
.bal-row [data-tip]:hover::before {
    content: "";
    position: absolute;
    bottom: calc(100% + 2px);
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1f2937;
    z-index: 1090;
    pointer-events: none;
}
.bal-row.is-low .bl-bar-avail {
    background: linear-gradient(90deg, #d4a056 0%, #e6c569 100%);
}
/* Allow tooltip to escape parent overflow */
#balanceOptModal .modal-body { overflow: visible; }
#balanceOptModal .bal-row { overflow: visible; }
.bal-row .bl-num {
    flex: 0 0 110px;
    text-align: right;
    color: #5fa885;
    font-weight: 600;
    font-size: 0.82rem;
}
.bal-row.is-low .bl-num { color: #a06262; }
.bal-summary {
    background: #fff7ed;
    border-left: 3px solid #d4a056;
    padding: 10px 14px;
    border-radius: 6px;
    color: #8b6f47;
    font-size: 0.86rem;
    margin: 14px 0;
}
.bal-summary.ok { background: #f0fdf4; border-left-color: #5fa885; color: #3a6d4f; }
.bal-recommend {
    background: #f0edff;
    border: 1px solid #ddd5f6;
    border-radius: 0.5rem;
    padding: 14px;
    margin-top: 12px;
}
.bal-recommend .br-title {
    font-weight: 600;
    color: #5648c4;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
}
.bal-recommend .br-row {
    background: #fff;
    border: 1px solid #ddd5f6;
    border-radius: 0.4rem;
    padding: 8px 12px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.84rem;
    color: #2c2e3a;
}
.bal-recommend .br-row:last-child { margin-bottom: 0; }

/* ── Modern card wrap ─────────────────────────────────── */
.app-form-wrap {
    max-width: 1100px;
    margin: 0 auto;     /* center-aligned */
    width: 100%;
}
@media (min-width: 1400px) {
    .app-form-wrap { max-width: 1200px; }
}
.app-form-card { border-radius: 0.75rem; }
.app-form-card .card-body { padding: 1.1rem 1.5rem; }
@media (max-width: 575px) {
    .app-form-wrap { max-width: none; }
    .app-form-card .card-body { padding: 0.85rem 1rem; }
}

/* Compact form spacing — fit more in one screen */
.app-form-card .form-section-header {
    padding: 8px 12px !important;
    margin: 10px 0 8px !important;
    gap: 8px !important;
}
.app-form-card .form-section-header:first-of-type { margin-top: 0 !important; }
.app-form-card .form-section-header .section-num {
    width: 24px !important;
    height: 24px !important;
    font-size: 0.78rem !important;
}
.app-form-card .form-section-header .section-icon {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.95rem !important;
}
.app-form-card .form-section-header .section-title { font-size: 0.88rem !important; }
.app-form-card .form-section-header .section-sub { font-size: 0.7rem !important; line-height: 1.25 !important; }

.app-form-card .row.mb-3 { margin-bottom: 0.45rem !important; }
.app-form-card .row.g-2 { --bs-gutter-y: 0.4rem !important; }
.app-form-card .col-form-label {
    padding-top: 0.3rem !important;
    padding-bottom: 0.3rem !important;
    font-size: 0.82rem !important;
}
.app-form-card .form-control,
.app-form-card .form-select {
    padding: 0.3rem 0.65rem !important;
    font-size: 0.86rem !important;
    min-height: 32px !important;
    height: 32px !important;
}
.app-form-card textarea.form-control { height: auto !important; }
.app-form-card .input-group-text {
    padding: 0.3rem 0.6rem !important;
    font-size: 0.86rem !important;
}
/* Select2 wrapper match height */
.app-form-card .select2-container--bootstrap-5 .select2-selection {
    min-height: 32px !important;
    padding: 0.25rem 0.65rem !important;
}
.app-form-card .select2-container--bootstrap-5 .select2-selection__rendered {
    line-height: 1.4 !important;
    font-size: 0.86rem !important;
}
.app-form-card textarea.form-control {
    min-height: auto !important;
    line-height: 1.4 !important;
}
.app-form-card #leaveApplication {
    min-height: 0 !important;
    height: 56px !important;     /* approx 2 lines */
    resize: vertical !important;
}

/* Tighter leave segments */
.app-form-card .leave-segment {
    padding: 8px 10px !important;
    margin-bottom: 6px !important;
}
.app-form-card .leave-segment .form-label.small {
    font-size: 0.7rem !important;
    margin-bottom: 0.15rem !important;
}
.app-form-card .leave-segment .row.g-2 > * { padding-top: 0 !important; }
.app-form-card .leave-segment .segment-badge {
    font-size: 0.72rem !important;
    padding: 0.25em 0.55em !important;
}

/* Action row tighter */
.app-form-card form > div:last-child {
    padding-top: 0.65rem !important;
    margin-top: 0.65rem !important;
}
.app-form-card .btn {
    padding: 0.35rem 0.9rem !important;
    font-size: 0.88rem !important;
}

/* Page header — reduce vertical real-estate */
body .row.mb-4.align-items-center:has(.tabler-file-plus, .tabler-edit) {
    margin-bottom: 0.85rem !important;
}
body h4.fw-bold.mb-0 { font-size: 1.15rem !important; }

/* ── AI accent button (theme-matching) ─────────────────── */
.ai-btn {
    background: linear-gradient(135deg, #6c5ce7 0%, #5648c4 100%);
    border: none;
    color: #fff;
    font-weight: 500;
    transition: filter 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 1px 3px rgba(86, 72, 196, 0.18);
}
.ai-btn:hover, .ai-btn:focus {
    color: #fff;
    filter: brightness(1.08);
    box-shadow: 0 3px 10px rgba(86, 72, 196, 0.28);
}
.ai-btn:active { transform: translateY(1px); }
.ai-btn:disabled { opacity: 0.6; }
</style>

<!-- Leave Application Form Card -->
<div class="app-form-wrap">
<div class="card app-form-card shadow-sm border-0">
    <div class="card-body">
        <form class="form-login" name="form" id="form">
            <input type="hidden" name="organization_id" value="<?php echo $getCurrentEmpDetailsQRW['organization_id']; ?>" />
            <?php if ($editMode): ?>
                <input type="hidden" name="editID" value="<?= (int)$editApp['dataID'] ?>" />
            <?php endif; ?>

            <!-- Application Type Section -->
            <div class="form-section-header" data-color="indigo">
                <div class="section-num">১</div>
                <div class="section-text">
                    <h6 class="section-title">আবেদনের তথ্য</h6>
                </div>
                <span class="section-icon"><i class="ti tabler-file-text"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="applicationType">
                    আবেদনের প্রকার <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="form-select" name="applicationType" id="applicationType" onChange="generateSubject()" required>
                        <option value=''>-- আবেদনের প্রকার নির্বাচন করুন --</option>
                        <option value='1'>নিয়মিত ছুটির আবেদন</option>
                        <option value='2'>অনুপস্থিতকালের জন্য ছুটির আবেদন</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3" id="isinformed" style="display: none;">
                <label class="col-md-3 col-form-label">
                    কর্তৃপক্ষকে পূর্বে অবগত <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="isinformedValue" id="informed_yes" value="1" />
                        <label class="form-check-label" for="informed_yes">করেছি</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="isinformedValue" id="informed_no" value="0" />
                        <label class="form-check-label" for="informed_no">করতে পারিনি</label>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="to">
                    বরাবর <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single-2" style="width: 100%;" name="to" id="to" required>
                        <option value=''>-- নির্বাচন করুন --</option>
                        <?php if($getCurrentEmpDetailsQRW['organization_id'] == 4){ ?>
                        <option selected value="1">পরিচালক (প্রশাসন ও অর্থ)</option>
                        <option value="2">মহাপরিচালক</option>
                        <?php }else if($getCurrentEmpDetailsQRW['organization_id'] == 5 || $getCurrentEmpDetailsQRW['organization_id'] == 6 || $getCurrentEmpDetailsQRW['organization_id'] == 7 || $getCurrentEmpDetailsQRW['organization_id'] == 8 || $getCurrentEmpDetailsQRW['organization_id'] == 9){ ?>
                        <option selected value="1">অতিরিক্ত পরিচালক (কেন্দ্র প্রধান)</option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    কর্মকর্তা/কর্মচারী নির্বাচন করুন <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="form-select" name="employeeID" id="employeeID" onChange="getEmployees(this.value)" required>
                        <option value='<?php echo $getUserDetailsQRW['employee_id']; ?>'>নিজ</option>
                        <option value='0'>পক্ষে</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3" id="employeeIDOnbehalfDiv" style="display: none;">
                <label class="col-md-3 col-form-label" for="employeeIDOnbehalf">&nbsp;</label>
                <div class="col-md-9">
                    <select class="employeeIDOnbehalf" style="width: 100%;" name="employeeIDOnbehalf" id="employeeIDOnbehalf">
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                    </select>
                </div>
            </div>

            <!-- Leave Details Section -->
            <div class="form-section-header" data-color="green">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">ছুটির বিবরণ</h6>
                </div>
                <span class="section-icon"><i class="ti tabler-calendar-event"></i></span>
            </div>

            <!-- Segments container -->
            <div id="leaveSegments">
                <!-- First segment (server-side rendered) -->
                <div class="leave-segment p-3 mb-2 border rounded" data-segment-idx="0" style="background:#fafbff;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-label-primary segment-badge">ধরন ১</span>
                            <button type="button" class="suggest-btn" onclick="openSuggestionModal(this)" title="AI সাজেশন">
                                <i class="ti tabler-sparkles"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-segment-btn" onclick="removeSegment(this)" title="বাদ দিন" style="display:none;">
                            <i class="ti tabler-x"></i> বাদ
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small">ছুটির ধরণ <span class="text-danger">*</span></label>
                            <select class="form-select segment-type" name="segment_leaveType[]" required>
                                <option value=''>-- নির্বাচন করুন --</option>
                                <?php foreach ($leaveTypesArr as $lt): ?>
                                    <option value='<?= $lt['leaveID'] ?>'><?= htmlspecialchars($lt['leaveTitle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">শুরু <span class="text-danger">*</span></label>
                            <input type="text" class="form-control segment-from" name="segment_dateFrom[]" placeholder="dd/mm/yyyy" required autocomplete="off" readonly />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">শেষ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control segment-to" name="segment_dateTo[]" placeholder="dd/mm/yyyy" required autocomplete="off" readonly />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">দিন</label>
                            <input type="text" class="form-control segment-days" name="segment_days[]" placeholder="—" autocomplete="off" readonly />
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3 align-items-center">
                <div class="col-md-3"></div>
                <div class="col-md-9 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-label-primary" onclick="addSegment()">
                        <i class="ti tabler-plus me-1"></i>আরেকটা ধরন যোগ করুন
                    </button>
                    <div class="text-muted small d-flex align-items-center">
                        মোট: <strong id="totalDaysDisplay" class="text-dark ms-1">০ দিন</strong>
                        <span class="ms-2" id="totalRangeDisplay" style="opacity:0.7;"></span>
                        <button type="button" class="suggest-btn ms-2" id="balanceOptimizeBtn" onclick="openBalanceOptimizer()" title="ব্যালেন্স অপ্টিমাইজার (AI সাজেশন)">
                            <i class="ti tabler-sparkles"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- BSR validation warning — shown right below segments so it's instantly visible -->
            <div class="row mb-3">
                <div class="col-md-3"></div>
                <div class="col-md-9">
                    <div id="bsrWarning" class="alert alert-warning mb-0" style="display:none;">
                        <i class="ti tabler-alert-triangle me-1"></i>
                        <span id="bsrWarningText"></span>
                    </div>
                </div>
            </div>

            

            <!-- Application Content Section -->
            <div class="form-section-header" data-color="amber">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">আবেদনের বিষয়বস্তু</h6>
                </div>
                <span class="section-icon"><i class="ti tabler-edit"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="subject">
                    বিষয় <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <input type="text" class="form-control" name="subject" id="subject" placeholder="" readonly />
                    <small class="text-muted mt-1 d-block">
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="templateSelector">
                    লিভ টেম্পলেট
                </label>
                <div class="col-md-9">
                    <select class="form-select" id="templateSelector" onchange="insertTemplate(this.value)">
                        <option value=''>-- টেম্পলেট নির্বাচন করুন --</option>
                        <?php while($ltRow = mysqli_fetch_array($getLeaveTemplatesQ)){ ?>
                        <option value='<?php echo htmlspecialchars($ltRow['templateData']); ?>'><?php echo htmlspecialchars($ltRow['templateData']); ?></option>
                        <?php } ?>
                    </select>
                    
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="leaveApplication">
                    ছুটির দরখাস্ত <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <textarea class="form-control" name="leaveApplication" id="leaveApplication" rows="6" placeholder="" required></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="leaveFile">
                    সংযুক্তি <span id="leaveFileRequired" class="text-danger" style="display:none;">*</span>
                </label>
                <div class="col-md-9">
                    <input type="file" name="leaveFile" id="leaveFile" class="form-control" />
                    <small class="text-danger mt-1 d-none" id="leaveFileMandatoryHint">
                        <i class="ti tabler-alert-circle me-1"></i>
                        <span id="leaveFileMandatoryText">এই ছুটির জন্য ডকুমেন্ট সংযুক্ত করা আবশ্যক</span>
                    </small>
                </div>
            </div>

            <!-- Supervisor Section -->
            <div class="form-section-header" data-color="purple">
                <div class="section-num">৪</div>
                <div class="section-text">
                    <h6 class="section-title">সুপারভাইজার তথ্য</h6>
                </div>
                <span class="section-icon"><i class="ti tabler-user-check"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="supervisorID">
                    সুপারভাইজার/ঊর্ধ্বতন কর্মকর্তা <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single" style="width: 100%;" name="supervisorID" id="supervisorID" required>
                        <option value=''>-- সুপারভাইজার নির্বাচন করুন --</option>
                        <?php
                        $desigStmt = mysqli_prepare($con, "SELECT * FROM job_title WHERE id = ?");
                        while($empRow=mysqli_fetch_array($getAllEmployeeListQ2)){
                            $desigIdVal = $empRow['designation'];
                            mysqli_stmt_bind_param($desigStmt, 's', $desigIdVal);
                            mysqli_stmt_execute($desigStmt);
                            $desigResult = mysqli_stmt_get_result($desigStmt);
                            $getSupervisorDesigQRW = $desigResult ? mysqli_fetch_assoc($desigResult) : null;
                        ?>
                        <option value='<?php echo $empRow['id']; ?>'>
                            <?php echo Bengali_DTN($empRow['employee_id']).' - '.htmlspecialchars($empRow['employee_name']).', '.htmlspecialchars($getSupervisorDesigQRW['job_title_name'] ?? ''); ?>
                        </option>
                        <?php }
                        mysqli_stmt_close($desigStmt);
                        mysqli_stmt_close($listStmt);
                        ?>
                    </select>
                </div>
            </div>

            <div id="formresult"></div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3" style="border-top:1px solid #eef0f5;">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" name="submit" id="submit" class="btn btn-primary px-4">
                    <i class="ti tabler-send me-1"></i>প্রেরণ করুন
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- AI: Reason Templates Modal -->
<div class="modal fade" id="reasonTplModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-sparkles"></i> কারণের টেমপ্লেট</h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3"><i class="ti tabler-info-circle me-1"></i>একটি কারণ বেছে নিলে আবেদনপত্রের বিষয়বস্তু এবং ছুটির ধরণ স্বয়ংক্রিয়ভাবে পূরণ হবে।</p>
                <div class="row g-2" id="reasonTplGrid"></div>
            </div>
        </div>
    </div>
</div>

<!-- AI: Balance Optimizer Modal -->
<div class="modal fade" id="balanceOptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-sparkles"></i> ব্যালেন্স অপ্টিমাইজার</h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <div class="modal-body">
                <div id="balanceOptBody"></div>
            </div>
            <div class="modal-footer" id="balanceOptFooter">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn ai-btn" id="applyBalanceOptBtn" style="display:none;">
                    <i class="ti tabler-check me-1"></i>প্রয়োগ করুন
                </button>
            </div>
        </div>
    </div>
</div>

<!-- AI সাজেশন Modal (auto-split) -->
<div class="modal fade" id="suggestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti tabler-sparkles"></i> AI সাজেশন</h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="suggest-detect" id="suggestDetect"></div>
                <div class="suggest-rule" id="suggestRule"></div>
                <div class="suggest-preview">
                    <div class="suggest-preview-title">
                        <i class="ti tabler-wand"></i> প্রস্তাবিত পরিবর্তন
                    </div>
                    <div id="suggestPreview"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn ai-btn" id="applySuggestionBtn">
                    <i class="ti tabler-check me-1"></i>প্রয়োগ করুন
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>

<script type="text/javascript">
$(document).ready(function() {
    // Bind datepicker + handlers to all currently-rendered segments
    $('.leave-segment').each(function() { bindSegmentHandlers($(this)); });
    updateRemoveButtons();
    updateAttachmentRequired();

    // Initialize Select2
    $('.js-example-basic-single').select2({
        placeholder: "-- সুপারভাইজার নির্বাচন করুন --",
        allowClear: true,
        width: '100%'
    });

    $('.js-example-basic-single-2').select2({
        placeholder: "-- নির্বাচন করুন --",
        allowClear: true,
        width: '100%'
    });
});

// Insert template into textarea
function insertTemplate(str){
    $('#leaveApplication').val(str);
}

// Get employees for "on behalf" option
function getEmployees(option){
    if(option == 0){
        var dataString = 'option='+ option;

        $.ajax({
            type: "POST",
            url: "get_employees.php",
            data: dataString,
            cache: false,
            success: function(html){
                $("#employeeIDOnbehalf").html(html);
                $('.employeeIDOnbehalf').select2({
                    placeholder: "-- কর্মচারী নির্বাচন করুন --",
                    allowClear: true,
                    width: '100%'
                });
            }
        });
        $('#employeeIDOnbehalfDiv').show();
    } else{
        $('#employeeIDOnbehalfDiv').hide();
    }
}

// ════════════════════════════════════════════════════════════════
// Multi-segment leave application — dynamic add/remove + validation
// (var instead of const + window-scoped functions to survive Turbo re-execution)
// ════════════════════════════════════════════════════════════════

// Leave types available (from PHP) — use var so a Turbo Frame re-load doesn't blow up
var LEAVE_TYPES = <?= json_encode($leaveTypesArr, JSON_UNESCAPED_UNICODE) ?>;

// Helper: parse "dd/mm/yyyy" → Date object (or null)
function parseDmy(str) {
    if (!str) return null;
    const p = str.split('/');
    if (p.length !== 3) return null;
    const d = new Date(`${p[2]}-${p[1]}-${p[0]}`);
    return isNaN(d) ? null : d;
}

function fmtDmy(date) {
    const d = String(date.getDate()).padStart(2, '0');
    const m = String(date.getMonth() + 1).padStart(2, '0');
    return `${d}/${m}/${date.getFullYear()}`;
}

// Add a new segment row
function addSegment() {
    const $existing = $('.leave-segment').last();
    const newIdx = $('.leave-segment').length;
    let optsHtml = `<option value=''>-- নির্বাচন করুন --</option>`;
    LEAVE_TYPES.forEach(lt => {
        optsHtml += `<option value="${lt.leaveID}">${lt.leaveTitle}</option>`;
    });
    const html = `
        <div class="leave-segment p-3 mb-2 border rounded" data-segment-idx="${newIdx}" style="background:#fafbff;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center">
                    <span class="badge bg-label-primary segment-badge">ধরন ${toBn(newIdx + 1)}</span>
                    <button type="button" class="suggest-btn" onclick="openSuggestionModal(this)" title="AI সাজেশন">
                        <i class="ti tabler-sparkles"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-segment-btn" onclick="removeSegment(this)" title="বাদ দিন">
                    <i class="ti tabler-x"></i> বাদ
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">ছুটির ধরণ <span class="text-danger">*</span></label>
                    <select class="form-select segment-type" name="segment_leaveType[]" required>${optsHtml}</select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">শুরু <span class="text-danger">*</span></label>
                    <input type="text" class="form-control segment-from" name="segment_dateFrom[]" placeholder="dd/mm/yyyy" required autocomplete="off" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">শেষ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control segment-to" name="segment_dateTo[]" placeholder="dd/mm/yyyy" required autocomplete="off" readonly />
                </div>
                <div class="col-md-2">
                    <label class="form-label small">দিন</label>
                    <input type="text" class="form-control segment-days" name="segment_days[]" placeholder="—" autocomplete="off" readonly />
                </div>
            </div>
        </div>`;
    $('#leaveSegments').append(html);
    bindSegmentHandlers($('.leave-segment').last());
    updateRemoveButtons();
    recalcAll();
}

// Toggle bn digit
function toBn(n) { return String(n).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[d]); }

// Remove a segment (cannot remove if only 1 left)
function removeSegment(btn) {
    if ($('.leave-segment').length <= 1) return;
    $(btn).closest('.leave-segment').remove();
    renumberSegments();
    updateRemoveButtons();
    recalcAll();
}

function renumberSegments() {
    $('.leave-segment').each(function(i) {
        $(this).attr('data-segment-idx', i);
        $(this).find('.segment-badge').text('ধরন ' + toBn(i + 1));
    });
}

function updateRemoveButtons() {
    const segs = $('.leave-segment');
    if (segs.length <= 1) {
        segs.find('.remove-segment-btn').hide();
    } else {
        segs.find('.remove-segment-btn').show();
    }
}

// Bind datepickers + change handlers to a segment
function bindSegmentHandlers($seg) {
    const $from = $seg.find('.segment-from');
    const $to   = $seg.find('.segment-to');
    [$from, $to].forEach($el => {
        if (!$el.hasClass('hasDatepicker')) {
            $el.datepicker({ dateFormat: 'dd/mm/yy' });
        }
    });
    $seg.find('.segment-from, .segment-to').on('change', recalcAll);
    $seg.find('.segment-type').on('change', function() {
        recalcAll();
        updateAttachmentRequired();
    });
}

// Recalculate one segment's days
function recalcSegment($seg) {
    const dFrom = parseDmy($seg.find('.segment-from').val());
    const dTo   = parseDmy($seg.find('.segment-to').val());
    if (dFrom && dTo && dTo >= dFrom) {
        const days = Math.floor((dTo - dFrom) / 86400000) + 1;
        $seg.find('.segment-days').val(days);
        return { from: dFrom, to: dTo, days, type: parseInt($seg.find('.segment-type').val()) || 0 };
    } else {
        $seg.find('.segment-days').val('');
        return null;
    }
}

// Total days, range, BSR check, subject regen
function recalcAll() {
    const segs = [];
    let total = 0;
    let earliest = null, latest = null;

    $('.leave-segment').each(function() {
        const s = recalcSegment($(this));
        if (s) {
            segs.push(s);
            total += s.days;
            if (!earliest || s.from < earliest) earliest = s.from;
            if (!latest || s.to > latest) latest = s.to;
        }
    });

    $('#totalDaysDisplay').text(toBn(total) + ' দিন');
    if (earliest && latest) {
        $('#totalRangeDisplay').text(`(${toBn(fmtDmy(earliest))} → ${toBn(fmtDmy(latest))})`);
    } else {
        $('#totalRangeDisplay').text('');
    }

    validateBsr(segs);
    updateAttachmentRequired();
    updateSuggestionState();
    if (segs.length > 0 && total > 0) generateSubjectFromSegments(segs, total);
}

// ─────────────────────────────────────────────────────────────────────
// AI সাজেশন (auto-split) — detect & highlight icon when available
// ─────────────────────────────────────────────────────────────────────

// Returns a suggestion object if this segment can be auto-split, else null.
// Suggestion shape:
//   { kind: 'split' | 'trim',
//     detect: 'human-readable detection text',
//     rule:   'human-readable BSR rule',
//     newSegments: [ {type, from, to, days, isNew}, ... ] }
function getSuggestionFor($seg) {
    const dFrom = parseDmy($seg.find('.segment-from').val());
    const dTo   = parseDmy($seg.find('.segment-to').val());
    const type  = parseInt($seg.find('.segment-type').val()) || 0;
    const isFirst = $seg.is($('.leave-segment').first());

    // ── Type Recommendation: type empty + body has matching keywords ──
    if (!type && isFirst) {
        const rec = (typeof suggestTypeFromBody === 'function') ? suggestTypeFromBody() : null;
        if (rec) {
            return {
                kind: 'recommend-type',
                detect: `আবেদনের কারণ পড়ে মনে হচ্ছে আপনার "${rec.typeName}" দরকার।`,
                rule:   'কীওয়ার্ড match করে BSR অনুযায়ী সবচেয়ে উপযুক্ত ছুটির ধরণ suggest করা হয়েছে।',
                applyTypeId: rec.typeId,
                applyTypeName: rec.typeName
            };
        }
    }

    // ── Mismatch: "strict" type selected but body lacks matching keywords ──
    // Strict types require specific reason context per BSR
    const STRICT = {
        2:  { name: 'অর্ধ-গড় বেতনে', expects: 'চিকিৎসা/অসুস্থতা', rxOk: /চিকিৎস|অসুস্থ|hospital|হাসপাতাল|ডাক্তার|রোগ|মেডিকেল|medical|surgery|অপারেশন/i },
        5:  { name: 'সংগনিরোধ',       expects: 'কোয়ারেন্টিন',     rxOk: /কোয়ারেন্টিন|সংগনিরোধ|quarantine|isolation/i },
        6:  { name: 'প্রসূতি',         expects: 'মাতৃত্ব/প্রসব',     rxOk: /প্রসব|মাতৃত্ব|সন্তান\s*জন্ম|maternity|গর্ভ/i },
        19: { name: 'অক্ষমতাজনিত',    expects: 'পেশাগত আঘাত/অক্ষমতা', rxOk: /আঘাত|injury|পেশাগত\s*অসুস্থ|disability|অক্ষম/i }
    };
    if (type && STRICT[type] && isFirst) {
        const body = ($('#leaveApplication').val() || '').trim();
        if (body.length >= 8 && !STRICT[type].rxOk.test(body)) {
            // Try to find a better-fit type from body
            const altRec = (typeof suggestTypeFromBody === 'function') ? suggestTypeFromBody() : null;
            const altLine = altRec ? `সম্ভাব্য ভালো বিকল্প: <strong>${altRec.typeName}</strong>।` : '';
            return {
                kind: 'mismatch-warning',
                detect: `"${STRICT[type].name}" সাধারণত <strong>${STRICT[type].expects}</strong>-এর জন্য নেওয়া হয়, কিন্তু আবেদনের কারণে এমন কোনো উল্লেখ পাওয়া যায়নি।`,
                rule:   'BSR অনুযায়ী এই ধরনের ছুটি specific reason দাবি করে এবং অনুমোদনকারী Medical certificate / প্রমাণপত্র দাবি করতে পারেন। ' + altLine,
                applyTypeId: altRec ? altRec.typeId : null,
                applyTypeName: altRec ? altRec.typeName : null
            };
        }
    }

    if (!dFrom || !dTo || dTo < dFrom || !type) return null;
    const days = Math.floor((dTo - dFrom) / 86400000) + 1;

    // Full-pay (type 1) > 120 days → split into 120 full + remaining half-pay
    if (type === 1 && days > 120) {
        const splitDate = new Date(dFrom.getTime() + 120 * 86400000); // day 121
        const part1End  = new Date(dFrom.getTime() + 119 * 86400000); // last day of part 1
        return {
            kind: 'split',
            detect: `আপনি ${toBn(days)} দিন পূর্ণ গড় বেতনে ছুটি চাইছেন।`,
            rule:   'BSR অনুচ্ছেদ ১২-ক অনুযায়ী একটানা সর্বোচ্চ ১২০ দিন পূর্ণ গড় বেতনে অনুমোদন করা যাবে।',
            newSegments: [
                { type: 1, from: dFrom,     to: part1End, days: 120,        isNew: false, label: 'পূর্ণ গড় বেতনে' },
                { type: 2, from: splitDate, to: dTo,      days: days - 120, isNew: true,  label: 'অর্ধ-গড় বেতনে' }
            ]
        };
    }

    // Casual (type 8) > 10 days → trim to 10
    if (type === 8 && days > 10) {
        const trimEnd = new Date(dFrom.getTime() + 9 * 86400000);
        return {
            kind: 'trim',
            detect: `আপনি ${toBn(days)} দিন নৈমিত্তিক ছুটি চাইছেন।`,
            rule:   'BSR অনুযায়ী নৈমিত্তিক ছুটি একটানা সর্বোচ্চ ১০ দিন; এবং অন্য ধরনের সাথে এক আবেদনে মিশানো যাবে না।',
            newSegments: [
                { type: 8, from: dFrom, to: trimEnd, days: 10, isNew: false, label: 'নৈমিত্তিক' }
            ]
        };
    }

    return null;
}

// Update sparkle icon state on every segment based on detection
function updateSuggestionState() {
    $('.leave-segment').each(function() {
        const $seg = $(this);
        const suggestion = getSuggestionFor($seg);
        const $btn = $seg.find('.suggest-btn');
        if (suggestion) {
            $btn.addClass('has-suggestion').attr('title', 'AI সাজেশন উপলব্ধ — ক্লিক করুন');
            $seg.data('suggestion', suggestion);
        } else {
            $btn.removeClass('has-suggestion').attr('title', 'AI সাজেশন');
            $seg.removeData('suggestion');
        }
    });
}

// Open suggestion modal — called from sparkle icon click
var pendingSuggestion = null; // {segIdx, suggestion}
function openSuggestionModal(btn) {
    const $seg = $(btn).closest('.leave-segment');
    const suggestion = $seg.data('suggestion') || getSuggestionFor($seg);
    if (!suggestion) {
        // No active suggestion — show a friendly message
        $('#suggestDetect').text('এই মুহূর্তে কোনো সাজেশন নেই।');
        $('#suggestRule').text('তারিখ ও ছুটির ধরণ ঠিকভাবে নির্বাচন করার পর সাজেশন (যদি থাকে) এখানে দেখা যাবে।');
        $('#suggestPreview').html('<div class="text-muted small">— প্রস্তাবিত পরিবর্তন নেই —</div>');
        $('#applySuggestionBtn').hide();
        new bootstrap.Modal(document.getElementById('suggestModal')).show();
        return;
    }

    pendingSuggestion = { segIdx: $seg.attr('data-segment-idx'), suggestion };
    $('#suggestDetect').html('<i class="ti tabler-alert-circle me-1"></i>' + suggestion.detect);
    $('#suggestRule').html('<i class="ti tabler-info-circle me-1"></i>' + suggestion.rule);

    let html = '';
    if (suggestion.kind === 'recommend-type') {
        html = `<div class="preview-row is-new">
            <span class="preview-badge">প্রস্তাবিত</span>
            <div class="preview-text">ছুটির ধরণ: <strong>${suggestion.applyTypeName}</strong></div>
        </div>`;
    } else if (suggestion.kind === 'mismatch-warning') {
        if (suggestion.applyTypeName) {
            html = `<div class="preview-row is-new">
                <span class="preview-badge">প্রস্তাবিত পরিবর্তন</span>
                <div class="preview-text">ছুটির ধরণ: <strong>${suggestion.applyTypeName}</strong></div>
            </div>`;
        } else {
            html = `<div class="text-muted small">আবেদনের কারণ পরিবর্তন করুন অথবা সঠিক ছুটির ধরণ নির্বাচন করুন।</div>`;
        }
    } else if (suggestion.newSegments) {
        suggestion.newSegments.forEach((s, i) => {
            const cls  = s.isNew ? 'preview-row is-new' : 'preview-row';
            const lbl  = s.isNew ? 'নতুন' : 'পরিবর্তিত';
            html += `
                <div class="${cls}">
                    <span class="preview-badge">${lbl}</span>
                    <div class="preview-text">
                        ${toBn(i + 1)}. <strong>${s.label}</strong> —
                        ${toBn(fmtDmy(s.from))} → ${toBn(fmtDmy(s.to))}
                    </div>
                    <span class="preview-days">${toBn(s.days)} দিন</span>
                </div>`;
        });
    }
    $('#suggestPreview').html(html);
    $('#applySuggestionBtn').show();
    new bootstrap.Modal(document.getElementById('suggestModal')).show();
}

// Apply the pending suggestion — modify current segment + insert new ones
function applySuggestion() {
    if (!pendingSuggestion) return;
    const { segIdx, suggestion } = pendingSuggestion;
    const $seg = $(`.leave-segment[data-segment-idx="${segIdx}"]`);
    if (!$seg.length) return;

    if (suggestion.kind === 'recommend-type') {
        // Just set the leave type on this segment
        $seg.find('.segment-type').val(suggestion.applyTypeId).trigger('change');
    } else if (suggestion.kind === 'mismatch-warning') {
        // Apply alternative type if one was suggested
        if (suggestion.applyTypeId) {
            $seg.find('.segment-type').val(suggestion.applyTypeId).trigger('change');
        }
    } else if (suggestion.newSegments) {
        // First new segment overwrites the current segment
        const first = suggestion.newSegments[0];
        $seg.find('.segment-type').val(first.type);
        $seg.find('.segment-from').val(fmtDmy(first.from));
        $seg.find('.segment-to').val(fmtDmy(first.to));
        $seg.find('.segment-days').val(first.days);

        // Remaining new segments → add via addSegment(), then populate
        for (let i = 1; i < suggestion.newSegments.length; i++) {
            addSegment();
            const $newSeg = $('.leave-segment').last();
            const s = suggestion.newSegments[i];
            $newSeg.find('.segment-type').val(s.type);
            $newSeg.find('.segment-from').val(fmtDmy(s.from));
            $newSeg.find('.segment-to').val(fmtDmy(s.to));
            $newSeg.find('.segment-days').val(s.days);
        }
    }

    pendingSuggestion = null;
    bootstrap.Modal.getInstance(document.getElementById('suggestModal')).hide();
    recalcAll();
    showToast('সাজেশন প্রয়োগ করা হয়েছে');
}

// সরকারি চাকরি বিধিমালা অনুযায়ী যে যে ছুটিতে সংযুক্তি বাধ্যতামূলক
// (leaveID → কী সংযুক্ত করতে হবে তার hint)
var MANDATORY_ATTACHMENT_TYPES = {
    2:  { name: 'অর্ধ-গড় বেতনে',     doc: 'Medical certificate' },
    5:  { name: 'সংগনিরোধ',          doc: 'Quarantine আদেশ/সার্টিফিকেট' },
    6:  { name: 'প্রসূতি',            doc: 'Medical certificate (গর্ভাবস্থা/প্রসব)' },
    19: { name: 'অক্ষমতাজনিত',       doc: 'Medical Board সার্টিফিকেট' }
};

// Make attachment mandatory if any selected segment requires it per BSR
// Uses d-none class toggle (not jQuery show/hide) because Bootstrap d-block has !important
// that would otherwise override an inline `display:none`.
function updateAttachmentRequired() {
    const $hint = $('#leaveFileMandatoryHint');
    const $hintText = $('#leaveFileMandatoryText');
    const $req = $('#leaveFileRequired');
    const $file = $('#leaveFile');

    const selectedIds = $('.segment-type').toArray()
        .map(el => parseInt(el.value) || 0)
        .filter(id => MANDATORY_ATTACHMENT_TYPES.hasOwnProperty(id));

    if (selectedIds.length > 0) {
        const docs = selectedIds.map(id => {
            const m = MANDATORY_ATTACHMENT_TYPES[id];
            return m.name + ' → ' + m.doc;
        });
        const uniqueDocs = [...new Set(docs)];
        $hintText.html('নিম্নলিখিত ছুটির জন্য সংযুক্তি বাধ্যতামূলক:<br>' + uniqueDocs.join('<br>'));
        $hint.removeClass('d-none').addClass('d-block');
        // In edit mode, if an existing attachment is on file, skip the required flag
        const hasExistingAttachment = !!(window.EDIT_DATA && window.EDIT_DATA.attachment);
        if (hasExistingAttachment) {
            $file.removeAttr('required');
        } else {
            $file.attr('required', true);
        }
        $req.show();
    } else {
        $hint.removeClass('d-block').addClass('d-none');
        $file.removeAttr('required');
        $req.hide();
    }
}

// BSR validation across segments
function validateBsr(segs) {
    const $warning = $('#bsrWarning');
    const $warningText = $('#bsrWarningText');
    const $submit = $('#submit');
    let message = null;

    // CL = leaveID 8; Full-pay = leaveID 1
    const hasCl    = segs.some(s => s.type === 8);
    const hasNonCl = segs.some(s => s.type && s.type !== 8);

    if (hasCl && hasNonCl) {
        message = 'নৈমিত্তিক ছুটি অন্য ধরনের ছুটির সাথে এক আবেদনে মিশানো যাবে না (সরকারি চাকরি বিধিমালা)। নৈমিত্তিকের জন্য আলাদা আবেদন করুন।';
    } else {
        for (const s of segs) {
            if (s.type === 1 && s.days > 120) {
                message = `সরকারি চাকরি বিধিমালা (অনুচ্ছেদ ১২-ক) অনুযায়ী পূর্ণ গড় বেতনে একটানা সর্বোচ্চ ১২০ দিন। ধরন ${toBn(segs.indexOf(s)+1)}-এ ${toBn(s.days)} দিন চাইছেন।`;
                break;
            }
            if (s.type === 8 && s.days > 10) {
                message = `নৈমিত্তিক (Casual) একটানা সর্বোচ্চ ১০ দিন। ধরন ${toBn(segs.indexOf(s)+1)}-এ ${toBn(s.days)} দিন চাইছেন।`;
                break;
            }
        }
    }

    // Overlap check
    if (!message && segs.length > 1) {
        for (let i = 0; i < segs.length; i++) {
            for (let j = i + 1; j < segs.length; j++) {
                const a = segs[i], b = segs[j];
                if (a.from <= b.to && b.from <= a.to) {
                    message = `ধরন ${toBn(i+1)} ও ধরন ${toBn(j+1)}-এর তারিখ overlap করছে।`;
                    break;
                }
            }
            if (message) break;
        }
    }

    if (message) {
        $warningText.text(message);
        $warning.removeClass('alert-info').addClass('alert-danger').show();
        $submit.prop('disabled', true);
    } else {
        $warning.hide();
        $submit.prop('disabled', false);
    }
}

// Generate subject — locally, based on all segments
function generateSubjectFromSegments(segs, totalDays) {
    const applicationType = $('#applicationType').val();
    if (applicationType == 2) {
        $('#isinformed').show();
        $("input[name='isinformedValue']").prop('required', true);
    } else {
        $('#isinformed').hide();
        $("input[name='isinformedValue']").prop('required', false);
    }
    if (!applicationType || segs.length === 0 || totalDays === 0) {
        $("#subject").val('');
        return;
    }

    // Build "X দিনের <type1> + Y দিনের <type2>" description
    const parts = segs
        .filter(s => s.type && s.days > 0)
        .map(s => {
            const lt = LEAVE_TYPES.find(l => parseInt(l.leaveID) === s.type);
            const name = lt ? lt.leaveTitle : '';
            return toBn(s.days) + ' দিনের ' + name;
        });

    if (parts.length === 0) { $("#subject").val(''); return; }

    const desc = parts.join(' + ');
    let subject;
    if (applicationType == 1) {
        subject = desc + ' ছুটির আবেদন পত্র।';
    } else if (applicationType == 2) {
        subject = 'কর্মস্থলে যোগদান ও ' + toBn(totalDays) + ' দিন অফিসে অনুপস্থিতকালের জন্য ' + desc + ' ছুটির আবেদন পত্র।';
    } else {
        return;
    }
    $("#subject").val(subject);
}

// Backward-compat shim — old code paths may still call these
function calculateDays(){ recalcAll(); }
function generateSubject(){ recalcAll(); }
function checkBsrRules(){ recalcAll(); }

// ─────────────────────────────────────────────────────────────────────
// AI সাজেশন #2 — কারণের টেমপ্লেট (Reason Templates)
// ─────────────────────────────────────────────────────────────────────
// Curated reason templates → seed body text + suggest leave type.
// type IDs: 1=পূর্ণ গড়, 2=অর্ধ-গড় (medical), 5=সংগনিরোধ, 6=প্রসূতি,
//           7=ঐচ্ছিক, 8=নৈমিত্তিক, 9=বিনা বেতনে, 10=অসাধারণ, 19=অক্ষমতাজনিত
var REASON_TEMPLATES = [
    { title: 'চিকিৎসার জন্য', icon: 'tabler-stethoscope', color: '#dc2626', bg: '#fee2e2', typeId: 2, typeName: 'অর্ধ-গড় বেতনে',
      body: 'বিনীত নিবেদন এই যে, আমি আকস্মিক অসুস্থতায় ভুগছি। চিকিৎসকের পরামর্শ অনুযায়ী আমার বিশ্রাম ও চিকিৎসা প্রয়োজন। সংযুক্ত মেডিকেল সার্টিফিকেট অনুযায়ী আমাকে অর্ধ-গড় বেতনে ছুটি মঞ্জুর করা হোক।' },
    { title: 'পারিবারিক জরুরি প্রয়োজন', icon: 'tabler-home-heart', color: '#0891b2', bg: '#cffafe', typeId: 1, typeName: 'পূর্ণ গড় বেতনে',
      body: 'বিনীত নিবেদন এই যে, পারিবারিক জরুরি প্রয়োজনে আমাকে কয়েক দিনের জন্য কর্মস্থল ত্যাগ করতে হবে। অতএব, আমাকে পূর্ণ গড় বেতনে ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'সন্তানের পরীক্ষা', icon: 'tabler-school', color: '#059669', bg: '#d1fae5', typeId: 1, typeName: 'পূর্ণ গড় বেতনে',
      body: 'বিনীত নিবেদন এই যে, আমার সন্তানের আসন্ন পরীক্ষার প্রস্তুতির জন্য পারিবারিক উপস্থিতি অপরিহার্য। অতএব আমাকে পূর্ণ গড় বেতনে ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'হজ্ব / ওমরাহ পালন', icon: 'tabler-moon-stars', color: '#7c6ba4', bg: '#ede9fe', typeId: 7, typeName: 'ঐচ্ছিক',
      body: 'বিনীত নিবেদন এই যে, আমি পবিত্র হজ্ব / ওমরাহ পালনের উদ্দেশ্যে যেতে ইচ্ছুক। অতএব আমাকে ঐচ্ছিক ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'বিবাহ অনুষ্ঠান', icon: 'tabler-heart', color: '#db2777', bg: '#fce7f3', typeId: 1, typeName: 'পূর্ণ গড় বেতনে',
      body: 'বিনীত নিবেদন এই যে, আমার / আমার নিকটাত্মীয়ের বিবাহ অনুষ্ঠান উপলক্ষে আমাকে কর্মস্থলে অনুপস্থিত থাকতে হবে। অতএব আমাকে পূর্ণ গড় বেতনে ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'মাতৃত্বকালীন ছুটি', icon: 'tabler-baby-carriage', color: '#e11d48', bg: '#ffe4e6', typeId: 6, typeName: 'প্রসূতি',
      body: 'বিনীত নিবেদন এই যে, সংযুক্ত মেডিকেল সার্টিফিকেট অনুযায়ী আমার মাতৃত্বকালীন ছুটি প্রয়োজন। অতএব আমাকে প্রসূতি ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'তীর্থ ভ্রমণ', icon: 'tabler-mountain', color: '#ea580c', bg: '#ffedd5', typeId: 7, typeName: 'ঐচ্ছিক',
      body: 'বিনীত নিবেদন এই যে, ধর্মীয় তীর্থ ভ্রমণের উদ্দেশ্যে আমাকে কর্মস্থলে অনুপস্থিত থাকতে হবে। অতএব আমাকে ঐচ্ছিক ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'স্বল্পমেয়াদী ব্যক্তিগত কাজ', icon: 'tabler-clock', color: '#0284c7', bg: '#e0f2fe', typeId: 8, typeName: 'নৈমিত্তিক',
      body: 'বিনীত নিবেদন এই যে, ব্যক্তিগত প্রয়োজনে আমাকে স্বল্প সময়ের জন্য কর্মস্থলে অনুপস্থিত থাকতে হবে। অতএব আমাকে নৈমিত্তিক ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'সংগনিরোধ / কোয়ারেন্টিন', icon: 'tabler-virus', color: '#9333ea', bg: '#f3e8ff', typeId: 5, typeName: 'সংগনিরোধ',
      body: 'বিনীত নিবেদন এই যে, স্বাস্থ্য কর্তৃপক্ষ / চিকিৎসকের নির্দেশনা অনুযায়ী আমাকে সংগনিরোধে থাকতে হচ্ছে। অতএব সংযুক্ত আদেশ অনুযায়ী আমাকে সংগনিরোধ ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'অক্ষমতাজনিত (পেশাগত)', icon: 'tabler-bandage', color: '#a06262', bg: '#fecaca', typeId: 19, typeName: 'অক্ষমতাজনিত',
      body: 'বিনীত নিবেদন এই যে, কর্মস্থলে / পেশাগত দায়িত্ব পালনের সময় প্রাপ্ত আঘাতের কারণে আমি বর্তমানে কর্মে অক্ষম। সংযুক্ত মেডিকেল বোর্ড সার্টিফিকেট অনুযায়ী আমাকে অক্ষমতাজনিত ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'প্রবাসে যাত্রা', icon: 'tabler-plane', color: '#1d4ed8', bg: '#dbeafe', typeId: 9, typeName: 'বিনা বেতনে',
      body: 'বিনীত নিবেদন এই যে, ব্যক্তিগত / পারিবারিক প্রয়োজনে আমাকে দীর্ঘ সময়ের জন্য প্রবাসে যেতে হবে। অতএব আমাকে বিনা বেতনে ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' },
    { title: 'অন্য কারণ (কাস্টম)', icon: 'tabler-edit', color: '#6b7280', bg: '#f3f4f6', typeId: 0, typeName: '— আপনি নির্বাচন করুন —',
      body: 'বিনীত নিবেদন এই যে, [এখানে আপনার কারণ লিখুন]। অতএব আমাকে ছুটি মঞ্জুর করার বিনীত আবেদন জানাচ্ছি।' }
];

function openReasonTemplates() {
    const $grid = $('#reasonTplGrid');
    $grid.empty();
    REASON_TEMPLATES.forEach((t, i) => {
        $grid.append(`
            <div class="col-md-6 col-lg-4">
                <div class="reason-card" data-tpl-idx="${i}" onclick="applyReasonTemplate(${i})">
                    <span class="rc-icon" style="--rc-bg:${t.bg};--rc-color:${t.color};">
                        <i class="ti ${t.icon}"></i>
                    </span>
                    <div class="rc-title">${t.title}</div>
                    <span class="rc-type">${t.typeName}</span>
                </div>
            </div>`);
    });
    new bootstrap.Modal(document.getElementById('reasonTplModal')).show();
}

function applyReasonTemplate(idx) {
    const t = REASON_TEMPLATES[idx];
    if (!t) return;
    $('#leaveApplication').val(t.body);
    // Set first segment's leave type if currently empty
    if (t.typeId > 0) {
        const $firstType = $('.leave-segment').first().find('.segment-type');
        if (!$firstType.val()) {
            $firstType.val(t.typeId).trigger('change');
        }
    }
    bootstrap.Modal.getInstance(document.getElementById('reasonTplModal')).hide();
    showToast('টেমপ্লেট প্রয়োগ করা হয়েছে — প্রয়োজনে সম্পাদনা করুন');
}

// ─────────────────────────────────────────────────────────────────────
// AI সাজেশন #3 — Leave Type Recommendation (keyword match from body)
// ─────────────────────────────────────────────────────────────────────
// Maps keyword regex → leaveID. First match wins.
var TYPE_KEYWORDS = [
    { rx: /চিকিৎস|অসুস্থ|hospital|হাসপাতাল|ডাক্তার|রোগ|মেডিকেল|medical|surgery|অপারেশন/i, typeId: 2, typeName: 'অর্ধ-গড় বেতনে' },
    { rx: /প্রসব|মাতৃত্ব|সন্তান\s*জন্ম|maternity|গর্ভ/i,                                          typeId: 6, typeName: 'প্রসূতি' },
    { rx: /কোয়ারেন্টিন|সংগনিরোধ|quarantine|isolation/i,                                          typeId: 5, typeName: 'সংগনিরোধ' },
    { rx: /হজ্ব|হজ্জ|ওমরাহ|তীর্থ|hajj|pilgrim/i,                                                typeId: 7, typeName: 'ঐচ্ছিক' },
    { rx: /আঘাত|injury|পেশাগত\s*অসুস্থ|disability|অক্ষম/i,                                       typeId: 19, typeName: 'অক্ষমতাজনিত' },
    { rx: /প্রবাস|বিদেশ|abroad|overseas/i,                                                       typeId: 9, typeName: 'বিনা বেতনে' },
    { rx: /জরুরি|জরুরী|urgent|emergency|পারিবারিক|বিবাহ|পরীক্ষা|family|wedding|exam|ব্যক্তিগত\s*কাজ/i, typeId: 1, typeName: 'পূর্ণ গড় বেতনে' }
];

function suggestTypeFromBody() {
    const body = ($('#leaveApplication').val() || '').trim();
    if (body.length < 8) return null;
    for (const k of TYPE_KEYWORDS) {
        if (k.rx.test(body)) return { typeId: k.typeId, typeName: k.typeName };
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────
// AI সাজেশন #4 — Balance Optimizer
// ─────────────────────────────────────────────────────────────────────
var USER_BALANCE = <?php echo json_encode($_aiBalance, JSON_UNESCAPED_UNICODE); ?>;

// Map segment type → balance bucket
function balanceBucket(typeId) {
    if (typeId === 1)  return 'fullAvgAvailable';
    if (typeId === 2)  return 'halfAvg';
    if (typeId === 7)  return 'optional';
    if (typeId === 8)  return 'casual';
    return null;
}

function openBalanceOptimizer() {
    const $body = $('#balanceOptBody');
    const $apply = $('#applyBalanceOptBtn');
    $apply.hide();

    // Collect current segments
    const segs = [];
    $('.leave-segment').each(function() {
        const s = recalcSegment($(this));
        if (s) segs.push(s);
    });

    // Render balance bars — each bar shows used (red) + remaining (green)
    // Bar width is relative to the largest "earned/allowed" across all buckets
    const det = USER_BALANCE.detail;
    const fullAvgTotalEarned = det.fullAvg.earned;
    const maxEarned = Math.max(fullAvgTotalEarned, det.halfAvg.earned, det.casual.earned, det.optional.earned, 1);

    const balRow = (label, earned, used, available, isLow) => {
        const totalPct = Math.max(2, Math.round((earned / maxEarned) * 100));
        const usedPct = earned > 0 ? Math.round((used / earned) * 100) : 0;
        const availPct = 100 - usedPct;
        const usedTip  = `ব্যবহৃত: ${toBn(used)} দিন`;
        const availTip = `অবশিষ্ট: ${toBn(available)} দিন`;
        return `<div class="bal-row ${isLow ? 'is-low' : ''}">
            <div class="bl-label">${label}</div>
            <div class="bl-bar" style="width:${totalPct}%;">
                <div class="bl-bar-used"  style="width:${usedPct}%;"  data-tip="${usedTip}"></div>
                <div class="bl-bar-avail" style="width:${availPct}%;" data-tip="${availTip}"></div>
            </div>
            <div class="bl-num">${toBn(available)} / ${toBn(earned)} দিন</div>
        </div>`;
    };

    let html = '<div class="text-muted small mb-2">আপনার বর্তমান ব্যালেন্স <span class="text-muted" style="font-size:0.78rem;">(<span style="color:#c97777;">■</span> ব্যবহৃত · <span style="color:#5fa885;">■</span> অবশিষ্ট)</span>:</div>';
    // পূর্ণ গড়: split-display showing both available (capped) and reserve
    html += balRow('পূর্ণ গড় বেতনে', det.fullAvg.earned, det.fullAvg.used, det.fullAvg.balance, USER_BALANCE.fullAvgAvailable < 5);
    if (USER_BALANCE.fullAvgReserve > 0) {
        html += `<div class="bal-row" style="background:#fef9c3;">
            <div class="bl-label" style="color:#9c8055;">↳ রিজার্ভ (encashable)</div>
            <div class="bl-bar" style="background:transparent;"></div>
            <div class="bl-num" style="color:#9c8055;">${toBn(USER_BALANCE.fullAvgReserve)} দিন</div>
        </div>`;
    }
    html += balRow('অর্ধ-গড় বেতনে', det.halfAvg.earned,  det.halfAvg.used,  det.halfAvg.balance,  det.halfAvg.balance < 5);
    html += balRow('নৈমিত্তিক',      det.casual.earned,   det.casual.used,   det.casual.balance,   det.casual.balance < 1);
    html += balRow('ঐচ্ছিক',         det.optional.earned, det.optional.used, det.optional.balance, det.optional.balance < 1);

    // Analyse current allocation
    const issues = [];
    const recommendations = [];
    let plannedNew = null; // alternate plan to apply

    // Sum requested per type
    const reqByType = {};
    segs.forEach(s => {
        if (!s.type) return;
        reqByType[s.type] = (reqByType[s.type] || 0) + s.days;
    });

    // Check overflow per type
    Object.keys(reqByType).forEach(tid => {
        const t = parseInt(tid);
        const req = reqByType[tid];
        const bucket = balanceBucket(t);
        if (!bucket) return;
        const avail = USER_BALANCE[bucket] || 0;
        if (req > avail) {
            const tn = (t === 1 ? 'পূর্ণ গড়' : t === 2 ? 'অর্ধ-গড়' : t === 7 ? 'ঐচ্ছিক' : t === 8 ? 'নৈমিত্তিক' : '');
            issues.push(`${tn}-এ ${toBn(req)} দিন চাইছেন, কিন্তু ব্যালেন্স মাত্র ${toBn(avail)} দিন।`);

            // Recommend: spill over to half-pay if full-pay overflows
            if (t === 1 && USER_BALANCE.halfAvg >= (req - avail)) {
                recommendations.push(`${toBn(avail)} দিন পূর্ণ গড় + ${toBn(req - avail)} দিন অর্ধ-গড় হিসেবে নিন।`);
            } else if (t === 1) {
                recommendations.push(`শুধু ${toBn(avail)} দিন পূর্ণ গড় নিন; বাকিটা পরে বা বিনা বেতনে।`);
            }
        }
    });

    // Smart suggestion: if user has only one full-pay segment within balance, but lots of full-pay reserve
    // suggest taking max from "available" bucket
    if (segs.length === 1 && segs[0].type === 1 && segs[0].days <= USER_BALANCE.fullAvgAvailable) {
        // Already optimal
    }

    // Build analysis box
    if (issues.length > 0) {
        html += `<div class="bal-summary"><i class="ti tabler-alert-triangle me-1"></i><strong>সমস্যা detect হয়েছে:</strong><br>${issues.join('<br>')}</div>`;
    } else if (segs.length === 0) {
        html += `<div class="bal-summary"><i class="ti tabler-info-circle me-1"></i>প্রথমে segment-এ ছুটির ধরণ ও তারিখ পূরণ করুন। তারপর এখানে optimal allocation দেখানো হবে।</div>`;
    } else {
        html += `<div class="bal-summary ok"><i class="ti tabler-circle-check me-1"></i>আপনার বর্তমান allocation ঠিক আছে — সব segment ব্যালেন্সের মধ্যে।</div>`;
    }

    // Recommendation box
    if (recommendations.length > 0) {
        html += `<div class="bal-recommend">
            <div class="br-title"><i class="ti tabler-bulb"></i> সাজেশন</div>
            ${recommendations.map(r => `<div class="br-row">${r}</div>`).join('')}
        </div>`;
    }

    $body.html(html);
    new bootstrap.Modal(document.getElementById('balanceOptModal')).show();
}

// Toast helper
function showToast(msg, isError) {
    const cls = isError ? 'alert-danger' : 'alert-success';
    const icon = isError ? 'ti-alert-circle' : 'ti-check';
    const $toast = $(`<div class="alert ${cls} py-2 px-3 mb-0" style="position:fixed;top:80px;right:20px;z-index:1080;box-shadow:0 4px 12px rgba(0,0,0,0.15);"><i class="ti ${icon} me-1"></i>${msg}</div>`);
    $('body').append($toast);
    setTimeout(() => $toast.fadeOut(300, () => $toast.remove()), 2400);
}

// Make segment functions globally callable from inline onclick attributes,
// even after a Turbo Frame swap re-injects this script.
window.addSegment           = addSegment;
window.removeSegment        = removeSegment;
window.recalcAll            = recalcAll;
window.bindSegmentHandlers  = bindSegmentHandlers;
window.updateRemoveButtons  = updateRemoveButtons;
window.updateAttachmentRequired = updateAttachmentRequired;
window.calculateDays        = calculateDays;
window.generateSubject      = generateSubject;
window.checkBsrRules        = checkBsrRules;
window.openSuggestionModal  = openSuggestionModal;
window.applySuggestion      = applySuggestion;
window.updateSuggestionState = updateSuggestionState;
window.openReasonTemplates  = openReasonTemplates;
window.applyReasonTemplate  = applyReasonTemplate;
window.openBalanceOptimizer = openBalanceOptimizer;
window.suggestTypeFromBody  = suggestTypeFromBody;
window.showToast            = showToast;

// Wire Apply button (delegated so it survives Turbo Frame swaps)
$(document).off('click.suggest', '#applySuggestionBtn').on('click.suggest', '#applySuggestionBtn', function(){
    applySuggestion();
});

// Refresh suggestion state when user edits body (for type recommendation)
$(document).off('input.suggestBody', '#leaveApplication').on('input.suggestBody', '#leaveApplication', function(){
    if (typeof updateSuggestionState === 'function') updateSuggestionState();
});

// ── Edit mode prefill data (server-injected) ──
window.EDIT_DATA = <?= $editMode ? json_encode([
    'editID'         => (int)$editApp['dataID'],
    'applicationType'=> (int)$editApp['applicationType'],
    'applicationTo'  => (int)$editApp['applicationTo'],
    'isinformed'     => (int)$editApp['isinformed'],
    'onbehalf'       => (int)$editApp['onbehalf'],
    'subject'        => $editApp['subject'] ?? '',
    'leaveApplication' => $editApp['leaveApplication'] ?? '',
    'attachment'     => $editApp['attachment'] ?? '',
    'supervisorID'   => $editSupervisorID,
    'segments'       => array_map(function($s) {
        return [
            'leaveType' => (int)$s['leaveType'],
            'dateFrom'  => date('d/m/Y', strtotime($s['dateFrom'])),
            'dateTo'    => date('d/m/Y', strtotime($s['dateTo'])),
            'days'      => (int)$s['days'],
        ];
    }, $editSegments),
], JSON_UNESCAPED_UNICODE) : 'null'; ?>;

function applyEditPrefill() {
    if (!window.EDIT_DATA) return;
    var d = window.EDIT_DATA;
    // Scalar fields
    if (d.applicationType) jQuery('#applicationType').val(d.applicationType).trigger('change');
    if (d.applicationTo)   jQuery('select[name="applicationTo"]').val(d.applicationTo);
    if (d.onbehalf !== undefined) jQuery('select[name="onbehalf"]').val(d.onbehalf);
    if (d.applicationType == 2) {
        jQuery('input[name="isinformedValue"][value="' + d.isinformed + '"]').prop('checked', true);
    }
    if (d.leaveApplication) jQuery('#leaveApplication').val(d.leaveApplication);
    if (d.subject) jQuery('#subject').val(d.subject);

    // Supervisor (Select2)
    if (d.supervisorID) {
        var $sup = jQuery('#supervisorID');
        if ($sup.length) {
            $sup.val(d.supervisorID);
            if ($sup.hasClass('select2-hidden-accessible')) $sup.trigger('change');
        }
    }

    // Segments — first one already exists; fill it, then add the rest
    if (Array.isArray(d.segments) && d.segments.length > 0) {
        // Add additional segment rows if needed
        for (var i = 1; i < d.segments.length; i++) {
            if (typeof window.addSegment === 'function') window.addSegment();
        }
        jQuery('.leave-segment').each(function(idx) {
            var s = d.segments[idx]; if (!s) return;
            jQuery(this).find('.segment-type').val(s.leaveType);
            jQuery(this).find('.segment-from').val(s.dateFrom);
            jQuery(this).find('.segment-to').val(s.dateTo);
            jQuery(this).find('.segment-days').val(s.days);
        });
    }

    // Attachment indicator
    if (d.attachment) {
        jQuery('#leaveFile').after('<small class="text-muted d-block mt-1"><i class="ti tabler-paperclip me-1"></i>বর্তমান সংযুক্তি: ' + d.attachment + ' (নতুন ফাইল না দিলে এটাই থাকবে)</small>');
    }

    // Trigger recalc to update totals + validation
    if (typeof window.recalcAll === 'function') window.recalcAll();
}

// Initialization — runs on first DOM ready AND on every Turbo Frame swap that brings this view back
function initLeaveForm() {
    if (typeof jQuery === 'undefined') return;
    var $segs = jQuery('.leave-segment');
    if ($segs.length === 0) return; // not on this page
    $segs.each(function() { window.bindSegmentHandlers(jQuery(this)); });
    window.updateRemoveButtons();
    window.updateAttachmentRequired();
    // Edit prefill — tie state to editID so a fresh edit page (different ID) re-prefills
    if (window.EDIT_DATA && window.EDIT_DATA.editID
        && window._lastPrefillEditID !== window.EDIT_DATA.editID) {
        window._lastPrefillEditID = window.EDIT_DATA.editID;
        applyEditPrefill();
    }
}

// Call now (script may run after $(document).ready already fired due to Turbo Frame)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLeaveForm);
} else {
    initLeaveForm();
}
// Also respond to Turbo's lifecycle
document.addEventListener('turbo:load', initLeaveForm);
document.addEventListener('turbo:frame-load', initLeaveForm);

// Form submission with validation and confirmation
$(document).ready(function() {
    var form = $('#form');
    var submit = $('#submit');

    form.on('submit', function(e) {
        e.preventDefault();

        // Form validation
        if (!validateForm()) {
            return false;
        }

        // SweetAlert2 confirmation dialog
        Swal.fire({
            title: 'আপনি কি নিশ্চিত?',
            text: "আবেদনটি প্রেরণ করতে চান?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6c5ce7',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, প্রেরণ করুন',
            cancelButtonText: 'বাতিল',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (result.isConfirmed) {
                // AJAX request to submit leave application
                $.ajax({
                    url: <?= $editMode ? "'../../api/leave/update-application.php'" : "'../../api/leave/insert-application.php'" ?>,
                    type: 'POST',
                    dataType: 'html',
                    data: new FormData(form[0]),
                    contentType: false,
                    cache: false,
                    processData: false,
                    beforeSend: function() {
                        submit.attr("disabled", "disabled");
                        submit.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>প্রক্রিয়াকরণ হচ্ছে...');
                    },
                    success: function(data) {
                        var trimmed = (data || '').toString().trim();

                        // Detect PHP error / warning / notice in response — these come back
                        // as plain text from the API when something explodes server-side.
                        var phpErrorRx = /(Fatal error|Parse error|Call to undefined|Uncaught\s+\w*Error|Warning:|Notice:|<b>(?:Fatal error|Parse error|Warning|Notice)<\/b>)/i;
                        var hasPhpError = phpErrorRx.test(trimmed);

                        if (data == 0 || hasPhpError) {
                            // Strip HTML tags to extract a clean error message
                            var errMsg = hasPhpError
                                ? trimmed.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 400)
                                : 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।';
                            Swal.fire({
                                title: 'ত্রুটি',
                                text: errMsg,
                                icon: 'error',
                                confirmButtonColor: '#ff3e1d',
                                customClass: {
                                    confirmButton: 'btn btn-danger'
                                },
                                buttonsStyling: false
                            });
                        } else {
                            var isEdit = !!(window.EDIT_DATA && window.EDIT_DATA.editID);
                            if (!isEdit) {
                                form.trigger('reset');
                                $('#formresult').html(data);
                            }

                            Swal.fire({
                                title: 'সম্পন্ন',
                                text: isEdit
                                    ? 'আবেদনটি সফলভাবে সম্পাদনা করা হয়েছে'
                                    : 'আবেদনটি সফলভাবে প্রেরণ করা হয়েছে',
                                icon: 'success',
                                confirmButtonColor: '#6c5ce7',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false
                            }).then(function() {
                                if (isEdit) {
                                    // Go back to the application list
                                    window.location.href = '../../views/leave/all-applications.php?menuslug=all-leave-application';
                                }
                            });
                        }
                        submit.removeAttr("disabled");
                        submit.html('<i class="ti tabler-send me-1"></i>প্রেরণ করুন');
                    },
                    error: function(e) {
                        console.log(e);

                        Swal.fire({
                            title: 'ত্রুটি',
                            text: 'আবেদন প্রেরণ করতে ব্যর্থ হয়েছে',
                            icon: 'error',
                            confirmButtonColor: '#ff3e1d',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            },
                            buttonsStyling: false
                        });

                        submit.removeAttr("disabled");
                        submit.html('<i class="ti tabler-send me-1"></i>প্রেরণ করুন');
                    }
                });
            }
        });
    });
});

// Form validation function
function validateForm() {
    var isValid = true;
    $('#form').find('[required]').each(function() {
        var val = $(this).val();
        // For file inputs, check the files array directly
        if ($(this).attr('type') === 'file') {
            if (!this.files || this.files.length === 0) {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        } else if (val === '' || val === null) {
            isValid = false;
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    if(!isValid){
        Swal.fire({
            title: 'সতর্কতা',
            text: 'অনুগ্রহ করে সকল প্রয়োজনীয় তথ্য পূরণ করুন',
            icon: 'warning',
            confirmButtonColor: '#6c5ce7',
            customClass: {
                confirmButton: 'btn btn-primary'
            },
            buttonsStyling: false
        });
    }

    return isValid;
}
</script>
