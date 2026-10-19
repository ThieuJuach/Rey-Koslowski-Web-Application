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
        </div>
    </div>
</nav>