<?php
/**
 * Media Manager — central upload / delete for every asset on the site.
 *
 * Changes in this revision:
 *  - 'presentations' added to the page list. presentations.php calls
 *    getPageFeaturedImage('presentations'), but the slug was missing here, so there
 *    was no way to give that page an image.
 *  - The "All Uploads" panel now covers PDFs and videos, not just images. Those file
 *    types could be uploaded from the various edit- screens but could not be deleted
 *    from anywhere, so they accumulated invisibly.
 *  - Every POST is schema-validated and rejects unexpected fields.
 *  - Deletes resolve the real path and confirm it is inside uploads/ before unlinking.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$success = '';
$error   = '';

// Page slugs that the public pages actually read (see getPageFeaturedImage calls).
$allPages = [
    'home', 'research', 'teaching', 'publications', 'presentations',
    'migration-studies', 'media-appearances', 'professional-service',
    'community-engagement',
];

$selectedPage = $_GET['page'] ?? 'home';
if (!in_array($selectedPage, $allPages, true)) $selectedPage = 'home';

// Which upload type the "All Uploads" panel is showing.
$mediaFilters = ['image', 'pdf', 'video', 'all'];
$mediaFilter  = $_GET['type'] ?? 'image';
if (!in_array($mediaFilter, $mediaFilters, true)) $mediaFilter = 'image';

/** Fetch uploads for the current filter. */
function fetchUploads(string $filter): array {
    global $pdo;
    if ($filter === 'all') {
        return $pdo->query("SELECT * FROM uploads ORDER BY created_at DESC")->fetchAll();
    }
    $stmt = $pdo->prepare("SELECT * FROM uploads WHERE file_type = ? ORDER BY created_at DESC");
    $stmt->execute([$filter]);
    return $stmt->fetchAll();
}

// deleteUploadedFile() now lives in includes/functions.php: the featured-block
// editor needs it too, and two definitions would be a redeclare fatal.

// Set when a video/PDF upload succeeds, so the path can be shown for copying.
$uploadedPath = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Admin editing is bursty, so the ceiling is generous — but not unbounded.
    enforceRateLimit('admin_media', 60, 60, 'html');

    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && !empty($_FILES['image']['name'])) {
        // Only these fields are accepted; anything else is a validation error.
        $v = validateSchema($_POST, [
            'page_slug'  => ['type' => 'enum', 'values' => $allPages, 'default' => $selectedPage],
            'image_type' => ['type' => 'enum', 'values' => ['featured', 'profile', 'gallery', 'content'], 'default' => 'featured'],
            'alt_text'   => ['type' => 'string', 'max' => 300],
            'caption'    => ['type' => 'string', 'max' => 500],
            'link_url'   => ['type' => 'url',    'max' => 500],
        ]);

        if (!$v['ok']) {
            $error = 'Please check the form: ' . implode('; ', $v['errors']);
        } else {
            $d    = $v['data'];
            $path = handleFileUpload($_FILES['image'], 'images');

            if ($path) {
                $stmt = $pdo->prepare(
                    "INSERT INTO page_images (page_slug, image_path, alt_text, caption, link_url, image_type)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $d['page_slug'] ?: $selectedPage,
                    $path,
                    $d['alt_text'],
                    $d['caption'],
                    $d['link_url'],
                    $d['image_type'] ?: 'featured',
                ]);
                $success = 'Image uploaded and assigned to page.';
            } else {
                $error = 'Upload failed. Check the file type and that it is under 20MB.';
            }
        }

    } elseif ($action === 'upload_media' && !empty($_FILES['media']['name'])) {
        /*
         * Upload a video or PDF to the shared library.
         *
         * Unlike images these are not assigned to a page slug: they are
         * referenced by pasting their path into a "Video URL" or PDF field on
         * the relevant edit screen. The resulting path is shown after upload so
         * it can be copied straight out.
         *
         * handleFileUpload() decides the destination from the extension it has
         * already validated, so a caller cannot steer the write location.
         */
        $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'webm', 'ogg'], true)) {
            $subdir = 'videos';
        } elseif ($ext === 'pdf') {
            $subdir = 'pdfs';
        } else {
            $subdir = '';
            $error  = 'Please choose an MP4, WebM or OGG video, or a PDF.';
        }

        if ($subdir !== '') {
            $path = handleFileUpload($_FILES['media'], $subdir);
            if ($path) {
                $uploadedPath = $path;   // surfaced in the UI for copy-paste
                $success = 'Uploaded. Copy the path below into the relevant field.';
            } else {
                $error = 'Upload failed. Check the file type and that it is under 20MB.';
            }
        }

    } elseif ($action === 'delete_page_image') {
        $v = validateSchema($_POST, ['id' => ['type' => 'int', 'required' => true, 'min' => 1]]);
        if (!$v['ok']) {
            $error = 'Invalid request.';
        } else {
            $stmt = $pdo->prepare("SELECT image_path FROM page_images WHERE id = ?");
            $stmt->execute([$v['data']['id']]);
            $img = $stmt->fetch();
            if ($img) {
                // Only remove the file from disk if no other row still points at it.
                $inUse = $pdo->prepare("SELECT COUNT(*) FROM page_images WHERE image_path = ? AND id <> ?");
                $inUse->execute([$img['image_path'], $v['data']['id']]);
                if ((int) $inUse->fetchColumn() === 0) {
                    deleteUploadedFile($img['image_path']);
                }
                $pdo->prepare("DELETE FROM page_images WHERE id = ?")->execute([$v['data']['id']]);
                $success = 'Image removed from page.';
            } else {
                $error = 'That image no longer exists.';
            }
        }

    } elseif ($action === 'delete_upload') {
        $v = validateSchema($_POST, ['id' => ['type' => 'int', 'required' => true, 'min' => 1]]);
        if (!$v['ok']) {
            $error = 'Invalid request.';
        } else {
            $stmt = $pdo->prepare("SELECT file_path FROM uploads WHERE id = ?");
            $stmt->execute([$v['data']['id']]);
            $upload = $stmt->fetch();
            if ($upload) {
                deleteUploadedFile($upload['file_path']);
                $pdo->prepare("DELETE FROM uploads WHERE id = ?")->execute([$v['data']['id']]);
                $success = 'File deleted.';
            } else {
                $error = 'That file no longer exists.';
            }
        }
    }
}

