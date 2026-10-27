<?php
/**
 * Home Page
 * 
 * Uses page-header style (consistent with all other pages)
 * CV buttons positioned under name/position on the left
 */
$pageTitle = 'Home';
$metaDescription = 'Rey Koslowski - Professor of Political Science at University at Albany (SUNY). Research on international relations, migration policy, and homeland security.';
$fullPageTitle = 'Rey Koslowski | Professor of Political Science';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$cvUrl = getSetting('cv_url');
$cvFilePath = getSetting('cv_file_path');
$profileImage = getProfileImage();
$currentResearch = getCurrentResearch();
$currentCourses = getCurrentCourses();
$currentPubs = getCurrentPublications();
$currentPresentations = getCurrentPresentations();
$page = getPageContent('home');


// Settings
$siteTitle = getSetting('site_title', 'Rey Koslowski');
$siteSubtitle = getSetting('site_subtitle', 'Professor of Political Science');
$siteAffiliation = getSetting('site_affiliation', 'Rockefeller College of Public Affairs and Policy, University at Albany (SUNY)');

/*
 * Fall back to the external CV link when the uploaded file is not actually on
 * disk. The setting can outlive the file - a tidy-up of uploads/, a restore,
 * or a half-finished replacement all leave the path pointing at nothing, and
 * the page would then offer a "Download CV" button that 404s. Checking the
 * file is cheap and keeps the button honest.
 */
$cvOnDisk = $cvFilePath && is_file(__DIR__ . '/' . ltrim($cvFilePath, '/'));
$cvSrc    = $cvOnDisk ? SITE_URL . '/' . $cvFilePath : $cvUrl;
?>

<main id="main-content">

<?php /*
  Home intro: identity on the left, books photo on the right.

  LAYOUT: a two-column grid from 1024px up. Below that the columns stack with
  the identity first, so a phone still leads with the name rather than a photo.
  The text column is padded to line up with the site container while the photo
  column runs to the right edge of the viewport, so the heading stays aligned
  with every other page and the image still reads as full-bleed.

  WHY THIS SHAPE: at half width the photo renders about 960x720 on a 1920
  screen, instead of the 1920x1440 a full-width band produced, so the whole
  intro fits above the fold. Nothing is cropped: the column is given the
  photo's own 4:3 ratio.

  PERFORMANCE: responsive WebP/JPEG derivatives in uploads/images/hero/ mean the
  browser fetches only the width it needs, roughly 93 KB on a phone and 250 KB
  on a desktop, against 3.5 MB for the original JPEG. "sizes" states the real
  rendered width (half the viewport once side by side) so desktop does not
  over-fetch. Deliberately not preloaded: the heading is the LCP element now,
  and prioritising the image would starve the text seen first.
*/ ?>
<section class="home-intro" id="homeHero">

    <div class="home-intro__text">
        <div class="page-header__divider"></div>
        <h1 class="home-intro__name"><?= e($siteTitle) ?></h1>
        <p class="home-intro__role"><?= e($siteSubtitle) ?></p>
        <p class="home-intro__affil"><?= e($siteAffiliation) ?></p>
        <?php // Gate on the resolved source, not on the raw settings: either one can
          // be set and still produce nothing to link to.
          if ($cvSrc): ?>
        <div class="page-header__cv-actions">
            <a href="#cv-viewer" class="page-header__cv-btn" data-cv-open>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                View CV
            </a>
            <a href="<?= safeUrl($cvSrc) ?>" download class="page-header__cv-btn page-header__cv-btn--download">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download CV
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="home-intro__media">
        <picture>
            <source
                type="image/webp"
                sizes="(min-width: 1024px) 50vw, 100vw"
                srcset="<?= SITE_URL ?>/uploads/images/hero/hero-960.webp 960w,
                        <?= SITE_URL ?>/uploads/images/hero/hero-1440.webp 1440w,
                        <?= SITE_URL ?>/uploads/images/hero/hero-1920.webp 1920w,
                        <?= SITE_URL ?>/uploads/images/hero/hero-2560.webp 2560w">
            <img
                class="home-intro__img"
                src="<?= SITE_URL ?>/uploads/images/hero/hero-1440.jpg"
                sizes="(min-width: 1024px) 50vw, 100vw"
                srcset="<?= SITE_URL ?>/uploads/images/hero/hero-960.jpg 960w,
                        <?= SITE_URL ?>/uploads/images/hero/hero-1440.jpg 1440w,
                        <?= SITE_URL ?>/uploads/images/hero/hero-1920.jpg 1920w,
                        <?= SITE_URL ?>/uploads/images/hero/hero-2560.jpg 2560w"
                width="4160" height="3120"
                alt="A shelf of books by authors who have influenced Rey Koslowski's thinking"
                decoding="async">
        </picture>
    </div>

</section>

<!-- CV Viewer -->
<?php // Gate on the resolved source, not on the raw settings: either one can
          // be set and still produce nothing to link to.
          if ($cvSrc): ?>
