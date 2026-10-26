<?php
/**
 * Admin: Manage Professional Service Items (blog-post style)
 * CRUD with CSRF, input validation, edit buttons
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$success = '';
$error = '';
$editItem = null;

// Handle edit mode
if (isset($_GET['edit'])) {
    $editId = sanitizeInt($_GET['edit'], 1);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM professional_service_items WHERE id = ?");
        $stmt->execute([$editId]);
        $editItem = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if (isset($_POST['delete_id'])) {
        $deleteId = sanitizeInt($_POST['delete_id'], 1);
        if ($deleteId) {
            $pdo->prepare("DELETE FROM professional_service_items WHERE id = ?")->execute([$deleteId]);
            $success = 'Item deleted.';
        }
    } elseif (isset($_POST['save_item'])) {
        $title = sanitizeInput($_POST['title'] ?? '', MAX_TITLE_LENGTH);
        $content = sanitizeInput($_POST['content'] ?? '', MAX_TEXTAREA_LENGTH);
        $excerpt = sanitizeInput($_POST['excerpt'] ?? '', MAX_INPUT_LENGTH);
        $category = sanitizeInput($_POST['category'] ?? '', 100);
        $startYear = sanitizeInt($_POST['start_year'] ?? '', 1900, 2100);
        $endYear = sanitizeInt($_POST['end_year'] ?? '', 1900, 2100);
        $linkUrl = sanitizeUrl($_POST['link_url'] ?? '');
        $order = sanitizeInt($_POST['display_order'] ?? 0, 0, 9999) ?? 0;

        if (empty($title)) {
            $error = 'Title is required.';
        } elseif ($linkUrl === false) {
            $error = 'Invalid URL.';
        } else {
            /*
             * Optional image. handleFileUpload() verifies the file from its own
             * bytes and re-encodes it, so nothing here needs to trust the name
             * or the browser-declared type. Keeping the existing path when no
             * new file is chosen is what stops an edit from silently dropping
             * the current image.
             */
            $imagePath = sanitizeInput($_POST['existing_image_path'] ?? '', 500);
            if (!empty($_FILES['cover_image']['name'])) {
                $uploaded = handleFileUpload($_FILES['cover_image'], 'images');
                if ($uploaded) {
                    // Remove the file being replaced so uploads/ does not accrete orphans.
                    if ($imagePath !== '') deleteUploadedFile($imagePath);
                    $imagePath = $uploaded;
                } else {
                    $error = 'The image was rejected - use a JPG, PNG, GIF or WebP under 20MB.';
                }
            }
            if (!empty($_POST['remove_image']) && $imagePath !== '') {
                deleteUploadedFile($imagePath);
                $imagePath = '';
            }

            $editId = sanitizeInt($_POST['edit_id'] ?? 0, 0);
            if ($editId) {
                $stmt = $pdo->prepare("UPDATE professional_service_items SET title=?, content=?, excerpt=?, category=?, start_year=?, end_year=?, link_url=?, image_path=?, display_order=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$title, $content, $excerpt, $category, $startYear, $endYear, $linkUrl, $imagePath, $order, $editId]);
                $success = 'Item updated!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO professional_service_items (title, content, excerpt, category, start_year, end_year, link_url, image_path, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $excerpt, $category, $startYear, $endYear, $linkUrl, $imagePath, $order]);
                $success = 'Item added!';
            }
            $editItem = null;
        }
    }
}

$items = $pdo->query("SELECT * FROM professional_service_items ORDER BY display_order ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Professional Service | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="admin-header">
                <h1 class="admin-header__title">Professional Service</h1>
                <a href="<?= SITE_URL ?>/professional-service.php" target="_blank" class="btn btn--outline">Preview ↗</a>
            </div>

            <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <?php if ($editItem): ?><input type="hidden" name="edit_id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input class="form-input" type="text" name="title" value="<?= e($editItem['title'] ?? '') ?>" required maxlength="500">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <input class="form-input" type="text" name="category" value="<?= e($editItem['category'] ?? '') ?>" maxlength="100" placeholder="e.g., Editorial Board">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Start Year</label>
                            <input class="form-input" type="number" name="start_year" value="<?= e($editItem['start_year'] ?? '') ?>" min="1900" max="2100">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Year (empty = Present)</label>
                            <input class="form-input" type="number" name="end_year" value="<?= e($editItem['end_year'] ?? '') ?>" min="1900" max="2100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Content (rich text, HTML links allowed)</label>
                        <textarea class="form-textarea" name="content" rows="8"><?= e($editItem['content'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Short Excerpt (used if no content)</label>
                        <input class="form-input" type="text" name="excerpt" value="<?= e($editItem['excerpt'] ?? '') ?>" maxlength="500">
                    </div>
                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Link URL</label>
                            <input class="form-input" type="url" name="link_url" value="<?= e($editItem['link_url'] ?? '') ?>" maxlength="500">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Display Order</label>
                            <input class="form-input" type="number" name="display_order" value="<?= e($editItem['display_order'] ?? 0) ?>" min="0">
                        </div>
                    </div>
                    <?php // Optional image for this item, shown beside its text on the public page. ?>
                    <div class="form-group">
                        <label class="form-label">Image (optional)</label>
                        <?php if (!empty($editItem['image_path'])): ?>
                            <img src="<?= SITE_URL . '/' . e($editItem['image_path']) ?>" alt=""
                                 style="max-width:180px;height:auto;border-radius:0.375rem;display:block;margin-bottom:0.5rem;">
                            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;margin-bottom:0.5rem;">
                                <input type="checkbox" name="remove_image" value="1"> Remove this image
                            </label>
                        <?php endif; ?>
                        <input type="hidden" name="existing_image_path" value="<?= e($editItem['image_path'] ?? '') ?>">
                        <input class="form-input" type="file" name="cover_image" accept="image/*">
                    </div>
                    <button type="submit" name="save_item" class="btn btn--primary"><?= $editItem ? 'Update' : 'Add' ?> Item</button>
                </form>
            </div>

            <!-- List -->
            <?php if (!empty($items)): ?>
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Service Items (<?= count($items) ?>)</h2></div>
                <table class="admin-table">
                    <thead><tr><th>Title</th><th>Category</th><th>Years</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= e($item['title']) ?></strong></td>
                            <td><?= e($item['category'] ?? '') ?></td>
                            <td><?= $item['start_year'] ? e($item['start_year']) . ($item['end_year'] ? '–' . e($item['end_year']) : '–Present') : '—' ?></td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?= $item['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">Edit</a>
                                <form method="POST" enctype="multipart/form-data" style="display:inline;" onsubmit="return confirm('Delete?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
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