<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

function Bengali_DTN($NRS){
    $englDTN = array('1','2','3','4','5','6','7','8','9','0',
        'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday',
        'Sat','Sun','Mon','Tue','Wed','Thu','Fri',
        'am','pm','at','st','nd','rd','th',
        'January','February','March','April','May','June','July','August','September','October','November','December',
        'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
    $bangDTN = array('১','২','৩','৪','৫','৬','৭','৮','৯','০',
        'শনিবার','রবিবার','সোমবার','মঙ্গলবার','বুধবার','বৃহস্পতিবার','শুক্রবার',
        'শনি','রবি','সোম','মঙ্গল','বুধ','বৃহঃ','শুক্র',
        'পূর্বাহ্ণ','অপরাহ্ণ','','','','','',
        'জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর',
        'জানু','ফেব্রু','মার্চ','এপ্রি','মে','জুন','জুলা','আগ','সেপ্টে','অক্টো','নভে','ডিসে');
    return str_replace($bangDTN, $englDTN, $NRS);
}

// Resolve user's organization (re-query because sidebar_menu_vuexy.php overwrites $getUserInfoQRW with partial data)
$orgStmt = $con->prepare("SELECT isCenterAdmin, organization_id, employee_id FROM user_list WHERE user_id = ?");
$orgStmt->bind_param("s", $_SESSION['username']);
$orgStmt->execute();
$orgUserRow = $orgStmt->get_result()->fetch_assoc();
$orgStmt->close();

if (!empty($orgUserRow['isCenterAdmin'])) {
    $userOrgID = (int)$orgUserRow['organization_id'];
} elseif (!empty($orgUserRow['employee_id'])) {
    $empOrgStmt = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $empOrgStmt->bind_param("i", $orgUserRow['employee_id']);
    $empOrgStmt->execute();
    $userOrgID = (int)($empOrgStmt->get_result()->fetch_assoc()['organization_id'] ?? 0);
    $empOrgStmt->close();
} else {
    $userOrgID = 0;
}

// Active organizations — restrict to user's center if applicable
if ($userOrgID > 0) {
    $orgQ = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE id = '$userOrgID' AND deleted=0");
} else {
    $orgQ = mysqli_query($con, "SELECT id, organization_name FROM organization WHERE deleted=0 ORDER BY display_order ASC");
}
$organizations = [];
while ($orgRow = mysqli_fetch_assoc($orgQ)) {
    $organizations[] = $orgRow;
}

// Selected center from GET
$selectedCenter = (int)($_GET['center'] ?? ($organizations[0]['id'] ?? 0));
$validCenter = false;
foreach ($organizations as $org) {
    if ($org['id'] == $selectedCenter) { $validCenter = true; break; }
}
if (!$validCenter && !empty($organizations)) {
    $selectedCenter = $organizations[0]['id'];
}

$stmt = $con->prepare("SELECT * FROM increment_settings WHERE organization_id = ?");
$getIncrementSettingsRW = [];
if ($stmt) {
    $stmt->bind_param("i", $selectedCenter);
    $stmt->execute();
    $getIncrementSettingsRW = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();
}

$getEmployeeListQ  = mysqli_query($con, "SELECT * FROM employee_list WHERE employment_status=1 AND organization_id='$selectedCenter' ORDER BY employee_name");
$getEmployeeListQ2 = mysqli_query($con, "SELECT * FROM employee_list WHERE employment_status=1 AND organization_id='$selectedCenter' ORDER BY employee_name");
$getEmployeeListQ4 = mysqli_query($con, "SELECT ul.dataID, ul.employee_id, ul.user_type, el.id, el.employee_name, el.employee_id AS emp_code
    FROM user_list ul
    INNER JOIN employee_list el ON ul.employee_id = el.id
    WHERE ul.user_type = 2 AND el.organization_id = '$selectedCenter'
    ORDER BY el.employee_name");
$getApprovalPersonsQ = mysqli_query($con, "SELECT * FROM salary_increment_approvals WHERE organization_id='$selectedCenter' ORDER BY approvalSL ASC");
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-8">
        <h4 class="fw-bold mb-0"><i class="ti tabler-currency-taka me-2 text-primary"></i>বেতন বৃদ্ধির সেটিংস</h4>
    </div>
    <div class="col-12 col-md-4 text-md-end">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<!-- Center Tabs (modernized) -->
<?php if (!empty($organizations)): ?>
<ul class="nav custom-leave-tabs px-3 pt-3 mb-3" role="tablist">
    <?php foreach ($organizations as $org): $isActive = ($org['id'] == $selectedCenter); ?>
    <li class="nav-item">
        <a href="?menuslug=salary-increment-settings&center=<?= (int)$org['id'] ?>"
           class="nav-link <?= $isActive ? 'active' : '' ?>"
           data-turbo="true">
            <i class="ti tabler-building me-2"></i>
            <span><?= htmlspecialchars($org['organization_name']) ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<!-- Settings Card -->
<div class="card leave-apps-card shadow-sm border-0">
    <div class="card-body">
        <div class="statusMsg" style="display:none;"></div>

        <form class="form form-horizontal" name="form" id="form" enctype="multipart/form-data">
            <input type="hidden" name="organization_id" value="<?= (int)$selectedCenter ?>">

            <!-- Section: Salary Increment Form -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <span class="settings-section-icon"><i class="ti tabler-currency-taka"></i></span>
                    <h5 class="settings-section-title">বেতন বৃদ্ধির ফরম</h5>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="salary_increment_date">
                        <i class="ti tabler-calendar-event me-1 text-muted"></i>বেতন বৃদ্ধির তারিখ <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <div class="field-shell">
                            <i class="ti tabler-calendar field-icon"></i>
                            <input type="date" id="salary_increment_date" class="form-control"
                                   name="salary_increment_date"
                                   value="<?= htmlspecialchars($getIncrementSettingsRW['salary_increment_date'] ?? '') ?>" required style="padding-left:2.2rem;">
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="applicationTo">
                        <i class="ti tabler-user me-1 text-muted"></i>প্রতি <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <select class="select2 applicationTo" style="width:100%;" name="applicationTo" id="applicationTo" data-allow-clear="true" required>
                            <option value=''>-- নির্বাচন করুন --</option>
                            <?php while ($aptRow = mysqli_fetch_array($getEmployeeListQ)): ?>
                            <option value="<?= $aptRow['id'] ?>"
                                <?= ($aptRow['id'] == ($getIncrementSettingsRW['applicationTo'] ?? '')) ? "selected" : "" ?>>
                                <?= htmlspecialchars(Bengali_DTN($aptRow['employee_id']) . ' - ' . $aptRow['employee_name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section: Office Order -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <span class="settings-section-icon"><i class="ti tabler-file-text"></i></span>
                    <h5 class="settings-section-title">অফিস আদেশ</h5>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="fileNo">
                        <i class="ti tabler-hash me-1 text-muted"></i>ফাইল নং
                    </label>
                    <div class="col-md-9">
                        <input type="text" id="fileNo" class="form-control"
                               placeholder="ফাইল নং লিখুন" name="fileNo"
                               value="<?= htmlspecialchars($getIncrementSettingsRW['fileNo'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="copyTo">
                        <i class="ti tabler-copy me-1 text-muted"></i>অনুলিপি
                    </label>
                    <div class="col-md-9">
                        <select class="select2 copyTo" name="copyTo[]" style="width:100%;" id="copyTo" data-allow-clear="true" multiple="multiple">
                            <option value=''>-- নির্বাচন করুন --</option>
                            <?php while ($aptRow = mysqli_fetch_array($getEmployeeListQ2)):
                                $checkCopyQ = mysqli_query($con, "SELECT dataID FROM salary_notice_copy WHERE employeeID='{$aptRow['id']}' AND organization_id='$selectedCenter' AND refFor=0");
                            ?>
                            <option value="<?= $aptRow['id'] ?>"
                                <?= (mysqli_num_rows($checkCopyQ) > 0) ? "selected" : "" ?>>
                                <?= htmlspecialchars(Bengali_DTN($aptRow['employee_id']) . ' - ' . $aptRow['employee_name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section: Office Order Content -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <span class="settings-section-icon"><i class="ti tabler-file-description"></i></span>
                    <h5 class="settings-section-title">অফিস আদেশ কনটেন্ট</h5>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <textarea class="form-control" name="notice_content" rows="6"
                                  placeholder="অফিস আদেশের বিষয়বস্তু লিখুন"><?= htmlspecialchars($getIncrementSettingsRW['notice_content'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section: Approval Officers -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <span class="settings-section-icon"><i class="ti tabler-users"></i></span>
                    <h5 class="settings-section-title">অনুমোদনকারী কর্মকর্তাগণ</h5>
                </div>

                <div class="table-responsive mb-3">
                    <table id="tbl" class="table modern-leave-table align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:80px;">ক্রমিক</th>
                                <th>কর্মকর্তা</th>
                                <th class="text-center" style="width:200px;">অনুমতির ক্রমিক</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $m = 1;
                            while ($dataRow = mysqli_fetch_array($getApprovalPersonsQ)):
                                $getUsersQ = mysqli_query($con, "SELECT ul.*, el.employee_name, el.employee_id AS emp_code
                                    FROM user_list ul
                                    INNER JOIN employee_list el ON ul.employee_id = el.id
                                    WHERE ul.user_type = 2 AND el.organization_id = '$selectedCenter'
                                    ORDER BY el.employee_name");
                            ?>
                            <tr>
                                <td class='text-center'><span class="serial-num"><?= $m ?></span></td>
                                <td>
                                    <select class="select2 js-example-basic-single" style="width:100%;" name="signatory[]" data-allow-clear="true">
                                        <option value=''>-- নির্বাচন করুন --</option>
                                        <?php while ($empRow = mysqli_fetch_array($getUsersQ)): ?>
                                        <option value="<?= $empRow['dataID'] ?>"
                                            <?= ($dataRow['employeeID'] == $empRow['dataID']) ? "selected" : "" ?>>
                                            <?= htmlspecialchars(Bengali_DTN($empRow['emp_code']) . ' - ' . $empRow['employee_name']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control" name="serial[]"
                                           value="<?= (int)$dataRow['approvalSL'] ?>" />
                                </td>
                            </tr>
                            <?php $m++; endwhile; ?>
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
                            <i class="ti tabler-trash me-1"></i>সারি মুছে ফেলুন
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="row mt-4 pt-3 border-top">
                <div class="col-12 text-end">
                    <a href="<?= $baseURL ?>dashboard.php" class="btn btn-label-secondary me-2" data-turbo="true">
                        <i class="ti tabler-x me-1"></i>বাতিল করুন
                    </a>
                    <button type="submit" class="btn btn-primary submitBtn">
                        <i class="ti tabler-check me-1"></i>সংরক্ষণ করুন
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
/* Settings sections — visual grouping */
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

/* Form-row label icon hint */
.col-form-label i { color: #8a90a6; }

/* Center-tab specific override (the tabs are hyperlinks here, not buttons) */
.custom-leave-tabs .nav-link {
    text-decoration: none;
}

/* Select2 dropdown must float above table sticky header (z-index:5) and modal-ish wrappers */
.select2-container--open .select2-dropdown,
.select2-container--bootstrap-5 .select2-dropdown {
    z-index: 1100 !important;
}
</style>

<script type="text/javascript">
// Note: Select2 init for the existing selects is handled globally in footer_vuexy.php
// (it targets all elements with class="select2"). We only need to init Select2
// on dynamically-added rows below.

function initRowSelect2($select) {
    // For row selects inside the table, append the dropdown to <body> so it
    // doesn't get clipped by the table's overflow/positioning context.
    $select.select2({
        theme: 'bootstrap-5',
        placeholder: '-- নির্বাচন করুন --',
        allowClear: true
    });
}

// Re-init row Select2s so the dropdown attaches to <body> instead of the
// position-relative wrapper inside the table cell. This avoids the dropdown
// being clipped/overlapped by the table's positioning context.
function fixRowSelect2() {
    $('#tbl tbody select.js-example-basic-single').each(function() {
        var $sel = $(this);
        try {
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }
        } catch (e) {}
        // Unwrap the .position-relative the global init added
        var $parent = $sel.parent();
        if ($parent.hasClass('position-relative') && $parent.children().length === 1) {
            $sel.unwrap();
        }
        // Init without dropdownParent → Select2 attaches dropdown to <body>
        $sel.select2({
            theme: 'bootstrap-5',
            placeholder: '-- নির্বাচন করুন --',
            allowClear: true
        });
    });
}

$(document).ready(function() {
    // Footer's global Select2 init runs first; we override row selects after a tick
    setTimeout(fixRowSelect2, 50);

    var rowCount = $('table tr').length;

    $("#add_row").on('click', function(){
        rowCount++;
        var html = '<tr>';
        html += "<td class='text-center'><span class='serial-num'>" + (rowCount - 2) + "</span></td>";
        html += "<td><select class='select2 js-example-basic-single-new" + rowCount + "' style='width:100%;' name='signatory[]'>";
        html += "<option value=''>-- নির্বাচন করুন --</option>";
        html += "<?php
        mysqli_data_seek($getEmployeeListQ4, 0);
        while ($cRow2 = mysqli_fetch_array($getEmployeeListQ4)) {
            echo '<option value=\"' . $cRow2['dataID'] . '\">' . addslashes(Bengali_DTN($cRow2['emp_code'])) . ' - ' . addslashes($cRow2['employee_name']) . '</option>';
        }
        ?>";
        html += "</select></td>";
        html += "<td><input type='number' name='serial[]' class='form-control' value='" + (rowCount - 2) + "' required /></td>";
        html += '</tr>';

        $('#tbl tbody').append(html);
        initRowSelect2($('.js-example-basic-single-new' + rowCount));
    });

    $("#delete_row").click(function(){
        $("#tbl tbody tr:last").remove();
    });

    // Bind submit on form (delegated for turbo-survival)
    $(document).off('submit.siform').on('submit.siform', '#form', function(e){
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: '../../api/salary-increment/save-settings.php',
            data: new FormData(this),
            dataType: 'json',
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function(){
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>সংরক্ষণ হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(response){
                $('.statusMsg').html('');
                if (response.status == 1) {
                    Swal.fire({
                        title: 'সম্পন্ন', text: response.message, icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
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
                $('.submitBtn').html('<i class="ti tabler-check me-1"></i>সংরক্ষণ করুন');
            },
            error: function(){
                Swal.fire({
                    title: 'ত্রুটি', text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।', icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-check me-1"></i>সংরক্ষণ করুন');
            }
        });
    });
});

</script>
