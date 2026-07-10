<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$incrementYear = date('Y');
$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-report-self');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-report-analytics me-2 text-primary"></i>লিভ রিপোর্ট</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>আপনার ছুটির ব্যবহার ও অবশিষ্ট হিসাবের রিপোর্ট</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.simple-form-card { border-radius: 0.75rem; }
.simple-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .simple-form-card .card-body { padding: 1rem; }
}
.simple-form-card .form-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #eef0f5;
}
.simple-form-card .section-icon-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.5rem;
    font-size: 1.05rem;
}
.simple-form-card .section-title {
    margin: 0;
    color: #2c2e3a;
    font-size: 1rem;
    font-weight: 600;
}
.simple-form-card .form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.simple-form-card .form-control:focus,
.simple-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.simple-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}

/* Info banner */
.popup-info-banner {
    background: #e3f1fb;
    border: 1px solid #c0dff2;
    border-left: 3px solid #1a6ea8;
    border-radius: 0.5rem;
    padding: 12px 14px;
    color: #1a4f7a;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
}
.popup-info-banner .ti { font-size: 1.1rem; flex-shrink: 0; }

.simple-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}
</style>

<!-- Leave Report Form Card -->
<div class="card simple-form-card shadow-sm border-0">
    <div class="card-body">
        <form id="leaveReportForm" method="post">
            <input type="hidden" name="employeeID" value="<?= htmlspecialchars($getUserInfoQRW['employee_id']) ?>">

            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-filter"></i></span>
                <h6 class="section-title">রিপোর্ট ফিল্টার</h6>
            </div>

            <div class="row g-3">
                <!-- Leave Type Selection -->
                <div class="col-md-6">
                    <label class="form-label" for="leaveTypeInTwo">ছুটির ধরণ</label>
                    <select class="form-select" name="leaveTypeInTwo" id="leaveTypeInTwo">
                        <option value=''>সকল ধরন</option>
                        <option value="1">গড় বেতন</option>
                        <option value="2">অর্ধ-গড় বেতন</option>
                        <option value="3">নৈমিত্তিক (Casual Leave)</option>
                        <option value="4">বিনা বেতনে ছুটি</option>
                        <option value="5">ঐচ্ছিক (Optional Leave)</option>
                        <option value="6">কর্তনহীন ছুটি</option>
                        <option value="10">অসাধারণ ছুটি</option>
                    </select>
                </div>

                <!-- Year Selection -->
                <div class="col-md-6">
                    <label class="form-label" for="year">ছুটি ভোগের বৎসর</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-calendar"></i></span>
                        <select class="form-select" name="year" id="year">
                            <option value=''>সকল বছর</option>
                            <?php for($year = 2023; $year <= 2030; $year++): ?>
                                <option value="<?= $year ?>" <?= ($year == $incrementYear) ? 'selected' : '' ?>><?= $obj->engToBn($year) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Popup info note -->
            <div class="popup-info-banner mt-3">
                <i class="ti tabler-info-circle"></i>
                <div><strong>নোট:</strong> রিপোর্টটি এই পাতাতেই একটি পপআপ উইন্ডোতে খুলবে।</div>
            </div>

            <!-- Form Actions -->
            <div class="simple-form-actions d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" name="submit" id="submit" class="btn btn-primary px-4">
                    <i class="ti tabler-file-export me-1"></i>রিপোর্ট তৈরি করুন
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     Report preview modal — same-page popup with iframe
═══════════════════════════════════════════════════════ -->
<style>
#leaveReportModal .modal-dialog { max-width: 95vw; margin: 1rem auto; }
#leaveReportModal .modal-content { height: calc(100vh - 2rem); display: flex; flex-direction: column; }
#leaveReportModal .modal-body { flex: 1 1 auto; min-height: 0; padding: 0; position: relative; background: #f5f7fa; }
#leaveReportModal #reportIframe { width: 100%; height: 100%; border: 0; background: #fff; display: block; }
#leaveReportModal #reportLoader {
    position: absolute; inset: 0;
    background: #fff; z-index: 2;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    transition: opacity 0.2s ease;
}
#leaveReportModal #reportLoader.d-none { display: none !important; }
</style>
<div class="modal fade" id="leaveReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 px-3" style="background:linear-gradient(155deg,#0e1e34 0%,#1e3a5f 100%);color:#fff;border:none;">
                <h5 class="modal-title mb-0" style="color:#fff;font-size:1rem;">
                    <i class="ti tabler-report-analytics me-2"></i>লিভ রিপোর্ট
                </h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a href="#" id="reportDownloadBtn" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                        <i class="ti tabler-external-link me-1"></i>নতুন ট্যাবে খুলুন
                    </a>
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                        <i class="ti tabler-x"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div id="reportLoader">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="text-muted small">রিপোর্ট লোড হচ্ছে...</div>
                </div>
                <iframe id="reportIframe" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>

<script>
$(document).ready(function() {
    var $modal  = $('#leaveReportModal');
    var $iframe = $('#reportIframe');
    var $loader = $('#reportLoader');
    var $dlBtn  = $('#reportDownloadBtn');

    $('#leaveReportForm').on('submit', function(e) {
        e.preventDefault();
        var empID   = $('input[name="employeeID"]').val();
        var ltInTwo = $('#leaveTypeInTwo').val();
        var yr      = $('#year').val();
        var url = '<?= $baseURL ?>api/reports/leave-self-pdf.php'
                + '?employeeID=' + encodeURIComponent(empID)
                + '&leaveTypeInTwo=' + encodeURIComponent(ltInTwo)
                + '&year=' + encodeURIComponent(yr);

        $loader.removeClass('d-none');
        $iframe[0].src = url;
        $dlBtn.attr('href', url);
        $modal.modal('show');
    });

    // Hide loader when viewer HTML has loaded (PDF.js viewer inside takes over)
    $iframe[0].addEventListener('load', function() {
        if ($iframe[0].src && $iframe[0].src.indexOf('about:blank') === -1) {
            $loader.addClass('d-none');
        }
    });

    // Reset for next open
    $modal.on('hidden.bs.modal', function() {
        $iframe[0].src = 'about:blank';
        $loader.removeClass('d-none');
    });
});
</script>
