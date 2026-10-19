<?php
/**
 * Presentations Page
 * 
 * Shows upcoming/past presentations with inline videos, PDFs, cover images
 */
$pageTitle = 'Presentations';
$metaDescription = 'Conference presentations, invited talks, workshops, and keynotes by Rey Koslowski on international migration, border security, and global governance.';
require_once 'config.php';
require_once 'includes/auth.php';

// Queries before header so structured data and OG image are available in <head>
$upcoming      = getPresentations('upcoming');
$completed     = getPresentations('completed');
$featuredImage = getPageFeaturedImage('presentations');
if ($featuredImage) $ogImage = SITE_URL . '/' . $featuredImage['image_path'];

// Build Event structured data
$_allPres = array_merge($upcoming, $completed);
if (!empty($_allPres)) {
    $schemaItems = [];
    foreach ($_allPres as $pres) {
        if (empty($pres['presentation_date'])) continue;
        $item = [
            '@type'       => 'Event',
            'name'        => $pres['title'],
            'startDate'   => $pres['presentation_date'],
            'eventStatus' => $pres['status'] === 'upcoming'
                ? 'https://schema.org/EventScheduled'
                : 'https://schema.org/EventCompleted',
            'performer'   => ['@type' => 'Person', 'name' => 'Rey Koslowski'],
        ];
        if (!empty($pres['end_date']))    $item['endDate']   = $pres['end_date'];
        if (!empty($pres['event_name'])) $item['organizer'] = ['@type' => 'Organization', 'name' => $pres['event_name']];
        if (!empty($pres['location']))   $item['location']  = ['@type' => 'Place', 'name' => $pres['location']];
        if (!empty($pres['link_url']))   $item['url']       = $pres['link_url'];
        $schemaItems[] = $item;
    }
    if (!empty($schemaItems)) {
        $pageSchema = ['@context' => 'https://schema.org', '@graph' => $schemaItems];
    }
}

include 'includes/header.php';
include 'includes/nav.php';
?>

<div class="page-header <?= $featuredImage ? 'page-header--has-bg' : '' ?>"
     <?php if ($featuredImage): ?>
         style="background-image: url('<?= SITE_URL . '/' . e($featuredImage['image_path']) ?>');"
     <?php endif; ?>
>
    <?php if ($featuredImage): ?>
        <div class="page-header__overlay"></div>
    <?php endif; ?>

    <div class="container">
        <div class="page-header__divider"></div>
        <h1 class="page-header__title">Presentations</h1>
        <p class="page-header__subtitle">
            Selected academic talks, conference presentations, and invited lectures.
        </p>

        <?php if ($featuredImage && !empty($featuredImage['caption'])): ?>
            <p class="page-header__caption">
                <?= e($featuredImage['caption']) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="content-max">
            <?php if (!empty($upcoming)): ?>
            <div class="section__divider"></div>
            <h2 class="section__title">Upcoming</h2>
            <div class="publications-list" style="margin-bottom: 4rem;">
                <?php foreach ($upcoming as $pres): ?>
                <div class="pub-card">
                    <?php if (!empty($pres['image_path'])): ?>
                    <img src="<?= SITE_URL . '/' . e($pres['image_path']) ?>" alt="<?= e($pres['title']) ?>" style="width:100px;height:auto;border-radius:var(--radius);flex-shrink:0;">
                    <?php endif; ?>
                    <div class="pub-card__content">
                        <?php if ($pres['link_url']): ?>
                        <a href="<?= e($pres['link_url']) ?>" target="_blank" rel="noopener" class="pub-card__title-link"><h4 class="pub-card__title"><?= e($pres['title']) ?></h4></a>
                        <?php else: ?>
                        <h4 class="pub-card__title"><?= e($pres['title']) ?></h4>
                        <?php endif; ?>
                        <p class="pub-card__meta"><?= $pres['event_name'] ? e($pres['event_name']) : '' ?><?= $pres['location'] ? ' · ' . e($pres['location']) : '' ?></p>
                        <?php if ($pres['presentation_date']): ?>
                        <p class="pub-card__meta" style="color:var(--color-accent); font-weight:500;"><?= date('M j, Y', strtotime($pres['presentation_date'])) ?><?= $pres['end_date'] ? ' – ' . date('M j, Y', strtotime($pres['end_date'])) : '' ?></p>
                        <?php endif; ?>
                        <?php if ($pres['description']): ?>
                        <p class="pub-card__meta" style="margin-top:0.5rem;"><?= formatText($pres['description']) ?></p>
                        <?php endif; ?>
                        <div class="pub-card__links">
                            <?php if (!empty($pres['pdf_path'])): ?>
                            <?= renderPdfLink($pres['pdf_path'], 'Slides/PDF') ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($pres['video_url'])): ?>
                        <?= renderVideoEmbed($pres['video_url']) ?>
                        <?php endif; ?>
                    </div>
                    <span class="pub-card__year"><?= e(ucfirst($pres['type'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="section__divider"></div>
            <h2 class="section__title"><?= !empty($upcoming) ? 'Past Presentations' : 'Presentations' ?></h2>
            <?php if (!empty($completed)): ?>
            <div class="publications-list">
                <?php foreach ($completed as $pres): ?>
                <div class="pub-card">
                    <?php if (!empty($pres['image_path'])): ?>
                    <img src="<?= SITE_URL . '/' . e($pres['image_path']) ?>" alt="<?= e($pres['title']) ?>" style="width:100px;height:auto;border-radius:var(--radius);flex-shrink:0;">
                    <?php endif; ?>
                    <div class="pub-card__content">
                        <?php if ($pres['link_url']): ?>
                        <a href="<?= e($pres['link_url']) ?>" target="_blank" rel="noopener" class="pub-card__title-link"><h4 class="pub-card__title"><?= e($pres['title']) ?></h4></a>
                        <?php else: ?>
                        <h4 class="pub-card__title"><?= e($pres['title']) ?></h4>
                        <?php endif; ?>
                        <p class="pub-card__meta"><?= $pres['event_name'] ? e($pres['event_name']) : '' ?><?= $pres['location'] ? ' · ' . e($pres['location']) : '' ?></p>
                        <?php if ($pres['presentation_date']): ?>
                        <p class="pub-card__meta"><?= date('M j, Y', strtotime($pres['presentation_date'])) ?><?= $pres['end_date'] ? ' – ' . date('M j, Y', strtotime($pres['end_date'])) : '' ?></p>
                        <?php endif; ?>
                        <?php if ($pres['description']): ?>
                        <p class="pub-card__meta" style="margin-top:0.5rem;"><?= formatText($pres['description']) ?></p>
                        <?php endif; ?>
                        <div class="pub-card__links">
                            <?php if (!empty($pres['pdf_path'])): ?>
                            <?= renderPdfLink($pres['pdf_path'], 'Slides/PDF') ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($pres['video_url'])): ?>
                        <?= renderVideoEmbed($pres['video_url']) ?>
                        <?php endif; ?>
                    </div>
                    <span class="pub-card__year"><?= e(ucfirst($pres['type'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">Presentations coming soon.</p>
            <?php endif; ?>

            <?= renderPagePdfs('presentations') ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
