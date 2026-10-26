<?php
/**
 * Admin: Edit Media Appearances
 * 
 * CRUD with CSRF, input validation, video/PDF/image uploads
 * SECURITY: Auth required, CSRF, sanitized inputs
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Optional intro paragraph for the public page (shared helper).
$introMsg = handlePageIntroSave('media-appearances', 'Media Appearances');

$success = '';
$editItem = null;
$mediaTypes = ['print', 'broadcast', 'podcast', 'op_ed', 'online', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    
    if ($_POST['action'] === 'add' || ($_POST['action'] === 'edit' && isset($_POST['id']))) {
        $type = sanitizeEnum($_POST['type'], $mediaTypes, 'print');
        $title = sanitizeInput($_POST['title'], MAX_TITLE_LENGTH);
        $outlet = sanitizeInput($_POST['outlet'] ?? '', 200);
        $year = sanitizeInt($_POST['year'] ?? 0, 1900, 2100);
        $url = sanitizeUrl($_POST['url'] ?? '') ?: '';
        $linkUrl = sanitizeUrl($_POST['link_url'] ?? '') ?: '';
        $videoUrl = sanitizeUrl($_POST['video_url'] ?? '') ?: '';
        $description = sanitizeInput($_POST['description'] ?? '', MAX_TEXTAREA_LENGTH);
        
        // Handle PDF upload
        $pdfPath = $_POST['existing_pdf_path'] ?? '';
        if (!empty($_FILES['pdf_file']['name'])) {
            $uploaded = handleFileUpload($_FILES['pdf_file'], 'pdfs');
            if ($uploaded) $pdfPath = $uploaded;
        }
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO media_appearances (type, title, outlet, year, url, link_url, video_url, pdf_path, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $title, $outlet, $year, $url, $linkUrl, $videoUrl, $pdfPath, $description]);
            $success = 'Media appearance added!';
        } else {
            $id = sanitizeInt($_POST['id'], 1);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE media_appearances SET type=?, title=?, outlet=?, year=?, url=?, link_url=?, video_url=?, pdf_path=?, description=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$type, $title, $outlet, $year, $url, $linkUrl, $videoUrl, $pdfPath, $description, $id]);
                $success = 'Media appearance updated!';
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) {
            $pdo->prepare("DELETE FROM media_appearances WHERE id = ?")->execute([$id]);
            $success = 'Deleted.';
        }
    }
}

// Page PDFs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page_pdf'])) {
    requireCsrf();
    if (!empty($_FILES['page_pdf']['name'])) {
        $pdfPath = handleFileUpload($_FILES['page_pdf'], 'pdfs');
        if ($pdfPath) {
            $stmt = $pdo->prepare("INSERT INTO page_pdfs (page_slug, title, pdf_path) VALUES ('media-appearances', ?, ?)");
            $stmt->execute([sanitizeInput($_POST['pdf_title'] ?? 'Document', MAX_TITLE_LENGTH), $pdfPath]);
            $success = 'PDF added!';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_page_pdf'])) {
    requireCsrf();
    $pdfId = sanitizeInt($_POST['delete_page_pdf'], 1);
    if ($pdfId) { $pdo->prepare("DELETE FROM page_pdfs WHERE id = ? AND page_slug = 'media-appearances'")->execute([$pdfId]); $success = 'PDF removed.'; }
}

if (isset($_GET['edit'])) {
    $editId = sanitizeInt($_GET['edit'], 1);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM media_appearances WHERE id = ?");
        $stmt->execute([$editId]);
        $editItem = $stmt->fetch();
    }
}

$mediaItems = getMediaAppearances();
$pagePdfs = getPagePdfs('media-appearances');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Media Appearances | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-header">
            <h1 class="admin-header__title"><?= $editItem ? 'Edit Media Appearance' : 'Edit Media Appearances' ?></h1>
            <a href="<?= SITE_URL ?>/media-appearances.php" target="_blank" class="btn btn--outline">Preview ↗</a>
        </div>

        <?php if (!empty($introMsg)): ?><div class="alert alert--success"><?= e($introMsg) ?></div><?php endif; ?>
        <?= renderPageIntroCard('media-appearances', 'Media Appearances') ?>
        <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>

        <div class="admin-card">
            <div class="admin-card__header">
                <h2 class="admin-card__title"><?= $editItem ? 'Edit' : 'Add Media Appearance' ?></h2>
                <?php if ($editItem): ?><a href="edit-media-appearances.php" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">← Cancel</a><?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
                <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <?php foreach ($mediaTypes as $t): ?>
                            <option value="<?= $t ?>" <?= ($editItem['type'] ?? 'print') === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Year</label><input class="form-input" type="number" name="year" min="1900" max="2100" value="<?= e($editItem['year'] ?? '') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Title / Citation</label><input class="form-input" type="text" name="title" required maxlength="500" value="<?= e($editItem['title'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Outlet</label><input class="form-input" type="text" name="outlet" maxlength="200" value="<?= e($editItem['outlet'] ?? '') ?>"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">URL</label><input class="form-input" type="url" name="url" maxlength="500" value="<?= e($editItem['url'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Additional Link</label><input class="form-input" type="url" name="link_url" maxlength="500" value="<?= e($editItem['link_url'] ?? '') ?>"></div>
                </div>
                <!-- Video URL -->
                <div class="form-group">
                    <label class="form-label">Video URL (YouTube, Vimeo, or .mp4 — plays inline)</label>
                    <input class="form-input" type="url" name="video_url" maxlength="500" value="<?= e($editItem['video_url'] ?? '') ?>" placeholder="https://youtube.com/watch?v=...">
                </div>
                <!-- PDF -->
                <div class="form-group">
                    <label class="form-label">PDF (transcript, article)</label>
                    <?php if (!empty($editItem['pdf_path'])): ?>
                    <div style="margin-bottom:0.5rem;"><a href="<?= SITE_URL . '/' . e($editItem['pdf_path']) ?>" target="_blank" style="color:var(--admin-accent);font-size:0.8125rem;">📄 Current PDF</a><input type="hidden" name="existing_pdf_path" value="<?= e($editItem['pdf_path']) ?>"></div>
                    <?php endif; ?>
                    <input class="form-input" type="file" name="pdf_file" accept="application/pdf">
                </div>
                <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"><?= e($editItem['description'] ?? '') ?></textarea></div>
                <button type="submit" class="btn btn--primary"><?= $editItem ? 'Update' : 'Add Media Appearance' ?></button>
            </form>
        </div>

        <!-- Page PDFs -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Page Documents</h2></div>
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

        <!-- Existing Items -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Existing Media (<?= count($mediaItems) ?>)</h2></div>
            <div data-sortable="media_appearances">
                <?php foreach ($mediaItems as $item): ?>
                <div data-sortable-item data-id="<?= $item['id'] ?>" style="display:flex;align-items:center;gap:1rem;padding:0.75rem 0;border-bottom:1px solid var(--admin-border);">
                    <span class="drag-handle">⠿</span>
                    <div style="flex:1;font-size:0.875rem;">
                        <?= e($item['title']) ?>
                        <span class="badge badge--warning"><?= e($item['type']) ?></span>
                        <?= ($item['video_url'] ?? '') ? '🎬' : '' ?>
                        <?= ($item['pdf_path'] ?? '') ? '📄' : '' ?>
                    </div>
                    <a href="?edit=<?= $item['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Edit</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $item['id'] ?>">
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
</body></html>
