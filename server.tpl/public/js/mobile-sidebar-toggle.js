/**
 * Travian Mobile Sidebar Toggle
 * Handles mobile sidebar interactions, swipe gestures, and layout adjustments
 */

(function () {
    'use strict';

    // Only run on mobile
    function isMobile() {
        return document.body.classList.contains('mobileOptimized');
    }

    // Initialize when mobile mode is active
    function init() {
        if (!isMobile()) {
            return;
        }

        console.log('[Mobile Sidebar] Initializing sidebar toggle system');

        setupSidebarToggles();
        setupSwipeGestures();
        setupOverlays();
        console.log('[Mobile Sidebar] Sidebar system ready');
    }

    /**
     * Setup sidebar toggle buttons
     */
    function setupSidebarToggles() {
        // Find sidebar toggle buttons if they exist
        const leftSidebarToggle = document.querySelector('.toggleSidebarBeforeContent');
        const rightSidebarToggle = document.querySelector('.toggleSidebarAfterContent');

        if (leftSidebarToggle) {
            leftSidebarToggle.addEventListener('click', function () {
                toggleSidebar('before');
            });
        }

        if (rightSidebarToggle) {
            rightSidebarToggle.addEventListener('click', function () {
                toggleSidebar('after');
            });
        }
    }

    /**
     * Setup swipe gesture detection
     */
    function setupSwipeGestures() {
        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;

        const minSwipeDistance = 50; // minimum distance for swipe
        const maxVerticalDistance = 100; // max vertical movement to still be horizontal swipe

        document.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        document.addEventListener('touchend', function (e) {
            if (!isMobile()) return;

            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;

            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            const horizontalDistance = touchEndX - touchStartX;
            const verticalDistance = Math.abs(touchEndY - touchStartY);

            // Only trigger if mostly horizontal movement
            if (verticalDistance > maxVerticalDistance) {
                return;
            }

            // Swipe right (show left sidebar)
            if (horizontalDistance > minSwipeDistance) {
                const isLeftEdge = touchStartX < 50; // Started near left edge
                if (isLeftEdge || hasSidebarOpen('before')) {
                    toggleSidebar('before');
                    console.log('[Mobile Sidebar] Swipe right detected');
                }
            }

            // Swipe left (show right sidebar)
            if (horizontalDistance < -minSwipeDistance) {
                const isRightEdge = touchStartX > (window.innerWidth - 50); // Started near right edge
                if (isRightEdge || hasSidebarOpen('after')) {
                    toggleSidebar('after');
                    console.log('[Mobile Sidebar] Swipe left detected');
                }
            }
        }
    }

    /**
     * Setup overlay clicks to close sidebars
     */
    function setupOverlays() {
        // Click outside sidebar to close it
        document.addEventListener('click', function (e) {
            if (!isMobile()) return;

            const clickedSidebar = e.target.closest('.sidebar');
            const clickedToggle = e.target.closest('[class*="toggleSidebar"]');

            // If clicked outside sidebar and not on a toggle button
            if (!clickedSidebar && !clickedToggle) {
                if (hasSidebarOpen('before') || hasSidebarOpen('after')) {
                    closeAllSidebars();
                }
            }
        });
    }

    /**
     * Toggle sidebar visibility
     * @param {string} position - 'before' or 'after'
     */
    function toggleSidebar(position) {
        const className = position === 'before' ? 'sidebarBeforeContent' : 'sidebarAfterContent';
        const otherClassName = position === 'before' ? 'sidebarAfterContent' : 'sidebarBeforeContent';

        // Close other sidebar first
        document.body.classList.remove(otherClassName);

        // Toggle this sidebar
        const isOpen = document.body.classList.contains(className);
        if (isOpen) {
            document.body.classList.remove(className);
            console.log(`[Mobile Sidebar] Closed ${position} sidebar`);
        } else {
            document.body.classList.add(className);
            console.log(`[Mobile Sidebar] Opened ${position} sidebar`);
        }

        // Add overlay if sidebar is now open
        toggleOverlay(!isOpen);
    }

    /**
     * Check if sidebar is open
     * @param {string} position - 'before' or 'after'
     * @returns {boolean}
     */
    function hasSidebarOpen(position) {
        const className = position === 'before' ? 'sidebarBeforeContent' : 'sidebarAfterContent';
        return document.body.classList.contains(className);
    }

    /**
     * Close all sidebars
     */
    function closeAllSidebars() {
        document.body.classList.remove('sidebarBeforeContent');
        document.body.classList.remove('sidebarAfterContent');
        toggleOverlay(false);
        console.log('[Mobile Sidebar] Closed all sidebars');
    }

    /**
     * Toggle overlay visibility
     * @param {boolean} show
     */
    function toggleOverlay(show) {
        let overlay = document.querySelector('.mobileSidebarOverlay');

        if (show && !overlay) {
            // Create overlay
            overlay = document.createElement('div');
            overlay.className = 'mobileSidebarOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 499;
                transition: opacity 250ms ease-out;
            `;
            document.body.appendChild(overlay);

            overlay.addEventListener('click', function () {
                closeAllSidebars();
            });

            // Fade in
            setTimeout(() => overlay.style.opacity = '1', 10);
        } else if (!show && overlay) {
            // Fade out and remove
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 250);
        }
    }

    // Re-initialize when mobileOptimized class changes
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.attributeName === 'class') {
                const wasMobile = mutation.oldValue && mutation.oldValue.includes('mobileOptimized');
                const isMobileNow = isMobile();

                if (!wasMobile && isMobileNow) {
                    // Switched to mobile
                    init();
                } else if (wasMobile && !isMobileNow) {
                    // Switched to desktop
                    closeAllSidebars();
                }
            }
        });
    });

    // Start observing body class changes
    observer.observe(document.body, {
        attributes: true,
        attributeOldValue: true,
        attributeFilter: ['class']
    });

    // Initial setup
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
