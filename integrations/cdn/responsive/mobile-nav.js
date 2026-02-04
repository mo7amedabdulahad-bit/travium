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

            // Desktop navigation sprite positions
            const navConfig = {
                'dorf1': {
                    selector: '#n1',
                    normalPos: 'left -1776px',
                    activePos: 'left -1532px'
                },
                'dorf2': {
                    selector: '#n2',
                    normalPos: 'left -1452px',
                    activePos: 'left -1208px'
                },
                'karte': {
                    selector: '#n3',
                    normalPos: 'left -420px',
                    activePos: 'left -420px'
                },
                'reports': {
                    selector: '#n5',
                    normalPos: 'left -892px',
                    activePos: 'left -892px'
                },
                'messages': {
                    selector: '#n6',
                    normalPos: 'left -656px',
                    activePos: 'left -656px'
                },
                'dailyQuests': {
                    selector: '#n4',
                    normalPos: 'left -1128px',
                    activePos: 'left -1128px'
                }
            };

            const currentPage = window.location.pathname.split('/').pop().split('.')[0];
            const mobileButtons = mobileNav.querySelectorAll('.mobile-nav-btn');

            mobileButtons.forEach(btn => {
                const page = btn.dataset.page;

                if (navConfig[page]) {
                    const config = navConfig[page];
                    const desktopBtn = desktopNav.querySelector(config.selector + ' a');

                    // CSS already sets the background-image, we only set the position
                    const isActive = (page === currentPage) ||
                        (desktopBtn && desktopBtn.classList.contains('active'));

                    // Set sprite position (CSS already has the image)
                    btn.style.backgroundPosition = (isActive ? config.activePos : config.normalPos) + ' !important';

                    // Mark as active
                    if (isActive) {
                        btn.classList.add('active');
                    }

                    // Get href for navigation
                    const href = desktopBtn ? desktopBtn.getAttribute('href') : `${page}.php`;

                    // Add click handler
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (href) {
                            window.location.href = href;
                        }
                    });
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
            const rightSidebar = document.getElementById('mobileSidebarRight');
            const sidebarBefore = document.getElementById('sidebarBeforeContent');
            const outOfGame = document.getElementById('outOfGame');

            // Clone LEFT sidebar content (info boxes)
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

            // Clone RIGHT sidebar content (top-right navigation: Profile, Options, Logout, etc.)
            if (rightSidebar && outOfGame) {
                const clone = outOfGame.cloneNode(true);
                clone.id = 'outOfGame_mobile'; // Prevent ID collision
                // Style the cloned navigation for mobile
                clone.style.cssText = 'display: block !important; position: static !important; width: 100% !important;';
                rightSidebar.appendChild(clone);

                // Make links in cloned navigation work as normal links (not mobile-specific)
                const links = clone.querySelectorAll('a');
                links.forEach(link => {
                    link.style.cssText = 'display: block; padding: 15px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #eee;';
                });
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