<section id="cv-viewer" class="cv-viewer" style="display:none;">
    <div class="container">
        <div class="content-max">
            <div class="cv-viewer__header">
                <h2 class="section__title">Curriculum Vitae</h2>
                <button type="button" class="cv-viewer__close" data-cv-close>&times; Close</button>
            </div>
            <div class="cv-viewer__frame">
                <iframe src="<?= safeUrl($cvSrc) ?>#toolbar=1" title="Curriculum Vitae" width="100%" height="800" style="border:none;border-radius:var(--radius);"></iframe>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- About Section: Text (70%) / Profile Photo (30%) -->
<!-- id is used by the welcome widget's scroll fade (assets/js/main.js) to know
     where the greeting should be fully faded out, and doubles as an anchor target. -->
<section class="section" id="about">
    <div class="container">
        <div class="content-max">
            <div class="section__divider"></div>
            <h2 class="section__title">About</h2>
            <div class="about-grid-main">
                <!-- Text column (70%) -->
                <div class="about-grid-main__text">
                    <?php if ($page && $page['content']): ?>
                        <div class="prose">
                            <?= formatText($page['content']) ?>
                        </div>
                    <?php else: ?>
                    <p class="text-lg">
                        Rey Koslowski is Professor of Political Science and Director of the
                        Master of International Affairs program at the Rockefeller College of
                        Public Affairs and Policy, University at Albany (SUNY). He received his
                        Ph.D from the University of Pennsylvania.
                    </p>
                    <p class="text-muted">
                        His primary teaching and research interests are in the field of international
                        relations dealing with international organization, European integration,
                        international migration, information technology, and homeland security.
                    </p>
                    <?php endif; ?>
                </div>
                <!-- Profile Photo column (30%) -->
                <div class="about-grid-main__photo">
                    <?php if ($profileImage): ?>
                    <div class="profile-image-card">
                        <img src="<?= SITE_URL . '/' . e($profileImage['image_path']) ?>" 
                             alt="<?= e($profileImage['alt_text'] ?? 'Rey Koslowski') ?>"
                             class="profile-image-card__img">
                        <?php if (!empty($profileImage['caption'])): ?>
                        <p class="profile-image-card__caption"><?= e($profileImage['caption']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="image-placeholder image-placeholder--profile-side">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                        <p class="image-placeholder__label">Profile Photo</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Current & Ongoing Highlights -->
<section class="section section--alt">
    <div class="container">
        <div class="content-max">
            <div class="section__divider"></div>
            <h2 class="section__title">Current & Ongoing</h2>

            <?php if (!empty($currentResearch)): ?>
            <h3 class="subsection-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                Ongoing Research
            </h3>
            <div class="projects-grid" style="margin-bottom: 2rem;">
                <?php foreach ($currentResearch as $project): ?>
                <?php $cardTag = safeUrl($project['link_url']) ? 'a' : 'div'; ?>
                <<?= $cardTag ?> class="card <?= safeUrl($project['link_url']) ? 'card--link' : '' ?>"<?= safeUrl($project['link_url']) ? ' href="' . safeUrl($project['link_url']) . '" target="_blank" rel="noopener"' : '' ?>>
                    <?php if (!empty($project['image_path'])): ?>
                    <img src="<?= SITE_URL . '/' . e($project['image_path']) ?>" alt="<?= e($project['title']) ?>" class="card__cover" loading="lazy">
                    <?php endif; ?>
                    <h4 class="card__title"><?= e($project['title']) ?></h4>
                    <?php if ($project['description']): ?>
                    <p class="card__text"><?= formatText($project['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($project['link_url']): ?>
                    <span class="card__arrow">View →</span>
                    <?php endif; ?>
                </<?= $cardTag ?>>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($currentCourses)): ?>
            <h3 class="subsection-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.1 2.69 2 6 2s6-.9 6-2v-5"/></svg>
                Current Courses
            </h3>
            <div class="current-items-list" style="margin-bottom: 2rem;">
                <?php foreach ($currentCourses as $course): ?>
                <div class="current-item">
                    <?php if ($course['code']): ?>
                    <span class="current-item__code"><?= e($course['code']) ?></span>
                    <?php endif; ?>
                    <div>
                        <?php if ($course['link_url']): ?>
                        <a href="<?= safeUrl($course['link_url']) ?>" target="_blank" rel="noopener" class="current-item__title"><?= e($course['title']) ?></a>
                        <?php else: ?>
                        <h4 class="current-item__title"><?= e($course['title']) ?></h4>
                        <?php endif; ?>
                        <p class="current-item__meta"><?= e($course['level']) ?> · <?= e($course['institution']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($currentPubs)): ?>
            <h3 class="subsection-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Recent Publications
            </h3>
            <div class="publications-list">
                <?php foreach ($currentPubs as $pub): ?>
                <div class="pub-card">
                    <div class="pub-card__content">
                        <?php if ($pub['link_url'] || $pub['url'] || $pub['doi']): ?>
                        <?php $pubLink = $pub['link_url'] ?: ($pub['url'] ?: ($pub['doi'] ? 'https://doi.org/' . $pub['doi'] : '')); ?>
                        <a href="<?= safeUrl($pubLink) ?>" target="_blank" rel="noopener" class="pub-card__title-link">
                            <h3 class="pub-card__title"><?= e($pub['title']) ?></h3>
                        </a>
                        <?php else: ?>
                        <h3 class="pub-card__title"><?= e($pub['title']) ?></h3>
                        <?php endif; ?>
                        <p class="pub-card__meta">
                            <?= e($pub['authors'] ?? '') ?>
                            <?= $pub['journal_or_publisher'] ? ' · ' . e($pub['journal_or_publisher']) : '' ?>
                        </p>
                    </div>
                    <span class="pub-card__year"><?= e($pub['year'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($currentPresentations)): ?>
            <h3 class="subsection-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Selected Presentations
            </h3>
            <div class="publications-list" style="margin-bottom: 2rem;">
                <?php foreach ($currentPresentations as $pres): ?>
                <div class="pub-card">
                    <div class="pub-card__content">
                        <?php if ($pres['link_url']): ?>
                        <a href="<?= safeUrl($pres['link_url']) ?>" target="_blank" rel="noopener" class="pub-card__title-link">
                            <h3 class="pub-card__title"><?= e($pres['title']) ?></h3>
                        </a>
                        <?php else: ?>
                        <h3 class="pub-card__title"><?= e($pres['title']) ?></h3>
                        <?php endif; ?>
                        <p class="pub-card__meta">
                            <?= $pres['event_name'] ? e($pres['event_name']) : '' ?>
                            <?= $pres['location'] ? ' · ' . e($pres['location']) : '' ?>
                        </p>
                        <?php if ($pres['presentation_date']): ?>
                        <p class="pub-card__meta"><?= date('M j, Y', strtotime($pres['presentation_date'])) ?><?= $pres['end_date'] ? ' – ' . date('M j, Y', strtotime($pres['end_date'])) : '' ?></p>
                        <?php endif; ?>
                        <?php if (!empty($pres['pdf_path'])): ?>
                        <div class="pub-card__links"><?= renderPdfLink($pres['pdf_path'], 'Slides/PDF') ?></div>
                        <?php endif; ?>
                        <?php if (!empty($pres['video_url'])): ?>
                        <?= renderVideoEmbed($pres['video_url']) ?>
                        <?php endif; ?>
                    </div>
                    <span class="pub-card__year"><?= e(ucfirst($pres['type'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($currentResearch) && empty($currentCourses) && empty($currentPubs) && empty($currentPresentations)): ?>
            <p class="text-muted">Current highlights will appear here soon.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Explore Section -->
<section class="section">
    <div class="container">
        <div class="content-max">
            <div class="section__divider"></div>
            <h2 class="section__title">Explore</h2>
            <div class="featured-grid">
                <a href="research.php" class="card card--link">
                    <h3 class="card__title">Research</h3>
                    <p class="card__text">International organization, migration policy, and homeland security.</p>
                    <span class="card__arrow">View →</span>
                </a>
                <a href="publications.php" class="card card--link">
                    <h3 class="card__title">Publications</h3>
                    <p class="card__text">Books, journal articles, and policy papers on global mobility.</p>
                    <span class="card__arrow">View →</span>
                </a>
                <a href="presentations.php" class="card card--link">
                    <h3 class="card__title">Presentations</h3>
                    <p class="card__text">Conference presentations, invited talks, and keynotes.</p>
                    <span class="card__arrow">View →</span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php
/*
 * Floating welcome widget.
 *
 * Both the text and the on/off switch come from Admin -> Home / About. They were
 * previously hard-coded here, which left that admin control doing nothing.
 *
 * Defaults are chosen so the widget cannot silently vanish: the toggle defaults
 * to shown when the setting row is missing, and the original sentence is used
 * when no message has been saved.
 *
 * It is shown from page load. It used to fade in only while the full-screen hero
 * was visible; the photo is now a side column, so tying it to the image would
 * hide the greeting on tall screens.
 */
$welcomeEnabled = getSetting('welcome_message_enabled', '1');
$welcomeMsg     = trim(getSetting('welcome_message', ''));
?>
<?php if ($welcomeEnabled === '1'): ?>
<div class="welcome-widget" id="welcomeWidget">
    <div class="welcome-widget__body" id="welcomeBody">
        <button type="button" class="welcome-widget__close" data-welcome-close aria-label="Minimize">&times;</button>
        <h4 class="welcome-widget__title">Welcome</h4>
        <div class="welcome-widget__text">
            <?php if ($welcomeMsg !== ''): ?>
                <?= formatText($welcomeMsg) ?>
            <?php else: ?>
                <p>Welcome to my personal academic website. Feel free to browse these books by authors who have influenced my thinking over the years.</p>
            <?php endif; ?>
        </div>
    </div>
    <button type="button" class="welcome-widget__toggle" id="welcomeToggle" style="display:none;" data-welcome-open aria-label="Open welcome message">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </button>
</div>
<?php endif; ?>

</main>

<?php include 'includes/footer.php'; ?>
