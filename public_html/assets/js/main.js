/**
 * Rey Koslowski Academic Website - Frontend JavaScript
 * Handles navigation, mobile menu, dropdown, animations
 */

document.addEventListener('DOMContentLoaded', () => {
    // --- Navbar scroll behavior ---
    const nav = document.getElementById('mainNav');
    const isHomePage = document.body.classList.contains('page-home');

    function handleScroll() {
        if (window.scrollY > 50 || !isHomePage) {
            nav.classList.add('nav--scrolled');
        } else {
            nav.classList.remove('nav--scrolled');
        }
    }

    if (!isHomePage) {
        nav.classList.add('nav--solid');
    }
    
    window.addEventListener('scroll', handleScroll);
    handleScroll();

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