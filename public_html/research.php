<?php
/**
 * Research Page
 * 
 * Shows ongoing/completed projects with cover images, inline videos, PDFs
 */
$pageTitle = 'Research';
$metaDescription = 'Rey Koslowski\'s research on international relations, global mobility regimes, migration policy, and homeland security.';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$ongoingProjects = getResearchProjects('ongoing');
$completedProjects = getResearchProjects('completed');
$featuredImage = getPageFeaturedImage('research');
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
        <h1 class="page-header__title">Research</h1>
        <p class="page-header__subtitle">
            Ongoing and completed research on international relations, migration, and security.
        </p>

        <?php if ($featuredImage && !empty($featuredImage['caption'])): ?>
            <p class="page-header__caption">
                <?= e($featuredImage['caption']) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php // Optional highlighted panel. Renders nothing unless text or an
      // image has been set in the admin for this page. ?>
<?= renderFeaturedBlock('research') ?>

<section class="section">
    <div class="container">
        <div class="content-max">

            <div class="section__divider"></div>
            <h2 class="section__title">Ongoing Projects</h2>
            <?php if (!empty($ongoingProjects)): ?>
            <div class="projects-grid">
                <?php foreach ($ongoingProjects as $project): ?>
                <div class="card <?= $project['link_url'] ? 'card--link' : '' ?>">
                    <?php if ($project['link_url']): ?>
                    <a href="<?= e($project['link_url']) ?>" target="_blank" rel="noopener" style="text-decoration:none;color:inherit;display:block;">
                    <?php endif; ?>
                    <?php if (!empty($project['image_path'])): ?>
                    <img src="<?= SITE_URL . '/' . e($project['image_path']) ?>" alt="<?= e($project['title']) ?>" class="card__cover">
                    <?php endif; ?>
                    <h3 class="card__title"><?= e($project['title']) ?></h3>
                    <?php if ($project['description']): ?>
                    <p class="card__text"><?= formatText($project['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($project['funding_source']): ?>
                    <p class="card__text" style="margin-top:0.5rem;font-style:italic;">Funded by: <?= e($project['funding_source']) ?></p>
                    <?php endif; ?>
                    <?php if ($project['link_url']): ?>
                    <span class="card__arrow">View →</span>
                    </a>
                    <?php endif; ?>
                    <?php /* Inline video if provided */ ?>
                    <?php if (!empty($project['video_path'])): ?>
                    <?= renderVideoEmbed($project['video_path']) ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">Research projects coming soon.</p>
            <?php endif; ?>

            <h2 class="section__title" style="margin-top: 4rem;">Past Projects</h2>
            <?php if (!empty($completedProjects)): ?>
            <div class="past-projects">
                <?php foreach ($completedProjects as $project): ?>
                <div class="past-project">
                    <?php if ($project['link_url']): ?>
                    <a href="<?= e($project['link_url']) ?>" target="_blank" rel="noopener" style="text-decoration:none;color:inherit;">
                    <?php endif; ?>
                    <h4 class="past-project__title"><?= e($project['title']) ?></h4>
                    <?php if ($project['description']): ?>
                    <p class="past-project__text"><?= formatText($project['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($project['link_url']): ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($project['video_path'])): ?>
                    <?= renderVideoEmbed($project['video_path']) ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">Past projects will appear here once added.</p>
            <?php endif; ?>

            <?= renderPagePdfs('research') ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
