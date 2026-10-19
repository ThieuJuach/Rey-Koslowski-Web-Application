<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard';

$pubCount = $pdo->query("SELECT COUNT(*) FROM publications")->fetchColumn();
$projectCount = $pdo->query("SELECT COUNT(*) FROM research_projects")->fetchColumn();
$courseCount = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$mediaCount = $pdo->query("SELECT COUNT(*) FROM media_appearances")->fetchColumn();
$uploadCount = $pdo->query("SELECT COUNT(*) FROM uploads")->fetchColumn();
$facultyCount = $pdo->query("SELECT COUNT(*) FROM migration_faculty")->fetchColumn();

// Presentations count (new)
$presCount = 0;
try { $presCount = $pdo->query("SELECT COUNT(*) FROM presentations")->fetchColumn(); } catch (Exception $e) {}
// Service items counts (new)
$profCount = 0;
try { $profCount = $pdo->query("SELECT COUNT(*) FROM professional_service_items")->fetchColumn(); } catch (Exception $e) {}
$commCount = 0;
try { $commCount = $pdo->query("SELECT COUNT(*) FROM community_engagement_items")->fetchColumn(); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="admin-header">
                <h1 class="admin-header__title">Dashboard</h1>
                <div class="admin-header__actions">
                    <a href="<?= SITE_URL ?>/" target="_blank" class="btn btn--outline">View Site ↗</a>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div class="admin-card" style="text-align: center;">
                    <p style="font-size: 2rem; font-weight: 700; color: var(--color-primary);"><?= $pubCount ?></p>
                    <p style="font-size: 0.875rem; color: var(--color-muted);">Publications</p>
                </div>
                <div class="admin-card" style="text-align: center;">
                    <p style="font-size: 2rem; font-weight: 700; color: var(--color-primary);"><?= $projectCount ?></p>
                    <p style="font-size: 0.875rem; color: var(--color-muted);">Research</p>
                </div>
                <div class="admin-card" style="text-align: center;">
                    <p style="font-size: 2rem; font-weight: 700; color: var(--color-primary);"><?= $courseCount ?></p>
                    <p style="font-size: 0.875rem; color: var(--color-muted);">Courses</p>
                </div>
                <div class="admin-card" style="text-align: center;">
                    <p style="font-size: 2rem; font-weight: 700; color: var(--color-primary);"><?= $presCount ?></p>
                    <p style="font-size: 0.875rem; color: var(--color-muted);">Presentations</p>
                </div>
                <div class="admin-card" style="text-align: center;">
                    <p style="font-size: 2rem; font-weight: 700; color: var(--color-primary);"><?= $mediaCount ?></p>
                    <p style="font-size: 0.875rem; color: var(--color-muted);">Media</p>
                </div>
                <div class="admin-card" style="text-align: center;">
                    <p style="font-size: 2rem; font-weight: 700; color: var(--color-primary);"><?= $uploadCount ?></p>
                    <p style="font-size: 0.875rem; color: var(--color-muted);">Uploads</p>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Content Management</h2>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <a href="edit-home.php" class="btn btn--primary">Edit Home / About</a>
                    <a href="edit-research.php" class="btn btn--outline">Research</a>
                    <a href="edit-teachings.php" class="btn btn--outline">Teaching</a>
                    <a href="edit-publications.php" class="btn btn--outline">Publications</a>
                    <a href="edit-presentations.php" class="btn btn--outline">Presentations</a>
                    <a href="edit-media-appearances.php" class="btn btn--outline">Media Appearances</a>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Services Pages</h2>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <a href="edit-migration.php" class="btn btn--outline">Migration Studies</a>
                    <a href="edit-professional-service.php" class="btn btn--outline">Professional Service (<?= $profCount ?>)</a>
                    <a href="edit-community-engagement.php" class="btn btn--outline">Community Engagement (<?= $commCount ?>)</a>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card__header">
                    <h2 class="admin-card__title">Site Settings</h2>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <a href="media-manager.php" class="btn btn--accent">Media Manager</a>
                    <a href="edit-footer.php" class="btn btn--outline">Footer</a>
                    <a href="edit-contact.php" class="btn btn--outline">Contact Info</a>
                    <a href="change-password.php" class="btn btn--outline">🔐 Security & 2FA</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>