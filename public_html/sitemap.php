<?php
/**
 * Dynamic XML sitemap.
 *
 * Replaces the previous hand-written sitemap.xml, which carried a hard-coded
 * <lastmod> of 2026-06-08 on every URL. A stale or future-dated lastmod is actively
 * counter-productive: Google learns the dates are meaningless and stops using them
 * to prioritise recrawls, so genuinely updated pages sit unindexed.
 *
 * Each URL's lastmod is now derived from the newest updated_at of the content that
 * actually appears on it, falling back to the PHP file's own mtime.
 *
 * /sitemap.xml is rewritten to this file in .htaccess, so the URL already submitted
 * to Google Search Console keeps working.
 */
/*
 * Buffer everything before the XML declaration.
 *
 * An XML document is invalid if a single byte precedes "<?xml" — and the classic way
 * that happens is a UTF-8 byte-order mark on an included PHP file, which Windows
 * editors add silently. Buffering lets us discard any such stray output and still set
 * headers afterwards, so a stray BOM cannot break the sitemap for Search Console.
 */
ob_start();

require_once __DIR__ . '/config.php';

ob_end_clean();

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex'); // Index the pages, not the sitemap file itself.

/**
 * Newest updated_at across a set of tables, as a Y-m-d date.
 * Missing tables/columns are ignored so the sitemap can never 500.
 */
function newestUpdate(array $tables, string $fallbackFile): string {
    global $pdo;
    $newest = null;

    foreach ($tables as $table) {
        try {
            // Table names come from this file only — never from user input.
            $row = $pdo->query("SELECT MAX(updated_at) AS m FROM `{$table}`")->fetch();
            if (!empty($row['m'])) {
                $ts = strtotime($row['m']);
                if ($ts && ($newest === null || $ts > $newest)) $newest = $ts;
            }
        } catch (Throwable $e) {
            // Table may not exist or may have no updated_at column — skip it.
        }
    }

    if ($newest === null) {
        $newest = is_file($fallbackFile) ? filemtime($fallbackFile) : time();
    }

    // Never advertise a future date.
    return date('Y-m-d', min($newest, time()));
}

/*
 * Public URLs, in rough order of importance.
 *   file       => the script on disk (used for the mtime fallback)
 *   tables     => content tables whose updated_at drives <lastmod>
 *   priority   => relative importance within this site
 *   changefreq => a hint only; Google largely ignores it
 */
$pages = [
    ['loc' => '/',                          'file' => 'index.php',
     'tables' => ['pages', 'site_settings', 'research_projects', 'publications', 'courses', 'presentations'],
     'priority' => '1.0', 'changefreq' => 'weekly'],

    ['loc' => '/publications.php',          'file' => 'publications.php',
     'tables' => ['publications'],          'priority' => '0.9', 'changefreq' => 'monthly'],

    ['loc' => '/research.php',              'file' => 'research.php',
     'tables' => ['research_projects'],     'priority' => '0.9', 'changefreq' => 'monthly'],

    ['loc' => '/presentations.php',         'file' => 'presentations.php',
     'tables' => ['presentations'],         'priority' => '0.8', 'changefreq' => 'monthly'],

    ['loc' => '/teachings.php',             'file' => 'teachings.php',
     'tables' => ['courses'],               'priority' => '0.8', 'changefreq' => 'monthly'],

    ['loc' => '/media-appearances.php',     'file' => 'media-appearances.php',
     'tables' => ['media_appearances'],     'priority' => '0.7', 'changefreq' => 'monthly'],

    ['loc' => '/migration-studies.php',     'file' => 'migration-studies.php',
     'tables' => ['migration_faculty', 'migration_courses'],
     'priority' => '0.7', 'changefreq' => 'monthly'],

    ['loc' => '/professional-service.php',  'file' => 'professional-service.php',
     'tables' => ['professional_service_items'], 'priority' => '0.6', 'changefreq' => 'yearly'],

    ['loc' => '/community-engagement.php',  'file' => 'community-engagement.php',
     'tables' => ['community_engagement_items'], 'priority' => '0.6', 'changefreq' => 'yearly'],

    ['loc' => '/contact.php',               'file' => 'contact.php',
     'tables' => ['site_settings'],         'priority' => '0.5', 'changefreq' => 'yearly'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($pages as $page) {
    $lastmod = newestUpdate($page['tables'], __DIR__ . '/' . $page['file']);
    echo "  <url>\n";
    echo "    <loc>" . e(SITE_URL . $page['loc']) . "</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>{$page['changefreq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
