<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$incrementYear = date('Y');

// Actor scope — Super Admin + HQ (org=4) see all centers; others restricted to own center
$_actorStmt = mysqli_prepare($con,
    "SELECT ul.user_group_id, el.organization_id AS emp_org
     FROM user_list ul
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     WHERE ul.user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actor = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);
$_isSuperAdmin  = ((int)($_actor['user_group_id'] ?? 0) === 1);
$_myCenterID    = (int)($_actor['emp_org'] ?? 0);
$_seeAllCenters = ($_isSuperAdmin || $_myCenterID === 4);
$_orgScope      = $_seeAllCenters ? '' : ' AND organization_id = ' . $_myCenterID;
$_orgScopeEL    = $_seeAllCenters ? '' : ' AND employee_list.organization_id = ' . $_myCenterID;

$getAllEmployeeQ    = mysqli_query($con, "SELECT * FROM employee_list WHERE (employment_status=1 OR employment_status=2) AND pending_section_assignment=0 $_orgScope ORDER BY employee_name");
$getAllCopytoListQ  = mysqli_query($con, "SELECT * FROM employee_list WHERE 1=1 $_orgScope ORDER BY employee_name");
$getAllCopytoListQ2 = mysqli_query($con, "SELECT * FROM employee_list WHERE 1=1 $_orgScope ORDER BY employee_name");
$getSignatoryListQ  = mysqli_query($con, "SELECT employee_list.*, job_title.job_title_name FROM employee_list
    INNER JOIN job_title ON employee_list.designation = job_title.id
    WHERE (employment_status=1 OR employment_status=2) AND employee_list.pending_section_assignment=0 $_orgScopeEL ORDER BY employee_id ASC");

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
    return str_replace($bangDTN, $englDTN, $NRS);
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-8">
        <h4 class="fw-bold mb-0"><i class="ti tabler-certificate me-2 text-primary"></i>ছুটির সার্টিফিকেট তৈরি</h4>
    </div>
    <div class="col-12 col-md-4 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body">
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-horizontal" name="form" id="form" enctype="multipart/form-data">

            <!-- Section: Basic Information -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <span class="settings-section-icon"><i class="ti tabler-calendar-event"></i></span>
                    <h5 class="settings-section-title">মৌলিক তথ্য</h5>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="incrementYear">
                        <i class="ti tabler-calendar me-1 text-muted"></i>বছর <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <select class="form-control" name="incrementYear" id="incrementYear">
                            <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="certificateDate">
                        <i class="ti tabler-calendar-event me-1 text-muted"></i>সার্টিফিকেট তারিখ <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <div class="field-shell">
                            <i class="ti tabler-calendar field-icon"></i>
                            <input type="text" class="form-control" id="certificateDate" name="certificateDate" placeholder="dd/mm/yyyy" required autocomplete="off" style="padding-left:2.2rem;" />
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="noticeDate">
                        <i class="ti tabler-calendar-event me-1 text-muted"></i>নোটিশ তারিখ <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <div class="field-shell">
                            <i class="ti tabler-calendar field-icon"></i>
                            <input type="text" class="form-control" id="noticeDate" name="noticeDate" placeholder="dd/mm/yyyy" required autocomplete="off" style="padding-left:2.2rem;" />
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="employeeID">
                        <i class="ti tabler-user me-1 text-muted"></i>কর্মচারী <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <select class="select2" style="width: 100%;" name="employeeID" id="employeeID" data-allow-clear="true" required>
                            <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                            <?php while($empRow = mysqli_fetch_array($getAllEmployeeQ)):
                                $getSupervisorDesigQ = mysqli_query($con, "SELECT * FROM job_title WHERE id='$empRow[designation]'");
                                $getSupervisorDesigQRW = mysqli_fetch_assoc($getSupervisorDesigQ);
                            ?>
                            <option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name'].', '.($getSupervisorDesigQRW['job_title_name'] ?? ''); ?></option>
                            <?php endwhile; ?>
                            <option value='0'>সকল</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="signatoryID">
                        <i class="ti tabler-signature me-1 text-muted"></i>স্বাক্ষরকারী কর্মকর্তা <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <select class="select2" style="width: 100%;" name="signatoryID" id="signatoryID" data-allow-clear="true" required>
                            <option value=''>-- স্বাক্ষরকারী নির্বাচন করুন --</option>
                            <?php while($sigRow = mysqli_fetch_array($getSignatoryListQ)): ?>
                            <option value='<?php echo $sigRow['id']; ?>'><?php echo Bengali_DTN($sigRow['employee_id']).' - '.$sigRow['employee_name'].', '.$sigRow['job_title_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section: Copy To -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <span class="settings-section-icon"><i class="ti tabler-copy"></i></span>
                    <h5 class="settings-section-title">অনুলিপি</h5>
                </div>

                <div class="table-responsive mb-3">
                    <table id="tbl" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:80px;">ক্রমিক</th>
                                <th>কর্মকর্তা</th>
                                <th class="text-center" style="width:160px;">সিরিয়াল</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id='addr0'>
                                <td class='text-center'><span class="serial-num">1</span></td>
                                <td>
                                    <select class="copy-select-row" style="width: 100%;" name="copyTo[]">
                                        <option value=''>-- নির্বাচন করুন --</option>
                                        <?php while($empRow = mysqli_fetch_array($getAllCopytoListQ)): ?>
                                        <option value='<?php echo $empRow['id']; ?>'><?php echo Bengali_DTN($empRow['employee_id']).' - '.$empRow['employee_name']; ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control text-center" name="serial[]" value="1" />
                                </td>
                            </tr>
                            <tr id='addr2'></tr>
                        </tbody>
                    </table>
                </div>

                <div class="row mb-3">
                    <div class="col-12 text-end">
                        <button type="button" id="add_row" class="btn btn-sm btn-label-success me-2">
                            <i class="ti tabler-plus me-1"></i>সারি যোগ করুন
                        </button>
                        <button type="button" id="delete_row" class="btn btn-sm btn-label-danger">
                            <i class="ti tabler-trash me-1"></i>সারি মুছুন
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="row mt-4 pt-3 border-top">
                <div class="col-12 text-end">
                    <a href="<?php echo $baseURL; ?>dashboard.php" class="btn btn-label-secondary me-2" data-turbo="true">
                        <i class="ti tabler-x me-1"></i>বাতিল করুন
                    </a>
                    <button type="submit" name="submit" id="submit" class="btn btn-primary submitBtn">
                        <i class="ti tabler-check me-1"></i>তৈরি করুন
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
/* Settings sections — visual grouping (same as salary-increment settings) */
.settings-section {
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-radius: 0.6rem;
    padding: 1.25rem 1.25rem 0.75rem;
    margin-bottom: 1.25rem;
}
.settings-section-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.1rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #eef0f5;
}
.settings-section-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #efeaff 0%, #ddd5f6 100%);
    color: #5648c4;
    border-radius: 0.5rem;
    font-size: 1.1rem;
}
.settings-section-title {
    margin: 0;
    color: #3a3d53;
    font-size: 1rem;
    font-weight: 600;
}
.col-form-label i { color: #8a90a6; }

/* Select2 dropdowns inside the table need to float above sticky header */
.select2-container--open .select2-dropdown,
.select2-container--bootstrap-5 .select2-dropdown {
    z-index: 1100 !important;
}
</style>

<script type="text/javascript">
function initRowSelect2($select) {
    // For row selects, attach dropdown to <body> so it doesn't get clipped
    $select.select2({
        theme: 'bootstrap-5',
        placeholder: '-- নির্বাচন করুন --',
        allowClear: true
    });
}

// After footer's global Select2 init has run, re-init the copy-table row selects
// without dropdownParent so they float above the table sticky header.
function fixCopyRowSelect2() {
    $('#tbl tbody select.copy-select-row').each(function() {
        var $sel = $(this);
        try {
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }
        } catch (e) {}
        var $parent = $sel.parent();
        if ($parent.hasClass('position-relative') && $parent.children().length === 1) {
            $sel.unwrap();
        }
        $sel.select2({
            theme: 'bootstrap-5',
            placeholder: '-- নির্বাচন করুন --',
            allowClear: true
        });
    });
}

