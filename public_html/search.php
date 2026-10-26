<?php
/**
 * Site search.
 *
 * Covers titles and descriptions only, by design - PDF contents are not indexed.
 * Extracting text from two dozen PDFs on every query would be slow and would
 * need a dependency the host does not have; the documents are reachable from
 * the records that do match.
 *
 * SECURITY
 * - The term is length-capped and bound as a parameter; no SQL is built from it
 * - Every column searched is named in code, never supplied by the caller
 * - Output is escaped, including the highlighted excerpt
 * - Rate limited, because this is an unauthenticated endpoint that runs several
 *   LIKE queries per request
 */
$pageTitle       = 'Search';
$metaDescription = 'Search publications, research, teaching, presentations and media by Rey Koslowski.';
$robotsOverride  = 'noindex, follow';   // a results page is not useful in an index

require_once 'config.php';
require_once 'includes/auth.php';

$term    = trim((string) ($_GET['q'] ?? ''));
$results = [];
$total   = 0;
$tooMany = false;

/*
 * A leading-wildcard LIKE cannot use an index, so a very short term would scan
 * every row of every table for almost no value. Two characters is the floor.
 */
$MIN_TERM = 2;
$MAX_TERM = 100;

if ($term !== '') {
    if (mb_strlen($term) > $MAX_TERM) {
        $term = mb_substr($term, 0, $MAX_TERM);
    }

    // Cheap to run, but not free: cap how often one IP can do it.
    $tooMany = isRateLimited('search_' . getClientIp(), 30, 60);

    if (!$tooMany && mb_strlen($term) >= $MIN_TERM) {
        /*
         * Each entry: the table, the columns to match, how to build a title and
         * a snippet, and where the result links to. Declaring them here keeps
         * every identifier in code - the caller only ever supplies the term.
         */
        $sources = [
            [
                'label'   => 'Publications',
                'sql'     => "SELECT title, authors, journal_or_publisher, abstract, year, link_url, url, doi
                              FROM publications
                              WHERE is_visible = 1 AND (title LIKE ? OR abstract LIKE ? OR authors LIKE ?
                                    OR journal_or_publisher LIKE ?)
                              ORDER BY year DESC LIMIT 40",
                'title'   => fn($r) => $r['title'],
                'snippet' => fn($r) => $r['abstract'] ?: trim(($r['authors'] ?? '') . ' · ' . ($r['journal_or_publisher'] ?? ''), ' ·'),
                'href'    => fn($r) => $r['link_url'] ?: ($r['url'] ?: (!empty($r['doi']) ? 'https://doi.org/' . $r['doi'] : SITE_URL . '/publications.php')),
                'meta'    => fn($r) => $r['year'] ?: '',
            ],
            [
                'label'   => 'Research',
                'sql'     => "SELECT title, description, link_url FROM research_projects
                              WHERE is_visible = 1 AND (title LIKE ? OR description LIKE ?)
                              ORDER BY display_order ASC LIMIT 20",
                'title'   => fn($r) => $r['title'],
                'snippet' => fn($r) => $r['description'],
                'href'    => fn($r) => $r['link_url'] ?: SITE_URL . '/research.php',
                'meta'    => fn($r) => '',
            ],
            [
                'label'   => 'Teaching',
                'sql'     => "SELECT code, title, description, link_url FROM courses
                              WHERE is_visible = 1 AND (title LIKE ? OR description LIKE ? OR code LIKE ?)
                              ORDER BY display_order ASC LIMIT 20",
                'title'   => fn($r) => trim(($r['code'] ? $r['code'] . ' — ' : '') . $r['title']),
                'snippet' => fn($r) => $r['description'],
                'href'    => fn($r) => $r['link_url'] ?: SITE_URL . '/teachings.php',
                'meta'    => fn($r) => '',
            ],
            [
                'label'   => 'Presentations',
                'sql'     => "SELECT title, event_name, location, description, presentation_date, link_url
                              FROM presentations
                              WHERE is_visible = 1 AND (title LIKE ? OR description LIKE ?
                                    OR event_name LIKE ? OR location LIKE ?)
                              ORDER BY presentation_date DESC LIMIT 20",
                'title'   => fn($r) => $r['title'],
                'snippet' => fn($r) => $r['description'] ?: trim(($r['event_name'] ?? '') . ' · ' . ($r['location'] ?? ''), ' ·'),
                'href'    => fn($r) => $r['link_url'] ?: SITE_URL . '/presentations.php',
                'meta'    => fn($r) => $r['presentation_date'] ? date('Y', strtotime($r['presentation_date'])) : '',
            ],
            [
                'label'   => 'Media',
                'sql'     => "SELECT title, outlet, description, year, url, link_url FROM media_appearances
                              WHERE is_visible = 1 AND (title LIKE ? OR description LIKE ? OR outlet LIKE ?)
                              ORDER BY year DESC LIMIT 20",
                'title'   => fn($r) => $r['title'],
                'snippet' => fn($r) => $r['description'] ?: ($r['outlet'] ?? ''),
                'href'    => fn($r) => $r['link_url'] ?: ($r['url'] ?: SITE_URL . '/media-appearances.php'),
                'meta'    => fn($r) => $r['year'] ?: '',
            ],
            [
                'label'   => 'Professional Service',
                'sql'     => "SELECT title, content, excerpt, link_url FROM professional_service_items
                              WHERE is_visible = 1 AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)
                              ORDER BY display_order ASC LIMIT 20",
                'title'   => fn($r) => $r['title'],
                'snippet' => fn($r) => $r['excerpt'] ?: $r['content'],
                'href'    => fn($r) => $r['link_url'] ?: SITE_URL . '/professional-service.php',
                'meta'    => fn($r) => '',
            ],
            [
                'label'   => 'Community Engagement',
                'sql'     => "SELECT title, content, excerpt, link_url FROM community_engagement_items
                              WHERE is_visible = 1 AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)
                              ORDER BY display_order ASC LIMIT 20",
                'title'   => fn($r) => $r['title'],
                'snippet' => fn($r) => $r['excerpt'] ?: $r['content'],
                'href'    => fn($r) => $r['link_url'] ?: SITE_URL . '/community-engagement.php',
                'meta'    => fn($r) => '',
            ],
        ];

        $like = '%' . $term . '%';

        foreach ($sources as $src) {
            try {
                /*
                 * Positional parameters, bound once per occurrence.
                 *
                 * A named placeholder cannot be reused in one statement when
                 * PDO::ATTR_EMULATE_PREPARES is false (it is, in db.php): MySQL
                 * native prepares require one marker per value, and reusing :q
                 * across the OR-ed columns fails with HY093. Counting the
                 * markers keeps each query free to search a different number of
                 * columns without the binding having to be maintained by hand.
                 */
                $stmt   = $pdo->prepare($src['sql']);
                $params = array_fill(0, substr_count($src['sql'], '?'), $like);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
            } catch (PDOException $e) {
                // A missing optional table must not take the whole page down.
                error_log('[search] ' . $src['label'] . ': ' . $e->getMessage());
                continue;
            }
            if (!$rows) continue;

            $group = [];
            foreach ($rows as $r) {
                $group[] = [
                    'title'   => (string) ($src['title'])($r),
                    'snippet' => (string) ($src['snippet'])($r),
                    'href'    => (string) ($src['href'])($r),
                    'meta'    => (string) ($src['meta'])($r),
                ];
            }
            $results[$src['label']] = $group;
            $total += count($group);
        }
    }
}

