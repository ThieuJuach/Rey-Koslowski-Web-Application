<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$success = '';
$error = '';

$adminId = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT username, email, phone, two_factor_enabled FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    if (isset($_POST['change_password'])) {
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';
        if ($newPw !== $confirmPw) {
            $error = 'New passwords do not match.';
        } else {
            $result = changePassword($adminId, $_POST['current_password'] ?? '', $newPw);
            if ($result['success']) {
                $success = 'Password changed successfully!';
            } else {
                $error = $result['error'];
            }
        }
    }
    if (isset($_POST['save_2fa'])) {
        // 2FA codes are delivered by email, so the recipient address is the setting
        // that matters. It is validated strictly — an unreachable address with 2FA
        // switched on would lock this account out.
        $v = validateSchema($_POST, [
            'email'              => ['type' => 'email'],
            'two_factor_enabled' => ['type' => 'bool'],
        ]);

        $newEmail = $v['data']['email'] ?? '';
        $enabled  = !empty($v['data']['two_factor_enabled']) ? 1 : 0;

        if (!$v['ok']) {
            $error = 'Please enter a valid email address for verification codes.';
        } elseif ($enabled && $newEmail === '') {
            $error = 'Add an email address before enabling two-factor authentication — '
                   . 'otherwise there is nowhere to send your login code.';
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET email = ?, two_factor_enabled = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newEmail, $enabled, $adminId]);

            $success = $enabled
                ? 'Two-factor authentication is on. Codes will be emailed to ' . $newEmail . '.'
                : 'Two-factor authentication is off.';

            $stmt = $pdo->prepare("SELECT username, email, phone, two_factor_enabled FROM admins WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
        }
    }

    if (isset($_POST['test_otp'])) {
        // Genuinely sends an email, so it verifies the whole delivery path — the very
        // thing you want to confirm BEFORE turning 2FA on and relying on it.
        if (isRateLimited('otp_send_' . $adminId, OTP_MAX_SENDS_PER_HOUR, 3600)) {
            $error = 'Too many test codes requested. Please wait before trying again.';
        } else {
            $code = generateOTP($adminId);
            $send = sendOTPtoEmail($admin['email'] ?? '', $code);

            if ($send['sent']) {
                $success = 'Test code sent to ' . ($admin['email'] ?? '')
                         . '. If it does not arrive within a minute, check your spam folder.';
            } else {
                // Show the real reason here: this page is already behind a login, and
                // a useless "sending failed" would make the problem undiagnosable.
                $error = 'Could not send the test email: ' . $send['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Security Settings | <?= SITE_TITLE ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-header"><h1 class="admin-header__title">Security Settings</h1></div>

        <?php // Both escaped — $success now carries user-supplied email addresses.
              // It was previously printed raw so the test-OTP handler could inject
              // <strong> around the code; that handler no longer exists. ?>
        <?php if ($success): ?><div class="alert alert--success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

        <!-- Change Password -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Change Password</h2></div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group"><label class="form-label">Current Password</label><input class="form-input" type="password" name="current_password" required autocomplete="current-password"></div>
                <div class="form-group"><label class="form-label">New Password (min 8 characters)</label><input class="form-input" type="password" name="new_password" required minlength="8" maxlength="255" autocomplete="new-password"></div>
                <div class="form-group"><label class="form-label">Confirm New Password</label><input class="form-input" type="password" name="confirm_password" required minlength="8" maxlength="255" autocomplete="new-password"></div>
                <button type="submit" name="change_password" class="btn btn--primary">Change Password</button>
            </form>
        </div>

        <!-- Two-Factor Authentication -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Two-Factor Authentication (2FA)</h2></div>
            <p style="color:var(--color-muted);font-size:0.875rem;margin-bottom:1.5rem;">
                When enabled, a 6-digit code is emailed to you each time you log in.
                The code expires after <?= (int) round(OTP_TTL_SECONDS / 60) ?> minutes and can only be used once.
            </p>

            <?php // Delivery transport, so a failing setup is obvious before it matters. ?>
            <p style="font-size:0.8125rem;margin-bottom:1.5rem;">
                Sending via:
                <?php if (SMTP_CONFIGURED): ?>
                    <span class="badge badge--success">Authenticated SMTP</span>
                    <span style="color:var(--color-muted);">(<?= e(SMTP_HOST) ?>)</span>
                <?php else: ?>
                    <span class="badge badge--warning">PHP mail()</span>
                    <span style="color:var(--color-muted);">
                        — add SMTP credentials to <code>secrets.php</code> for reliable delivery.
                    </span>
                <?php endif; ?>
                <br>
                <span style="color:var(--color-muted);">From: <?= e(MAIL_FROM_ADDRESS) ?></span>
            </p>

            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label" for="tfa_email">Email address for verification codes</label>
                    <input class="form-input" type="email" id="tfa_email" name="email"
                           value="<?= e($admin['email'] ?? '') ?>"
                           placeholder="you@example.com" maxlength="100" autocomplete="email">
                    <small style="color:var(--color-muted);">
                        Codes are sent here. Use an address you can always reach — if you lose access
                        to it while 2FA is on, you will not be able to sign in.
                    </small>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;">
                        <input type="checkbox" name="two_factor_enabled" value="1" <?= $admin['two_factor_enabled'] ? 'checked' : '' ?>>
                        <span class="form-label" style="margin:0;">Enable Two-Factor Authentication</span>
                    </label>
                </div>
                <button type="submit" name="save_2fa" class="btn btn--primary">Save 2FA Settings</button>
            </form>

            <?php // Test send is offered whenever an address exists — the point is to
                  // prove delivery works BEFORE switching 2FA on. ?>
            <?php if (!empty($admin['email'])): ?>
            <hr style="margin:1.5rem 0;border-color:var(--admin-border);">
            <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <button type="submit" name="test_otp" class="btn btn--accent">Send me a test code</button>
            </form>
            <p style="color:var(--color-muted);font-size:0.8125rem;margin-top:0.5rem;">
                Sends a real email to <?= e($admin['email']) ?>. Do this before enabling 2FA
                so you know delivery works.
            </p>
            <?php endif; ?>
        </div>

        <!-- Account Info -->
        <div class="admin-card">
            <div class="admin-card__header"><h2 class="admin-card__title">Account Information</h2></div>
            <?php // Phone is no longer shown: 2FA moved to email, and nothing else
                  // used it. The DB column is left in place so no data is lost. ?>
            <p><strong>Username:</strong> <?= e($admin['username']) ?></p>
            <p><strong>Email:</strong> <?= e($admin['email'] ?: 'Not set') ?></p>
            <p><strong>2FA:</strong> <?= $admin['two_factor_enabled'] ? '<span class="badge badge--success">Enabled</span>' : '<span class="badge badge--danger">Disabled</span>' ?></p>
        </div>
    </div>
</div>
</body></html>
