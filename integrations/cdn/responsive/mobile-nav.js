/**
 * Travium Mobile Navigation & Sidebar Handler
 * Official Travian Mobile UI Replication
 */

(function () {
    'use strict';

    // Only run on mobile
    if (!document.body.classList.contains('mobileOptimized')) {
        return;
    }

    console.log('✅ Initializing Mobile UI...');

    // ============ GLOBAL STATE ============
    let activeSidebar = null;

    // ============ NAVIGATION HANDLER ============

    function initNavigation() {
        const nav = document.getElementById('mobileNavigation');
        if (!nav) {
            console.warn('Mobile navigation not found');
            return;
        }

        // Get current page
        const currentPath = window.location.pathname;
        const currentPage = currentPath.split('/').pop().replace('.php', '');

        // Navigation button click handlers
        const buttons = nav.querySelectorAll('.mobile-nav-btn');
        buttons.forEach(btn => {
            const page = btn.dataset.page;

            // Highlight active button
            if (currentPage.includes(page) ||
                (page === 'dorf1' && currentPath.includes('dorf1')) ||
                (page === 'dorf2' && currentPath.includes('dorf2'))) {
                btn.classList.add('active');
            }

            // Click handler
            btn.addEventListener('click', function (e) {
                e.preventDefault();

                switch (page) {
                    case 'dorf1':
                        window.location.href = 'dorf1.php';
                        break;
                    case 'dorf2':
                        window.location.href = 'dorf2.php';
                        break;
                    case 'karte':
                        window.location.href = 'karte.php';
                        break;
                    case 'reports':
                        window.location.href = 'reports.php';
                        break;
                    case 'messages':
                        window.location.href = 'messages.php';
                        break;
                    case 'dailyQuests':
                        window.location.href = 'daily_quests.php';
                        break;
                    case 'plus':
                        window.location.href = 'payment.php';
                        break;
                    default:
                        console.warn('Unknown page:', page);
                }
            });
        });

        console.log('✅ Navigation initialized');
    }

    // ============ SIDEBAR HANDLER ============

    function openSidebar(side) {
        closeSidebar(); // Close any open sidebar first

        const sidebarId = side === 'left' ? 'mobileSidebarLeft' : 'mobileSidebarRight';
        const sidebar = document.getElementById(sidebarId);
        const backdrop = document.getElementById('mobileSidebarBackdrop');

        if (!sidebar || !backdrop) {
            console.warn('Sidebar or backdrop not found');
            return;
        }

        sidebar.classList.add('open');
        backdrop.classList.add('active');
        activeSidebar = side;

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        console.log('✅ Opened', side, 'sidebar');
    }

    function closeSidebar() {
        if (!activeSidebar) return;

        const sidebarId = activeSidebar === 'left' ? 'mobileSidebarLeft' : 'mobileSidebarRight';
        const sidebar = document.getElementById(sidebarId);
        const backdrop = document.getElementById('mobileSidebarBackdrop');

        if (sidebar) {
            sidebar.classList.remove('open');
        }

        if (backdrop) {
            backdrop.classList.remove('active');
        }

        // Restore body scroll
        document.body.style.overflow = '';

        activeSidebar = null;

        console.log('✅ Closed sidebar');
    }

    // Make closeSidebar globally accessible
    window.closeMobileSidebar = closeSidebar;
    window.openMobileSidebar = openSidebar;

    // Backdrop click closes sidebar
    const backdrop = document.getElementById('mobileSidebarBackdrop');
    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // Close button in sidebar
    const closeButtons = document.querySelectorAll('.mobile-sidebar-close');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeSidebar);
    });

    // ============ HERO AVATAR HANDLER ============

    function initHeroAvatar() {
        const heroAvatar = document.getElementById('mobileHeroAvatar');
        if (!heroAvatar) return;

        // Click hero avatar to open left sidebar
        heroAvatar.addEventListener('click', function () {
            openSidebar('left');
        });

        // Try to populate hero info from existing elements
        const heroImg = document.querySelector('.heroImage, .playerImage');
        if (heroImg) {
            const clonedImg = heroImg.cloneNode(true);
            heroAvatar.appendChild(clonedImg);
        } else {
            // Placeholder
            heroAvatar.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:24px;color:white;">⚔️</div>';
        }

        console.log('✅ Hero avatar initialized');
    }

    // ============ RESOURCES BAR INITIALIZATION ============

    function initResourcesBar() {
        const stockBar = document.getElementById('stockBar');
        if (!stockBar) {
            console.warn('StockBar not found');
            return;
        }

        // StockBar is already in the correct position via CSS
        // Just ensure it's visible
        stockBar.style.display = 'flex';

        console.log('✅ Resources bar initialized');
    }

    // ============ SIDEBAR CONTENT INITIALIZATION ============

    function initSidebarContent() {
        // Left sidebar: Move existing sidebar content
        const leftSidebar = document.getElementById('mobileSidebarLeft');
        const sidebarBeforeContent = document.getElementById('sidebarBeforeContent');

        if (leftSidebar && sidebarBeforeContent) {
            // Clone the content to avoid breaking desktop view
            const clonedContent = sidebarBeforeContent.cloneNode(true);
            leftSidebar.appendChild(clonedContent);
            console.log('✅ Left sidebar content populated');
        }

        // Right sidebar is already populated in HTML with options menu
        console.log('✅ Right sidebar ready');
    }

    // ============ SWIPE GESTURE DETECTION (Optional) ============

    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;

    const SWIPE_THRESHOLD = 100; // Min distance for swipe
    const EDGE_THRESHOLD = 50;   // Distance from edge to trigger
    const VERTICAL_TOLERANCE = 80; // Max vertical movement

    function handleSwipeGesture() {
        const swipeDistanceX = touchEndX - touchStartX;
        const swipeDistanceY = Math.abs(touchEndY - touchStartY);
        const screenWidth = window.innerWidth;

        // Ignore vertical swipes
        if (swipeDistanceY > VERTICAL_TOLERANCE) {
            return;
        }

        // Swipe RIGHT from left edge → Open left sidebar
        if (swipeDistanceX > SWIPE_THRESHOLD && touchStartX < EDGE_THRESHOLD) {
            openSidebar('left');
        }

        // Swipe LEFT from right edge → Open right sidebar
        else if (swipeDistanceX < -SWIPE_THRESHOLD && touchStartX > (screenWidth - EDGE_THRESHOLD)) {
            openSidebar('right');
        }

        // Swipe LEFT → Close left sidebar if open
        else if (swipeDistanceX < -SWIPE_THRESHOLD && activeSidebar === 'left') {
            closeSidebar();
        }

        // Swipe RIGHT → Close right sidebar if open
        else if (swipeDistanceX > SWIPE_THRESHOLD && activeSidebar === 'right') {
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
        handleSwipeGesture();
    }, { passive: true });

    // ============ INITIALIZATION ============

    function init() {
        console.log('📱 Initializing Mobile UI Components...');

        initNavigation();
        initHeroAvatar();
        initResourcesBar();
        initSidebarContent();

        console.log('✅ Mobile UI fully initialized!');
    }

    // Run init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
