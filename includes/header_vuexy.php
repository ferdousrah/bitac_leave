<?php
session_start();

// Record start time for performance profiling
$start = microtime(true);

// Include connection file
require_once(__DIR__ . '/../config/connection.php');

// Try to silently restore an expired session from the remember-me cookie
// before bouncing the user to the login page.
require_once(__DIR__ . '/remember-me.php');
remember_attempt($con);

// Check if the user is logged in, otherwise redirect to the login page.
// Absolute URL via BASE_URL — a relative 'index.php' resolves against the
// CURRENT page's directory (e.g. views/leave/index.php → 404) when the
// session expires deep inside /views/**.
if (!isset($_SESSION['username'])) {
    $loginURL = (defined('BASE_URL') ? BASE_URL : '') . '/index.php';
    echo "<script>alert('আপনার সেশনের মেয়াদ শেষ হয়েছে। অনুগ্রহ করে পুনরায় লগইন করুন।')</script>";
    echo "<script>window.location='" . htmlspecialchars($loginURL, ENT_QUOTES) . "'</script>";
    exit;
}

function ShowBangladeshDate()
{
    $hour = gmdate("H");
    $minute = gmdate("i");
    $seconds = gmdate("s");
    $day = gmdate("d");
    $month = gmdate("m");
    $year = gmdate("Y");
    $hour = $hour + 6;
    return date("Y-m-d", mktime ($hour,$minute,$seconds,$month,$day,$year));
}

function ShowBangladeshDate2()
{
    $hour = gmdate("H");
    $minute = gmdate("i");
    $seconds = gmdate("s");
    $day = gmdate("d");
    $month = gmdate("m");
    $year = gmdate("Y");
    $hour = $hour + 6;
    return date("m/d/Y", mktime ($hour,$minute,$seconds,$month,$day,$year));
}

