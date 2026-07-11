<?php
// Super admin only
$pageTitle    = 'থিম সেটিংস';
$pageSubtitle = 'সাইডবার ও কন্টেন্ট থিম কাস্টমাইজ করুন';

require_once(__DIR__ . '/../../includes/header_vuexy.php');

// Gate: only super admin (user_group_id = 1)
if ((int)($getUserInfoQRW['user_group_id'] ?? 0) !== 1) {
    echo '<div class="alert alert-danger m-4">অ্যাক্সেস নিষিদ্ধ — এই পেজটি শুধুমাত্র সুপার অ্যাডমিনের জন্য।</div>';
    require_once(__DIR__ . '/../../includes/footer_vuexy.php');
    exit;
}

// Default values (same as migration defaults)
$defaults = [
    'sidebar_bg_color'             => '#0f1419',
    'sidebar_menu_color'           => '#c1c2c5',
    'sidebar_icon_color'           => '#909196',
    'sidebar_hover_bg'             => 'rgba(255,255,255,0.04)',
    'sidebar_hover_color'          => '#ffffff',
    'sidebar_active_bg'            => '#24302C',
    'sidebar_active_color'         => '#ffffff',
    'sidebar_menu_font_size'       => '1.075rem',
    'sidebar_submenu_color'        => '#a1a1aa',
    'sidebar_submenu_hover_bg'     => 'rgba(255,255,255,0.04)',
    'sidebar_submenu_hover_color'  => '#ffffff',
    'sidebar_submenu_active_bg'    => '#24302C',
    'sidebar_submenu_active_color' => '#ffffff',
    'sidebar_submenu_font_size'    => '0.9rem',
    'sidebar_section_label_color'  => '#52525b',
    'sidebar_brand_color'          => '#ffffff',
    'content_bg_color'             => '#D9DDE0',
    'sidebar_menu_font_weight'     => '600',
    'sidebar_submenu_font_weight'  => '500',
];

$weightOptions = ['400' => '400 (Regular)', '500' => '500 (Medium)', '600' => '600 (Semi-bold)', '700' => '700 (Bold)', '800' => '800 (Extra-bold)'];

// Current values (fallback to defaults)
$cur = [];
foreach ($defaults as $k => $def) {
    $cur[$k] = $getSettingsDetailsQRW[$k] ?? $def;
}

$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'theme-settings');

// Current logo filename (from template_settings.header_logo)
$currentLogo = trim($getSettingsDetailsQRW['header_logo'] ?? '');
?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h4 class="fw-bold mb-0"><i class="ti tabler-palette me-2 text-primary"></i>থিম সেটিংস</h4>
        <div class="text-muted small mt-1 ms-1"><i class="ti tabler-info-circle me-1"></i>সাইডবার ও কন্টেন্ট এরিয়ার রঙ, ফন্ট এবং স্টাইল কাস্টমাইজ করুন</div>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-label-secondary">
            <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
        </button>
    </div>
</div>

<style>
.theme-settings-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 991px) { .theme-settings-wrap { grid-template-columns: 1fr; } }

/* Theme cards */
.ts-card {
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
    padding: 1.25rem 1.5rem;
}
.ts-card-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #eef0f5;
}
.ts-card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: #f0edff;
    color: #5648c4;
    border-radius: 0.5rem;
    font-size: 1rem;
    flex-shrink: 0;
}
.ts-card-title {
    margin: 0;
    color: #2c2e3a;
    font-size: 0.95rem;
    font-weight: 600;
}
.ts-card-sub {
    font-size: 0.78rem;
    color: #8a90a6;
    margin: 0;
    margin-top: 2px;
    line-height: 1.4;
}
.ts-card-text { flex: 1; min-width: 0; }

/* Field rows */
.ts-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
}
.ts-row:last-child { margin-bottom: 0; }
.ts-row label {
    font-size: 0.82rem;
    color: #3a3d53;
    font-weight: 500;
}
.ts-row .ctrl { display: flex; align-items: center; gap: 8px; }
.ts-row input[type="color"] {
    width: 38px;
    height: 32px;
    border: 1px solid #e0e4ee;
    border-radius: 0.4rem;
    padding: 2px;
    cursor: pointer;
    background: #fff;
}
.ts-row input[type="text"],
.ts-row select {
    padding: 6px 10px;
    font-size: 0.82rem;
    border: 1px solid #e0e4ee;
    border-radius: 0.4rem;
    background: #fff;
}
.ts-row input[type="text"] {
    width: 120px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.ts-row input[type="text"]:focus,
.ts-row select:focus {
    border-color: #b9b0f4;
    outline: 0;
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.12);
}
.ts-row select { min-width: 160px; cursor: pointer; }

