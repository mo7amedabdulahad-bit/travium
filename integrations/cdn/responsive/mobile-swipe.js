/**
 * Travium Mobile - Touch Swipe Detection for Sidebars
 * 
 * Detects left/right swipe gestures to toggle sidebars
 * - Swipe RIGHT (from left edge) → Show left sidebar
 * - Swipe LEFT (from right edge) → Show right sidebar
 * - Tap outside sidebar → Close
 */

(function () {
    'use strict';

    // Only run on mobile
    if (!document.body.classList.contains('mobileOptimized')) {
        return;
    }

    // Touch tracking variables
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;
    let touchStartTime = 0;

    // Configuration
    const SWIPE_THRESHOLD = 100; // Minimum distance for swipe (px)
    const EDGE_THRESHOLD = 50;   // Distance from edge to trigger (px)
    const TIME_THRESHOLD = 500;   // Maximum time for swipe (ms)
    const VERTICAL_TOLERANCE = 80; // Max vertical movement allowed

    // Get or create toggle checkboxes
    function ensureToggles() {
        if (!document.getElementById('leftSidebarToggle')) {
            const leftToggle = document.createElement('input');
            leftToggle.type = 'checkbox';
            leftToggle.id = 'leftSidebarToggle';
            leftToggle.style.display = 'none';
            document.body.insertBefore(leftToggle, document.body.firstChild);
        }

        if (!document.getElementById('rightSidebarToggle')) {
            const rightToggle = document.createElement('input');
            rightToggle.type = 'checkbox';
            rightToggle.id = 'rightSidebarToggle';
            rightToggle.style.display = 'none';
            document.body.insertBefore(rightToggle, document.body.firstChild);
        }

        // Create overlay for backdrop
        if (!document.querySelector('.sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.style.display = 'none';
            overlay.addEventListener('click', closeSidebars);
            document.body.appendChild(overlay);
        }
    }

    // Close all sidebars
    function closeSidebars() {
        const leftToggle = document.getElementById('leftSidebarToggle');
        const rightToggle = document.getElementById('rightSidebarToggle');

        if (leftToggle) leftToggle.checked = false;
        if (rightToggle) rightToggle.checked = false;
    }

    // Handle touch start
    function handleTouchStart(e) {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
        touchStartTime = Date.now();
    }

    // Handle touch end
    function handleTouchEnd(e) {
        touchEndX = e.changedTouches[0].screenX;
        touchEndY = e.changedTouches[0].screenY;

        handleSwipeGesture();
    }

    // Analyze swipe gesture
    function handleSwipeGesture() {
        const swipeTime = Date.now() - touchStartTime;
        const swipeDistanceX = touchEndX - touchStartX;
        const swipeDistanceY = Math.abs(touchEndY - touchStartY);
        const screenWidth = window.innerWidth;

        // Check if swipe is fast enough
        if (swipeTime > TIME_THRESHOLD) {
            return;
        }

        // Check if swipe is horizontal enough
        if (swipeDistanceY > VERTICAL_TOLERANCE) {
            return;
        }

        // SWIPE RIGHT (show left sidebar)
        if (swipeDistanceX > SWIPE_THRESHOLD && touchStartX < EDGE_THRESHOLD) {
            const leftToggle = document.getElementById('leftSidebarToggle');
            if (leftToggle) {
                leftToggle.checked = true;
                // Close right sidebar if open
                const rightToggle = document.getElementById('rightSidebarToggle');
                if (rightToggle) rightToggle.checked = false;
            }
        }

        // SWIPE LEFT (show right sidebar)
        else if (swipeDistanceX < -SWIPE_THRESHOLD && touchStartX > (screenWidth - EDGE_THRESHOLD)) {
            const rightToggle = document.getElementById('rightSidebarToggle');
            if (rightToggle) {
                rightToggle.checked = true;
                // Close left sidebar if open
                const leftToggle = document.getElementById('leftSidebarToggle');
                if (leftToggle) leftToggle.checked = false;
            }
        }

        // SWIPE LEFT (close left sidebar if open)
        else if (swipeDistanceX < -SWIPE_THRESHOLD) {
            const leftToggle = document.getElementById('leftSidebarToggle');
            if (leftToggle && leftToggle.checked) {
                leftToggle.checked = false;
            }
        }

        // SWIPE RIGHT (close right sidebar if open)
        else if (swipeDistanceX > SWIPE_THRESHOLD) {
            const rightToggle = document.getElementById('rightSidebarToggle');
            if (rightToggle && rightToggle.checked) {
                rightToggle.checked = false;
            }
        }
    }

    // Initialize on DOM ready
    function init() {
        // Ensure toggle elements exist
        ensureToggles();

        // Add touch event listeners
        document.addEventListener('touchstart', handleTouchStart, { passive: true });
        document.addEventListener('touchend', handleTouchEnd, { passive: true });

        console.log('✅ Mobile swipe detection initialized');
    }

    // Run init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
