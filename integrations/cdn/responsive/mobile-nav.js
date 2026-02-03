/**
 * Travium Mobile Navigation & Sidebar Handler
 * Official Travian Mobile UI Replication
 */

(function () {
    'use strict';

    // Main initialization function
    function initMobileUI() {
        // Check if mobile optimized
        if (!document.body || !document.body.classList.contains('mobileOptimized')) {
            return;
        }

        // ============ GLOBAL STATE ============
        let activeSidebar = null;

        // ============ NAVIGATION - USE DESKTOP ICON CLASSES ============

        function initNavigation() {
            const mobileNav = document.getElementById('mobileNavigation');
            const desktopNav = document.getElementById('navigation');

            if (!mobileNav || !desktopNav) return;

            // Map mobile buttons to desktop navigation items
            const navMap = {
                'dorf1': { selector: '#n1, .villageResources', class: 'villageResources' },
                'dorf2': { selector: '#n2, .villageBuildings', class: 'villageBuildings' },
                'karte': { selector: '#n3, .map', class: 'map' },
                'reports': { selector: '#n5, .reports', class: 'reports' },
                'messages': { selector: '#n6, .messages', class: 'messages' }
            };

            // Get mobile buttons
            const mobileButtons = mobileNav.querySelectorAll('.mobile-nav-btn');

            mobileButtons.forEach(btn => {
                const page = btn.dataset.page;

                if (navMap[page]) {
                    // Find desktop nav item
                    const desktopItem = desktopNav.querySelector(navMap[page].selector);
                    const desktopLink = desktopItem ? desktopItem.querySelector('a') : null;

                    if (desktopLink) {
                        // Copy the icon class to the button
                        const iconClass = navMap[page].class;
                        btn.classList.add(iconClass);

                        // Get the desktop link's href
                        const href = desktopLink.getAttribute('href');

                        // Add click handler
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            if (href) {
                                window.location.href = href;
                            }
                        });

                        // Highlight if active
                        if (desktopLink.classList.contains('active')) {
                            btn.classList.add('active');
                        }
                    }
                }
                // Special buttons
                else if (page === 'dailyQuests') {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        window.location.href = 'daily_quests.php';
                    });
                }
                else if (page === 'plus') {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        window.location.href = 'payment.php';
                    });
                }
            });
        }

        // ============ SIDEBAR FUNCTIONS ============

        function openSidebar(side) {
            closeSidebar();

            const sidebarId = side === 'left' ? 'mobileSidebarLeft' : 'mobileSidebarRight';
            const sidebar = document.getElementById(sidebarId);
            const backdrop = document.getElementById('mobileSidebarBackdrop');

            if (!sidebar || !backdrop) return;

            sidebar.classList.add('open');
            backdrop.classList.add('active');
            backdrop.style.display = 'block';
            activeSidebar = side;

            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            if (!activeSidebar) return;

            const sidebarId = activeSidebar === 'left' ? 'mobileSidebarLeft' : 'mobileSidebarRight';
            const sidebar = document.getElementById(sidebarId);
            const backdrop = document.getElementById('mobileSidebarBackdrop');

            if (sidebar) sidebar.classList.remove('open');
            if (backdrop) {
                backdrop.classList.remove('active');
                backdrop.style.display = 'none';
            }

            document.body.style.overflow = '';
            activeSidebar = null;
        }

        // Export to window
        window.closeMobileSidebar = closeSidebar;
        window.openMobileSidebar = openSidebar;

        // ============ SIDEBAR CONTENT - MOVE EXISTING ============

        function moveSidebarContent() {
            const leftSidebar = document.getElementById('mobileSidebarLeft');
            const sidebarBefore = document.getElementById('sidebarBeforeContent');

            if (leftSidebar && sidebarBefore) {
                // MOVE it to hide from main screen
                leftSidebar.appendChild(sidebarBefore);
            }

            // Backdrop listener
            const backdrop = document.getElementById('mobileSidebarBackdrop');
            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }

            // Close button listeners
            const closeButtons = document.querySelectorAll('.mobile-sidebar-close');
            closeButtons.forEach(btn => {
                btn.addEventListener('click', closeSidebar);
            });
        }

        // ============ SWIPE GESTURES ============

        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;

        const SWIPE_THRESHOLD = 80;
        const EDGE_THRESHOLD = 50;
        const VERTICAL_TOLERANCE = 100;

        function handleSwipe() {
            const deltaX = touchEndX - touchStartX;
            const deltaY = Math.abs(touchEndY - touchStartY);
            const screenWidth = window.innerWidth;

            // Ignore if too much vertical movement
            if (deltaY > VERTICAL_TOLERANCE) return;

            // Swipe RIGHT from left edge → open left sidebar
            if (deltaX > SWIPE_THRESHOLD && touchStartX < EDGE_THRESHOLD) {
                openSidebar('left');
            }
            // Swipe LEFT from right edge → open right sidebar
            else if (deltaX < -SWIPE_THRESHOLD && touchStartX > (screenWidth - EDGE_THRESHOLD)) {
                openSidebar('right');
            }
            // Swipe LEFT to close left sidebar
            else if (deltaX < -SWIPE_THRESHOLD && activeSidebar === 'left') {
                closeSidebar();
            }
            // Swipe RIGHT to close right sidebar
            else if (deltaX > SWIPE_THRESHOLD && activeSidebar === 'right') {
                closeSidebar();
            }
        }

        document.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        document.addEventListener('touchend', function (e) {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        }, { passive: true });

        // ============ RUN ALL INITIALIZATIONS ============

        initNavigation();
        moveSidebarContent();
    }

    // ============ WAIT FOR DOM THEN RUN ============

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileUI);
    } else {
        setTimeout(initMobileUI, 100);
    }

})();
