<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$dataID = base64_decode($_GET['dataID']);

$getEmployeeInfoQ = mysqli_query($con, "SELECT * FROM `employee_list` WHERE `id`='$dataID'");
$getEmployeeInfoQRW = mysqli_fetch_assoc($getEmployeeInfoQ);

// Fetch all designations
$getAllDesig = mysqli_query($con, "SELECT * FROM `job_title` WHERE `deleted`=0 ORDER BY job_title_name ASC");

// Fetch all organizations
$getAllOrg = mysqli_query($con, "SELECT * FROM `organization` WHERE `deleted`=0 ORDER BY display_order ASC");

// Fetch all sections
$getAllSectionsQ = mysqli_query($con, "SELECT * FROM `sections` WHERE deleted=0 ORDER BY section_name ASC");

// Fetch all salary groups
$getAllGroupQ = mysqli_query($con, "SELECT * FROM salary_group ORDER BY id ASC");

// Fetch all grades/scales
$getScalesQ = mysqli_query($con, "SELECT * FROM grade WHERE deleted=0 ORDER BY minimum_salary ASC");

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'manage-employee');
$empName  = htmlspecialchars($getEmployeeInfoQRW['employee_name'] ?? '');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-user-cog me-2 text-primary"></i>কর্মকর্তা / কর্মচারী সম্পাদনা</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i><strong class="text-dark"><?= $empName ?></strong> এর তথ্য সম্পাদনা</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </a>
    </div>
</div>

<style>
.emp-form-card { border-radius: 0.75rem; }
.emp-form-card .card-body { padding: 1.75rem; }
@media (max-width: 575px) {
    .emp-form-card .card-body { padding: 1rem; }
}

