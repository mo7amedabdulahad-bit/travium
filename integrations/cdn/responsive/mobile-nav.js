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

            // Desktop sprite positions (for 70px icons)
            // Mobile buttons are 55px, so we scale: 55/70 = 0.7857
            const scale = 55 / 70;

            function scalePos(desktopPos) {
                // Extract the Y value and scale it
                const match = desktopPos.match(/left (-?\d+)px/);
                if (match) {
                    const yPos = parseInt(match[1]);
                    const scaledY = Math.round(yPos * scale);
                    return `left ${scaledY}px`;
                }
                return desktopPos;
            }

            const navConfig = {
                'dorf1': {
                    selector: '#n1',
                    normalPos: scalePos('left -1776px'),
                    activePos: scalePos('left -1532px')
                },
                'dorf2': {
                    selector: '#n2',
                    normalPos: scalePos('left -1452px'),
                    activePos: scalePos('left -1208px')
                },
                'karte': {
                    selector: '#n3',
                    normalPos: scalePos('left -420px'),
                    activePos: scalePos('left -420px')
                },
                'reports': {
                    selector: '#n5',
                    normalPos: scalePos('left -892px'),
                    activePos: scalePos('left -892px')
                },
                'messages': {
                    selector: '#n6',
                    normalPos: scalePos('left -656px'),
                    activePos: scalePos('left -656px')
                },
                'dailyQuests': {
                    selector: '#n4',
                    normalPos: scalePos('left -1128px'),
                    activePos: scalePos('left -1128px')
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

                    // Set sprite position WITH !important to override CSS
                    const pos = isActive ? config.activePos : config.normalPos;
                    btn.style.setProperty('background-position', pos, 'important');

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

            // Clone LEFT mobile sidebar = Desktop LEFT sidebar (Villages, Daily Quests, etc.)
            if (leftSidebar && sidebarBefore) {
                console.log('Cloning desktop LEFT sidebar to mobile LEFT sidebar');

                // Add server time at the top
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

                // Clone desktop left sidebar content
                const clone = sidebarBefore.cloneNode(true);
                clone.id = 'sidebarBeforeContent_mobile_left';
                leftSidebar.appendChild(clone);
            } else {
                console.warn('Cannot clone to left sidebar:', { leftSidebar, sidebarBefore });
            }

            // Clone RIGHT mobile sidebar = Desktop top-right navigation (Profile, Options, Logout)
            if (rightSidebar && outOfGame) {
                console.log('Cloning desktop outOfGame to mobile RIGHT sidebar');
                const clone = outOfGame.cloneNode(true);
                clone.id = 'outOfGame_mobile';
                // Convert horizontal icon list to vertical menu
                clone.style.cssText = 'display: block; list-style: none; margin: 0; padding: 0; width: 100%;';

                // Style each list item as a menu item
                const items = clone.querySelectorAll('li');
                items.forEach(item => {
                    item.style.cssText = 'display: block; width: 100%; float: none; margin: 0; border-bottom: 1px solid #eee;';
                    const link = item.querySelector('a, div.a');
                    if (link) {
                        link.style.cssText = 'display: block; padding: 15px 20px; width: 100%; height: auto; background: none; text-align: left;';
                        // Get the title for link text
                        const img = link.querySelector('img');
                        if (img && img.alt) {
                            link.innerHTML = img.alt;
                        }
                    }
                });

                rightSidebar.appendChild(clone);
            } else {
                console.warn('Cannot clone to right sidebar:', { rightSidebar, outOfGame });
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
