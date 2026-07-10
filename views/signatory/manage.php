<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'leave-settings');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-settings-cog me-2 text-primary"></i>লিভ সেটিংস</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>সিগনেটরি ব্যবস্থাপনার জন্য নিচের যেকোনো অংশ নির্বাচন করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.settings-nav-card {
    border: 1px solid #eef0f5 !important;
    border-radius: 0.75rem !important;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    text-decoration: none;
    display: block;
    background: #fff;
    height: 100%;
}
.settings-nav-card:hover {
    border-color: #ddd5f6 !important;
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.10);
    transform: translateY(-3px);
    text-decoration: none;
}
.settings-nav-card .card-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    height: 100%;
}
.settings-nav-card .icon-tile {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #f0edff 0%, #ddd5f6 100%);
    color: #5648c4;
    border-radius: 0.65rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
}
.settings-nav-card .nav-title {
    font-weight: 600;
    color: #2c2e3a;
    font-size: 1rem;
    margin: 0;
    line-height: 1.4;
}
.settings-nav-card .nav-sub {
    font-size: 0.82rem;
    color: #5d6580;
    line-height: 1.5;
    margin: 0;
}
.settings-nav-card .nav-action {
    margin-top: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #5648c4;
    font-weight: 500;
    font-size: 0.86rem;
}
.settings-nav-card:hover .nav-action {
    gap: 0.55rem;
}
.settings-nav-card .nav-action .ti {
    transition: transform 0.15s ease;
}
.settings-nav-card:hover .nav-action .ti {
    transform: translateX(3px);
}
</style>

<div class="row g-3">
    <div class="col-xl-4 col-lg-6 col-md-6">
        <a href="previous_leave_deduction_addition_certificate_main.php?menuslug=<?= $menuslug ?>" class="card settings-nav-card shadow-none" data-turbo="true">
            <div class="card-body">
                <span class="icon-tile"><i class="ti tabler-history"></i></span>
                <div>
                    <h6 class="nav-title">পূর্ব ছুটি / ছুটি কর্তন / যোজন / ছুটির সনদ</h6>
                    <p class="nav-sub mt-1">পূর্বের ছুটির হিসাব, কর্তন বা যোজন এবং ছুটির সনদ ইস্যু করার জন্য সিগনেটরি নির্ধারণ করুন</p>
                </div>
                <span class="nav-action">
                    সিগনেটরি ব্যবস্থাপনা <i class="ti tabler-arrow-right"></i>
                </span>
            </div>
        </a>
    </div>

    <div class="col-xl-4 col-lg-6 col-md-6">
        <a href="recommend_approval_revision_main.php?menuslug=<?= $menuslug ?>" class="card settings-nav-card shadow-none" data-turbo="true">
            <div class="card-body">
                <span class="icon-tile"><i class="ti tabler-route"></i></span>
                <div>
                    <h6 class="nav-title">সুপারিশ / অনুমোদন / সংশোধন</h6>
                    <p class="nav-sub mt-1">প্রতিটি কেন্দ্রের সিগনেটরি চেইন ও গ্রেড-ভিত্তিক রাউটিং নিয়মাবলী কনফিগার করুন</p>
                </div>
                <span class="nav-action">
                    সিগনেটরি ব্যবস্থাপনা <i class="ti tabler-arrow-right"></i>
                </span>
            </div>
        </a>
    </div>

    <div class="col-xl-4 col-lg-6 col-md-6">
        <a href="role-approver.php?menuslug=<?= $menuslug ?>" class="card settings-nav-card shadow-none" data-turbo="true">
            <div class="card-body">
                <span class="icon-tile"><i class="ti tabler-user-shield"></i></span>
                <div>
                    <h6 class="nav-title">রোল অনুমোদনকারী</h6>
                    <p class="nav-sub mt-1">Regional Super Admin / Regional Op. Admin assignment proposal কে approve করবে — HQ এর একজন</p>
                </div>
                <span class="nav-action">
                    অনুমোদনকারী নির্ধারণ <i class="ti tabler-arrow-right"></i>
                </span>
            </div>
        </a>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
