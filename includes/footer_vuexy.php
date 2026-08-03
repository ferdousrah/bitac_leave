                    </div>
                    <!-- / Content -->
                    </turbo-frame>
                    <!-- / Turbo Frame: main-content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="text-body" style="font-size: 12px;">
                                    
                                </div>
                                <div class="d-none d-lg-inline-block">
                                    <?php
                                    global $start;
                                    $end = microtime(true);
                                    $time = number_format(($end - $start), 2);
                                    // echo 'Page loaded in ' . $time . ' seconds';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>

        <!-- Drag Target Area To SlideIn Menu On Small Screens -->
        <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS - data-turbo-eval="false" prevents re-execution on Turbo navigation -->
    <script src="<?= $assetURL ?>/vendor/libs/jquery/jquery.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/popper/popper.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/js/bootstrap.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/node-waves/node-waves.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/pickr/pickr.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/perfect-scrollbar/perfect-scrollbar.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/hammer/hammer.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/js/menu.js" data-turbo-eval="false"></script>

    <!-- Vendors JS -->
    <script src="<?= $assetURL ?>/vendor/libs/datatables-bs5/datatables-bootstrap5.js" data-turbo-eval="false"></script>
    <!-- DataTables Responsive plugin (bundled CSS is already loaded; JS comes from CDN) -->
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js" data-turbo-eval="false"></script>
    <script data-turbo-eval="false">
        // Enable responsive rendering globally so every DataTable collapses extra
        // columns into expandable rows on small screens — no per-page edits needed.
        if (window.jQuery && jQuery.fn.dataTable) {
            jQuery.extend(true, jQuery.fn.dataTable.defaults, { responsive: true });
        }
    </script>
    <script src="<?= $assetURL ?>/vendor/libs/moment/moment.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/flatpickr/flatpickr.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/@form-validation/popular.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/@form-validation/bootstrap5.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/@form-validation/auto-focus.js" data-turbo-eval="false"></script>
    <!-- Use CDN Select2 JS for testing -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" data-turbo-eval="false"></script>
    <!-- <script src="<?= $assetURL ?>/vendor/libs/select2/select2.js" data-turbo-eval="false"></script> -->
    <script src="<?= $assetURL ?>/vendor/libs/sweetalert2/sweetalert2.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/bootstrap-select/bootstrap-select.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/typeahead-js/typeahead.js" data-turbo-eval="false"></script>
    <script src="<?= $assetURL ?>/vendor/libs/tagify/tagify.js" data-turbo-eval="false"></script>
   

    <!-- Main JS -->
    <script src="<?= $assetURL ?>/js/main.js" data-turbo-eval="false"></script>

    <!-- jQuery UI for Bengali date picker if needed -->
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
    <script src="//code.jquery.com/ui/1.13.0/jquery-ui.js" data-turbo-eval="false"></script>

    <!-- Application Scripts -->
    <script>
// Ensure assetsPath is always set correctly (absolute URL)
window.assetsPath = '<?= $assetURL ?>/';
window.templateName = 'vertical-menu-template';

// Logout function
function logout() {
    Swal.fire({
        title: 'Are you sure?',
        text: "Once logout, you will not be able to access this page without login!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, logout!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'post',
                url: '<?php echo $baseURL; ?>logout.php',
                data: 'username=' + '' + '&password=' + '',
                success: function(data) {
                    window.location = '<?php echo $baseURL; ?>index.php';
                }
            });
        }
    });
}

