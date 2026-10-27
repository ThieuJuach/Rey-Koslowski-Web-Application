<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Optional featured panel for the public page (shared helper).
$featuredMsg = handleFeaturedBlockSave('contact', 'Contact');


$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save_featured'])) {
    requireCsrf();
    $settings = [
        'contact_email' => sanitizeInput($_POST['email'] ?? '', 100),
        'contact_phone' => sanitizeInput($_POST['phone'] ?? '', 30),
        'office_address' => sanitizeInput($_POST['address'] ?? '', MAX_INPUT_LENGTH),
        'google_scholar_url' => sanitizeUrl($_POST['google_scholar'] ?? '') ?: '',
        'researchgate_url' => sanitizeUrl($_POST['researchgate'] ?? '') ?: '',
        'orcid_url' => sanitizeUrl($_POST['orcid'] ?? '') ?: '',
        'university_url' => sanitizeUrl($_POST['university'] ?? '') ?: '',
    ];
    foreach ($settings as $key => $value) {
        updateSetting($key, $value);
    }
    $success = 'Contact info updated!';
}

$email = getSetting('contact_email');
$phone = getSetting('contact_phone');
$address = getSetting('office_address');
$scholar = getSetting('google_scholar_url');
$rg = getSetting('researchgate_url');
$orcid = getSetting('orcid_url');
$uni = getSetting('university_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Contact | <?= SITE_TITLE ?> Admin</title>
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
            <h1 class="admin-header__title">Edit Contact Info</h1>
            <a href="<?= SITE_URL ?>/contact.php" target="_blank" class="btn btn--outline">Preview ↗</a>
        </div>

        <?php if (!empty($featuredMsg)): ?><div class="alert alert--success"><?= e($featuredMsg) ?></div><?php endif; ?>
        <?= renderFeaturedBlockCard('contact', 'Contact') ?>

        <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Contact Details</h2></div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" value="<?= e($email) ?>" maxlength="100"></div>
                <div class="form-group"><label class="form-label">Phone</label><input class="form-input" type="text" name="phone" value="<?= e($phone) ?>" maxlength="30"></div>
                <div class="form-group"><label class="form-label">Office Address</label><textarea class="form-textarea" name="address" rows="3"><?= e($address) ?></textarea></div>
                <div class="form-group"><label class="form-label">University Profile URL</label><input class="form-input" type="url" name="university" value="<?= e($uni) ?>" maxlength="500"></div>
                <hr style="margin:1.5rem 0;border-color:var(--admin-border);">
                <h3 style="font-family:var(--font-heading);margin-bottom:1rem;">Academic Profiles</h3>
                <div class="form-group"><label class="form-label">Google Scholar URL</label><input class="form-input" type="url" name="google_scholar" value="<?= e($scholar) ?>" maxlength="500"></div>
                <div class="form-group"><label class="form-label">ResearchGate URL</label><input class="form-input" type="url" name="researchgate" value="<?= e($rg) ?>" maxlength="500"></div>
                <div class="form-group"><label class="form-label">ORCID URL</label><input class="form-input" type="url" name="orcid" value="<?= e($orcid) ?>" maxlength="500"></div>
                <button type="submit" class="btn btn--primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>
</body></html>
