<?php
$pageTitle = 'Professional Service';
$metaDescription = 'Professional service activities by Rey Koslowski, including editorial roles, advisory positions, and contributions to academic organizations in international relations.';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$page = getPageContent('professional-service');
$items = getProfessionalServiceItems();
$featuredImage = getPageFeaturedImage('professional-service');
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
        <h1 class="page-header__title">Professional Service</h1>
        <p class="page-header__subtitle">
            Contributions to the academic community and field of political science.
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
            <?php if ($page && $page['content']): ?>
            <div class="prose" style="margin-bottom: 3rem;">
                <?= formatText($page['content']) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($items)): ?>
            <div class="blog-posts-list">
                <?php foreach ($items as $item): ?>
                <article class="blog-post-card">
                    <div class="blog-post-card__header">
                        <?php if ($item['category']): ?>
                        <span class="blog-post-card__category"><?= e($item['category']) ?></span>
                        <?php endif; ?>
                        <?php if ($item['start_year']): ?>
                        <span class="blog-post-card__date">
                            <?= e($item['start_year']) ?><?= $item['end_year'] ? '–' . e($item['end_year']) : '–Present' ?>
                        </span>
                        <?php endif; ?>
                    </div>
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
            <p class="text-muted">Professional service items coming soon.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>