/* Wide rgba inputs */
.ts-row input[type="text"].wide { width: 180px; }

/* Brand & Logo card */
.brand-logo-row {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 20px;
    align-items: center;
}
@media (max-width: 575px) {
    .brand-logo-row { grid-template-columns: 1fr; }
}
.brand-logo-preview {
    background: #f8f9fc;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    height: 90px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    overflow: hidden;
}
.brand-logo-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
}
.brand-logo-empty {
    color: #9ca3af;
    font-size: 0.8rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.brand-logo-empty i { font-size: 1.6rem; }
.brand-logo-controls { min-width: 0; }
.brand-logo-file-name {
    word-break: break-all;
    line-height: 1.4;
}

/* Action bar */
.ts-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    flex-wrap: wrap;
    padding: 1.25rem 1.5rem;
    background: #fafbfd;
    border: 1px solid #eef0f5;
    border-radius: 0.75rem;
    margin-top: 1rem;
}
</style>

<form id="themeSettingsForm" class="theme-settings-wrap" enctype="multipart/form-data">

    <!-- Brand & Logo -->
    <div class="ts-card" style="grid-column: 1 / -1;">
        <div class="ts-card-header">
            <span class="ts-card-icon"><i class="ti tabler-photo"></i></span>
            <div class="ts-card-text">
                <h6 class="ts-card-title">ব্র্যান্ড ও লোগো</h6>
                <p class="ts-card-sub">সাইডবারের হেডার লোগো (PNG / JPG / SVG / WEBP, সর্বোচ্চ ২ MB)</p>
            </div>
        </div>

        <div class="brand-logo-row">
            <div class="brand-logo-preview" id="brandLogoPreview">
                <?php if ($currentLogo): ?>
                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($currentLogo) ?>" alt="Current logo">
                <?php else: ?>
                    <div class="brand-logo-empty">
                        <i class="ti tabler-photo-off"></i>
                        <span>কোনো লোগো নির্ধারিত নেই</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="brand-logo-controls">
                <label for="header_logo_file" class="btn btn-label-primary btn-sm mb-2">
                    <i class="ti tabler-upload me-1"></i>নতুন লোগো বেছে নিন
                </label>
                <input type="file" id="header_logo_file" name="header_logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="d-none">
                <div class="brand-logo-file-name text-muted small" id="brandLogoFileName">
                    <?php if ($currentLogo): ?>
                        বর্তমান: <?= htmlspecialchars($currentLogo) ?>
                    <?php else: ?>
                        কোনো ফাইল নির্বাচিত নয়
                    <?php endif; ?>
                </div>
                <div class="text-muted small mt-2" style="line-height:1.55;">
                    <i class="ti tabler-info-circle me-1"></i>
                    সাইজ প্রায় <strong>200 × 60 px</strong> বা <strong>1:3</strong> অনুপাত সবচেয়ে ভালো দেখাবে। সাইডবার collapsed অবস্থায় ছোট আইকন-শৈলীর লোগো ব্যবহার করুন।
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar surface + brand + label -->
    <div class="ts-card">
        <div class="ts-card-header">
            <span class="ts-card-icon"><i class="ti tabler-layout-sidebar"></i></span>
            <div class="ts-card-text">
                <h6 class="ts-card-title">সাইডবার সারফেস</h6>
                <p class="ts-card-sub">সাইডবারের মূল ব্যাকগ্রাউন্ড ও লোগো / লেবেল রঙ</p>
            </div>
        </div>

        <div class="ts-row">
            <label>সাইডবার ব্যাকগ্রাউন্ড</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_bg_color" value="<?= htmlspecialchars($cur['sidebar_bg_color']) ?>">
                <input type="text" name="sidebar_bg_color" value="<?= htmlspecialchars($cur['sidebar_bg_color']) ?>">
            </div>
        </div>

        <div class="ts-row">
            <label>ব্র্যান্ড টেক্সট রঙ</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_brand_color" value="<?= htmlspecialchars($cur['sidebar_brand_color']) ?>">
                <input type="text" name="sidebar_brand_color" value="<?= htmlspecialchars($cur['sidebar_brand_color']) ?>">
            </div>
        </div>

        <div class="ts-row">
            <label>সেকশন লেবেল রঙ</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_section_label_color" value="<?= htmlspecialchars($cur['sidebar_section_label_color']) ?>">
                <input type="text" name="sidebar_section_label_color" value="<?= htmlspecialchars($cur['sidebar_section_label_color']) ?>">
            </div>
        </div>

        <div class="ts-row">
            <label>কন্টেন্ট এরিয়া ব্যাকগ্রাউন্ড</label>
            <div class="ctrl">
                <input type="color" data-pair="content_bg_color" value="<?= htmlspecialchars($cur['content_bg_color']) ?>">
                <input type="text" name="content_bg_color" value="<?= htmlspecialchars($cur['content_bg_color']) ?>">
            </div>
        </div>
    </div>

    <!-- Main menu items -->
    <div class="ts-card">
        <div class="ts-card-header">
            <span class="ts-card-icon"><i class="ti tabler-menu-2"></i></span>
            <div class="ts-card-text">
                <h6 class="ts-card-title">মেইন মেনু</h6>
                <p class="ts-card-sub">উপরের লেভেলের মেনু আইটেম (ড্যাশবোর্ড, কনফিগারেশন, ইত্যাদি)</p>
            </div>
        </div>

        <div class="ts-row">
            <label>টেক্সট রঙ</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_menu_color" value="<?= htmlspecialchars($cur['sidebar_menu_color']) ?>">
                <input type="text" name="sidebar_menu_color" value="<?= htmlspecialchars($cur['sidebar_menu_color']) ?>">
            </div>
        </div>

        <div class="ts-row">
            <label>আইকন রঙ</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_icon_color" value="<?= htmlspecialchars($cur['sidebar_icon_color']) ?>">
                <input type="text" name="sidebar_icon_color" value="<?= htmlspecialchars($cur['sidebar_icon_color']) ?>">
            </div>
        </div>

        <div class="ts-row">
            <label>ফন্ট সাইজ</label>
            <div class="ctrl">
                <input type="text" name="sidebar_menu_font_size" value="<?= htmlspecialchars($cur['sidebar_menu_font_size']) ?>" placeholder="1.075rem or 16px">
            </div>
        </div>

        <div class="ts-row">
            <label>ফন্ট ওয়েট</label>
            <div class="ctrl">
                <select name="sidebar_menu_font_weight">
                    <?php foreach ($weightOptions as $w => $label): ?>
                        <option value="<?= $w ?>" <?= $cur['sidebar_menu_font_weight'] == $w ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="ts-row">
            <label>হোভার ব্যাকগ্রাউন্ড</label>
            <div class="ctrl">
                <input type="text" class="wide" name="sidebar_hover_bg" value="<?= htmlspecialchars($cur['sidebar_hover_bg']) ?>" placeholder="rgba(255,255,255,0.04)">
            </div>
        </div>

        <div class="ts-row">
            <label>হোভার টেক্সট রঙ</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_hover_color" value="<?= htmlspecialchars($cur['sidebar_hover_color']) ?>">
                <input type="text" name="sidebar_hover_color" value="<?= htmlspecialchars($cur['sidebar_hover_color']) ?>">
            </div>
        </div>

        <div class="ts-row">
            <label>অ্যাক্টিভ ব্যাকগ্রাউন্ড</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_active_bg" value="<?= htmlspecialchars($cur['sidebar_active_bg']) ?>">
                <input type="text" name="sidebar_active_bg" value="<?= htmlspecialchars($cur['sidebar_active_bg']) ?>">
            </div>
        </div>

        <div class="ts-row">
            <label>অ্যাক্টিভ টেক্সট রঙ</label>
            <div class="ctrl">
                <input type="color" data-pair="sidebar_active_color" value="<?= htmlspecialchars($cur['sidebar_active_color']) ?>">
                <input type="text" name="sidebar_active_color" value="<?= htmlspecialchars($cur['sidebar_active_color']) ?>">
            </div>
        </div>
    </div>

    <!-- Submenu items -->
    <div class="ts-card" style="grid-column: 1 / -1;">
        <div class="ts-card-header">
            <span class="ts-card-icon"><i class="ti tabler-list-tree"></i></span>
            <div class="ts-card-text">
                <h6 class="ts-card-title">সাবমেনু</h6>
                <p class="ts-card-sub">এক্সপান্ড করা পারেন্টের নিচের আইটেম (যেমন "ছুটি" এর নিচের আইটেমগুলো)</p>
            </div>
        </div>

        <div class="theme-settings-wrap" style="gap: 14px;">
            <div>
                <div class="ts-row">
                    <label>টেক্সট রঙ</label>
                    <div class="ctrl">
                        <input type="color" data-pair="sidebar_submenu_color" value="<?= htmlspecialchars($cur['sidebar_submenu_color']) ?>">
                        <input type="text" name="sidebar_submenu_color" value="<?= htmlspecialchars($cur['sidebar_submenu_color']) ?>">
                    </div>
                </div>

                <div class="ts-row">
                    <label>ফন্ট সাইজ</label>
                    <div class="ctrl">
                        <input type="text" name="sidebar_submenu_font_size" value="<?= htmlspecialchars($cur['sidebar_submenu_font_size']) ?>" placeholder="0.9rem or 14px">
                    </div>
                </div>

                <div class="ts-row">
                    <label>ফন্ট ওয়েট</label>
                    <div class="ctrl">
                        <select name="sidebar_submenu_font_weight">
                            <?php foreach ($weightOptions as $w => $label): ?>
                                <option value="<?= $w ?>" <?= $cur['sidebar_submenu_font_weight'] == $w ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ts-row">
                    <label>হোভার ব্যাকগ্রাউন্ড</label>
                    <div class="ctrl">
                        <input type="text" class="wide" name="sidebar_submenu_hover_bg" value="<?= htmlspecialchars($cur['sidebar_submenu_hover_bg']) ?>" placeholder="rgba(255,255,255,0.04)">
                    </div>
                </div>

                <div class="ts-row">
                    <label>হোভার টেক্সট রঙ</label>
                    <div class="ctrl">
                        <input type="color" data-pair="sidebar_submenu_hover_color" value="<?= htmlspecialchars($cur['sidebar_submenu_hover_color']) ?>">
                        <input type="text" name="sidebar_submenu_hover_color" value="<?= htmlspecialchars($cur['sidebar_submenu_hover_color']) ?>">
                    </div>
                </div>
            </div>

            <div>
                <div class="ts-row">
                    <label>অ্যাক্টিভ ব্যাকগ্রাউন্ড</label>
                    <div class="ctrl">
                        <input type="color" data-pair="sidebar_submenu_active_bg" value="<?= htmlspecialchars($cur['sidebar_submenu_active_bg']) ?>">
                        <input type="text" name="sidebar_submenu_active_bg" value="<?= htmlspecialchars($cur['sidebar_submenu_active_bg']) ?>">
                    </div>
                </div>

                <div class="ts-row">
                    <label>অ্যাক্টিভ টেক্সট রঙ</label>
                    <div class="ctrl">
                        <input type="color" data-pair="sidebar_submenu_active_color" value="<?= htmlspecialchars($cur['sidebar_submenu_active_color']) ?>">
                        <input type="text" name="sidebar_submenu_active_color" value="<?= htmlspecialchars($cur['sidebar_submenu_active_color']) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ts-actions" style="grid-column: 1 / -1;">
        <button type="button" class="btn btn-label-danger" id="resetDefaultsBtn">
            <i class="ti tabler-refresh me-1"></i>ডিফল্টে রিসেট
        </button>
        <button type="button" class="btn btn-label-secondary" id="previewBtn">
            <i class="ti tabler-eye me-1"></i>প্রিভিউ
        </button>
        <button type="submit" class="btn btn-primary px-4">
            <i class="ti tabler-device-floppy me-1"></i>সেভ করুন
        </button>
    </div>

