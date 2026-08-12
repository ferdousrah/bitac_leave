<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Get center ID from query string
$dataID   = isset($_GET['dataID']) ? intval($_GET['dataID']) : 0;
$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-center');

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

// Fetch center details using prepared statement
$stmt = $con->prepare("SELECT * FROM organization WHERE id = ? AND deleted = 0");
$stmt->bind_param("i", $dataID);
$stmt->execute();
$result = $stmt->get_result();
$centerData = $result->fetch_assoc();
$stmt->close();

if (!$centerData) {
    echo "<script>
        Swal.fire({
            title: 'ত্রুটি',
            text: 'কেন্দ্র খুঁজে পাওয়া যায়নি!',
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

// ── Regional roles config ───────────────────────────────────────────
// We're moving from legacy "Center Admin (isCenterAdmin=1)" to two separate
// approval-gated roles: Regional Super Admin (group_id=7) and Regional Op. Admin (group_id=8).
$REGIONAL_ROLES = [
    7 => ['label' => 'Regional Super Admin', 'icon' => 'tabler-shield-star'],
    8 => ['label' => 'Regional Op. Admin',   'icon' => 'tabler-shield-half'],
];

/**
 * For a given role+org, fetch the currently-active assignment row joined to user/employee info.
 * "Active" = effective_to IS NULL (still in tenure).
 */
function fetchActiveRegionalAdmin(mysqli $con, int $orgID, int $roleID): ?array {
    $stmt = $con->prepare(
        "SELECT ul.dataID AS user_dataID, ul.user_id, ul.full_name,
                el.id AS employee_dataID, el.employee_id AS employee_no, el.employee_name,
                jt.job_title_name,
                uga.effective_from
         FROM user_group_assignment uga
         INNER JOIN user_list ul ON uga.user_id = ul.dataID
         LEFT JOIN employee_list el ON ul.employee_id = el.id
         LEFT JOIN job_title jt ON el.designation = jt.id
         WHERE uga.group_id = ?
           AND uga.effective_to IS NULL
           AND el.organization_id = ?
         ORDER BY uga.effective_from DESC LIMIT 1"
    );
    $stmt->bind_param("ii", $roleID, $orgID);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Fetch the most recent PENDING proposal for a role+org (or null).
 */
function fetchPendingProposal(mysqli $con, int $orgID, int $roleID): ?array {
    $stmt = $con->prepare(
        "SELECT rap.*, el.employee_name, jt.job_title_name,
                pby.full_name AS proposed_by_name
         FROM role_assignment_proposal rap
         LEFT JOIN employee_list el ON rap.employee_id = el.id
         LEFT JOIN job_title jt ON el.designation = jt.id
         LEFT JOIN user_list pby ON rap.proposed_by = pby.dataID
         WHERE rap.organization_id = ? AND rap.role_id = ? AND rap.status = 0
         ORDER BY rap.createdAt DESC LIMIT 1"
    );
    $stmt->bind_param("ii", $orgID, $roleID);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$regionalState = [];
foreach ($REGIONAL_ROLES as $rid => $meta) {
    $regionalState[$rid] = [
        'meta'    => $meta,
        'active'  => fetchActiveRegionalAdmin($con, $dataID, $rid),
        'pending' => fetchPendingProposal($con, $dataID, $rid),
    ];
}

// Employees of this center — for the proposal employee dropdown.
// LEFT JOIN user_list so we know which employees already have a login account.
// Those will not require username/password fields in the proposal — approval
// will just add the role to their existing user_group_assignment instead of
// creating a duplicate user.
// MIN() + GROUP BY collapses duplicates when an employee has multiple user_list
// rows tied to them (rare but possible from legacy data).
$empStmt = $con->prepare(
    "SELECT el.id, el.employee_id, el.employee_name, jt.job_title_name,
            MIN(ul.dataID)  AS existing_user_id,
            MIN(ul.user_id) AS existing_username
     FROM employee_list el
     LEFT JOIN job_title jt ON el.designation = jt.id
     LEFT JOIN user_list ul ON ul.employee_id = el.id
     WHERE el.organization_id = ? AND el.employment_status = 1
     GROUP BY el.id, el.employee_id, el.employee_name, jt.job_title_name
     ORDER BY el.employee_name ASC"
);
$empStmt->bind_param("i", $dataID);
$empStmt->execute();
$centerEmployees = $empStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$empStmt->close();

// Is the role-assignment approver configured?
$approverStmt = $con->prepare(
    "SELECT rac.approver_user_id, ul.full_name, el.employee_name
     FROM role_approver_config rac
     LEFT JOIN user_list ul ON rac.approver_user_id = ul.dataID
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     ORDER BY rac.dataID DESC LIMIT 1"
);
$approverStmt->execute();
$approverRow = $approverStmt->get_result()->fetch_assoc();
$approverStmt->close();
$approverConfigured = $approverRow && !empty($approverRow['approver_user_id']);
$approverDisplayName = $approverRow ? ($approverRow['full_name'] ?: $approverRow['employee_name'] ?: '') : '';
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-building-cog me-2 text-primary"></i>কেন্দ্র সম্পাদনা</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i><strong class="text-dark"><?= htmlspecialchars($centerData['organization_name']) ?></strong> এর তথ্য সম্পাদনা</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
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
    flex-wrap: wrap;
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
    flex-shrink: 0;
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
.simple-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 0.5rem;
}
.admin-status-pill {
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.35em 0.75em;
    border-radius: 0.4rem;
}
.admin-status-pill.exists { background: #e6f7ee; color: #1a7e44; }
.admin-status-pill.missing { background: #fff3e1; color: #b8651a; }
</style>

<!-- Center Edit Form Card -->
<div class="card simple-form-card shadow-sm border-0">
    <div class="card-body">
        <!-- Status Message -->
        <div class="statusMsg" style="display:none;"></div>

        <form class="form-login" name="form" id="form">
            <input type="hidden" name="dataID" value="<?= $dataID ?>">

            <!-- Section header -->
            <div class="form-section-header">
                <span class="section-icon-tile"><i class="ti tabler-building"></i></span>
                <h6 class="section-title">কেন্দ্রের তথ্য</h6>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="organization_name">
                    কেন্দ্রের নাম <span class="text-danger">*</span>
                </label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-building"></i></span>
                        <input type="text" id="organization_name" class="form-control" value="<?= htmlspecialchars($centerData['organization_name']) ?>" name="organization_name" required>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="address">ঠিকানা</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-map-pin"></i></span>
                        <textarea id="address" class="form-control" placeholder="প্রতিষ্ঠানের সম্পূর্ণ ঠিকানা লিখুন" name="address" rows="3"><?= htmlspecialchars($centerData['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-md-3 col-form-label" for="phone">ফোন নম্বর</label>
                <div class="col-md-9">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-phone"></i></span>
                        <input type="text" id="phone" class="form-control" placeholder="উদাহরণ: ০২-৯৩৩৬৮৮৮" value="<?= htmlspecialchars($centerData['phone'] ?? '') ?>" name="phone">
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="simple-form-actions d-flex gap-2 justify-content-end">
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

<?php if (!$approverConfigured): ?>
<div class="alert alert-warning mt-4">
    <i class="ti tabler-alert-triangle me-1"></i>
    এই কেন্দ্রের জন্য Regional Admin propose করতে হলে প্রথমে
    <a href="<?= BASE_URL ?>/views/signatory/role-approver.php?menuslug=leave-settings" data-turbo="true">
        রোল অনুমোদনকারী</a>
    নির্ধারণ করুন।
</div>
<?php endif; ?>

<?php foreach ($regionalState as $roleID => $st):
    $meta    = $st['meta'];
    $active  = $st['active'];
    $pending = $st['pending'];
?>
<!-- <?= htmlspecialchars($meta['label']) ?> Card -->
<div class="card simple-form-card shadow-sm border-0 mt-4">
    <div class="card-body">
        <!-- Section header -->
        <div class="form-section-header">
            <span class="section-icon-tile"><i class="ti <?= htmlspecialchars($meta['icon']) ?>"></i></span>
            <h6 class="section-title"><?= htmlspecialchars($meta['label']) ?></h6>
            <?php if ($active): ?>
                <span class="admin-status-pill exists ms-auto"><i class="ti tabler-circle-check me-1"></i>সক্রিয়</span>
            <?php elseif ($pending): ?>
                <span class="admin-status-pill missing ms-auto" style="background:#fff3cd;color:#7a5400;"><i class="ti tabler-clock me-1"></i>অপেক্ষমান</span>
            <?php else: ?>
                <span class="admin-status-pill missing ms-auto"><i class="ti tabler-alert-triangle me-1"></i>নির্ধারিত নেই</span>
            <?php endif; ?>
        </div>

        <?php if ($active): ?>
            <!-- Active admin display -->
            <div class="current-admin-display">
                <div class="row mb-2">
                    <div class="col-md-3 col-form-label text-muted">কর্মকর্তা</div>
                    <div class="col-md-9 col-form-label">
                        <strong><?= htmlspecialchars($active['employee_name'] ?: $active['full_name']) ?></strong>
                        <?php if (!empty($active['job_title_name'])): ?>
                            <span class="text-muted">— <?= htmlspecialchars($active['job_title_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3 col-form-label text-muted">ইউজারনেম</div>
                    <div class="col-md-9 col-form-label"><code><?= htmlspecialchars($active['user_id']) ?></code></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 col-form-label text-muted">দায়িত্বে যোগদান</div>
                    <div class="col-md-9 col-form-label">
                        <?= $active['effective_from'] ? date('d/m/Y', strtotime($active['effective_from'])) : '—' ?>
                    </div>
                </div>
                <div class="text-muted small mt-3 mb-3">
                    <i class="ti tabler-info-circle me-1"></i>পরিবর্তন করতে চাইলে নিচ থেকে নতুন প্রস্তাব দিন। Approve হলে বর্তমান দায়িত্ব শেষ হয়ে নতুন জন দায়িত্ব নেবেন।
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pending): ?>
            <div class="alert alert-warning">
                <i class="ti tabler-clock me-1"></i>
                <strong><?= htmlspecialchars($pending['employee_name'] ?: $pending['proposed_full_name']) ?></strong>
                এর জন্য একটি প্রস্তাব ইতিমধ্যে অপেক্ষমান —
                <?= htmlspecialchars($pending['proposed_by_name'] ?: '—') ?> কর্তৃক
                <?= date('d/m/Y', strtotime($pending['createdAt'])) ?> তারিখে দেওয়া হয়েছে।
                অনুমোদনকারী সিদ্ধান্ত না দেওয়া পর্যন্ত নতুন প্রস্তাব দেওয়া যাবে না।
            </div>
        <?php elseif ($approverConfigured): ?>
            <!-- Proposal form -->
            <form class="roleProposalForm" enctype="multipart/form-data">
                <input type="hidden" name="organization_id" value="<?= $dataID ?>">
                <input type="hidden" name="role_id" value="<?= $roleID ?>">

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label">
                        কর্মকর্তা <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <select class="form-control select2 employeeSelect" name="employee_id" required>
                            <option value="">-- কর্মকর্তা নির্বাচন করুন --</option>
                            <?php foreach ($centerEmployees as $emp):
                                $optLabel = '';
                                if (!empty($emp['employee_id'])) $optLabel .= '(' . $emp['employee_id'] . ') ';
                                $optLabel .= $emp['employee_name'];
                                if (!empty($emp['job_title_name'])) $optLabel .= ' — ' . $emp['job_title_name'];
                                if (!empty($emp['existing_user_id'])) $optLabel .= ' [অ্যাকাউন্ট আছে]';
                            ?>
                            <option value="<?= (int)$emp['id'] ?>"
                                    data-name="<?= htmlspecialchars($emp['employee_name'], ENT_QUOTES) ?>"
                                    data-has-user="<?= !empty($emp['existing_user_id']) ? '1' : '0' ?>"
                                    data-existing-username="<?= htmlspecialchars($emp['existing_username'] ?? '', ENT_QUOTES) ?>">
                                <?= htmlspecialchars($optLabel) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted mt-1 d-block">
                            <i class="ti tabler-info-circle me-1"></i>এই কেন্দ্রের active কর্মকর্তাদের তালিকা। [অ্যাকাউন্ট আছে] মানে ব্যবহারকারীর ইতিমধ্যে লগইন আছে — তখন শুধু role যুক্ত হবে।
                        </small>
                    </div>
                </div>

                <!-- Existing-user banner — shown only when picked employee already has a login -->
                <div class="row mb-3 existingUserBanner" style="display:none;">
                    <div class="col-md-3"></div>
                    <div class="col-md-9">
                        <div class="alert alert-info mb-0" style="background:#e8f4ff;border-color:#bbdcff;color:#1c5aa3;">
                            <i class="ti tabler-info-circle me-1"></i>
                            এই কর্মকর্তার অ্যাকাউন্ট ইতিমধ্যে আছে (<code class="existingUsernameLabel"></code>) — অনুমোদন হলে শুধু এই role যুক্ত হবে, নতুন অ্যাকাউন্ট তৈরি হবে না।
                        </div>
                    </div>
                </div>

                <!-- Username + password fields — only required for NEW user creation -->
                <div class="newUserFields">
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">
                            ইউজারনেম <span class="text-danger">*</span>
                        </label>
                        <div class="col-md-9">
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-at"></i></span>
                                <input type="text" class="form-control usernameInput" name="username"
                                    placeholder="ইউজারনেম লিখুন" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">
                            পাসওয়ার্ড <span class="text-danger">*</span>
                        </label>
                        <div class="col-md-9">
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-lock"></i></span>
                                <input type="password" class="form-control passwordInput" name="password"
                                    placeholder="পাসওয়ার্ড লিখুন" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label">
                        সংযুক্তি (অফিস আদেশ) <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <input type="file" class="form-control" name="attachment"
                               accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted mt-1 d-block">
                            <i class="ti tabler-info-circle me-1"></i>PDF, JPG, PNG সর্বোচ্চ 2MB
                        </small>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 col-form-label">মন্তব্য</label>
                    <div class="col-md-9">
                        <textarea class="form-control" name="note" rows="2"
                            placeholder="প্রস্তাবের কারণ / নোট (ঐচ্ছিক)"></textarea>
                    </div>
                </div>

                <div class="simple-form-actions d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary proposalBtn px-4">
                        <i class="ti tabler-send me-1"></i>অনুমোদনের জন্য প্রস্তাব পাঠান
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
// Wait for libs (loaded by footer_vuexy AFTER this script on hard load) before
// binding handlers. On Turbo nav libs are already in memory so this runs
// immediately. Keeping the script inside the turbo-frame ensures re-binding
// on Turbo navigation.
(function bootCenterEdit() {
    if (typeof jQuery === 'undefined' || !jQuery.fn ||
        !jQuery.fn.select2 || typeof Swal === 'undefined') {
        return setTimeout(bootCenterEdit, 20);
    }

$(document).ready(function(){
    // Init Select2 for the employee dropdowns in role-proposal forms
    $('.employeeSelect').select2({
        placeholder: '-- কর্মকর্তা নির্বাচন করুন --',
        allowClear: true,
        width: '100%'
    });

    // When an employee is picked, toggle username/password visibility based on
    // whether they already have a login account. data-has-user=1 means an
    // existing user_list row → no need to create one, no need for new username/password.
    function syncProposalFormForEmployee($form) {
        var $select       = $form.find('.employeeSelect');
        var $opt          = $select.find('option:selected');
        var hasUser       = $opt.data('has-user') == 1 || $opt.attr('data-has-user') === '1';
        var existingUname = $opt.attr('data-existing-username') || '';
        var $banner       = $form.find('.existingUserBanner');
        var $newFields    = $form.find('.newUserFields');
        var $unameInput   = $form.find('.usernameInput');
        var $pwdInput     = $form.find('.passwordInput');

        if (!$opt.val()) {
            // Nothing selected — show fields for now, require them
            $banner.hide();
            $newFields.show();
            $unameInput.prop('required', true);
            $pwdInput.prop('required', true);
            return;
        }

        if (hasUser) {
            $banner.find('.existingUsernameLabel').text(existingUname);
            $banner.show();
            $newFields.hide();
            // Clear values + drop the required attribute so the form can submit
            $unameInput.prop('required', false).val('');
            $pwdInput.prop('required', false).val('');
        } else {
            $banner.hide();
            $newFields.show();
            $unameInput.prop('required', true);
            $pwdInput.prop('required', true);
        }
    }

    $('.employeeSelect').on('change', function () {
        syncProposalFormForEmployee($(this).closest('form'));
    });
    // Sync on initial load too (in case browser preserves a selection across nav)
    $('.roleProposalForm').each(function () { syncProposalFormForEmployee($(this)); });

    // Role-assignment proposal form submit (used for both Regional Super Admin + Regional Op. Admin sections)
    $('.roleProposalForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('.proposalBtn');
        var orig  = $btn.html();
        $.ajax({
            type: 'POST',
            url: '../../api/role-assignment/propose.php',
            data: new FormData(this),
            dataType: 'json',
            contentType: false,
            processData: false,
            cache: false,
            beforeSend: function () {
                $btn.attr('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>প্রস্তাব পাঠানো হচ্ছে...');
            },
            success: function (resp) {
                if (resp && resp.status === 1) {
                    Swal.fire({
                        title: 'সম্পন্ন', text: resp.message || 'প্রস্তাব অনুমোদনের জন্য পাঠানো হয়েছে',
                        icon: 'success', confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    }).then(function () { location.reload(); });
                } else {
                    Swal.fire({
                        title: 'ত্রুটি', text: (resp && resp.message) || 'প্রস্তাব পাঠানো ব্যর্থ',
                        icon: 'error', confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                    $btn.removeAttr('disabled').html(orig);
                }
            },
            error: function () {
                Swal.fire({
                    title: 'ত্রুটি', text: 'সার্ভারের সাথে সংযোগ ব্যর্থ',
                    icon: 'error', confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                });
                $btn.removeAttr('disabled').html(orig);
            }
        });
    });

    $('#form').on("submit", function(e){
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: '../../api/center/update.php',
            data: new FormData(this),
            dataType: 'json',
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function(){
                $('.submitBtn').attr("disabled", "disabled");
                $('.submitBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>সংরক্ষণ হচ্ছে...');
                $('#form').css("opacity", ".5");
            },
            success: function(response){
                if(response.status == 1){
                    Swal.fire({
                        title: 'সম্পন্ন',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    }).then(() => {
                        window.location.href = 'manage.php?menuslug=<?= $menuslug ?>';
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

})(); // end bootCenterEdit
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
