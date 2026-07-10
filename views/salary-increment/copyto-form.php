<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Validate employee ID
if (!isset($_GET['employeeID']) || empty($_GET['employeeID'])) {
    echo '<div class="alert alert-danger">কর্মচারী আইডি প্রয়োজন!</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

$employeeID = intval($_GET['employeeID']);

// Get increment settings
$getIncrementSettings = mysqli_query($con, "SELECT * FROM increment_settings WHERE dataID=1");
$getIncrementSettingsRW = mysqli_fetch_assoc($getIncrementSettings);

// Get active employees for dropdowns
$getEmployeeListQ = mysqli_query($con, "SELECT * FROM employee_list WHERE employment_status=1 ORDER BY employee_name");

// Get existing copy recipients for this employee
$getApprovalPersonsQ = mysqli_query($con, "SELECT * FROM salary_notice_copy WHERE refFor='" . $employeeID . "' ORDER BY serial ASC");

// Get employee info
$getEmployeeInfo = mysqli_query($con, "SELECT * FROM employee_list WHERE id='" . $employeeID . "'");
$employeeInfo = mysqli_fetch_assoc($getEmployeeInfo);

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-salary-increment');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-copy me-2 text-primary"></i>অনুলিপি সেটিংস</h4>
        <?php if ($employeeInfo): ?>
            <div class="text-muted small mt-1 ms-1">
                <i class="ti tabler-info-circle me-1"></i>
                <strong class="text-dark"><?= htmlspecialchars($employeeInfo['employee_name']) ?></strong>
                (<?= htmlspecialchars($obj->engToBn($employeeInfo['employee_id'])) ?>) এর বেতন বৃদ্ধি অনুলিপি প্রাপক
            </div>
        <?php else: ?>
            <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>বার্ষিক বেতন বৃদ্ধির অনুলিপি প্রাপকদের নির্ধারণ করুন</div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.copyto-card { border-radius: 0.75rem; }
.copyto-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .copyto-card .card-body { padding: 1rem; }
}

.copyto-card .form-section-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #eef0f5;
}
.copyto-card .section-icon-tile {
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
.copyto-card .section-title {
    margin: 0;
    color: #2c2e3a;
    font-size: 1rem;
    font-weight: 600;
}
.copyto-card .section-helper {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-left: auto;
}

#tbl thead th {
    background: #fafbfd !important;
    font-size: 0.78rem;
    color: #5d6580;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #eef0f5;
}
#tbl tbody td {
    vertical-align: middle;
    font-size: 0.88rem;
}
#tbl .row-serial {
    color: #5d6580;
    font-weight: 500;
}

.copyto-card .form-control:focus,
.copyto-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}

.copyto-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}
</style>

