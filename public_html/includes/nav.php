<?php $current = currentPage(); ?>
<nav class="nav" id="mainNav">
    <div class="nav__container">
        <a href="<?= SITE_URL ?>/" class="nav__brand">Rey Koslowski</a>
        <button class="nav__toggle" id="navToggle" aria-label="Toggle menu">
            <span class="nav__toggle-bar"></span>
            <span class="nav__toggle-bar"></span>
            <span class="nav__toggle-bar"></span>
        </button>
        <div class="nav__menu" id="navMenu">
            <a href="<?= SITE_URL ?>/" class="nav__link <?= $current === 'home' ? 'nav__link--active' : '' ?>">Home</a>
            <a href="<?= SITE_URL ?>/research.php" class="nav__link <?= $current === 'research' ? 'nav__link--active' : '' ?>">Research</a>
            <a href="<?= SITE_URL ?>/teachings.php" class="nav__link <?= $current === 'teachings' ? 'nav__link--active' : '' ?>">Teaching</a>
            <a href="<?= SITE_URL ?>/publications.php" class="nav__link <?= $current === 'publications' ? 'nav__link--active' : '' ?>">Publications</a>
            <a href="<?= SITE_URL ?>/presentations.php" class="nav__link <?= $current === 'presentations' ? 'nav__link--active' : '' ?>">Presentations</a>
            <a href="<?= SITE_URL ?>/media-appearances.php" class="nav__link <?= $current === 'media-appearances' ? 'nav__link--active' : '' ?>">Media</a>
            
            <!-- Services dropdown — responsive: click on mobile, hover on desktop -->
            <div class="nav__item nav__item--dropdown" id="servicesDropdown">
                <button class="nav__link nav__link--dropdown-trigger <?= in_array($current, ['migration-studies', 'professional-service', 'community-engagement']) ? 'nav__link--active' : '' ?>" 
                        aria-expanded="false" aria-haspopup="true" type="button">
                    Service ▾
                </button>
                <div class="nav__dropdown" role="menu">
                    <a href="<?= SITE_URL ?>/migration-studies.php" class="nav__dropdown-link" role="menuitem">Migration Studies</a>
                    <a href="<?= SITE_URL ?>/professional-service.php" class="nav__dropdown-link" role="menuitem">Professional Service</a>
                    <a href="<?= SITE_URL ?>/community-engagement.php" class="nav__dropdown-link" role="menuitem">Community Engagement</a>
                </div>
            </div>

            <a href="<?= SITE_URL ?>/contact.php" class="nav__link <?= $current === 'contact' ? 'nav__link--active' : '' ?>">Contact</a>

            <?php /*
                Site search. A real GET form, so it works with scripting
                disabled - the magnifier is then simply a submit button and the
                field is reachable by tab. main.js only adds the expand/collapse
                behaviour on top of that.
            */ ?>
            <form class="nav__search" id="navSearch" method="GET"
                  action="<?= SITE_URL ?>/search.php" role="search">
                <label class="sr-only" for="navSearchField">Search this site</label>
                <input class="nav__search-field" type="search" id="navSearchField"
                       name="q" maxlength="100" placeholder="Search…"
                       value="<?= e($_GET['q'] ?? '') ?>">
                <button class="nav__search-toggle" type="submit" id="navSearchToggle"
                        aria-label="Search" aria-expanded="false">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</nav>