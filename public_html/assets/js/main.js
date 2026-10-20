/**
 * Rey Koslowski Academic Website - Frontend JavaScript
 * Handles navigation, mobile menu, dropdown, animations
 */

document.addEventListener('DOMContentLoaded', () => {
    /*
     * --- Navbar ---
     *
     * This previously branched on a 'page-home' class to keep the navbar
     * transparent over the full-screen hero until you scrolled 50px. That class
     * was never actually set on <body> by any template, so the branch always took
     * the "not home" path and the navbar has in practice always been solid.
     *
     * The home page now opens with the name and title on the page background
     * rather than a photo, so a transparent navbar would be unreadable there
     * anyway. The dead branch and its scroll listener are removed; the navbar is
     * simply always solid, which is exactly how the site already behaved.
     */
    const nav = document.getElementById('mainNav');
    if (nav) {
        nav.classList.add('nav--solid');
    }

    // --- Mobile menu toggle ---
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('nav__menu--open');
            navToggle.classList.toggle('active');
        });
    }

    // --- Services dropdown: touch/click support for mobile/tablet ---
    const servicesDropdown = document.getElementById('servicesDropdown');
    if (servicesDropdown) {
        const trigger = servicesDropdown.querySelector('.nav__link--dropdown-trigger');
        const dropdown = servicesDropdown.querySelector('.nav__dropdown');

        if (trigger && dropdown) {
            // Toggle on click/touch (works on mobile, tablet, desktop)
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = dropdown.classList.contains('nav__dropdown--open');
                // Close all other dropdowns first
                document.querySelectorAll('.nav__dropdown--open').forEach(d => d.classList.remove('nav__dropdown--open'));
                if (!isOpen) {
                    dropdown.classList.add('nav__dropdown--open');
                    trigger.setAttribute('aria-expanded', 'true');
                } else {
                    dropdown.classList.remove('nav__dropdown--open');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!servicesDropdown.contains(e.target)) {
                    dropdown.classList.remove('nav__dropdown--open');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }


    /*
     * --- Welcome widget: scroll-linked fade ---
     *
     * The greeting is fully opaque while the hero intro is on screen, fades
     * progressively once it scrolls past, and is gone by the bottom of the About
     * section. Only the home page has the widget, so this is a no-op elsewhere.
     *
     * Opacity is written directly rather than toggled via a class, because the
     * value tracks scroll position continuously. The CSS opacity transition is
     * cleared while scrolling so the two do not fight each other - a 0.4s
     * transition on a value updated every frame produces visible lag.
     *
     * Reads are batched into a requestAnimationFrame callback so the scroll
     * handler itself never forces a synchronous layout.
     */
    const welcome = document.getElementById('welcomeWidget');
    const intro   = document.getElementById('homeHero');
    const about   = document.getElementById('about');

    if (welcome && intro && about) {
        // Offsets are cached and only recomputed on resize: reading offsetTop on
        // every scroll event would trigger layout thrashing.
        let fadeStart = 0;
        let fadeEnd = 0;

        function measure() {
            fadeStart = intro.offsetTop + intro.offsetHeight;
            fadeEnd   = about.offsetTop + about.offsetHeight;
            // Guarantee a usable fade distance even if About is unusually short.
            if (fadeEnd - fadeStart < 300) fadeEnd = fadeStart + 300;
        }

        function paint() {
            const y = window.scrollY || window.pageYOffset;
            let t = (y - fadeStart) / (fadeEnd - fadeStart);
            t = t < 0 ? 0 : (t > 1 ? 1 : t);          // clamp to 0..1
            const opacity = 1 - t;

            welcome.style.opacity = String(opacity);
            // Once invisible, stop it swallowing clicks meant for the page.
            welcome.style.pointerEvents = opacity < 0.05 ? 'none' : '';
            welcome.style.visibility = opacity <= 0 ? 'hidden' : 'visible';
        }

        let ticking = false;
        function onScroll() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                paint();
                ticking = false;
            });
        }

        // The transition is for the open/close buttons, not for scrolling.
        welcome.style.transition = 'none';

        measure();
        paint();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', function () { measure(); paint(); });
        // Late-loading images change the page height, so re-measure when they land.
        window.addEventListener('load', function () { measure(); paint(); });
    }


    /*
     * --- Analytics consent + page-view beacon ---
     *
     * Nothing is measured until the visitor clicks Accept. Reject is remembered
     * and honoured permanently: no request is sent, so no row is ever written.
     *
     * The decision is stored in localStorage rather than a cookie, so a visitor
     * who declines ends up with nothing in their cookie jar at all. Every access
     * is wrapped in try/catch because localStorage throws rather than returning
     * null in some privacy modes - and a storage failure must never break the page.
     */
    const CONSENT_KEY = 'rk-analytics-consent';

    function readConsent() {
        try { return window.localStorage.getItem(CONSENT_KEY); }
        catch (e) { return null; }          // private mode / storage blocked
    }
    function writeConsent(value) {
        try { window.localStorage.setItem(CONSENT_KEY, value); }
        catch (e) { /* non-fatal: the banner simply reappears next visit */ }
    }

    function sendPageView() {
        const payload = JSON.stringify({
            path: window.location.pathname,
            referrer: document.referrer || ''
        });

        // sendBeacon survives the page being closed and never delays navigation.
        // fetch with keepalive is the fallback for browsers without it.
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/api/track.php',
                    new Blob([payload], { type: 'application/json' }));
                return;
            }
        } catch (e) { /* fall through */ }

        try {
            fetch('/api/track.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true
            }).catch(function () { /* analytics must never surface an error */ });
        } catch (e) { /* ignore */ }
    }

    const banner = document.getElementById('consentBanner');
    const decision = readConsent();

    if (decision === 'granted') {
        sendPageView();
    } else if (decision !== 'denied' && banner) {
        /*
         * No decision yet: open the modal and block the page behind it until the
         * visitor chooses. Rendering it hidden in the markup and unhiding here
         * stops it flashing for visitors who already decided.
         */
        const accept = document.getElementById('consentAccept');
        const reject = document.getElementById('consentReject');

        function closeConsent() {
            banner.hidden = true;
            document.body.classList.remove('consent-open');
            document.removeEventListener('keydown', trapKeys, true);
        }

        /*
         * Keep focus inside the dialog while it is open.
         *
         * Tab cycles between the two buttons; Escape is swallowed rather than
         * closing anything, because dismissing without choosing would leave the
         * decision unrecorded and the modal would simply return on the next page.
         */
        function trapKeys(e) {
            if (e.key === 'Escape') { e.preventDefault(); return; }
            if (e.key !== 'Tab') return;
            const focusable = [reject, accept].filter(Boolean);
            if (!focusable.length) return;
            const first = focusable[0];
            const last  = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        }

        banner.hidden = false;
        document.body.classList.add('consent-open');
        document.addEventListener('keydown', trapKeys, true);
        if (accept) accept.focus();

        if (accept) {
            accept.addEventListener('click', function () {
                writeConsent('granted');
                closeConsent();
                sendPageView();           // count the page they consented on
            });
        }
        if (reject) {
            reject.addEventListener('click', function () {
                writeConsent('denied');
                closeConsent();           // nothing is sent, now or later
            });
        }
    }

});

/*
 * Scroll restoration on back/forward navigation.
 *
 * This file previously ended with `window.scrollTo(0, 0)` inside DOMContentLoaded,
 * which ran on EVERY page load — including when the visitor pressed Back. That threw
 * away the scroll position the browser had just restored, dumping them at the top of
 * the page instead of returning them to the link they clicked.
 *
 * Setting scrollRestoration to 'auto' is the browser default; we set it explicitly so
 * the intent is obvious and so it is re-armed even if a page turned it off earlier.
 */
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'auto';
}