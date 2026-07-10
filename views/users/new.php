<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

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

// Detect Super Admin (user_group_id=1) — they can create users for ANY center
$_actorStmt = mysqli_prepare($con, "SELECT user_group_id FROM user_list WHERE user_id = ? LIMIT 1");
$_un = $_SESSION['username'] ?? '';
mysqli_stmt_bind_param($_actorStmt, 's', $_un);
mysqli_stmt_execute($_actorStmt);
$_actorRow = mysqli_fetch_assoc(mysqli_stmt_get_result($_actorStmt)) ?: [];
mysqli_stmt_close($_actorStmt);
$_isSuperAdmin = ((int)($_actorRow['user_group_id'] ?? 0) === 1);

// Resolve organization_id for current user (used by non-Super-Admin)
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

// Super Admin → all centers; others → own center only
$_orgWhere = $_isSuperAdmin ? "" : " AND el.organization_id = $orgID ";
$getEmployeeListQ = mysqli_query($con, "SELECT el.*, jt.job_title_name, o.organization_name
    FROM employee_list el
    LEFT JOIN job_title    jt ON el.designation = jt.id
    LEFT JOIN organization o  ON el.organization_id = o.id
    WHERE el.employment_status = 1
      $_orgWhere
    ORDER BY o.organization_name ASC, el.employee_name ASC");

// Center list for Super Admin filter dropdown
$_centerListQ = $_isSuperAdmin
    ? mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY (id=4) DESC, organization_name ASC")
    : null;

// Fetch user groups for dropdown.
// Excluded from the regular user form:
//   - Super Admin (id=1) — system-level role, not assignable via UI
//   - Center Admin (id=2) — legacy, soft-deleted; managed via center-admin page
//   - Regional Super Admin (id=7) + Regional Op. Admin (id=8) — managed via
//     the role-approval workflow on the center edit page, with attachment +
//     approver sign-off (kept out of the manual user form on purpose).
$getUserGroupsQ = mysqli_query($con, "SELECT * FROM user_group WHERE deleted = 0 AND id NOT IN (1, 2, 7, 8) ORDER BY display_order ASC");

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-user');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-plus me-2 text-primary"></i>নতুন ব্যবহারকারী</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>একজন কর্মচারীর জন্য নতুন লগইন অ্যাকাউন্ট তৈরি করুন</div>
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

.user-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
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

@media (max-width: 575px) {
    .user-section-header { padding: 12px 14px; gap: 10px; }
    .user-section-header .section-icon { display: none; }
    .user-section-header .section-num { width: 26px; height: 26px; font-size: 0.8rem; }
    .user-section-header .section-title { font-size: 0.92rem; }
}
</style>

<!-- User Form Card -->
<div class="card user-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form">
            <!-- ───── Section 1: Employee selection ───── -->
            <div class="user-section-header" data-color="indigo">
                <div class="section-num">১</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মচারী নির্বাচন</h6>
                    <span class="section-sub">নাম, পদবী, ইমেইল, মোবাইল — সব তথ্য কর্মচারী তালিকা থেকে নেওয়া হবে</span>
                </div>
                <span class="section-icon"><i class="ti tabler-user"></i></span>
            </div>

            <?php if ($_isSuperAdmin): ?>
            <!-- Super Admin: center filter dropdown -->
            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="centerFilter">
                    কেন্দ্র ফিল্টার
                </label>
                <div class="col-md-9">
                    <select class="form-select" id="centerFilter">
                        <option value="">— সকল কেন্দ্র —</option>
                        <?php while($_c = mysqli_fetch_assoc($_centerListQ)): ?>
                            <option value="<?= (int)$_c['id'] ?>"><?= htmlspecialchars($_c['organization_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <small class="text-muted mt-1 d-block">
                        <i class="ti tabler-info-circle me-1"></i>Super Admin হিসেবে যেকোনো কেন্দ্রের কর্মচারীর জন্য user তৈরি করতে পারবেন
                    </small>
                </div>
            </div>
            <?php endif; ?>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="employeeID">
                    কর্মচারী <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <select class="js-example-basic-single select2" style="width: 100%;" name="employeeID" id="employeeID" required>
                        <option value=''>-- কর্মচারী নির্বাচন করুন --</option>
                        <?php while($empRow = mysqli_fetch_array($getEmployeeListQ)){ ?>
                        <option value='<?= $empRow['id'] ?>'
                            data-name='<?= htmlspecialchars($empRow['employee_name'], ENT_QUOTES) ?>'
                            data-designation='<?= htmlspecialchars($empRow['job_title_name'] ?? '', ENT_QUOTES) ?>'
                            data-email='<?= htmlspecialchars($empRow['email'] ?? '', ENT_QUOTES) ?>'
                            data-mobile='<?= htmlspecialchars($empRow['mobileNo'] ?? '', ENT_QUOTES) ?>'
                            data-center='<?= (int)($empRow['organization_id'] ?? 0) ?>'
                            data-center-name='<?= htmlspecialchars($empRow['organization_name'] ?? '', ENT_QUOTES) ?>'>
                            <?= Bengali_DTN($empRow['employee_id']).' - '.htmlspecialchars($empRow['employee_name']) ?><?php if ($_isSuperAdmin && !empty($empRow['organization_name'])): ?> <span style="color:#8a90a6;">[<?= htmlspecialchars($empRow['organization_name']) ?>]</span><?php endif; ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <!-- Preview card — read-only display of the employee's stored info.
                 Source of truth lives in employee_list; user_list copies will be
                 auto-synced on save. -->
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
                    <span class="section-sub">লগইন ক্রেডেনশিয়াল ও অ্যাক্সেস গ্রুপ</span>
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
                        <input type="text" id="user_id" class="form-control" placeholder="ইউজারনেম লিখুন" name="user_id" required>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="password">
                    পাসওয়ার্ড <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-lock"></i></span>
                        <input type="password" id="password" class="form-control" placeholder="পাসওয়ার্ড লিখুন" name="password" required>
                        <span class="input-group-text cursor-pointer" id="togglePassword" style="cursor:pointer;">
                            <i class="ti tabler-eye-off" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="user_group_ids">
                    ব্যবহারকারী গ্রুপ <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <?php
                    // Materialise groups so we can reuse for the default-group picker
                    $allGroups = [];
                    while ($groupRow = mysqli_fetch_array($getUserGroupsQ)) {
                        $allGroups[] = ['id' => $groupRow['id'], 'group_name' => $groupRow['group_name']];
                    }
                    ?>
                    <select id="user_group_ids" class="form-control select2" name="user_group_ids[]" multiple="multiple" required>
                        <?php foreach ($allGroups as $g): ?>
                        <option value='<?= $g['id'] ?>'><?= htmlspecialchars($g['group_name']) ?></option>
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
                        <option value="">-- প্রথমে গ্রুপ নির্বাচন করুন --</option>
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
// inside the turbo-frame so it re-runs on Turbo navigation (otherwise Select2
// stays uninitialised until a hard refresh).
(function bootUserNew() {
    if (typeof jQuery === 'undefined' || !jQuery.fn ||
        !jQuery.fn.select2 || typeof Swal === 'undefined') {
        return setTimeout(bootUserNew, 20);
    }

$(document).ready(function(){
    // Note: Select2 on `.js-example-basic-single` is initialized by the footer's
    // global initializePageComponents() (picks up `.select2` class). Here we
    // only wire the preview card + change handler.
    var $empSel = $('.js-example-basic-single');

    function refreshEmpPreview() {
        var $opt = $empSel.find('option:selected');
        if (!$opt.val()) {
            $('#empPreviewRow').hide();
            return;
        }
        $('#prevName').text($opt.data('name') || '—');
        $('#prevDesignation').text($opt.data('designation') || '—');
        $('#prevEmail').text($opt.data('email') || '—');
        $('#prevMobile').text($opt.data('mobile') || '—');
        $('#empPreviewRow').show();
    }
    $empSel.on('change', refreshEmpPreview);
    refreshEmpPreview();

    // Super Admin: center filter for employee dropdown
    var $centerFilter = $('#centerFilter');
    if ($centerFilter.length) {
        // Cache all original options so we can re-show them on reset
        var allOptions = $empSel.find('option').map(function() {
            return {
                $el: $(this),
                center: $(this).data('center') || ''
            };
        }).get();

        $centerFilter.on('change', function() {
            var selectedCenter = $(this).val();
            // Detach all options first, then re-append matching ones
            $empSel.empty();
            allOptions.forEach(function(opt) {
                if (!selectedCenter || String(opt.center) === String(selectedCenter) || !opt.$el.val()) {
                    // Always keep the placeholder (empty value) and matching center
                    $empSel.append(opt.$el.clone());
                }
            });
            // Reset value + refresh Select2
            $empSel.val('').trigger('change');
            if ($.fn.select2) {
                $empSel.select2('destroy');
                $empSel.select2({ placeholder: '-- কর্মচারী নির্বাচন করুন --', allowClear: true, width: '100%' });
            }
        });
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
    // When the user picks groups, only those become candidates for "default".
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
            // Preserve previous selection if still applicable, otherwise pick first
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
            url: '../../api/users/insert.php',
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
                        text: 'ব্যবহারকারী সংরক্ষণ করতে ব্যর্থ হয়েছে!',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: 'সম্পন্ন',
                        text: 'ব্যবহারকারী সফলভাবে সংরক্ষণ করা হয়েছে',
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
});

})(); // end bootUserNew
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
