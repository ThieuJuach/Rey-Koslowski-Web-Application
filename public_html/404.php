<?php
/**
 * 404 Not Found page.
 *
 * SEO: previously a missing URL fell through to Apache's default error page, and
 * anything unmatched could look like a "soft 404" to Google — a page returning 200
 * with no real content. This returns a genuine 404 status alongside a useful page,
 * and is marked noindex so the error page itself never enters the index.
 */
$pageTitle       = 'Page not found';
$metaDescription = 'The page you were looking for could not be found on Rey Koslowski\'s website.';

require_once 'config.php';
require_once 'includes/auth.php';

// Send the real status code before any output.
http_response_code(404);

// Overrides the site-wide "index, follow" from header.php.
$robotsOverride = 'noindex, follow';

include 'includes/header.php';
include 'includes/nav.php';
?>

<main id="main-content">
    <div class="page-header">
        <div class="container">
            <div class="page-header__divider"></div>
            <h1 class="page-header__title">Page not found</h1>
            <p class="page-header__subtitle">
                The page you were looking for does not exist, or has moved.
            </p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <div class="content-max">
                <div class="section__divider"></div>
                <h2 class="section__title">Try one of these</h2>
                <div class="featured-grid">
                    <a href="<?= SITE_URL ?>/" class="card card--link">
                        <h3 class="card__title">Home</h3>
                        <p class="card__text">Biography, current research, and recent publications.</p>
                        <span class="card__arrow">View →</span>
                    </a>
                    <a href="<?= SITE_URL ?>/publications.php" class="card card--link">
                        <h3 class="card__title">Publications</h3>
                        <p class="card__text">Books, journal articles, and policy papers.</p>
                        <span class="card__arrow">View →</span>
                    </a>
                    <a href="<?= SITE_URL ?>/research.php" class="card card--link">
                        <h3 class="card__title">Research</h3>
                        <p class="card__text">International organization, migration policy, and homeland security.</p>
                        <span class="card__arrow">View →</span>
                    </a>
                    <a href="<?= SITE_URL ?>/contact.php" class="card card--link">
                        <h3 class="card__title">Contact</h3>
                        <p class="card__text">Email, phone, and office information.</p>
                        <span class="card__arrow">View →</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
