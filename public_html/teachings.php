<?php
/**
 * Teaching Page
 * 
 * Shows courses with cover images, inline videos, PDFs/syllabi
 */
$pageTitle = 'Teaching';
$metaDescription = 'Courses taught by Rey Koslowski at University at Albany (SUNY) in political science, international relations, and migration.';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$courses = getCourses();
$ugCourses = array_filter($courses, fn($c) => $c['level'] === 'undergraduate');
$gradCourses = array_filter($courses, fn($c) => $c['level'] === 'graduate');
$featuredImage = getPageFeaturedImage('teaching');
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
        <h1 class="page-header__title">Teaching</h1>
        <p class="page-header__subtitle">
            Undergraduate and graduate courses at the University at Albany (SUNY).
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

            <?= renderPageIntro('teaching') ?>

            <div class="section__divider"></div>
            <h2 class="section__title">Courses</h2>

            <div class="courses-grid">
                <div>
                    <h3 class="courses__heading">Undergraduate Courses</h3>
                    <ul class="courses__list">
                        <?php if (!empty($ugCourses)): ?>
                            <?php foreach ($ugCourses as $course): ?>
                            <li>
                                <?php if ($course['link_url']): ?>
                                <a href="<?= e($course['link_url']) ?>" target="_blank" rel="noopener"><?= e(($course['code'] ? $course['code'] . ' — ' : '') . $course['title']) ?></a>
                                <?php else: ?>
                                <?= e(($course['code'] ? $course['code'] . ' — ' : '') . $course['title']) ?>
                                <?php endif; ?>
                                <?php if ($course['description']): ?>
                                <br><small style="color:var(--color-muted);"><?= formatText($course['description']) ?></small>
                                <?php endif; ?>
                                <?php /* Inline video */ ?>
                                <?php if (!empty($course['video_url'])): ?>
                                <?= renderVideoEmbed($course['video_url']) ?>
                                <?php endif; ?>
                                <?php /* PDF link */ ?>
                                <?php if (!empty($course['pdf_path'])): ?>
                                <?= renderPdfLink($course['pdf_path'], 'Syllabus') ?>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="courses__heading">Graduate Courses</h3>
                    <ul class="courses__list">
                        <?php if (!empty($gradCourses)): ?>
                            <?php foreach ($gradCourses as $course): ?>
                            <li>
                                <?php if ($course['link_url']): ?>
                                <a href="<?= e($course['link_url']) ?>" target="_blank" rel="noopener"><?= e(($course['code'] ? $course['code'] . ' — ' : '') . $course['title']) ?></a>
                                <?php else: ?>
                                <?= e(($course['code'] ? $course['code'] . ' — ' : '') . $course['title']) ?>
                                <?php endif; ?>
                                <?php if ($course['description']): ?>
                                <br><small style="color:var(--color-muted);"><?= formatText($course['description']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($course['video_url'])): ?>
                                <?= renderVideoEmbed($course['video_url']) ?>
                                <?php endif; ?>
                                <?php if (!empty($course['pdf_path'])): ?>
                                <?= renderPdfLink($course['pdf_path'], 'Syllabus') ?>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <?= renderPagePdfs('teaching') ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
