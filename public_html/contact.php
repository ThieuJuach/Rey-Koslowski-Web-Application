<?php
/**
 * Contact Page
 *
 * NOTE: this page previously carried a ~40-line POST handler for a contact form
 * (`$_POST['contact_form']`) that no longer exists in the markup — the form itself
 * had been removed, leaving the handler unreachable. It has been deleted as part of
 * the dead-code cleanup. The page now lists contact details only.
 *
 * If the form is reinstated, wrap the handler in:
 *     requireCsrf();
 *     enforceRateLimit('contact', CONTACT_MAX_PER_HOUR, 3600, 'html');
 *     $clean = validateSchema($_POST, [...]);   // see includes/functions.php
 */
$pageTitle = 'Contact';
$metaDescription = 'Contact Rey Koslowski, Professor of Political Science at the Rockefeller College, University at Albany (SUNY). Email, phone, and office information.';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$contactEmail = getSetting('contact_email', 'rkoslowski@albany.edu');
$contactPhone = getSetting('contact_phone', '518-442-5314');
$officeAddress = getSetting('office_address', 'Department of Political Science, 135 Western Ave., Albany, NY 12222');
$scholarUrl = getSetting('google_scholar_url');
$rgUrl = getSetting('researchgate_url');
$orcidUrl = getSetting('orcid_url');
$footerAbout = getSetting('footer_about_text', '');
$aff1Name = getSetting('footer_affiliation_1_name');
$aff1Url = getSetting('footer_affiliation_1_url');
$aff2Name = getSetting('footer_affiliation_2_name');
$aff2Url = getSetting('footer_affiliation_2_url');
$aff3Name = getSetting('footer_affiliation_3_name');
$aff3Url = getSetting('footer_affiliation_3_url');

?>

<div class="page-header">
    <div class="container">
        <div class="page-header__divider"></div>
        <h1 class="page-header__title">Contact</h1>
        <p class="page-header__subtitle">For inquiries about research collaboration, speaking engagements, or academic matters.</p>
    </div>
</div>


<?php // Optional highlighted panel. Renders nothing unless text or an
      // image has been set in the admin for this page. ?>
<?= renderFeaturedBlock('contact') ?>

<!-- Contact Details Section -->
<section class="section section--dark">
    <div class="container">
        <div class="content-max">
            <div class="contact-grid">
                <div>
                    <p class="text-lg" style="color: rgba(255,255,255,0.8); margin-bottom: 2rem; line-height: 1.8;">
                        For inquiries about research collaboration, speaking engagements,
                        or academic matters, please reach out using the contact information below.
                    </p>
                    <div class="contact-items">
                        <div class="contact-item">
                            <div class="contact-item__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            </div>
                            <div>
                                <h3 class="contact-item__label">Office Address</h3>
                                <p class="contact-item__value"><?= nl2br(e($officeAddress)) ?></p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-item__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            </div>
                            <div>
                                <h3 class="contact-item__label">Phone</h3>
                                <p class="contact-item__value"><?= e($contactPhone) ?></p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-item__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            </div>
                            <div>
                                <h3 class="contact-item__label">Email</h3>
                                <p class="contact-item__value"><a href="mailto:<?= e($contactEmail) ?>" style="color:var(--color-accent);"><?= e($contactEmail) ?></a></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="contact-links-box">
                    <h3 class="contact-links-box__title">Quick Links</h3>
                    <div class="contact-links-box__list">
                        <a href="<?= SITE_URL ?>/">→ Home</a>
                        <a href="research.php">→ Research Projects</a>
                        <a href="teachings.php">→ Teaching</a>
                        <a href="publications.php">→ Publications</a>
                        <a href="presentations.php">→ Presentations</a>
                        <a href="media-appearances.php">→ Media Appearances</a>
                        <a href="migration-studies.php">→ Migration Studies</a>
                        <a href="professional-service.php">→ Professional Services</a>
                        <a href="community-engagement.php">→ Community Engagement</a>
                        <a href="contact.php">→ Contact</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer content inline (kept as-is: this page uses the compact
     .contact-footer-section variant rather than the site-wide includes/footer.php) -->
<section class="contact-footer-section">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__col footer__col--about">
                <h3 class="footer__heading--brand">Rey Koslowski</h3>
                <p class="footer__text"><?= nl2br(e($footerAbout)) ?></p>
            </div>
            <div class="footer__col">
                <h4 class="footer__heading">Affiliations</h4>
                <ul class="footer__list">
                    <?php if ($aff1Name): ?><li><a href="<?= e($aff1Url) ?>" target="_blank" rel="noopener noreferrer"><?= e($aff1Name) ?> ↗</a></li><?php endif; ?>
                    <?php if ($aff2Name): ?><li><a href="<?= e($aff2Url) ?>" target="_blank" rel="noopener noreferrer"><?= e($aff2Name) ?> ↗</a></li><?php endif; ?>
                    <?php if ($aff3Name): ?><li><a href="<?= e($aff3Url) ?>" target="_blank" rel="noopener noreferrer"><?= e($aff3Name) ?> ↗</a></li><?php endif; ?>
                </ul>
            </div>
            <div class="footer__col">
                <h4 class="footer__heading">Academic Profiles</h4>
                <div class="footer__socials">
                    <?php if ($scholarUrl): ?>
                    <a href="<?= e($scholarUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer__social-link"><span class="footer__social-icon">GS</span> Google Scholar</a>
                    <?php endif; ?>
                    <?php if ($rgUrl): ?>
                    <a href="<?= e($rgUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer__social-link"><span class="footer__social-icon">RG</span> ResearchGate</a>
                    <?php endif; ?>
                    <?php if ($orcidUrl): ?>
                    <a href="<?= e($orcidUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer__social-link"><span class="footer__social-icon">ID</span> ORCID</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="contact-footer-bottom">
            <p>&copy; <?= date('Y') ?> Rey Koslowski. All rights reserved.
                <button type="button" class="footer__consent-link" id="consentReopen">Cookie preferences</button>
            </p>
            <p>University at Albany (SUNY) · Department of Political Science</p>
        </div>
    </div>
</section>

<?php // Consent banner - contact.php builds its own footer, so it is repeated here. ?>
<div class="consent-overlay" id="consentBanner" hidden>
    <div class="consent" role="dialog" aria-modal="true"
         aria-labelledby="consentTitle" aria-describedby="consentText">
        <h2 class="consent__title" id="consentTitle">Before you continue</h2>
        <p class="consent__text" id="consentText">
            This site records anonymous page statistics to work out which pages are
            useful. No personal data is collected and nothing is shared with anyone else.
            A small cookie remembers that you were asked, and is deleted when you close
            your browser.
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