/**
 * Build a short plain-text excerpt centred on the match, with the term marked.
 *
 * The text is escaped FIRST and <mark> inserted afterwards, so stored content
 * can never inject markup through the results page.
 */
function searchSnippet(string $text, string $term, int $len = 180): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    if ($text === '') return '';

    $pos = mb_stripos($text, $term);
    if ($pos === false) {
        $out = mb_substr($text, 0, $len);
        return e($out) . (mb_strlen($text) > $len ? '…' : '');
    }

    $start  = max(0, $pos - 60);
    $out    = mb_substr($text, $start, $len);
    $prefix = $start > 0 ? '…' : '';
    $suffix = ($start + $len) < mb_strlen($text) ? '…' : '';

    $escaped = e($out);
    $escTerm = preg_quote(e($term), '/');
    $escaped = preg_replace('/(' . $escTerm . ')/i', '<mark>$1</mark>', $escaped, 3);

    return $prefix . $escaped . $suffix;
}

include 'includes/header.php';
include 'includes/nav.php';
?>

<div class="page-header">
    <div class="container">
        <div class="page-header__divider"></div>
        <h1 class="page-header__title">Search</h1>
        <p class="page-header__subtitle">
            Publications, research, teaching, presentations, media and service.
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="content-max">

            <form class="search-page__form" method="GET" action="<?= SITE_URL ?>/search.php" role="search">
                <label class="sr-only" for="q">Search this site</label>
                <input class="search-page__input" type="search" id="q" name="q"
                       value="<?= e($term) ?>" maxlength="<?= (int) $MAX_TERM ?>"
                       placeholder="Try: migration, borders, refugees…" autofocus>
                <button class="search-page__submit" type="submit">Search</button>
            </form>

            <?php if ($tooMany): ?>
                <p class="search-page__note">Too many searches in a short time. Please wait a moment and try again.</p>

            <?php elseif ($term !== '' && mb_strlen($term) < $MIN_TERM): ?>
                <p class="search-page__note">Please enter at least <?= (int) $MIN_TERM ?> characters.</p>

            <?php elseif ($term !== '' && $total === 0): ?>
                <p class="search-page__note">
                    No matches for &ldquo;<?= e($term) ?>&rdquo;. Titles and descriptions are searched;
                    the text inside PDFs is not.
                </p>

            <?php elseif ($total > 0): ?>
                <p class="search-page__count">
                    <?= (int) $total ?> result<?= $total === 1 ? '' : 's' ?> for &ldquo;<?= e($term) ?>&rdquo;
                </p>

                <?php foreach ($results as $label => $group): ?>
                <div class="search-group">
                    <h2 class="search-group__title"><?= e($label) ?> <span class="search-group__count"><?= count($group) ?></span></h2>
                    <?php foreach ($group as $row): ?>
                    <article class="search-result">
                        <h3 class="search-result__title">
                            <a href="<?= e($row['href']) ?>"<?= str_starts_with($row['href'], SITE_URL) ? '' : ' target="_blank" rel="noopener"' ?>>
                                <?= e($row['title']) ?>
                            </a>
                        </h3>
                        <?php if ($row['snippet'] !== ''): ?>
                        <p class="search-result__snippet"><?= searchSnippet($row['snippet'], $term) ?></p>
                        <?php endif; ?>
                        <?php if ($row['meta'] !== ''): ?>
                        <span class="search-result__meta"><?= e($row['meta']) ?></span>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
