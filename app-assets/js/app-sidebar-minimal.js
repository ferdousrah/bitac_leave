/*!
 =========================================================
 * Minimal Sidebar Functionality
 * Handles ONLY sidebar collapse and mobile toggle
 * Menu functionality is handled by custom-sidebar-menu.js
 =========================================================
*/

// Global flag to prevent duplicate initialization
if (typeof window._sidebarMinimalInitialized === 'undefined') {
    window._sidebarMinimalInitialized = false;
}

function initSidebarMinimal() {
    // Prevent duplicate initialization
    if (window._sidebarMinimalInitialized) {
        console.log('Sidebar minimal already initialized, skipping...');
        return;
    }

    var $sidebar = $('.app-sidebar'),
        $sidebar_content = $('.sidebar-content'),
        $sidebar_img = $sidebar.data('image'),
        $sidebar_img_container = $('.sidebar-background'),
        $wrapper = $('.wrapper');

    if (!$sidebar.length) {
        console.warn('Sidebar not found, initialization skipped');
        return;
    }

    console.log('Initializing sidebar minimal functionality...');

    // Initialize perfect scrollbar
    if ($.fn.perfectScrollbar && $sidebar_content.length) {
        try {
            $sidebar_content.perfectScrollbar();
        } catch (e) {
            console.warn('Perfect scrollbar initialization failed:', e);
        }
    }

    // Set sidebar background image if exists - DISABLED (using gradient instead)
    // if ($sidebar_img_container.length !== 0 && $sidebar_img !== undefined) {
    //     $sidebar_img_container.css('background-image', 'url("' + $sidebar_img + '")');
    // }

    // Match card heights
    setTimeout(function() {
        $('.row.match-height').each(function() {
            $(this).find('.card').not('.card .card').matchHeight();
        });
    }, 500);

    // ========================================
    // SIDEBAR COLLAPSE FUNCTIONALITY
    // ========================================

    $('.nav-toggle').on('click', function() {
        var $this = $(this),
            toggle_icon = $this.find('.toggle-icon'),
            toggle = toggle_icon.attr('data-toggle'),
            compact_menu_checkbox = $('.cz-compact-menu');

        if (toggle === 'expanded') {
            $wrapper.addClass('nav-collapsed');
            $('.nav-toggle').find('.toggle-icon').removeClass('ft-toggle-right').addClass('ft-toggle-left');
            toggle_icon.attr('data-toggle', 'collapsed');
            if (compact_menu_checkbox.length > 0) {
                compact_menu_checkbox.prop('checked', true);
            }
        } else {
            $wrapper.removeClass('nav-collapsed menu-collapsed');
            $('.nav-toggle').find('.toggle-icon').removeClass('ft-toggle-left').addClass('ft-toggle-right');
            toggle_icon.attr('data-toggle', 'expanded');
            if (compact_menu_checkbox.length > 0) {
                compact_menu_checkbox.prop('checked', false);
            }
        }
    });

    // ========================================
    // SIDEBAR HOVER BEHAVIOR (when collapsed)
    // ========================================

    $sidebar.on('mouseenter', function() {
        if ($wrapper.hasClass('nav-collapsed')) {
            $wrapper.removeClass('menu-collapsed');

            // Open any previously collapsed submenus
            var $listItem = $('.custom-sidebar-menu li.nav-collapsed-open');
            if ($listItem.length && window.CustomSidebarMenu) {
                $listItem.addClass('open').removeClass('nav-collapsed-open');
            }
        }
    }).on('mouseleave', function(event) {
        if ($wrapper.hasClass('nav-collapsed')) {
            $wrapper.addClass('menu-collapsed');

            // Close open submenus
            var $listItem = $('.custom-sidebar-menu li.open');
            $listItem.addClass('nav-collapsed-open').removeClass('open');
        }
    });

    // ========================================
    // MOBILE RESPONSIVE FUNCTIONALITY
    // ========================================

    // Hide sidebar on mobile initially
    if ($(window).width() < 992) {
        $sidebar.addClass('hide-sidebar');
        $wrapper.removeClass('nav-collapsed menu-collapsed');
    }

    // Handle window resize
    $(window).resize(function() {
        if ($(window).width() < 992) {
            $sidebar.addClass('hide-sidebar');
            $wrapper.removeClass('nav-collapsed menu-collapsed');
        }
        if ($(window).width() > 992) {
            $sidebar.removeClass('hide-sidebar');
            if ($('.toggle-icon').attr('data-toggle') === 'collapsed' && $wrapper.not('.nav-collapsed menu-collapsed')) {
                $wrapper.addClass('nav-collapsed menu-collapsed');
            }
        }
    });

    // Close sidebar when clicking on menu item (mobile only)
    $(document).on('click', '.custom-sidebar-menu li a', function() {
        // Don't close if it's a parent menu with submenu
        if (!$(this).parent().hasClass('has-submenu')) {
            if ($(window).width() < 992) {
                $sidebar.addClass('hide-sidebar');
            }
        }
    });

    // Close sidebar when clicking logo (mobile only)
    $(document).on('click', '.logo-text', function() {
        if ($(window).width() < 992) {
            $sidebar.addClass('hide-sidebar');
        }
    });

    // ========================================
    // MOBILE MENU TOGGLE (hamburger button)
    // ========================================

    $('.navbar-toggle').on('click', function(e) {
        e.stopPropagation();
        $sidebar.toggleClass('hide-sidebar');
    });

    // Close sidebar when clicking outside (mobile only)
    $('html').on('click', function(e) {
        if ($(window).width() < 992) {
            if (!$sidebar.hasClass('hide-sidebar') && $sidebar.has(e.target).length === 0) {
                $sidebar.addClass('hide-sidebar');
            }
        }
    });

    // Sidebar close button
    $('#sidebarClose').on('click', function() {
        $sidebar.addClass('hide-sidebar');
    });

    // ========================================
    // NOTIFICATION LIST SCROLLBAR
    // ========================================

    if ($.fn.perfectScrollbar) {
        $('.noti-list').perfectScrollbar();
    }

    // ========================================
    // FULLSCREEN FUNCTIONALITY
    // ========================================

    $('.apptogglefullscreen').on('click', function(e) {
        if (typeof screenfull != 'undefined') {
            if (screenfull.enabled) {
                screenfull.toggle();
            }
        }
    });

    if (typeof screenfull != 'undefined') {
        if (screenfull.enabled) {
            $(document).on(screenfull.raw.fullscreenchange, function() {
                if (screenfull.isFullscreen) {
                    $('.apptogglefullscreen').find('i').toggleClass('ft-minimize ft-maximize');
                } else {
                    $('.apptogglefullscreen').find('i').toggleClass('ft-maximize ft-minimize');
                }
            });
        }
    }

    // Mark as initialized
    window._sidebarMinimalInitialized = true;
    console.log('Sidebar minimal functionality initialized successfully');
}

// Initialize on document ready
$(document).ready(function() {
    initSidebarMinimal();
});

// Reinitialize responsive behavior on Turbo navigation (but don't reinit event listeners)
document.addEventListener('turbo:load', function() {
    // Just ensure mobile state is correct, don't reinit everything
    var $sidebar = $('.app-sidebar');
    var $wrapper = $('.wrapper');

    if ($(window).width() < 992) {
        $sidebar.addClass('hide-sidebar');
        $wrapper.removeClass('nav-collapsed menu-collapsed');
    }
});
