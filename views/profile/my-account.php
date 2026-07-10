<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');

$dataID = $_SESSION['userID'];

$getUserDetailsQ = $con->prepare("SELECT * FROM user_list WHERE dataID = ?");
$getUserDetailsQ->bind_param("s", $dataID);
$getUserDetailsQ->execute();
$getUserDetailsQRW = $getUserDetailsQ->get_result()->fetch_assoc();
$getUserDetailsQ->close();

if (!$getUserDetailsQRW) {
    echo "<script>Swal.fire({title:'Error!',text:'User details not found!',icon:'error'}).then(()=>{window.location='../../index.php';});</script>";
    exit();
}

$employeeID = $getUserDetailsQRW['employee_id'];
$getEmpQ = $con->prepare("
    SELECT e.*, o.organization_name, s.section_name, d.job_title_name
    FROM employee_list e
    LEFT JOIN organization o ON e.organization_id = o.id
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN job_title d ON e.designation = d.id
    WHERE e.id = ?
");
$getEmpQ->bind_param("s", $employeeID);
$getEmpQ->execute();
$getEmployeeDetailsQRW = $getEmpQ->get_result()->fetch_assoc();
$getEmpQ->close();

$displayName  = htmlspecialchars($getUserDetailsQRW['full_name'] ?? $getUserDetailsQRW['user_id'] ?? 'User');
$designation  = htmlspecialchars($getEmployeeDetailsQRW['job_title_name'] ?? $getUserDetailsQRW['designation'] ?? '');
$organization = htmlspecialchars($getEmployeeDetailsQRW['organization_name'] ?? 'BITAC');
$section      = htmlspecialchars($getEmployeeDetailsQRW['section_name'] ?? '');
$empID        = htmlspecialchars($getEmployeeDetailsQRW['employee_id'] ?? '');
$joiningDate  = !empty($getEmployeeDetailsQRW['joining_date'])
    ? date('d M Y', strtotime($getEmployeeDetailsQRW['joining_date'])) : '—';
$email        = htmlspecialchars($getEmployeeDetailsQRW['email'] ?? '—');
$mobile       = htmlspecialchars($getEmployeeDetailsQRW['mobileNo'] ?? '—');
$photo        = $getEmployeeDetailsQRW['photo'] ?? '';

$initial = mb_substr($displayName, 0, 1, 'UTF-8');
$_parts  = preg_split('/\s+/u', $displayName);
if (count($_parts) > 1) {
    $initial = mb_substr($_parts[0], 0, 1, 'UTF-8') . mb_substr(end($_parts), 0, 1, 'UTF-8');
}
$initial = mb_strtoupper($initial, 'UTF-8');
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0 acc-page-title">আমার প্রোফাইল</h4>
        <div class="text-muted small mt-1">অ্যাকাউন্ট তথ্য, পাসওয়ার্ড ও প্রোফাইল ছবি ব্যবস্থাপনা</div>
    </div>
    <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-sm btn-light-clean">
        <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
    </button>
</div>

<div class="row g-3">

    <!-- ── Left Column: Profile Summary ── -->
    <div class="col-12 col-xl-4">
        <div class="acc-card acc-identity-card">
            <div class="acc-identity-top">
                <?php if (!empty($photo)): ?>
                    <img src="../../uploads/<?= htmlspecialchars($photo) ?>" alt="<?= $displayName ?>" class="acc-avatar" />
                <?php else: ?>
                    <div class="acc-avatar acc-avatar-fallback"><?= htmlspecialchars($initial) ?></div>
                <?php endif; ?>
                <div class="acc-identity-name"><?= $displayName ?></div>
                <?php if ($designation): ?>
                    <div class="acc-identity-role"><?= $designation ?></div>
                <?php endif; ?>
                <div class="acc-identity-org"><?= $organization ?><?= $section ? ' &middot; '.$section : '' ?></div>
            </div>

            <div class="acc-kv-list">
                <div class="acc-kv">
                    <span class="acc-kv-label">আইডি</span>
                    <span class="acc-kv-value"><?= $empID ? banglaNumber($empID) : '—' ?></span>
                </div>
                <div class="acc-kv">
                    <span class="acc-kv-label">যোগদান</span>
                    <span class="acc-kv-value"><?= $joiningDate ?></span>
                </div>
                <div class="acc-kv">
                    <span class="acc-kv-label">ইমেইল</span>
                    <span class="acc-kv-value">
                        <?php if ($email !== '—'): ?>
                            <a href="mailto:<?= $email ?>" class="acc-mail-link"><?= $email ?></a>
                        <?php else: echo '—'; endif; ?>
                    </span>
                </div>
                <div class="acc-kv">
                    <span class="acc-kv-label">মোবাইল</span>
                    <span class="acc-kv-value"><?= $mobile !== '—' ? banglaNumber($mobile) : '—' ?></span>
                </div>
                <div class="acc-kv acc-kv-last">
                    <span class="acc-kv-label">স্বাক্ষর</span>
                    <span class="acc-kv-value">
                        <?php if (!empty($getUserDetailsQRW['signature'])): ?>
                            <img src="data:image/png;charset=utf8;base64,<?= base64_encode($getUserDetailsQRW['signature']) ?>" alt="Signature" class="acc-sig-img" />
                        <?php else: ?>
                            <span class="text-muted small">আপলোড করা হয়নি</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Right Column: Edit ── -->
    <div class="col-12 col-xl-8">
        <form id="profileForm" enctype="multipart/form-data" class="acc-form">
            <input type="hidden" name="dataID"    value="<?= $dataID ?>" />
            <input type="hidden" name="prevPhoto" value="<?= htmlspecialchars($photo) ?>" />

            <!-- Password Card -->
            <div class="acc-card mb-3">
                <div class="acc-card-head">
                    <h6 class="acc-card-title">পাসওয়ার্ড পরিবর্তন</h6>
                    <span class="acc-card-sub">নিরাপদ থাকতে নিয়মিত পাসওয়ার্ড পরিবর্তন করুন</span>
                </div>
                <div class="acc-card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="acc-label" for="password">নতুন পাসওয়ার্ড <span class="text-danger">*</span></label>
                            <div class="acc-input-wrap">
                                <input type="password" id="password" name="password" class="form-control acc-input" placeholder="নতুন পাসওয়ার্ড লিখুন" autocomplete="new-password">
                                <button type="button" class="acc-input-toggle" id="togglePassword" tabindex="-1">
                                    <i class="ti tabler-eye-off" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="acc-label" for="confnewpassword">পাসওয়ার্ড নিশ্চিত করুন <span class="text-danger">*</span></label>
                            <div class="acc-input-wrap">
                                <input type="password" id="confnewpassword" name="confnewpassword" class="form-control acc-input" placeholder="পুনরায় পাসওয়ার্ড লিখুন" autocomplete="new-password">
                                <button type="button" class="acc-input-toggle" id="toggleConfirmPassword" tabindex="-1">
                                    <i class="ti tabler-eye-off" id="eyeIconConfirm"></i>
                                </button>
                            </div>
                            <div class="acc-inline-msg text-danger d-none" id="passwordMismatch">
                                <i class="ti tabler-alert-circle me-1"></i>পাসওয়ার্ড মিলছে না
                            </div>
                            <div class="acc-inline-msg d-none" id="passwordMatch" style="color:#1a7e44;">
                                <i class="ti tabler-circle-check me-1"></i>পাসওয়ার্ড মিলেছে
                            </div>
                        </div>
                    </div>

                    <div class="acc-strength d-none" id="passwordHints">
                        <div class="acc-strength-label">পাসওয়ার্ড শর্তাবলী</div>
                        <div class="acc-strength-pills">
                            <span id="hint-len"   class="pwd-hint"><i class="ti tabler-circle me-1"></i>কমপক্ষে ৬ অক্ষর</span>
                            <span id="hint-upper" class="pwd-hint"><i class="ti tabler-circle me-1"></i>বড় হাতের অক্ষর</span>
                            <span id="hint-num"   class="pwd-hint"><i class="ti tabler-circle me-1"></i>সংখ্যা</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photo Upload Card -->
            <div class="acc-card mb-3">
                <div class="acc-card-head">
                    <h6 class="acc-card-title">প্রোফাইল ছবি</h6>
                    <span class="acc-card-sub">JPG, PNG বা JPEG · সর্বোচ্চ ২ MB</span>
                </div>
                <div class="acc-card-body">
                    <div class="acc-upload-row">
                        <div class="acc-upload-thumb-wrap">
                            <?php if (!empty($photo)): ?>
                                <img id="photoPreview" src="../../uploads/<?= htmlspecialchars($photo) ?>" class="acc-upload-thumb" />
                            <?php else: ?>
                                <div id="photoPreview" class="acc-upload-thumb acc-avatar-fallback"><?= htmlspecialchars($initial) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="acc-upload-meta">
                            <label for="photo" class="btn btn-sm btn-primary-clean">
                                <i class="ti tabler-upload me-1"></i>নতুন ছবি বেছে নিন
                            </label>
                            <input type="file" id="photo" name="photo" class="d-none" accept="image/*" />
                            <div class="acc-upload-hint">
                                বর্গাকার (1:1) ছবি সবচেয়ে ভালো দেখাবে।<br>
                                ছবি নির্বাচন করলে প্রিভিউ আপডেট হবে।
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save / Cancel -->
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-light-clean">বাতিল</button>
                <button type="submit" id="submitBtn" class="btn btn-primary-clean px-4">
                    <i class="ti tabler-check me-1"></i>পরিবর্তন সংরক্ষণ করুন
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>

<style>
/* ── Root variables ──────────────────────────────── */
:root {
    --acc-fg: #111827;
    --acc-fg-soft: #4b5563;
    --acc-fg-muted: #6b7280;
    --acc-fg-faint: #9ca3af;
    --acc-border: #e5e7eb;
    --acc-border-soft: #f1f2f6;
    --acc-bg-soft: #fafbfc;
    --acc-accent: #4f46e5;
    --acc-accent-hover: #4338ca;
    --acc-accent-soft: #eef0ff;
}

.acc-page-title {
    color: var(--acc-fg);
    letter-spacing: -0.01em;
    font-size: 1.15rem;
}

/* ── Card shell ──────────────────────────────────── */
.acc-card {
    background: #fff;
    border: 1px solid var(--acc-border);
    border-radius: 12px;
    overflow: hidden;
}
.acc-card-head {
    padding: 14px 18px 10px;
    border-bottom: 1px solid var(--acc-border-soft);
}
.acc-card-title {
    margin: 0;
    color: var(--acc-fg);
    font-size: 0.92rem;
    font-weight: 600;
    letter-spacing: -0.005em;
}
.acc-card-sub {
    display: block;
    color: var(--acc-fg-muted);
    font-size: 0.75rem;
    margin-top: 2px;
}
.acc-card-body {
    padding: 18px;
}

/* ── Identity card (left) ────────────────────────── */
.acc-identity-card { padding: 0; }
.acc-identity-top {
    text-align: center;
    padding: 26px 18px 20px;
    background:
        radial-gradient(circle at 50% -20%, rgba(79,70,229,0.06) 0%, transparent 65%),
        #fff;
    border-bottom: 1px solid var(--acc-border-soft);
}
.acc-avatar {
    width: 84px;
    height: 84px;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    border: 1px solid var(--acc-border);
    box-shadow: 0 1px 2px rgba(17,24,39,.04);
    display: inline-block;
    margin-bottom: 12px;
}
.acc-avatar-fallback {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(155deg, #4f46e5 0%, #7c3aed 100%);
    color: #fff;
    font-size: 1.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.01em;
}
.acc-identity-name {
    font-size: 1rem;
    font-weight: 700;
    color: var(--acc-fg);
    line-height: 1.3;
    letter-spacing: -0.005em;
}
.acc-identity-role {
    font-size: 0.8rem;
    color: var(--acc-fg-soft);
    margin-top: 3px;
}
.acc-identity-org {
    display: inline-block;
    margin-top: 8px;
    padding: 3px 10px;
    background: var(--acc-bg-soft);
    border: 1px solid var(--acc-border-soft);
    border-radius: 999px;
    font-size: 0.72rem;
    color: var(--acc-fg-muted);
    font-weight: 500;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.acc-kv-list { padding: 4px 0; }
.acc-kv {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 18px;
    border-bottom: 1px solid var(--acc-border-soft);
    font-size: 0.83rem;
}
.acc-kv.acc-kv-last { border-bottom: 0; }
.acc-kv-label {
    color: var(--acc-fg-muted);
    font-weight: 500;
    flex-shrink: 0;
}
.acc-kv-value {
    color: var(--acc-fg);
    font-weight: 500;
    text-align: right;
    word-break: break-word;
    min-width: 0;
}
.acc-mail-link {
    color: var(--acc-fg);
    text-decoration: none;
    border-bottom: 1px dashed var(--acc-border);
    transition: border-color .15s ease, color .15s ease;
}
.acc-mail-link:hover { color: var(--acc-accent); border-color: var(--acc-accent); }
.acc-sig-img {
    max-height: 32px;
    max-width: 130px;
    object-fit: contain;
    border: 1px solid var(--acc-border-soft);
    border-radius: 6px;
    padding: 3px 5px;
    background: #fff;
    vertical-align: middle;
}

/* ── Form labels & inputs ───────────────────────── */
.acc-label {
    display: block;
    font-size: 0.78rem;
    color: var(--acc-fg-soft);
    font-weight: 600;
    margin-bottom: 6px;
    letter-spacing: -0.005em;
}
.acc-input-wrap { position: relative; }
.acc-input {
    height: 40px;
    padding: 8px 42px 8px 14px;
    border: 1px solid var(--acc-border);
    border-radius: 8px;
    font-size: 0.88rem;
    color: var(--acc-fg);
    background: #fff;
    transition: border-color .15s ease, box-shadow .15s ease;
    width: 100%;
}
.acc-input::placeholder { color: var(--acc-fg-faint); }
.acc-input:focus {
    outline: none;
    border-color: var(--acc-accent);
    box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
}
.acc-input.is-valid { border-color: #16a34a; }
.acc-input.is-invalid { border-color: #dc2626; }
.acc-input-toggle {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    width: 28px; height: 28px;
    border-radius: 6px;
    background: transparent;
    border: none;
    color: var(--acc-fg-faint);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: color .15s ease, background .15s ease;
}
.acc-input-toggle:hover { color: var(--acc-fg); background: var(--acc-bg-soft); }

.acc-inline-msg {
    font-size: 0.75rem;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 2px;
}

/* ── Password strength ──────────────────────────── */
.acc-strength { margin-top: 14px; }
.acc-strength-label {
    font-size: 0.72rem;
    color: var(--acc-fg-muted);
    font-weight: 600;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.acc-strength-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.pwd-hint {
    display: inline-flex; align-items: center;
    font-size: 0.72rem; padding: 3px 10px;
    border-radius: 999px;
    background: var(--acc-bg-soft); color: var(--acc-fg-muted);
    border: 1px solid var(--acc-border-soft);
    transition: all .2s ease;
    font-weight: 500;
}
.pwd-hint.ok {
    background: #ecfdf5; color: #047857;
    border-color: #a7f3d0;
}
.pwd-hint.ok i::before { content: "\ec78"; }

/* ── Upload row ─────────────────────────────────── */
.acc-upload-row {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}
.acc-upload-thumb-wrap { flex-shrink: 0; }
.acc-upload-thumb {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--acc-border);
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
}
.acc-upload-meta { flex: 1; min-width: 200px; }
.acc-upload-hint {
    color: var(--acc-fg-muted);
    font-size: 0.75rem;
    line-height: 1.55;
    margin-top: 8px;
}

/* ── Buttons ────────────────────────────────────── */
.btn-primary-clean {
    background: var(--acc-accent);
    border: 1px solid var(--acc-accent);
    color: #fff;
    font-weight: 500;
    font-size: 0.85rem;
    padding: 8px 18px;
    border-radius: 8px;
    transition: background .15s ease, border-color .15s ease;
}
.btn-primary-clean:hover,
.btn-primary-clean:focus {
    background: var(--acc-accent-hover);
    border-color: var(--acc-accent-hover);
    color: #fff;
}
.btn-primary-clean:disabled { opacity: .65; }

.btn-light-clean {
    background: #fff;
    border: 1px solid var(--acc-border);
    color: var(--acc-fg-soft);
    font-weight: 500;
    font-size: 0.85rem;
    padding: 8px 16px;
    border-radius: 8px;
    transition: background .15s ease, border-color .15s ease;
}
.btn-light-clean:hover,
.btn-light-clean:focus {
    background: var(--acc-bg-soft);
    border-color: #d1d5db;
    color: var(--acc-fg);
}

/* Print */
@media print {
    .btn, .acc-input-toggle { display: none !important; }
}
</style>

<script>
$(document).ready(function() {
    var $form        = $('#profileForm');
    var $submit      = $('#submitBtn');
    var $pwd         = $('#password');
    var $conf        = $('#confnewpassword');
    var $mismatch    = $('#passwordMismatch');
    var $matchOk     = $('#passwordMatch');
    var $hints       = $('#passwordHints');

    $('#togglePassword').on('click', function() {
        var t = $pwd.attr('type') === 'password' ? 'text' : 'password';
        $pwd.attr('type', t);
        $('#eyeIcon').toggleClass('tabler-eye-off tabler-eye');
    });
    $('#toggleConfirmPassword').on('click', function() {
        var t = $conf.attr('type') === 'password' ? 'text' : 'password';
        $conf.attr('type', t);
        $('#eyeIconConfirm').toggleClass('tabler-eye-off tabler-eye');
    });

    $pwd.on('input', function() {
        var v = $pwd.val();
        if (v.length > 0) {
            $hints.removeClass('d-none');
            $('#hint-len').toggleClass('ok',  v.length >= 6);
            $('#hint-upper').toggleClass('ok', /[A-Z]/.test(v));
            $('#hint-num').toggleClass('ok',  /[0-9]/.test(v));
        } else {
            $hints.addClass('d-none');
        }
        checkMatch();
    });

    $conf.on('input', checkMatch);
    function checkMatch() {
        if ($conf.val() === '') { $mismatch.addClass('d-none'); $matchOk.addClass('d-none'); $conf.removeClass('is-valid is-invalid'); return; }
        if ($pwd.val() === $conf.val()) {
            $mismatch.addClass('d-none'); $matchOk.removeClass('d-none');
            $conf.removeClass('is-invalid').addClass('is-valid');
        } else {
            $matchOk.addClass('d-none'); $mismatch.removeClass('d-none');
            $conf.removeClass('is-valid').addClass('is-invalid');
        }
    }

    $('#photo').on('change', function() {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            var $prev = $('#photoPreview');
            if ($prev.is('img')) {
                $prev.attr('src', e.target.result);
            } else {
                $prev.replaceWith('<img id="photoPreview" src="' + e.target.result + '" class="acc-upload-thumb" />');
            }
        };
        reader.readAsDataURL(file);
    });

    $form.on('submit', function(e) {
        e.preventDefault();
        var pwd  = $pwd.val();
        var conf = $conf.val();
        if (pwd !== '' || conf !== '') {
            if (pwd !== conf) {
                Swal.fire({ title: 'ত্রুটি', text: 'পাসওয়ার্ড মিলছে না', icon: 'error',
                    confirmButtonColor: '#dc2626',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false });
                return;
            }
        }
        $.ajax({
            url: '../../api/profile/update-account.php',
            type: 'POST',
            dataType: 'html',
            data: new FormData(this),
            contentType: false, cache: false, processData: false,
            beforeSend: function() {
                $submit.html('<span class="spinner-border spinner-border-sm me-1"></span>সংরক্ষণ হচ্ছে...').prop('disabled', true);
            },
            success: function(data) {
                if (data == 0) {
                    Swal.fire({ title: 'ত্রুটি', text: 'প্রোফাইল আপডেট ব্যর্থ হয়েছে', icon: 'error',
                        confirmButtonColor: '#dc2626',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                } else {
                    Swal.fire({ title: 'সম্পন্ন', text: 'প্রোফাইল সফলভাবে আপডেট হয়েছে', icon: 'success',
                        confirmButtonColor: '#4f46e5',
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    }).then(function() {
                        $pwd.val(''); $conf.val('');
                        $mismatch.addClass('d-none'); $matchOk.addClass('d-none');
                        $conf.removeClass('is-valid is-invalid');
                        $hints.addClass('d-none');
                    });
                }
                $submit.html('<i class="ti tabler-check me-1"></i>পরিবর্তন সংরক্ষণ করুন').prop('disabled', false);
            },
            error: function() {
                Swal.fire({ title: 'ত্রুটি', text: 'প্রোফাইল আপডেট ব্যর্থ হয়েছে', icon: 'error',
                    confirmButtonColor: '#dc2626',
                    customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                $submit.html('<i class="ti tabler-check me-1"></i>পরিবর্তন সংরক্ষণ করুন').prop('disabled', false);
            }
        });
    });
});
</script>
