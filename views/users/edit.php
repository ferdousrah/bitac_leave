<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Get user ID from query string
$dataID   = isset($_GET['dataID']) ? intval($_GET['dataID']) : 0;
$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-user');

if ($dataID === 0) {
    echo "<script>
        Swal.fire({
            title: 'ত্রুটি',
            text: 'অবৈধ তথ্য আইডি!',
            icon: 'error',
            confirmButtonColor: '#ff3e1d',
            customClass: { confirmButton: 'btn btn-danger' },
            buttonsStyling: false
        }).then(() => {
            window.location='manage.php?menuslug={$menuslug}';
        });
    </script>";
    exit;
}

// Fetch user details using prepared statement
$stmt = $con->prepare("SELECT * FROM user_list WHERE dataID = ?");
$stmt->bind_param("i", $dataID);
$stmt->execute();
$result = $stmt->get_result();
$getUserDetailsQRW = $result->fetch_assoc();
$stmt->close();

if (!$getUserDetailsQRW) {
    echo "<script>
        Swal.fire({
            title: 'ত্রুটি',
            text: 'ব্যবহারকারী খুঁজে পাওয়া যায়নি!',
            icon: 'error',
            confirmButtonColor: '#ff3e1d',
            customClass: { confirmButton: 'btn btn-danger' },
            buttonsStyling: false
        }).then(() => {
            window.location='manage.php?menuslug={$menuslug}';
        });
    </script>";
    exit;
}

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

// Resolve organization_id for current user
if (!empty($_SESSION['isCenterAdmin']) && !empty($_SESSION['centerAdminOrgID'])) {
    $orgID = intval($_SESSION['centerAdminOrgID']);
} else {
    $empID = intval($_SESSION['employeeID'] ?? 0);
    $stmt_org = $con->prepare("SELECT organization_id FROM employee_list WHERE id = ?");
    $stmt_org->bind_param("i", $empID);
    $stmt_org->execute();
    $orgRow = $stmt_org->get_result()->fetch_assoc();
    $stmt_org->close();
    $orgID = intval($orgRow['organization_id'] ?? 0);
}

$getEmployeeListQ = mysqli_query($con, "SELECT * FROM employee_list
    WHERE employment_status = 1 AND organization_id = $orgID
    ORDER BY employee_name ASC");

// Fetch user groups for dropdown.
// Reserved groups excluded from this manual form:
//   - Super Admin (id=1) — system-level
//   - Center Admin (id=2) — legacy, managed via center-admin page
//   - Regional Super Admin (id=7) + Regional Op. Admin (id=8) — managed via
//     the role-approval workflow with attachment + approver sign-off
$getUserGroupsQ = mysqli_query($con, "SELECT * FROM user_group WHERE deleted = 0 AND id NOT IN (1, 2, 7, 8) ORDER BY display_order ASC");

// Materialise + read this user's existing group assignments
$allGroups = [];
while ($g = mysqli_fetch_assoc($getUserGroupsQ)) {
    $allGroups[] = ['id' => $g['id'], 'group_name' => $g['group_name']];
}
$assignedGroupIds = [];
$assignStmt = $con->prepare("SELECT group_id FROM user_group_assignment WHERE user_id = ?");
$assignStmt->bind_param("i", $dataID);
$assignStmt->execute();
$assignRes = $assignStmt->get_result();
while ($a = mysqli_fetch_assoc($assignRes)) {
    $assignedGroupIds[] = (int)$a['group_id'];
}
$assignStmt->close();
// Fallback for legacy users not yet in the assignment table — use user_list.user_group_id
if (empty($assignedGroupIds) && !empty($getUserDetailsQRW['user_group_id'])) {
    $assignedGroupIds[] = (int)$getUserDetailsQRW['user_group_id'];
}
$currentDefaultGroupId = (int)($getUserDetailsQRW['user_group_id'] ?? 0);
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-cog me-2 text-primary"></i>ব্যবহারকারী সম্পাদনা</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i><strong class="text-dark"><?= htmlspecialchars($getUserDetailsQRW['full_name'] ?? $getUserDetailsQRW['user_id']) ?></strong> এর তথ্য সম্পাদনা</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.user-form-card { border-radius: 0.75rem; }
.user-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .user-form-card .card-body { padding: 1rem; }
}