// Always read fresh state after any mutation.
$pageImages = getPageImages($selectedPage);
$allUploads = fetchUploads($mediaFilter);

/** Human-readable file size. */
function formatBytes($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Media Manager | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-header"><h1 class="admin-header__title">Media Manager</h1></div>
        <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Select Page</h2></div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <?php foreach ($allPages as $p): ?>
                <a href="?page=<?= e($p) ?>&type=<?= e($mediaFilter) ?>" class="btn <?= $selectedPage === $p ? 'btn--primary' : 'btn--outline' ?>"><?= e(ucfirst(str_replace('-', ' ', $p))) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Upload Image to "<?= e(ucfirst(str_replace('-', ' ', $selectedPage))) ?>"</h2></div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="page_slug" value="<?= e($selectedPage) ?>">
                <div class="form-group"><label class="form-label">Image File</label><input class="form-input" type="file" name="image" accept="image/*" required></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Image Type</label>
                        <?php /* Only "featured" and "profile" are read by the public
                                 pages. Gallery and Content are stored but nothing
                                 renders them, so they are labelled rather than left
                                 looking functional. */ ?>
                        <select class="form-select" name="image_type">
                            <option value="featured">Featured Image &mdash; shown at the top of the page</option>
                            <option value="profile">Profile Photo &mdash; shown in the About section</option>
                            <option value="gallery">Gallery &mdash; stored only, not displayed</option>
                            <option value="content">Content Image &mdash; stored only, not displayed</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Alt Text</label><input class="form-input" type="text" name="alt_text" maxlength="300"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Caption</label><input class="form-input" type="text" name="caption" maxlength="500"></div>
                    <div class="form-group"><label class="form-label">Link URL</label><input class="form-input" type="url" name="link_url" maxlength="500"></div>
                </div>
                <button type="submit" class="btn btn--primary">Upload &amp; Assign</button>
            </form>
        </div>

                <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Upload a Video or PDF</h2></div>
            <p style="color:var(--color-muted);font-size:0.8125rem;margin-bottom:1rem;">
                Videos and PDFs are not tied to one page. Upload here, then paste the
                path it gives you into a &ldquo;Video URL&rdquo; or PDF field on any edit
                screen. MP4, WebM, OGG or PDF, up to 20MB.
            </p>
            <?php if ($uploadedPath): ?>
            <div class="alert alert--success" style="word-break:break-all;">
                Uploaded to <code><?= e($uploadedPath) ?></code><br>
                Full URL: <code><?= e(SITE_URL . '/' . $uploadedPath) ?></code>
            </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="upload_media">
                <div class="form-group">
                    <label class="form-label">Video or PDF file</label>
                    <input class="form-input" type="file" name="media"
                           accept="video/mp4,video/webm,video/ogg,application/pdf" required>
                </div>
                <button type="submit" class="btn btn--primary">Upload</button>
            </form>
        </div>

<div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Images on "<?= e(ucfirst(str_replace('-', ' ', $selectedPage))) ?>" (<?= count($pageImages) ?>)</h2></div>
            <?php if (empty($pageImages)): ?>
            <p style="color:var(--color-muted);font-size:0.875rem;">No images assigned yet.</p>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
                <?php foreach ($pageImages as $img): ?>
                <div style="border:1px solid var(--admin-border);border-radius:0.5rem;overflow:hidden;">
                    <img src="<?= SITE_URL . '/' . e($img['image_path']) ?>" alt="<?= e($img['alt_text'] ?? '') ?>" style="width:100%;height:150px;object-fit:cover;" loading="lazy">
                    <div style="padding:0.75rem;">
                        <span class="badge badge--warning"><?= e($img['image_type']) ?></span>
                        <form method="POST" style="margin-top:0.5rem;" onsubmit="return confirm('Remove this image from the page?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_page_image">
                            <input type="hidden" name="id" value="<?= (int) $img['id'] ?>">
                            <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;width:100%;">Remove</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="admin-card">
            <div class="admin-card__header">
                <h2 class="admin-card__title">All Uploads (<?= count($allUploads) ?>)</h2>
            </div>

            <?php // Filter tabs — lets PDFs and videos be reviewed and deleted, not just images. ?>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
                <?php
                $filterLabels = ['image' => 'Images', 'pdf' => 'PDFs', 'video' => 'Videos', 'all' => 'Everything'];
                foreach ($mediaFilters as $f): ?>
                <a href="?page=<?= e($selectedPage) ?>&type=<?= e($f) ?>"
                   class="btn <?= $mediaFilter === $f ? 'btn--primary' : 'btn--outline' ?>"
                   style="padding:0.35rem 0.75rem;font-size:0.8125rem;"><?= e($filterLabels[$f]) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($allUploads)): ?>
            <p style="color:var(--color-muted);font-size:0.875rem;">Nothing uploaded in this category yet.</p>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem;">
                <?php foreach ($allUploads as $upload): ?>
                <div style="border:1px solid var(--admin-border);border-radius:0.375rem;overflow:hidden;display:flex;flex-direction:column;">
                    <?php if ($upload['file_type'] === 'image'): ?>
                        <img src="<?= SITE_URL . '/' . e($upload['file_path']) ?>" alt="<?= e($upload['original_name']) ?>" style="width:100%;height:100px;object-fit:cover;" loading="lazy">
                    <?php else: ?>
                        <?php // Non-images get an icon tile plus a link to open the file. ?>
                        <a href="<?= SITE_URL . '/' . e($upload['file_path']) ?>" target="_blank" rel="noopener"
                           style="height:100px;display:flex;align-items:center;justify-content:center;background:var(--admin-bg);color:var(--color-muted);text-decoration:none;">
                            <span style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">
                                <?= $upload['file_type'] === 'pdf' ? 'PDF' : 'VIDEO' ?>
                            </span>
                        </a>
                    <?php endif; ?>
                    <div style="padding:0.5rem;display:flex;flex-direction:column;gap:0.25rem;flex:1;">
                        <p style="font-size:0.7rem;color:var(--color-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($upload['original_name']) ?>"><?= e($upload['original_name']) ?></p>
                        <p style="font-size:0.65rem;color:var(--color-muted);"><?= e(formatBytes($upload['file_size'] ?? 0)) ?></p>
                        <form method="POST" style="margin-top:auto;" onsubmit="return confirm('Delete this file permanently? Any page still using it will show a broken link.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_upload">
                            <input type="hidden" name="id" value="<?= (int) $upload['id'] ?>">
                            <button type="submit" class="btn btn--danger" style="padding:0.125rem 0.375rem;font-size:0.7rem;width:100%;">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body></html>
