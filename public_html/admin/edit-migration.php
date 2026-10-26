<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Optional featured panel for the public page (shared helper).
$featuredMsg = handleFeaturedBlockSave('migration-studies', 'Migration Studies');


$success = '';
$editFaculty = null;
$editCourse = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    
    if ($_POST['action'] === 'add_faculty') {
        $stmt = $pdo->prepare("INSERT INTO migration_faculty (name, department, profile_url, link_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([sanitizeInput($_POST['name'], 200), sanitizeInput($_POST['department'] ?? '', 200), sanitizeUrl($_POST['profile_url'] ?? '') ?: '', sanitizeUrl($_POST['link_url'] ?? '') ?: '']);
        $success = 'Faculty member added!';
    } elseif ($_POST['action'] === 'edit_faculty' && isset($_POST['id'])) {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) {
            $stmt = $pdo->prepare("UPDATE migration_faculty SET name=?, department=?, profile_url=?, link_url=? WHERE id=?");
            $stmt->execute([sanitizeInput($_POST['name'], 200), sanitizeInput($_POST['department'] ?? '', 200), sanitizeUrl($_POST['profile_url'] ?? '') ?: '', sanitizeUrl($_POST['link_url'] ?? '') ?: '', $id]);
            $success = 'Faculty member updated!';
        }
    } elseif ($_POST['action'] === 'add_course') {
        $stmt = $pdo->prepare("INSERT INTO migration_courses (code, title, level, description, link_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([sanitizeInput($_POST['code'] ?? '', 50), sanitizeInput($_POST['title'], 200), sanitizeEnum($_POST['level'], ['graduate', 'undergraduate'], 'graduate'), sanitizeInput($_POST['description'] ?? '', MAX_TEXTAREA_LENGTH), sanitizeUrl($_POST['link_url'] ?? '') ?: '']);
        $success = 'Migration course added!';
    } elseif ($_POST['action'] === 'edit_course' && isset($_POST['id'])) {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) {
            $stmt = $pdo->prepare("UPDATE migration_courses SET code=?, title=?, level=?, description=?, link_url=? WHERE id=?");
            $stmt->execute([sanitizeInput($_POST['code'] ?? '', 50), sanitizeInput($_POST['title'], 200), sanitizeEnum($_POST['level'], ['graduate', 'undergraduate'], 'graduate'), sanitizeInput($_POST['description'] ?? '', MAX_TEXTAREA_LENGTH), sanitizeUrl($_POST['link_url'] ?? '') ?: '', $id]);
            $success = 'Course updated!';
        }
    } elseif ($_POST['action'] === 'delete_faculty') {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) $pdo->prepare("DELETE FROM migration_faculty WHERE id = ?")->execute([$id]);
        $success = 'Faculty member deleted.';
    } elseif ($_POST['action'] === 'delete_course') {
        $id = sanitizeInt($_POST['id'], 1);
        if ($id) $pdo->prepare("DELETE FROM migration_courses WHERE id = ?")->execute([$id]);
        $success = 'Course deleted.';
    }
}

if (isset($_GET['edit_faculty'])) {
    $id = sanitizeInt($_GET['edit_faculty'], 1);
    if ($id) { $stmt = $pdo->prepare("SELECT * FROM migration_faculty WHERE id = ?"); $stmt->execute([$id]); $editFaculty = $stmt->fetch(); }
}
if (isset($_GET['edit_course'])) {
    $id = sanitizeInt($_GET['edit_course'], 1);
    if ($id) { $stmt = $pdo->prepare("SELECT * FROM migration_courses WHERE id = ?"); $stmt->execute([$id]); $editCourse = $stmt->fetch(); }
}

