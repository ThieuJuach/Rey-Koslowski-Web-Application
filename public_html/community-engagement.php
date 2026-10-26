<?php
$pageTitle = 'Community Engagement';
$metaDescription = 'Community engagement and public outreach by Rey Koslowski on migration policy, border security, and international relations at University at Albany (SUNY).';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$items = getCommunityEngagementItems();
$featuredImage = getPageFeaturedImage('community-engagement');
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
        <h1 class="page-header__title">Community Engagement</h1>
        <p class="page-header__subtitle">
            Public outreach, workshops, and community involvement.
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
            <?php if (!empty($items)): ?>
            <div class="blog-posts-list">
                <?php foreach ($items as $item): ?>
                <article class="blog-post-card">
                    <div class="blog-post-card__header">
                        <?php if ($item['category']): ?>
                        <span class="blog-post-card__category"><?= e($item['category']) ?></span>
                        <?php endif; ?>
                        <?php if ($item['event_date']): ?>
                        <span class="blog-post-card__date"><?= date('M j, Y', strtotime($item['event_date'])) ?></span>
                        <?php endif; ?>
                        <?php if ($item['location']): ?>
                        <span class="blog-post-card__date"><?= e($item['location']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['image_path'])): ?>
                    <img class="blog-post-card__media"
                         src="<?= SITE_URL . '/' . e($item['image_path']) ?>"
                         alt="<?= e($item['title']) ?>" loading="lazy">
                    <?php endif; ?>
                    <h3 class="blog-post-card__title">
                        <?php if ($item['link_url']): ?>
                        <a href="<?= e($item['link_url']) ?>" target="_blank" rel="noopener"><?= e($item['title']) ?></a>
                        <?php else: ?>
                        <?= e($item['title']) ?>
                        <?php endif; ?>
                    </h3>
                    <?php if ($item['content']): ?>
                    <div class="blog-post-card__content prose">
                        <?= formatText($item['content']) ?>
                    </div>
                    <?php elseif ($item['excerpt']): ?>
                    <p class="blog-post-card__excerpt"><?= e($item['excerpt']) ?></p>
                    <?php endif; ?>
                    <?php if ($item['link_url']): ?>
                    <a href="<?= e($item['link_url']) ?>" target="_blank" rel="noopener" class="blog-post-card__link">Learn more →</a>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">Community engagement items coming soon.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>