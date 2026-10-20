<?php
/**
 * Admin: site analytics.
 *
 * Reads the first-party page_views table written by api/track.php. Everything is
 * aggregate — there is no per-visitor view, because the data deliberately cannot
 * support one (see the privacy notes in the v3 migration).
 *
 * Crawlers are excluded by default so the figures reflect people; the toggle
 * below includes them when you want to see raw traffic.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// ── Options ──────────────────────────────────────────────────────────────
// Whitelisted so neither value can reach SQL as arbitrary input.
$days      = sanitizeInt($_GET['days'] ?? 30, 1, 365) ?? 30;
$days      = in_array($days, [7, 30, 90, 365], true) ? $days : 30;
$includeBots = isset($_GET['bots']) && $_GET['bots'] === '1';

$botClause = $includeBots ? '' : ' AND is_bot = 0';

/*
 * Has the migration been applied? Without this the page would fatal with a raw
 * SQL error on a site that has not run schema_update_v3_analytics.sql yet.
 */
$tableReady = false;
try {
    $pdo->query("SELECT 1 FROM page_views LIMIT 1");
    $tableReady = true;
} catch (PDOException $e) {
    $tableReady = false;
}

$totals = ['views' => 0, 'visitors' => 0];
$topPages = $topReferrers = $daily = [];

if ($tableReady) {
    // Totals for the window.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
         FROM page_views
         WHERE viewed_at >= NOW() - INTERVAL ? DAY {$botClause}"
    );
    $stmt->execute([$days]);
    $totals = $stmt->fetch() ?: $totals;

    // Most-visited pages.
    $stmt = $pdo->prepare(
        "SELECT path, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
         FROM page_views
         WHERE viewed_at >= NOW() - INTERVAL ? DAY {$botClause}
         GROUP BY path ORDER BY views DESC LIMIT 15"
    );
    $stmt->execute([$days]);
    $topPages = $stmt->fetchAll();

    // Where people arrived from. NULL = internal navigation or no referrer.
    $stmt = $pdo->prepare(
        "SELECT referrer_host, COUNT(*) AS views
         FROM page_views
         WHERE viewed_at >= NOW() - INTERVAL ? DAY {$botClause}
           AND referrer_host IS NOT NULL
         GROUP BY referrer_host ORDER BY views DESC LIMIT 10"
    );
    $stmt->execute([$days]);
    $topReferrers = $stmt->fetchAll();

    // Daily series for the chart.
    $stmt = $pdo->prepare(
        "SELECT DATE(viewed_at) AS d, COUNT(*) AS views
         FROM page_views
         WHERE viewed_at >= NOW() - INTERVAL ? DAY {$botClause}
         GROUP BY DATE(viewed_at) ORDER BY d ASC"
    );
    $stmt->execute([$days]);
    $daily = $stmt->fetchAll();
}

