<?php
/**
 * Media Appearances Page
 * 
 * Shows media items with inline video playback, PDFs
 */
$pageTitle = 'Media Appearances';
$metaDescription = 'Media appearances, interviews, and commentary by Rey Koslowski on migration, border security, and international relations.';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$mediaItems = getMediaAppearances();
$featuredImage = getPageFeaturedImage('media-appearances');
$bgImage = $featuredImage ? SITE_URL . '/' . $featuredImage['image_path'] : '';
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
        <h1 class="page-header__title">Media Appearances</h1>
        <p class="page-header__subtitle">
            Interviews, commentary, and media coverage on migration, border security, and international relations.
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
            <div class="section__divider"></div>
            <h2 class="section__title">Appearances</h2>
            <p class="text-lg text-muted" style="margin-bottom: 3rem;">
                Dr. Koslowski is frequently interviewed and widely quoted in major publications
                and broadcast media on topics related to migration, border security, and
                international relations.
            </p>

            <?php if (!empty($mediaItems)): ?>
            <div class="media-list">
                <?php foreach ($mediaItems as $item): ?>
                <div class="media-card">
                    <div class="media-card__content">
                        <div style="flex:1;">
                            <?php if ($item['url'] || $item['link_url']): ?>
                            <a href="<?= e($item['link_url'] ?: $item['url']) ?>" target="_blank" rel="noopener" class="media-card__title-link">
                                <?= e($item['title']) ?>
                            </a>
                            <?php else: ?>
                            <span class="media-card__citation"><?= e($item['title']) ?></span>
                            <?php endif; ?>
                            <?php if ($item['outlet']): ?>
                            <span class="media-card__outlet"> — <?= e($item['outlet']) ?></span>
                            <?php endif; ?>
                            <?php if ($item['description']): ?>
                            <p class="media-card__desc"><?= formatText($item['description']) ?></p>
                            <?php endif; ?>
                            <?php /* PDF link */ ?>
                            <?php if (!empty($item['pdf_path'])): ?>
                            <div style="margin-top:0.5rem;"><?= renderPdfLink($item['pdf_path']) ?></div>
                            <?php endif; ?>
                            <?php /* Inline video — plays on site instead of redirecting */ ?>
                            <?php if (!empty($item['video_url'])): ?>
                            <?= renderVideoEmbed($item['video_url']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="media-card__badges">
                        <span class="media-card__type"><?= e($item['type']) ?></span>
                        <?php if ($item['year']): ?>
                        <span class="pub-card__year"><?= e($item['year']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">Media appearances coming soon.</p>
            <?php endif; ?>

            <?= renderPagePdfs('media-appearances') ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
