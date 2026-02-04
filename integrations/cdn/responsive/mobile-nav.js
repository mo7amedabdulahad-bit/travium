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

        // ============ NAVIGATION - USE COMPUTED STYLES FROM DESKTOP ============

        function initNavigation() {
            const mobileNav = document.getElementById('mobileNavigation');
            const desktopNav = document.getElementById('navigation');

            if (!mobileNav || !desktopNav) {
                console.warn('Navigation elements not found');
                return;
            }

            // Map mobile buttons to desktop navigation items
            const navMap = {
                'dorf1': '#n1 a',
                'dorf2': '#n2 a',
                'karte': '#n3 a',
                'reports': '#n5 a',
                'messages': '#n6 a',
                'dailyQuests': '#n4 a'  // Daily Quests uses Statistics (#n4)
            };

            // Get mobile buttons
            const mobileButtons = mobileNav.querySelectorAll('.mobile-nav-btn');
            const currentPage = window.location.pathname.split('/').pop().split('.')[0];

            mobileButtons.forEach(btn => {
                const page = btn.dataset.page;

                if (navMap[page]) {
                    // Find desktop nav button
                    const desktopBtn = desktopNav.querySelector(navMap[page]);

                    if (desktopBtn) {
                        // Get computed styles from desktop button
                        const computedStyle = window.getComputedStyle(desktopBtn);
                        const bgImage = computedStyle.backgroundImage;
                        const bgPosition = computedStyle.backgroundPosition;

                        // Apply to mobile button
                        if (bgImage && bgImage !== 'none') {
                            btn.style.backgroundImage = bgImage;
                            btn.style.backgroundPosition = bgPosition;
                            btn.style.backgroundSize = 'contain';
                            btn.style.backgroundRepeat = 'no-repeat';
                        }

                        // Get href for navigation
                        const href = desktopBtn.getAttribute('href');

                        // Add click handler
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            if (href) {
                                window.location.href = href;
                            }
                        });

                        // Highlight if this is the active page
                        if (page === currentPage || desktopBtn.classList.contains('active')) {
                            btn.classList.add('active');
                        }
                    }
                }
                // Plus/Gold button
                else if (page === 'plus') {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        if (typeof jQuery !== 'undefined') {
                            jQuery(window).trigger('startPaymentWizard', {});
                        } else {
                            window.location.href = 'payment.php';
                        }
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

        // ============ SIDEBAR CONTENT - CLONE (NOT MOVE) ============

        function cloneSidebarContent() {
            const leftSidebar = document.getElementById('mobileSidebarLeft');
            const sidebarBefore = document.getElementById('sidebarBeforeContent');

            if (leftSidebar && sidebarBefore) {
                // Add server time at the top of left sidebar
                const servertime = document.getElementById('servertime');
                if (servertime) {
                    const servertimeClone = servertime.cloneNode(true);
                    servertimeClone.id = 'servertime_mobile';
                    servertimeClone.style.cssText = 'display: block; text-align: center; padding: 10px; background: rgba(255,255,255,0.1); margin-bottom: 15px; color: #fff; font-size: 14px;';
                    const header = leftSidebar.querySelector('.mobile-sidebar-header');
                    if (header) {
                        header.after(servertimeClone);
                    } else {
                        leftSidebar.insertBefore(servertimeClone, leftSidebar.firstChild);
                    }
                }

                // CLONE sidebar content (not move) so desktop keeps original
                const clone = sidebarBefore.cloneNode(true);
                clone.id = 'sidebarBeforeContent_mobile'; // Prevent ID collision
                leftSidebar.appendChild(clone);
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
        cloneSidebarContent();
    }

    // ============ WAIT FOR DOM THEN RUN ============

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileUI);
    } else {
        setTimeout(initMobileUI, 100);
    }

})();
