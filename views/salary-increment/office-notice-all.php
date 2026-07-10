<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Get all salary groups/sections
$getAllSectionsQ = mysqli_query($con, "SELECT * FROM salary_group ORDER BY sub_head ASC");

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'salary-increment-office-notice-all');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-file-description me-2 text-primary"></i>অফিস আদেশ (সকল)</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>শাখা ও অর্থ বছরের ভিত্তিতে অফিস আদেশ জেনারেট করুন</div>
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
.simple-form-card .col-form-label {
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
}
.popup-info-banner .ti { font-size: 1.1rem; flex-shrink: 0; }

.simple-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}
</style>

<!-- Office Notice All Form Card -->
<div class="card simple-form-card shadow-sm border-0">
    <div class="card-body">
        <form action="office-notice-all-print.php" method="post" id="officeNoticeAllForm" onsubmit="target_popup(this)" data-turbo="false">
            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-file-description"></i></span>
                <h6 class="section-title">অফিস আদেশ জেনারেশন</h6>
            </div>

            <!-- Section Selection -->
            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="sectionID">
                    শাখা <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="form-select" name="sectionID" id="sectionID" required>
                        <option value=''>-- শাখা নির্বাচন করুন --</option>
                        <?php
                        if ($getAllSectionsQ && mysqli_num_rows($getAllSectionsQ) > 0) {
                            while($sRow = mysqli_fetch_array($getAllSectionsQ)):
                        ?>
                            <option value='<?= intval($sRow['id']) ?>'><?= htmlspecialchars($sRow['sub_head']) ?></option>
                        <?php
                            endwhile;
                        }
                        ?>
                        <option value='0'>সকল শাখা (All)</option>
                    </select>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>নির্দিষ্ট শাখা অথবা সকল শাখা নির্বাচন করুন</small>
                </div>
            </div>

            <!-- Financial Year Selection -->
            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="financialYear">
                    অর্থ বছর <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-calendar"></i></span>
                        <select class="form-select" id="financialYear" name="financialYear" required>
                            <option value=''>-- অর্থ বছর নির্বাচন করুন --</option>
                            <?php for ($i = 2023; $i <= 2030; $i++): ?>
                                <option value='<?= $i ?>' <?= ($i == date('Y')) ? 'selected' : '' ?>><?= $obj->engToBn($i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>যে অর্থ বছরের জন্য অফিস আদেশ জেনারেট করতে চান তা নির্বাচন করুন</small>
                </div>
            </div>

            <!-- Popup info note -->
            <div class="row mb-3">
                <div class="col-md-9 offset-md-3">
                    <div class="popup-info-banner">
                        <i class="ti tabler-info-circle"></i>
                        <div><strong>নোট:</strong> অফিস আদেশটি একটি নতুন উইন্ডোতে খুলবে। অনুগ্রহ করে পপ-আপ ব্লকার নিষ্ক্রিয় করুন।</div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="simple-form-actions d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" name="submit" id="submit" class="btn btn-primary px-4">
                    <i class="ti tabler-file-text me-1"></i>জেনারেট করুন
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>

<script type="text/javascript">
// Open form result in popup window
function target_popup(form) {
    window.open('', 'formpopup', 'width=1200,height=800,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,status=no');
    form.target = 'formpopup';
}

$(document).ready(function() {
    // Add visual feedback on form submission
    $('#officeNoticeAllForm').on('submit', function() {
        var submitBtn = $('#submit');
        submitBtn.attr("disabled", "disabled");
        submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>জেনারেট হচ্ছে...');

        // Re-enable button after a delay (popup will open)
        setTimeout(function() {
            submitBtn.removeAttr("disabled");
            submitBtn.html('<i class="ti tabler-file-text me-1"></i>জেনারেট করুন');
        }, 2000);
    });
});
</script>