// Multi-role: switch active user_group with confirmation Swal + full page reload
// so the sidebar + permission lookups pick up the new role.
function switchUserRole(groupId, anchorEl) {
    var groupName = anchorEl ? (anchorEl.getAttribute('data-group-name') || '') : '';
    Swal.fire({
        title: 'রোল পরিবর্তন করবেন?',
        html: 'আপনি কি <b>' + (groupName ? $('<span>').text(groupName).html() : 'এই গ্রুপ') + '</b> রোলে পরিবর্তন করতে চান?<br><small class="text-muted">পেজ রিলোড হবে।</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6c5ce7',
        cancelButtonColor: '#8592a3',
        confirmButtonText: 'হ্যাঁ, পরিবর্তন',
        cancelButtonText: 'বাতিল',
        customClass: {
            confirmButton: 'btn btn-primary me-3',
            cancelButton:  'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({
            type: 'POST',
            url: '<?php echo $baseURL; ?>api/auth/switch-role.php',
            data: { group_id: groupId },
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.status === 1) {
                    // Full reload (NOT Turbo) — sidebar + active group must be re-resolved server-side
                    window.location.reload();
                } else {
                    Swal.fire({
                        title: 'ত্রুটি',
                        text: (resp && resp.message) ? resp.message : 'রোল পরিবর্তন ব্যর্থ হয়েছে',
                        icon: 'error',
                        confirmButtonColor: '#ff3e1d',
                        customClass: { confirmButton: 'btn btn-danger' },
                        buttonsStyling: false
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: 'ত্রুটি',
                    text: 'সার্ভারের সাথে সংযোগ ব্যর্থ হয়েছে',
                    icon: 'error',
                    confirmButtonColor: '#ff3e1d',
                    customClass: { confirmButton: 'btn btn-danger' },
                    buttonsStyling: false
                });
            }
        });
    });
}

// Function to load notifications after the page has loaded
document.addEventListener("turbo:load", function() {
    fetchNotifications();
});

document.addEventListener("DOMContentLoaded", function() {
    fetchNotifications();
});

