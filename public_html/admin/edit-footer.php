<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $fields = [
        'footer_about_text', 'contact_email', 'contact_phone', 'office_address',
        'footer_affiliation_1_name', 'footer_affiliation_1_url',
        'footer_affiliation_2_name', 'footer_affiliation_2_url',
        'footer_affiliation_3_name', 'footer_affiliation_3_url',
        'google_scholar_url', 'researchgate_url', 'orcid_url'
    ];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitizeInput($_POST[$field], MAX_INPUT_LENGTH);
            // Validate URL fields
            if (str_contains($field, '_url') && $value) {
                $validated = sanitizeUrl($value);
                $value = ($validated !== false) ? $validated : '';
            }
            updateSetting($field, $value);
        }
    }
    $success = 'Footer settings updated!';
}

$footerAbout = getSetting('footer_about_text');
$email = getSetting('contact_email');
$phone = getSetting('contact_phone');
$address = getSetting('office_address');
$aff1Name = getSetting('footer_affiliation_1_name');
$aff1Url = getSetting('footer_affiliation_1_url');
$aff2Name = getSetting('footer_affiliation_2_name');
$aff2Url = getSetting('footer_affiliation_2_url');
$aff3Name = getSetting('footer_affiliation_3_name');
$aff3Url = getSetting('footer_affiliation_3_url');
$scholar = getSetting('google_scholar_url');
$rg = getSetting('researchgate_url');
$orcid = getSetting('orcid_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Footer | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-header"><h1 class="admin-header__title">Edit Footer</h1></div>
        <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
        <p style="color:var(--color-muted);font-size:0.875rem;margin-bottom:1.5rem;">Edit all footer content below. <strong>Quick Links are auto-generated</strong> and cannot be changed here.</p>

        <form method="POST">
            <?= csrfField() ?>
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">About Text</h2></div>
                <div class="form-group"><label class="form-label">Footer Biography / Description</label><textarea class="form-textarea" name="footer_about_text" rows="4"><?= e($footerAbout) ?></textarea></div>
            </div>
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Contact Details</h2></div>
                <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="contact_email" value="<?= e($email) ?>" maxlength="100"></div>
                <div class="form-group"><label class="form-label">Phone</label><input class="form-input" type="text" name="contact_phone" value="<?= e($phone) ?>" maxlength="30"></div>
                <div class="form-group"><label class="form-label">Office Address</label><textarea class="form-textarea" name="office_address" rows="3"><?= e($address) ?></textarea></div>
            </div>
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Affiliations</h2></div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Affiliation 1 Name</label><input class="form-input" name="footer_affiliation_1_name" value="<?= e($aff1Name) ?>" maxlength="200"></div>
                    <div class="form-group"><label class="form-label">Affiliation 1 URL</label><input class="form-input" type="url" name="footer_affiliation_1_url" value="<?= e($aff1Url) ?>" maxlength="500"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Affiliation 2 Name</label><input class="form-input" name="footer_affiliation_2_name" value="<?= e($aff2Name) ?>" maxlength="200"></div>
                    <div class="form-group"><label class="form-label">Affiliation 2 URL</label><input class="form-input" type="url" name="footer_affiliation_2_url" value="<?= e($aff2Url) ?>" maxlength="500"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Affiliation 3 Name</label><input class="form-input" name="footer_affiliation_3_name" value="<?= e($aff3Name) ?>" maxlength="200"></div>
                    <div class="form-group"><label class="form-label">Affiliation 3 URL</label><input class="form-input" type="url" name="footer_affiliation_3_url" value="<?= e($aff3Url) ?>" maxlength="500"></div>
                </div>
            </div>
            <div class="admin-card">
                <div class="admin-card__header"><h2 class="admin-card__title">Academic Profiles</h2></div>
                <div class="form-group"><label class="form-label">Google Scholar URL</label><input class="form-input" type="url" name="google_scholar_url" value="<?= e($scholar) ?>" maxlength="500"></div>
                <div class="form-group"><label class="form-label">ResearchGate URL</label><input class="form-input" type="url" name="researchgate_url" value="<?= e($rg) ?>" maxlength="500"></div>
                <div class="form-group"><label class="form-label">ORCID URL</label><input class="form-input" type="url" name="orcid_url" value="<?= e($orcid) ?>" maxlength="500"></div>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-bottom:2rem;">Save Footer Settings</button>
        </form>
    </div>
</div>
</body></html>