function dateDiffInDays($date1, $date2)
{
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

// Get user information securely using prepared statements
$stmt = $con->prepare("SELECT * FROM user_list WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$getUserInfoQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ensure we have user data, redirect to login if not (absolute URL — see above)
if (!$getUserInfoQRW) {
    $loginURL = (defined('BASE_URL') ? BASE_URL : '') . '/index.php';
    echo "<script>alert('সেশন অবৈধ। অনুগ্রহ করে পুনরায় লগইন করুন।')</script>";
    echo "<script>window.location='" . htmlspecialchars($loginURL, ENT_QUOTES) . "'</script>";
    exit;
}

// Set default values for optional fields
if (!isset($getUserInfoQRW['full_name']) || empty($getUserInfoQRW['full_name'])) {
    $getUserInfoQRW['full_name'] = $getUserInfoQRW['user_id'] ?? 'User';
}

// Check for unread and important notifications
$stmt = $con->prepare("SELECT * FROM notification WHERE userID = ? AND isRead = 0 AND isImportant = 1 ORDER BY notificationID DESC LIMIT 10");
$stmt->bind_param("s", $_SESSION['userID']);
$stmt->execute();
$getMyMostImpNotificationsQ = $stmt->get_result();
$stmt->close();

// Get employee details — employee_list is the source of truth for the navbar
// display (name + designation + org + photo). user_list keeps cached copies,
// but they go stale when employee_list is edited. JOIN job_title for the live
// designation text.
$stmt = $con->prepare(
    "SELECT employee_list.employee_name, employee_list.organization_id,
            employee_list.photo, organization.organization_name,
            job_title.job_title_name
     FROM employee_list
     INNER JOIN organization ON employee_list.organization_id = organization.id
     LEFT JOIN job_title ON employee_list.designation = job_title.id
     WHERE employee_list.id = ?"
);
$stmt->bind_param("s", $getUserInfoQRW['employee_id']);
$stmt->execute();
$getEmployeeDetailsQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fallback for center admin users (no employee record) — get org from user_list.organization_id
if (!$getEmployeeDetailsQRW && !empty($getUserInfoQRW['organization_id'])) {
    $stmt = $con->prepare("SELECT organization_name FROM organization WHERE id = ?");
    $stmt->bind_param("i", $getUserInfoQRW['organization_id']);
    $stmt->execute();
    $orgRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($orgRow) {
        $getEmployeeDetailsQRW = ['organization_name' => $orgRow['organization_name'], 'photo' => ''];
    }
}

// Set default organization name if not found
if (!$getEmployeeDetailsQRW || !isset($getEmployeeDetailsQRW['organization_name'])) {
    $getEmployeeDetailsQRW = ['organization_name' => 'BITAC'];
}

// Pre-compute navbar display variables HERE before sidebar_menu_vuexy.php include
// (that include overwrites $getUserInfoQRW with a smaller query, losing full_name/isCenterAdmin/etc.)
//
// Prefer live employee_list values over the cached user_list copies so that
// edits in কর্মচারী তালিকা reflect immediately in the navbar.
$navUserDisplayName = $getEmployeeDetailsQRW['employee_name']
                   ?? $getUserInfoQRW['full_name']
                   ?? $getUserInfoQRW['user_id']
                   ?? 'User';
$navUserInitial     = mb_strtoupper(mb_substr($navUserDisplayName, 0, 1, 'UTF-8'), 'UTF-8');
$navUserDesignation = $getEmployeeDetailsQRW['job_title_name']
                   ?? (!empty($getUserInfoQRW['isCenterAdmin']) ? 'কেন্দ্র অ্যাডমিন' : null)
                   ?? $getUserInfoQRW['designation']
                   ?? 'Staff';
$navUserOrg         = $getEmployeeDetailsQRW['organization_name'] ?? 'BITAC';
$navUserPhoto       = $getEmployeeDetailsQRW['photo'] ?? '';
$navHasPhoto        = !empty($navUserPhoto);
$navProfileUrl      = BASE_URL . '/views/profile/my-account.php?menuslug=dashboard';

// ── Multi-role: assigned groups for the role-switcher in user dropdown ──
// $navUserGroups: list of [id, group_name] this user can switch between
// $navActiveGroupId: currently active group (matches user_list.user_group_id)
$navUserGroups = [];
$navActiveGroupId = (int)($getUserInfoQRW['user_group_id'] ?? 0);
if (!empty($getUserInfoQRW['dataID'])) {
    $rgStmt = $con->prepare(
        "SELECT uga.group_id, ug.group_name
         FROM user_group_assignment uga
         INNER JOIN user_group ug ON uga.group_id = ug.id
         WHERE uga.user_id = ? AND ug.deleted = 0
         ORDER BY ug.display_order ASC"
    );
    $rgStmt->bind_param("i", $getUserInfoQRW['dataID']);
    $rgStmt->execute();
    $rgRes = $rgStmt->get_result();
    while ($rg = $rgRes->fetch_assoc()) {
        $navUserGroups[] = ['id' => (int)$rg['group_id'], 'group_name' => $rg['group_name']];
    }
    $rgStmt->close();
}
// Resolve active group name for the badge label
$navActiveGroupName = '';
foreach ($navUserGroups as $g) {
    if ($g['id'] === $navActiveGroupId) { $navActiveGroupName = $g['group_name']; break; }
}
// Legacy users not in the assignment table — fall back to user_list.user_group_id name
if ($navActiveGroupName === '' && $navActiveGroupId > 0) {
    $rgStmt2 = $con->prepare("SELECT group_name FROM user_group WHERE id = ?");
    $rgStmt2->bind_param("i", $navActiveGroupId);
    $rgStmt2->execute();
    $rg2 = $rgStmt2->get_result()->fetch_assoc();
    $rgStmt2->close();
    if ($rg2) $navActiveGroupName = $rg2['group_name'];
}

// Define asset URL for use in templates
$assetURL = BASE_URL . '/vuexy-assets';

?>
<!doctype html>
<html lang="en" class="layout-navbar-fixed layout-menu-fixed" dir="ltr" data-skin="default" data-bs-theme="light" data-assets-path="<?= $assetURL ?>/" data-template="vertical-menu-template">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title><?php echo $getSettingsDetailsQRW['software_title']; ?></title>
    <meta name="description" content="BITAC Leave Management System" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/uploads/bitac-logo-inner.png" />

    <!-- Tiro Bangla — elegant, traditional, thin Bangla typeface -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiro+Bangla:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- PWA -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json" />
    <meta name="theme-color" content="#696cff" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="BITAC Leave" />
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/bitac-logo-inner.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap" rel="stylesheet" />
    <link href="https://fonts.maateen.me/siyam-rupali/font.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/fonts/iconify-icons.css" />
    <script src="<?= $assetURL ?>/vendor/libs/@algolia/autocomplete-js.js"></script>

    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/fonts/flag-icons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/css/core.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/sweetalert2/sweetalert2.css" />
    <!-- Use CDN Select2 CSS for testing -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/select2/select2.css" /> -->

    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/bootstrap-select/bootstrap-select.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/flatpickr/flatpickr.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/typeahead-js/typeahead.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/tagify/tagify.css" />
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/@form-validation/form-validation.css" />

    <!-- Modern Leave Tables (shared) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/app-assets/css/modern-leave-tables.css?v=<?= filemtime(__DIR__ . '/../app-assets/css/modern-leave-tables.css') ?>" />

    <!-- Global text-rendering override: remove blurry text-shadow / softening effects -->
    <style>
        /* Kill any inherited text-shadow that softens text rendering */
        *, *::before, *::after { text-shadow: none !important; }

        /* Crisp glyph rendering — fixes "jhapsa" (blurry) Bengali + Latin text */
        html, body, button, input, select, textarea {
            -webkit-font-smoothing: subpixel-antialiased;
            -moz-osx-font-smoothing: auto;
            text-rendering: optimizeLegibility;
        }

        /* Avoid sub-pixel rounding from translate3d transforms on common containers */
        .card, .modal-content, .dropdown-menu, .navbar, .menu, .layout-page {
            -webkit-transform: none;
            transform: none;
        }
    </style>

    <!-- Helpers -->
    <script src="<?= $assetURL ?>/vendor/js/helpers.js" data-turbo-eval="false"></script>
    <!-- Pre-set assetsPath and apply stored theme before config.js loads -->
    <script data-turbo-eval="false">
        window.assetsPath = '<?= $assetURL ?>/';
        window.templateName = 'vertical-menu-template';

        // Apply stored theme settings early to prevent flash of different colors
        (function() {
            try {
                // Force wide layout - remove any stored compact setting
                localStorage.removeItem('templateCustomizer-vertical-menu-template--ContentLayout');

                var storedTheme = localStorage.getItem('templateCustomizer-vertical-menu-template--Theme');
                if (storedTheme) {
                    document.documentElement.setAttribute('data-bs-theme', storedTheme);
                }
                var storedStyle = localStorage.getItem('templateCustomizer-vertical-menu-template--Style');
                if (storedStyle) {
                    document.documentElement.classList.remove('light-style', 'dark-style');
                    document.documentElement.classList.add(storedStyle + '-style');
                }
            } catch(e) {}
        })();
    </script>
    <script src="<?= $assetURL ?>/js/config.js?v=<?= time() ?>" data-turbo-eval="false"></script>

    <!-- Turbo for SPA-like navigation -->
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js"></script>
    <meta name="turbo-cache-control" content="no-cache">
    

    <!-- Theme variables (driven by template_settings) -->
    <style>
        :root {
            --sb-bg: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_bg_color'] ?? '#0f1419') ?>;
            --sb-menu-color: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_menu_color'] ?? '#c1c2c5') ?>;
            --sb-icon-color: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_icon_color'] ?? '#909196') ?>;
            --sb-hover-bg: <?= $getSettingsDetailsQRW['sidebar_hover_bg'] ?? 'rgba(255,255,255,0.04)' ?>;
            --sb-hover-color: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_hover_color'] ?? '#ffffff') ?>;
            --sb-active-bg: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_active_bg'] ?? '#24302C') ?>;
            --sb-active-color: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_active_color'] ?? '#ffffff') ?>;
            --sb-menu-font-size: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_menu_font_size'] ?? '1.0rem') ?>;
            --sb-submenu-color: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_submenu_color'] ?? '#a1a1aa') ?>;
            --sb-submenu-hover-bg: <?= $getSettingsDetailsQRW['sidebar_submenu_hover_bg'] ?? 'rgba(255,255,255,0.04)' ?>;
            --sb-submenu-hover-color: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_submenu_hover_color'] ?? '#ffffff') ?>;
            --sb-submenu-active-bg: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_submenu_active_bg'] ?? '#24302C') ?>;
            --sb-submenu-active-color: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_submenu_active_color'] ?? '#ffffff') ?>;
            --sb-submenu-font-size: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_submenu_font_size'] ?? '0.9rem') ?>;
            --sb-menu-font-weight: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_menu_font_weight'] ?? '600') ?>;
            --sb-submenu-font-weight: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_submenu_font_weight'] ?? '500') ?>;
            --sb-section-label: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_section_label_color'] ?? '#52525b') ?>;
            --sb-brand: <?= htmlspecialchars($getSettingsDetailsQRW['sidebar_brand_color'] ?? '#ffffff') ?>;
            --content-bg: <?= htmlspecialchars($getSettingsDetailsQRW['content_bg_color'] ?? '#D9DDE0') ?>;
        }
    </style>

    <!-- Custom Styles -->
    <style>
        /* Prevent layout shift when modal opens by always reserving scrollbar space */
        html {
            scrollbar-gutter: stable;
        }

        /* Prevent body padding adjustment when SweetAlert2 modal opens */
        body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) {
            padding-right: 0 !important;
        }

        body {
            font-family: 'Tiro Bangla', 'Inter', 'Kalpurush', 'Public Sans', Arial, sans-serif !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        table th {
            font-size: <?php echo $getSettingsDetailsQRW['table_heading_font_size'] + 2; ?>px;
            text-transform: <?php echo $getSettingsDetailsQRW['form_heading_text_transform']; ?>;
        }

        table td {
            font-size: <?php echo $getSettingsDetailsQRW['table_data_font_size'] + 2; ?>px;
        }

        .form-label {
            font-size: <?php echo $getSettingsDetailsQRW['form_label_font_size'] + 2; ?>px;
            text-transform: <?php echo $getSettingsDetailsQRW['form_label_text_transform']; ?>;
        }

        input[type="text"], input[type="password"], input[type="email"], input[type="number"], textarea, select {
            font-size: <?php echo $getSettingsDetailsQRW['form_input_font_size'] + 2; ?>px;
        }

        .form-group-bg {
            background-color: #feefef;
        }

        .turbo-progress-bar {
            position: fixed;
            display: block;
            top: 0;
            left: 0;
            height: 3px;
            background: #696cff;
            z-index: 9999;
            transition: width 300ms ease-out, opacity 150ms 150ms ease-in;
        }

        /* Select2 - using Bootstrap 5 theme from CDN, minor overrides if needed */
        .select2-container {
            width: 100% !important;
        }

        /* Sidebar menu font size */
        .menu-vertical .menu-inner > .menu-item > .menu-link {
            font-size: 1.075rem;
        }

        .menu-vertical .menu-sub .menu-link {
            font-size: 1.025rem;
        }

        /* ═══════════════════════════════════════════════════
           Sidebar refinement — matches dashboard design
        ═══════════════════════════════════════════════════ */

        /* Page surface — light gray content area, sidebar stays dark + flush */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: var(--sb-bg) !important;
        }
        body {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }
        .layout-wrapper,
        .layout-container {
            background: transparent !important;
            margin: 0 !important;
            padding: 0 !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        .layout-page {
            background: var(--content-bg) !important;
            min-height: 100vh;
            margin: 0 !important;
            padding-top: 0 !important;
            padding-block-start: 0 !important;
            /* NOTE: keep default horizontal padding — Vuexy adds padding-inline-start = sidebar width
               at >=1200px to keep content out from under the fixed sidebar. */
        }
        .container-xxl,
        .content-wrapper {
            background: transparent !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        /* Kill any potential gap above the navbar */
        .bitac-navbar { margin-top: 0 !important; margin-block-start: 0 !important; }

        /* Disable Vuexy's fixed-navbar blur pseudo-element — it's no longer needed
           since our navbar is position: static and flows inline with the content. */
        .layout-wrapper .layout-page::before,
        .layout-wrapper::before,
        .layout-container::before,
        .content-wrapper::before,
        .layout-navbar::before,
        .layout-page::after,
        .layout-wrapper::after,
        .layout-container::after {
            display: none !important;
            content: none !important;
            block-size: 0 !important;
            inline-size: 0 !important;
            height: 0 !important;
            width: 0 !important;
        }

        /* Nuclear option: force everything at the top to have zero vertical offset */
        body > .layout-wrapper,
        .layout-wrapper > .layout-container,
        .layout-container > aside#layout-menu,
        .layout-container > .layout-page,
        .layout-page > .layout-navbar,
        .layout-page > nav.bitac-navbar {
            margin-top: 0 !important;
            margin-block-start: 0 !important;
            padding-top: 0 !important;
            padding-block-start: 0 !important;
            top: 0 !important;
            inset-block-start: 0 !important;
        }

        /* Also force sidebar to start at y=0 */
        #layout-menu.layout-menu {
            inset-block-start: 0 !important;
            top: 0 !important;
        }

        /* Kill demo.css's 72px top reservation for the fixed navbar (ours is static now).
           Selector made more specific than demo.css by prefixing with html. */
        html.layout-navbar-fixed .layout-wrapper:not(.layout-horizontal):not(.layout-without-menu) .layout-page,
        html .layout-wrapper .layout-page {
            padding-top: 0 !important;
            padding-block-start: 0 !important;
        }

        /* Sidebar surface — dark, flush with left edge */
        #layout-menu.layout-menu {
            background: var(--sb-bg) !important;
            border: 0 !important;
            border-right: 1px solid rgba(255,255,255,0.04) !important;
            box-shadow: none;
            margin: 0 !important;
            border-radius: 0 !important;
        }
        #layout-menu .menu-inner-shadow {
            background: linear-gradient(180deg, var(--sb-bg) 60%, transparent 100%) !important;
        }

        /* Brand (logo + title) */
        #layout-menu .app-brand.demo {
            padding: 20px 20px 18px;
            height: auto;
            border-bottom: 1px solid #1a202e;
            margin-bottom: 14px;
        }
        #layout-menu .app-brand-link { gap: 10px; }
        #layout-menu .app-brand-text.menu-text {
            color: var(--sb-brand) !important;
            letter-spacing: -0.2px;
            font-weight: 700 !important;
            font-size: 1.05rem !important;
        }
        #layout-menu .layout-menu-toggle {
            color: #6b7280 !important;
            background: transparent;
            border-radius: 8px;
            width: 28px; height: 28px;
            display: inline-flex !important;
            align-items: center; justify-content: center;
            transition: all .15s ease;
        }
        #layout-menu .layout-menu-toggle:hover {
            color: var(--sb-hover-color) !important;
            background: rgba(255,255,255,0.05);
        }

        /* Section group label (e.g. MAIN MENU) */
        .menu-section-label {
            padding: 18px 22px 10px;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--sb-section-label);
            text-transform: uppercase;
            letter-spacing: 1.4px;
        }

        /* Top-level menu items */
        #layout-menu.menu-vertical .menu-inner > .menu-item { margin: 2px 10px; }
        #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link {
            color: var(--sb-menu-color) !important;
            padding: 10px 14px !important;
            border-radius: 8px !important;
            font-weight: var(--sb-menu-font-weight) !important;
            font-size: var(--sb-menu-font-size) !important;
            transition: background .18s ease, color .18s ease, padding .18s ease;
            position: relative;
        }
        #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link .menu-icon {
            color: var(--sb-icon-color) !important;
            font-size: 1.2rem !important;
            margin-right: 12px !important;
            transition: color .18s ease;
        }
        #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link:hover {
            color: var(--sb-hover-color) !important;
            background: var(--sb-hover-bg) !important;
        }
        #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link:hover .menu-icon {
            color: var(--sb-hover-color) !important;
        }

        /* Expanded (open) parent item — subtle visual cue */
        #layout-menu.menu-vertical .menu-inner > .menu-item.open:not(.active) > .menu-link {
            color: var(--sb-hover-color) !important;
            background: rgba(255,255,255,0.03) !important;
        }
        #layout-menu.menu-vertical .menu-inner > .menu-item.open:not(.active) > .menu-link .menu-icon {
            color: var(--sb-hover-color) !important;
        }

        /* Active top-level item — clean dark highlight, no extras */
        #layout-menu.menu-vertical .menu-inner > .menu-item.active > .menu-link,
        #layout-menu.menu-vertical .menu-inner > .menu-item.active.open > .menu-link {
            background: var(--sb-active-bg) !important;
            color: var(--sb-active-color) !important;
            font-weight: var(--sb-menu-font-weight) !important;
            box-shadow: none !important;
        }
        #layout-menu.menu-vertical .menu-inner > .menu-item.active > .menu-link::before,
        #layout-menu.menu-vertical .menu-inner > .menu-item.active.open > .menu-link::before {
            content: none !important;
            display: none !important;
        }
        #layout-menu.menu-vertical .menu-inner > .menu-item.active > .menu-link .menu-icon,
        #layout-menu.menu-vertical .menu-inner > .menu-item.active.open > .menu-link .menu-icon {
            color: var(--sb-active-color) !important;
        }

        /* Submenu container (no longer owns the vertical guide — per-item segments do) */
        #layout-menu.menu-vertical .menu-sub {
            position: relative;
            padding: 4px 0 8px 32px !important;
            margin: 2px 0 4px;
        }
        #layout-menu.menu-vertical .menu-sub::before {
            content: none !important;
            display: none !important;
        }

        /* Per-item vertical line segment — so the guide can terminate at the last item */
        #layout-menu.menu-vertical .menu-sub .menu-item { position: relative; }
        #layout-menu.menu-vertical .menu-sub .menu-item::before {
            content: "";
            position: absolute;
            left: -10px;
            top: -2px;
            bottom: -2px;
            width: 2px;
            background: rgba(255,255,255,0.28);
            border-radius: 2px;
            pointer-events: none;
        }
        #layout-menu.menu-vertical .menu-sub .menu-item:last-child::before {
            bottom: auto;
            height: calc(50% + 2px);
        }

        /* Submenu items — clean, no dot decoration */
        #layout-menu.menu-vertical .menu-sub .menu-item { margin: 2px 10px 2px 0; }
        #layout-menu.menu-vertical .menu-sub .menu-link {
            color: var(--sb-submenu-color) !important;
            padding: 8px 14px !important;
            border-radius: 7px !important;
            font-size: var(--sb-submenu-font-size) !important;
            font-weight: var(--sb-submenu-font-weight) !important;
            position: relative;
            transition: color .15s ease, background .15s ease;
        }
        /* Horizontal connector line from the vertical guide to each submenu item */
        #layout-menu.menu-vertical .menu-sub .menu-link::before {
            content: "" !important;
            position: absolute !important;
            left: -10px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 10px !important;
            height: 2px !important;
            background-color: rgba(255,255,255,0.28) !important;
            background-image: none !important;
            border-radius: 2px !important;
            display: block !important;
            pointer-events: none;
            mask: none !important;
            -webkit-mask: none !important;
            inset-inline-start: -10px !important;
            block-size: 2px !important;
            inline-size: 10px !important;
        }
        /* Suppress any Vuexy ::after decoration on these links */
        #layout-menu.menu-vertical .menu-sub .menu-link::after {
            content: none !important;
            display: none !important;
        }
        #layout-menu.menu-vertical .menu-sub .menu-link:hover {
            color: var(--sb-submenu-hover-color) !important;
            background: var(--sb-submenu-hover-bg) !important;
        }
        #layout-menu.menu-vertical .menu-sub .menu-item.active > .menu-link {
            color: var(--sb-submenu-active-color) !important;
            background: var(--sb-submenu-active-bg) !important;
            font-weight: var(--sb-submenu-font-weight) !important;
            box-shadow: none !important;
        }

        /* Chevron arrow on expandable items */
        #layout-menu.menu-vertical .menu-toggle::after {
            color: #6b7280;
            font-size: 0.85rem;
            transition: transform .2s ease, color .15s ease;
        }
        #layout-menu.menu-vertical .menu-toggle:hover::after,
        #layout-menu.menu-vertical .menu-item.active > .menu-toggle::after,
        #layout-menu.menu-vertical .menu-item.open > .menu-toggle::after {
            color: #ffffff;
        }
        #layout-menu.menu-vertical .menu-item.open > .menu-toggle::after {
            transform: rotate(90deg);
        }

        /* Notification badges — subtle */
        #layout-menu.menu-vertical .badge.bg-danger,
        #layout-menu.menu-vertical .badge.rounded-pill.bg-danger {
            background: #ea5455 !important;
            color: #fff !important;
            font-size: 0.65rem !important;
            font-weight: 700 !important;
            padding: 2px 7px !important;
            letter-spacing: 0.3px;
            border: 0 !important;
            box-shadow: none !important;
            min-width: 20px;
            text-align: center;
        }

        /* Sidebar scrollbar */
        #layout-menu .menu-inner::-webkit-scrollbar { width: 6px; }
        #layout-menu .menu-inner::-webkit-scrollbar-track { background: transparent; }
        #layout-menu .menu-inner::-webkit-scrollbar-thumb {
            background: #1a202e;
            border-radius: 3px;
        }
        #layout-menu .menu-inner::-webkit-scrollbar-thumb:hover { background: #2a3244; }

        /* ═══════════════════════════════════════════════════
           Sidebar — width + mobile drawer + polish
        ═══════════════════════════════════════════════════ */

        /* Wider sidebar — default width, but resizable via --bs-menu-width drag handle */
        :root, html { --bs-menu-width: 17.5rem; --sb-collapsed-w: 78px; }
        #layout-menu.layout-menu {
            width: var(--bs-menu-width) !important;
            inline-size: var(--bs-menu-width) !important;
            display: flex !important;
            flex-direction: column !important;
            transition: width 0.22s ease, inline-size 0.22s ease;
        }
        @media (min-width: 1200px) {
            html.layout-menu-fixed .layout-wrapper:not(.layout-horizontal):not(.layout-without-menu) .layout-page,
            html.layout-menu-fixed-offcanvas .layout-wrapper:not(.layout-horizontal):not(.layout-without-menu) .layout-page {
                padding-inline-start: var(--bs-menu-width) !important;
                transition: padding-inline-start 0.22s ease;
            }
        }

        /* ── Desktop collapsed state (icon-only rail, hover-to-expand) ── */
        @media (min-width: 1200px) {
            /* Collapsed: narrow rail (handle both html.* and body.* — Vuexy versions vary) */
            html.layout-menu-collapsed #layout-menu.layout-menu,
            body.layout-menu-collapsed #layout-menu.layout-menu {
                width: var(--sb-collapsed-w) !important;
                inline-size: var(--sb-collapsed-w) !important;
                min-width: var(--sb-collapsed-w) !important;
                max-width: var(--sb-collapsed-w) !important;
            }
            html.layout-menu-collapsed .layout-wrapper:not(.layout-horizontal):not(.layout-without-menu) .layout-page,
            body.layout-menu-collapsed .layout-wrapper:not(.layout-horizontal):not(.layout-without-menu) .layout-page {
                padding-inline-start: var(--sb-collapsed-w) !important;
            }

            /* Hide text while collapsed — keeps just icons */
            html.layout-menu-collapsed #layout-menu .app-brand-text,
            html.layout-menu-collapsed #layout-menu .menu-section-label,
            html.layout-menu-collapsed #layout-menu .menu-link > div,
            body.layout-menu-collapsed #layout-menu .app-brand-text,
            body.layout-menu-collapsed #layout-menu .menu-section-label,
            body.layout-menu-collapsed #layout-menu .menu-link > div {
                display: none !important;
            }
            /* Hide chevron arrow on toggleable items in collapsed state */
            html.layout-menu-collapsed #layout-menu .menu-toggle::after,
            body.layout-menu-collapsed #layout-menu .menu-toggle::after {
                content: none !important;
                display: none !important;
            }

            /* Center icons in collapsed rail */
            html.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item,
            body.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item {
                margin: 4px 8px !important;
            }
            html.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link,
            body.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link {
                padding: 11px 0 !important;
                justify-content: center;
            }
            html.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link .menu-icon,
            body.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link .menu-icon {
                margin: 0 !important;
                font-size: 1.35rem !important;
            }

            /* Hide submenu fly-out content in collapsed state */
            html.layout-menu-collapsed #layout-menu .menu-sub,
            body.layout-menu-collapsed #layout-menu .menu-sub {
                display: none !important;
            }

            /* Brand area in collapsed state — show logo + toggle (stacked) */
            html.layout-menu-collapsed #layout-menu .app-brand.demo,
            body.layout-menu-collapsed #layout-menu .app-brand.demo {
                flex-direction: column;
                gap: 10px;
                padding: 16px 0 !important;
                justify-content: center;
                align-items: center;
                position: relative;
            }
            html.layout-menu-collapsed #layout-menu .app-brand-link,
            body.layout-menu-collapsed #layout-menu .app-brand-link {
                width: auto;
                gap: 0;
            }
            html.layout-menu-collapsed #layout-menu .app-brand-logo img,
            body.layout-menu-collapsed #layout-menu .app-brand-logo img {
                height: 32px !important;
                width: auto;
            }
            /* Toggle button stays visible & clickable so user can expand back */
            html.layout-menu-collapsed #layout-menu .layout-menu-toggle,
            body.layout-menu-collapsed #layout-menu .layout-menu-toggle {
                position: static !important;
                margin: 0 auto !important;
                width: 32px !important;
                height: 32px !important;
                background: rgba(255, 255, 255, 0.05) !important;
                border-radius: 8px !important;
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                opacity: 1 !important;
                pointer-events: auto !important;
                transform: none !important;
            }
            html.layout-menu-collapsed #layout-menu .layout-menu-toggle:hover,
            body.layout-menu-collapsed #layout-menu .layout-menu-toggle:hover {
                background: rgba(255, 255, 255, 0.12) !important;
            }

            /* Active accent bar position adjustment in collapsed */
            html.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item.active::before,
            html.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item.active.open::before,
            body.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item.active.open::before,
            body.layout-menu-collapsed #layout-menu.menu-vertical .menu-inner > .menu-item.active::before {
                left: -8px;
                top: 12px;
                bottom: 12px;
            }

            /* Notification badge in collapsed — show as small dot */
            html.layout-menu-collapsed #layout-menu .menu-link .badge.bg-danger,
            body.layout-menu-collapsed #layout-menu .menu-link .badge.bg-danger {
                position: absolute;
                top: 6px;
                right: 12px;
                min-width: 8px !important;
                width: 8px;
                height: 8px;
                padding: 0 !important;
                border-radius: 50% !important;
                font-size: 0 !important;
                line-height: 0;
            }
        }

        /* Sticky brand — stays put while menu scrolls */
        #layout-menu .app-brand.demo {
            position: sticky;
            top: 0;
            z-index: 3;
            background: var(--sb-bg);
            flex-shrink: 0;
        }

        /* Menu inner — flexible scroll area */
        #layout-menu .menu-inner {
            flex: 1 1 auto;
            min-height: 0;
            overscroll-behavior: contain;
        }

        /* Active item — subtle left accent bar.
           Attached to the .menu-link (NOT the .menu-item li) so when an active parent
           is expanded with submenu visible, the bar only covers the link's height,
           not the entire li including the submenu list. */
        #layout-menu.menu-vertical .menu-inner > .menu-item { position: relative; }
        #layout-menu.menu-vertical .menu-inner > .menu-item.active > .menu-link::before,
        #layout-menu.menu-vertical .menu-inner > .menu-item.active.open > .menu-link::before {
            content: "";
            position: absolute;
            left: -14px;
            top: 6px;
            bottom: 6px;
            width: 3px;
            background: linear-gradient(180deg, var(--sb-active-color, #fff) 0%, rgba(255,255,255,0.55) 100%);
            border-radius: 0 3px 3px 0;
            opacity: 0.9;
            pointer-events: none;
        }

        /* Notification badge alignment */
        #layout-menu.menu-vertical .menu-link .badge.bg-danger {
            margin-left: auto;
            align-self: center;
        }

        /* ── Mobile drawer (< 1200px) ───────────────────────── */
        @media (max-width: 1199.98px) {
            #layout-menu.layout-menu {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                bottom: 0 !important;
                width: 290px !important;
                max-width: 85vw !important;
                z-index: 1100 !important;
                transform: translate3d(-100%, 0, 0);
                transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 4px 0 24px rgba(0, 0, 0, 0.18);
                will-change: transform;
            }
            html.layout-menu-expanded #layout-menu.layout-menu,
            body.layout-menu-expanded #layout-menu.layout-menu {
                transform: translate3d(0, 0, 0);
            }

            /* Lock body scroll when drawer is open */
            html.layout-menu-expanded,
            html.layout-menu-expanded body {
                overflow: hidden !important;
            }

            /* Bigger tap targets on mobile */
            #layout-menu.menu-vertical .menu-inner > .menu-item > .menu-link {
                padding: 12px 16px !important;
            }
            #layout-menu.menu-vertical .menu-sub .menu-link {
                padding: 10px 14px !important;
            }

            /* Sidebar's built-in close button (×) — pill shape, top-right */
            #layout-menu .layout-menu-toggle {
                width: 34px !important;
                height: 34px !important;
                border-radius: 10px !important;
                background: rgba(255, 255, 255, 0.06) !important;
                color: #e5e7eb !important;
                font-size: 1.1rem;
            }
            #layout-menu .layout-menu-toggle:hover {
                background: rgba(255, 255, 255, 0.12) !important;
                color: #fff !important;
            }

            /* Overlay backdrop — only visible when drawer is open */
            .layout-overlay.layout-menu-toggle {
                background: rgba(15, 20, 25, 0.55) !important;
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
                z-index: 1099 !important;
            }
        }

        /* Floating mobile toggler — hidden; we use the navbar's own hamburger instead */
        .menu-mobile-toggler { display: none !important; }

        /* Smaller mobile screens — narrower drawer */
        @media (max-width: 575px) {
            #layout-menu.layout-menu {
                width: 86vw !important;
                max-width: 320px !important;
            }
        }

        /* Navbar's inline mobile toggle — the primary hamburger button on mobile.
           The floating .menu-mobile-toggler can stay as a fallback. */
        .bitac-navbar .layout-menu-toggle.navbar-nav {
            margin: 0 !important;
            padding: 0 !important;
        }
        .bitac-navbar .layout-menu-toggle.navbar-nav .nav-item.nav-link {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            color: #2c2e3a !important;
            background: transparent;
            transition: background 0.15s ease;
            padding: 0 !important;
        }
        .bitac-navbar .layout-menu-toggle.navbar-nav .nav-item.nav-link:hover {
            background: #f0edff !important;
            color: #5648c4 !important;
        }
        .bitac-navbar .layout-menu-toggle.navbar-nav .nav-item.nav-link i {
            font-size: 1.4rem !important;
            color: inherit !important;
        }

        /* Tooltip styling for collapsed sidebar icons */
        .sidebar-icon-tooltip {
            --bs-tooltip-bg: #2c2e3a;
            --bs-tooltip-color: #fff;
            --bs-tooltip-padding-x: 10px;
            --bs-tooltip-padding-y: 6px;
            --bs-tooltip-border-radius: 6px;
            --bs-tooltip-font-size: 0.82rem;
            font-weight: 500;
            z-index: 1102 !important;
        }
        .sidebar-icon-tooltip .tooltip-inner {
            background: #2c2e3a;
            color: #fff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.82rem;
            border-radius: 6px;
        }
        .sidebar-icon-tooltip .tooltip-arrow::before {
            border-right-color: #2c2e3a !important;
        }

        /* ═══════════════════════════════════════════════════
           Navbar — clean light header with page title
        ═══════════════════════════════════════════════════ */
        .bitac-navbar {
            position: static !important;
            inset: auto !important;
            background: #ffffff !important;
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            border-radius: 0 !important;
            border: 0 !important;
            border-bottom: 1px solid #eef0f3 !important;
            box-shadow: none !important;
            padding: 12px 24px !important;
            padding-block: 12px !important;
            padding-inline: 24px !important;
            height: auto !important;
            block-size: auto !important;
            min-height: 64px !important;
            min-block-size: 64px !important;
            flex-wrap: nowrap !important;
            justify-content: space-between !important;
            gap: 16px;
        }
        /* Keep the left title block from over-growing */
        .bitac-navbar > .d-flex:first-child {
            flex: 1 1 auto !important;
            min-width: 0;
        }
        /* Right cluster safety — ensure it's always visible at its natural size */
        .bitac-navbar .bitac-nav-right {
            flex: 0 0 auto !important;
            display: flex !important;
            align-items: center;
            gap: 6px;
            margin-left: auto !important;
        }
        /* Icon button hover */
        .bitac-nav-icon:hover {
            background: #eef0f3 !important;
            color: #111827 !important;
        }
        .bitac-nav-icon:hover i { color: #111827 !important; }
        /* User avatar hover */
        .dropdown-user .user-avatar-btn:hover {
            background: #f3f5f8 !important;
        }
        /* Hide Bootstrap's default dropdown caret on these triggers */
        .bitac-nav-right .dropdown-toggle::after,
        .bitac-nav-right .user-avatar-btn::after { display: none !important; }
        /* Mobile: hide divider */
        @media (max-width: 767px) {
            .bitac-navbar .bitac-nav-divider-line { display: none !important; }
        }
        .bitac-navbar::before,
        .bitac-navbar::after { display: none !important; }
        .bitac-navbar .navbar-title {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .bitac-navbar .navbar-title-text {
            font-size: 1.3rem !important;
            font-weight: 400 !important;
            color: #1f2937 !important;
            letter-spacing: 0;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 520px;
            margin: 0 !important;
        }
        .bitac-navbar .navbar-title-sub {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 3px;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 520px;
            font-weight: 500;
        }
        .bitac-navbar .navbar-title-sub strong {
            color: #111827;
            font-weight: 600;
        }

        .bitac-navbar .navbar-icon-btn {
            width: 40px !important;
            height: 40px !important;
            border-radius: 10px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            color: #4b5563 !important;
            background: transparent !important;
            transition: background .15s ease, color .15s ease;
            padding: 0 !important;
            position: relative;
            line-height: 1;
        }
        .bitac-navbar .navbar-icon-btn:hover {
            background: #eef0f3 !important;
            color: #111827 !important;
        }
        .bitac-navbar .navbar-icon-btn::after { display: none !important; }

        /* Bell + always-visible red dot indicator (matches Weavy) */
        .bitac-navbar .bell-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .bitac-navbar .bell-dot {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #ea5455;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px rgba(234,84,85,0.25), 0 2px 4px rgba(234,84,85,0.35);
            z-index: 2;
        }

        .bitac-navbar .bitac-nav-divider-item {
            list-style: none;
            margin: 0 12px;
            align-items: center;
            padding: 0;
            display: flex !important;
        }
        .bitac-navbar .bitac-nav-divider {
            display: inline-block;
            width: 1px;
            height: 32px;
            background: #d1d5db;
        }
        @media (max-width: 767px) {
            .bitac-navbar .bitac-nav-divider-item { display: none !important; }
        }

        /* Tidy avatar dropdown button in new navbar */
        .bitac-navbar .user-avatar-btn { padding: 2px !important; }
        .bitac-navbar .user-avatar-btn:hover { background: transparent !important; }
        .bitac-navbar .user-avatar-btn .avatar img,
        .bitac-navbar .user-avatar-btn .avatar .avatar-initial {
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px #e5e7eb;
            transition: box-shadow .15s ease;
        }
        .bitac-navbar .user-avatar-btn:hover .avatar img,
        .bitac-navbar .user-avatar-btn:hover .avatar .avatar-initial {
            box-shadow: 0 0 0 2px #696cff;
        }

        /* Small chevron hint next to avatar */
        .bitac-navbar .user-avatar-btn::after {
            content: "";
            display: inline-block;
            width: 6px; height: 6px;
            border-right: 1.5px solid #9ca3af;
            border-bottom: 1.5px solid #9ca3af;
            transform: rotate(45deg) translate(-2px, -2px);
            margin-left: 10px;
            margin-right: 2px;
            vertical-align: middle;
            transition: border-color .15s ease;
        }
        .bitac-navbar .user-avatar-btn:hover::after {
            border-color: #111827;
        }

        /* Bell positioning in the new navbar */
        .bitac-navbar .notif-bell-btn {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: inline-flex !important;
            align-items: center; justify-content: center;
            padding: 0 !important;
        }
        .bitac-navbar .notif-bell-btn:hover { background: #f3f5f8 !important; }

        /* Mobile: stack title smaller, hide subtitle */
        @media (max-width: 575px) {
            .bitac-navbar { padding: 12px 16px !important; }
            .navbar-title-text { font-size: 1rem; max-width: 200px; }
            .navbar-title-sub { display: none; }
        }

        /* (Sidebar entrance animation removed — was re-playing on navigation and contributing
           to the "refreshing" look. Kept reduced-motion stub for future use.) */

        /* ── Notification dropdown panel (moved out of <ul>) ── */
        .notif-bell-btn { transition: background 0.2s ease; border-radius: 10px; }
        .notif-dropdown-menu { animation: notifSlideIn 0.22s cubic-bezier(.22,.68,0,1.2); }

        /* Mobile: notification + user dropdowns become near-full-width */
        @media (max-width: 575.98px) {
            .notif-dropdown-menu {
                width: calc(100vw - 24px) !important;
                max-width: 360px !important;
                position: fixed !important;
                top: 64px !important;
                left: 12px !important;
                right: 12px !important;
                margin: 0 auto !important;
                inset: 64px 12px auto 12px !important;
                transform: none !important;
            }
            .user-dropdown-menu {
                width: calc(100vw - 24px) !important;
                max-width: 280px !important;
                position: fixed !important;
                top: 64px !important;
                right: 12px !important;
                left: auto !important;
                inset: 64px 12px auto auto !important;
                transform: none !important;
            }
            /* Cap list height so it doesn't run off-screen */
            .dropdown-notifications-list { max-height: 60vh !important; }
        }
        @keyframes notifSlideIn {
            from { opacity:0; transform: translateY(-10px) scale(0.97); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }
        .dropdown-notifications-list::-webkit-scrollbar { width: 5px; }
        .dropdown-notifications-list::-webkit-scrollbar-track { background: #f8f9fb; }
        .dropdown-notifications-list::-webkit-scrollbar-thumb { background: #d1d5e0; border-radius: 4px; }
        .notif-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 13px 18px; border-bottom: 1px solid #f3f4f8;
            text-decoration: none; color: inherit; position: relative;
            transition: background 0.15s ease; cursor: pointer;
        }
        .notif-item:hover { background: #f5f7ff; text-decoration: none; color: inherit; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item-unread { background: #fafbff; }
        .notif-item-unread .notif-item-title { color: #1e293b; }
        .notif-unread-dot {
            position: absolute; top: 18px; right: 14px;
            width: 8px; height: 8px; border-radius: 50%; background: #4338ca;
            box-shadow: 0 0 0 2px #fff;
        }
        .notif-icon-wrap {
            flex-shrink: 0; width: 38px; height: 38px;
            border-radius: 11px; display: flex;
            align-items: center; justify-content: center;
            font-size: 1rem;
        }
        .notif-item-title { font-size: 0.84rem; font-weight: 600; color: #3A3D53; line-height: 1.4; margin-bottom: 2px; }
        .notif-item-time  { font-size: 0.72rem; color: #adb5bd; margin-top: 3px; display: flex; align-items: center; gap: 3px; }
        .notif-empty { padding: 36px 20px; text-align: center; }
        .notif-empty i { font-size: 2.8rem; color: #dee2e6; display: block; margin-bottom: 10px; }
        .notif-empty p { color: #adb5bd; font-size: 0.84rem; margin: 0; font-weight: 500; }

        /* ── User dropdown panel (moved out of <ul>) ── */
        .user-avatar-btn { border-radius: 10px; transition: background 0.2s ease; }
        .user-dropdown-menu { animation: userDropIn 0.22s cubic-bezier(.22,.68,0,1.2); margin-top: 8px !important; }
        @keyframes userDropIn {
            from { opacity:0; transform: translateY(-10px) scale(0.97); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }
        .user-dd-item {
            display: flex; align-items: center; gap: 11px;
            padding: 9px 16px; font-size: 0.875rem; font-weight: 500;
            color: #3A3D53; text-decoration: none;
            transition: background 0.15s ease;
        }
        .user-dd-item:hover { background: #f5f7ff; color: #3A3D53; text-decoration: none; }
        .user-dd-icon-wrap {
            flex-shrink: 0; width: 34px; height: 34px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
        }

        /* Multi-role: active-role label */
        .role-current-label {
            display: flex; align-items: center; gap: 10px;
            background: #f0edff; border: 1px solid #e0d9f9;
            border-radius: 10px; padding: 8px 11px;
        }
        .role-current-icon {
            flex-shrink: 0; width: 32px; height: 32px; border-radius: 8px;
            background: #6c5ce7; color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
        }
        .role-current-caption {
            font-size: 0.58rem; color: #6b6b80; letter-spacing: 0.03em;
            text-transform: uppercase; font-weight: 600;
        }
        .role-current-name {
            font-size: 0.78rem; color: #2c2e3a; font-weight: 600; line-height: 1.25;
        }

        /* Multi-role: switcher list */
        .role-switch-header {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 16px 4px;
            font-size: 0.7rem; color: #8a90a6;
            letter-spacing: 0.04em; text-transform: uppercase; font-weight: 700;
        }
        .role-switch-item.is-active { cursor: default; }
        .role-switch-item.is-active:hover { background: transparent; }
        .role-switch-active-pill {
            margin-left: auto;
            font-size: 0.62rem; font-weight: 700;
            color: #28c76f; background: #e8f5e9;
            padding: 2px 8px; border-radius: 999px;
            letter-spacing: 0.04em; text-transform: uppercase;
        }

        /* Pull submenu chevron in from sidebar's right edge for visual balance (expanded only) */
        html:not(.layout-menu-collapsed) .menu-vertical .menu-item .menu-toggle::after,
        body:not(.layout-menu-collapsed) .menu-vertical .menu-item .menu-toggle::after {
            inset-inline-end: 1.5rem !important;
        }
    </style>

    <script data-turbo-eval="false">
    // Cleanup any leftover sidebar-width settings from removed drag-resize feature
    try {
        localStorage.removeItem('bitac_sidebar_width');
        localStorage.removeItem('bitac_menu_width_rem');
    } catch(e) {}
    </script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu" data-bs-theme="dark" data-turbo-permanent>
                <div class="app-brand demo">
                    <a href="<?= BASE_URL ?>/dashboard" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <img src="<?= BASE_URL ?>/uploads/<?php echo $getSettingsDetailsQRW['header_logo']; ?>" height="40" alt="Logo" />
                        </span>
                        <span class="app-brand-text demo ms-3" style="font-size: 1.1rem;color: #f7f7f7;">
                            <?php echo $getEmployeeDetailsQRW['organization_name'] ?? 'BITAC'; ?>
                        </span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                        <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
                        <i class="icon-base ti tabler-x d-block d-xl-none"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <!-- Section label 
                <div class="menu-section-label">মূল মেনু</div>
                -->

                <!-- Dynamic Menu will be loaded here -->
                <?php include('sidebar_menu_vuexy.php'); ?>
            </aside>
            <!-- / Menu -->

            <!-- Mobile Menu Toggle -->
            <div class="menu-mobile-toggler d-xl-none rounded-1">
                <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
                    <i class="ti tabler-menu icon-base"></i>
                    <i class="ti tabler-chevron-right icon-base"></i>
                </a>
            </div>

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar navbar navbar-expand-xl align-items-center container-fluid bitac-navbar" id="layout-navbar">
                    <!-- Left: mobile toggle + page title -->
                    <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                        <div class="layout-menu-toggle navbar-nav align-items-xl-center d-xl-none">
                            <a class="nav-item nav-link px-0" href="javascript:void(0)">
                                <i class="icon-base ti tabler-menu-2 icon-md"></i>
                            </a>
                        </div>

                        <div class="navbar-title min-w-0">
                            <?php if (!empty($pageTitle)): ?>
                                <h5 class="navbar-title-text mb-0"><?= htmlspecialchars($pageTitle) ?></h5>
                            <?php endif; ?>
                            <?php if (!empty($pageSubtitle)): ?>
                                <div class="navbar-title-sub"><?= htmlspecialchars($pageSubtitle) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bitac-nav-right" style="display:flex !important; align-items:center; gap:6px; flex:0 0 auto !important; margin-left:auto !important;">

                            <!-- Search -->
                            <a href="javascript:void(0);" aria-label="Search" id="globalSearchTrigger" data-bs-toggle="modal" data-bs-target="#globalSearchModal" class="bitac-nav-icon" style="display:inline-flex !important; align-items:center; justify-content:center; width:42px; height:42px; border-radius:10px; color:#4b5563; text-decoration:none;">
                                <i class="ti tabler-search" style="font-size:1.45rem; color:#4b5563; line-height:1;"></i>
                            </a>

                            <!-- Notifications (dropdown) -->
                            <div class="dropdown dropdown-notifications">
                                <a href="javascript:void(0);" class="bitac-nav-icon notif-bell-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                   style="display:inline-flex !important; align-items:center; justify-content:center; width:42px; height:42px; border-radius:10px; color:#4b5563; text-decoration:none; position:relative;">
                                    <span style="position:relative; display:inline-flex; align-items:center; justify-content:center; line-height:1;">
                                        <i class="ti tabler-bell" style="font-size:1.45rem; color:#4b5563; line-height:1;"></i>
                                        <span style="position:absolute; top:-3px; right:-3px; width:11px; height:11px; background:#ea5455; border:2px solid #fff; border-radius:50%; box-shadow:0 0 0 1px rgba(234,84,85,0.25);"></span>
                                    </span>
                                    <span class="notif-badge-count" style="display:none; position:absolute; top:2px; right:2px; min-width:16px; height:16px; background:#ea5455; color:#fff; border-radius:10px; font-size:0.6rem; font-weight:700; line-height:16px; text-align:center; padding:0 4px; border:2px solid #fff;">0</span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end p-0 notif-dropdown-menu" style="width:360px; border:none; border-radius:16px; box-shadow:0 8px 40px rgba(58,61,83,0.18); overflow:hidden;">

                                    <!-- Header -->
                                    <li class="notif-header" style="background:linear-gradient(135deg,#3A3D53,#5a5f78); padding:16px 20px;">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="background:rgba(255,255,255,0.15); padding:7px 9px; border-radius:9px;">
                                                    <i class="ti tabler-bell text-white" style="font-size:1.1rem;"></i>
                                                </div>
                                                <div>
                                                    <h6 class="text-white mb-0 fw-semibold" style="font-size:0.95rem;">নোটিফিকেশন</h6>
                                                    <small style="font-size:0.75rem; color:rgba(255,255,255,0.85);">সর্বশেষ আপডেট</small>
                                                </div>
                                            </div>
                                            <span class="notif-header-count" style="background:rgba(255,255,255,0.2); color:#fff; border-radius:20px; padding:3px 10px; font-size:0.72rem; font-weight:600;">০ নতুন</span>
                                        </div>
                                    </li>

                                    <!-- List -->
                                    <li class="dropdown-notifications-list" style="max-height:340px; overflow-y:auto; background:#fff;"></li>

                                    <!-- Footer -->
                                    <li style="background:#f8f9fb; border-top:1px solid #f0f2f5; padding:12px 16px;">
                                        <a href="<?= BASE_URL ?>/views/notifications/all.php" class="btn btn-sm w-100 fw-semibold" style="background:linear-gradient(135deg,#3A3D53,#5a5f78); color:#fff; border:none; border-radius:10px; font-size:0.82rem; padding:9px;">
                                            <i class="ti tabler-list me-1"></i> সব নোটিফিকেশন দেখুন
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <!--/ Notifications -->

                            <!-- Divider -->
                            <span class="bitac-nav-divider-line" style="display:inline-block; width:1px; height:32px; background:#d1d5db; margin:0 10px;"></span>

                            <!-- User -->
                            <?php
                                // Use pre-computed nav vars (set before sidebar include overwrites $getUserInfoQRW)
                                $userDisplayName = $navUserDisplayName;
                                $userInitial     = $navUserInitial;
                                $userDesignation = $navUserDesignation;
                                $userOrg         = $navUserOrg;
                                $userPhoto       = $navUserPhoto;
                                $hasPhoto        = $navHasPhoto;
                                $profileUrl      = $navProfileUrl;
                            ?>
                            <div class="dropdown dropdown-user">
                                <a class="user-avatar-btn" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false"
                                   style="display:inline-flex !important; align-items:center; gap:8px; padding:2px; border-radius:10px; text-decoration:none;">
                                    <span style="display:inline-flex; width:40px; height:40px; border-radius:50%; overflow:hidden; border:2px solid #fff; box-shadow:0 0 0 1px #e5e7eb; align-items:center; justify-content:center;">
                                        <?php if ($hasPhoto): ?>
                                            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($userPhoto) ?>" alt="<?= htmlspecialchars($userDisplayName) ?>" width="40" height="40" style="object-fit:cover; width:100%; height:100%;" />
                                        <?php else: ?>
                                            <span style="display:inline-flex; width:100%; height:100%; align-items:center; justify-content:center; background:linear-gradient(135deg,#3A3D53,#6b7280); color:#fff; font-weight:700; font-size:0.95rem;"><?= $userInitial ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <i class="ti tabler-chevron-down" style="font-size:1rem; color:#9ca3af; line-height:1;"></i>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end p-0 user-dropdown-menu" style="width:270px; border:none; border-radius:16px; box-shadow:0 8px 40px rgba(58,61,83,0.2); overflow:hidden;">

                                    <!-- Header -->
                                    <li>
                                        <a class="d-block text-decoration-none" href="<?= $profileUrl ?>" style="background:linear-gradient(135deg,#3A3D53 0%,#5a5f78 100%); padding:20px 18px 18px;">
                                            <div class="d-flex align-items-center gap-3">
                                                <!-- Avatar -->
                                                <div style="flex-shrink:0; position:relative;">
                                                    <?php if ($hasPhoto): ?>
                                                        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($userPhoto) ?>" alt="" class="rounded-circle" width="52" height="52" style="object-fit:cover; border:3px solid rgba(255,255,255,0.3);" />
                                                    <?php else: ?>
                                                        <div style="width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,0.18); border:3px solid rgba(255,255,255,0.3); display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:700; color:#fff;"><?= $userInitial ?></div>
                                                    <?php endif; ?>
                                                    <!-- Online dot -->
                                                    <span style="position:absolute; bottom:2px; right:2px; width:11px; height:11px; background:#28c76f; border-radius:50%; border:2px solid #4a5070;"></span>
                                                </div>
                                                <!-- Info -->
                                                <div style="flex:1; min-width:0;">
                                                    <div class="fw-semibold text-white text-truncate" style="font-size:0.92rem; line-height:1.3;"><?= htmlspecialchars($userDisplayName) ?></div>
                                                    <div class="text-truncate mt-1" style="font-size:0.75rem; color:rgba(255,255,255,0.65);"><?= htmlspecialchars($userDesignation) ?></div>
                                                    <div class="mt-1">
                                                        <span style="background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.85); border-radius:20px; padding:2px 9px; font-size:0.68rem; font-weight:600;"><?= htmlspecialchars($userOrg) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </li>

                                    <?php if (!empty($navActiveGroupName)): ?>
                                    <!-- Active role label (always shown when user has a group) -->
                                    <li style="background:#fff; padding:10px 16px 6px;">
                                        <div class="role-current-label">
                                            <span class="role-current-icon"><i class="ti tabler-shield-check"></i></span>
                                            <div style="flex:1; min-width:0;">
                                                <div class="role-current-caption">Current Role</div>
                                                <div class="role-current-name"><?= htmlspecialchars($navActiveGroupName) ?></div>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (count($navUserGroups) > 1): ?>
                                    <!-- Role switcher — only when user has multiple groups assigned -->
                                    <li style="background:#fff; padding:6px 0 8px;">
                                        <div class="role-switch-header">
                                            <i class="ti tabler-switch-horizontal"></i>
                                            <span>Switch Role</span>
                                        </div>
                                        <?php foreach ($navUserGroups as $g):
                                            $isActive = ($g['id'] === $navActiveGroupId);
                                        ?>
                                        <a class="user-dd-item role-switch-item<?= $isActive ? ' is-active' : '' ?>"
                                           href="javascript:void(0);"
                                           data-group-id="<?= $g['id'] ?>"
                                           data-group-name="<?= htmlspecialchars($g['group_name'], ENT_QUOTES) ?>"
                                           onclick="<?= $isActive ? 'return false;' : 'switchUserRole(' . $g['id'] . ', this);' ?>">
                                            <span class="user-dd-icon-wrap" style="background:<?= $isActive ? '#e8f5e9' : '#f3f4f8' ?>;">
                                                <i class="ti <?= $isActive ? 'tabler-circle-check' : 'tabler-circle' ?>" style="color:<?= $isActive ? '#28c76f' : '#9ca3af' ?>;"></i>
                                            </span>
                                            <span style="<?= $isActive ? 'color:#28c76f; font-weight:600;' : '' ?>"><?= htmlspecialchars($g['group_name']) ?></span>
                                            <?php if ($isActive): ?>
                                            <span class="role-switch-active-pill">সক্রিয়</span>
                                            <?php endif; ?>
                                        </a>
                                        <?php endforeach; ?>
                                        <div style="margin:6px 16px; border-top:1px solid #f3f4f8;"></div>
                                    </li>
                                    <?php endif; ?>

                                    <!-- Actions -->
                                    <li style="background:#fff; padding:8px 0;">
                                        <a class="user-dd-item" href="<?= $profileUrl ?>">
                                            <span class="user-dd-icon-wrap" style="background:#eef2ff;">
                                                <i class="ti tabler-user" style="color:#696cff;"></i>
                                            </span>
                                            <span>আমার প্রোফাইল</span>
                                        </a>

                                        <div style="margin:4px 16px; border-top:1px solid #f3f4f8;"></div>

                                        <a class="user-dd-item" href="javascript:void(0);" onclick="logout()" data-turbo="false">
                                            <span class="user-dd-icon-wrap" style="background:#fff0f0;">
                                                <i class="ti tabler-logout" style="color:#ea5455;"></i>
                                            </span>
                                            <span style="color:#ea5455;">লগ আউট</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <!--/ User -->

                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- ═══════════════════════════════════════════════════════════
                     Global Search Modal
                ═══════════════════════════════════════════════════════════ -->
<?php
                // Leave types for global-search filter dropdown
                $_gsLeaveTypesQ = mysqli_query($con, "SELECT leaveID, leaveTitle FROM leave_types ORDER BY leaveTitle ASC");
                $_gsLeaveTypes = [];
                while ($_gsLeaveTypesQ && $_lt = mysqli_fetch_assoc($_gsLeaveTypesQ)) $_gsLeaveTypes[] = $_lt;
                ?>
                <div class="modal fade" id="globalSearchModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable" style="margin-top:60px;">
                        <div class="modal-content" style="border:none; border-radius:14px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.18);">
                            <div class="modal-header gs-modal-header">
                                <div class="gs-search-row">
                                    <div class="gs-input-wrap">
                                        <i class="ti tabler-search gs-input-icon"></i>
                                        <input type="text" id="globalSearchInput" placeholder="আবেদন নং বা Employee ID..."
                                               autocomplete="off">
                                    </div>
                                    <div class="gs-filter-field">
                                        <i class="ti tabler-calendar-event"></i>
                                        <select id="gsLeaveType">
                                            <option value="">সব ছুটির ধরন</option>
                                            <?php foreach ($_gsLeaveTypes as $_lt): ?>
                                                <option value="<?= (int)$_lt['leaveID'] ?>"><?= htmlspecialchars($_lt['leaveTitle']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="button" id="gsClearFilters" class="gs-filter-clear" title="ফিল্টার পরিষ্কার করুন" style="display:none;">
                                        <i class="ti tabler-x"></i>
                                    </button>
                                </div>
                                <button type="button" class="gs-close-btn" data-bs-dismiss="modal" aria-label="Close">
                                    <i class="ti tabler-x"></i>
                                </button>
                            </div>
                            <div class="modal-body" id="globalSearchBody" style="padding:18px 20px; min-height:200px;">
                                <div id="gsHint" style="text-align:center; color:#9ca3af; padding:40px 10px; font-size:0.92rem;">
                                    <i class="ti tabler-search" style="font-size:2.4rem; opacity:0.4;"></i>
                                    <div class="mt-2">টাইপ শুরু করুন</div>
                                    <div class="text-muted small mt-1">আবেদন নং, Employee ID বা ছুটির ধরন দিয়ে খুঁজুন</div>
                                </div>
                                <div id="gsResults"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                /* Modal header — search input + filter inline */
                .gs-modal-header {
                    background: linear-gradient(155deg, #0e1e34 0%, #1e3a5f 100%);
                    color: #fff;
                    border: none;
                    padding: 14px 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    position: relative;
                    overflow: hidden;
                }
                .gs-modal-header::after {
                    content: "";
                    position: absolute;
                    left: 0; right: 0; bottom: 0;
                    height: 2px;
                    background: linear-gradient(90deg, #b18b3e 0%, transparent 60%);
                }
                .gs-search-row {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex: 1;
                    min-width: 0;
                }
                .gs-input-wrap {
                    flex: 1 1 auto;
                    position: relative;
                    min-width: 0;
                }
                .gs-input-icon {
                    position: absolute;
                    left: 14px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #1e3a5f;
                    font-size: 1rem;
                    pointer-events: none;
                }
                #globalSearchInput {
                    width: 100%;
                    padding: 9px 14px 9px 38px;
                    border: none;
                    border-radius: 8px;
                    font-size: 0.9rem;
                    background: rgba(255, 255, 255, 0.96);
                    color: #1f2937;
                    outline: none;
                }
                #globalSearchInput::placeholder { color: #8a90a6; }

                /* Filter dropdown */
                .gs-filter-field {
                    position: relative;
                    display: inline-flex;
                    align-items: center;
                    flex: 0 0 auto;
                }
                .gs-filter-field > .ti {
                    position: absolute;
                    left: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #1e3a5f;
                    font-size: 0.95rem;
                    pointer-events: none;
                }
                .gs-filter-field select {
                    padding: 9px 30px 9px 34px;
                    border: none;
                    border-radius: 8px;
                    font-size: 0.86rem;
                    background-color: rgba(255, 255, 255, 0.96);
                    color: #1f2937;
                    width: 200px;
                    cursor: pointer;
                    appearance: none;
                    -webkit-appearance: none;
                    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235648c4' stroke-width='2.5'><polyline points='6 9 12 15 18 9'></polyline></svg>");
                    background-repeat: no-repeat;
                    background-position: right 10px center;
                }
                .gs-filter-field select:focus {
                    outline: none;
                    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.4);
                }

                /* Clear filter button */
                .gs-filter-clear {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 34px;
                    height: 34px;
                    border-radius: 8px;
                    border: none;
                    background: rgba(255, 255, 255, 0.18);
                    color: #fff;
                    cursor: pointer;
                    transition: background 0.15s ease;
                    flex-shrink: 0;
                }
                .gs-filter-clear:hover { background: rgba(255, 255, 255, 0.3); }

                /* Close button */
                .gs-close-btn {
                    background: transparent;
                    border: none;
                    color: #fff;
                    width: 34px;
                    height: 34px;
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    opacity: 0.85;
                    transition: opacity 0.15s ease, background 0.15s ease;
                    flex-shrink: 0;
                }
                .gs-close-btn:hover { background: rgba(255, 255, 255, 0.15); opacity: 1; }
                .gs-close-btn .ti { font-size: 1.1rem; }

                /* Mobile — stack input + filter vertically */
                @media (max-width: 575px) {
                    .gs-search-row { flex-wrap: wrap; }
                    .gs-input-wrap { flex: 1 1 100%; }
                    .gs-filter-field { flex: 1 1 auto; }
                    .gs-filter-field select { width: 100%; }
                }

                .gs-section-title { font-weight:600; color:#4b5563; margin:0 0 8px; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; }
                .gs-card {
                    background:#fff; border:1px solid #eef0f5; border-radius:10px;
                    padding:14px 16px; margin-bottom:10px; transition:all .15s ease;
                }
                .gs-card:hover { border-color:#ddd5f6; box-shadow:0 4px 12px rgba(108, 92, 231, 0.08); }
                .gs-row-top { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
                .gs-app-no { background:#efeaf5; color:#7c6ba4; padding:2px 9px; border-radius:5px; font-size:0.72rem; font-weight:600; }
                .gs-status { padding:3px 10px; border-radius:99px; font-size:0.72rem; font-weight:600; }
                .gs-applicant { font-weight:600; color:#1f2937; font-size:0.95rem; }
                .gs-meta { font-size:0.78rem; color:#6b7280; margin-top:2px; }
                .gs-detail-row { font-size:0.84rem; color:#4b5563; padding:4px 0; }
                .gs-segments { display:flex; flex-wrap:wrap; gap:6px; margin:8px 0; }
                .gs-seg-chip { background:#e8eef9; color:#5b7396; padding:3px 9px; border-radius:5px; font-size:0.74rem; font-weight:500; }
                .gs-chain { background:#f9fafb; border-radius:8px; padding:10px 12px; margin-top:10px; }
                .gs-chain-row { display:flex; align-items:center; gap:10px; padding:5px 0; font-size:0.82rem; }
                .gs-chain-step { width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.74rem; font-weight:700; flex-shrink:0; }
                .gs-chain-step.s-done   { background:#5fa885; color:#fff; }
                .gs-chain-step.s-pending{ background:#e5e7eb; color:#6b7280; }
                .gs-chain-step.s-current{ background:#d4a056; color:#fff; box-shadow:0 0 0 3px rgba(212,160,86,0.18); }
                .gs-chain-name { flex:1; color:#1f2937; font-weight:500; }
                .gs-chain-title { color:#6b7280; font-size:0.74rem; }
                .gs-chain-date  { color:#5fa885; font-size:0.72rem; }
                .gs-pending-list { background:#fef9e6; border-radius:8px; padding:10px 12px; margin-top:8px; }
                .gs-pending-row { display:flex; align-items:center; gap:10px; padding:6px 0; border-bottom:1px dashed #f0e3b3; font-size:0.84rem; }
                .gs-pending-row:last-child { border-bottom:none; }
                .gs-pending-row a { color:#7d9bc5; text-decoration:none; font-weight:500; flex-shrink:0; }
                .gs-empty { text-align:center; color:#9ca3af; font-size:0.88rem; padding:20px 10px; }
                .gs-action-btns { display:flex; gap:8px; margin-top:10px; }
                .gs-btn {
                    text-decoration:none; padding:6px 14px; border-radius:8px; font-size:0.82rem;
                    font-weight:500; display:inline-flex; align-items:center; gap:5px;
                }
                .gs-btn-primary { background:#7d9bc5; color:#fff; }
                .gs-btn-primary:hover { background:#5b7396; color:#fff; }
                .gs-btn-secondary { background:#f3f4f6; color:#4b5563; }
                .gs-btn-secondary:hover { background:#e5e7eb; }
                </style>

                <script>
                (function(){
                    function gsInit() {
                        if (typeof jQuery === 'undefined') return setTimeout(gsInit, 50);
                        var $ = jQuery;
                        var $modal = $('#globalSearchModal');
                        var $input = $('#globalSearchInput');
                        var $leaveType = $('#gsLeaveType');
                        var $clearBtn = $('#gsClearFilters');
                        var $hint  = $('#gsHint');
                        var $res   = $('#gsResults');
                        if (!$modal.length) return;

                        // Auto-focus when modal opens
                        $modal.off('shown.bs.modal.gs').on('shown.bs.modal.gs', function(){
                            $input.val('').trigger('input').focus();
                            $leaveType.val('');
                            $clearBtn.hide();
                            $res.empty(); $hint.show();
                        });

                        function updateClearBtn() {
                            if ($leaveType.val()) $clearBtn.show();
                            else $clearBtn.hide();
                        }
                        function triggerSearch() {
                            var q = $input.val().trim();
                            var lt = $leaveType.val();
                            updateClearBtn();
                            if (q === '' && !lt) { $res.empty(); $hint.show(); return; }
                            $hint.hide();
                            runSearch(q, lt);
                        }

                        var debounceTimer = null;
                        $input.off('input.gs').on('input.gs', function(){
                            clearTimeout(debounceTimer);
                            debounceTimer = setTimeout(triggerSearch, 300);
                        });
                        $leaveType.off('change.gs').on('change.gs', function(){
                            triggerSearch();
                        });
                        $clearBtn.off('click.gs').on('click.gs', function(){
                            $leaveType.val('');
                            triggerSearch();
                        });

                        function runSearch(q, leaveType) {
                            $res.html('<div class="gs-empty"><i class="ti tabler-loader-2 ti-spin"></i> খুঁজছি...</div>');
                            var data = { q: q };
                            if (leaveType) data.leaveType = leaveType;
                            $.ajax({
                                url: '<?= BASE_URL ?>/api/search/global.php',
                                method: 'GET', data: data, dataType: 'json',
                                success: function(r){
                                    if (!r || r.ok === false) {
                                        $res.html('<div class="gs-empty">' + (r && r.error ? r.error : 'কিছু পাওয়া যায়নি') + '</div>');
                                        return;
                                    }
                                    renderResults(r);
                                },
                                error: function(){
                                    $res.html('<div class="gs-empty" style="color:#c97777;">Server error — আবার চেষ্টা করুন</div>');
                                }
                            });
                        }

                        function renderResults(r) {
                            var html = '';
                            var hasApps = r.applications && r.applications.length > 0;
                            var hasEmps = r.employees && r.employees.length > 0;
                            if (!hasApps && !hasEmps) {
                                $res.html('<div class="gs-empty">কোনো ফলাফল নেই। আবেদন নং বা Employee ID যাচাই করুন।</div>');
                                return;
                            }
                            if (hasApps) {
                                html += '<div class="gs-section-title"><i class="ti tabler-file-text me-1"></i>ছুটির আবেদন (' + bn(r.applications.length) + ')</div>';
                                r.applications.forEach(function(a){ html += renderApp(a); });
                            }
                            if (hasEmps) {
                                html += '<div class="gs-section-title mt-3"><i class="ti tabler-user me-1"></i>কর্মচারী (' + bn(r.employees.length) + ')</div>';
                                r.employees.forEach(function(e){ html += renderEmp(e); });
                            }
                            $res.html(html);
                        }

                        function bn(n) { return String(n).replace(/[0-9]/g, function(d){ return '০১২৩৪৫৬৭৮৯'[d]; }); }
                        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

                        function renderApp(a) {
                            var segs = (a.segments || []).map(function(s){
                                return '<span class="gs-seg-chip">' + bn(s.days) + ' দিন ' + esc(s.title) + '</span>';
                            }).join('');
                            var chain = (a.chain || []).map(function(c, idx, arr){
                                var prevDone = idx === 0 || arr[idx-1].isApproved === 1;
                                var stepCls = c.isApproved === 1 ? 's-done'
                                            : (prevDone && (c.isSupervisor === 1 || c.isSentbyAdmin === 1) ? 's-current' : 's-pending');
                                var icon = c.isApproved === 1 ? '<i class="ti tabler-check"></i>'
                                         : (stepCls === 's-current' ? bn(c.serial) : bn(c.serial));
                                var roleLabel = c.isSupervisor ? 'সুপারিশ' : 'অনুমোদন';
                                return '<div class="gs-chain-row">'
                                    +    '<span class="gs-chain-step ' + stepCls + '">' + icon + '</span>'
                                    +    '<span class="gs-chain-name">' + esc(c.name)
                                    +      ' <span class="gs-chain-title">— ' + esc(c.title || roleLabel) + '</span>'
                                    +    '</span>'
                                    +    (c.approvedDate ? '<span class="gs-chain-date">✓ ' + c.approvedDate + '</span>' : '')
                                    +  '</div>';
                            }).join('');
                            return '<div class="gs-card">'
                                +    '<div class="gs-row-top">'
                                +      '<div>'
                                +        '<div class="gs-app-no"><i class="ti tabler-hash"></i> ' + esc(a.app_no) + '</div>'
                                +        '<div class="gs-applicant mt-1">' + esc(a.applicant) + '</div>'
                                +        '<div class="gs-meta">' + esc(a.designation) + (a.section ? ' · ' + esc(a.section) : '') + ' · <i class="ti tabler-building"></i> ' + esc(a.organization) + '</div>'
                                +      '</div>'
                                +      '<span class="gs-status" style="background:' + a.statusBg + '; color:' + a.statusColor + ';">' + esc(a.statusLabel) + '</span>'
                                +    '</div>'
                                +    (a.subject ? '<div class="gs-detail-row"><strong>বিষয়:</strong> ' + esc(a.subject) + '</div>' : '')
                                +    '<div class="gs-detail-row">'
                                +      '<i class="ti tabler-calendar-event"></i> ' + esc(a.dateFrom) + ' → ' + esc(a.dateTo)
                                +      ' · মোট <strong>' + bn(a.totalDays) + ' দিন</strong>'
                                +      ' · জমা: ' + esc(a.submitDate)
                                +    '</div>'
                                +    (segs ? '<div class="gs-segments">' + segs + '</div>' : '')
                                +    (chain ? '<div class="gs-chain"><div class="gs-section-title" style="margin:0 0 6px;">অনুমোদনের ধাপসমূহ</div>' + chain + '</div>' : '')
                                +    '<div class="gs-action-btns">'
                                +      '<a class="gs-btn gs-btn-primary" target="_blank" href="<?= BASE_URL ?>/views/leave/application-details.php?menuslug=dashboard&leaveApplicationID=' + a.dataID + '"><i class="ti tabler-file-text"></i> আবেদনপত্র</a>'
                                +    '</div>'
                                +  '</div>';
                        }

                        function renderEmp(e) {
                            var pending = '';
                            if ((e.pending || []).length === 0) {
                                pending = '<div class="gs-empty" style="padding:14px 8px;">এখন কোনো pending আবেদন নেই</div>';
                            } else {
                                pending = '<div class="gs-pending-list">'
                                    + e.pending.map(function(p){
                                        var segText = (p.segParts || []).join(', ');
                                        return '<div class="gs-pending-row">'
                                            +    '<span class="gs-app-no"><i class="ti tabler-hash"></i> ' + esc(p.app_no) + '</span>'
                                            +    '<span style="flex:1;">' + esc(p.dateFrom) + ' → ' + esc(p.dateTo) + ' · ' + bn(p.totalDays) + ' দিন'
                                            +      (segText ? ' <small style="color:#6b7280;">(' + esc(segText) + ')</small>' : '')
                                            +      (p.currentSig ? '<br><small style="color:#7c6ba4;"><i class="ti tabler-user-circle"></i> বর্তমানে: ' + esc(p.currentSig) + '</small>' : '')
                                            +    '</span>'
                                            +    '<span class="gs-status" style="background:' + p.statusBg + '; color:' + p.statusColor + ';">' + esc(p.statusLabel) + '</span>'
                                            +    '<a target="_blank" href="<?= BASE_URL ?>/views/leave/application-details.php?menuslug=dashboard&leaveApplicationID=' + p.dataID + '"><i class="ti tabler-eye"></i></a>'
                                            +  '</div>';
                                    }).join('')
                                    + '</div>';
                            }
                            return '<div class="gs-card">'
                                +    '<div class="gs-row-top">'
                                +      '<div>'
                                +        '<div class="gs-applicant">' + esc(e.name) + '</div>'
                                +        '<div class="gs-meta">'
                                +          '<i class="ti tabler-id-badge-2"></i> ' + esc(e.employee_id)
                                +          (e.designation ? ' · ' + esc(e.designation) : '')
                                +          (e.section ? ' · ' + esc(e.section) : '')
                                +          ' · <i class="ti tabler-building"></i> ' + esc(e.organization)
                                +        '</div>'
                                +      '</div>'
                                +      '<span class="gs-status" style="background:#fef9e6; color:#9c8055;">'
                                +        bn(e.pendingCount) + ' টি pending'
                                +      '</span>'
                                +    '</div>'
                                +    pending
                                +  '</div>';
                        }
                    }
                    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', gsInit);
                    else gsInit();
                    document.addEventListener('turbo:load', gsInit);
                    document.addEventListener('turbo:frame-load', gsInit);
                })();
                </script>

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Turbo Frame: menu navigation swaps ONLY this area, never touching sidebar/navbar -->
                    <turbo-frame id="main-content" data-turbo-action="advance">
                    <!-- Content -->
                    <div class="flex-grow-1 container-p-y container-fluid">