.emp-section-header {
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
.emp-section-header:first-of-type { margin-top: 0; }
.emp-section-header[data-color="indigo"] { --sec-bg: #f0edff; --sec-accent: #6c5ce7; }
.emp-section-header[data-color="green"]  { --sec-bg: #e6f7ee; --sec-accent: #1a7e44; }
.emp-section-header[data-color="amber"]  { --sec-bg: #fff3e1; --sec-accent: #b8651a; }
.emp-section-header[data-color="purple"] { --sec-bg: #f5e9ff; --sec-accent: #7c3aed; }

.emp-section-header .section-num {
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
.emp-section-header .section-text { flex: 1; min-width: 0; }
.emp-section-header .section-title {
    font-size: 0.98rem;
    font-weight: 600;
    color: #2c2e3a;
    margin: 0;
    line-height: 1.3;
}
.emp-section-header .section-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin-top: 2px;
    display: block;
}
.emp-section-header .section-icon {
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

.emp-form-card .form-label {
    font-size: 0.82rem;
    color: #3a3d53;
    font-weight: 500;
    margin-bottom: 0.4rem;
}
.emp-form-card .form-control,
.emp-form-card .form-select {
    font-size: 0.9rem;
    border-color: #e0e4ee;
}
.emp-form-card .form-control:focus,
.emp-form-card .form-select:focus {
    border-color: #b9b0f4;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.emp-form-card .input-group-text {
    background: #fafbfd;
    border-color: #e0e4ee;
    color: #5d6580;
}
.emp-form-card .required-mark { color: #dc3545; font-weight: 700; }

/* Current photo / signature preview tile */
.emp-preview-tile {
    border: 1px solid #eef0f5;
    background: #fafbfd;
    border-radius: 0.5rem;
    padding: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 96px;
    min-width: 110px;
}
.emp-preview-tile img { max-height: 80px; border-radius: 0.4rem; }
.emp-preview-tile .empty-text {
    color: #8a90a6;
    font-size: 0.8rem;
}

/* Status radios */
.emp-status-group {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.emp-status-group .form-check {
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-radius: 0.5rem;
    padding: 0.55rem 1rem 0.55rem 2.4rem;
    cursor: pointer;
    transition: all 0.15s ease;
    margin: 0;
}
.emp-status-group .form-check:hover { border-color: #ddd5f6; background: #fdfcff; }
.emp-status-group .form-check-input { margin-left: -1.7rem; }
.emp-status-group .form-check-input:checked {
    background-color: #6c5ce7;
    border-color: #6c5ce7;
}
.emp-status-group .form-check-input:checked ~ .form-check-label { color: #5648c4; font-weight: 600; }
.emp-status-group .form-check-label { color: #3a3d53; font-size: 0.9rem; cursor: pointer; }

.emp-form-actions {
    border-top: 1px solid #eef0f5;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

@media (max-width: 575px) {
    .emp-section-header { padding: 12px 14px; gap: 10px; }
    .emp-section-header .section-icon { display: none; }
    .emp-section-header .section-num { width: 26px; height: 26px; font-size: 0.8rem; }
    .emp-section-header .section-title { font-size: 0.92rem; }
}
</style>

<?php
$_empType        = $getEmployeeInfoQRW['employment_type']      ?? 'permanent';
$_probStart      = $getEmployeeInfoQRW['probation_start_date'] ?? null;
$_permFrom       = $getEmployeeInfoQRW['permanent_from_date']  ?? null;
$_isProbationary = ($_empType === 'probationary');
$_currentOrgID   = (int)($getEmployeeInfoQRW['organization_id'] ?? 0);
?>

<!-- Lifecycle banner -->
<div class="card mb-3 shadow-none" style="border:1px solid <?= $_isProbationary ? '#ddd5f6' : '#c4ebd4' ?>; background:<?= $_isProbationary ? '#f8f7ff' : '#f0faf4' ?>;">
    <div class="card-body py-3 px-4 d-flex align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2">
            <i class="ti <?= $_isProbationary ? 'tabler-clock-hour-4' : 'tabler-id-badge-2' ?>" style="font-size:1.4rem; color:<?= $_isProbationary ? '#6c5ce7' : '#1a7e44' ?>;"></i>
            <div>
                <div style="font-weight:700; color:#2c2e3a;">
                    <?= $_isProbationary ? 'শিক্ষানবিশ কর্মচারী' : 'স্থায়ী কর্মচারী' ?>
                </div>
                <div class="small text-muted">
                    <?php if ($_isProbationary): ?>
                        অস্থায়ী আইডি: <strong><?= htmlspecialchars($getEmployeeInfoQRW['employee_id']) ?></strong>
                        <?php if ($_probStart): ?>
                            • শিক্ষানবিশ শুরু: <?= date('d/m/Y', strtotime($_probStart)) ?>
                            <?php
                                $months = (int)((time() - strtotime($_probStart)) / (86400 * 30));
                                echo " • <strong>$months মাস</strong> অতিবাহিত";
                            ?>
                        <?php endif; ?>
                    <?php else: ?>
                        BITAC আইডি: <strong><?= htmlspecialchars($getEmployeeInfoQRW['employee_id']) ?></strong>
                        <?php if ($_permFrom): ?>
                            • স্থায়ী হয়েছেন: <?= date('d/m/Y', strtotime($_permFrom)) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="ms-auto d-flex gap-2 flex-wrap">
            <?php if ($_isProbationary): ?>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#promoteModal">
                    <i class="ti tabler-id-badge-2 me-1"></i>স্থায়ীতে রূপান্তর
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-label-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
                <i class="ti tabler-transfer me-1"></i>বদলি
            </button>
            <a href="<?= BASE_URL ?>/views/employees/details.php?employeeID=<?= base64_encode($dataID) ?>&menuslug=<?= htmlspecialchars($_GET['menuslug'] ?? 'manage-employee') ?>" class="btn btn-label-secondary btn-sm">
                <i class="ti tabler-history me-1"></i>পদ-ইতিহাস
            </a>
        </div>
    </div>
</div>

<form class="form-login" name="form" id="form" enctype="multipart/form-data">
    <input type="hidden" name="dataID" value="<?= $dataID ?>" />
    <input type="hidden" name="prevPhoto" value="<?= htmlspecialchars($getEmployeeInfoQRW['photo'] ?? '') ?>" />
    <input type="hidden" name="prevsignature" value="<?= base64_encode($getEmployeeInfoQRW['signature'] ?? '') ?>" />

    <div class="card emp-form-card shadow-sm border-0">
        <div class="card-body">

            <!-- ───── Section 1: Personal Info ───── -->
            <div class="emp-section-header" data-color="indigo">
                <div class="section-num">১</div>
                <div class="section-text">
                    <h6 class="section-title">ব্যক্তিগত তথ্য</h6>
                    <span class="section-sub">নাম, পরিচয়পত্র, পদবি ও যোগাযোগের তথ্য</span>
                </div>
                <span class="section-icon"><i class="ti tabler-user"></i></span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="employee_name">নাম <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-user"></i></span>
                        <input type="text" id="employee_name" class="form-control" name="employee_name"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['employee_name']) ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="nid">জাতীয় পরিচয়পত্র নং <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-id"></i></span>
                        <input class="form-control" type="text" id="nid" name="nid"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['nationalID']) ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="designation">পদবি <span class="required-mark">*</span></label>
                    <select data-placeholder="পদবি নির্বাচন করুন" class="select2 form-select" name="designation" id="designation" required>
                        <option value="">-- পদবি নির্বাচন করুন --</option>
                        <?php while($degRow = mysqli_fetch_array($getAllDesig)): ?>
                            <option <?= ($getEmployeeInfoQRW['designation'] == $degRow['id']) ? 'selected' : '' ?> value="<?= $degRow['id'] ?>"><?= htmlspecialchars($degRow['job_title_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="employee_id">আইডি <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-hash"></i></span>
                        <input class="form-control" type="text" id="employee_id" name="employee_id"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['employee_id']) ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="memorialNo">স্মারক নম্বর <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-bookmark"></i></span>
                        <input class="form-control" type="text" id="memorialNo" name="memorialNo"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['memorialNo']) ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="employee_type">চাকরীর ধরন <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="employee_type" id="employee_type" required>
                        <option value="">-- ধরন নির্বাচন করুন --</option>
                        <option <?= ($getEmployeeInfoQRW['employee_type'] == 1) ? 'selected' : '' ?> value="1">কর্মকর্তা</option>
                        <option <?= ($getEmployeeInfoQRW['employee_type'] == 2) ? 'selected' : '' ?> value="2">কর্মচারী</option>
                        <option <?= ($getEmployeeInfoQRW['employee_type'] == 3) ? 'selected' : '' ?> value="3">পি আর এল</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="dob">জন্ম তারিখ <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-cake"></i></span>
                        <input type="text" class="form-control flatpickr-validation" id="dob" name="dob"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['date_of_birth']) ?>" autocomplete="off" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="joining_date">চাকরিতে যোগদানের তারিখ <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-calendar-event"></i></span>
                        <input type="text" class="form-control flatpickr-validation" id="joining_date" name="joining_date"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['joining_date']) ?>" autocomplete="off" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="email">ইমেইল</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-mail"></i></span>
                        <input class="form-control" type="email" id="email" name="email" placeholder="example@bitac.gov.bd"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['email']) ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mobileNo">মোবাইল নম্বর</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-phone"></i></span>
                        <input class="form-control" type="text" id="mobileNo" name="mobileNo" placeholder="01XXXXXXXXX"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['mobileNo']) ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="photo" class="form-label">ফটো</label>
                    <input class="form-control" type="file" id="photo" name="photo" accept="image/jpeg,image/png">
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>JPEG বা PNG ফরম্যাট, সর্বোচ্চ ১MB</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">বর্তমান ফটো</label>
                    <div class="emp-preview-tile">
                        <?php if (!empty($getEmployeeInfoQRW['photo'])): ?>
                            <img src="../../uploads/<?= htmlspecialchars($getEmployeeInfoQRW['photo']) ?>" alt="ফটো">
                        <?php else: ?>
                            <span class="empty-text"><i class="ti tabler-photo-off me-1"></i>কোন ফটো নেই</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="signature" class="form-label">স্বাক্ষর</label>
                    <input class="form-control" type="file" id="signature" name="signature" accept="image/jpeg,image/png">
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle me-1"></i>JPEG বা PNG ফরম্যাট, সর্বোচ্চ ১MB</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">বর্তমান স্বাক্ষর</label>
                    <div class="emp-preview-tile" style="min-height:78px;">
                        <?php if (!empty($getEmployeeInfoQRW['signature'])): ?>
                            <img height="60" src="data:image/jpg;charset=utf8;base64,<?= base64_encode($getEmployeeInfoQRW['signature']) ?>" alt="স্বাক্ষর">
                        <?php else: ?>
                            <span class="empty-text"><i class="ti tabler-writing-off me-1"></i>কোন স্বাক্ষর নেই</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ───── Section 2: Workplace ───── -->
            <div class="emp-section-header" data-color="green">
                <div class="section-num">২</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মক্ষেত্র</h6>
                    <span class="section-sub">কেন্দ্র এবং শাখা নির্বাচন করুন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-building"></i></span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="organization_id">কেন্দ্র <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="organization_id" id="organization_id" required>
                        <option value="">-- কেন্দ্র নির্বাচন করুন --</option>
                        <?php while($orgRow = mysqli_fetch_array($getAllOrg)): ?>
                            <option <?= ($getEmployeeInfoQRW['organization_id'] == $orgRow['id']) ? 'selected' : '' ?> value="<?= $orgRow['id'] ?>"><?= htmlspecialchars($orgRow['organization_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="section_id">শাখা <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="section_id" id="section_id" required>
                        <option value="">-- শাখা নির্বাচন করুন --</option>
                        <?php while($secRow = mysqli_fetch_array($getAllSectionsQ)): ?>
                            <option <?= ($getEmployeeInfoQRW['section_id'] == $secRow['id']) ? 'selected' : '' ?> value="<?= $secRow['id'] ?>"><?= htmlspecialchars($secRow['section_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- ───── Section 3: Salary ───── -->
            <div class="emp-section-header" data-color="amber">
                <div class="section-num">৩</div>
                <div class="section-text">
                    <h6 class="section-title">বেতন সংক্রান্ত তথ্য</h6>
                    <span class="section-sub">বেতন স্কেল, মূল বেতন ও রিপোর্ট ক্যাটাগরি</span>
                </div>
                <span class="section-icon"><i class="ti tabler-currency-taka"></i></span>
            </div>

            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label" for="pay_scale">বর্তমান বেতন স্কেল <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="pay_scale" id="pay_scale" required>
                        <option value="">-- বেতন স্কেল নির্বাচন করুন --</option>
                        <?php while($sRow = mysqli_fetch_array($getScalesQ)): ?>
                            <option <?= ($getEmployeeInfoQRW['pay_scale'] == $sRow['id']) ? 'selected' : '' ?> value="<?= $sRow['id'] ?>"><?= $obj->engToBn($sRow['minimum_salary']) ." - ". $obj->engToBn($sRow['maximum_salary']) ." (". htmlspecialchars($sRow['grade_title']) .")" ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="basic_salary">বর্তমান মূল বেতন <span class="required-mark">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-cash"></i></span>
                        <input class="form-control" type="text" id="basic_salary" name="basic_salary"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['basic_salary']) ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="display_order">ডিসপ্লে অর্ডার</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-list-numbers"></i></span>
                        <input class="form-control" type="number" id="display_order" name="display_order"
                            value="<?= htmlspecialchars($getEmployeeInfoQRW['display_order']) ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="form-label" for="salary_group_id">রিপোর্ট ক্যাটাগরি <span class="required-mark">*</span></label>
                    <select class="select2 form-select" name="salary_group_id" id="salary_group_id" required>
                        <option value="">-- ক্যাটাগরি নির্বাচন করুন --</option>
                        <?php while($sgRow = mysqli_fetch_array($getAllGroupQ)): ?>
                            <option <?= ($getEmployeeInfoQRW['salary_group_id'] == $sgRow['id']) ? 'selected' : '' ?> value="<?= $sgRow['id'] ?>"><?= htmlspecialchars($sgRow['head']) ." => ". htmlspecialchars($sgRow['sub_head']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- ───── Section 4: Employment Status ───── -->
            <div class="emp-section-header" data-color="purple">
                <div class="section-num">৪</div>
                <div class="section-text">
                    <h6 class="section-title">কর্মসংস্থানের অবস্থা</h6>
                    <span class="section-sub">বর্তমান চাকরির অবস্থা নির্বাচন করুন</span>
                </div>
                <span class="section-icon"><i class="ti tabler-user-check"></i></span>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="emp-status-group">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="employment_status" id="status_active" value="1" <?= ($getEmployeeInfoQRW['employment_status'] == 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_active"><i class="ti tabler-circle-check me-1"></i>চাকরিরত</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="employment_status" id="status_inactive" value="0" <?= ($getEmployeeInfoQRW['employment_status'] == 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_inactive"><i class="ti tabler-circle-x me-1"></i>কর্মরত নন</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="employment_status" id="status_prl" value="2" <?= ($getEmployeeInfoQRW['employment_status'] == 2) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_prl"><i class="ti tabler-clock me-1"></i>পি আর এল</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Actions -->
            <div class="emp-form-actions d-flex gap-2 justify-content-end flex-wrap">
                <a href="manage.php?menuslug=<?= $menuslug ?>" class="btn btn-label-secondary" data-turbo="true">
                    <i class="ti tabler-x me-1"></i>বাতিল করুন
                </a>
                <button type="submit" name="submit" id="submit" class="btn btn-primary px-4">
                    <i class="ti tabler-device-floppy me-1"></i>আপডেট করুন
                </button>
            </div>

        </div>
    </div>
</form>

<?php
// Load center list for Transfer modal
$_centerListQ = mysqli_query($con, "SELECT id, organization_name FROM organization ORDER BY (id=4) DESC, organization_name ASC");
?>

<!-- ───── Promote to Permanent Modal ───── -->
<?php if ($_isProbationary): ?>
<div class="modal fade" id="promoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1a7e44 0%,#15803d 100%); color:#fff; border:0;">
                <h5 class="modal-title text-white"><i class="ti tabler-id-badge-2 me-1"></i>স্থায়ী হিসেবে নিবন্ধন</h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <form id="promoteForm">
                <input type="hidden" name="dataID" value="<?= $dataID ?>" />
                <div class="modal-body">
                    <p class="text-muted small mb-3"><i class="ti tabler-info-circle me-1"></i>BITAC কর্তৃক প্রদত্ত স্থায়ী আইডি ও তারিখ লিখুন। অস্থায়ী আইডি archive হিসেবে সংরক্ষিত থাকবে।</p>
                    <div class="mb-3">
                        <label class="form-label">BITAC স্থায়ী আইডি <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="permanent_emp_id" placeholder="যেমন: ১২৩৪" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">স্থায়ী হওয়ার তারিখ <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="permanent_from_date" value="<?= date('Y-m-d') ?>" required />
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #eef0f5;">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-success"><i class="ti tabler-check me-1"></i>নিবন্ধন করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ───── Transfer Modal ───── -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#6c5ce7 0%,#5648c4 100%); color:#fff; border:0;">
                <h5 class="modal-title text-white"><i class="ti tabler-transfer me-1"></i>কর্মচারী বদলি</h5>
                <button type="button" class="ai-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti tabler-x"></i></button>
            </div>
            <form id="transferForm" enctype="multipart/form-data">
                <input type="hidden" name="dataID" value="<?= $dataID ?>" />
                <div class="modal-body">
                    <p class="text-muted small mb-3"><i class="ti tabler-info-circle me-1"></i>বর্তমান কেন্দ্র থেকে নতুন কেন্দ্রে বদলির রেকর্ড। পদ-ইতিহাসে সংরক্ষিত হবে।</p>

                    <div class="mb-3">
                        <label class="form-label">গন্তব্য কেন্দ্র <span class="text-danger">*</span></label>
                        <select class="form-select" name="to_organization_id" required>
                            <option value="">-- কেন্দ্র নির্বাচন করুন --</option>
                            <?php while ($_c = mysqli_fetch_assoc($_centerListQ)):
                                if ((int)$_c['id'] === $_currentOrgID) continue; // skip current
                            ?>
                                <option value="<?= (int)$_c['id'] ?>"><?= htmlspecialchars($_c['organization_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">বদলির তারিখ <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="transfer_date" value="<?= date('Y-m-d') ?>" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">অফিস আদেশ নম্বর</label>
                            <input type="text" class="form-control" name="order_number" placeholder="অফিস আদেশ" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">আদেশের তারিখ</label>
                            <input type="date" class="form-control" name="order_date" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">সংযুক্তি (ঐচ্ছিক)</label>
                            <input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png" />
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">কারণ / মন্তব্য</label>
                        <textarea class="form-control" name="reason" rows="2" placeholder="বদলির কারণ / প্রাসঙ্গিক মন্তব্য"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #eef0f5;">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary"><i class="ti tabler-check me-1"></i>বদলি রেকর্ড করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.ai-modal-close {
    background: transparent; border: none; color: #fff;
    width: 32px; height: 32px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.15rem; cursor: pointer; opacity: 0.85;
    padding: 0; line-height: 1; margin-left: auto;
}
.ai-modal-close:hover { background: rgba(255,255,255,0.18); opacity: 1; }
.ai-modal-close i { color: #fff; }
</style>

<?php
// Capture page scripts using output buffer
ob_start();
?>
<script>
jQuery(function($) {
    // Flatpickr for date fields
    try {
        $(".flatpickr-validation").each(function() {
            if (typeof flatpickr !== "undefined" && !$(this).hasClass("flatpickr-input")) {
                $(this).flatpickr({
                    enableTime: false,
                    dateFormat: "Y-m-d",
                    allowInput: true
                });
            }
        });
    } catch(e) {
        console.error("Flatpickr init error:", e);
    }

    // Form submission
    var form = $('#form');
    var submit = $('#submit');
    var originalButtonText = '<i class="ti tabler-device-floppy me-1"></i>আপডেট করুন';

    form.on('submit', function(e) {
        e.preventDefault();
        submit.prop('disabled', true);

        $.ajax({
            url: '../../api/employees/update.php',
            type: 'POST',
            dataType: 'text',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function() {
                submit.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>আপডেট হচ্ছে...');
            },
            success: function(data) {
                var response = $.trim(data);

                if (response == '0' || response == '' || response === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ত্রুটি',
                        text: 'সব আবশ্যক ফিল্ড পূরণ করুন এবং আবার চেষ্টা করুন।',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'সম্পন্ন',
                        text: 'কর্মকর্তা/কর্মচারীর তথ্য সফলভাবে আপডেট হয়েছে',
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                }

                submit.prop('disabled', false);
                submit.html(originalButtonText);
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', status, error);
                Swal.fire({
                    icon: 'error',
                    title: 'সার্ভার ত্রুটি',
                    text: 'অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন।',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });

                submit.prop('disabled', false);
                submit.html(originalButtonText);
            }
        });
    });

    // File validation helper
    function bindFileValidation(elementId) {
        var el = document.getElementById(elementId);
        if (!el) return;
        el.addEventListener('change', function(e) {
            var allowedTypes = ['image/jpeg', 'image/png'];
            var file = e.target.files[0];
            if (!file) return;
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'অবৈধ ফাইল',
                    text: 'অনুগ্রহ করে JPEG বা PNG ফাইল নির্বাচন করুন।',
                    confirmButtonColor: '#6c5ce7',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                e.target.value = '';
            } else if (file.size > 1048576) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ফাইল অনেক বড়',
                    text: 'অনুগ্রহ করে ১MB এর কম সাইজের ফাইল নির্বাচন করুন।',
                    confirmButtonColor: '#6c5ce7',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
                e.target.value = '';
            }
        });
    }
    bindFileValidation('photo');
    bindFileValidation('signature');

    // ── Promote to Permanent ──
    $('#promoteForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '../../api/employees/promote-to-permanent.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function(resp) {
            if (resp && resp.status === 1) {
                Swal.fire({icon:'success', title:'সম্পন্ন', text: resp.message,
                    confirmButtonColor:'#1a7e44', customClass:{confirmButton:'btn btn-success'}, buttonsStyling:false})
                    .then(() => window.location.reload());
            } else {
                Swal.fire({icon:'error', title:'ত্রুটি', text: (resp && resp.message) || 'ব্যর্থ',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
            }
        }).fail(function() {
            Swal.fire({icon:'error', title:'সার্ভার ত্রুটি', confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
        });
    });

    // ── Transfer ──
    $('#transferForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '../../api/employees/transfer.php',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(resp) {
            if (resp && resp.status === 1) {
                Swal.fire({icon:'success', title:'সম্পন্ন', text: resp.message,
                    confirmButtonColor:'#1a7e44', customClass:{confirmButton:'btn btn-success'}, buttonsStyling:false})
                    .then(() => window.location.reload());
            } else {
                Swal.fire({icon:'error', title:'ত্রুটি', text: (resp && resp.message) || 'ব্যর্থ',
                    confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
            }
        }).fail(function() {
            Swal.fire({icon:'error', title:'সার্ভার ত্রুটি', confirmButtonColor:'#dc3545', customClass:{confirmButton:'btn btn-danger'}, buttonsStyling:false});
        });
    });
});
</script>
<?php
define('PAGE_SCRIPTS', ob_get_clean());

require_once(__DIR__ . '/../../includes/footer_vuexy.php');
?>