</form>

<script>
(function(){
    const form = document.getElementById('themeSettingsForm');
    if (!form) return;

    // Defaults (for reset)
    const defaults = <?= json_encode($defaults, JSON_UNESCAPED_SLASHES) ?>;

    // Keep color picker and text input in sync
    form.querySelectorAll('input[type="color"][data-pair]').forEach(cp => {
        const name = cp.dataset.pair;
        const txt = form.querySelector('input[type="text"][name="' + name + '"]');
        cp.addEventListener('input', () => { txt.value = cp.value; applyPreview(); });
        txt.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(txt.value)) cp.value = txt.value;
            applyPreview();
        });
    });
    form.querySelectorAll('input[type="text"]').forEach(t => {
        t.addEventListener('input', applyPreview);
    });

    // Map input name -> CSS variable name
    const varMap = {
        sidebar_bg_color: '--sb-bg',
        sidebar_menu_color: '--sb-menu-color',
        sidebar_icon_color: '--sb-icon-color',
        sidebar_hover_bg: '--sb-hover-bg',
        sidebar_hover_color: '--sb-hover-color',
        sidebar_active_bg: '--sb-active-bg',
        sidebar_active_color: '--sb-active-color',
        sidebar_menu_font_size: '--sb-menu-font-size',
        sidebar_menu_font_weight: '--sb-menu-font-weight',
        sidebar_submenu_color: '--sb-submenu-color',
        sidebar_submenu_hover_bg: '--sb-submenu-hover-bg',
        sidebar_submenu_hover_color: '--sb-submenu-hover-color',
        sidebar_submenu_active_bg: '--sb-submenu-active-bg',
        sidebar_submenu_active_color: '--sb-submenu-active-color',
        sidebar_submenu_font_size: '--sb-submenu-font-size',
        sidebar_submenu_font_weight: '--sb-submenu-font-weight',
        sidebar_section_label_color: '--sb-section-label',
        sidebar_brand_color: '--sb-brand',
        content_bg_color: '--content-bg',
    };

    // Selects should trigger preview too
    form.querySelectorAll('select[name]').forEach(s => {
        s.addEventListener('change', applyPreview);
    });

    function applyPreview() {
        const root = document.documentElement;
        for (const name in varMap) {
            const inp = form.querySelector('[name="' + name + '"]');
            if (inp) root.style.setProperty(varMap[name], inp.value);
        }
    }

    document.getElementById('previewBtn').addEventListener('click', applyPreview);

    // Logo file preview
    const logoFileInput = document.getElementById('header_logo_file');
    const logoPreview   = document.getElementById('brandLogoPreview');
    const logoFileName  = document.getElementById('brandLogoFileName');
    if (logoFileInput) {
        logoFileInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({ title: 'ত্রুটি', text: 'ফাইল ২ MB এর বেশি হতে পারবে না', icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false });
                this.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = e => {
                logoPreview.innerHTML = '<img src="' + e.target.result + '" alt="New logo">';
            };
            reader.readAsDataURL(file);
            logoFileName.textContent = 'নির্বাচিত: ' + file.name;
        });
    }

    document.getElementById('resetDefaultsBtn').addEventListener('click', () => {
        Swal.fire({
            title: 'ডিফল্টে ফিরিয়ে আনবেন?',
            text: 'সমস্ত মান প্রাথমিক ডিফল্টে সেট হয়ে যাবে (এখনো সেভ হবে না, শুধু ফিল্ডে আসবে)।',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'হ্যাঁ, রিসেট',
            cancelButtonText: 'বাতিল',
            customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
            buttonsStyling: false
        }).then(r => {
            if (!r.isConfirmed) return;
            for (const name in defaults) {
                const inp = form.querySelector('[name="' + name + '"]');
                if (inp) inp.value = defaults[name];
                const cp = form.querySelector('input[type="color"][data-pair="' + name + '"]');
                if (cp && /^#[0-9a-fA-F]{6}$/.test(defaults[name])) cp.value = defaults[name];
            }
            applyPreview();
        });
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        fetch('<?= BASE_URL ?>/api/settings/save-theme.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    Swal.fire({
                        title: 'সম্পন্ন', icon: 'success', timer: 1500, showConfirmButton: false,
                        confirmButtonColor: '#6c5ce7',
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire({
                        title: 'ত্রুটি', text: res.error || 'সেভ করা যায়নি', icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
                    });
                }
            })
            .catch(err => Swal.fire({
                title: 'ত্রুটি', text: String(err), icon: 'error',
                confirmButtonColor: '#ff3e1d',
                customClass: { confirmButton: 'btn btn-danger' }, buttonsStyling: false
            }));
    });
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
