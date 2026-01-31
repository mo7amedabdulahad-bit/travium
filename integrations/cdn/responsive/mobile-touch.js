/**
 * MOBILE TOUCH HANDLER - Official Travian Swipe Detection
 * Based on: travian-live-recording-1769893639257.json
 * 
 * Functionality:
 * - Detects swipe from left edge (< 50px) to open left sidebar
 * - Detects swipe from right edge (< 50px) to open right sidebar
 * - Adds .mobileOpened class to sidebar
 * - Adds .withSidebarBeforeContent or .withSidebarAfterContent to #reactDialogWrapper
 * - Closes sidebar on backdrop click or swipe back
 */

(function () {
    'use strict';

    // Configuration
    const EDGE_THRESHOLD = 50;        // Distance from edge to detect swipe start
    const SWIPE_THRESHOLD = 300;      // Minimum swipe distance to trigger
    const VIEWPORT_BREAKPOINT = 620;  // Only activate on mobile viewports

    // State
    let touchStartX = 0;
    let touchStartY = 0;
    let touchCurrentX = 0;
    let touchCurrentY = 0;
    let isSwiping = false;
    let swipeDirection = null; // 'left' or 'right'

    // Elements
    const leftSidebar = document.querySelector('#sidebarBeforeContent');
    const rightSidebar = document.querySelector('#sidebarAfterContent');
    const dialogWrapper = document.querySelector('#reactDialogWrapper');

    /**
     * Check if we're in mobile viewport
     */
    function isMobileViewport() {
        return window.innerWidth <= VIEWPORT_BREAKPOINT;
    }

    /**
     * Open left sidebar
     */
    function openLeftSidebar() {
        if (leftSidebar && dialogWrapper) {
            leftSidebar.classList.add('mobileOpened');
            dialogWrapper.classList.add('withSidebarBeforeContent');
            console.log('📱 Left sidebar opened');
        }
    }

    /**
     * Open right sidebar
     */
    function openRightSidebar() {
        if (rightSidebar && dialogWrapper) {
            rightSidebar.classList.add('mobileOpened');
            dialogWrapper.classList.add('withSidebarAfterContent');
            console.log('📱 Right sidebar opened');
        }
    }

    /**
     * Close all sidebars
     */
    function closeAllSidebars() {
        if (leftSidebar) {
            leftSidebar.classList.remove('mobileOpened');
        }
        if (rightSidebar) {
            rightSidebar.classList.remove('mobileOpened');
        }
        if (dialogWrapper) {
            dialogWrapper.classList.remove('withSidebarBeforeContent');
            dialogWrapper.classList.remove('withSidebarAfterContent');
        }
        console.log('📱 All sidebars closed');
    }

    /**
     * Handle touch start
     */
    function handleTouchStart(e) {
        if (!isMobileViewport()) return;

        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        touchCurrentX = touchStartX;
        touchCurrentY = touchStartY;

        // Check if starting from edge
        if (touchStartX < EDGE_THRESHOLD) {
            swipeDirection = 'right'; // Swipe from left edge
            isSwiping = true;
            console.log('📱 Swipe started from left edge');
        } else if (touchStartX > window.innerWidth - EDGE_THRESHOLD) {
            swipeDirection = 'left'; // Swipe from right edge
            isSwiping = true;
            console.log('📱 Swipe started from right edge');
        }
    }

    /**
     * Handle touch move
     */
    function handleTouchMove(e) {
        if (!isMobileViewport() || !isSwiping) return;

        touchCurrentX = e.touches[0].clientX;
        touchCurrentY = e.touches[0].clientY;

        const deltaX = touchCurrentX - touchStartX;
        const deltaY = touchCurrentY - touchStartY;

        // Check if horizontal swipe (not vertical scroll)
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
            // Prevent page scroll during swipe
            e.preventDefault();

            // Check swipe distance and direction
            if (swipeDirection === 'right' && deltaX > SWIPE_THRESHOLD) {
                // Swiped right from left edge - open left sidebar
                openLeftSidebar();
                isSwiping = false;
            } else if (swipeDirection === 'left' && deltaX < -SWIPE_THRESHOLD) {
                // Swiped left from right edge - open right sidebar
                openRightSidebar();
                isSwiping = false;
            }
        }
    }

    /**
     * Handle touch end
     */
    function handleTouchEnd(e) {
        isSwiping = false;
        swipeDirection = null;
    }

    /**
     * Handle backdrop click to close sidebars
     */
    function handleBackdropClick(e) {
        if (!isMobileViewport()) return;

        // Only close if clicking on backdrop itself, not sidebar content
        if (e.target === dialogWrapper ||
            (dialogWrapper && dialogWrapper.classList.contains('withSidebarBeforeContent') && e.target.closest('#sidebarBeforeContent') === null) ||
            (dialogWrapper && dialogWrapper.classList.contains('withSidebarAfterContent') && e.target.closest('#sidebarAfterContent') === null)) {
            closeAllSidebars();
        }
    }

    /**
     * Detect swipe to close sidebar
     */
    function handleSidebarSwipeClose(e) {
        if (!isMobileViewport()) return;

        const target = e.target.closest('#sidebarBeforeContent, #sidebarAfterContent');
        if (!target) return;

        const startX = e.touches[0].clientX;
        let closeSwipe = false;

        const moveHandler = (moveEvent) => {
            const currentX = moveEvent.touches[0].clientX;
            const delta = currentX - startX;

            // Left sidebar: swipe left to close
            if (target.id === 'sidebarBeforeContent' && delta < -SWIPE_THRESHOLD) {
                closeSwipe = true;
            }
            // Right sidebar: swipe right to close
            else if (target.id === 'sidebarAfterContent' && delta > SWIPE_THRESHOLD) {
                closeSwipe = true;
            }
        };

        const endHandler = () => {
            if (closeSwipe) {
                closeAllSidebars();
            }
            document.removeEventListener('touchmove', moveHandler);
            document.removeEventListener('touchend', endHandler);
        };

        document.addEventListener('touchmove', moveHandler, { passive: false });
        document.addEventListener('touchend', endHandler);
    }

    /**
     * Initialize event listeners
     */
    function init() {
        // Touch events for swipe detection
        document.addEventListener('touchstart', handleTouchStart, { passive: true });
        document.addEventListener('touchmove', handleTouchMove, { passive: false });
        document.addEventListener('touchend', handleTouchEnd, { passive: true });

        // Backdrop click to close
        if (dialogWrapper) {
            dialogWrapper.addEventListener('click', handleBackdropClick);
        }

        // Swipe on sidebar to close
        if (leftSidebar) {
            leftSidebar.addEventListener('touchstart', handleSidebarSwipeClose, { passive: true });
        }
        if (rightSidebar) {
            rightSidebar.addEventListener('touchstart', handleSidebarSwipeClose, { passive: true });
        }

        console.log('📱 Mobile touch handler initialized');
        console.log(`   Edge threshold: ${EDGE_THRESHOLD}px`);
        console.log(`   Swipe threshold: ${SWIPE_THRESHOLD}px`);
        console.log(`   Viewport breakpoint: ${VIEWPORT_BREAKPOINT}px`);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
