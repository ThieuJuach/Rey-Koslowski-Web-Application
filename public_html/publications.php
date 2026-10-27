<?php
/**
 * Publications Page
 * 
 * Shows publications with PDF links, inline videos, cover images
 */
$pageTitle = 'Publications';
$metaDescription = 'Books, journal articles, and policy papers by Rey Koslowski on migration, security, and international relations.';
require_once 'config.php';
require_once 'includes/auth.php';

// Queries before header so structured data and OG image are available in <head>
$allPubs       = getPublications();
$books         = array_filter($allPubs, fn($p) => in_array($p['type'], ['book', 'edited_volume']));
$articles      = array_filter($allPubs, fn($p) => $p['type'] === 'journal_article');
$chapters      = array_filter($allPubs, fn($p) => $p['type'] === 'book_chapter');
$others        = array_filter($allPubs, fn($p) => !in_array($p['type'], ['book', 'edited_volume', 'journal_article', 'book_chapter']));
$featuredImage = getPageFeaturedImage('publications');
if ($featuredImage) $ogImage = SITE_URL . '/' . $featuredImage['image_path'];

// Build ScholarlyArticle / Book structured data
if (!empty($allPubs)) {
    $schemaItems = [];
    foreach ($allPubs as $pub) {
        $isBook = in_array($pub['type'], ['book', 'edited_volume']);
        $item   = [
            '@type'  => $isBook ? 'Book' : 'ScholarlyArticle',
            'name'   => $pub['title'],
            'author' => ['@type' => 'Person', 'name' => 'Rey Koslowski'],
        ];
        if (!empty($pub['year']))                 $item['datePublished'] = (string)$pub['year'];
        if (!empty($pub['journal_or_publisher'])) $item[$isBook ? 'publisher' : 'isPartOf'] = ['@type' => 'Organization', 'name' => $pub['journal_or_publisher']];
        $url = $pub['link_url'] ?: ($pub['url'] ?: ($pub['doi'] ? 'https://doi.org/' . $pub['doi'] : ''));
        if ($url)              $item['url']        = $url;
        if (!empty($pub['doi'])) $item['identifier'] = ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $pub['doi']];
        $schemaItems[] = $item;
    }
    $pageSchema = ['@context' => 'https://schema.org', '@graph' => $schemaItems];
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
        <h1 class="page-header__title">Publications</h1>
        <p class="page-header__subtitle">
            Books, journal articles, policy papers, and reports on migration, security, and international relations.
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
<?= renderFeaturedBlock('publications') ?>

