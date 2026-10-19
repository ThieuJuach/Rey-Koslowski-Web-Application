<?php
/**
 * Admin Login with email-based 2FA
 *
 * SECURITY:
 * - CSRF protection on all forms
 * - Rate limiting on login attempts, per IP and per username
 * - Rate limiting on OTP verification attempts and on OTP sends
 * - Session regeneration on successful login
 * - Input validation and sanitization
 * - The verification code is never rendered on this page; it is emailed
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// Already logged in? Redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';       // Plain text, always escaped on output
$show2fa = false;
$clientIp = getClientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCsrf();

    if (isset($_POST['verify_otp'])) {
        // ── 2FA Verification Step ──
        $adminId = $_SESSION['pending_admin_id'] ?? 0;
        $code = sanitizeInput($_POST['otp_code'] ?? '', 6);

        // Rate limit OTP attempts, per IP and per account being verified. Without the
        // account dimension an attacker could rotate IPs to brute-force a 6-digit code.
        $otpIpLimited      = isRateLimited('otp_ip_' . $clientIp, 10, 300);
        $otpAccountLimited = $adminId && isRateLimited('otp_acct_' . $adminId, 10, 300);

        if ($otpIpLimited || $otpAccountLimited) {
            $error = 'Too many verification attempts. Please wait 5 minutes.';
            $show2fa = true;
        } elseif ($adminId && verifyOTP($adminId, $code)) {
            // OTP verified — complete login. Clear every bucket for this session so a
            // successful sign-in does not leave the user throttled.
            complete2FALogin();
            clearRateLimit('login_ip_' . $clientIp);
            clearRateLimit('otp_ip_' . $clientIp);
            clearRateLimit('otp_acct_' . $adminId);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid or expired verification code. Please try again.';
            $show2fa = true;
        }
    } else {
        // ── Primary Login Step ──
        $username = sanitizeInput($_POST['username'] ?? '', 50);
        $password = $_POST['password'] ?? '';

        /*
         * Brute-force protection on two dimensions (OWASP Authentication cheat sheet):
         *   - per IP      : stops one host hammering many accounts
         *   - per username: stops a distributed attack on one account from many IPs
         * The per-account allowance is higher so a shared office IP is not the
         * limiting factor for a legitimate user.
         */
        $ipLimited = isRateLimited(
            'login_ip_' . $clientIp,
            LOGIN_MAX_ATTEMPTS,
            LOGIN_LOCKOUT_MINUTES * 60
        );
        $accountLimited = $username !== '' && isRateLimited(
            'login_acct_' . strtolower($username),
            LOGIN_MAX_PER_ACCOUNT,
            LOGIN_LOCKOUT_MINUTES * 60
        );

        if ($ipLimited || $accountLimited) {
            // Same message either way, so the response does not reveal which limit hit.
            $error = 'Too many login attempts. Please wait ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
        } elseif (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $result = loginAdmin($username, $password);

            if ($result['status'] === 'success') {
                clearRateLimit('login_ip_' . $clientIp);
                clearRateLimit('login_acct_' . strtolower($username));
                header('Location: dashboard.php');
                exit;
            } elseif ($result['status'] === '2fa_required') {
                /*
                 * Cap how often a code can be emailed for one account. Without this,
                 * anyone holding the password could use the login form to flood the
                 * owner's inbox, and repeated sends would keep invalidating the code
                 * the owner is actually trying to type.
                 */
                if (isRateLimited('otp_send_' . $result['admin_id'], OTP_MAX_SENDS_PER_HOUR, 3600)) {
                    $error = 'Too many verification codes requested. Please wait before trying again.';
                } else {
                    $code = generateOTP($result['admin_id']);
                    $send = sendOTPtoEmail($result['email'], $code);
                    $show2fa = true;

                    if ($send['sent']) {
                        // Show a masked address so the user knows where to look
                        // without the page disclosing the full address.
                        $error = 'We sent a verification code to ' . maskEmail($result['email'])
                               . '. It expires in ' . (int) round(OTP_TTL_SECONDS / 60) . ' minutes.';
                    } else {
                        // Never reveal the code here. The real reason is in the error
                        // log, along with a recovery copy of the code.
                        $error = 'We could not send your verification code right now. '
                               . 'Please try again in a moment, or contact your administrator.';
                        error_log('[2FA] Send failure surfaced at login: ' . $send['error']);
                    }
                }
            } else {
                // Generic error message (OWASP: don't reveal which field is wrong)
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login | <?= SITE_TITLE ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
    <style>
        .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--admin-bg); }
        .login-card { width: 100%; max-width: 420px; padding: 2.5rem; background: #fff; border-radius: 0.5rem; border: 1px solid var(--admin-border); box-shadow: var(--shadow-elevated); }
        .login-card__title { font-family: var(--font-heading); font-size: 1.5rem; font-weight: 600; text-align: center; margin-bottom: 0.5rem; color: var(--color-primary); }
        .login-card__subtitle { text-align: center; font-size: 0.875rem; color: var(--color-muted); margin-bottom: 2rem; }
        .login-card .btn { width: 100%; justify-content: center; margin-top: 0.5rem; }
    </style>
</head>
<body class="admin-body">
    <div class="login-page">
        <div class="login-card">
            <h1 class="login-card__title">Admin Login</h1>
            <p class="login-card__subtitle"><?= SITE_TITLE ?> Dashboard</p>

            <?php // Always escaped — no branch of this page emits trusted HTML. ?>
            <?php if ($error): ?>
                <div class="alert <?= $show2fa ? 'alert--success' : 'alert--error' ?>"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($show2fa): ?>
            <!-- 2FA Verification Form -->
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label" for="otp_code">Verification Code</label>
                    <input class="form-input" type="text" id="otp_code" name="otp_code" required 
                           autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                           placeholder="Enter 6-digit code" style="text-align:center;font-size:1.25rem;letter-spacing:0.5em;">
                </div>
                <button type="submit" name="verify_otp" value="1" class="btn btn--primary">Verify</button>
            </form>
            <p style="text-align:center;margin-top:1rem;font-size:0.8125rem;color:var(--color-muted);">
                <a href="login.php" style="color:var(--color-primary);">← Back to login</a>
            </p>
            <?php else: ?>
            <!-- Regular Login Form -->
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-input" type="text" id="username" name="username" required 
                           autocomplete="username" maxlength="50">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-input" type="password" id="password" name="password" required 
                           autocomplete="current-password" maxlength="255">
                </div>
                <button type="submit" class="btn btn--primary">Sign In</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
