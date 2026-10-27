<?php
/**
 * Admin: Edit Research Projects
 * 
 * CRUD with CSRF, input validation, image/video/PDF uploads
 * SECURITY: Auth required, CSRF, sanitized inputs, file validation
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Optional featured panel for the public page (shared helper).
$featuredMsg = handleFeaturedBlockSave('research', 'Research');


$success = '';
$editItem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    
    if ($_POST['action'] === 'add' || ($_POST['action'] === 'edit' && isset($_POST['id']))) {
        $title = sanitizeInput($_POST['title'], MAX_TITLE_LENGTH);
        $description = sanitizeInput($_POST['description'] ?? '', MAX_TEXTAREA_LENGTH);
        $status = sanitizeEnum($_POST['status'], ['ongoing', 'completed', 'upcoming'], 'ongoing');
        $fundingSource = sanitizeInput($_POST['funding_source'] ?? '', MAX_TITLE_LENGTH);
        $linkUrl = sanitizeUrl($_POST['link_url'] ?? '') ?: '';
        $videoUrl = sanitizeUrl($_POST['video_url'] ?? '') ?: '';
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        
        // Handle image upload
        $imagePath = $_POST['existing_image_path'] ?? '';
        if (!empty($_FILES['cover_image']['name'])) {
            $uploaded = handleFileUpload($_FILES['cover_image'], 'images');
            if ($uploaded) $imagePath = $uploaded;
        }
        
        // Handle PDF upload
        $pdfPath = $_POST['existing_pdf_path'] ?? '';
        if (!empty($_FILES['pdf_file']['name'])) {
            $uploaded = handleFileUpload($_FILES['pdf_file'], 'pdfs');
            if ($uploaded) $pdfPath = $uploaded;
        }
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO research_projects (title, description, status, funding_source, link_url, image_path, video_path, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $status, $fundingSource, $linkUrl, $imagePath, $videoUrl, $isCurrent]);
            $success = 'Research project added!';
        } else {
            $id = sanitizeInt($_POST['id'], 1);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE research_projects SET title=?, description=?, status=?, funding_source=?, link_url=?, image_path=?, video_path=?, is_current=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$title, $description, $status, $fundingSource, $linkUrl, $imagePath, $videoUrl, $isCurrent, $id]);
                $success = 'Project updated!';
            }
        }
} elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) {
            $pdo->prepare("DELETE FROM research_projects WHERE id = ?")->execute([$id]);
            $success = 'Project deleted.';
        }
    }
}

// Handle page PDF upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page_pdf'])) {
    requireCsrf();
    if (!empty($_FILES['page_pdf']['name'])) {
        $pdfPath = handleFileUpload($_FILES['page_pdf'], 'pdfs');
        if ($pdfPath) {
            $pdfTitle = sanitizeInput($_POST['pdf_title'] ?? 'Document', MAX_TITLE_LENGTH);
            $pdfDesc = sanitizeInput($_POST['pdf_description'] ?? '', MAX_TEXTAREA_LENGTH);
            $stmt = $pdo->prepare("INSERT INTO page_pdfs (page_slug, title, pdf_path, description) VALUES ('research', ?, ?, ?)");
            $stmt->execute([$pdfTitle, $pdfPath, $pdfDesc]);
            $success = 'PDF added to Research page!';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_page_pdf'])) {
    requireCsrf();
    $pdfId = sanitizeInt($_POST['delete_page_pdf'], 1);
    if ($pdfId) {
        $pdo->prepare("DELETE FROM page_pdfs WHERE id = ? AND page_slug = 'research'")->execute([$pdfId]);
        $success = 'PDF removed.';
    }
}

// Load edit item
if (isset($_GET['edit'])) {
    $editId = sanitizeInt($_GET['edit'], 1);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM research_projects WHERE id = ?");
        $stmt->execute([$editId]);
        $editItem = $stmt->fetch();
    }
}

$ongoingProjects = getResearchProjects('ongoing');
$completedProjects = getResearchProjects('completed');
$pagePdfs = getPagePdfs('research');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Research | <?= SITE_TITLE ?> Admin</title>
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
                <h1 class="admin-header__title"><?= $editItem ? 'Edit Project' : 'Edit Research' ?></h1>
                <a href="<?= SITE_URL ?>/research.php" target="_blank" class="btn btn--outline">Preview ↗</a>
            </div>

        <?php if (!empty($featuredMsg)): ?><div class="alert alert--success"><?= e($featuredMsg) ?></div><?php endif; ?>
        <?= renderFeaturedBlockCard('research', 'Research') ?>

            <?php /*
               A leftover "page introduction" form used to sit here, from before
               the featured block replaced it. Nothing handled its save_intro
               action and $pageIntro was never set, so it saved nothing - but it
               also carried a stray </div> that closed .admin-main early, which
               threw every card below it outside the layout container. That is
               what made this page look unstyled.
            */ ?>

            <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>

            <!-- Add/Edit Form -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title"><?= $editItem ? 'Edit Project' : 'Add Research Project' ?></h2>
                    <?php if ($editItem): ?>
                    <a href="edit-research.php" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">← Cancel Edit</a>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
                    <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input class="form-input" type="text" name="title" required maxlength="300" value="<?= e($editItem['title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-textarea" name="description" rows="5" placeholder="Formatting preserved. Use HTML for links."><?= e($editItem['description'] ?? '') ?></textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="ongoing" <?= ($editItem['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                <option value="completed" <?= ($editItem['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="upcoming" <?= ($editItem['status'] ?? '') === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Funding Source</label>
                            <input class="form-input" type="text" name="funding_source" maxlength="200" value="<?= e($editItem['funding_source'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link URL (optional)</label>
                        <input class="form-input" type="url" name="link_url" placeholder="https://..." maxlength="500" value="<?= e($editItem['link_url'] ?? '') ?>">
                    </div>
                    <!-- Cover Image -->
                    <div class="form-group">
                        <label class="form-label">Cover Image (optional)</label>
                        <?php if (!empty($editItem['image_path'])): ?>
                        <div style="margin-bottom:0.5rem;">
                            <img src="<?= SITE_URL . '/' . e($editItem['image_path']) ?>" alt="Cover" style="max-width:200px;border-radius:var(--radius);border:1px solid var(--admin-border);">
                            <input type="hidden" name="existing_image_path" value="<?= e($editItem['image_path']) ?>">
                        </div>
                        <?php endif; ?>
                        <input class="form-input" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <!-- Video URL -->
                    <div class="form-group">
                        <label class="form-label">Video URL (YouTube, Vimeo, or direct .mp4)</label>
                        <input class="form-input" type="url" name="video_url" placeholder="https://youtube.com/watch?v=..." maxlength="500" value="<?= e($editItem['video_path'] ?? '') ?>">
                        <small style="color:var(--color-muted);">Videos will play inline on the site instead of opening externally.</small>
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="checkbox" name="is_current" value="1" <?= ($editItem['is_current'] ?? 0) ? 'checked' : '' ?>>
                            <span class="form-label" style="margin:0;">Show on Home Page (Current/Ongoing)</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn--primary"><?= $editItem ? 'Update Project' : 'Add Project' ?></button>
                </form>
            </div>

            <!-- Page PDFs -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Page Documents (PDFs)</h2>
                </div>
                <p style="font-size:0.8125rem;color:var(--color-muted);margin-bottom:1rem;">Upload PDFs that will appear on the Research page for visitors to view/download.</p>
                <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr auto;gap:0.75rem;align-items:end;margin-bottom:1rem;">
                    <?= csrfField() ?>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Title</label>
                        <input class="form-input" type="text" name="pdf_title" required maxlength="300" placeholder="Document title">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">PDF File</label>
                        <input class="form-input" type="file" name="page_pdf" accept="application/pdf" required>
                    </div>
                    <button type="submit" name="add_page_pdf" class="btn btn--primary" style="height:fit-content;">Upload</button>
                </form>
                <?php if (!empty($pagePdfs)): ?>
                <?php foreach ($pagePdfs as $pdf): ?>
                <div style="display:flex;align-items:center;gap:1rem;padding:0.5rem 0;border-bottom:1px solid var(--admin-border);">
                    <span>📄</span>
                    <div style="flex:1;font-size:0.875rem;"><?= e($pdf['title']) ?></div>
                    <a href="<?= SITE_URL . '/' . e($pdf['pdf_path']) ?>" target="_blank" class="btn btn--outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">View</a>
                    <form method="POST" style="display:inline" data-confirm="Remove this PDF?" >
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_page_pdf" value="<?= $pdf['id'] ?>">
                        <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Remove</button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Existing Projects List -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Existing Projects</h2>
                </div>
                <div data-sortable="research_projects">
                    <?php foreach (array_merge($ongoingProjects, $completedProjects) as $project): ?>
                    <div data-sortable-item data-id="<?= $project['id'] ?>" style="display:flex;align-items:center;gap:1rem;padding:0.75rem 0;border-bottom:1px solid var(--admin-border);">
                        <span class="drag-handle">⠿</span>
                        <div style="flex:1">
                            <strong><?= e($project['title']) ?></strong>
                            <span class="badge <?= $project['status'] === 'ongoing' ? 'badge--success' : 'badge--warning' ?>"><?= e($project['status']) ?></span>
                            <?php if ($project['is_current']): ?><span class="badge badge--success">Home</span><?php endif; ?>
                            <?php if ($project['image_path']): ?><span title="Has cover image">🖼️</span><?php endif; ?>
                            <?php if ($project['video_path']): ?><span title="Has video">🎬</span><?php endif; ?>
                            <?php if ($project['link_url']): ?>
                            <a href="<?= safeUrl($project['link_url']) ?>" target="_blank" style="font-size:0.75rem;color:var(--admin-accent);">🔗</a>
                            <?php endif; ?>
                        </div>
                        <a href="?edit=<?= $project['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">Edit</a>
                        <form method="POST" style="display:inline" data-confirm="Delete this project?" >
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $project['id'] ?>">
                            <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.75rem;font-size:0.75rem;">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= asset('assets/js/admin.js') ?>"></script>
</body>
</html>