<!-- Copy To Form Card -->
<div class="card copyto-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-horizontal" name="form" id="form" enctype="multipart/form-data">
            <input type="hidden" name="employeeID" value="<?= $employeeID ?>">

            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-users-group"></i></span>
                <h6 class="section-title">অনুলিপি প্রাপক</h6>
                <small class="section-helper"><i class="ti tabler-info-circle me-1"></i>যাদের অনুলিপি পাঠানো হবে</small>
            </div>

            <div class="table-responsive">
                <table id="tbl" class="table table-bordered" width="100%">
                    <thead>
                        <tr>
                            <th class="text-center" width="80">ক্রমিক</th>
                            <th>কর্মকর্তার নাম ও পদবী</th>
                            <th class="text-center" width="120">অনুক্রম</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $m = 1;
                        if ($getApprovalPersonsQ && mysqli_num_rows($getApprovalPersonsQ) > 0) {
                            while($dataRow = mysqli_fetch_array($getApprovalPersonsQ)) {
                                mysqli_data_seek($getEmployeeListQ, 0);
                        ?>
                        <tr>
                            <td class="text-center align-middle row-serial"><?= $m ?></td>
                            <td>
                                <select class="js-example-basic-single" style="width: 100%;" name="copyTo[]">
                                    <option value="">-- নির্বাচন করুন --</option>
                                    <?php while($empRow = mysqli_fetch_array($getEmployeeListQ)): ?>
                                        <option <?= ($dataRow['employeeID'] == $empRow['id']) ? 'selected' : '' ?> value="<?= $empRow['id'] ?>">
                                            <?= $obj->engToBn($empRow['employee_id']) . ' - ' . htmlspecialchars($empRow['employee_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <input type="number" class="form-control text-center" name="serial[]" value="<?= intval($dataRow['serial']) ?>" min="1">
                            </td>
                        </tr>
                        <?php
                                $m++;
                            }
                        }
                        ?>
                        <tr id="addr_placeholder"></tr>
                    </tbody>
                </table>
            </div>

            <!-- Add/Delete Row Buttons -->
            <div class="d-flex gap-2 mt-3">
                <button type="button" id="add_row" class="btn btn-sm btn-label-primary">
                    <i class="ti tabler-plus me-1"></i>সারি যোগ করুন
                </button>
                <button type="button" id="delete_row" class="btn btn-sm btn-label-danger">
                    <i class="ti tabler-minus me-1"></i>সারি মুছুন
                </button>
            </div>

            <!-- Form Actions -->
            <div class="copyto-form-actions d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </button>
                <button type="submit" name="submit" id="submit" class="btn btn-primary submitBtn px-4">
                    <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Get fresh employee list for JavaScript
$getEmployeeListJS = mysqli_query($con, "SELECT id, employee_id, employee_name FROM employee_list WHERE employment_status=1 ORDER BY employee_name");
$employeeOptions = '';
while($empJS = mysqli_fetch_array($getEmployeeListJS)) {
    $employeeOptions .= '<option value="' . $empJS['id'] . '">' . $obj->engToBn($empJS['employee_id']) . ' - ' . htmlspecialchars($empJS['employee_name']) . '</option>';
}

require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>

<script type="text/javascript">
$(document).ready(function() {
    // Initialize Select2
    $('.js-example-basic-single').select2({
        placeholder: "-- নির্বাচন করুন --",
        allowClear: true,
        width: '100%'
    });

    var rowCount = $('#tbl tbody tr').length;

    // Add new row
    $("#add_row").on('click', function() {
        rowCount++;
        var newRowNum = rowCount - 1;

        var html = '<tr>';
        html += '<td class="text-center align-middle row-serial">' + newRowNum + '</td>';
        html += '<td><select class="js-example-basic-single-new" style="width:100%;" name="copyTo[]" required>';
        html += '<option value="">-- নির্বাচন করুন --</option>';
        html += '<?= addslashes($employeeOptions) ?>';
        html += '</select></td>';
        html += '<td class="text-center"><input type="number" name="serial[]" class="form-control text-center" value="' + newRowNum + '" min="1" required /></td>';
        html += '</tr>';

        $('#addr_placeholder').before(html);

        // Initialize Select2 for the new row
        $('#tbl tbody tr:last-child').prev().find('.js-example-basic-single-new').select2({
            placeholder: "-- নির্বাচন করুন --",
            allowClear: true,
            width: '100%'
        });
    });

    // Delete last row
    $("#delete_row").click(function() {
        var rows = $('#tbl tbody tr').length;
        if (rows > 1) {
            $('#tbl tbody tr:nth-last-child(2)').remove();
            rowCount--;
        }
    });

    // Form submission
    $('#form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '../../api/salary-increment/save-copyto.php',
            type: 'POST',
            dataType: 'json',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>প্রক্রিয়াকরণ হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(response) {
                if(response.status == 1) {
                    Swal.fire({
                        title: 'সম্পন্ন',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(() => {
                        window.history.go(-1);
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
            },
            error: function(xhr, status, error) {
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');

                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'ডেটা সংরক্ষণে ব্যর্থ হয়েছে!',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
});
</script>