$(document).ready(function() {
    // Date pickers (jQuery UI)
    if ($.fn.datepicker) {
        $("#certificateDate").datepicker({ dateFormat: "dd/mm/yy" });
        $("#noticeDate").datepicker({ dateFormat: "dd/mm/yy" });
    }

    // The two main selects (employeeID, signatoryID) carry .select2 class — handled by footer's global init.
    // Copy-table row selects use .copy-select-row and need re-init for proper dropdown positioning.
    setTimeout(fixCopyRowSelect2, 50);

    var i = $('table tr').length;

    $("#add_row").on('click', function() {
        i++;
        var html = '<tr>';
        html += "<td class='text-center'><span class='serial-num'>" + (i - 2) + "</span></td>";
        html += "<td><select class='copy-select-row copy-select-row-" + i + "' style='width:100%;' name='copyTo[]' id='copyTo_" + i + "'><option value=''>-- নির্বাচন করুন --</option>";
        html += "<?php while($cRow2 = mysqli_fetch_array($getAllCopytoListQ2)){ ?><option value='<?php echo $cRow2['id']; ?>'><?php echo Bengali_DTN($cRow2['employee_id']).' - '.$cRow2['employee_name']; ?></option><?php } ?>";
        html += "</select></td>";
        html += "<td><input type='number' name='serial[]' id='serial_" + i + "' class='form-control text-center' value='" + (i - 2) + "' /></td>";
        html += '</tr>';
        $('#tbl tbody').append(html);
        initRowSelect2($('.copy-select-row-' + i));
    });

    $("#delete_row").click(function() {
        var rowCount = $('#tbl tbody tr').length;
        if (rowCount > 2) {
            $("#tbl tbody tr:last").remove();
        } else {
            Swal.fire({
                title: 'সতর্কতা',
                text: 'কমপক্ষে একটি সারি থাকতে হবে',
                icon: 'warning',
                confirmButtonColor: '#dc3545',
                customClass: { confirmButton: 'btn btn-warning' },
                buttonsStyling: false
            });
        }
    });

    // Form submission (delegated for Turbo survival)
    $(document).off('submit.lcform').on('submit.lcform', '#form', function(e) {
        e.preventDefault();
        $.ajax({
            url: '../../api/leave-certificate/generate.php',
            type: 'POST',
            dataType: 'json',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>তৈরি হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(response) {
                $('.statusMsg').html('').hide();
                if (response.status == 1) {
                    Swal.fire({
                        title: 'সম্পন্ন', text: response.message, icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(function() {
                        $('#form')[0].reset();
                        $('#employeeID, #signatoryID, #tbl tbody select').val(null).trigger('change');
                    });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি', text: response.message, icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-check me-1"></i>তৈরি করুন');
            },
            error: function() {
                Swal.fire({
                    title: 'ত্রুটি', text: 'সার্টিফিকেট তৈরি করতে ব্যর্থ হয়েছে', icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-check me-1"></i>তৈরি করুন');
            }
        });
    });
});
</script>
