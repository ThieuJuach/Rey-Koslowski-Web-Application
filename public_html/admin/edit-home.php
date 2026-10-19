<?php
/**
 * Admin: Edit Home / About Page
 * Includes welcome message, header settings, CV, about content, profile photo
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$page = getPageContent('home');
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    if (isset($_POST['save_header'])) {
        updateSetting('site_title', sanitizeInput($_POST['site_title'] ?? '', MAX_TITLE_LENGTH));
        updateSetting('site_subtitle', sanitizeInput($_POST['site_subtitle'] ?? '', MAX_TITLE_LENGTH));
        updateSetting('site_affiliation', sanitizeInput($_POST['site_affiliation'] ?? '', MAX_INPUT_LENGTH));
        $success = 'Header settings updated!';
    }
    if (isset($_POST['save_welcome'])) {
        updateSetting('welcome_message', sanitizeInput($_POST['welcome_message'] ?? '', MAX_TEXTAREA_LENGTH));
        updateSetting('welcome_message_enabled', isset($_POST['welcome_enabled']) ? '1' : '0');
        $success = 'Welcome message updated!';
    }
    if (isset($_POST['save_about'])) {
        $content = sanitizeInput($_POST['content'] ?? '', MAX_TEXTAREA_LENGTH);
        $stmt = $pdo->prepare("UPDATE pages SET content = ?, updated_at = NOW() WHERE slug = 'home'");
        $stmt->execute([$content]);
        $success = 'About content updated successfully!';
        $page = getPageContent('home');
    }
    if (isset($_POST['save_cv'])) {
        $cvUrl = sanitizeUrl($_POST['cv_url'] ?? '');
        if ($cvUrl !== false) {
            updateSetting('cv_url', $cvUrl);
        }
        if (!empty($_FILES['cv_file']['name'])) {
            $path = handleFileUpload($_FILES['cv_file'], 'pdfs');
            if ($path) {
                updateSetting('cv_file_path', $path);
                $success = 'CV uploaded successfully!';
            }
        } else {
            $success = 'CV settings saved!';
        }
    }
    if (isset($_POST['save_profile_photo'])) {
        if (!empty($_FILES['profile_photo']['name'])) {
            $path = handleFileUpload($_FILES['profile_photo'], 'images');
            if ($path) {
                $altText = sanitizeInput($_POST['alt_text'] ?? 'Rey Koslowski', MAX_TITLE_LENGTH);
                $caption = sanitizeInput($_POST['caption'] ?? '', MAX_TITLE_LENGTH);
                // Remove old profile image
                $pdo->prepare("DELETE FROM page_images WHERE page_slug = 'home' AND image_type = 'profile'")->execute();
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO page_images (page_slug, image_path, alt_text, caption, image_type) VALUES ('home', ?, ?, ?, 'profile')");
                $stmt->execute([$path, $altText, $caption]);
                $success = 'Profile photo uploaded!';
            } else {
                $success = 'Upload failed. Check file type and size.';
            }
        }
    }
    if (isset($_POST['save_hero_image'])) {
        if (isset($_POST['remove_hero_image'])) {
            $pdo->prepare("DELETE FROM page_images WHERE page_slug = 'home' AND image_type = 'featured'")->execute();
            $success = 'Hero image removed.';
        } elseif (!empty($_FILES['hero_image']['name'])) {
            $path = handleFileUpload($_FILES['hero_image'], 'images');
            if ($path) {
                $pdo->prepare("DELETE FROM page_images WHERE page_slug = 'home' AND image_type = 'featured'")->execute();
                $stmt = $pdo->prepare("INSERT INTO page_images (page_slug, image_path, alt_text, image_type, is_visible) VALUES ('home', ?, 'Homepage hero image', 'featured', 1)");
                $stmt->execute([$path]);
                $success = 'Hero image uploaded!';
            } else {
                $success = 'Upload failed. Check file type and size.';
            }
        }
    }
}

$cvUrl = getSetting('cv_url');
$cvFilePath = getSetting('cv_file_path');
$siteTitle = getSetting('site_title', 'Rey Koslowski');
$siteSubtitle = getSetting('site_subtitle', 'Professor of Political Science');
$siteAffiliation = getSetting('site_affiliation');
$welcomeMsg = getSetting('welcome_message');
$welcomeEnabled = getSetting('welcome_message_enabled', '0');
$profileImage = getProfileImage();
$heroImage = getPageFeaturedImage('home');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Home / About | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="admin-header">
                <h1 class="admin-header__title">Edit Home / About Page</h1>
                <a href="<?= SITE_URL ?>/" target="_blank" class="btn btn--outline">Preview ↗</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert--success"><?= e($success) ?></div>
            <?php endif; ?>

            <!-- Header Settings -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Header Information</h2>
                </div>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">Name / Title</label>
                        <input class="form-input" type="text" name="site_title" value="<?= e($siteTitle) ?>" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subtitle / Position</label>
                        <input class="form-input" type="text" name="site_subtitle" value="<?= e($siteSubtitle) ?>" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Affiliation (HTML allowed for formatting)</label>
                        <textarea class="form-textarea" name="site_affiliation" rows="3"><?= e($siteAffiliation) ?></textarea>
                    </div>
                    <button type="submit" name="save_header" class="btn btn--primary">Save Header</button>
                </form>
            </div>

            <!-- Hero Image -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Hero Image (full-screen background)</h2>
                </div>
                <?php if ($heroImage): ?>
                <div style="margin-bottom:1rem;">
                    <img src="<?= SITE_URL . '/' . e($heroImage['image_path']) ?>" alt="Current hero" style="max-width:100%;max-height:220px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--admin-border);">
                    <p style="font-size:0.8125rem;color:var(--admin-success);margin-top:0.5rem;">Hero image set ✓</p>
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">Upload Hero Image (JPG, PNG, WebP — use a high-resolution landscape photo)</label>
                        <input class="form-input" type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                        <button type="submit" name="save_hero_image" class="btn btn--primary">Upload Hero Image</button>
                        <?php if ($heroImage): ?>
                        <button type="submit" name="save_hero_image" value="1" onclick="document.querySelector('[name=remove_hero_image]').value='1';" class="btn btn--danger" style="display:none;"></button>
                        <button type="button" class="btn btn--outline" onclick="if(confirm('Remove hero image?')){var f=this.closest('form');var i=document.createElement('input');i.type='hidden';i.name='remove_hero_image';i.value='1';f.appendChild(i);f.submit();}">Remove Image</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Welcome Message -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Welcome Message (floating widget)</h2>
                </div>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="checkbox" name="welcome_enabled" value="1" <?= $welcomeEnabled === '1' ? 'checked' : '' ?>>
                            <span class="form-label" style="margin:0;">Enable welcome message</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message (HTML links allowed)</label>
                        <textarea class="form-textarea" name="welcome_message" rows="5"><?= e($welcomeMsg) ?></textarea>
                        <small style="color:var(--color-muted);">This message appears as a floating widget on the bottom-right of the homepage.</small>
                    </div>
                    <button type="submit" name="save_welcome" class="btn btn--primary">Save Welcome Message</button>
                </form>
            </div>

            <!-- Profile Photo -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Profile Photo</h2>
                </div>
                <?php if ($profileImage): ?>
                <div style="margin-bottom:1rem;">
                    <img src="<?= SITE_URL . '/' . e($profileImage['image_path']) ?>" alt="Current profile" style="max-width:200px;border-radius:var(--radius);border:1px solid var(--admin-border);">
                    <p style="font-size:0.8125rem;color:var(--admin-success);margin-top:0.5rem;">Current photo uploaded ✓</p>
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">Upload Photo (JPG, PNG, WebP)</label>
                        <input class="form-input" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Alt Text</label>
                            <input class="form-input" type="text" name="alt_text" value="Rey Koslowski" maxlength="300">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Caption (optional)</label>
                            <input class="form-input" type="text" name="caption" value="" maxlength="500">
                        </div>
                    </div>
                    <button type="submit" name="save_profile_photo" class="btn btn--primary">Upload Photo</button>
                </form>
            </div>

            <!-- CV Settings -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Curriculum Vitae (CV)</h2>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">CV Link (external URL)</label>
                        <input class="form-input" type="url" name="cv_url" value="<?= e($cvUrl) ?>" placeholder="https://example.com/cv.pdf" maxlength="2048">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Or Upload CV (PDF)</label>
                        <input class="form-input" type="file" name="cv_file" accept="application/pdf">
                        <?php if ($cvFilePath): ?>
                        <small style="color:var(--admin-success);">Current file: <?= e($cvFilePath) ?></small>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="save_cv" class="btn btn--primary">Save CV Settings</button>
                </form>
            </div>

            <!-- About Content -->
            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">About Content</h2>
                </div>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">Biography Content</label>
                        <textarea class="form-textarea" name="content" rows="15"><?= e($page['content'] ?? '') ?></textarea>
                        <small style="color:var(--color-muted);">
                            <strong>Formatting guide:</strong> Paragraphs and line breaks are preserved exactly as you type them.
                            Capitalization is kept as-is. You can use basic HTML for links: <code>&lt;a href="https://..."&gt;link text&lt;/a&gt;</code>,
                            bold: <code>&lt;strong&gt;text&lt;/strong&gt;</code>, 
                            italic: <code>&lt;em&gt;text&lt;/em&gt;</code>,
                            and lists: <code>&lt;ul&gt;&lt;li&gt;item&lt;/li&gt;&lt;/ul&gt;</code>.
                        </small>
                    </div>
                    <button type="submit" name="save_about" class="btn btn--primary">Save About Content</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>