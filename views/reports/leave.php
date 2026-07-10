<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$incrementYear = date('Y');

$getAllEmployeeListQ = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name
    FROM employee_list
    INNER JOIN job_title ON employee_list.designation = job_title.id
    WHERE employment_status=1 OR employment_status=2
    ORDER BY employee_id ASC");

function Bengali_DTN($NRS){
    $englDTN = array('1','2','3','4','5','6','7','8','9','0');
    $bangDTN = array('১','২','৩','৪','৫','৬','৭','৮','৯','০');
    $converted = str_replace($bangDTN, $englDTN, $NRS);
    return $converted;
}

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-report');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-report-analytics me-2 text-primary"></i>লিভ রিপোর্ট</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>কর্মচারীর ছুটির ব্যবহার ও অবশিষ্ট হিসাবের রিপোর্ট</div>
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
    margin-top: 1rem;
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
            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-filter"></i></span>
                <h6 class="section-title">রিপোর্ট ফিল্টার</h6>
            </div>

            <!-- Employee Selection -->
            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label" for="employeeID">
                        কর্মকর্তা / কর্মচারী <span class="text-danger">*</span>
                    </label>
                    <select class="form-select employeeID" name="employeeID" id="employeeID" required>
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                        <?php while($empRow = mysqli_fetch_array($getAllEmployeeListQ)): ?>
                            <option value='<?= $empRow['id'] ?>'>
                                <?= Bengali_DTN($empRow['employee_id']) . ' - ' . htmlspecialchars($empRow['employee_name']) . ', ' . htmlspecialchars($empRow['job_title_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
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
            <div class="popup-info-banner">
                <i class="ti tabler-info-circle"></i>
                <div><strong>নোট:</strong> রিপোর্টটি একটি নতুন ট্যাবে PDF ফরম্যাটে খুলবে।</div>
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

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function() {
    // Initialize Select2 for employee dropdown
    $('.employeeID').select2({
        placeholder: '-- কর্মচারী নির্বাচন করুন --',
        allowClear: true,
        width: '100%',
        theme: 'bootstrap-5'
    });

    $('#leaveReportForm').on('submit', function(e) {
        e.preventDefault();
        var empID   = $('#employeeID').val();
        var ltInTwo = $('#leaveTypeInTwo').val();
        var yr      = $('#year').val();
        var url = '<?= $baseURL ?>api/reports/leave-pdf.php'
                + '?employeeID=' + encodeURIComponent(empID)
                + '&leaveTypeInTwo=' + encodeURIComponent(ltInTwo)
                + '&year=' + encodeURIComponent(yr);
        window.open(url, '_blank');
    });
});
</script>
