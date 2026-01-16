/**
 * Mobile Sidebar Toggle System
 * Manages collapsible sidebars with floating toggle buttons
 */

(function () {
    'use strict';

    const MOBILE_BREAKPOINT = 620;

    // Utility functions
    function isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    function $(selector) {
        return document.querySelector(selector);
    }

    // Create UI elements
    function createSidebarUI() {
        if (!isMobile()) return;

        // Don't create if already exists
        if ($('.sidebarToggleLeft')) return;

        // Create overlay backdrop
        const overlay = document.createElement('div');
        overlay.className = 'sidebarOverlay';
        overlay.addEventListener('click', closeAllSidebars);
        document.body.appendChild(overlay);

        // Create left sidebar toggle button
        const leftToggle = document.createElement('div');
        leftToggle.className = 'sidebarToggle sidebarToggleLeft';
        leftToggle.innerHTML = '»';
        leftToggle.setAttribute('aria-label', 'Toggle left sidebar');
        leftToggle.setAttribute('role', 'button');
        leftToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidebar('left');
        });
        document.body.appendChild(leftToggle);

        // Create right sidebar toggle button
        const rightToggle = document.createElement('div');
        rightToggle.className = 'sidebarToggle sidebarToggleRight';
        rightToggle.innerHTML = '«';
        rightToggle.setAttribute('aria-label', 'Toggle right sidebar');
        rightToggle.setAttribute('role', 'button');
        rightToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidebar('right');
        });
        document.body.appendChild(rightToggle);

        console.log('[Mobile Sidebars] UI elements created');
    }

    // Toggle sidebar open/close
    function toggleSidebar(side) {
        const leftSidebar = $('#sidebarBeforeContent');
        const rightSidebar = $('#sidebarAfterContent');
        const overlay = $('.sidebarOverlay');

        if (!leftSidebar || !rightSidebar || !overlay) {
            console.warn('[Mobile Sidebars] Sidebar elements not found');
            return;
        }

        if (side === 'left') {
            const isOpen = leftSidebar.classList.contains('open');
            closeAllSidebars();

            if (!isOpen) {
                leftSidebar.classList.add('open');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden'; // Prevent background scroll
            }
        } else if (side === 'right') {
            const isOpen = rightSidebar.classList.contains('open');
            closeAllSidebars();

            if (!isOpen) {
                rightSidebar.classList.add('open');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }
    }

    // Close all sidebars
    function closeAllSidebars() {
        const leftSidebar = $('#sidebarBeforeContent');
        const rightSidebar = $('#sidebarAfterContent');
        const overlay = $('.sidebarOverlay');

        if (leftSidebar) leftSidebar.classList.remove('open');
        if (rightSidebar) rightSidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');

        document.body.style.overflow = ''; // Restore scroll
    }

    // Remove all mobile UI elements
    function removeSidebarUI() {
        const leftToggle = $('.sidebarToggleLeft');
        const rightToggle = $('.sidebarToggleRight');
        const overlay = $('.sidebarOverlay');

        if (leftToggle) leftToggle.remove();
        if (rightToggle) rightToggle.remove();
        if (overlay) overlay.remove();

        // Ensure sidebars are reset
        closeAllSidebars();
    }

    // Handle window resize
    function handleResize() {
        if (isMobile()) {
            // Switching to mobile - create UI if not exists
            if (!$('.sidebarToggleLeft')) {
                createSidebarUI();
            }
        } else {
            // Switching to desktop - remove mobile UI
            removeSidebarUI();
        }
    }

    // Debounce resize handler
    let resizeTimeout;
    function debouncedResize() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(handleResize, 150);
    }

    // Initialize on DOM ready
    function init() {
        if (isMobile()) {
            createSidebarUI();
        }

        // Setup resize listener
        window.addEventListener('resize', debouncedResize);

        console.log('[Mobile Sidebars] Initialized');
    }

    // Auto-start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose API for external use (optional)
    window.MobileSidebars = {
        toggle: toggleSidebar,
        close: closeAllSidebars,
        isMobile: isMobile
    };

})();
