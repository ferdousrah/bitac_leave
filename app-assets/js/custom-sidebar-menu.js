/* ========================================
   Custom Sidebar Menu JavaScript
   Pure vanilla JS - No dependencies on old code
   ======================================== */

(function() {
    'use strict';

    // Configuration
    const config = {
        menuSelector: '.custom-sidebar-menu',
        parentItemSelector: '.has-submenu',
        submenuSelector: '.custom-submenu',
        activeClass: 'active',
        openClass: 'open',
        animationDuration: 300
    };

    // Page relationships for form pages
    const pageRelationships = {
        'new_center_form': 'manage_center',
        'edit_center_form': 'manage_center',
        'new_section_form': 'manage_sections',
        'edit_section_form': 'manage_sections',
        'new_designation_form': 'manage_designations',
        'edit_designation_form': 'manage_designations',
        'new_employee_form': 'manage_employees',
        'edit_employee_info_form': 'manage_employees',
        'new_signatory_form': 'manage_leave_signatory',
        'edit_leave_sig_form': 'manage_leave_signatory',
        'new_user_group_form': 'manage_user_group',
        'edit_user_group_form': 'manage_user_group',
        'manage_group_access': 'manage_user_group',
        'new_user_form': 'manage_user',
        'edit_user_form': 'manage_user'
    };

    // Global flag to prevent duplicate initialization across Turbo navigations
    // Store on window object so it persists even if this script is re-evaluated
    if (typeof window._customMenuEventsInitialized === 'undefined') {
        window._customMenuEventsInitialized = false;
    }

    // Main menu object
    const CustomSidebarMenu = {
        // Initialize the menu
        init: function() {
            console.log('CustomSidebarMenu.init() called');
            // Only setup event listeners ONCE (check global flag)
            if (!window._customMenuEventsInitialized) {
                this.setupEventListeners();
                window._customMenuEventsInitialized = true;
            }
            // Always highlight active page (can be called multiple times)
            console.log('CustomSidebarMenu.init(): Calling highlightActivePage()');
            this.highlightActivePage();
        },

        // Setup click event listeners (only called once)
        setupEventListeners: function() {
            const menu = document.querySelector(config.menuSelector);
            if (!menu) {
                console.error('Custom menu not found!');
                return;
            }

            console.log('Custom menu initialized successfully');

            // Use event delegation for better performance
            menu.addEventListener('click', (e) => {
                // Find the closest parent link (even if clicking on icon or span inside)
                const parentLink = e.target.closest(config.parentItemSelector + ' > a');

                if (parentLink) {
                    e.preventDefault();
                    e.stopPropagation();

                    const parentItem = parentLink.closest(config.parentItemSelector);
                    console.log('Menu clicked:', parentItem);
                    this.toggleSubmenu(parentItem);
                }
            });
        },

        // Toggle submenu open/close
        toggleSubmenu: function(parentItem) {
            if (!parentItem) return;

            const isOpen = parentItem.classList.contains(config.openClass);
            console.log('Toggling menu, currently open:', isOpen);

            // Close all other open menus (accordion behavior)
            this.closeAllSubmenus();

            // Toggle this menu
            if (!isOpen) {
                parentItem.classList.add(config.openClass);
                this.animateSubmenu(parentItem, true);
                console.log('Menu opened');
            }
        },

        // Close all submenus
        closeAllSubmenus: function() {
            const openItems = document.querySelectorAll(config.parentItemSelector + '.' + config.openClass);
            openItems.forEach(item => {
                item.classList.remove(config.openClass);
                this.animateSubmenu(item, false);
            });
        },

        // Animate submenu opening/closing
        animateSubmenu: function(parentItem, isOpening) {
            const submenu = parentItem.querySelector(config.submenuSelector);
            if (!submenu) {
                console.warn('No submenu found in:', parentItem);
                return;
            }

            if (isOpening) {
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
                console.log('Submenu max-height set to:', submenu.scrollHeight + 'px');
            } else {
                submenu.style.maxHeight = '0';
            }
        },

        // Highlight the active page in menu
        highlightActivePage: function() {
            const currentPage = this.getCurrentPage();
            const pageToMatch = pageRelationships[currentPage] || currentPage;

            console.log('Highlighting page:', currentPage, '-> match with:', pageToMatch);

            // Clear all active states
            this.clearActiveStates();

            // Find and highlight matching menu item
            const allLinks = document.querySelectorAll(config.menuSelector + ' a');

            for (let link of allLinks) {
                const href = link.getAttribute('href');
                if (!href) continue;

                // Extract page name from href and remove .php extension if present
                let linkPage = href.split('/').pop().split('?')[0];
                linkPage = linkPage.replace('.php', '');

                console.log('Checking link:', linkPage, 'against:', pageToMatch);

                if (linkPage === pageToMatch) {
                    const listItem = link.parentElement;
                    listItem.classList.add(config.activeClass);

                    console.log('Match found! Highlighting:', linkPage);

                    // If it's a submenu item, open and highlight parent
                    const parentMenu = listItem.closest(config.parentItemSelector);
                    if (parentMenu) {
                        parentMenu.classList.add(config.openClass, config.activeClass);
                        this.animateSubmenu(parentMenu, true);
                        console.log('Parent menu opened');
                    }

                    break;
                }
            }
        },

        // Clear all active states
        clearActiveStates: function() {
            const activeItems = document.querySelectorAll(config.menuSelector + ' .' + config.activeClass);
            activeItems.forEach(item => {
                item.classList.remove(config.activeClass);
            });
        },

        // Get current page name from URL
        getCurrentPage: function() {
            const path = window.location.pathname;
            let page = path.split('/').pop().split('?')[0];
            // Remove .php extension if present
            page = page.replace('.php', '');
            return page || 'dashboard';
        },

        // Reinitialize (for Turbo navigation)
        reinit: function() {
            console.log('CustomSidebarMenu.reinit() called');
            this.highlightActivePage();
        }
    };

    // DON'T auto-initialize - we call init() manually after sidebar loads via AJAX
    // This prevents trying to initialize before the menu HTML is loaded

    // Turbo navigation support - only reinit (highlight), don't re-setup events
    document.addEventListener('turbo:load', () => {
        console.log('CustomSidebarMenu: Turbo load event fired');
        const menuExists = document.querySelector(config.menuSelector);
        console.log('CustomSidebarMenu: Menu exists?', !!menuExists);
        if (menuExists) {
            console.log('CustomSidebarMenu: Calling reinit()');
            CustomSidebarMenu.reinit();
        } else {
            console.warn('CustomSidebarMenu: Menu not found, cannot highlight');
        }
    });

    // Make it globally accessible so we can call init() manually
    window.CustomSidebarMenu = CustomSidebarMenu;

})();
