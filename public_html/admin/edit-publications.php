<?php
/**
 * Admin: Edit Publications
 * 
 * CRUD with CSRF, input validation, PDF/image/video uploads
 * SECURITY: Auth required, CSRF, sanitized inputs, file validation
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Optional featured panel for the public page (shared helper).
$featuredMsg = handleFeaturedBlockSave('publications', 'Publications');


$success = '';
$editItem = null;
$pubTypes = ['book', 'edited_volume', 'journal_article', 'book_chapter', 'working_paper', 'report', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    
    if ($_POST['action'] === 'add' || ($_POST['action'] === 'edit' && isset($_POST['id']))) {
        $type = sanitizeEnum($_POST['type'], $pubTypes, 'journal_article');
        $title = sanitizeInput($_POST['title'], MAX_TITLE_LENGTH);
        $authors = sanitizeInput($_POST['authors'] ?? '', MAX_TITLE_LENGTH);
        $journal = sanitizeInput($_POST['journal_or_publisher'] ?? '', 300);
        $year = sanitizeInt($_POST['year'] ?? 0, 1900, 2100);
        $doi = sanitizeInput($_POST['doi'] ?? '', 200);
        $url = sanitizeUrl($_POST['url'] ?? '') ?: '';
        $linkUrl = sanitizeUrl($_POST['link_url'] ?? '') ?: '';
        $abstract = sanitizeInput($_POST['abstract'] ?? '', MAX_TEXTAREA_LENGTH);
        $citation = sanitizeInput($_POST['citation'] ?? '', MAX_TEXTAREA_LENGTH);
        $videoUrl = sanitizeUrl($_POST['video_url'] ?? '') ?: '';
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        
        // Handle PDF upload
        $pdfPath = $_POST['existing_pdf_path'] ?? '';
        if (!empty($_FILES['pdf_file']['name'])) {
            $uploaded = handleFileUpload($_FILES['pdf_file'], 'pdfs');
            if ($uploaded) $pdfPath = $uploaded;
        }
        
        // Handle image upload
        $imagePath = $_POST['existing_image_path'] ?? '';
        if (!empty($_FILES['cover_image']['name'])) {
            $uploaded = handleFileUpload($_FILES['cover_image'], 'images');
            if ($uploaded) $imagePath = $uploaded;
        }
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO publications (type, title, authors, journal_or_publisher, year, doi, url, link_url, abstract, citation, pdf_path, image_path, video_url, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $title, $authors, $journal, $year, $doi, $url, $linkUrl, $abstract, $citation, $pdfPath, $imagePath, $videoUrl, $isCurrent]);
            $success = 'Publication added!';
        } else {
            $id = sanitizeInt($_POST['id'], 1);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE publications SET type=?, title=?, authors=?, journal_or_publisher=?, year=?, doi=?, url=?, link_url=?, abstract=?, citation=?, pdf_path=?, image_path=?, video_url=?, is_current=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$type, $title, $authors, $journal, $year, $doi, $url, $linkUrl, $abstract, $citation, $pdfPath, $imagePath, $videoUrl, $isCurrent, $id]);
                $success = 'Publication updated!';
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) {
            $pdo->prepare("DELETE FROM publications WHERE id = ?")->execute([$id]);
            $success = 'Publication deleted.';
        }
    }
}

// Page PDFs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page_pdf'])) {
    requireCsrf();
    if (!empty($_FILES['page_pdf']['name'])) {
        $pdfPath = handleFileUpload($_FILES['page_pdf'], 'pdfs');
        if ($pdfPath) {
            $stmt = $pdo->prepare("INSERT INTO page_pdfs (page_slug, title, pdf_path, description) VALUES ('publications', ?, ?, ?)");
            $stmt->execute([sanitizeInput($_POST['pdf_title'] ?? 'Document', MAX_TITLE_LENGTH), $pdfPath, sanitizeInput($_POST['pdf_description'] ?? '', MAX_TEXTAREA_LENGTH)]);
            $success = 'PDF added!';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_page_pdf'])) {
    requireCsrf();
    $pdfId = sanitizeInt($_POST['delete_page_pdf'], 1);
    if ($pdfId) { $pdo->prepare("DELETE FROM page_pdfs WHERE id = ? AND page_slug = 'publications'")->execute([$pdfId]); $success = 'PDF removed.'; }
}

if (isset($_GET['edit'])) {
    $editId = sanitizeInt($_GET['edit'], 1);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM publications WHERE id = ?");
        $stmt->execute([$editId]);
        $editItem = $stmt->fetch();
    }
}

$publications = getPublications();
$pagePdfs = getPagePdfs('publications');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Publications | <?= SITE_TITLE ?> Admin</title>
        <?php // Self-hosted: see assets/css/fonts.css. Loading these from
              // Google sent every visitor's IP there before they had answered
              // the consent banner. ?>
        <link rel="stylesheet" href="<?= asset('assets/css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="admin-header">
                <h1 class="admin-header__title"><?= $editItem ? 'Edit Publication' : 'Edit Publications' ?></h1>
                <a href="<?= SITE_URL ?>/publications.php" target="_blank" class="btn btn--outline">Preview ↗</a>
            </div>

        <?php if (!empty($featuredMsg)): ?><div class="alert alert--success"><?= e($featuredMsg) ?></div><?php endif; ?>
        <?= renderFeaturedBlockCard('publications', 'Publications') ?>


            <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>

            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title"><?= $editItem ? 'Edit Publication' : 'Add Publication' ?></h2>
                    <?php if ($editItem): ?><a href="edit-publications.php" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">← Cancel</a><?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
                    <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group"><label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <?php foreach ($pubTypes as $t): ?>
                                <option value="<?= $t ?>" <?= ($editItem['type'] ?? 'journal_article') === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Year</label><input class="form-input" type="number" name="year" min="1900" max="2100" value="<?= e($editItem['year'] ?? '') ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Title</label><input class="form-input" type="text" name="title" required maxlength="500" value="<?= e($editItem['title'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Authors</label><input class="form-input" type="text" name="authors" maxlength="500" value="<?= e($editItem['authors'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Journal / Publisher</label><input class="form-input" type="text" name="journal_or_publisher" maxlength="300" value="<?= e($editItem['journal_or_publisher'] ?? '') ?>"></div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group"><label class="form-label">DOI</label><input class="form-input" type="text" name="doi" placeholder="10.xxxx/xxxxx" maxlength="200" value="<?= e($editItem['doi'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">URL</label><input class="form-input" type="url" name="url" maxlength="500" value="<?= e($editItem['url'] ?? '') ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Additional Link URL</label><input class="form-input" type="url" name="link_url" maxlength="500" value="<?= e($editItem['link_url'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Full Citation (optional)</label><textarea class="form-textarea" name="citation"><?= e($editItem['citation'] ?? '') ?></textarea></div>
                    <div class="form-group"><label class="form-label">Abstract</label><textarea class="form-textarea" name="abstract"><?= e($editItem['abstract'] ?? '') ?></textarea></div>
                    <!-- Cover Image -->
                    <div class="form-group">
                        <label class="form-label">Cover Image (optional, e.g. book cover)</label>
                        <?php if (!empty($editItem['image_path'])): ?>
                        <div style="margin-bottom:0.5rem;"><img src="<?= SITE_URL . '/' . e($editItem['image_path']) ?>" style="max-width:120px;border-radius:var(--radius);"><input type="hidden" name="existing_image_path" value="<?= e($editItem['image_path']) ?>"></div>
                        <?php endif; ?>
                        <input class="form-input" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <!-- PDF Upload -->
                    <div class="form-group">
                        <label class="form-label">PDF File (optional)</label>
                        <?php if (!empty($editItem['pdf_path'])): ?>
                        <div style="margin-bottom:0.5rem;"><a href="<?= SITE_URL . '/' . e($editItem['pdf_path']) ?>" target="_blank" style="color:var(--admin-accent);font-size:0.8125rem;">📄 Current PDF</a><input type="hidden" name="existing_pdf_path" value="<?= e($editItem['pdf_path']) ?>"></div>
                        <?php endif; ?>
                        <input class="form-input" type="file" name="pdf_file" accept="application/pdf">
                    </div>
                    <!-- Video URL -->
                    <div class="form-group">
                        <label class="form-label">Video URL (YouTube, Vimeo, or .mp4)</label>
                        <input class="form-input" type="url" name="video_url" maxlength="500" value="<?= e($editItem['video_url'] ?? '') ?>" placeholder="https://youtube.com/watch?v=...">
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="checkbox" name="is_current" value="1" <?= ($editItem['is_current'] ?? 0) ? 'checked' : '' ?>>
                            <span class="form-label" style="margin:0;">Show on Home Page</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn--primary"><?= $editItem ? 'Update Publication' : 'Add Publication' ?></button>
                </form>
            </div>

            <!-- Page PDFs -->
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Page Documents (PDFs)</h2></div>
                <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr auto;gap:0.75rem;align-items:end;margin-bottom:1rem;">
                    <?= csrfField() ?>
                    <div class="form-group" style="margin:0;"><label class="form-label">Title</label><input class="form-input" type="text" name="pdf_title" required maxlength="300"></div>
                    <div class="form-group" style="margin:0;"><label class="form-label">PDF</label><input class="form-input" type="file" name="page_pdf" accept="application/pdf" required></div>
                    <button type="submit" name="add_page_pdf" class="btn btn--primary" style="height:fit-content;">Upload</button>
                </form>
                <?php foreach ($pagePdfs as $pdf): ?>
                <div style="display:flex;align-items:center;gap:1rem;padding:0.5rem 0;border-bottom:1px solid var(--admin-border);">
                    <span>📄</span><div style="flex:1;font-size:0.875rem;"><?= e($pdf['title']) ?></div>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Remove?')"><?= csrfField() ?><input type="hidden" name="delete_page_pdf" value="<?= $pdf['id'] ?>"><button type="submit" class="btn btn--danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Remove</button></form>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Existing Publications -->
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Existing Publications (<?= count($publications) ?>)</h2></div>
                <div data-sortable="publications">
                    <?php foreach ($publications as $pub): ?>
                    <div data-sortable-item data-id="<?= $pub['id'] ?>" style="display:flex;align-items:center;gap:1rem;padding:0.75rem 0;border-bottom:1px solid var(--admin-border);">
                        <span class="drag-handle">⠿</span>
                        <div style="flex:1">
                            <strong style="font-size:0.875rem;"><?= e($pub['title']) ?></strong>
                            <div style="font-size:0.75rem;color:var(--color-muted);margin-top:0.25rem;">
                                <span class="badge badge--warning"><?= e($pub['type']) ?></span>
                                <?= $pub['year'] ? '· ' . e($pub['year']) : '' ?>
                                <?= $pub['pdf_path'] ? ' 📄' : '' ?>
                                <?= ($pub['image_path'] ?? '') ? ' 🖼️' : '' ?>
                                <?= ($pub['video_url'] ?? '') ? ' 🎬' : '' ?>
                                <?php if ($pub['is_current']): ?> · <span class="badge badge--success">Home</span><?php endif; ?>
                            </div>
                        </div>
                        <a href="?edit=<?= $pub['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Edit</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $pub['id'] ?>">
                            <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script>const SITE_URL = '<?= SITE_URL ?>';</script>
    <script src="<?= asset('assets/js/admin.js') ?>"></script>
</body>
</html>