function fetchNotifications() {
    fetch('<?php echo $baseURL; ?>api/notifications/fetch.php?scope=unread&limit=15', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(resp => {
            if (!resp || resp.status !== 1) return;
            const items = Array.isArray(resp.items) ? resp.items : [];
            const unread = parseInt(resp.unreadCount || 0, 10);

            // ── Badge on bell icon ──
            const badge = document.querySelector('.notif-badge-count');
            if (badge) {
                if (unread > 0) {
                    badge.textContent = unread > 9 ? '৯+' : toBn(unread);
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }

            // ── Header count pill ──
            const headerCount = document.querySelector('.notif-header-count');
            if (headerCount) {
                headerCount.textContent = toBn(unread) + ' নতুন';
                headerCount.style.background = unread > 0
                    ? 'linear-gradient(135deg,#f5576c,#c0392b)'
                    : 'rgba(255,255,255,0.2)';
            }

            // ── List ──
            const notificationList = document.querySelector('.dropdown-notifications-list');
            if (!notificationList) return;
            notificationList.innerHTML = '';

            if (items.length === 0) {
                notificationList.innerHTML = `
                    <div class="notif-empty">
                        <i class="ti tabler-bell-off"></i>
                        <p>কোনো নতুন নোটিফিকেশন নেই</p>
                    </div>`;
                ensureMarkAllReadBtn(0);
                return;
            }

            // Normalize a stored notification link into an absolute URL under
            // BASE_URL. Stored links are relative paths (`views/leave/…`) —
            // rendering them raw makes the browser resolve them against the
            // CURRENT page, so from any /views/** page the link 404s.
            //
            // Also rewrites legacy paths (old snake_case root-level files that
            // don't exist anymore) to their modern equivalents. Handles ~28k
            // historical notification rows without a DB migration.
            const LEGACY_MAP = [
                { m: /^leave_office_notice(\.php)?\b/,               to: 'api/reports/leave-notice.php' },
                { m: /^leave_application_details(\.php)?\b/,         to: 'views/leave/application-details.php' },
                { m: /^leave_joining_application_details(\.php)?\b/, to: 'views/leave/approve-joining-application.php' },
                { m: /^approve_increment_data_changes(\.php)?\b/,    to: 'views/salary-increment/approve-changes.php' },
            ];
            function resolveLink(link) {
                if (!link || link === 'javascript:void(0);') return 'javascript:void(0);';
                if (/^(https?:)?\/\//i.test(link) || link.startsWith('javascript:')) return link;
                let normalized = link.replace(/^\/+/, '');
                for (const rule of LEGACY_MAP) {
                    if (rule.m.test(normalized)) { normalized = normalized.replace(rule.m, rule.to); break; }
                }
                const base = '<?php echo rtrim($baseURL, "/"); ?>';
                return base + '/' + normalized;
            }

            items.forEach((notif, idx) => {
                const el = document.createElement('a');
                el.href = resolveLink(notif.link);
                el.className = 'notif-item' + (notif.isRead ? '' : ' notif-item-unread');
                el.dataset.notifId = notif.id;
                el.style.animationDelay = (idx * 0.04) + 's';

                // pick icon & colour by type slug OR message keyword
                let iconClass = 'tabler-bell', iconBg = '#eef0ff', iconColor = '#667eea';
                const typeSlug = String(notif.type || '').toLowerCase();
                const msg = (notif.message || '').toLowerCase();
                if (typeSlug.includes('reject') || msg.includes('প্রত্যাখ্যাত')) {
                    iconClass = 'tabler-x'; iconBg = '#fee2e2'; iconColor = '#dc2626';
                } else if (typeSlug.includes('office') || msg.includes('অফিস আদেশ')) {
                    iconClass = 'tabler-file-certificate'; iconBg = '#fff3cd'; iconColor = '#f59e0b';
                } else if (typeSlug.includes('join') || msg.includes('যোগদান')) {
                    iconClass = 'tabler-user-check'; iconBg = '#e0f2fe'; iconColor = '#0ea5e9';
                } else if (typeSlug.includes('opa') || typeSlug.includes('optional') || msg.includes('ঐচ্ছিক')) {
                    iconClass = 'tabler-calendar-star'; iconBg = '#f3e8ff'; iconColor = '#9333ea';
                } else if (typeSlug.includes('leave') || msg.includes('ছুটি')) {
                    iconClass = 'tabler-calendar-event'; iconBg = '#e8f5e9'; iconColor = '#28a745';
                } else if (typeSlug.includes('addition') || typeSlug.includes('deduction') || msg.includes('সংযোজন') || msg.includes('কর্তন')) {
                    iconClass = 'tabler-adjustments-alt'; iconBg = '#eef2ff'; iconColor = '#4338ca';
                }

                el.innerHTML = `
                    <div class="notif-icon-wrap" style="background:${iconBg}; color:${iconColor};">
                        <i class="ti ${iconClass}"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div class="notif-item-title">${notif.message || ''}</div>
                        <div class="notif-item-time">
                            <i class="ti tabler-clock" style="font-size:0.7rem;"></i>
                            ${notif.dateHuman || notif.dateTime || ''}
                        </div>
                    </div>
                    ${notif.isRead ? '' : '<span class="notif-unread-dot" aria-label="unread"></span>'}
                `;

                // Click → mark read then follow link (async, don't block navigation)
                el.addEventListener('click', function(ev){
                    if (notif.id && !notif.isRead) {
                        try {
                            navigator.sendBeacon
                                ? navigator.sendBeacon('<?php echo $baseURL; ?>api/notifications/mark-read.php',
                                    new URLSearchParams({ id: notif.id }))
                                : fetch('<?php echo $baseURL; ?>api/notifications/mark-read.php', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'id=' + encodeURIComponent(notif.id),
                                    keepalive: true
                                });
                        } catch (e) {}
                    }
                    // If link is a placeholder (javascript:void(0)), prevent nav
                    if (!notif.link || notif.link === 'javascript:void(0);') {
                        ev.preventDefault();
                    }
                });

                notificationList.appendChild(el);
            });

            ensureMarkAllReadBtn(unread);
        })
        .catch(error => console.error('Error fetching notifications:', error));
}

// Ensure a "সব পড়া হয়েছে" action exists in the dropdown footer.
// Idempotent — replaces existing on each fetch so the count refreshes.
function ensureMarkAllReadBtn(unread) {
    const menu = document.querySelector('.notif-dropdown-menu');
    if (!menu) return;
    let footer = menu.querySelector('.notif-dd-footer');
    if (!footer) {
        footer = document.createElement('div');
        footer.className = 'notif-dd-footer';
        footer.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:8px 14px;border-top:1px solid #eef0f5;background:#fafbfd;border-radius:0 0 12px 12px;';
        menu.appendChild(footer);
    }
    footer.innerHTML = `
        <button type="button" class="notif-mark-all-btn" ${unread === 0 ? 'disabled' : ''}
            style="background:transparent;border:none;color:${unread === 0 ? '#9ca3af' : '#4338ca'};font-size:0.78rem;font-weight:500;cursor:${unread === 0 ? 'default' : 'pointer'};padding:4px 6px;">
            <i class="ti tabler-checks me-1"></i>সব পড়া হয়েছে
        </button>
    `;
    const btn = footer.querySelector('.notif-mark-all-btn');
    if (btn && unread > 0) {
        btn.addEventListener('click', function(e){
            e.preventDefault();
            fetch('<?php echo $baseURL; ?>api/notifications/mark-all-read.php', {
                method: 'POST', credentials: 'same-origin'
            }).then(r => r.json()).then(res => {
                if (res && res.status === 1) fetchNotifications();
            });
        });
    }
}

function toBn(n) {
    return String(n).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[d]);
}

// Function to load menu badge counts — signatory-wise (per current user)
function loadContent() {
    $.ajax({
        url: '<?php echo $baseURL; ?>api/menu-counts.php',
        type: 'GET',
        dataType: 'json',
        cache: false,
        success: function(resp) {
            if (!resp || resp.status !== 1) return;

            // Slug → response key mapping. Any submenu with a matching id
            // gets its badge updated. Add new entries here as endpoints expand.
            var slugs = [
                'regular-leave-addition',
                'previous-leave-regular-info-approve',
                'optional-pre-approval-supervisor-queue',
                'optional-pre-approval-forward-queue',
                'optional-pre-approval-queue',
                'leave-approval',
                'leave-joining-approval',
                'allowed-leave-applications',
                'all-leave-application'
            ];

            slugs.forEach(function(slug) {
                var v = parseInt(resp[slug] || 0, 10);
                var $badge = $('#' + slug);
                if (!$badge.length) return;
                if (v > 0) $badge.text(v).css('display', 'inline');
                else       $badge.text('0').css('display', 'none');
            });

            // Aggregate on the parent Leave module badge
            var total = parseInt(resp.total || 0, 10);
            if (total > 0) $('#totalTask').text(total).css('display', 'inline');
            else           $('#totalTask').text('0').css('display', 'none');

            // Admin Panel parent badge
            var adminTotal = parseInt(resp.admin_total || 0, 10);
            if (adminTotal > 0) $('#adminPanelTotal').text(adminTotal).css('display', 'inline');
            else                $('#adminPanelTotal').text('0').css('display', 'none');
        },
        error: function(xhr, status, error) {
            console.log('Error loading badge counts:', error);
        }
    });
}

// Function to update menu active state based on current URL
// NOTE: We only touch .active — never strip .open — so expanded submenus stay expanded across navigations.
function updateMenuActiveState() {
    const urlParams = new URLSearchParams(window.location.search);
    const menuSlug = urlParams.get('menuslug');

    if (!menuSlug) return;

    // Remove only .active (NOT .open) — preserves which submenus are expanded
    $('.menu-item').removeClass('active');

    const menuSlugPattern = new RegExp('menuslug=' + menuSlug + '(&|#|$)');

    $('.menu-link').each(function() {
        const href = $(this).attr('href');
        if (href && menuSlugPattern.test(href)) {
            const menuItem = $(this).closest('.menu-item');
            menuItem.addClass('active');

            // If this is a submenu item, ensure its parent is active and open
            const parentMenuItem = menuItem.parent('.menu-sub').closest('.menu-item');
            if (parentMenuItem.length) {
                parentMenuItem.addClass('active open');
            }
        }
    });
}

// Function to expand active menu item programmatically
function expandActiveMenu() {
    const urlParams = new URLSearchParams(window.location.search);
    const menuSlug = urlParams.get('menuslug');

    if (!menuSlug) return;

    // Create regex pattern for exact menuslug match (must be followed by &, #, or end of string)
    const menuSlugPattern = new RegExp('menuslug=' + menuSlug + '(&|#|$)');

    // Find the active menu link using exact match
    let activeLink = null;
    document.querySelectorAll('.menu-link').forEach(function(link) {
        const href = link.getAttribute('href');
        if (href && menuSlugPattern.test(href)) {
            activeLink = link;
        }
    });

    if (activeLink) {
        const menuItem = activeLink.closest('.menu-item');
        if (menuItem) {
            menuItem.classList.add('active');

            // Find parent menu-item (for submenus)
            const parentSub = menuItem.closest('.menu-sub');
            if (parentSub) {
                const parentItem = parentSub.closest('.menu-item');
                if (parentItem) {
                    parentItem.classList.add('active', 'open');
                    // Don't set inline style - let the Menu component handle it via CSS classes
                }
            }
        }
    }
}

// ========================================
// Turbo Configuration for Smooth Navigation
// ========================================

// Global flag to prevent duplicate event listener registration
if (typeof window._turboEventsInitialized === 'undefined') {
    window._turboEventsInitialized = false;
}

// Debounce flag to prevent double initialization
if (typeof window._lastInitTime === 'undefined') {
    window._lastInitTime = 0;
}

// Function to initialize page components
function initializePageComponents() {
    // Debounce: prevent initialization if called within 100ms of last init
    var now = Date.now();
    if (now - window._lastInitTime < 100) {
        return;
    }
    window._lastInitTime = now;

    // Initialize menu ONCE — sidebar is Turbo-permanent so don't destroy/recreate on navigation
    if (typeof Menu !== 'undefined' && typeof window.Helpers !== 'undefined') {
        let layoutMenuEl = document.querySelector('#layout-menu');
        if (layoutMenuEl) {
            if (!window.Helpers.mainMenu) {
                // First-time setup
                window.Helpers.mainMenu = new Menu(layoutMenuEl, {
                    orientation: 'vertical',
                    closeChildren: true,
                    showDropdownOnHover: true
                });
                console.log('Menu initialized (first time)');
            }
            // Always update active state based on current URL (cheap, no re-render)
            expandActiveMenu();
        }
    }

    // Load menu badge counts
    if (typeof loadContent === 'function') {
        loadContent();
    }

    // Update menu active state based on current URL
    if (typeof updateMenuActiveState === 'function') {
        updateMenuActiveState();
    }

    // Initialize Select2 if exists (using CDN version with Bootstrap 5 theme)
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('.select2').each(function() {
            var $this = jQuery(this);
            // Destroy existing Select2 instance if present (handles Turbo navigation)
            if ($this.hasClass('select2-hidden-accessible')) {
                try {
                    $this.select2('destroy');
                } catch(e) {}
                // Unwrap position-relative div if it was added previously
                var $parent = $this.parent();
                if ($parent.hasClass('position-relative') && $parent.children().length === 1) {
                    $this.unwrap();
                }
            }
            // Wrap in position-relative div (required for proper dropdown positioning)
            if (!$this.parent().hasClass('position-relative')) {
                $this.wrap('<div class="position-relative"></div>');
            }
            try {
                // CDN Select2 with Bootstrap 5 theme
                $this.select2({
                    theme: 'bootstrap-5',
                    placeholder: $this.find('option[value=""]').text() || 'Select...',
                    allowClear: $this.data('allow-clear') === true,
                    dropdownParent: $this.parent()
                });
            } catch(e) {
                console.log('Select2 init error:', e);
            }
        });
    }

    // Initialize tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el);
            }
        });
    }
}

