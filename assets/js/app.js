/**
 * assets/js/app.js
 * -----------------------------------------------------------------------
 * Global JS for authenticated pages: sidebar toggle, AJAX CSRF wiring,
 * and small shared UI behaviors used across all modules.
 */
(function ($) {
    'use strict';

    // Keep Chrome/Edge's install event available to the Settings page. The
    // browser only exposes this prompt after the PWA requirements are met and
    // it must be triggered directly by a user click.
    window.POS_INSTALL = window.POS_INSTALL || {};
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.POS_INSTALL.deferredPrompt = event;
        window.dispatchEvent(new Event('posinstallavailable'));
    });
    window.addEventListener('appinstalled', function () {
        window.POS_INSTALL.deferredPrompt = null;
        window.dispatchEvent(new Event('posinstalled'));
    });

    $(function () {
        const isMobile = window.matchMedia && window.matchMedia('(max-width: 767.98px), (pointer: coarse)').matches;
        const shouldUseMobileFullscreen = isMobile && window.POS_CONFIG && window.POS_CONFIG.mobileFullscreen;

        // Browsers only permit Fullscreen API calls from a user gesture. The first
        // tap is therefore used to enter it, instead of showing an error on load.
        if (shouldUseMobileFullscreen && document.documentElement.requestFullscreen) {
            const enterFullscreen = function () {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(function () {});
                }
                document.removeEventListener('pointerdown', enterFullscreen, true);
                document.removeEventListener('keydown', enterFullscreen, true);
            };
            document.addEventListener('pointerdown', enterFullscreen, true);
            document.addEventListener('keydown', enterFullscreen, true);
        }

        // Enables compatible mobile browsers to install the POS as an app.
        if ('serviceWorker' in navigator && window.isSecureContext) {
            navigator.serviceWorker.register((window.APP_URL || '') + '/sw.js').catch(function () {});
        }

        // Mobile sidebar toggle
        $('#sidebarToggle').on('click', function () {
            $('#posSidebar').toggleClass('show');
        });

        // -----------------------------------------------------------------
        // Full-screen toggle (navbar button)
        // -----------------------------------------------------------------
        function updateFullscreenIcon() {
            const active = !!document.fullscreenElement;
            $('#fullscreenToggleIcon').toggleClass('bi-arrows-fullscreen', !active).toggleClass('bi-fullscreen-exit', active);
            $('#fullscreenToggleBtn').attr('aria-label', active ? 'Exit full screen' : 'Enter full screen').attr('title', active ? 'Exit full screen' : 'Enter full screen');
        }
        $('#fullscreenToggleBtn').on('click', function () {
            if (!document.documentElement.requestFullscreen && !document.exitFullscreen) return;
            if (document.fullscreenElement) {
                document.exitFullscreen().catch(function () {});
            } else {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        });
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
        updateFullscreenIcon();

        // -----------------------------------------------------------------
        // Dark / Light mode toggle - default is Light; the saved choice
        // is applied instantly on every page via the inline script in
        // includes/header.php, before this file even loads.
        // -----------------------------------------------------------------
        function updateThemeIcon() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            $('#themeToggleIcon').toggleClass('bi-moon-stars', !isDark).toggleClass('bi-sun', isDark);
            $('#themeToggleBtn').attr('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode').attr('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        }
        $('#themeToggleBtn').on('click', function () {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
            try { localStorage.setItem('pos_theme', isDark ? 'light' : 'dark'); } catch (e) { /* private-mode storage denial - theme still applies for this page load */ }
            updateThemeIcon();
        });
        updateThemeIcon();

        $(document).on('click', function (e) {
            const $sidebar = $('#posSidebar');
            if (!$sidebar.length) return;
            if ($sidebar.hasClass('show') &&
                !$(e.target).closest('#posSidebar, #sidebarToggle').length) {
                $sidebar.removeClass('show');
            }
        });

        // Attach the CSRF token to every AJAX request automatically,
        // so individual modules don't have to remember to add it.
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        if (csrfToken) {
            $.ajaxSetup({
                headers: { 'X-CSRF-Token': csrfToken }
            });
        }

        // Global AJAX error handler: if the server reports the session
        // expired (401), bounce back to the login screen.
        $(document).ajaxError(function (event, jqXHR) {
            if (jqXHR.status === 401 && jqXHR.responseJSON && jqXHR.responseJSON.session_expired) {
                window.location.href = (window.APP_URL || '') + '/login.php';
            }
        });
    });
})(jQuery);
