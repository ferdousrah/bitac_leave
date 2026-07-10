<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// ─── Resolve leave application ─────────────────────────────────────────────
$leaveApplicationID = (int)($_GET['leaveApplicationID'] ?? 0);
if ($leaveApplicationID <= 0) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>অবৈধ আবেদন আইডি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$appStmt = mysqli_prepare($con, "SELECT * FROM leave_applications WHERE dataID = ? LIMIT 1");
mysqli_stmt_bind_param($appStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($appStmt);
$leaveApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
mysqli_stmt_close($appStmt);

if (!$leaveApp) {
    echo '<div class="alert alert-danger m-4"><i class="ti tabler-alert-circle me-2"></i>আবেদন খুঁজে পাওয়া যায়নি</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

if ((int)$leaveApp['status'] !== 1) {
    echo '<div class="alert alert-warning m-4"><i class="ti tabler-alert-triangle me-2"></i>শুধু অনুমোদিত ছুটি সংশোধন করা যাবে</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Block if a pending edit request already exists for this application
$pendStmt = mysqli_prepare($con, "SELECT dataID FROM leave_edit_data WHERE leaveApplicationID = ? AND status = 0 LIMIT 1");
mysqli_stmt_bind_param($pendStmt, 'i', $leaveApplicationID);
mysqli_stmt_execute($pendStmt);
$pendRow = mysqli_fetch_assoc(mysqli_stmt_get_result($pendStmt));
mysqli_stmt_close($pendStmt);
if ($pendRow) {
    echo '<div class="alert alert-info m-4"><i class="ti tabler-info-circle me-2"></i>এই আবেদনের জন্য একটি সংশোধন প্রস্তাব অনুমোদনের অপেক্ষায় রয়েছে (#' . (int)$pendRow['dataID'] . ')</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Applicant info
$applicantID = (int)$leaveApp['applicantID'];
$empStmt = mysqli_prepare($con,
    "SELECT el.id, el.employee_id, el.employee_name, el.organization_id, el.designation,
            jt.job_title_name, org.organization_name
     FROM employee_list el
     LEFT JOIN job_title jt ON el.designation = jt.id
     LEFT JOIN organization org ON el.organization_id = org.id
     WHERE el.id = ? LIMIT 1");
mysqli_stmt_bind_param($empStmt, 'i', $applicantID);
mysqli_stmt_execute($empStmt);
$applicant = mysqli_fetch_assoc(mysqli_stmt_get_result($empStmt));
mysqli_stmt_close($empStmt);

// Original approved segments — prefer kind='proposed', fallback to 'requested', else build synthetic
$origSegments = [];
$segQ = mysqli_query($con,
    "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
     LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
     WHERE s.applicationID = $leaveApplicationID AND s.kind = 'proposed'
     ORDER BY s.serial ASC, s.dataID ASC");
if ($segQ && mysqli_num_rows($segQ) > 0) {
    while ($r = mysqli_fetch_assoc($segQ)) $origSegments[] = $r;
} else {
    // Fallback to requested
    $segQ2 = mysqli_query($con,
        "SELECT s.*, lt.leaveTitle FROM leave_application_segments s
         LEFT JOIN leave_types lt ON s.leaveType = lt.leaveID
         WHERE s.applicationID = $leaveApplicationID AND s.kind = 'requested'
         ORDER BY s.serial ASC, s.dataID ASC");
    if ($segQ2 && mysqli_num_rows($segQ2) > 0) {
        while ($r = mysqli_fetch_assoc($segQ2)) $origSegments[] = $r;
    } else if (!empty($leaveApp['approvedDateFrom'])) {
        // Legacy single-date application
        $ltQ = mysqli_query($con, "SELECT leaveTitle FROM leave_types WHERE leaveID = " . (int)$leaveApp['approvedLeaveType']);
        $ltTitle = $ltQ ? (mysqli_fetch_assoc($ltQ)['leaveTitle'] ?? '') : '';
        $origSegments[] = [
            'leaveType'  => (int)$leaveApp['approvedLeaveType'],
            'leaveTitle' => $ltTitle,
            'dateFrom'   => $leaveApp['approvedDateFrom'],
            'dateTo'     => $leaveApp['approvedDateTo'],
            'days'       => (int)$leaveApp['approvedDays'],
        ];
    }
}

// Leave types for dropdown
$leaveTypes = [];
$ltQuery = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types WHERE leaveID != 22 ORDER BY leaveTitle ASC");
while ($r = mysqli_fetch_assoc($ltQuery)) $leaveTypes[] = $r;

function be_num($n) {
    $map = ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯'];
    return strtr((string)$n, $map);
}
?>

<style>
.edit-wrap { max-width: 1100px; }
.edit-card { border-radius: 0.75rem; }
.edit-card .card-body { padding: 1.1rem 1.5rem; }

.section-hdr {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; margin: 14px 0 12px;
    background: #fafbfd; border: 1px solid #eef0f5;
    border-left: 3px solid var(--sec-accent, #6c5ce7);
    border-radius: 0.5rem;
}
.section-hdr[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.section-hdr[data-color="amber"]  { --sec-bg: #fff3e1; --sec-accent: #b8651a; }
.section-hdr[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }
.section-hdr .section-num {
    width: 26px; height: 26px; border-radius: 0.4rem;
    background: var(--sec-bg); color: var(--sec-accent);
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.82rem; flex-shrink: 0;
}
.section-hdr .section-title { font-size: 0.92rem; font-weight: 600; color: #2c2e3a; margin: 0; }
.section-hdr .section-sub { font-size: 0.72rem; color: #8a90a6; line-height: 1.3; }
.section-hdr .section-icon {
    width: 32px; height: 32px; border-radius: 0.5rem;
    background: var(--sec-bg); color: var(--sec-accent);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.section-hdr .section-text { flex: 1; min-width: 0; }
.section-hdr:first-of-type { margin-top: 0; }

.applicant-card {
    background: linear-gradient(135deg, #f8f7ff 0%, #fefefe 100%);
    border: 1px solid #ddd5f6;
    border-radius: 0.6rem;
    padding: 14px 18px;
    margin-bottom: 14px;
}
.applicant-card .ap-name { font-weight: 700; font-size: 1rem; color: #2c2e3a; }
.applicant-card .ap-meta { font-size: 0.82rem; color: #5d6580; margin-top: 4px; }
.applicant-card .ap-app-no {
    background: #6c5ce7; color: #fff;
    padding: 4px 10px; border-radius: 0.3rem;
    font-size: 0.78rem; font-weight: 600;
}

.orig-seg-list { background: #fffaf0; border: 1px solid #fde0a8; border-radius: 0.5rem; padding: 10px 14px; }
.orig-seg-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-bottom: 1px dashed #fde0a8; font-size: 0.86rem; }
.orig-seg-row:last-child { border-bottom: none; }
.orig-seg-row .seg-badge {
    background: #b8651a; color: #fff;
    font-size: 0.7rem; padding: 2px 8px; border-radius: 0.3rem;
    font-weight: 600; min-width: 50px; text-align: center;
}
.orig-seg-row .seg-text { flex: 1; color: #5d3f1c; }
.orig-seg-row .seg-days { color: #b8651a; font-weight: 700; font-size: 0.84rem; }

.edit-segment {
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-radius: 0.5rem;
    padding: 10px 12px;
    margin-bottom: 8px;
}
.edit-segment:hover { border-color: #ddd5f6; background: #fdfcff; }
.edit-segment .segment-badge {
    background: #f0edff; color: #5648c4;
    font-weight: 600; font-size: 0.74rem;
    padding: 0.25em 0.6em; border-radius: 0.35rem;
}
.edit-segment .form-label.small {
    font-size: 0.72rem; font-weight: 500; color: #5d6580; margin-bottom: 0.2rem;
}

#totalEditDaysDisplay { color: #5648c4; font-weight: 700; }

.form-login .col-form-label { font-size: 0.85rem; color: #3a3d53; font-weight: 500; }
.edit-card .col-form-label { padding-top: 0.3rem !important; padding-bottom: 0.3rem !important; font-size: 0.82rem !important; }
.edit-card .form-control, .edit-card .form-select {
    padding: 0.3rem 0.65rem !important; font-size: 0.86rem !important;
    min-height: 32px !important; height: 32px !important;
}
.edit-card textarea.form-control { height: auto !important; min-height: auto !important; line-height: 1.4; }
.edit-card .select2-container--bootstrap-5 .select2-selection { min-height: 32px !important; padding: 0.25rem 0.65rem !important; }
.edit-card .select2-container--bootstrap-5 .select2-selection__rendered { line-height: 1.4 !important; font-size: 0.86rem !important; }
</style>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0">
            <i class="ti tabler-pencil me-2 text-primary"></i>ছুটি সংশোধন প্রস্তাব
        </h4>
        <div class="text-muted small mt-1 ms-1">
            <i class="ti tabler-info-circle me-1"></i>অনুমোদিত ছুটিতে সংশোধন প্রস্তাব করুন — অনুমোদন চেইনের মাধ্যমে চূড়ান্ত হবে
        </div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<div class="edit-wrap">
<div class="card edit-card shadow-sm border-0">
    <div class="card-body">

        <!-- Applicant context card -->
        <div class="applicant-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <div class="ap-name"><?= htmlspecialchars($applicant['employee_name'] ?? '') ?></div>
                    <div class="ap-meta">
                        <i class="ti tabler-id-badge-2 me-1"></i><?= be_num(htmlspecialchars($applicant['employee_id'] ?? '')) ?>
                        <span class="mx-2">•</span>
                        <i class="ti tabler-briefcase me-1"></i><?= htmlspecialchars($applicant['job_title_name'] ?? '—') ?>
                        <span class="mx-2">•</span>
                        <i class="ti tabler-building me-1"></i><?= htmlspecialchars($applicant['organization_name'] ?? '—') ?>
                    </div>
                </div>
                <div>
                    <span class="ap-app-no">আবেদন #<?= be_num($leaveApplicationID) ?></span>
                </div>
            </div>
        </div>

        <!-- Section 1: Current approved leave (read-only) -->
        <div class="section-hdr" data-color="amber">
            <div class="section-num">১</div>
            <div class="section-text">
                <h6 class="section-title">বর্তমান অনুমোদিত ছুটি</h6>
                <span class="section-sub">যা আপনি সংশোধন করতে যাচ্ছেন</span>
            </div>
            <span class="section-icon"><i class="ti tabler-clipboard-check"></i></span>
        </div>

        <div class="orig-seg-list mb-3">
            <?php if (empty($origSegments)): ?>
                <div class="text-muted small">কোনো অনুমোদিত ছুটির অংশ পাওয়া যায়নি</div>
            <?php else:
                $origTotal = 0;
                foreach ($origSegments as $i => $sg):
                    $origTotal += (int)$sg['days'];
            ?>
                <div class="orig-seg-row">
                    <span class="seg-badge">অংশ <?= be_num($i + 1) ?></span>
                    <span class="seg-text">
                        <strong><?= htmlspecialchars($sg['leaveTitle'] ?? '—') ?></strong> —
                        <?= be_num(date('d/m/Y', strtotime($sg['dateFrom']))) ?> হইতে
                        <?= be_num(date('d/m/Y', strtotime($sg['dateTo']))) ?>
                    </span>
                    <span class="seg-days"><?= be_num($sg['days']) ?> দিন</span>
                </div>
            <?php endforeach; ?>
                <div class="orig-seg-row" style="background:rgba(255,255,255,0.5); margin: 4px -14px -10px; padding: 8px 14px; border-radius: 0 0 0.5rem 0.5rem; border-top: 1px solid #fde0a8;">
                    <span class="seg-badge" style="background:#8b6f47;">মোট</span>
                    <span class="seg-text">&nbsp;</span>
                    <span class="seg-days"><?= be_num($origTotal) ?> দিন</span>
                </div>
            <?php endif; ?>
        </div>

        <form id="editForm" enctype="multipart/form-data">
            <input type="hidden" name="leaveApplicationID" value="<?= $leaveApplicationID ?>" />

            <!-- Section 2: Proposed segments (editable) -->
            <div class="section-hdr" data-color="green">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">প্রস্তাবিত সংশোধন</h6>
                    <span class="section-sub">নতুন ছুটির বিবরণ — একাধিক ধরন যোগ করা যাবে</span>
                </div>
                <span class="section-icon"><i class="ti tabler-edit"></i></span>
            </div>

            <div id="editSegments">
                <?php
                // Prefill with original segments so admin can edit
                if (empty($origSegments)) {
                    $origSegments = [['leaveType' => '', 'dateFrom' => '', 'dateTo' => '', 'days' => '']];
                }
                foreach ($origSegments as $i => $sg):
                    $fromDisplay = !empty($sg['dateFrom']) ? date('d/m/Y', strtotime($sg['dateFrom'])) : '';
                    $toDisplay   = !empty($sg['dateTo'])   ? date('d/m/Y', strtotime($sg['dateTo']))   : '';
                ?>
                <div class="edit-segment" data-segment-idx="<?= $i ?>">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge segment-badge">প্রস্তাব ধরন <?= be_num($i + 1) ?></span>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-edit-seg" onclick="removeEditSeg(this)" style="<?= $i === 0 ? 'display:none;' : '' ?>">
                            <i class="ti tabler-x"></i> বাদ
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small">ছুটির ধরণ <span class="text-danger">*</span></label>
                            <select class="form-select edit-seg-type" name="segment_leaveType[]" required>
                                <option value="">-- নির্বাচন করুন --</option>
                                <?php foreach ($leaveTypes as $lt): ?>
                                    <option value="<?= $lt['leaveID'] ?>" <?= ((int)$sg['leaveType'] === (int)$lt['leaveID']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lt['leaveTitle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">শুরু <span class="text-danger">*</span></label>
                            <input type="text" class="form-control edit-seg-from" name="segment_dateFrom[]" placeholder="dd/mm/yyyy" required autocomplete="off" readonly value="<?= htmlspecialchars($fromDisplay) ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">শেষ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control edit-seg-to" name="segment_dateTo[]" placeholder="dd/mm/yyyy" required autocomplete="off" readonly value="<?= htmlspecialchars($toDisplay) ?>" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">দিন</label>
                            <input type="text" class="form-control edit-seg-days" name="segment_days[]" placeholder="—" readonly value="<?= htmlspecialchars($sg['days'] ?? '') ?>" />
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row mb-3 align-items-center">
                <div class="col-md-9 offset-md-3 d-flex justify-content-between flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-label-primary" onclick="addEditSeg()">
                        <i class="ti tabler-plus me-1"></i>আরেকটা ধরন যোগ করুন
                    </button>
                    <div class="text-muted small">
                        মোট: <strong id="totalEditDaysDisplay" class="text-dark">০ দিন</strong>
                    </div>
                </div>
            </div>

            <!-- Section 3: Admin note + attachment -->
            <div class="section-hdr" data-color="indigo">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">সংশোধনের কারণ ও সংযুক্তি</h6>
                    <span class="section-sub">সংশোধনের প্রয়োজনীয়তা ব্যাখ্যা করুন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-file-description"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="adminNote">
                    সংশোধনের কারণ <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <textarea class="form-control" name="adminNote" id="adminNote" rows="4" placeholder="কেন এই সংশোধন প্রয়োজন তা বিস্তারিত লিখুন..." required></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="attachment">
                    সংযুক্তি (ঐচ্ছিক)
                </label>
                <div class="col-md-9">
                    <input type="file" name="attachment" id="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf" />
                    <small class="text-muted mt-1 d-block">
                        <i class="ti tabler-info-circle me-1"></i>JPEG, JPG, PNG বা PDF — সর্বোচ্চ ২ MB
                    </small>
                </div>
            </div>

            <div id="editFormResult"></div>

            <div class="d-flex justify-content-end gap-2 mt-3 pt-3" style="border-top:1px solid #eef0f5;">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল
                </button>
                <button type="submit" id="submitEditBtn" class="btn btn-primary px-4">
                    <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য প্রেরণ করুন
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<script type="text/javascript">
(function() {
    function initEditForm() {
        if (typeof $ === 'undefined' || typeof flatpickr === 'undefined') { setTimeout(initEditForm, 100); return; }

        // Detach any auto-bound jQuery UI datepicker (which doesn't respect our min/max)
        try { if ($.fn.datepicker) $('.edit-seg-from, .edit-seg-to').datepicker('destroy'); } catch(e) {}

        // Localize Bengali numerals for display
        function beNum(n) {
            var map = {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'};
            return String(n).replace(/[0-9]/g, function(d){ return map[d]; });
        }

        function calcDays($seg) {
            var fromStr = $seg.find('.edit-seg-from').val();
            var toStr   = $seg.find('.edit-seg-to').val();
            if (!fromStr || !toStr) { $seg.find('.edit-seg-days').val(''); return; }
            var fp = fromStr.split('/'), tp = toStr.split('/');
            if (fp.length !== 3 || tp.length !== 3) return;
            var d1 = new Date(+fp[2], +fp[1]-1, +fp[0]);
            var d2 = new Date(+tp[2], +tp[1]-1, +tp[0]);
            if (isNaN(d1) || isNaN(d2)) return;
            if (d2 < d1) { $seg.find('.edit-seg-days').val(''); return; }
            var days = Math.round((d2 - d1) / 86400000) + 1;
            $seg.find('.edit-seg-days').val(days);
        }

        function updateTotal() {
            var total = 0;
            $('.edit-segment .edit-seg-days').each(function() {
                var v = parseInt($(this).val(), 10);
                if (!isNaN(v) && v > 0) total += v;
            });
            $('#totalEditDaysDisplay').text(beNum(total) + ' দিন');
        }

        function bindSegment($seg) {
            // Init flatpickr on from & to
            $seg.find('.edit-seg-from').each(function() {
                if (this._flatpickr) return;
                flatpickr(this, {
                    dateFormat: 'd/m/Y',
                    allowInput: false,
                    onChange: function(selectedDates, dateStr) {
                        var $row = $(this.input).closest('.edit-segment');
                        // Set "to" min
                        var $toInp = $row.find('.edit-seg-to');
                        if ($toInp[0]._flatpickr) $toInp[0]._flatpickr.set('minDate', selectedDates[0] || null);
                        calcDays($row);
                        updateTotal();
                    }
                });
            });
            $seg.find('.edit-seg-to').each(function() {
                if (this._flatpickr) return;
                flatpickr(this, {
                    dateFormat: 'd/m/Y',
                    allowInput: false,
                    onChange: function() {
                        var $row = $(this.input).closest('.edit-segment');
                        calcDays($row);
                        updateTotal();
                    }
                });
            });
        }

        function refreshBadges() {
            $('.edit-segment').each(function(i) {
                $(this).attr('data-segment-idx', i);
                $(this).find('.segment-badge').text('প্রস্তাব ধরন ' + beNum(i + 1));
                $(this).find('.remove-edit-seg').toggle(i > 0);
            });
        }

        window.addEditSeg = function() {
            var $first = $('.edit-segment').first().clone(true, true);
            // Reset values + remove flatpickr instances on clones
            $first.find('select').val('').trigger('change');
            $first.find('input').each(function() {
                if (this._flatpickr) { this._flatpickr.destroy(); }
                $(this).val('');
                this.removeAttribute('readonly');
                if ($(this).hasClass('edit-seg-from') || $(this).hasClass('edit-seg-to')) {
                    this.setAttribute('readonly', 'readonly');
                }
            });
            $first.find('.remove-edit-seg').show();
            $('#editSegments').append($first);
            bindSegment($first);
            refreshBadges();
            updateTotal();
        };

        window.removeEditSeg = function(btn) {
            $(btn).closest('.edit-segment').remove();
            refreshBadges();
            updateTotal();
        };

        // Init existing segments
        $('.edit-segment').each(function() { bindSegment($(this)); });
        refreshBadges();
        updateTotal();

        // Form submit
        $('#editForm').off('submit').on('submit', function(e) {
            e.preventDefault();

            // Validate at least one segment with valid days
            var hasValidSeg = false;
            $('.edit-segment').each(function() {
                var d = parseInt($(this).find('.edit-seg-days').val(), 10);
                if (!isNaN(d) && d > 0) hasValidSeg = true;
            });
            if (!hasValidSeg) {
                Swal.fire({title:'ত্রুটি', text:'কমপক্ষে একটি ধরনের তারিখ পূরণ করুন', icon:'error',
                    confirmButtonColor:'#6c5ce7', customClass:{confirmButton:'btn btn-primary'}, buttonsStyling:false});
                return;
            }

            var $btn = $('#submitEditBtn');
            $btn.prop('disabled', true).html('<i class="ti tabler-loader me-1"></i>প্রক্রিয়াকরণ...');
            var formData = new FormData(this);

            $.ajax({
                url: '../../api/leave/submit-edit-application.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({
                            title: 'সফল', text: resp.message || 'সংশোধন প্রস্তাব প্রেরিত',
                            icon: 'success',
                            confirmButtonColor:'#6c5ce7',
                            customClass:{confirmButton:'btn btn-primary'},
                            buttonsStyling:false
                        }).then(function() {
                            window.location.href = 'allowed-applications.php?menuslug=allowed-leave-applications';
                        });
                    } else {
                        Swal.fire({title:'ত্রুটি', text:(resp && resp.message) || 'প্রেরণ ব্যর্থ', icon:'error',
                            confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                        $btn.prop('disabled', false).html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য প্রেরণ করুন');
                    }
                },
                error: function(xhr) {
                    Swal.fire({title:'সার্ভার ত্রুটি', text:'অনুরোধ ব্যর্থ — পরে আবার চেষ্টা করুন', icon:'error',
                        confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
                    $btn.prop('disabled', false).html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য প্রেরণ করুন');
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEditForm);
    } else {
        initEditForm();
    }
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
