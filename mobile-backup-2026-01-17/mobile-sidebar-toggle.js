/**
 * Mobile Sidebar Toggle - Travian Method
 * Manages .mobileOpened class on sidebars for slide-in/out effect
 */

(function () {
    'use strict';

    // ==================== CONFIGURATION ====================

    const MOBILE_BREAKPOINT = 620; // pixels
    const SIDEBAR_LEFT_ID = 'sidebarBeforeContent';
    const SIDEBAR_RIGHT_ID = 'sidebarAfterContent';
    const CLASS_MOBILE_OPENED = 'mobileOpened';
    const CLASS_LEFT_OPEN = 'leftSidebarOpen';
    const CLASS_RIGHT_OPEN = 'rightSidebarOpen';

    // ==================== UTILITY FUNCTIONS ====================

    function isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    function $(id) {
        return document.getElementById(id);
    }

    // ==================== SIDEBAR MANAGEMENT ====================

    function openSidebar(sidebarId) {
        const sidebar = $(sidebarId);
        if (!sidebar) return;

        // Close the other sidebar first
        closeAllSidebars();

        // Open this sidebar
        sidebar.classList.add(CLASS_MOBILE_OPENED);

        // Add body class for CSS targeting
        if (sidebarId === SIDEBAR_LEFT_ID) {
            document.body.classList.add(CLASS_LEFT_OPEN);
        } else {
            document.body.classList.add(CLASS_RIGHT_OPEN);
        }
    }

    function closeSidebar(sidebarId) {
        const sidebar = $(sidebarId);
        if (!sidebar) return;

        sidebar.classList.remove(CLASS_MOBILE_OPENED);

        // Remove body class
        if (sidebarId === SIDEBAR_LEFT_ID) {
            document.body.classList.remove(CLASS_LEFT_OPEN);
        } else {
            document.body.classList.remove(CLASS_RIGHT_OPEN);
        }
    }

    function closeAllSidebars() {
        closeSidebar(SIDEBAR_LEFT_ID);
        closeSidebar(SIDEBAR_RIGHT_ID);
    }

    function toggleSidebar(sidebarId) {
        const sidebar = $(sidebarId);
        if (!sidebar) return;

        if (sidebar.classList.contains(CLASS_MOBILE_OPENED)) {
            closeSidebar(sidebarId);
        } else {
            openSidebar(sidebarId);
        }
    }

    // ==================== TOGGLE BUTTON CREATION ====================

    function createToggleButtons() {
        // Remove existing buttons if any
        removeToggleButtons();

        // Create left toggle button
        const leftToggle = document.createElement('div');
        leftToggle.className = 'sidebarToggleLeft';
        leftToggle.innerHTML = '&raquo;'; // »
        leftToggle.setAttribute('title', 'Info & Links');
        leftToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidebar(SIDEBAR_LEFT_ID);
        });
        document.body.appendChild(leftToggle);

        // Create right toggle button
        const rightToggle = document.createElement('div');
        rightToggle.className = 'sidebarToggleRight';
        rightToggle.innerHTML = '&laquo;'; // «
        rightToggle.setAttribute('title', 'Village List');
        rightToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidebar(SIDEBAR_RIGHT_ID);
        });
        document.body.appendChild(rightToggle);
    }

    function removeToggleButtons() {
        const leftToggle = document.querySelector('.sidebarToggleLeft');
        const rightToggle = document.querySelector('.sidebarToggleRight');
        if (leftToggle) leftToggle.remove();
        if (rightToggle) rightToggle.remove();
    }

    // ==================== EVENT HANDLERS ====================

    function handleClickOutside(e) {
        if (!isMobile()) return;

        const leftSidebar = $(SIDEBAR_LEFT_ID);
        const rightSidebar = $(SIDEBAR_RIGHT_ID);

        // Close left sidebar if clicking outside
        if (leftSidebar && leftSidebar.classList.contains(CLASS_MOBILE_OPENED)) {
            if (!leftSidebar.contains(e.target) && !e.target.classList.contains('sidebarToggleLeft')) {
                closeSidebar(SIDEBAR_LEFT_ID);
            }
        }

        // Close right sidebar if clicking outside
        if (rightSidebar && rightSidebar.classList.contains(CLASS_MOBILE_OPENED)) {
            if (!rightSidebar.contains(e.target) && !e.target.classList.contains('sidebarToggleRight')) {
                closeSidebar(SIDEBAR_RIGHT_ID);
            }
        }
    }

    function handleResize() {
        if (isMobile()) {
            // Switching to mobile - create buttons if not exist
            if (!document.querySelector('.sidebarToggleLeft')) {
                createToggleButtons();
            }
        } else {
            // Switching to desktop - remove buttons and close sidebars
            removeToggleButtons();
            closeAllSidebars();
        }
    }

    // ==================== INITIALIZATION ====================

    function init() {
        if (!isMobile()) return;

        // Create toggle buttons
        createToggleButtons();

        // Setup click-outside-to-close
        document.addEventListener('click', handleClickOutside);

        // Setup resize handler
        window.addEventListener('resize', handleResize);
    }

    // ==================== AUTO-START ====================

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Handle window resize
    window.addEventListener('resize', handleResize);

})();
