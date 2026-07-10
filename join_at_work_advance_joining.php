<?php
require_once(__DIR__ . '/includes/header_vuexy.php');

$leaveApplicationID = intval($_GET['applicationID'] ?? 0);

$getLeaveApplicationDetailsQ = mysqli_query($con, "SELECT * FROM leave_applications WHERE dataID='$leaveApplicationID'");
$getLeaveApplicationDetailsQRW = mysqli_fetch_assoc($getLeaveApplicationDetailsQ);

$getSupervisorQ = mysqli_query($con, "SELECT * FROM leave_data_for_approval WHERE leaveApplicationID='$leaveApplicationID' AND isSupervisor=1");
$getSupervisorQRW = mysqli_fetch_assoc($getSupervisorQ);
$supervisorID = $getSupervisorQRW['signatory'] ?? 0;

$aDateFrom = !empty($getLeaveApplicationDetailsQRW['approvedDateFrom'])
    ? $getLeaveApplicationDetailsQRW['approvedDateFrom']
    : $getLeaveApplicationDetailsQRW['dateFrom'];
$aDateTo = !empty($getLeaveApplicationDetailsQRW['approvedDateTo'])
    ? $getLeaveApplicationDetailsQRW['approvedDateTo']
    : $getLeaveApplicationDetailsQRW['dateTo'];

$dateDiff = dateDiffInDays($aDateFrom, $aDateTo) + 1;

// Employee dropdown
$getAllEmployeesQ = mysqli_query($con, "SELECT el.id, el.employee_name, el.employee_id, jt.job_title_name FROM employee_list el LEFT JOIN job_title jt ON el.designation = jt.id WHERE el.employment_status=1 ORDER BY el.employee_name ASC");
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <h4 class="fw-bold">ছুটি পূর্ণ ভোগ না করে অগ্রিম যোগদান</h4>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form name="form" id="form">
            <input type="hidden" name="leaveApplicationID" value="<?= $leaveApplicationID ?>">

            <div class="row mb-3 align-items-center">
                <label class="col-md-3 col-form-label fw-semibold">অনুমোদনকৃত ছুটি(দিন)</label>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="leaveFrom" value="<?= htmlspecialchars(convertDateTotrad($aDateFrom)) ?>" readonly>
                </div>
                <label class="col-md-1 col-form-label">পর্যন্ত</label>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="leaveTo" value="<?= htmlspecialchars(convertDateTotrad($aDateTo)) ?>" readonly>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" name="approvedDays" value="<?= $dateDiff ?>" readonly>
                </div>
            </div>

            <div class="row mb-3 align-items-center">
                <label class="col-md-3 col-form-label fw-semibold">ভোগকৃত ছুটি(দিন)</label>
                <div class="col-md-3">
                    <input type="text" class="form-control" id="spentLeaveFrom" name="spentLeaveFrom" value="<?= htmlspecialchars(convertDateTotrad($aDateFrom)) ?>" readonly>
                </div>
                <label class="col-md-1 col-form-label">পর্যন্ত</label>
                <div class="col-md-3">
                    <input type="text" class="form-control flatpickr-input" id="spentLeaveTo" name="spentLeaveTo" placeholder="dd/mm/yyyy" onchange="calculateDays()" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" id="reqJoiningLeaveDays" readonly>
                </div>
            </div>

            <div class="row mb-4 align-items-center">
                <label class="col-md-3 col-form-label fw-semibold">সুপারভাইজার/ঊর্ধ্বতন কর্মকর্তা</label>
                <div class="col-md-9">
                    <select class="form-select select2" name="supervisorID" id="supervisorID" required>
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php while ($empRow = mysqli_fetch_assoc($getAllEmployeesQ)): ?>
                        <option value="<?= $empRow['id'] ?>" <?= ($supervisorID == $empRow['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($empRow['employee_name'] . ', ' . ($empRow['job_title_name'] ?? '')) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div id="formresult"></div>

            <div class="d-flex gap-2 mt-3">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" id="submit" class="btn btn-primary">
                    <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান
                </button>
            </div>
        </form>

        <div id="letter" class="mt-4"></div>
    </div>
</div>

<?php require_once(__DIR__ . '/includes/footer_vuexy.php'); ?>

<script>
$(document).ready(function () {
    if ($.fn.select2) {
        $('#supervisorID').select2({ placeholder: '-- নির্বাচন করুন --' });
    }

    // Flatpickr for spentLeaveTo
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#spentLeaveTo', {
            dateFormat: 'd/m/Y',
            allowInput: true,
            onChange: function () { calculateDays(); }
        });
    }

    var form   = $('#form');
    var submit = $('#submit');

    form.on('submit', function (e) {
        e.preventDefault();
        submit.attr('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>পাঠানো হচ্ছে...');
        $.ajax({
            url: 'insert_advance_leave_joining_application_action.php',
            type: 'POST',
            dataType: 'html',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            success: function (data) {
                $('#letter').html(data);
                submit.removeAttr('disabled').html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
                if (data != 0) {
                    window.location = 'views/leave/all-applications.php?menuslug=all-leave-application';
                } else {
                    Swal.fire({ icon: 'error', title: 'ত্রুটি!', text: 'একটি সমস্যা হয়েছে।', buttonsStyling: false, customClass: { confirmButton: 'btn btn-danger' } });
                }
            },
            error: function (e) {
                console.log(e);
                submit.removeAttr('disabled').html('<i class="ti tabler-send me-1"></i>অনুমোদনের জন্য পাঠান');
            }
        });
    });
});

function calculateDays() {
    var date1Str = $('#spentLeaveFrom').val();
    var date2Str = $('#spentLeaveTo').val();
    if (!date1Str || !date2Str) return;

    const [d1, m1, y1] = date1Str.split('/');
    const [d2, m2, y2] = date2Str.split('/');
    const date1 = new Date(y1 + '-' + m1 + '-' + d1);
    const date2 = new Date(y2 + '-' + m2 + '-' + d2);
    if (isNaN(date1) || isNaN(date2)) return;

    const days = Math.floor(Math.abs(date2 - date1) / (1000 * 60 * 60 * 24));
    $('#reqJoiningLeaveDays').val(days + 1);
}
</script>