// Only register Turbo event listeners ONCE
if (!window._turboEventsInitialized && typeof Turbo !== 'undefined') {
    console.log('Registering Turbo event listeners...');

    // Clean up Select2 before Turbo caches the page snapshot
    // This prevents dead Select2 widgets in the cached DOM
    document.addEventListener('turbo:before-cache', function() {
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            jQuery('.select2-hidden-accessible').each(function() {
                try {
                    jQuery(this).select2('destroy');
                } catch(e) {}
            });
            // Remove any leftover position-relative wrappers added by initializePageComponents
            jQuery('.select2').each(function() {
                var $this = jQuery(this);
                var $parent = $this.parent();
                if ($parent.hasClass('position-relative') && $parent.children().length === 1) {
                    $this.unwrap();
                }
            });
        }
    });

    // Reinitialize components after Turbo navigation
    document.addEventListener('turbo:load', function() {
        console.log('Turbo: Page loaded');

        // Apply stored theme settings
        if (typeof applyStoredTheme === 'function') {
            applyStoredTheme();
        }

        // Small delay to ensure DOM is ready
        setTimeout(function() {
            initializePageComponents();
        }, 50);

        // Scroll to top on page change
        window.scrollTo(0, 0);
    });

    // Mark as initialized
    window._turboEventsInitialized = true;
    console.log('Turbo event listeners registered successfully');
}

