<?php
/**
 * Admin: Manage Community Engagement Items (blog-post style)
 * CRUD with CSRF, input validation, edit buttons
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$page = getPageContent('community-engagement');
$success = '';
$error = '';
$editItem = null;

// Handle edit mode
if (isset($_GET['edit'])) {
    $editId = sanitizeInt($_GET['edit'], 1);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM community_engagement_items WHERE id = ?");
        $stmt->execute([$editId]);
        $editItem = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if (isset($_POST['save_page_content'])) {
        $content = sanitizeInput($_POST['page_content'] ?? '', MAX_TEXTAREA_LENGTH);
        $stmt = $pdo->prepare("INSERT INTO pages (slug, title, content) VALUES ('community-engagement', 'Community Engagement', ?) ON DUPLICATE KEY UPDATE content = ?, updated_at = NOW()");
        $stmt->execute([$content, $content]);
        $success = 'Page intro updated!';
        $page = getPageContent('community-engagement');
    } elseif (isset($_POST['delete_id'])) {
        $deleteId = sanitizeInt($_POST['delete_id'], 1);
        if ($deleteId) {
            $pdo->prepare("DELETE FROM community_engagement_items WHERE id = ?")->execute([$deleteId]);
            $success = 'Item deleted.';
        }
    } elseif (isset($_POST['save_item'])) {
        $title = sanitizeInput($_POST['title'] ?? '', MAX_TITLE_LENGTH);
        $content = sanitizeInput($_POST['content'] ?? '', MAX_TEXTAREA_LENGTH);
        $excerpt = sanitizeInput($_POST['excerpt'] ?? '', MAX_INPUT_LENGTH);
        $category = sanitizeInput($_POST['category'] ?? '', 100);
        $eventDate = sanitizeInput($_POST['event_date'] ?? '', 10);
        $location = sanitizeInput($_POST['location'] ?? '', 300);
        $linkUrl = sanitizeUrl($_POST['link_url'] ?? '');
        $order = sanitizeInt($_POST['display_order'] ?? 0, 0, 9999) ?? 0;

        if (empty($title)) {
            $error = 'Title is required.';
        } elseif ($linkUrl === false) {
            $error = 'Invalid URL.';
        } else {
            if ($eventDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) $eventDate = null;

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
                $stmt = $pdo->prepare("UPDATE community_engagement_items SET title=?, content=?, excerpt=?, category=?, event_date=?, location=?, link_url=?, display_order=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$title, $content, $excerpt, $category, $eventDate, $location, $linkUrl, $order, $editId]);
                $success = 'Item updated!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO community_engagement_items (title, content, excerpt, category, event_date, location, link_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $excerpt, $category, $eventDate, $location, $linkUrl, $order]);
                $success = 'Item added!';
            }
            $editItem = null;
        }
    }
}

$items = $pdo->query("SELECT * FROM community_engagement_items ORDER BY display_order ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Community Engagement | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="admin-header">
                <h1 class="admin-header__title">Community Engagement</h1>
                <a href="<?= SITE_URL ?>/community-engagement.php" target="_blank" class="btn btn--outline">Preview ↗</a>
            </div>

            <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

            <!-- Page Intro -->
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Page Introduction (optional)</h2></div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <textarea class="form-textarea" name="page_content" rows="6"><?= e($page['content'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" name="save_page_content" class="btn btn--outline">Save Intro</button>
                </form>
            </div>

            <!-- Add / Edit Item -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title"><?= $editItem ? 'Edit Item' : 'Add Engagement Item' ?></h2>
                    <?php if ($editItem): ?><a href="edit-community-engagement.php" class="btn btn--outline">Cancel Edit</a><?php endif; ?>
                </div>
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
                            <input class="form-input" type="text" name="category" value="<?= e($editItem['category'] ?? '') ?>" maxlength="100" placeholder="e.g., Workshop">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Event Date</label>
                            <input class="form-input" type="date" name="event_date" value="<?= e($editItem['event_date'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input class="form-input" type="text" name="location" value="<?= e($editItem['location'] ?? '') ?>" maxlength="300">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Content (rich text, HTML links allowed)</label>
                        <textarea class="form-textarea" name="content" rows="8"><?= e($editItem['content'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Short Excerpt</label>
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
                <div class="admin-card__header"><h2 class="admin-card__title">Engagement Items (<?= count($items) ?>)</h2></div>
                <table class="admin-table">
                    <thead><tr><th>Title</th><th>Category</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= e($item['title']) ?></strong></td>
                            <td><?= e($item['category'] ?? '') ?></td>
                            <td><?= $item['event_date'] ? date('M j, Y', strtotime($item['event_date'])) : '—' ?></td>
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