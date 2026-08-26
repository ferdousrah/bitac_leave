<?php
// Note: session and connection are already started in header.php
// So we don't need to include them again

// Third-level menus hang off submodules.parent_id. The column is optional on
// purpose: an install that has not run the migration yet simply renders the menu
// flat, rather than fataling on every page because the sidebar is included
// everywhere. Nothing here creates the column — a page must never depend on
// another page's lazy migration.
$__subHasParent = false;
$__pcheck = @mysqli_query($con, "SHOW COLUMNS FROM submodules LIKE 'parent_id'");
if ($__pcheck && mysqli_num_rows($__pcheck) > 0) $__subHasParent = true;
$__parentSel = $__subHasParent ? "sm.parent_id AS submodule_parent_id," : "0 AS submodule_parent_id,";

// Securely get user information using prepared statements
$stmt = $con->prepare("SELECT dataID, employee_id, user_group_id FROM user_list WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$getUserInfoQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if user has a group assigned
if (!empty($getUserInfoQRW['user_group_id'])) {
    // Fetch accessible modules based on user's group permissions
    $query = "
        SELECT DISTINCT
            m.dataID AS module_id,
            m.module_name,
            m.page_link,
            m.icon,
            m.slug,
            m.display_order AS module_display_order,
            sm.dataID AS submodule_id,
            sm.submodule_name,
            sm.page_link AS submodule_link,
            sm.slug AS submodule_slug,
            " . $__parentSel . "
            sm.display_order AS submodule_display_order
        FROM group_access_permission gap
        INNER JOIN modules m ON gap.module_id = m.dataID
        LEFT JOIN submodules sm ON (
            (gap.submodule_id IS NULL AND sm.module_id = m.dataID AND sm.deleted = 0) OR
            (gap.submodule_id = sm.dataID)
        )
        WHERE gap.user_group_id = ? AND m.deleted = 0
        ORDER BY m.display_order ASC, sm.display_order ASC
    ";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $getUserInfoQRW['user_group_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // Fallback to individual user permissions if no group assigned
    $query = "
        SELECT DISTINCT
            m.dataID AS module_id,
            m.module_name,
            m.page_link,
            m.icon,
            m.slug,
            m.display_order AS module_display_order,
            sm.dataID AS submodule_id,
            sm.submodule_name,
            sm.page_link AS submodule_link,
            sm.slug AS submodule_slug,
            " . $__parentSel . "
            sm.display_order AS submodule_display_order
        FROM access_permission ap
        INNER JOIN modules m ON ap.module_id = m.dataID
        LEFT JOIN submodules sm ON (
            (ap.submodule_id IS NULL AND sm.module_id = m.dataID AND sm.deleted = 0) OR
            (ap.submodule_id = sm.dataID)
        )
        WHERE ap.user_id = ? AND m.deleted = 0
        ORDER BY m.display_order ASC, sm.display_order ASC
    ";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $getUserInfoQRW['dataID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}

// Organize the results by modules and submodules
$menuData = [];
while ($row = $result->fetch_assoc()) {
    $module_id = $row['module_id'];
    if (!isset($menuData[$module_id])) {
        $menuData[$module_id] = [
            'module_name' => $row['module_name'],
            'page_link' => $row['page_link'],
            'icon' => $row['icon'],
            'slug' => $row['slug'],
            'submodules' => []
        ];
    }
    if ($row['submodule_id']) {
        $menuData[$module_id]['submodules'][] = [
            'id'              => (int)$row['submodule_id'],
            'parent_id'       => (int)($row['submodule_parent_id'] ?? 0),
            'submodule_name'  => $row['submodule_name'],
            'page_link'       => $row['submodule_link'],
            'slug'            => $row['submodule_slug'],
            'children'        => []
        ];
    }
}

// ── Nest third-level items under their parent submodule ──────────────────
// A parent is a grouping row: it carries no page of its own, so it must appear
// whenever any of its children are permitted, even when the group has no
// permission row for the parent itself. Those parents are fetched separately
// and slotted in at the position their display_order asks for.
if ($__subHasParent) {
    foreach ($menuData as $__mid => $__mod) {
        $__flat = $__mod['submodules'];
        if (!$__flat) continue;

        $__byId = [];
        foreach ($__flat as $__s) $__byId[$__s['id']] = $__s;

        // Parents referenced by a permitted child but absent from the result set.
        $__missing = [];
        foreach ($__flat as $__s) {
            if ($__s['parent_id'] > 0 && !isset($__byId[$__s['parent_id']])) {
                $__missing[$__s['parent_id']] = true;
            }
        }
        if ($__missing) {
            $__ids = implode(',', array_map('intval', array_keys($__missing)));
            $__pq = mysqli_query($con,
                "SELECT dataID, parent_id, submodule_name, page_link, slug, display_order
                 FROM submodules WHERE dataID IN ($__ids) AND deleted = 0");
            if ($__pq) {
                while ($__pr = mysqli_fetch_assoc($__pq)) {
                    $__byId[(int)$__pr['dataID']] = [
                        'id'             => (int)$__pr['dataID'],
                        'parent_id'      => (int)$__pr['parent_id'],
                        'submodule_name' => $__pr['submodule_name'],
                        'page_link'      => $__pr['page_link'],
                        'slug'           => $__pr['slug'],
                        'children'       => [],
                        '__order'        => (int)$__pr['display_order'],
                    ];
                }
            }
        }

        // Attach children to parents, keeping the query's order within each level.
        $__roots = [];
        foreach ($__byId as $__id => $__s) {
            if ($__s['parent_id'] > 0 && isset($__byId[$__s['parent_id']])) continue;
            $__roots[$__id] = true;
        }
        foreach ($__byId as $__id => $__s) {
            if ($__s['parent_id'] > 0 && isset($__byId[$__s['parent_id']])) {
                $__byId[$__s['parent_id']]['children'][] = $__byId[$__id];
            }
        }

        // Rebuild the level in the original order, then append any parent that
        // was pulled in only because a child needed it.
        $__out = [];
        $__seen = [];
        foreach ($__flat as $__s) {
            if (!isset($__roots[$__s['id']])) continue;
            if (isset($__seen[$__s['id']])) continue;
            $__seen[$__s['id']] = true;
            $__out[] = $__byId[$__s['id']];
        }
        foreach ($__byId as $__id => $__s) {
            if (!isset($__roots[$__id]) || isset($__seen[$__id])) continue;
            $__seen[$__id] = true;
            $__out[] = $__s;
        }
        $menuData[$__mid]['submodules'] = $__out;
    }
}

$menuSlug = $_GET['menuslug'] ?? $_POST['menuslug'] ?? '';

// Helper function to check if a module is active
function isModuleActive($module, $submodules, $currentSlug) {
    // Check if any submodule — or any third-level child — is active
    foreach ($submodules as $sub) {
        if ($sub['slug'] === $currentSlug) {
            return true;
        }
        foreach ($sub['children'] ?? [] as $child) {
            if ($child['slug'] === $currentSlug) {
                return true;
            }
        }
    }
    // Check if module itself is active (for modules without submodules)
    if ($module['slug'] === $currentSlug) {
        return true;
    }
    return false;
}

// Helper function to build proper URLs for both old and new style page links
function buildMenuUrl($baseURL, $pageLink) {
    // If page_link is empty or just '#', return as-is
    if (empty($pageLink) || $pageLink === '#') {
        return 'javascript:void(0);';
    }

    // Remove trailing slash from baseURL to prevent double slashes
    $baseURL = rtrim($baseURL, '/');

    // New style: starts with 'views/' - use as-is
    if (strpos($pageLink, 'views/') === 0) {
        return $baseURL . '/' . $pageLink;
    }

    // Old style: doesn't have .php extension - add it
    if (substr($pageLink, -4) !== '.php') {
        return $baseURL . '/' . $pageLink . '.php';
    }

    // Old style with .php extension already
    return $baseURL . '/' . $pageLink;
}
?>

<ul class="menu-inner py-1" style="padding-bottom:24px !important;" data-turbo-frame="main-content">
    <?php foreach ($menuData as $module_id => $module): ?>
        <?php
        $isActive = isModuleActive($module, $module['submodules'], $menuSlug);
        ?>
        <?php if (!empty($module['submodules'])): ?>
            <!-- Module with submodules -->
            <li class="menu-item <?= $isActive ? 'active open' : '' ?>">
                <a href="javascript:void(0);" class="menu-link menu-toggle" data-turbo="false">
                    <i class="menu-icon <?= $module['icon'] ?>"></i>
                    <div data-i18n="<?= htmlspecialchars($module['module_name']) ?>"><?= htmlspecialchars($module['module_name']) ?></div>
                    <?php if($module['slug'] == 'leave-management'): ?>
                        <div class="badge rounded-pill bg-danger ms-auto" id="totalTask" style="display: none;">0</div>
                    <?php elseif($module['slug'] == 'admin-panel'): ?>
                        <div class="badge rounded-pill bg-danger ms-auto" id="adminPanelTotal" style="display: none;">0</div>
                    <?php endif; ?>
                </a>
                <ul class="menu-sub">
                    <?php foreach ($module['submodules'] as $submodule): ?>
                        <?php
                        $children    = $submodule['children'] ?? [];
                        $isSubActive = ($submodule['slug'] === $menuSlug);
                        $childActive = false;
                        foreach ($children as $child) {
                            if ($child['slug'] === $menuSlug) { $childActive = true; break; }
                        }
                        ?>
                        <?php if ($children): ?>
                            <!-- Third level: opens as a flyout beside the sidebar on hover -->
                            <li class="menu-item menu-has-flyout <?= ($isSubActive || $childActive) ? 'active' : '' ?>">
                                <a href="javascript:void(0);" class="menu-link menu-flyout-toggle" data-turbo="false">
                                    <div data-i18n="<?= htmlspecialchars($submodule['submodule_name']) ?>">
                                        <?= htmlspecialchars($submodule['submodule_name']) ?>
                                    </div>
                                    <div class="badge rounded-pill bg-danger ms-auto menu-flyout-total" data-flyout-total style="display: none;">0</div>
                                    <i class="ti tabler-chevron-right menu-flyout-caret"></i>
                                </a>
                                <ul class="menu-flyout-list">
                                    <?php foreach ($children as $child): ?>
                                        <?php $isChildActive = ($child['slug'] === $menuSlug); ?>
                                        <li class="menu-item <?= $isChildActive ? 'active' : '' ?>">
                                            <a href="<?= buildMenuUrl($baseURL, $child['page_link']) ?>?menuslug=<?= $child['slug'] ?>" class="menu-link">
                                                <div data-i18n="<?= htmlspecialchars($child['submodule_name']) ?>">
                                                    <?= htmlspecialchars($child['submodule_name']) ?>
                                                </div>
                                                <div class="badge rounded-pill bg-danger ms-auto" id="<?= $child['slug'] ?>" style="display: none;">0</div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="menu-item <?= $isSubActive ? 'active' : '' ?>">
                                <a href="<?= buildMenuUrl($baseURL, $submodule['page_link']) ?>?menuslug=<?= $submodule['slug'] ?>" class="menu-link">
                                    <div data-i18n="<?= htmlspecialchars($submodule['submodule_name']) ?>">
                                        <?= htmlspecialchars($submodule['submodule_name']) ?>
                                    </div>
                                    <div class="badge rounded-pill bg-danger ms-auto" id="<?= $submodule['slug'] ?>" style="display: none;">0</div>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php
                    // ── Conditional hardcoded items injected into কনফিগারেশন submenu ──
                    // NOTE: role-approval, honour-board, role-audit-log, system-audit-log
                    // are now in `submodules` table — visibility via group_access_permission.
                    // Only থিম সেটিংস remains hardcoded (Super Admin only, runtime check).
                    if ($module['slug'] === 'configuration'):
                        if ((int)($getUserInfoQRW['user_group_id'] ?? 0) === 1):
                            $themeActive = ($menuSlug === 'theme-settings');
                        ?>
                            <li class="menu-item <?= $themeActive ? 'active' : '' ?>">
                                <a href="<?= BASE_URL ?>/views/settings/theme.php?menuslug=theme-settings" class="menu-link">
                                    <div>থিম সেটিংস</div>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; // configuration slug check ?>
                </ul>
            </li>
        <?php else: ?>
            <!-- Module without submodules -->
            <?php $isModuleActive = ($module['slug'] === $menuSlug); ?>
            <li class="menu-item <?= $isModuleActive ? 'active' : '' ?>">
                <a href="<?= buildMenuUrl($baseURL, $module['page_link']) ?>?menuslug=<?= $module['slug'] ?>" class="menu-link">
                    <i class="menu-icon <?= $module['icon'] ?>"></i>
                    <div data-i18n="<?= htmlspecialchars($module['module_name']) ?>"><?= htmlspecialchars($module['module_name']) ?></div>
                </a>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- All users: ছুটির বিধিমালা (info hub) -->
    <?php $rulesActive = ($menuSlug === 'leave-rules'); ?>
    <li class="menu-item <?= $rulesActive ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>/views/info/leave-rules.php?menuslug=leave-rules" class="menu-link">
            <i class="menu-icon ti tabler-book-2"></i>
            <div>ছুটির বিধিমালা</div>
        </a>
    </li>

    <?php // (Theme Settings, রোল অনুমোদন, রোল ইতিহাস, রোল কার্যক্রম লগ are
          // injected into the কনফিগারেশন submenu above.) ?>

    <!-- Mobile menu items (show on mobile only) -->
    <li class="menu-item d-xl-none">
        <a href="<?= buildMenuUrl($baseURL, 'views/profile/my-account.php') ?>?menuslug=dashboard" class="menu-link">
            <i class="menu-icon ti tabler-user"></i>
            <div>My Account</div>
        </a>
    </li>
    <li class="menu-item d-xl-none">
        <a href="javascript:void(0)" onclick="logout()" data-turbo="false" class="menu-link">
            <i class="menu-icon ti tabler-logout"></i>
            <div>Logout</div>
        </a>
    </li>
</ul>

    <!-- ── Third-level menu flyout ──────────────────────────────────────────
         The panel is position:fixed, so its coordinates have to be written on
         each open: the sidebar scrolls, and an absolutely-positioned panel
         would be clipped by the sidebar's own overflow. Delegated and
         idempotent so it survives Turbo navigation. -->
    <script type="text/javascript">
    (function menuFlyout() {
        if (window.__menuFlyoutBound) return;
        window.__menuFlyoutBound = true;

        var CLOSE_DELAY = 160;   // grace period while the pointer crosses the gap
        var closeTimer  = null;
        var openItem    = null;

        function isFloating() {
            return window.matchMedia('(min-width: 1200px)').matches;
        }

        function place(item) {
            var panel = item.querySelector(':scope > .menu-flyout-list');
            var menu  = document.getElementById('layout-menu');
            if (!panel || !menu) return;
            if (!isFloating()) { panel.style.cssText = ''; return; }

            var itemRect = item.getBoundingClientRect();
            var menuRect = menu.getBoundingClientRect();

            panel.style.left = (menuRect.right + 6) + 'px';
            panel.style.top  = itemRect.top + 'px';
            panel.style.bottom = 'auto';

            // Flip upward when the panel would run past the viewport bottom.
            var h = panel.offsetHeight;
            if (itemRect.top + h > window.innerHeight - 12) {
                var top = window.innerHeight - h - 12;
                panel.style.top = Math.max(12, top) + 'px';
            }
        }

        function open(item) {
            if (openItem && openItem !== item) close(openItem);
            clearTimeout(closeTimer);
            openItem = item;
            item.classList.add('flyout-open');
            place(item);
        }

        function close(item) {
            if (!item) return;
            item.classList.remove('flyout-open');
            if (openItem === item) openItem = null;
        }

        function itemFrom(target) {
            return target && target.closest ? target.closest('.menu-has-flyout') : null;
        }

        document.addEventListener('mouseover', function (e) {
            var item = itemFrom(e.target);
            if (!item) return;
            if (!isFloating()) return;   // no hover intent on touch layouts
            open(item);
        });

        document.addEventListener('mouseout', function (e) {
            var item = itemFrom(e.target);
            if (!item || !isFloating()) return;
            // Ignore moves that stay inside the same group (row -> panel).
            if (e.relatedTarget && itemFrom(e.relatedTarget) === item) return;
            clearTimeout(closeTimer);
            closeTimer = setTimeout(function () { close(item); }, CLOSE_DELAY);
        });

        // Tap/click toggles, which is the only way in on touch layouts.
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest ? e.target.closest('.menu-flyout-toggle') : null;
            if (toggle) {
                e.preventDefault();
                var item = itemFrom(toggle);
                if (!item) return;
                if (item.classList.contains('flyout-open')) close(item);
                else open(item);
                return;
            }
            if (openItem && !itemFrom(e.target)) close(openItem);
        });

        // A pinned panel must not stay behind when the ground moves.
        ['scroll', 'resize'].forEach(function (evt) {
            window.addEventListener(evt, function () {
                if (openItem) place(openItem);
            }, true);
        });

        document.addEventListener('turbo:before-render', function () {
            if (openItem) close(openItem);
        });
    })();
    </script>
