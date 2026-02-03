/**
 * Travium Mobile Navigation & Sidebar Handler
 * Official Travian Mobile UI Replication
 * 
 * MOVES existing desktop elements to mobile positions
 */

(function () {
    'use strict';

    // Main initialization function
    function initMobileUI() {
        // Check if mobile optimized
        if (!document.body || !document.body.classList.contains('mobileOptimized')) {
            console.log('Not mobile optimized, skipping');
            return;
        }

        console.log('✅ Starting Mobile UI Init...');

        // ============ GLOBAL STATE ============
        let activeSidebar = null;

        // ============ NAVIGATION - MOVE EXISTING ICONS ============

        function initNavigation() {
            const mobileNav = document.getElementById('mobileNavigation');
            const desktopNav = document.getElementById('navigation');

            if (!mobileNav) {
                console.warn('Mobile navigation not found');
                return;
            }

            console.log('Initializing navigation...');

            // Map page names to desktop navigation selectors
            const navMap = {
                'dorf1': 'li.villageResources a, #n1 a',
                'dorf2': 'li.villageBuildings a, #n2 a',
                'karte': 'li.map a, #n3 a',
                'reports': 'li.reports a, #n5 a',
                'messages': 'li.messages a, #n6 a'
            };

            // Get mobile buttons
            const mobileButtons = mobileNav.querySelectorAll('.mobile-nav-btn');

            mobileButtons.forEach(btn => {
                const page = btn.dataset.page;

                // Find corresponding desktop button
                if (navMap[page] && desktopNav) {
                    const desktopBtn = desktopNav.querySelector(navMap[page]);

                    if (desktopBtn) {
                        // Copy background image from desktop button
                        const style = window.getComputedStyle(desktopBtn);
                        const bgImage = style.backgroundImage;

                        if (bgImage && bgImage !== 'none') {
                            btn.style.backgroundImage = bgImage;
                            btn.style.backgroundSize = '70%';
                            btn.style.backgroundPosition = 'center';
                            btn.style.backgroundRepeat = 'no-repeat';
                        }

                        // Get href for navigation
                        const href = desktopBtn.getAttribute('href');

                        // Add click handler
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('Navigating to:', href);
                            if (href) {
                                window.location.href = href;
                            }
                        });

                        // Highlight if active
                        if (desktopBtn.classList.contains('active')) {
                            btn.classList.add('active');
                        }

                        console.log('Button', page, 'configured with href:', href);
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

            console.log('✅ Navigation buttons initialized');
        }

        // ============ SIDEBAR FUNCTIONS ============

        function openSidebar(side) {
            closeSidebar();

            const sidebarId = side === 'left' ? 'mobileSidebarLeft' : 'mobileSidebarRight';
            const sidebar = document.getElementById(sidebarId);
            const backdrop = document.getElementById('mobileSidebarBackdrop');

            if (!sidebar || !backdrop) {
                console.warn('Sidebar or backdrop not found:', sidebarId);
                return;
            }

            sidebar.classList.add('open');
            backdrop.classList.add('active');
            backdrop.style.display = 'block';
            activeSidebar = side;

            document.body.style.overflow = 'hidden';

            console.log('✅ Opened', side, 'sidebar');
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

            console.log('✅ Closed sidebar');
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
                console.log('✅ Moved left sidebar content');
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

        // ============ HERO AVATAR - TAP TO OPEN SIDEBAR ============

        function initHeroAvatar() {
            const mobileHero = document.getElementById('mobileHeroAvatar');
            if (!mobileHero) {
                console.warn('Mobile hero avatar not found');
                return;
            }

            // Click to open left sidebar
            mobileHero.addEventListener('click', function () {
                console.log('Hero clicked, opening left sidebar');
                openSidebar('left');
            });

            // Try to find existing hero image
            const heroImg = document.querySelector('.heroImageBg img, .heroImage img, #playerBox img');

            if (heroImg) {
                const clone = heroImg.cloneNode(true);
                clone.style.width = '100%';
                clone.style.height = '100%';
                clone.style.objectFit = 'cover';
                mobileHero.appendChild(clone);
                console.log('✅ Hero image cloned');
            } else {
                // Placeholder
                mobileHero.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;background:rgba(0,0,0,0.3);border-radius:50%;">⚔️</div>';
                console.log('✅ Hero placeholder created');
            }

            console.log('✅ Hero avatar initialized');
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

            console.log('Swipe detected:', deltaX, 'px');

            // Swipe RIGHT from left edge → open left sidebar
            if (deltaX > SWIPE_THRESHOLD && touchStartX < EDGE_THRESHOLD) {
                console.log('Opening left sidebar via swipe');
                openSidebar('left');
            }
            // Swipe LEFT from right edge → open right sidebar
            else if (deltaX < -SWIPE_THRESHOLD && touchStartX > (screenWidth - EDGE_THRESHOLD)) {
                console.log('Opening right sidebar via swipe');
                openSidebar('right');
            }
            // Swipe LEFT to close left sidebar
            else if (deltaX < -SWIPE_THRESHOLD && activeSidebar === 'left') {
                console.log('Closing left sidebar via swipe');
                closeSidebar();
            }
            // Swipe RIGHT to close right sidebar
            else if (deltaX > SWIPE_THRESHOLD && activeSidebar === 'right') {
                console.log('Closing right sidebar via swipe');
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

        console.log('✅ Swipe gestures initialized');

        // ============ RUN ALL INITIALIZATIONS ============

        initNavigation();
        moveSidebarContent();
        initHeroAvatar();

        console.log('✅✅✅ Mobile UI Fully Initialized!');
    }

    // ============ WAIT FOR DOM THEN RUN ============

    if (document.readyState === 'loading') {
        console.log('Waiting for DOM...');
        document.addEventListener('DOMContentLoaded', initMobileUI);
    } else {
        console.log('DOM already loaded, initializing immediately');
        // Wait a bit for everything to settle
        setTimeout(initMobileUI, 100);
    }

})();