<section class="section">
    <div class="container">
        <div class="content-max">
            <?php if (!empty($allPubs)): ?>

            <?php if (!empty($books)): ?>
            <div class="section__divider"></div>
            <h2 class="section__title">Books & Edited Volumes</h2>
            <div class="publications-list">
                <?php foreach ($books as $pub): ?>
                <div class="pub-card">
                    <?php if (!empty($pub['image_path'])): ?>
                    <img class="pub-card__cover" src="<?= SITE_URL . '/' . e($pub['image_path']) ?>" alt="<?= e($pub['title']) ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                    <div class="pub-card__content">
                        <?php $pubLink = $pub['link_url'] ?: ($pub['url'] ?: ($pub['doi'] ? 'https://doi.org/' . $pub['doi'] : '')); ?>
                        <?php if ($pubLink): ?>
                        <a href="<?= safeUrl($pubLink) ?>" target="_blank" rel="noopener" class="pub-card__title-link"><h3 class="pub-card__title"><?= e($pub['title']) ?></h3></a>
                        <?php else: ?>
                        <h3 class="pub-card__title"><?= e($pub['title']) ?></h3>
                        <?php endif; ?>
                        <p class="pub-card__meta"><?php if ($pub['authors']): ?><span class="pub-card__role"><?= e($pub['authors']) ?></span> · <?php endif; ?><?= e($pub['journal_or_publisher'] ?? '') ?></p>
                        <div class="pub-card__links">
                            <?php if ($pub['doi']): ?><a href="https://doi.org/<?= e($pub['doi']) ?>" target="_blank" class="pub-card__doi">[DOI]</a><?php endif; ?>
                            <?php if ($pub['url']): ?><a href="<?= safeUrl($pub['url']) ?>" target="_blank" class="pub-card__doi">[Link]</a><?php endif; ?>
                            <?php if ($pub['pdf_path']): ?><a href="<?= SITE_URL . '/' . e($pub['pdf_path']) ?>#toolbar=1&navpanes=0&scrollbar=1" target="_blank" rel="noopener" class="pub-card__doi">[PDF]</a><?php endif; ?>
                        </div>
                        <?php if (!empty($pub['video_url'])): ?>
                        <?= renderVideoEmbed($pub['video_url']) ?>
                        <?php endif; ?>
                    </div>
                    <span class="pub-card__year"><?= e($pub['year'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($articles)): ?>
            <h2 class="section__title" style="margin-top: 4rem;">Journal Articles</h2>
            <div class="publications-list">
                <?php foreach ($articles as $pub): ?>
                <div class="pub-card">
                    <div class="pub-card__content">
                        <?php $pubLink = $pub['link_url'] ?: ($pub['url'] ?: ($pub['doi'] ? 'https://doi.org/' . $pub['doi'] : '')); ?>
                        <?php if ($pub['citation']): ?>
                        <p class="pub-card__citation"><?= formatText($pub['citation']) ?>
                            <?php if ($pub['doi']): ?> <a href="https://doi.org/<?= e($pub['doi']) ?>" target="_blank" class="pub-card__doi">[DOI]</a><?php endif; ?>
                            <?php if ($pub['url']): ?> <a href="<?= safeUrl($pub['url']) ?>" target="_blank" class="pub-card__doi">[Link]</a><?php endif; ?>
                            <?php if ($pub['pdf_path']): ?> <a href="<?= SITE_URL . '/' . e($pub['pdf_path']) ?>#toolbar=1&navpanes=0&scrollbar=1" target="_blank" rel="noopener" class="pub-card__doi">[PDF]</a><?php endif; ?>
                        </p>
                        <?php else: ?>
                        <?php if ($pubLink): ?>
                        <a href="<?= safeUrl($pubLink) ?>" target="_blank" rel="noopener" class="pub-card__title-link"><h3 class="pub-card__title"><?= e($pub['title']) ?></h3></a>
                        <?php else: ?>
                        <h3 class="pub-card__title"><?= e($pub['title']) ?></h3>
                        <?php endif; ?>
                        <p class="pub-card__meta"><?= e($pub['authors'] ?? '') ?><?= $pub['journal_or_publisher'] ? ' · ' . e($pub['journal_or_publisher']) : '' ?></p>
                        <div class="pub-card__links">
                            <?php if ($pub['doi']): ?><a href="https://doi.org/<?= e($pub['doi']) ?>" target="_blank" class="pub-card__doi">[DOI]</a><?php endif; ?>
                            <?php if ($pub['url']): ?><a href="<?= safeUrl($pub['url']) ?>" target="_blank" class="pub-card__doi">[Link]</a><?php endif; ?>
                            <?php if ($pub['pdf_path']): ?><a href="<?= SITE_URL . '/' . e($pub['pdf_path']) ?>#toolbar=1&navpanes=0&scrollbar=1" target="_blank" rel="noopener" class="pub-card__doi">[PDF]</a><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($pub['video_url'])): ?>
                        <?= renderVideoEmbed($pub['video_url']) ?>
                        <?php endif; ?>
                    </div>
                    <span class="pub-card__year"><?= e($pub['year'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($chapters)): ?>
            <h2 class="section__title" style="margin-top: 4rem;">Book Chapters</h2>
            <div class="publications-list">
                <?php foreach ($chapters as $pub): ?>
                <div class="pub-card">
                    <div class="pub-card__content">
                        <?php $pubLink = $pub['link_url'] ?: ($pub['url'] ?: ''); ?>
                        <?php if ($pubLink): ?>
                        <a href="<?= safeUrl($pubLink) ?>" target="_blank" rel="noopener" class="pub-card__title-link"><h3 class="pub-card__title"><?= e($pub['title']) ?></h3></a>
                        <?php else: ?>
                        <h3 class="pub-card__title"><?= e($pub['title']) ?></h3>
                        <?php endif; ?>
                        <p class="pub-card__meta"><?= e($pub['journal_or_publisher'] ?? '') ?></p>
                        <div class="pub-card__links">
                            <?php if ($pub['doi']): ?><a href="https://doi.org/<?= e($pub['doi']) ?>" target="_blank" class="pub-card__doi">[DOI]</a><?php endif; ?>
                            <?php if ($pub['url']): ?><a href="<?= safeUrl($pub['url']) ?>" target="_blank" class="pub-card__doi">[Link]</a><?php endif; ?>
                            <?php if ($pub['pdf_path']): ?><a href="<?= SITE_URL . '/' . e($pub['pdf_path']) ?>#toolbar=1&navpanes=0&scrollbar=1" target="_blank" rel="noopener" class="pub-card__doi">[PDF]</a><?php endif; ?>
                        </div>
                    </div>
                    <span class="pub-card__year"><?= e($pub['year'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($others)): ?>
            <h2 class="section__title" style="margin-top: 4rem;">Other Publications</h2>
            <div class="publications-list">
                <?php foreach ($others as $pub): ?>
                <div class="pub-card">
                    <div class="pub-card__content">
                        <?php $pubLink = $pub['link_url'] ?: ($pub['url'] ?: ''); ?>
                        <?php if ($pubLink): ?>
                        <a href="<?= safeUrl($pubLink) ?>" target="_blank" rel="noopener" class="pub-card__title-link"><h3 class="pub-card__title"><?= e($pub['title']) ?></h3></a>
                        <?php else: ?>
                        <h3 class="pub-card__title"><?= e($pub['title']) ?></h3>
                        <?php endif; ?>
                        <p class="pub-card__meta"><?= e($pub['authors'] ?? '') ?><?= $pub['journal_or_publisher'] ? ' · ' . e($pub['journal_or_publisher']) : '' ?></p>
                        <div class="pub-card__links">
                            <?php if ($pub['pdf_path']): ?><a href="<?= SITE_URL . '/' . e($pub['pdf_path']) ?>#toolbar=1&navpanes=0&scrollbar=1" target="_blank" rel="noopener" class="pub-card__doi">[PDF]</a><?php endif; ?>
                        </div>
                    </div>
                    <span class="pub-card__year"><?= e($pub['year'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="section__divider"></div>
            <h2 class="section__title">Publications</h2>
            <p class="text-muted">Publications coming soon.</p>
            <?php endif; ?>

            <h2 class="section__title" style="margin-top: 4rem;">Published In</h2>
            <p class="text-muted" style="margin-bottom: 1.5rem;">Articles published in leading academic journals:</p>
            <div class="journal-tags">
                <span class="journal-tag">International Organization</span>
                <span class="journal-tag">International Studies Quarterly</span>
                <span class="journal-tag">Journal of European Public Policy</span>
                <span class="journal-tag">Journal of Common Market Studies</span>
                <span class="journal-tag">Journal of Ethnic and Migration Studies</span>
                <span class="journal-tag">International Migration</span>
                <span class="journal-tag">Proceedings of the National Academy of Sciences</span>
                <span class="journal-tag">International Studies Perspectives</span>
                <span class="journal-tag">Columbia Journal of Transnational Law</span>
            </div>

            <?= renderPagePdfs('publications') ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
