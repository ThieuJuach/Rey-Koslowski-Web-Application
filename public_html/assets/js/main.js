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
     * Nothing is measured until the visitor clicks Accept. Reject is honoured
     * for the rest of the session: no request is sent, so no row is written.
     *
     * TWO PIECES OF STATE, deliberately:
     *
     *   rk-analytics-consent  (localStorage) - the choice itself. Kept so the
     *       dialog can show "your current choice" and so the footer link has
     *       something to display.
     *
     *   rk_consent_seen       (session cookie) - marks that the dialog has
     *       already been shown in this browser session. It carries no value
     *       beyond "1" and is deleted when the browser closes, which is what
     *       produces the once-per-session prompt.
     *
     * A cookie is used for the session marker rather than sessionStorage
     * because sessionStorage is scoped to a single tab: opening a link in a new
     * tab would create fresh storage and re-prompt immediately. A session
     * cookie is shared across tabs and cleared on browser close, which is the
     * behaviour actually wanted. It is exempt from consent requirements itself,
     * being strictly necessary to honour the visitor's own preference.
     *
     * Every storage access is wrapped in try/catch: localStorage throws rather
     * than returning null in some privacy modes, and a storage failure must
     * never break the page.
     */
    const CONSENT_KEY  = 'rk-analytics-consent';
    const SESSION_FLAG = 'rk_consent_seen';

    function readConsent() {
        try { return window.localStorage.getItem(CONSENT_KEY); }
        catch (e) { return null; }          // private mode / storage blocked
    }
    function writeConsent(value) {
        try { window.localStorage.setItem(CONSENT_KEY, value); }
        catch (e) { /* non-fatal: the dialog simply reappears next visit */ }
    }

    /** Has the dialog already been shown in this browser session? */
    function askedThisSession() {
        return document.cookie.split('; ').indexOf(SESSION_FLAG + '=1') !== -1;
    }

    /** Mark the session. No Expires/Max-Age, so it dies with the browser. */
    function markAskedThisSession() {
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = SESSION_FLAG + '=1; Path=/; SameSite=Lax' + secure;
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
    const accept = document.getElementById('consentAccept');
    const reject = document.getElementById('consentReject');
    const status = document.getElementById('consentStatus');

    // Guards against counting the same page twice if the dialog is opened,
    // dismissed and reopened within one page view.
    let sentThisPage = false;

    function sendPageViewOnce() {
        if (sentThisPage) return;
        sentThisPage = true;
        sendPageView();
    }

    function closeConsent() {
        if (!banner) return;
        banner.hidden = true;
        document.body.classList.remove('consent-open');
        document.removeEventListener('keydown', trapKeys, true);

        // Dismissed without pressing a button (Escape on a re-ask). The previous
        // choice still stands, so an existing Accept should still be honoured.
        if (readConsent() === 'granted') sendPageViewOnce();
    }

    /*
     * Keep focus inside the dialog while it is open.
     *
     * Tab cycles between the two buttons. Escape is swallowed on the first visit,
     * because dismissing without choosing would leave the decision unrecorded and
     * the modal would simply return on the next page. When the dialog has been
     * reopened from the footer a choice already exists, so Escape just closes it.
     */
    let escapeCloses = false;

    function trapKeys(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            if (escapeCloses) closeConsent();
            return;
        }
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

    /**
     * Show the dialog.
     * @param {boolean} revisit true when reopened from the footer link, in which
     *        case the current choice is shown and Escape is allowed to dismiss.
     */
    function openConsent(revisit) {
        if (!banner) return;
        escapeCloses = !!revisit;

        if (status) {
            const current = readConsent();
            status.textContent = revisit && current
                ? (current === 'granted'
                    ? 'Your current choice: analytics allowed.'
                    : 'Your current choice: analytics declined.')
                : '';
            status.hidden = !status.textContent;
        }

        banner.hidden = false;
        document.body.classList.add('consent-open');
        document.addEventListener('keydown', trapKeys, true);
        if (accept) accept.focus();
    }

    // Wired once, so the buttons work on the first visit and on every reopen.
    if (accept) {
        accept.addEventListener('click', function () {
            writeConsent('granted');
            closeConsent();
            sendPageViewOnce();       // count the page they consented on
        });
    }
    if (reject) {
        reject.addEventListener('click', function () {
            writeConsent('denied');
            closeConsent();           // nothing further is sent
        });
    }

    /*
     * "Cookie preferences" in the footer. Consent has to be as easy to withdraw
     * as it was to give, and without this the first click was permanent for the
     * life of the browser profile.
     */
    const reopen = document.getElementById('consentReopen');
    if (reopen) {
        reopen.addEventListener('click', function (e) {
            e.preventDefault();
            openConsent(true);
        });
    }

    /*
     * On the first page of each browser session the dialog is shown, whether or
     * not a choice already exists - a returning visitor may well feel
     * differently than they did last time. If they already chose, the dialog
     * says so and Escape dismisses it, leaving that choice untouched.
     *
     * For the rest of the session the stored choice is applied silently.
     */
    const decision = readConsent();

    if (!askedThisSession()) {
        markAskedThisSession();
        openConsent(decision !== null);   // show current choice if there is one
    } else if (decision === 'granted') {
        sendPageViewOnce();
    }


    /*
     * --- Navbar search ---
     *
     * The form submits on its own, so this only manages the expand/collapse.
     * First click opens the field and focuses it; submitting is only allowed
     * once something has been typed, so the magnifier cannot fire an empty
     * search. With scripting off the field is simply always visible via CSS.
     */
    const navSearch = document.getElementById('navSearch');
    const navSearchToggle = document.getElementById('navSearchToggle');
    const navSearchField = document.getElementById('navSearchField');

    if (navSearch && navSearchToggle && navSearchField) {
        // Open it straight away if a term is already present (a results page).
        if (navSearchField.value.trim() !== '') {
            navSearch.classList.add('nav__search--open');
            navSearchToggle.setAttribute('aria-expanded', 'true');
        }

        navSearchToggle.addEventListener('click', function (e) {
            const isOpen = navSearch.classList.contains('nav__search--open');
            if (!isOpen || navSearchField.value.trim() === '') {
                e.preventDefault();                 // do not submit an empty query
                navSearch.classList.add('nav__search--open');
                navSearchToggle.setAttribute('aria-expanded', 'true');
                navSearchField.focus();
            }
            // otherwise fall through and let the form submit
        });

        // Clicking away closes it again, unless something has been typed.
        document.addEventListener('click', function (e) {
            if (navSearch.contains(e.target)) return;
            if (navSearchField.value.trim() !== '') return;
            navSearch.classList.remove('nav__search--open');
            navSearchToggle.setAttribute('aria-expanded', 'false');
        });

        navSearchField.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                navSearchField.value = '';
                navSearch.classList.remove('nav__search--open');
                navSearchToggle.setAttribute('aria-expanded', 'false');
                navSearchToggle.focus();
            }
        });
    }


    /*
     * --- Deferred video players ---
     *
     * Each facade swaps itself for the real iframe when its play button is
     * pressed. Until then nothing has been requested from YouTube or Vimeo, so
     * the visitor's IP never reaches them before they have chosen to watch -
     * and a page with four videos loads without two dozen extra requests.
     *
     * autoplay=1 is set on the swapped-in iframe because the visitor has
     * already clicked play; without it they would have to click twice.
     */
    document.querySelectorAll('.video-embed--facade').forEach(function (box) {
        const btn = box.querySelector('.video-facade__play');
        if (!btn) return;

        btn.addEventListener('click', function () {
            const provider = box.dataset.videoProvider;
            const id = box.dataset.videoId;
            if (!id) return;

            const src = provider === 'vimeo'
                ? 'https://player.vimeo.com/video/' + encodeURIComponent(id) + '?dnt=1&autoplay=1'
                : 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?rel=0&autoplay=1';

            const frame = document.createElement('iframe');
            frame.src = src;
            frame.title = 'Video';
            frame.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture';
            frame.allowFullscreen = true;

            box.classList.remove('video-embed--facade');
            box.innerHTML = '';
            box.appendChild(frame);
        });
    });

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