// Initialize on document ready (for initial page load)
$(document).ready(function() {
    // Always initialize components on document ready
    initializePageComponents();
    loadContent();

    // Apply theme from localStorage if available
    applyStoredTheme();
});

// Function to apply stored theme settings
function applyStoredTheme() {
    try {
        const storedTheme = localStorage.getItem('templateCustomizer-' + window.templateName + '--Theme');
        if (storedTheme) {
            document.documentElement.setAttribute('data-bs-theme', storedTheme);
        }
    } catch (e) {
        console.log('Could not apply stored theme:', e);
    }
}

// PWA: register the service worker so the app is installable and caches static assets.
// On localhost we never register — and actively unregister any leftover SW + purge its caches —
// so dev work is never broken by a stale worker returning fake 504s for CSS/JS.
(function () {
    if (!('serviceWorker' in navigator)) return;
    var isLocalhost = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname);

    if (isLocalhost) {
        navigator.serviceWorker.getRegistrations().then(function (regs) {
            regs.forEach(function (r) { r.unregister(); });
        }).catch(function () {});
        if (window.caches) {
            caches.keys().then(function (keys) {
                keys.forEach(function (k) { caches.delete(k); });
            }).catch(function () {});
        }
        return;
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= BASE_URL ?>/service-worker.js', {
            scope: '<?= BASE_URL ?>/'
        }).catch(function (err) {
            console.log('Service worker registration failed:', err);
        });
    });
})();
</script>

