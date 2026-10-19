<?php
/**
 * Admin: Edit Teaching / Courses
 * 
 * CRUD with CSRF, input validation, image/PDF uploads for courses
 * SECURITY: Auth required, CSRF, sanitized inputs
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$success = '';
$editItem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    
    if ($_POST['action'] === 'add' || ($_POST['action'] === 'edit' && isset($_POST['id']))) {
        $code = sanitizeInput($_POST['code'] ?? '', 50);
        $title = sanitizeInput($_POST['title'], MAX_TITLE_LENGTH);
        $level = sanitizeEnum($_POST['level'], ['undergraduate', 'graduate'], 'undergraduate');
        $description = sanitizeInput($_POST['description'] ?? '', MAX_TEXTAREA_LENGTH);
        $institution = sanitizeInput($_POST['institution'] ?? 'University at Albany (SUNY)', 200);
        $linkUrl = sanitizeUrl($_POST['link_url'] ?? '') ?: '';
        $videoUrl = sanitizeUrl($_POST['video_url'] ?? '') ?: '';
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        
        // Handle image upload
        $imagePath = $_POST['existing_image_path'] ?? '';
        if (!empty($_FILES['cover_image']['name'])) {
            $uploaded = handleFileUpload($_FILES['cover_image'], 'images');
            if ($uploaded) $imagePath = $uploaded;
        }
        
        // Handle syllabus/PDF upload
        $pdfPath = $_POST['existing_pdf_path'] ?? '';
        if (!empty($_FILES['pdf_file']['name'])) {
            $uploaded = handleFileUpload($_FILES['pdf_file'], 'pdfs');
            if ($uploaded) $pdfPath = $uploaded;
        }
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO courses (code, title, level, description, institution, link_url, image_path, video_url, pdf_path, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $title, $level, $description, $institution, $linkUrl, $imagePath, $videoUrl, $pdfPath, $isCurrent]);
            $success = 'Course added!';
        } else {
            $id = sanitizeInt($_POST['id'], 1);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE courses SET code=?, title=?, level=?, description=?, institution=?, link_url=?, image_path=?, video_url=?, pdf_path=?, is_current=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$code, $title, $level, $description, $institution, $linkUrl, $imagePath, $videoUrl, $pdfPath, $isCurrent, $id]);
                $success = 'Course updated!';
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) {
            $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
            $success = 'Course deleted.';
        }
    }
}

// Page PDFs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page_pdf'])) {
    requireCsrf();
    if (!empty($_FILES['page_pdf']['name'])) {
        $pdfPath = handleFileUpload($_FILES['page_pdf'], 'pdfs');
        if ($pdfPath) {
            $stmt = $pdo->prepare("INSERT INTO page_pdfs (page_slug, title, pdf_path, description) VALUES ('teaching', ?, ?, ?)");
            $stmt->execute([sanitizeInput($_POST['pdf_title'] ?? 'Document', MAX_TITLE_LENGTH), $pdfPath, sanitizeInput($_POST['pdf_description'] ?? '', MAX_TEXTAREA_LENGTH)]);
            $success = 'PDF added!';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_page_pdf'])) {
    requireCsrf();
    $pdfId = sanitizeInt($_POST['delete_page_pdf'], 1);
    if ($pdfId) { $pdo->prepare("DELETE FROM page_pdfs WHERE id = ? AND page_slug = 'teaching'")->execute([$pdfId]); $success = 'PDF removed.'; }
}

if (isset($_GET['edit'])) {
    $editId = sanitizeInt($_GET['edit'], 1);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$editId]);
        $editItem = $stmt->fetch();
    }
}

$courses = getCourses();
$pagePdfs = getPagePdfs('teaching');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Teaching | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="admin-header">
                <h1 class="admin-header__title"><?= $editItem ? 'Edit Course' : 'Edit Teaching' ?></h1>
                <a href="<?= SITE_URL ?>/teachings.php" target="_blank" class="btn btn--outline">Preview ↗</a>
            </div>

            <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>

            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title"><?= $editItem ? 'Edit Course' : 'Add Course' ?></h2>
                    <?php if ($editItem): ?><a href="edit-teachings.php" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">← Cancel</a><?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
                    <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                    <div style="display:grid; grid-template-columns:1fr 2fr; gap:1rem;">
                        <div class="form-group"><label class="form-label">Course Code</label><input class="form-input" type="text" name="code" maxlength="50" value="<?= e($editItem['code'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">Title</label><input class="form-input" type="text" name="title" required maxlength="200" value="<?= e($editItem['title'] ?? '') ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Level</label>
                        <select class="form-select" name="level">
                            <option value="undergraduate" <?= ($editItem['level'] ?? '') === 'undergraduate' ? 'selected' : '' ?>>Undergraduate</option>
                            <option value="graduate" <?= ($editItem['level'] ?? '') === 'graduate' ? 'selected' : '' ?>>Graduate</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"><?= e($editItem['description'] ?? '') ?></textarea></div>
                    <div class="form-group"><label class="form-label">Link URL (optional)</label><input class="form-input" type="url" name="link_url" maxlength="500" value="<?= e($editItem['link_url'] ?? '') ?>"></div>
                    <!-- Cover Image -->
                    <div class="form-group">
                        <label class="form-label">Cover Image (optional)</label>
                        <?php if (!empty($editItem['image_path'])): ?>
                        <div style="margin-bottom:0.5rem;"><img src="<?= SITE_URL . '/' . e($editItem['image_path']) ?>" style="max-width:150px;border-radius:var(--radius);"><input type="hidden" name="existing_image_path" value="<?= e($editItem['image_path']) ?>"></div>
                        <?php endif; ?>
                        <input class="form-input" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <!-- Video URL -->
                    <div class="form-group">
                        <label class="form-label">Video URL (YouTube, Vimeo, or .mp4)</label>
                        <input class="form-input" type="url" name="video_url" maxlength="500" value="<?= e($editItem['video_url'] ?? '') ?>" placeholder="https://youtube.com/watch?v=...">
                        <small style="color:var(--color-muted);">Videos play inline on the site.</small>
                    </div>
                    <!-- PDF / Syllabus -->
                    <div class="form-group">
                        <label class="form-label">Syllabus / PDF (optional)</label>
                        <?php if (!empty($editItem['pdf_path'])): ?>
                        <div style="margin-bottom:0.5rem;"><a href="<?= SITE_URL . '/' . e($editItem['pdf_path']) ?>" target="_blank" style="color:var(--admin-accent);font-size:0.8125rem;">📄 Current PDF</a><input type="hidden" name="existing_pdf_path" value="<?= e($editItem['pdf_path']) ?>"></div>
                        <?php endif; ?>
                        <input class="form-input" type="file" name="pdf_file" accept="application/pdf">
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="checkbox" name="is_current" value="1" <?= ($editItem ? $editItem['is_current'] : 1) ? 'checked' : '' ?>>
                            <span class="form-label" style="margin:0;">Current Course (show on Home Page)</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn--primary"><?= $editItem ? 'Update Course' : 'Add Course' ?></button>
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

            <!-- Existing Courses -->
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Existing Courses (<?= count($courses) ?>)</h2></div>
                <table class="admin-table">
                    <thead><tr><th>Code</th><th>Title</th><th>Level</th><th>Media</th><th>Current</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?= e($course['code'] ?? '-') ?></td>
                            <td><?= e($course['title']) ?></td>
                            <td><span class="badge <?= $course['level'] === 'graduate' ? 'badge--warning' : 'badge--success' ?>"><?= e($course['level']) ?></span></td>
                            <td>
                                <?= $course['image_path'] ? '🖼️' : '' ?>
                                <?= ($course['video_url'] ?? '') ? '🎬' : '' ?>
                                <?= ($course['pdf_path'] ?? '') ? '📄' : '' ?>
                                <?= $course['link_url'] ? '🔗' : '' ?>
                            </td>
                            <td><?= $course['is_current'] ? '✅' : '—' ?></td>
                            <td style="display:flex;gap:0.25rem;">
                                <a href="?edit=<?= $course['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Edit</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $course['id'] ?>">
                                    <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>const SITE_URL = '<?= SITE_URL ?>';</script>
    <script src="<?= SITE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
