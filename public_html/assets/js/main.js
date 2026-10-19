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