<?php
// Dynamic footer - all content editable by admin except quick links
$footerAbout = getSetting('footer_about_text', 'Professor of Political Science & Director, Master of International Affairs Program.');
$footerEmail = getSetting('contact_email', 'rkoslowski@albany.edu');
$footerPhone = getSetting('contact_phone', '518-442-5314');
$footerAddress = getSetting('office_address', 'Department of Political Science, 135 Western Ave., Albany, NY 12222');
$footerAff1Name = getSetting('footer_affiliation_1_name', 'UAlbany Political Science');
$footerAff1Url = getSetting('footer_affiliation_1_url', 'https://www.albany.edu/rockefeller/political-science');
$footerAff2Name = getSetting('footer_affiliation_2_name', 'Rockefeller College');
$footerAff2Url = getSetting('footer_affiliation_2_url', 'https://www.albany.edu/rockefeller');
$footerAff3Name = getSetting('footer_affiliation_3_name', 'UAlbany Faculty Profile');
$footerAff3Url = getSetting('footer_affiliation_3_url', 'https://www.albany.edu/~rk289758/');
$scholarUrl = getSetting('google_scholar_url');
$rgUrl = getSetting('researchgate_url');
$orcidUrl = getSetting('orcid_url');
?>
<footer class="footer">
    <div class="footer__contact">
        <div class="container">
            <div class="footer__divider"></div>
            <h2 class="footer__contact-title">Get in Touch</h2>
            <div class="footer__contact-grid">
                <a href="mailto:<?= e($footerEmail) ?>" class="footer__contact-item">
                    <div class="footer__icon-circle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    </div>
                    <div>
                        <h3 class="footer__contact-label">Email</h3>
                        <p class="footer__contact-value"><?= e($footerEmail) ?></p>
                    </div>
                </a>
                <a href="tel:<?= e($footerPhone) ?>" class="footer__contact-item">
                    <div class="footer__icon-circle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <div>
                        <h3 class="footer__contact-label">Phone</h3>
                        <p class="footer__contact-value"><?= e($footerPhone) ?></p>
                    </div>
                </a>
                <div class="footer__contact-item">
                    <div class="footer__icon-circle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div>
                        <h3 class="footer__contact-label">Office</h3>
                        <p class="footer__contact-value"><?= nl2br(e($footerAddress)) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer__links">
        <div class="container">
            <div class="footer__grid">
                <div class="footer__col footer__col--about">
                    <h3 class="footer__heading--brand">Rey Koslowski</h3>
                    <p class="footer__text"><?= nl2br(e($footerAbout)) ?></p>
                </div>
                <div class="footer__col">
                    <h4 class="footer__heading">Quick Links</h4>
                     <ul class="footer__list">
                        <li><a href="<?= SITE_URL ?>/">Home</a></li>
                        <li><a href="<?= SITE_URL ?>/research.php">Research</a></li>
                        <li><a href="<?= SITE_URL ?>/teachings.php">Teaching</a></li>
                        <li><a href="<?= SITE_URL ?>/publications.php">Publications</a></li>
                        <li><a href="<?= SITE_URL ?>/presentations.php">Presentations</a></li>
                        <li><a href="<?= SITE_URL ?>/media-appearances.php">Media</a></li>
                        <li><a href="<?= SITE_URL ?>/migration-studies.php">Migration Studies</a></li>
                        <li><a href="<?= SITE_URL ?>/professional-service.php">Professional Service</a></li>
                        <li><a href="<?= SITE_URL ?>/community-engagement.php">Community Engagement</a></li>
                        <li><a href="<?= SITE_URL ?>/contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer__col">
                    <h4 class="footer__heading">Affiliations</h4>
                    <ul class="footer__list">
                        <?php if ($footerAff1Name): ?>
                        <li><a href="<?= e($footerAff1Url) ?>" target="_blank" rel="noopener noreferrer"><?= e($footerAff1Name) ?> ↗</a></li>
                        <?php endif; ?>
                        <?php if ($footerAff2Name): ?>
                        <li><a href="<?= e($footerAff2Url) ?>" target="_blank" rel="noopener noreferrer"><?= e($footerAff2Name) ?> ↗</a></li>
                        <?php endif; ?>
                        <?php if ($footerAff3Name): ?>
                        <li><a href="<?= e($footerAff3Url) ?>" target="_blank" rel="noopener noreferrer"><?= e($footerAff3Name) ?> ↗</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="footer__col">
                    <h4 class="footer__heading">Academic Profiles</h4>
                    <div class="footer__socials">
                        <?php if ($scholarUrl): ?>
                        <a href="<?= e($scholarUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer__social-link"><span class="footer__social-icon">GS</span>Google Scholar</a>
                        <?php endif; ?>
                        <?php if ($rgUrl): ?>
                        <a href="<?= e($rgUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer__social-link"><span class="footer__social-icon">RG</span>ResearchGate</a>
                        <?php endif; ?>
                        <?php if ($orcidUrl): ?>
                        <a href="<?= e($orcidUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer__social-link"><span class="footer__social-icon">ID</span>ORCID</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer__bottom">
        <div class="container">
            <div class="footer__bottom-inner">
                <p>&copy; <?= date('Y') ?> Rey Koslowski. All rights reserved.</p>
                <button type="button" class="footer__consent-link" id="consentReopen">Cookie preferences</button>
                <p>University at Albany (SUNY) · Department of Political Science</p>
            </div>
        </div>
    </div>
</footer>

<?php
/*
 * Cookie / analytics consent banner.
 *
 * Shown on every public page until a choice is recorded. The choice itself is
 * kept in localStorage rather than a cookie, so a visitor who declines never has
 * anything stored in their cookie jar at all.
 *
 * Nothing is measured before Accept is clicked: the analytics beacon in
 * assets/js/main.js is gated on this decision, so Reject means no request is
 * ever sent and no row is ever written.
 *
 * Rendered hidden and revealed by JS, so visitors who have already decided never
 * see it flash on screen.
 */
?>
<div class="consent-overlay" id="consentBanner" hidden>
    <div class="consent" role="dialog" aria-modal="true"
         aria-labelledby="consentTitle" aria-describedby="consentText">
        <h2 class="consent__title" id="consentTitle">Before you continue</h2>
        <p class="consent__text" id="consentText">
            This site records anonymous page statistics to work out which pages are
            useful. No personal data is collected and nothing is shared with anyone else.
        </p>
        <?php // Populated by JS only when the dialog is reopened from the footer. ?>
        <p class="consent__status" id="consentStatus" hidden></p>
        <div class="consent__actions">
            <button type="button" class="consent__btn" id="consentReject">Reject</button>
            <button type="button" class="consent__btn consent__btn--accept" id="consentAccept">Accept</button>
        </div>
    </div>
</div>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
