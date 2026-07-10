<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Auto-create organization_id column if missing
$checkCol = mysqli_query($con, "SHOW COLUMNS FROM leave_edit_approval_signatory LIKE 'organization_id'");
if (mysqli_num_rows($checkCol) === 0) {
    mysqli_query($con, "ALTER TABLE leave_edit_approval_signatory ADD COLUMN organization_id INT(11) DEFAULT 0 AFTER dataID");
}

$getAllCentersQuery = "SELECT id, organization_name FROM organization";
$centersResult = mysqli_query($con, $getAllCentersQuery);

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-settings');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-pen me-2 text-primary"></i>পূর্ব ছুটি / ছুটি কর্তন / যোজন / ছুটির সনদ</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>প্রতিটি কেন্দ্রের জন্য একজন সিগনেটরি নির্ধারণ করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.center-card {
    border: 1px solid #eef0f5 !important;
    border-radius: 0.75rem !important;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.center-card:hover {
    border-color: #ddd5f6 !important;
    box-shadow: 0 4px 16px rgba(108, 92, 231, 0.08);
    transform: translateY(-2px);
}
.center-card .card-body { padding: 1.1rem 1.25rem; }
.center-card .center-name {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 1rem;
    margin-bottom: 0.15rem;
}
.center-card .sig-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.3em 0.7em;
    border-radius: 0.4rem;
    margin-top: 4px;
}
.center-card .sig-status-pill.has { background: #e6f7ee; color: #1a7e44; }
.center-card .sig-status-pill.empty { background: #fff3e1; color: #b8651a; }
.center-card .manage-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #5648c4;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    margin-top: 0.5rem;
}
.center-card .manage-link:hover { color: #5648c4; text-decoration: underline; }
.center-card .avatar-group .avatar img,
.center-card .avatar-group .avatar .avatar-initial {
    border: 2px solid #fff;
    width: 36px;
    height: 36px;
}
</style>

<div class="row g-3">
    <?php while ($center = mysqli_fetch_assoc($centersResult)):
        $centerId   = $center['id'];
        $centerName = $center['organization_name'];

        // Fetch signatory for this center
        $sigQ = mysqli_query($con, "
            SELECT el.employee_name, el.photo
            FROM leave_edit_approval_signatory las
            INNER JOIN employee_list el ON las.employeeID = el.id
            WHERE las.organization_id = '$centerId'
            LIMIT 1
        ");
        $sig = mysqli_fetch_assoc($sigQ);

        if ($sig) {
            $sigName  = htmlspecialchars($sig['employee_name']);
            $photoSrc = !empty($sig['photo']) && file_exists(__DIR__ . '/../../uploads/' . $sig['photo'])
                ? '../../uploads/' . htmlspecialchars($sig['photo'])
                : '../../assets/img/avatars/1.png';
            $avatarHtml = '
                <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                    data-bs-placement="top" title="' . $sigName . '" class="avatar pull-up">
                    <img class="rounded-circle" src="' . $photoSrc . '" alt="' . $sigName . '" />
                </li>';
            $sigLabel = '<span class="sig-status-pill has"><i class="ti tabler-user-check"></i>' . $sigName . '</span>';
        } else {
            $avatarHtml = '
                <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                    data-bs-placement="top" title="সিগনেটরি নির্ধারণ করা হয়নি" class="avatar pull-up">
                    <span class="avatar-initial rounded-circle bg-label-secondary">
                        <i class="ti tabler-user-question"></i>
                    </span>
                </li>';
            $sigLabel = '<span class="sig-status-pill empty"><i class="ti tabler-alert-triangle"></i>সিগনেটরি নির্ধারণ করা হয়নি</span>';
        }
    ?>
    <div class="col-xl-4 col-lg-6 col-md-6">
        <div class="card center-card shadow-none h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <ul class="list-unstyled d-flex align-items-center avatar-group mb-0">
                        <?= $avatarHtml ?>
                    </ul>
                </div>
                <div>
                    <div class="center-name"><?= htmlspecialchars($centerName) ?></div>
                    <?= $sigLabel ?>
                    <div>
                        <a href="other_task_signatory_form.php?center_id=<?= $centerId ?>&menuslug=<?= $menuslug ?>" class="manage-link" data-turbo="true">
                            <i class="ti tabler-settings"></i>সিগনেটরি আপডেট / সংযোজন
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