.user-section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    margin: 28px 0 20px;
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-left: 3px solid var(--sec-accent, #6c5ce7);
    border-radius: 0.6rem;
}
.user-section-header:first-of-type { margin-top: 0; }
.user-section-header[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.user-section-header[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }

.user-section-header .section-num {
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
}
.user-section-header .section-text { flex: 1; min-width: 0; }
.user-section-header .section-title {
    font-size: 0.98rem;
    font-weight: 600;
    color: #2c2e3a;
    margin: 0;
    line-height: 1.3;
}
.user-section-header .section-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 2px;
    display: block;
}
.user-section-header .section-icon {
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

.user-form-card .col-form-label {
    font-size: 0.85rem;
    color: #3a3d53;
    font-weight: 500;
}
.user-form-card .form-control:focus,
.user-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.user-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}

/* Employee preview card — read-only display of employee_list data */
.emp-preview-card {
    background: #f7f8fc;
    border: 1px solid #e0e4ee;
    border-radius: 0.65rem;
    padding: 1rem 1.15rem;
}
.emp-preview-caption {
    font-size: 0.7rem;
    color: #8a90a6;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 0.15rem;
}
.emp-preview-value {
    font-size: 0.92rem;
    color: #2c2e3a;
    font-weight: 500;
    line-height: 1.4;
    word-break: break-word;
}
.emp-preview-hint {
    font-size: 0.78rem;
    color: #6b7280;
    border-top: 1px dashed #d8dce8;
}


/* Current signature preview */
.sig-preview-tile {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-radius: 0.45rem;
    padding: 6px 10px;
    margin-top: 0.5rem;
}
.sig-preview-tile img {
    max-height: 40px;
    border-radius: 0.3rem;
    background: #fff;
}
.sig-preview-tile small {
    color: #5d6580;
    font-size: 0.78rem;
}

.user-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

@media (max-width: 575px) {
    .user-section-header { padding: 12px 14px; gap: 10px; }
    .user-section-header .section-icon { display: none; }
    .user-section-header .section-num { width: 26px; height: 26px; font-size: 0.8rem; }
    .user-section-header .section-title { font-size: 0.92rem; }
}
</style>