<!-- Sidebar mobile drawer + desktop collapsed-rail enhancements -->
<script data-turbo-eval="false">
(function() {
    var html = document.documentElement;
    var body = document.body;
    var sidebar = document.getElementById('layout-menu');

    function isExpanded() {
        return html.classList.contains('layout-menu-expanded') || body.classList.contains('layout-menu-expanded');
    }
    function closeDrawer() {
        html.classList.remove('layout-menu-expanded');
        body.classList.remove('layout-menu-expanded');
    }

    // Backup mobile drawer toggle — document-delegated so it survives Turbo swaps.
    // Vuexy's Menu class can lose its binding to the navbar hamburger after navigation;
    // this ensures the hamburger ALWAYS works on mobile.
    document.addEventListener('click', function(e) {
        if (window.innerWidth >= 1200) return; // desktop uses Vuexy's own logic
        var toggle = e.target.closest('.layout-menu-toggle, .layout-overlay.layout-menu-toggle');
        if (!toggle) return;
        // Skip the sidebar's own × close-button (Vuexy handles that fine on the persistent sidebar)
        // — actually we WANT it to also work via our handler so let's just toggle.
        e.preventDefault();
        e.stopPropagation();
        if (isExpanded()) {
            closeDrawer();
        } else {
            html.classList.add('layout-menu-expanded');
            body.classList.add('layout-menu-expanded');
        }
    }, true); // capture phase — beats other handlers

    // Close drawer when a submodule link is tapped on mobile (better UX — feels native)
    document.addEventListener('click', function(e) {
        if (window.innerWidth >= 1200) return;
        var link = e.target.closest('#layout-menu .menu-sub .menu-link, #layout-menu .menu-inner > .menu-item:not(.menu-toggle) > .menu-link');
        if (!link) return;
        if (link.classList.contains('menu-toggle')) return;
        setTimeout(closeDrawer, 80);
    });

    // Escape key closes drawer
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isExpanded()) closeDrawer();
    });

    // Auto-close drawer on viewport resize to desktop width
    var resizeT;
    window.addEventListener('resize', function() {
        clearTimeout(resizeT);
        resizeT = setTimeout(function() {
            if (window.innerWidth >= 1200 && isExpanded()) closeDrawer();
        }, 100);
    });

    // Neutralize Vuexy's built-in hover-to-expand behavior on the collapsed sidebar.
    // menu.js auto-adds `layout-menu-hover` on mouseenter — strip it instantly so
    // our collapsed CSS rules keep applying and the rail doesn't twitch on hover.
    function killHoverClass() {
        if (html.classList.contains('layout-menu-hover')) html.classList.remove('layout-menu-hover');
        if (body.classList.contains('layout-menu-hover')) body.classList.remove('layout-menu-hover');
        syncSidebarTooltips();
    }
    var hoverObserver = new MutationObserver(killHoverClass);
    hoverObserver.observe(html, { attributes: true, attributeFilter: ['class'] });
    hoverObserver.observe(body, { attributes: true, attributeFilter: ['class'] });

    // Tooltip on collapsed sidebar icons — shows menu name on hover.
    // Uses Bootstrap Tooltip so the popup escapes the sidebar's overflow.
    function isSidebarCollapsed() {
        return html.classList.contains('layout-menu-collapsed') || body.classList.contains('layout-menu-collapsed');
    }
    function syncSidebarTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        var collapsed = isSidebarCollapsed() && window.innerWidth >= 1200;
        var links = document.querySelectorAll('#layout-menu .menu-inner > .menu-item > .menu-link');
        links.forEach(function(link) {
            var inst = bootstrap.Tooltip.getInstance(link);
            if (collapsed) {
                if (!inst) {
                    var div = link.querySelector(':scope > div');
                    var label = div ? (div.getAttribute('data-i18n') || div.textContent || '').trim() : '';
                    if (!label) return;
                    link.setAttribute('data-bs-toggle', 'tooltip');
                    link.setAttribute('data-bs-placement', 'right');
                    link.setAttribute('title', label);
                    new bootstrap.Tooltip(link, {
                        boundary: 'window',
                        container: 'body',
                        customClass: 'sidebar-icon-tooltip',
                        offset: [0, 8]
                    });
                }
            } else if (inst) {
                inst.dispose();
                link.removeAttribute('data-bs-toggle');
                link.removeAttribute('title');
                link.removeAttribute('data-bs-original-title');
            }
        });
    }
    // Initial sync + on resize
    syncSidebarTooltips();
    var tipResizeT;
    window.addEventListener('resize', function() {
        clearTimeout(tipResizeT);
        tipResizeT = setTimeout(syncSidebarTooltips, 120);
    });
    // Re-sync after Turbo navigation (sidebar is permanent but content may swap)
    document.addEventListener('turbo:load', syncSidebarTooltips);
    document.addEventListener('turbo:frame-load', syncSidebarTooltips);
})();
</script>

