<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'role-approval');

// Resolve current user
$meStmt = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? LIMIT 1");
$meStmt->bind_param("s", $_SESSION['username']);
$meStmt->execute();
$meRow = $meStmt->get_result()->fetch_assoc();
$meStmt->close();
$currentUserID = (int)($meRow['dataID'] ?? 0);

// Resolve configured approver
$apStmt = $con->prepare("SELECT approver_user_id FROM role_approver_config ORDER BY dataID DESC LIMIT 1");
$apStmt->execute();
$apRow = $apStmt->get_result()->fetch_assoc();
$apStmt->close();
$configuredApprover = (int)($apRow['approver_user_id'] ?? 0);

// Only the configured approver should see pending items in their queue
$isApprover = ($currentUserID > 0 && $currentUserID === $configuredApprover);

// Pending proposals visible to this approver
$proposals = [];
if ($isApprover) {
    $q = $con->prepare(
        "SELECT rap.*,
                o.organization_name,
                el.employee_name, el.employee_id AS emp_no,
                jt.job_title_name,
                ug.group_name AS role_name,
                pby.full_name AS proposed_by_name
         FROM role_assignment_proposal rap
         INNER JOIN organization o   ON rap.organization_id = o.id
         INNER JOIN employee_list el ON rap.employee_id = el.id
         LEFT JOIN job_title jt      ON el.designation = jt.id
         INNER JOIN user_group ug    ON rap.role_id = ug.id
         LEFT JOIN user_list pby     ON rap.proposed_by = pby.dataID
         WHERE rap.status = 0
         ORDER BY rap.createdAt ASC"
    );
    $q->execute();
    $proposals = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();
}
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-shield me-2 text-primary"></i>রোল অনুমোদন</h4>
        <div class="text-muted small mt-1 ms-1">
            <i class="ti tabler-info-circle me-1"></i>
            Regional Super Admin / Regional Op. Admin assignment proposal গুলো এখানে দেখুন
        </div>
    </div>
</div>