// Tallest bar sets the scale for the chart.
$peak = 0;
foreach ($daily as $d) { $peak = max($peak, (int) $d['views']); }
$peak = $peak ?: 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Analytics | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-header">
            <h1 class="admin-header__title">Analytics</h1>
            <div class="admin-header__actions">
                <a href="<?= SITE_URL ?>/" target="_blank" class="btn btn--outline">View Site &#8599;</a>
            </div>
        </div>

        <?php if (!$tableReady): ?>
            <div class="alert alert--error">
                The <code>page_views</code> table does not exist yet. Apply
                <code>database/schema_update_v3_analytics.sql</code> in phpMyAdmin,
                then reload this page.
            </div>
        <?php else: ?>

        <!-- Range + bot filter -->
        <div class="admin-card">
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
                <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'] as $d => $label): ?>
                    <a href="?days=<?= $d ?><?= $includeBots ? '&bots=1' : '' ?>"
                       class="btn <?= $days === $d ? 'btn--primary' : 'btn--outline' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                <span style="flex:1"></span>
                <a href="?days=<?= $days ?><?= $includeBots ? '' : '&bots=1' ?>"
                   class="btn <?= $includeBots ? 'btn--accent' : 'btn--outline' ?>">
                    <?= $includeBots ? 'Excluding bots' : 'Include bots' ?>
                </a>
            </div>
        </div>

        <!-- Headline numbers -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem;">
            <div class="admin-card" style="text-align:center;">
                <p style="font-size:2rem;font-weight:700;color:var(--color-primary);"><?= number_format((int) $totals['views']) ?></p>
                <p style="font-size:0.875rem;color:var(--color-muted);">Page views</p>
            </div>
            <div class="admin-card" style="text-align:center;">
                <p style="font-size:2rem;font-weight:700;color:var(--color-primary);"><?= number_format((int) $totals['visitors']) ?></p>
                <p style="font-size:0.875rem;color:var(--color-muted);">Unique visitors</p>
            </div>
            <div class="admin-card" style="text-align:center;">
                <p style="font-size:2rem;font-weight:700;color:var(--color-primary);">
                    <?= $totals['visitors'] ? number_format($totals['views'] / $totals['visitors'], 1) : '0' ?>
                </p>
                <p style="font-size:0.875rem;color:var(--color-muted);">Pages per visitor</p>
            </div>
        </div>

        <!-- Daily views: plain CSS bars, no charting library -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Views per day</h2></div>
            <?php if (empty($daily)): ?>
                <p style="color:var(--color-muted);font-size:0.875rem;">No data for this period yet.</p>
            <?php else: ?>
            <div style="display:flex;align-items:flex-end;gap:2px;height:160px;overflow-x:auto;padding-top:0.5rem;">
                <?php foreach ($daily as $d): ?>
                    <div title="<?= e($d['d']) ?>: <?= (int) $d['views'] ?> views"
                         style="flex:1 0 6px;min-width:6px;border-radius:2px 2px 0 0;
                                background:var(--color-primary);
                                height:<?= max(2, (int) round((int) $d['views'] / $peak * 100)) ?>%;"></div>
                <?php endforeach; ?>
            </div>
            <p style="font-size:0.75rem;color:var(--color-muted);margin-top:0.5rem;">
                <?= e((string) $daily[0]['d']) ?> &ndash; <?= e((string) $daily[count($daily) - 1]['d']) ?>
                &middot; peak <?= number_format($peak) ?> views/day
            </p>
            <?php endif; ?>
        </div>

        <!-- Top pages -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Most visited pages</h2></div>
            <?php if (empty($topPages)): ?>
                <p style="color:var(--color-muted);font-size:0.875rem;">No data yet.</p>
            <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>Page</th><th style="text-align:right;">Views</th><th style="text-align:right;">Visitors</th></tr></thead>
                <tbody>
                <?php foreach ($topPages as $row): ?>
                    <tr>
                        <td><a href="<?= SITE_URL . e($row['path']) ?>" target="_blank" rel="noopener"><?= e($row['path']) ?></a></td>
                        <td style="text-align:right;"><?= number_format((int) $row['views']) ?></td>
                        <td style="text-align:right;"><?= number_format((int) $row['visitors']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Referrers -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Where visitors came from</h2></div>
            <?php if (empty($topReferrers)): ?>
                <p style="color:var(--color-muted);font-size:0.875rem;">
                    No external referrers recorded yet. Visits typed directly or arriving
                    without a referrer are not listed here.
                </p>
            <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>Source</th><th style="text-align:right;">Views</th></tr></thead>
                <tbody>
                <?php foreach ($topReferrers as $row): ?>
                    <tr>
                        <td><?= e($row['referrer_host']) ?></td>
                        <td style="text-align:right;"><?= number_format((int) $row['views']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="admin-card">
            <p style="font-size:0.8125rem;color:var(--color-muted);margin:0;">
                Figures cover visitors who accepted the consent banner. No IP addresses or
                user-agent strings are stored; a visitor is represented only by a digest
                that changes daily, so the same person cannot be followed across days.
            </p>
        </div>

        <?php endif; ?>
    </div>
</div>
</body></html>