$faculty = getMigrationFaculty();
$gradCourses = getMigrationCourses('graduate');
$ugCourses = getMigrationCourses('undergraduate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Migration Studies | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-header">
            <h1 class="admin-header__title">Edit Migration Studies</h1>
            <a href="<?= SITE_URL ?>/migration-studies.php" target="_blank" class="btn btn--outline">Preview ↗</a>
        </div>

        <?php if (!empty($featuredMsg)): ?><div class="alert alert--success"><?= e($featuredMsg) ?></div><?php endif; ?>
        <?= renderFeaturedBlockCard('migration-studies', 'Migration Studies') ?>

        <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>

        <!-- Faculty Form -->
        <div class="admin-card">
            <div class="admin-card__header">
                <h2 class="admin-card__title"><?= $editFaculty ? 'Edit Faculty' : 'Add Faculty Member' ?></h2>
                <?php if ($editFaculty): ?><a href="edit-migration.php" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">← Cancel</a><?php endif; ?>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= $editFaculty ? 'edit_faculty' : 'add_faculty' ?>">
                <?php if ($editFaculty): ?><input type="hidden" name="id" value="<?= $editFaculty['id'] ?>"><?php endif; ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Name</label><input class="form-input" type="text" name="name" required maxlength="200" value="<?= e($editFaculty['name'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Department</label><input class="form-input" type="text" name="department" maxlength="200" value="<?= e($editFaculty['department'] ?? '') ?>"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Profile URL</label><input class="form-input" type="url" name="profile_url" maxlength="500" value="<?= e($editFaculty['profile_url'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Additional Link</label><input class="form-input" type="url" name="link_url" maxlength="500" value="<?= e($editFaculty['link_url'] ?? '') ?>"></div>
                </div>
                <button type="submit" class="btn btn--primary"><?= $editFaculty ? 'Update Faculty' : 'Add Faculty' ?></button>
            </form>
        </div>

        <!-- Existing Faculty -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Faculty (<?= count($faculty) ?>)</h2></div>
            <?php foreach ($faculty as $f): ?>
            <div style="display:flex;align-items:center;gap:1rem;padding:0.5rem 0;border-bottom:1px solid var(--admin-border);">
                <div style="flex:1;font-size:0.875rem;"><strong><?= e($f['name']) ?></strong> — <?= e($f['department'] ?? '') ?>
                    <?php if ($f['profile_url']): ?> <a href="<?= e($f['profile_url']) ?>" target="_blank" rel="noopener" style="color:var(--admin-accent);">🔗</a><?php endif; ?>
                </div>
                <a href="?edit_faculty=<?= $f['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Edit</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_faculty"><input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Delete</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Course Form -->
        <div class="admin-card">
            <div class="admin-card__header">
                <h2 class="admin-card__title"><?= $editCourse ? 'Edit Course' : 'Add Migration Course' ?></h2>
                <?php if ($editCourse): ?><a href="edit-migration.php" class="btn btn--outline" style="padding:0.25rem 0.75rem;font-size:0.75rem;">← Cancel</a><?php endif; ?>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= $editCourse ? 'edit_course' : 'add_course' ?>">
                <?php if ($editCourse): ?><input type="hidden" name="id" value="<?= $editCourse['id'] ?>"><?php endif; ?>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Code</label><input class="form-input" type="text" name="code" maxlength="50" value="<?= e($editCourse['code'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Title</label><input class="form-input" type="text" name="title" required maxlength="200" value="<?= e($editCourse['title'] ?? '') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Level</label>
                    <select class="form-select" name="level">
                        <option value="graduate" <?= ($editCourse['level'] ?? '') === 'graduate' ? 'selected' : '' ?>>Graduate</option>
                        <option value="undergraduate" <?= ($editCourse['level'] ?? '') === 'undergraduate' ? 'selected' : '' ?>>Undergraduate</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"><?= e($editCourse['description'] ?? '') ?></textarea></div>
                <div class="form-group"><label class="form-label">Link URL</label><input class="form-input" type="url" name="link_url" maxlength="500" value="<?= e($editCourse['link_url'] ?? '') ?>"></div>
                <button type="submit" class="btn btn--primary"><?= $editCourse ? 'Update Course' : 'Add Course' ?></button>
            </form>
        </div>

        <!-- Existing Courses -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Migration Courses</h2></div>
            <?php foreach (array_merge($gradCourses, $ugCourses) as $c): ?>
            <div style="display:flex;align-items:center;gap:1rem;padding:0.5rem 0;border-bottom:1px solid var(--admin-border);">
                <div style="flex:1;font-size:0.875rem;"><?= e($c['code'] ?? '') ?> <strong><?= e($c['title']) ?></strong>
                    <span class="badge <?= $c['level'] === 'graduate' ? 'badge--warning' : 'badge--success' ?>"><?= e($c['level']) ?></span>
                </div>
                <a href="?edit_course=<?= $c['id'] ?>" class="btn btn--outline" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Edit</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_course"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn--danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Delete</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>const SITE_URL = '<?= SITE_URL ?>';</script>
<script src="<?= asset('assets/js/admin.js') ?>"></script>
</body></html>
