/**
 * Travium Mobile Navigation & Sidebar Handler
 * Official Travian Mobile UI Replication
 * 
 * FIXED VERSION: Uses emoji icons instead of broken sprite sheets
 */

(function () {
    'use strict';

    function initMobileUI() {
        if (!document.body || !document.body.classList.contains('mobileOptimized')) {
            return;
        }

        console.log('=== MOBILE UI INIT ===');

        // ============ GLOBAL STATE ============
        let activeSidebar = null;

        // ============ NAVIGATION ============

        function initNavigation() {
            const mobileNav = document.getElementById('mobileNavigation');
            if (!mobileNav) {
                console.warn('Mobile navigation not found');
                return;
            }

            // Simple, stable icon mapping using emoji
            const iconConfig = {
                'dorf1': { icon: '🏡', url: 'dorf1.php', label: 'Resources' },
                'dorf2': { icon: '🏛️', url: 'dorf2.php', label: 'Buildings' },
                'karte': { icon: '🗺️', url: 'karte.php', label: 'Map' },
                'reports': { icon: '📜', url: 'berichte.php', label: 'Reports' },
                'messages': { icon: '✉️', url: 'messages.php', label: 'Messages' },
                'dailyQuests': { icon: '📊', url: 'statistiken.php', label: 'Statistics' },
                'plus': { icon: '💰', url: 'payment.php', label: 'Gold' }
            };

            const currentPage = window.location.pathname.split('/').pop().split('.')[0];
            const mobileButtons = mobileNav.querySelectorAll('.mobile-nav-btn');

            console.log(`Initializing ${mobileButtons.length} nav buttons, current page: ${currentPage}`);

            mobileButtons.forEach(btn => {
                const page = btn.dataset.page;
                const config = iconConfig[page];

                if (config) {
                    // Set emoji icon - STABLE, won't move
                    btn.textContent = config.icon;
                    btn.style.cssText = `
                        font-size: 28px !important;
                        line-height: 55px !important;
                        text-align: center !important;
                        background-image: none !important;
                    `;

                    // Active state
                    if (page === currentPage || page === 'berichte' && currentPage === 'reports') {
                        btn.classList.add('active');
                    }

                    // Click handler
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (page === 'plus') {
                            if (typeof jQuery !== 'undefined') {
                                jQuery(window).trigger('startPaymentWizard', {});
                            } else {
                                window.location.href = config.url;
                            }
                        } else {
                            window.location.href = config.url;
                        }
                    });

                    console.log(`Button ${page}: ${config.icon}`);
                }
            });
        }

        // ============ SIDEBARS ============

        function openSidebar(side) {
            const sidebarId = side === 'left' ? 'mobileSidebarLeft' : 'mobileSidebarRight';
            const sidebar = document.getElementById(sidebarId);
            const backdrop = document.getElementById('mobileSidebarBackdrop');

            if (!sidebar) {
                console.warn(`Sidebar ${sidebarId} not found`);
                return;
            }

            console.log(`Opening ${side} sidebar`);

            // Close any open sidebar first
            closeSidebar();

            sidebar.classList.add('open');
            if (backdrop) backdrop.style.display = 'block';
            activeSidebar = side;
        }

        function closeSidebar() {
            const leftSidebar = document.getElementById('mobileSidebarLeft');
            const rightSidebar = document.getElementById('mobileSidebarRight');
            const backdrop = document.getElementById('mobileSidebarBackdrop');

            if (leftSidebar) leftSidebar.classList.remove('open');
            if (rightSidebar) rightSidebar.classList.remove('open');
            if (backdrop) backdrop.style.display = 'none';
            activeSidebar = null;
        }

        window.closeMobileSidebar = closeSidebar;
        window.openMobileSidebar = openSidebar;

        // ============ SIDEBAR CONTENT CLONING ============

        function cloneSidebarContent() {
            const leftSidebar = document.getElementById('mobileSidebarLeft');
            const rightSidebar = document.getElementById('mobileSidebarRight');
            const sidebarBefore = document.getElementById('sidebarBeforeContent');

            console.log('Cloning sidebar content...');
            console.log('sidebarBefore:', !!sidebarBefore);

            // ========================================
            // BOTH SIDEBARS clone from sidebarBeforeContent (desktop left sidebar)
            // Based on official Travian screenshots:
            // - "Left side bar.png" = Info Box, Link List
            // - "Right side bar.png" = Village info, Villages, Hero, Tasks
            // ========================================

            if (sidebarBefore) {
                // LEFT SIDEBAR = Full sidebar content
                if (leftSidebar) {
                    console.log('LEFT sidebar: Cloning sidebarBeforeContent');
                    const clone = sidebarBefore.cloneNode(true);
                    clone.id = 'sidebarBeforeContent_mobile_left';
                    clone.style.cssText = 'display: block; width: 100%; padding: 10px; box-sizing: border-box;';
                    leftSidebar.appendChild(clone);
                }

                // RIGHT SIDEBAR = Same content (full sidebar)
                if (rightSidebar) {
                    console.log('RIGHT sidebar: Cloning sidebarBeforeContent');
                    const clone = sidebarBefore.cloneNode(true);
                    clone.id = 'sidebarBeforeContent_mobile_right';
                    clone.style.cssText = 'display: block; width: 100%; padding: 10px; box-sizing: border-box;';
                    rightSidebar.appendChild(clone);
                }
            } else {
                console.warn('sidebarBeforeContent not found!');
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
        let isEdgeSwipe = false;

        const SWIPE_THRESHOLD = 60;
        const EDGE_THRESHOLD = 40;
        const VERTICAL_TOLERANCE = 100;

        function handleSwipe() {
            const deltaX = touchEndX - touchStartX;
            const deltaY = Math.abs(touchEndY - touchStartY);
            const screenWidth = window.innerWidth;

            console.log(`Swipe: startX=${touchStartX}, deltaX=${deltaX}, deltaY=${deltaY}, screenWidth=${screenWidth}`);

            // Ignore if too much vertical movement
            if (deltaY > VERTICAL_TOLERANCE) {
                console.log('Swipe ignored: too vertical');
                return;
            }

            // Swipe RIGHT from left edge → open LEFT sidebar (menu)
            if (deltaX > SWIPE_THRESHOLD && touchStartX < EDGE_THRESHOLD) {
                console.log('>>> OPENING LEFT SIDEBAR');
                openSidebar('left');
            }
            // Swipe LEFT from right edge → open RIGHT sidebar (village info)
            else if (deltaX < -SWIPE_THRESHOLD && touchStartX > (screenWidth - EDGE_THRESHOLD)) {
                console.log('>>> OPENING RIGHT SIDEBAR');
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

        // Touchstart - detect edge swipes
        document.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            const screenWidth = window.innerWidth;

            // Detect if starting from edge
            isEdgeSwipe = (touchStartX < EDGE_THRESHOLD) || (touchStartX > screenWidth - EDGE_THRESHOLD);

            console.log(`Touch start: x=${touchStartX}, isEdge=${isEdgeSwipe}`);
        }, { passive: true });

        // Touchmove - BLOCK browser back navigation on edge swipes
        document.addEventListener('touchmove', function (e) {
            if (isEdgeSwipe) {
                // Prevent browser's back/forward gesture
                e.preventDefault();
            }
        }, { passive: false });

        // Touchend - process the swipe
        document.addEventListener('touchend', function (e) {
            touchEndX = e.changedTouches[0].clientX;
            touchEndY = e.changedTouches[0].clientY;
            handleSwipe();
            isEdgeSwipe = false;
        }, { passive: true });

        // ============ RUN ALL INITIALIZATIONS ============

        initNavigation();
        cloneSidebarContent();

        console.log('=== MOBILE UI INIT COMPLETE ===');
    }

    // ============ WAIT FOR DOM THEN RUN ============

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileUI);
    } else {
        setTimeout(initMobileUI, 100);
    }

})();
