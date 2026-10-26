<?php
/**
 * Admin: Manage Presentations
 * CRUD with CSRF, input validation, image/video/PDF uploads
 * SECURITY: Auth required, CSRF, sanitized inputs
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Optional featured panel for the public page (shared helper).
$featuredMsg = handleFeaturedBlockSave('presentations', 'Presentations');


$success = '';
$error = '';
$editItem = null;

// Handle edit mode
if (isset($_GET['edit'])) {
    $editId = sanitizeInt($_GET['edit'], 1);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM presentations WHERE id = ?");
        $stmt->execute([$editId]);
        $editItem = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save_featured'])) {
    requireCsrf();

    if (isset($_POST['toggle_current'])) {
        $toggleId = sanitizeInt($_POST['toggle_id'], 1);
        if ($toggleId) {
            $stmt = $pdo->prepare("UPDATE presentations SET is_current = IF(is_current = 1, 0, 1), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$toggleId]);
            $success = 'Homepage visibility updated.';
        }
    }

    if (isset($_POST['delete_id'])) {
        $deleteId = sanitizeInt($_POST['delete_id'], 1);
        if ($deleteId) {
            $pdo->prepare("DELETE FROM presentations WHERE id = ?")->execute([$deleteId]);
            $success = 'Presentation deleted.';
        }
    } elseif (isset($_POST['save'])) {
        $title = sanitizeInput($_POST['title'] ?? '', MAX_TITLE_LENGTH);
        $eventName = sanitizeInput($_POST['event_name'] ?? '', MAX_TITLE_LENGTH);
        $location = sanitizeInput($_POST['location'] ?? '', MAX_TITLE_LENGTH);
        $presDate = sanitizeInput($_POST['presentation_date'] ?? '', 10);
        $endDate = sanitizeInput($_POST['end_date'] ?? '', 10);
        $description = sanitizeInput($_POST['description'] ?? '', MAX_TEXTAREA_LENGTH);
        $type = sanitizeEnum($_POST['type'] ?? '', ['conference', 'invited', 'workshop', 'seminar', 'panel', 'keynote', 'other'], 'conference');
        $status = sanitizeEnum($_POST['status'] ?? '', ['upcoming', 'completed'], 'upcoming');
        $linkUrl = sanitizeUrl($_POST['link_url'] ?? '');
        $videoUrl = sanitizeUrl($_POST['video_url'] ?? '') ?: '';
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        $order = sanitizeInt($_POST['display_order'] ?? 0, 0, 9999) ?? 0;

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

        if (empty($title)) {
            $error = 'Title is required.';
        } elseif ($linkUrl === false) {
            $error = 'Invalid URL format.';
        } else {
            if ($presDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $presDate)) $presDate = null;
            if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = null;

            $editId = sanitizeInt($_POST['edit_id'] ?? 0, 0);
            if ($editId) {
                $stmt = $pdo->prepare("UPDATE presentations SET title=?, event_name=?, location=?, presentation_date=?, end_date=?, description=?, type=?, status=?, link_url=?, image_path=?, video_url=?, pdf_path=?, is_current=?, display_order=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$title, $eventName, $location, $presDate, $endDate, $description, $type, $status, $linkUrl, $imagePath, $videoUrl, $pdfPath, $isCurrent, $order, $editId]);
                $success = 'Presentation updated!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO presentations (title, event_name, location, presentation_date, end_date, description, type, status, link_url, image_path, video_url, pdf_path, is_current, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $eventName, $location, $presDate, $endDate, $description, $type, $status, $linkUrl, $imagePath, $videoUrl, $pdfPath, $isCurrent, $order]);
                $success = 'Presentation added!';
            }
            $editItem = null;
        }
    }
}

// Page PDFs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page_pdf'])) {
    requireCsrf();
    if (!empty($_FILES['page_pdf']['name'])) {
        $pdfPath = handleFileUpload($_FILES['page_pdf'], 'pdfs');
        if ($pdfPath) {
            $stmt = $pdo->prepare("INSERT INTO page_pdfs (page_slug, title, pdf_path) VALUES ('presentations', ?, ?)");
            $stmt->execute([sanitizeInput($_POST['pdf_title'] ?? 'Document', MAX_TITLE_LENGTH), $pdfPath]);
            $success = 'PDF added!';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_page_pdf'])) {
    requireCsrf();
    $pdfId = sanitizeInt($_POST['delete_page_pdf'], 1);
    if ($pdfId) { $pdo->prepare("DELETE FROM page_pdfs WHERE id = ? AND page_slug = 'presentations'")->execute([$pdfId]); $success = 'PDF removed.'; }
}

$presentations = $pdo->query("SELECT * FROM presentations ORDER BY presentation_date DESC, display_order ASC")->fetchAll();
$pagePdfs = getPagePdfs('presentations');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Presentations | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="admin-header">
                <h1 class="admin-header__title">Presentations</h1>
                <a href="<?= SITE_URL ?>/presentations.php" target="_blank" class="btn btn--outline">Preview ↗</a>
            </div>

        <?php if (!empty($featuredMsg)): ?><div class="alert alert--success"><?= e($featuredMsg) ?></div><?php endif; ?>
        <?= renderFeaturedBlockCard('presentations', 'Presentations') ?>


            <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title"><?= $editItem ? 'Edit Presentation' : 'Add Presentation' ?></h2>
                    <?php if ($editItem): ?><a href="edit-presentations.php" class="btn btn--outline">Cancel Edit</a><?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <?php if ($editItem): ?><input type="hidden" name="edit_id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input class="form-input" type="text" name="title" value="<?= e($editItem['title'] ?? '') ?>" required maxlength="500">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group"><label class="form-label">Event / Conference</label><input class="form-input" type="text" name="event_name" value="<?= e($editItem['event_name'] ?? '') ?>" maxlength="300"></div>
                        <div class="form-group"><label class="form-label">Location</label><input class="form-input" type="text" name="location" value="<?= e($editItem['location'] ?? '') ?>" maxlength="300" placeholder="City, Country"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:1rem;">
                        <div class="form-group"><label class="form-label">Date</label><input class="form-input" type="date" name="presentation_date" value="<?= e($editItem['presentation_date'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">End Date</label><input class="form-input" type="date" name="end_date" value="<?= e($editItem['end_date'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <?php foreach (['conference','invited','workshop','seminar','panel','keynote','other'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($editItem['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="upcoming" <?= ($editItem['status'] ?? '') === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                <option value="completed" <?= ($editItem['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description" rows="4"><?= e($editItem['description'] ?? '') ?></textarea></div>
                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem;">
                        <div class="form-group"><label class="form-label">Link URL</label><input class="form-input" type="url" name="link_url" value="<?= e($editItem['link_url'] ?? '') ?>" maxlength="500"></div>
                        <div class="form-group"><label class="form-label">Display Order</label><input class="form-input" type="number" name="display_order" value="<?= e($editItem['display_order'] ?? 0) ?>" min="0"></div>
                    </div>
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
                        <input class="form-input" type="url" name="video_url" value="<?= e($editItem['video_url'] ?? '') ?>" maxlength="500" placeholder="https://youtube.com/watch?v=...">
                    </div>
                    <!-- PDF -->
                    <div class="form-group">
                        <label class="form-label">PDF (slides, handout)</label>
                        <?php if (!empty($editItem['pdf_path'])): ?>
                        <div style="margin-bottom:0.5rem;"><a href="<?= SITE_URL . '/' . e($editItem['pdf_path']) ?>" target="_blank" style="color:var(--admin-accent);font-size:0.8125rem;">📄 Current PDF</a><input type="hidden" name="existing_pdf_path" value="<?= e($editItem['pdf_path']) ?>"></div>
                        <?php endif; ?>
                        <input class="form-input" type="file" name="pdf_file" accept="application/pdf">
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="checkbox" name="is_current" value="1" <?= ($editItem['is_current'] ?? 0) ? 'checked' : '' ?>>
                            <span class="form-label" style="margin:0;">Show in Homepage "Current & Ongoing"</span>
                        </label>
                    </div>
                    <button type="submit" name="save" class="btn btn--primary"><?= $editItem ? 'Update' : 'Add' ?> Presentation</button>
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

            <!-- List -->
            <?php if (!empty($presentations)): ?>
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">All Presentations (<?= count($presentations) ?>)</h2></div>
                <table class="admin-table">
                    <thead><tr><th>Title</th><th>Date</th><th>Type</th><th>Media</th><th>Home</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($presentations as $p): ?>
                        <tr>
                            <td><strong><?= e($p['title']) ?></strong><br><small style="color:var(--color-muted);"><?= e($p['event_name'] ?? '') ?></small></td>
                            <td><?= $p['presentation_date'] ? date('M j, Y', strtotime($p['presentation_date'])) : '—' ?></td>
                            <td><span class="badge badge--warning"><?= e(ucfirst($p['type'])) ?></span></td>
                            <td><?= ($p['image_path'] ?? '') ? '🖼️' : '' ?><?= ($p['video_url'] ?? '') ? '🎬' : '' ?><?= ($p['pdf_path'] ?? '') ? '📄' : '' ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="toggle_current" class="btn <?= $p['is_current'] ? 'btn--primary' : 'btn--outline' ?>" style="padding:0.2rem 0.6rem;font-size:0.75rem;" title="Toggle homepage visibility">
                                        <?= $p['is_current'] ? '✅ On' : '○ Off' ?>
                                    </button>
                                </form>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?= $p['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.75rem;font-size:0.75rem;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body></html>
