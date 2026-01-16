/**
 * Mobile Navigation System
 * Manages bottom navigation bar and hamburger menu
 */

(function () {
    'use strict';

    const MOBILE_BREAKPOINT = 620;

    function isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    // Create mobile bottom navigation
    function createBottomNav() {
        if (!isMobile()) return;
        if (document.getElementById('mobileBottomNav')) return; // Already exists

        // Get current page info
        const currentPath = window.location.pathname;
        const isResources = currentPath.includes('dorf1.php');
        const isBuildings = currentPath.includes('dorf2.php');
        const isMap = currentPath.includes('karte.php');
        const isStats = currentPath.includes('statistiken.php');
        const isReports = currentPath.includes('reports.php');
        const isMessages = currentPath.includes('messages.php');

        // Get notification counts from existing navigation
        const reportsCount = getNotificationCount('#navigation #n5');
        const messagesCount = getNotificationCount('#navigation #n6');

        // Create bottom nav HTML
        const nav = document.createElement('div');
        nav.id = 'mobileBottomNav';
        nav.innerHTML = `
            <a href="dorf1.php" class="nav-resources ${isResources ? 'active' : ''}">
                <span class="icon"></span>
                <span class="label">Resources</span>
            </a>
            <a href="dorf2.php" class="nav-buildings ${isBuildings ? 'active' : ''}">
                <span class="icon"></span>
                <span class="label">Buildings</span>
            </a>
            <a href="karte.php" class="nav-map ${isMap ? 'active' : ''}">
                <span class="icon"></span>
                <span class="label">Map</span>
            </a>
            <a href="reports.php" class="nav-reports ${isReports ? 'active' : ''}">
                <span class="icon"></span>
                <span class="label">Reports</span>
                ${reportsCount > 0 ? `<span class="badge">${reportsCount > 99 ? '99+' : reportsCount}</span>` : ''}
            </a>
            <a href="messages.php" class="nav-messages ${isMessages ? 'active' : ''}">
                <span class="icon"></span>
                <span class="label">Messages</span>
                ${messagesCount > 0 ? `<span class="badge">${messagesCount > 99 ? '99+' : messagesCount}</span>` : ''}
            </a>
            <a href="#" class="nav-menu" id="hamburgerMenuBtn">
                <div class="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="label">Menu</span>
            </a>
        `;

        document.body.appendChild(nav);

        // Setup hamburger menu
        setupHamburgerMenu();

        console.log('[Mobile Nav] Bottom navigation created');
    }

    // Get notification count from existing navigation
    function getNotificationCount(selector) {
        const elem = document.querySelector(selector + ' .speechBubbleContent');
        if (elem) {
            const text = elem.textContent.trim();
            if (text === '+99') return 99;
            return parseInt(text) || 0;
        }
        return 0;
    }

    // Setup hamburger menu
    function setupHamburgerMenu() {
        // Create menu if doesn't exist
        if (!document.getElementById('mobileHamburgerMenu')) {
            const menu = document.createElement('div');
            menu.id = 'mobileHamburgerMenu';
            menu.innerHTML = `
                <a href="statistiken.php">Statistics</a>
                <a href="spieler.php">Profile</a>
                <a href="options.php">Options</a>
                <a href="help.php">Help</a>
                <a href="logout.php">Logout</a>
            `;
            document.body.appendChild(menu);
        }

        // Toggle menu on button click
        const btn = document.getElementById('hamburgerMenuBtn');
        const menu = document.getElementById('mobileHamburgerMenu');

        if (btn && menu) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                menu.classList.toggle('show');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function (e) {
                if (!menu.contains(e.target) && !btn.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });

            // Close menu when clicking a link
            menu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function () {
                    menu.classList.remove('show');
                });
            });
        }
    }

    // Remove mobile navigation
    function removeBottomNav() {
        const nav = document.getElementById('mobileBottomNav');
        const menu = document.getElementById('mobileHamburgerMenu');

        if (nav) nav.remove();
        if (menu) menu.remove();
    }

    // Handle window resize
    function handleResize() {
        if (isMobile()) {
            if (!document.getElementById('mobileBottomNav')) {
                createBottomNav();
            }
        } else {
            removeBottomNav();
        }
    }

    // Debounce resize handler
    let resizeTimeout;
    function debouncedResize() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(handleResize, 150);
    }

    // Initialize
    function init() {
        if (isMobile()) {
            createBottomNav();
        }

        window.addEventListener('resize', debouncedResize);

        console.log('[Mobile Nav] Initialized');
    }

    // Auto-start
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose API
    window.MobileNav = {
        isMobile: isMobile,
        refresh: createBottomNav
    };

})();
