<?php
$pageTitle = 'Migration Studies';
$metaDescription = 'Migration studies at University at Albany (SUNY) - faculty directory and courses on international migration.';
require_once 'config.php';
require_once 'includes/auth.php';
include 'includes/header.php';
include 'includes/nav.php';

$faculty = getMigrationFaculty();
$gradCourses = getMigrationCourses('graduate');
$ugCourses = getMigrationCourses('undergraduate');
$featuredImage = getPageFeaturedImage('migration-studies');
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
        <h1 class="page-header__title">Migration Studies</h1>
        <p class="page-header__subtitle">
            Faculty directory and courses on international migration at UAlbany.
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
<?= renderFeaturedBlock('migration-studies') ?>

<!-- Faculty -->
<section class="section">
    <div class="container">
        <div class="content-max">
            <div class="section__divider"></div>
            <h2 class="section__title">Faculty</h2>
            <p class="text-muted" style="margin-bottom: 1.5rem;">Faculty with research and/or teaching interests related to international migration and refugees:</p>
            <div class="faculty-grid">
                <?php if (!empty($faculty)): ?>
                    <?php foreach ($faculty as $f): ?>
                    <a href="<?= e($f['profile_url'] ?? $f['link_url'] ?? '#') ?>" target="_blank" rel="noopener" class="faculty-card">
                        <h4 class="faculty-card__name"><?= e($f['name']) ?></h4>
                        <p class="faculty-card__dept"><?= e($f['department'] ?? '') ?></p>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php
                    $defaultFaculty = [
                        ['Susan Appe', 'Public Administration and Policy', 'https://www.albany.edu/rockefeller/faculty/susan-appe'],
                        ['Carl Bon Tempo', 'History', 'https://www.albany.edu/history/faculty/carl-bon-tempo'],
                        ['Peter Brandon', 'Sociology', 'https://www.albany.edu/sociology/faculty/peter-brandon'],
                        ['Jennifer L. Burrell', 'Anthropology', 'https://www.albany.edu/anthropology/faculty/jennifer-burrell'],
                        ['Pedro Caban', 'Latin American, Caribbean and U.S. Latino Studies', 'https://www.albany.edu/sites/default/files/resumes/Pedro-Caban-CV.pdf'],
                        ['Angie Y. Chung', 'Sociology', 'https://www.albany.edu/sociology/faculty/angie-y-chung'],
                        ['Kate Coddington', 'Geography and Planning', 'https://katecoddington.weebly.com'],
                        ['Jose E. Cruz', 'Political Science', 'https://www.albany.edu/rockefeller/faculty/jose-e-cruz'],
                        ['Joanna Dreby', 'Sociology', 'https://www.albany.edu/sociology/faculty/joanna-dreby'],
                        ['Samantha Friedman', 'Sociology', 'https://www.albany.edu/sociology/faculty/samantha-friedman'],
                        ['Zoya Gubernskaya', 'Sociology', 'https://www.albany.edu/sociology/faculty/zoya-gubernskaya'],
                        ['Youqin Huang', 'Geography and Planning', 'https://www.albany.edu/~yhuang/'],
                        ['Rey Koslowski', 'Political Science', 'https://www.albany.edu/~rk289758/'],
                        ['Zai Liang', 'Sociology', 'https://www.albany.edu/sociology/faculty/zai-liang'],
                    ];
                    foreach ($defaultFaculty as $f): ?>
                    <a href="<?= e($f[2]) ?>" target="_blank" rel="noopener" class="faculty-card">
                        <h4 class="faculty-card__name"><?= e($f[0]) ?></h4>
                        <p class="faculty-card__dept"><?= e($f[1]) ?></p>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Graduate Courses -->
<section class="section">
    <div class="container">
        <div class="content-max">
            <h3 class="subsection-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 1.1 2.69 2 6 2s6-.9 6-2v-5"/></svg>
                Graduate Courses
            </h3>
            <div class="migration-courses">
                <?php if (!empty($gradCourses)): ?>
                    <?php foreach ($gradCourses as $c): ?>
                    <div class="migration-course-card">
                        <?php if ($c['code']): ?>
                        <span class="migration-course-card__code"><?= e($c['code']) ?></span>
                        <?php endif; ?>
                        <div>
                            <?php if ($c['link_url']): ?>
                            <a href="<?= e($c['link_url']) ?>" target="_blank" rel="noopener"><h4 class="migration-course-card__title"><?= e($c['title']) ?></h4></a>
                            <?php else: ?>
                            <h4 class="migration-course-card__title"><?= e($c['title']) ?></h4>
                            <?php endif; ?>
                            <?php if ($c['description']): ?>
                            <p class="migration-course-card__desc"><?= formatText($c['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Graduate courses coming soon.</p>
                <?php endif; ?>
            </div>

            <h3 class="subsection-title" style="margin-top: 4rem;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Undergraduate Courses
            </h3>
            <div class="migration-courses">
                <?php if (!empty($ugCourses)): ?>
                    <?php foreach ($ugCourses as $c): ?>
                    <div class="migration-course-card">
                        <?php if ($c['code']): ?>
                        <span class="migration-course-card__code"><?= e($c['code']) ?></span>
                        <?php endif; ?>
                        <div>
                            <?php if ($c['link_url']): ?>
                            <a href="<?= e($c['link_url']) ?>" target="_blank" rel="noopener"><h4 class="migration-course-card__title"><?= e($c['title']) ?></h4></a>
                            <?php else: ?>
                            <h4 class="migration-course-card__title"><?= e($c['title']) ?></h4>
                            <?php endif; ?>
                            <?php if ($c['description']): ?>
                            <p class="migration-course-card__desc"><?= formatText($c['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Undergraduate courses coming soon.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