<!-- ───────────────────────────────────────────────────────────────────
     Turbo page-script re-runner
     ───────────────────────────────────────────────────────────────────
     Pages historically place `<script>$(document).ready(...)</script>`
     AFTER `require_once footer_vuexy.php`, which renders OUTSIDE the
     `<turbo-frame>`. On Turbo navigation Turbo only swaps the frame
     contents, so those bottom scripts never re-execute — form handlers,
     event bindings, page init logic all silently break until a hard
     refresh.

     This listener fires on every Turbo frame-load. It refetches the
     just-loaded URL (Turbo's response cache typically serves this without
     a real network round-trip), extracts inline `<script>` tags that
     appeared AFTER `</turbo-frame>` in the response, and evaluates them.
     The marker `lastReinitUrl` is seeded with the current URL so the
     first frame-load (initial hard load) is a no-op — scripts already
     ran inline during HTML parse, no need to re-run them.

     Scripts opted out via `data-turbo-eval="false"` or external `src=`
     are skipped. data-turbo-eval="false" on this script itself keeps
     the listener bound across Turbo navigations without re-registering. -->
<script data-turbo-eval="false">
(function () {
    var lastReinitUrl = window.location.href; // skip the initial frame-load
    document.addEventListener('turbo:frame-load', function (e) {
        if (!e.target || e.target.id !== 'main-content') return;
        if (window.location.href === lastReinitUrl) return;
        lastReinitUrl = window.location.href;

        fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.text() : ''; })
            .then(function (html) {
                if (!html) return;
                var marker = '</turbo-frame>';
                var idx = html.indexOf(marker);
                if (idx === -1) return;
                var tail = html.substring(idx + marker.length);

                var doc = (new DOMParser()).parseFromString(tail, 'text/html');
                var scripts = doc.querySelectorAll('script:not([src]):not([data-turbo-eval="false"])');
                if (!scripts.length) return;

                scripts.forEach(function (scriptEl) {
                    var newScript = document.createElement('script');
                    if (scriptEl.type) newScript.type = scriptEl.type;
                    newScript.text = scriptEl.textContent;
                    document.body.appendChild(newScript);
                    document.body.removeChild(newScript);
                });
            })
            .catch(function () { /* silent — page just won't re-init */ });
    });
})();
</script>

<!-- Page-specific Scripts Placeholder -->
<?php if (defined('INCLUDE_FORM_VALIDATION_JS')): ?>
<script src="<?= $assetURL ?>/js/form-validation.js"></script>
<?php endif; ?>

<?php
// Hook for page-specific inline scripts - pages can define this before including footer
if (defined('PAGE_SCRIPTS')) {
    echo PAGE_SCRIPTS;
}
?>

</body>
</html>
