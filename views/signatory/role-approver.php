<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-settings');

// HQ = বিটাক, প্রধান কার্যালয় = organization.id 4. Hard-coded here (and in
// save-role-approver.php) because matching by Bengali name proved fragile
// across encodings. Update this constant if HQ id ever changes per env.
const HQ_ORG_ID = 4;

$uStmt = $con->prepare(
    "SELECT ul.dataID, ul.user_id, ul.full_name,
            el.employee_name, el.employee_id AS emp_no,
            jt.job_title_name, o.organization_name
     FROM user_list ul
     INNER JOIN employee_list el ON ul.employee_id = el.id
     LEFT JOIN organization o ON el.organization_id = o.id
     LEFT JOIN job_title jt ON el.designation = jt.id
     WHERE el.organization_id = ? AND el.employment_status = 1
     ORDER BY el.employee_name ASC"
);
$hqOrgID = HQ_ORG_ID;
$uStmt->bind_param("i", $hqOrgID);
$uStmt->execute();
$eligibleUsers = $uStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$uStmt->close();

// Current approver config (single row)
$current = null;
$cStmt = $con->prepare(
    "SELECT rac.approver_user_id, rac.updatedAt, ul.user_id, ul.full_name, el.employee_name, jt.job_title_name
     FROM role_approver_config rac
     LEFT JOIN user_list ul ON rac.approver_user_id = ul.dataID
     LEFT JOIN employee_list el ON ul.employee_id = el.id
     LEFT JOIN job_title jt ON el.designation = jt.id
     ORDER BY rac.dataID DESC LIMIT 1"
);
$cStmt->execute();
$current = $cStmt->get_result()->fetch_assoc();
$cStmt->close();
$currentApproverId = (int)($current['approver_user_id'] ?? 0);
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-shield me-2 text-primary"></i>রোল অনুমোদনকারী</h4>
        <div class="text-muted small mt-1 ms-1">
            <i class="ti tabler-info-circle me-1"></i>
            Regional Super Admin / Regional Op. Admin assignment proposal যিনি approve করবেন — HQ (বিটাক, প্রধান কার্যালয়) এর একজন
        </div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.approver-card { border-radius: 0.75rem; }
.approver-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .approver-card .card-body { padding: 1rem; }
}
.current-approver-box {
    background: #f0edff;
    border: 1px solid #ddd5f6;
    border-left: 3px solid #6c5ce7;
    border-radius: 0.6rem;
    padding: 1rem 1.15rem;
    margin-bottom: 1.5rem;
}
.current-approver-box .ca-caption {
    font-size: 0.7rem; color: #6b6b80;
    letter-spacing: 0.04em; text-transform: uppercase;
    font-weight: 700; margin-bottom: 0.35rem;
}
.current-approver-box .ca-name {
    font-size: 1.02rem; color: #2c2e3a; font-weight: 600;
}
.current-approver-box .ca-meta {
    font-size: 0.82rem; color: #5d6580; margin-top: 0.2rem;
}
.no-approver-warning {
    background: #fff3cd;
    border: 1px solid #ffe69c;
    border-left: 3px solid #d97706;
    border-radius: 0.6rem;
    padding: 0.9rem 1.1rem;
    margin-bottom: 1.5rem;
    color: #7a5400;
    font-size: 0.88rem;
}
</style>

<div class="card approver-card shadow-sm border-0">
    <div class="card-body">
        <?php if ($current && !empty($current['full_name'])): ?>
            <div class="current-approver-box">
                <div class="ca-caption">বর্তমান অনুমোদনকারী</div>
                <div class="ca-name"><?= htmlspecialchars($current['full_name'] ?: $current['employee_name'] ?: $current['user_id']) ?></div>
                <?php if (!empty($current['job_title_name'])): ?>
                <div class="ca-meta"><?= htmlspecialchars($current['job_title_name']) ?></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-approver-warning">
                <i class="ti tabler-alert-triangle me-1"></i>কোনো অনুমোদনকারী নির্ধারিত নেই। নিচ থেকে নির্বাচন করে সংরক্ষণ করুন।
            </div>
        <?php endif; ?>

        <?php if (empty($eligibleUsers)): ?>
            <div class="alert alert-warning">
                <i class="ti tabler-alert-circle me-1"></i>
                বিটাক, প্রধান কার্যালয় এ কোনো active user পাওয়া যায়নি। আগে user create করুন।
            </div>
        <?php else: ?>
            <form id="approverForm">
                <div class="row mb-3">
                    <label class="col-md-3 col-form-label" for="approver_user_id">
                        অনুমোদনকারী নির্বাচন করুন <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-9">
                        <select id="approver_user_id" name="approver_user_id" class="form-control select2" required>
                            <option value="">-- নির্বাচন করুন --</option>
                            <?php foreach ($eligibleUsers as $u):
                                $label = '';
                                if (!empty($u['emp_no'])) $label .= '(' . $u['emp_no'] . ') ';
                                $label .= $u['full_name'] ?: $u['employee_name'];
                                if (!empty($u['job_title_name'])) $label .= ' — ' . $u['job_title_name'];
                                if (!empty($u['organization_name'])) $label .= ' · ' . $u['organization_name'];
                            ?>
                            <option value="<?= (int)$u['dataID'] ?>" <?= ((int)$u['dataID'] === $currentApproverId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted mt-1 d-block">
                            <i class="ti tabler-info-circle me-1"></i>সকল role assignment proposal এই ব্যক্তির কাছে যাবে
                        </small>
                    </div>
                </div>

                <div class="d-flex justify-content-end pt-2 border-top">
                    <button type="submit" class="btn btn-primary saveBtn px-4">
                        <i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function bootRoleApprover() {
    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2 || typeof Swal === 'undefined') {
        return setTimeout(bootRoleApprover, 20);
    }

    $(document).ready(function () {
        $('#approver_user_id').select2({
            placeholder: '-- নির্বাচন করুন --',
            width: '100%'
        });

        $('#approverForm').on('submit', function (e) {
            e.preventDefault();
            var $btn = $('.saveBtn');
            $btn.attr('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>সংরক্ষণ হচ্ছে...');
            $.ajax({
                type: 'POST',
                url: '../../api/signatory/save-role-approver.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({
                            title: 'সম্পন্ন', text: resp.message || 'সংরক্ষণ হয়েছে',
                            icon: 'success', confirmButtonColor: '#6c5ce7',
                            customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                        }).then(function () { window.location.reload(); });
                    } else {
                        Swal.fire({
                            title: 'ত্রুটি', text: (resp && resp.message) || 'ব্যর্থ হয়েছে',
                            icon: 'error', confirmButtonColor: '#ff3e1d',
                            customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                        });
                    }
                    $btn.removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
                },
                error: function () {
                    Swal.fire({
                        title: 'ত্রুটি', text: 'সার্ভারের সাথে সংযোগ ব্যর্থ',
                        icon: 'error', confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                    $btn.removeAttr('disabled').html('<i class="ti tabler-device-floppy me-1"></i>সংরক্ষণ করুন');
                }
            });
        });
    });

})(); // end bootRoleApprover
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