<style>
.proposal-card { border-radius: 0.75rem; border: 1px solid #eef0f5 !important; margin-bottom: 1rem; }
.proposal-card .card-body { padding: 1.25rem 1.5rem; }
.proposal-meta-row {
    display: flex; flex-wrap: wrap; gap: 1.25rem 2rem;
    margin-bottom: 0.85rem;
}
.proposal-meta-item .meta-caption {
    font-size: 0.66rem; color: #8a90a6;
    letter-spacing: 0.04em; text-transform: uppercase; font-weight: 600;
}
.proposal-meta-item .meta-value {
    font-size: 0.92rem; color: #2c2e3a; font-weight: 600; margin-top: 0.15rem;
}
.proposal-meta-item .meta-sub { font-size: 0.78rem; color: #5d6580; }
.role-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.25rem 0.7rem; border-radius: 999px;
    font-size: 0.74rem; font-weight: 600;
}
.role-badge.r7 { background: #f0edff; color: #5648c4; }
.role-badge.r8 { background: #e8f5e9; color: #1a7e44; }
.proposal-actions { display: flex; gap: 0.5rem; }
.attachment-link {
    display: inline-flex; align-items: center; gap: 0.35rem;
    color: #5648c4; font-weight: 500; font-size: 0.85rem; text-decoration: none;
}
.attachment-link:hover { text-decoration: underline; }
.empty-queue {
    background: #fafbfd; border: 1px dashed #c4c9d6; border-radius: 0.75rem;
    padding: 3rem 2rem; text-align: center; color: #5d6580;
}
.empty-queue i { font-size: 3rem; color: #c4c9d6; }
</style>

<?php if (!$isApprover): ?>
    <div class="alert alert-warning">
        <i class="ti tabler-alert-triangle me-1"></i>আপনি এই page এর জন্য অনুমোদিত নন। রোল অনুমোদনকারী হিসেবে নির্ধারিত ব্যক্তিই এই page এ proposal দেখতে পারবেন।
    </div>
<?php elseif (empty($proposals)): ?>
    <div class="empty-queue">
        <i class="ti tabler-clipboard-check"></i>
        <h6 class="mt-3 mb-1">কোনো অপেক্ষমান প্রস্তাব নেই</h6>
        <div class="small text-muted">এই মুহূর্তে কোনো role assignment proposal আপনার সিদ্ধান্তের জন্য অপেক্ষা করছে না</div>
    </div>
<?php else: ?>
    <?php foreach ($proposals as $p):
        $roleClass = ((int)$p['role_id'] === 7) ? 'r7' : 'r8';
    ?>
    <div class="card proposal-card shadow-none" data-proposal-id="<?= (int)$p['dataID'] ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <span class="role-badge <?= $roleClass ?>">
                    <i class="ti <?= (int)$p['role_id'] === 7 ? 'tabler-shield-star' : 'tabler-shield-half' ?>"></i>
                    <?= htmlspecialchars($p['role_name']) ?>
                </span>
                <small class="text-muted">
                    <i class="ti tabler-clock me-1"></i>
                    <?= date('d/m/Y H:i', strtotime($p['createdAt'])) ?>
                </small>
            </div>

            <div class="proposal-meta-row">
                <div class="proposal-meta-item">
                    <div class="meta-caption">কেন্দ্র</div>
                    <div class="meta-value"><?= htmlspecialchars($p['organization_name']) ?></div>
                </div>
                <div class="proposal-meta-item">
                    <div class="meta-caption">প্রস্তাবিত কর্মকর্তা</div>
                    <div class="meta-value">
                        <?php if (!empty($p['emp_no'])): ?>
                            <span class="text-muted">(<?= htmlspecialchars($p['emp_no']) ?>)</span>
                        <?php endif; ?>
                        <?= htmlspecialchars($p['employee_name']) ?>
                    </div>
                    <?php if (!empty($p['job_title_name'])): ?>
                    <div class="meta-sub"><?= htmlspecialchars($p['job_title_name']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="proposal-meta-item">
                    <div class="meta-caption">প্রস্তাবিত ইউজারনেম</div>
                    <div class="meta-value"><code><?= htmlspecialchars($p['proposed_username']) ?></code></div>
                </div>
                <div class="proposal-meta-item">
                    <div class="meta-caption">প্রস্তাবক</div>
                    <div class="meta-value"><?= htmlspecialchars($p['proposed_by_name'] ?: '—') ?></div>
                </div>
            </div>

            <?php if (!empty($p['note'])): ?>
            <div class="bg-light rounded p-2 mb-3 small">
                <strong class="text-muted">মন্তব্য:</strong> <?= nl2br(htmlspecialchars($p['note'])) ?>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <?php if (!empty($p['attachment'])): ?>
                <a class="attachment-link" target="_blank"
                   href="<?= BASE_URL ?>/uploads/role-attachments/<?= rawurlencode($p['attachment']) ?>">
                    <i class="ti tabler-paperclip"></i>সংযুক্তি (অফিস আদেশ)
                </a>
                <?php else: ?>
                <span class="text-muted small"><i class="ti tabler-paperclip me-1"></i>কোনো সংযুক্তি নেই</span>
                <?php endif; ?>

                <div class="proposal-actions">
                    <button type="button" class="btn btn-sm btn-success approveBtn"
                            data-proposal-id="<?= (int)$p['dataID'] ?>"
                            data-employee-name="<?= htmlspecialchars($p['employee_name'], ENT_QUOTES) ?>">
                        <i class="ti tabler-check me-1"></i>অনুমোদন
                    </button>
                    <button type="button" class="btn btn-sm btn-label-danger rejectBtn"
                            data-proposal-id="<?= (int)$p['dataID'] ?>"
                            data-employee-name="<?= htmlspecialchars($p['employee_name'], ENT_QUOTES) ?>">
                        <i class="ti tabler-x me-1"></i>প্রত্যাখ্যান
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function bootApprovalQueue() {
    if (typeof jQuery === 'undefined' || typeof Swal === 'undefined') {
        return setTimeout(bootApprovalQueue, 20);
    }

    function decide(action, proposalID, empName) {
        var isReject = (action === 'reject');
        Swal.fire({
            title: isReject ? 'প্রত্যাখ্যানের কারণ' : 'অনুমোদন নিশ্চিত করুন',
            html: '<div class="text-start small text-muted mb-2"><strong>' + $('<span>').text(empName).html() + '</strong> এর জন্য প্রস্তাবিত role</div>',
            input: 'textarea',
            inputLabel: isReject ? 'কারণ (আবশ্যক)' : 'মন্তব্য (ঐচ্ছিক)',
            inputPlaceholder: isReject ? 'প্রত্যাখ্যানের কারণ লিখুন...' : 'কোনো মন্তব্য থাকলে লিখুন...',
            inputValidator: function (value) {
                if (isReject && (!value || !value.trim())) {
                    return 'প্রত্যাখ্যানের জন্য কারণ আবশ্যক';
                }
            },
            showCancelButton: true,
            confirmButtonText: isReject ? 'প্রত্যাখ্যান করুন' : 'অনুমোদন করুন',
            cancelButtonText: 'বাতিল',
            confirmButtonColor: isReject ? '#dc3545' : '#28a745',
            customClass: {
                confirmButton: 'btn ' + (isReject ? 'btn-danger' : 'btn-success') + ' me-2',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                type: 'POST',
                url: '../../api/role-assignment/decide.php',
                data: { proposal_id: proposalID, action: action, note: result.value || '' },
                dataType: 'json',
                success: function (resp) {
                    if (resp && resp.status === 1) {
                        Swal.fire({
                            title: 'সম্পন্ন', text: resp.message,
                            icon: 'success', confirmButtonColor: '#6c5ce7',
                            customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                        }).then(function () { window.location.reload(); });
                    } else {
                        Swal.fire({
                            title: 'ত্রুটি', text: (resp && resp.message) || 'কাজ সম্পন্ন হয়নি',
                            icon: 'error', confirmButtonColor: '#ff3e1d',
                            customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        title: 'ত্রুটি', text: 'সার্ভার সংযোগ ব্যর্থ',
                        icon: 'error', confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                }
            });
        });
    }

    $(document).on('click', '.approveBtn', function () {
        decide('approve', $(this).data('proposal-id'), $(this).data('employee-name'));
    });
    $(document).on('click', '.rejectBtn', function () {
        decide('reject', $(this).data('proposal-id'), $(this).data('employee-name'));
    });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