<!-- User Edit Form Card -->
<div class="card user-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form" enctype="multipart/form-data">
            <input type="hidden" name="dataID" value="<?= $dataID ?>">

            <!-- ───── Section 1: Employee selection ───── -->
            <div class="user-section-header" data-color="indigo">
                <div class="section-num">১</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মচারী নির্বাচন</h6>
                    <span class="section-sub">নাম, পদবী, ইমেইল, মোবাইল — সব তথ্য কর্মচারী তালিকা থেকে নেওয়া হবে</span>
                </div>
                <span class="section-icon"><i class="ti tabler-user"></i></span>
            </div>

            <?php
            // Build a quick lookup from employee_list. Active employees in the
            // admin's org show by default; the currently-assigned employee is
            // ALWAYS included even if they're inactive OR from a different
            // organization — otherwise the dropdown can't display the name of
            // who's already linked to this user.
            $currentEmpID = (int)($getUserDetailsQRW['employee_id'] ?? 0);
            $allEmpsQ = mysqli_query($con,
                "SELECT el.id, el.employee_id, el.employee_name, el.email, el.mobileNo,
                        el.employment_status, el.organization_id,
                        jt.job_title_name
                 FROM employee_list el
                 LEFT JOIN job_title jt ON el.designation = jt.id
                 WHERE el.id = $currentEmpID
                    OR (el.organization_id = $orgID AND el.employment_status = 1)
                 ORDER BY el.employee_name ASC");
            $allEmployeesForDropdown = [];
            while ($r = mysqli_fetch_assoc($allEmpsQ)) { $allEmployeesForDropdown[] = $r; }
            ?>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    কর্মচারী <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single select2" style="width: 100%;" name="employeeID" id="employeeID" required>
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                        <?php foreach ($allEmployeesForDropdown as $emp):
                            $isInactive = ((int)($emp['employment_status'] ?? 0) !== 1);
                        ?>
                            <option value='<?= (int)$emp['id'] ?>'
                                <?= ($getUserDetailsQRW['employee_id'] == $emp['id']) ? 'selected' : '' ?>
                                data-name='<?= htmlspecialchars($emp['employee_name'] ?? '', ENT_QUOTES) ?>'
                                data-designation='<?= htmlspecialchars($emp['job_title_name'] ?? '', ENT_QUOTES) ?>'
                                data-email='<?= htmlspecialchars($emp['email'] ?? '', ENT_QUOTES) ?>'
                                data-mobile='<?= htmlspecialchars($emp['mobileNo'] ?? '', ENT_QUOTES) ?>'>
                                <?= Bengali_DTN($emp['employee_id']) . ' - ' . htmlspecialchars($emp['employee_name']) ?><?= $isInactive ? ' [নিষ্ক্রিয়]' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3" id="empPreviewRow" style="display:none;">
                <div class="col-md-3"></div>
                <div class="col-md-9">
                    <div class="emp-preview-card">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <div class="emp-preview-caption">পূর্ণ নাম</div>
                                <div class="emp-preview-value" id="prevName">—</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="emp-preview-caption">পদবী</div>
                                <div class="emp-preview-value" id="prevDesignation">—</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="emp-preview-caption">ইমেইল</div>
                                <div class="emp-preview-value" id="prevEmail">—</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="emp-preview-caption">মোবাইল</div>
                                <div class="emp-preview-value" id="prevMobile">—</div>
                            </div>
                        </div>
                        <div class="emp-preview-hint mt-3 pt-2">
                            <i class="ti tabler-info-circle me-1"></i>এই তথ্যগুলো কর্মচারী তালিকা থেকে স্বয়ংক্রিয়ভাবে নেওয়া হচ্ছে — পরিবর্তন করতে কর্মচারী তালিকা থেকে এডিট করুন
                        </div>
                    </div>
                </div>
            </div>

            <!-- ───── Section 2: Account Info ───── -->
            <div class="user-section-header" data-color="green">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">অ্যাকাউন্টের তথ্য</h6>
                    <span class="section-sub">লগইন ক্রেডেনশিয়াল, স্বাক্ষর ও অ্যাক্সেস গ্রুপ</span>
                </div>
                <span class="section-icon"><i class="ti tabler-lock"></i></span>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="user_id">
                    ইউজারনেম <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-at"></i></span>
                        <input type="text" id="user_id" class="form-control" placeholder="ইউজারনেম লিখুন" name="user_id" value="<?= htmlspecialchars($getUserDetailsQRW['user_id']) ?>" required>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="password">নতুন পাসওয়ার্ড</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-lock"></i></span>
                        <input type="password" id="password" class="form-control" placeholder="নতুন পাসওয়ার্ড (খালি রাখলে পূর্বের বহাল)" name="password">
                        <span class="input-group-text" id="togglePassword" style="cursor:pointer;">
                            <i class="ti tabler-eye-off" id="eyeIcon"></i>
                        </span>
                    </div>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>খালি রাখলে পূর্বের পাসওয়ার্ড বহাল থাকবে</small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="signature">স্বাক্ষর</label>
                <div class="col-md-9">
                    <input type="file" id="signature" class="form-control" name="signature" accept="image/jpeg,image/png">
                    <?php if (!empty($getUserDetailsQRW['signature'])): ?>
                        <div class="sig-preview-tile">
                            <img src="data:image/jpg;charset=utf8;base64,<?= base64_encode($getUserDetailsQRW['signature']) ?>" alt="স্বাক্ষর">
                            <small><i class="ti tabler-paperclip me-1"></i>বর্তমান স্বাক্ষর</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="user_group_ids">
                    ব্যবহারকারী গ্রুপ <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select id="user_group_ids" class="form-control select2" name="user_group_ids[]" multiple="multiple" required>
                        <?php foreach ($allGroups as $g): ?>
                            <option value='<?= $g['id'] ?>' <?= in_array((int)$g['id'], $assignedGroupIds, true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['group_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>একাধিক গ্রুপ নির্বাচন করুন — ব্যবহারকারী লগইন করার পর রোল পরিবর্তন করতে পারবেন</small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="default_group_id">
                    ডিফল্ট গ্রুপ <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select id="default_group_id" class="form-control select2" name="default_group_id" required>
                        <?php foreach ($allGroups as $g): if (!in_array((int)$g['id'], $assignedGroupIds, true)) continue; ?>
                            <option value='<?= $g['id'] ?>' <?= ((int)$g['id'] === $currentDefaultGroupId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['group_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>লগইন করার সময় ব্যবহারকারী যে গ্রুপে থাকবেন</small>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="user-form-actions d-flex gap-2 justify-content-end">
                <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </a>
                <button type="submit" class="btn btn-primary submitBtn px-4">
                    <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Wait for libs (jQuery, Select2, Swal) before binding — keeps the script
// inside the turbo-frame so it re-runs on Turbo navigation.
(function bootUserEdit() {
    if (typeof jQuery === 'undefined' || !jQuery.fn ||
        !jQuery.fn.select2 || typeof Swal === 'undefined') {
        return setTimeout(bootUserEdit, 20);
    }
    // DOM is already ready when this fires (libs are loaded by footer below).
    // Skip $(document).ready() — its callback queue can be quirky on Turbo nav.

    // Select2 on this `<select>` is initialized by the footer's global
    // initializePageComponents() (it picks up `.select2` class elements).
    // Capture the server-rendered selected value NOW so we can re-apply it
    // after the global init runs (footer destroys/recreates Select2 widgets
    // on every Turbo nav, which can drop the pre-selected option).
    var $empSel = $('.js-example-basic-single');
    var preselectedEmp = $empSel.find('option[selected]').attr('value') || $empSel.val() || '';

    // Read-only preview of employee info — source-of-truth is employee_list.
    function refreshEmpPreview() {
        var $opt = $empSel.find('option:selected');
        if (!$opt.val()) { $('#empPreviewRow').hide(); return; }
        $('#prevName').text($opt.data('name') || '—');
        $('#prevDesignation').text($opt.data('designation') || '—');
        $('#prevEmail').text($opt.data('email') || '—');
        $('#prevMobile').text($opt.data('mobile') || '—');
        $('#empPreviewRow').show();
    }
    $empSel.on('change', refreshEmpPreview);
    refreshEmpPreview();

    // Re-apply the pre-selected employee AFTER footer's global Select2 init runs.
    // Footer schedules its init via setTimeout(..., 50) on turbo:load; we wait
    // a touch longer so our val() takes effect on the freshly built widget.
    if (preselectedEmp) {
        setTimeout(function () {
            $empSel.val(preselectedEmp).trigger('change');
        }, 120);
    }

    $('#user_group_ids').select2({
        placeholder: "-- গ্রুপ নির্বাচন করুন --",
        allowClear: true,
        width: '100%'
    });

    $('#default_group_id').select2({
        placeholder: "-- ডিফল্ট গ্রুপ নির্বাচন করুন --",
        width: '100%'
    });

    // Sync default-group dropdown options with selected groups in the multi-select.
    function syncDefaultGroupOptions() {
        var $multi   = $('#user_group_ids');
        var $default = $('#default_group_id');
        var selectedIds = $multi.val() || [];
        var prevDefault = $default.val();

        $default.empty();
        if (selectedIds.length === 0) {
            $default.append('<option value="">-- প্রথমে গ্রুপ নির্বাচন করুন --</option>');
        } else {
            $default.append('<option value="">-- ডিফল্ট গ্রুপ নির্বাচন করুন --</option>');
            selectedIds.forEach(function (id) {
                var label = $multi.find('option[value="' + id + '"]').text();
                $default.append('<option value="' + id + '">' + $('<div>').text(label).html() + '</option>');
            });
            if (selectedIds.indexOf(prevDefault) !== -1) {
                $default.val(prevDefault);
            } else {
                $default.val(selectedIds[0]);
            }
        }
        $default.trigger('change.select2');
    }
    $('#user_group_ids').on('change', syncDefaultGroupOptions);

    // Password show/hide
    $('#togglePassword').on('click', function() {
        var $pwd = $('#password');
        var t = $pwd.attr('type') === 'password' ? 'text' : 'password';
        $pwd.attr('type', t);
        $('#eyeIcon').toggleClass('tabler-eye-off tabler-eye');
    });

    $('#form').on("submit", function(e){
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: '../../api/users/update.php',
            data: new FormData(this),
            dataType: 'html',
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function(){
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>সংরক্ষণ হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(data){
                if(data == 0){
                    Swal.fire({
                        title: 'ত্রুটি',
                        text: 'ব্যবহারকারী আপডেট করতে ব্যর্থ হয়েছে!',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'সম্পন্ন',
                        text: 'ব্যবহারকারী সফলভাবে আপডেট করা হয়েছে',
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(() => {
                        window.location.href = 'manage.php?menuslug=<?= $menuslug ?>';
                    });
                }
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'একটি ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
                $('#form').css("opacity", "");
                $('.submitBtn').removeAttr("disabled");
                $('.submitBtn').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
            }
        });
    });

})(); // end bootUserEdit
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
