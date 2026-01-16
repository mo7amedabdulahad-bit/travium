/**
 * Travian Mobile Scaling Engine
 * Implements "Adaptive Scaling" method from official Travian
 * 
 * This module:
 * 1. Detects mobile devices based on screen width (< 768px)
 * 2. Dynamically adds/removes .mobileOptimized class to body
 * 3. Calculates and applies scale factor for game containers
 * 4. Handles window resize events
 */

(function () {
    'use strict';

    // Configuration
    const MOBILE_BREAKPOINT = 768;  // Width threshold for mobile detection
    const BASE_GAME_WIDTH = 800;    // Base width for game interface scaling

    /**
     * Check if current viewport is mobile
     */
    function isMobileViewport() {
        return window.innerWidth < MOBILE_BREAKPOINT;
    }

    /**
     * Calculate scale factor for mobile view
     */
    function calculateScaleFactor() {
        return window.innerWidth / BASE_GAME_WIDTH;
    }

    /**
     * Apply or remove mobile optimizations
     */
    function updateMobileState() {
        const body = document.body;
        const isMobile = isMobileViewport();

        if (isMobile) {
            // Add mobile class
            if (!body.classList.contains('mobileOptimized')) {
                body.classList.add('mobileOptimized');
                console.log('[Mobile] Enabled mobile view');
            }

            // Calculate and set scale factor as CSS variable
            const scaleFactor = calculateScaleFactor();
            document.documentElement.style.setProperty('--mobile-scale-factor', scaleFactor);

            console.log('[Mobile] Scale factor:', scaleFactor.toFixed(3));
        } else {
            // Remove mobile class
            if (body.classList.contains('mobileOptimized')) {
                body.classList.remove('mobileOptimized');
                console.log('[Mobile] Disabled mobile view');
            }

            // Remove scale factor
            document.documentElement.style.removeProperty('--mobile-scale-factor');
        }
    }

    /**
     * Debounce function to limit resize event frequency
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function () {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Initialize mobile scaling
     */
    function init() {
        // Initial check
        updateMobileState();

        // Handle window resize with debouncing (250ms delay)
        const debouncedUpdate = debounce(updateMobileState, 250);
        window.addEventListener('resize', debouncedUpdate);

        // Also check on orientation change for mobile devices
        if (window.screen && window.screen.orientation) {
            window.screen.orientation.addEventListener('change', updateMobileState);
        }

        console.log('[Mobile] Scaling engine initialized');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }

})();
