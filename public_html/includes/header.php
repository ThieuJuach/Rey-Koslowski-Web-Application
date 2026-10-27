<?php
/*
 * Baseline rate limit for every public page.
 *
 * header.php is included by all public pages and nothing in /admin, so this is the
 * natural choke point. It runs before any output so the 429 page can be sent cleanly.
 *
 * 120 requests/minute/IP is far above what a person browsing (or a well-behaved
 * crawler like Googlebot) will ever generate, but it stops scrapers and naive
 * flooding from consuming database connections. Signed-in admins get double.
 */
enforceRateLimit('public_page', PUBLIC_PAGE_MAX_PER_MINUTE, 60, 'html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php // Pages may opt out of indexing by setting $robotsOverride (see 404.php). ?>
    <meta name="robots" content="<?= e($robotsOverride ?? 'index, follow') ?>">
    <meta name="author" content="Rey Koslowski">
    <meta name="theme-color" content="#1e3a6e">
    <?php
    // Canonical URL — home page gets trailing-slash form, not /index.php
    $canonicalPage = basename($_SERVER['SCRIPT_NAME']);
    $canonicalUrl  = ($canonicalPage === 'index.php') ? SITE_URL . '/' : SITE_URL . '/' . $canonicalPage;

    // Title: pages may override entirely via $fullPageTitle
    $fullTitle = !empty($fullPageTitle)
        ? e($fullPageTitle)
        : e(($pageTitle ?? 'Home') . ' | ' . SITE_TITLE);

    $fullDesc = e($metaDescription ?? 'Rey Koslowski - Professor of Political Science, University at Albany (SUNY). Research on international relations, migration, and homeland security.');

    // OG image: page may set $ogImage explicitly; otherwise fall back to profile photo
    $_headerProfileImg = getProfileImage();
    if (empty($ogImage) && $_headerProfileImg) {
        $ogImage    = SITE_URL . '/' . $_headerProfileImg['image_path'];
        $ogImageAlt = $ogImageAlt ?? ($_headerProfileImg['alt_text'] ?: 'Rey Koslowski');
    }
    ?>

    <!-- Primary meta -->
    <title><?= $fullTitle ?></title>
    <meta name="description" content="<?= $fullDesc ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <!-- Open Graph -->
    <meta property="og:type"        content="<?= e($ogType ?? 'website') ?>">
    <meta property="og:url"         content="<?= e($canonicalUrl) ?>">
    <meta property="og:title"       content="<?= $fullTitle ?>">
    <meta property="og:description" content="<?= $fullDesc ?>">
    <meta property="og:site_name"   content="Rey Koslowski">
    <meta property="og:locale"      content="en_US">
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image"       content="<?= e($ogImage) ?>">
    <meta property="og:image:alt"   content="<?= e($ogImageAlt ?? 'Rey Koslowski') ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="<?= empty($ogImage) ? 'summary' : 'summary_large_image' ?>">
    <meta name="twitter:title"       content="<?= $fullTitle ?>">
    <meta name="twitter:description" content="<?= $fullDesc ?>">
    <?php if (!empty($ogImage)): ?>
    <meta name="twitter:image"       content="<?= e($ogImage) ?>">
    <?php endif; ?>

    <meta name="referrer" content="strict-origin-when-cross-origin">

    <?php /*
      The home-page books photo used to be preloaded with fetchpriority="high",
      back when it was a full-screen hero and therefore the Largest Contentful
      Paint element. It now sits BELOW the name and title, so the LCP element is
      the heading text instead. Preloading the photo here would make it compete
      with the stylesheet and fonts for bandwidth and delay the very content the
      visitor sees first, so the preload has been removed on purpose.
      The <picture> in index.php still serves responsive WebP/JPEG derivatives.
    */ ?>

    <!-- Fonts -->
        <?php // Self-hosted: see assets/css/fonts.css. Loading these from
              // Google sent every visitor's IP there before they had answered
              // the consent banner. ?>
        <link rel="stylesheet" href="<?= asset('assets/css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">

    <?php
    // Person schema — present on every page
    $scholarUrl = getSetting('google_scholar_url', '');
    $rgUrl      = getSetting('researchgate_url', '');
    $orcidUrl   = getSetting('orcid_url', '');
    $sameAs     = array_values(array_filter([$scholarUrl, $rgUrl, $orcidUrl]));

    $personSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'Person',
        'name'       => 'Rey Koslowski',
        'givenName'  => 'Rey',
        'familyName' => 'Koslowski',
        'url'        => SITE_URL . '/',
        'jobTitle'   => 'Professor of Political Science',
        'affiliation' => [
            '@type' => 'CollegeOrUniversity',
            'name'  => 'University at Albany (SUNY)',
            'url'   => 'https://www.albany.edu/',
        ],
        'alumniOf' => [
            '@type' => 'CollegeOrUniversity',
            'name'  => 'University of Pennsylvania',
        ],
        'email'     => 'rkoslowski@albany.edu',
        'telephone' => '518-442-5314',
        'knowsAbout' => [
            'International Relations', 'Migration Policy', 'Homeland Security',
            'European Integration', 'International Organization',
        ],
    ];
    if (!empty($sameAs))          $personSchema['sameAs'] = $sameAs;
    if ($_headerProfileImg)       $personSchema['image']  = SITE_URL . '/' . $_headerProfileImg['image_path'];
    ?>
    <script type="application/ld+json"><?= json_encode($personSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <?php if (!empty($pageSchema)): ?>
    <script type="application/ld+json"><?= json_encode($pageSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php endif; ?>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
<?php if (isLoggedIn()): ?>
<a href="<?= SITE_URL ?>/admin/dashboard.php" class="admin-back-btn" title="Back to Admin Dashboard">
    ⚙ Admin
</a>
<?php endif; ?>
