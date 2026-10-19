<?php
/**
 * Authentication Helper Functions with 2FA Support
 * 
 * SECURITY (OWASP):
 * - Session timeout after 30 minutes of inactivity
 * - Session regeneration on login to prevent fixation
 * - Rate limiting on login attempts
 * - Bcrypt password hashing
 * - CSRF protection on login forms
 * - OTP codes expire after 5 minutes and are single-use
 */

/**
 * Check if admin is logged in with session timeout
 */
function isLoggedIn(): bool {
    // Session timeout (30 minutes of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        // Session expired — destroy it
        session_unset();
        session_destroy();
        return false;
    }
    
    // Update last activity timestamp
    if (isset($_SESSION['admin_id'])) {
        $_SESSION['last_activity'] = time();
    }
    
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Require authentication or redirect to login
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Attempt to log in an admin user
 * Returns: ['status' => 'success'|'2fa_required'|'error', ...]
 */
function loginAdmin(string $username, string $password): array {
    global $pdo;
    
    // Validate input lengths
    if (mb_strlen($username) > 50 || mb_strlen($password) > 255) {
        return ['status' => 'error'];
    }
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash, email, two_factor_enabled FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    /*
     * Always run a bcrypt verification, even when the username does not exist.
     *
     * Returning early for an unknown user makes that path measurably faster than the
     * known-user path, which lets an attacker enumerate valid usernames by timing
     * alone. Verifying against a throwaway hash keeps both paths the same cost.
     * (OWASP Authentication cheat sheet — account enumeration.)
     *
     * The constant below is the well-known dummy hash from PHP's own
     * password_verify() documentation. It is NOT a credential and corresponds
     * to no usable password - it exists purely to burn equivalent CPU.
     */
    $hashToCheck = $admin['password_hash']
        ?? '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    $passwordOk = password_verify($password, $hashToCheck);

    if ($admin && $passwordOk) {
        // 2FA is only enforceable if there is somewhere to send the code. Without a
        // valid address the account would be unreachable, so we sign in directly
        // rather than stranding the user at an unpassable prompt.
        $canDo2fa = $admin['two_factor_enabled']
            && filter_var($admin['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if ($canDo2fa) {
            // 2FA required — store pending state
            $_SESSION['pending_admin_id'] = $admin['id'];
            $_SESSION['pending_admin_username'] = $admin['username'];
            return [
                'status'   => '2fa_required',
                'admin_id' => $admin['id'],
                'email'    => $admin['email'],
            ];
        }
        
        // Direct login — regenerate session ID to prevent fixation (OWASP)
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['last_activity'] = time();
        
        return ['status' => 'success'];
    }
    return ['status' => 'error'];
}

/**
 * Complete 2FA login after OTP verification
 */
function complete2FALogin(): void {
    $adminId = $_SESSION['pending_admin_id'];
    $adminUsername = $_SESSION['pending_admin_username'];
    
    // Regenerate session ID (OWASP Session Fixation prevention)
    session_regenerate_id(true);
    
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_username'] = $adminUsername;
    $_SESSION['last_activity'] = time();
    
    // Clean up pending state
    unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_username']);
}

/**
 * Generate a 6-digit OTP code for 2FA.
 * - Invalidates any existing unused OTP for this admin, so only the newest works
 * - Expires after OTP_TTL_SECONDS (2 minutes)
 *
 * NOTE ON STORAGE: the code is stored as-issued rather than hashed. That is a
 * deliberate call, not an oversight — a 6-digit code has only 10^6 possibilities, so
 * anyone who can read this table can enumerate the original in milliseconds whatever
 * digest is used. What actually protects the code is its 2-minute lifetime, being
 * single-use, and the per-IP/per-account attempt limits in admin/login.php.
 * (An earlier comment here claimed the value was hashed; it never was.)
 */
function generateOTP(int $adminId): string {
    global $pdo;

    // Only one live code per admin at a time.
    $pdo->prepare("DELETE FROM otp_codes WHERE admin_id = ? AND used = 0")->execute([$adminId]);

    // Housekeeping: drop anything long expired.
    $pdo->exec("DELETE FROM otp_codes WHERE expires_at < NOW() - INTERVAL 1 HOUR");

    // Cryptographically secure 6-digit code. random_int(0, 999999) with zero-padding
    // uses the full 10^6 space, including codes that start with 0.
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    /*
     * The expiry MUST be computed by MySQL, not by PHP.
     *
     * config.php sets PHP to America/New_York while MySQL runs in the server's own
     * zone (UTC on most hosts). Writing a PHP-generated timestamp and then testing it
     * against MySQL's NOW() in verifyOTP() compares two different clocks: with the
     * database even a few hours ahead, every code is already "expired" the instant it
     * is issued and 2FA can never succeed. Letting NOW() supply both sides makes the
     * comparison correct regardless of how the two are configured.
     *
     * OTP_TTL_SECONDS is an internal int constant, so inlining it is safe; MySQL does
     * not accept a bound parameter as an INTERVAL quantity.
     */
    $ttl  = (int) OTP_TTL_SECONDS;
    $stmt = $pdo->prepare(
        "INSERT INTO otp_codes (admin_id, code, expires_at)
         VALUES (?, ?, NOW() + INTERVAL {$ttl} SECOND)"
    );
    $stmt->execute([$adminId, $code]);

    return $code;
}

/**
 * Verify an OTP code
 * - Checks code matches, is unused, and not expired
 * - Marks as used immediately to prevent replay
 */
function verifyOTP(int $adminId, string $code): bool {
    global $pdo;
    
    // Validate code format (6 digits only)
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM otp_codes WHERE admin_id = ? AND code = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$adminId, $code]);
    $otp = $stmt->fetch();
    
    if ($otp) {
        // Mark as used immediately (prevent replay attacks)
        $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$otp['id']]);
        return true;
    }
    return false;
}

/**
 * Send OTP to phone number
 * 
 * PRODUCTION: Replace this with an actual SMS API integration:
 * - Twilio: https://www.twilio.com/docs/sms
 * - Vonage: https://developer.vonage.com/messaging/sms
 * 
 * For local XAMPP testing, the OTP is stored in session and displayed on screen.
 */
function sendOTPtoEmail(string $email, string $code): array {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'error' => 'No valid email address is set on this account'];
    }

    $minutes = (int) round(OTP_TTL_SECONDS / 60);
    $window  = $minutes <= 1 ? 'about a minute' : "{$minutes} minutes";

    // The code is deliberately NOT in the subject: subjects surface in lock-screen
    // and desktop notification previews, which would show the code without unlocking.
    $subject = 'Your admin verification code';

    // Plain-text alternative. Some clients show only this, so it must stand alone.
    $text = "Your verification code is: {$code}\n\n"
          . "It expires in {$window} and can only be used once.\n\n"
          . "If you did not try to sign in to the "
          . SITE_TITLE . " admin panel, you can ignore this email — "
          . "someone may have entered your password, so consider changing it.\n\n"
          . "This is an automated message; replies are not monitored.\n";

    // HTML alternative. Deliberately table-free, inline-styled and tiny: the more
    // markup an OTP email carries the more likely a spam filter is to hold it.
    $html = '<!DOCTYPE html><html><body style="margin:0;padding:24px;'
          . 'background:#f4f2ef;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">'
          . '<div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:8px;padding:32px;">'
          . '<h1 style="margin:0 0 8px;font-size:18px;font-weight:600;color:#1e3a6e;">Verification code</h1>'
          . '<p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#4b5563;">'
          . 'Use this code to finish signing in to the ' . e(SITE_TITLE) . ' admin panel.</p>'
          . '<p style="margin:0 0 24px;font-size:34px;font-weight:700;letter-spacing:8px;'
          . 'color:#1e3a6e;text-align:center;font-family:Consolas,Menlo,monospace;">' . e($code) . '</p>'
          . '<p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#6b7280;">'
          . 'It expires in ' . e($window) . ' and can only be used once.</p>'
          . '<p style="margin:0;padding-top:16px;border-top:1px solid #e5e7eb;'
          . 'font-size:12px;line-height:1.6;color:#9ca3af;">'
          . 'If you did not try to sign in, you can ignore this email — though someone may '
          . 'have entered your password, so consider changing it. '
          . 'This is an automated message; replies are not monitored.</p>'
          . '</div></body></html>';

    $result = sendMail($email, $subject, $text, $html);

    if ($result['sent']) {
        // Log the delivery, never the code, and mask the address.
        error_log('[2FA] OTP emailed to ' . maskEmail($email) . ' via ' . $result['transport']);
        return ['sent' => true, 'error' => ''];
    }

    /*
     * Delivery failed. Write the code to the error log so the site owner can still
     * get in via hPanel's file manager rather than being permanently locked out.
     * The log is outside the web root's served paths and denied by .htaccess.
     */
    error_log(sprintf(
        '[2FA] OTP delivery FAILED for %s (%s). Recovery code: %s (valid %ds from now)',
        maskEmail($email), $result['error'], $code, OTP_TTL_SECONDS
    ));

    return ['sent' => false, 'error' => $result['error']];
}

/**
 * Mask an email for logging — keeps the first character and the domain.
 * e.g. rkoslowski@albany.edu -> r*********@albany.edu
 */
function maskEmail(string $email): string {
    $at = strpos($email, '@');
    if ($at === false || $at < 1) return '***';
    return substr($email, 0, 1) . str_repeat('*', max(1, $at - 1)) . substr($email, $at);
}

/**
 * Log out and destroy session completely
 */
function logoutAdmin(): void {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
    
    header('Location: ' . SITE_URL . '/admin/login.php');
    exit;
}

/**
 * Get the current admin's display name
 */
function getAdminUsername(): string {
    return $_SESSION['admin_username'] ?? 'Admin';
}

/**
 * Change admin password with validation
 * - Requires correct current password
 * - New password must be at least 8 characters
 * - Uses bcrypt with cost factor 12
 */
function changePassword(int $adminId, string $currentPassword, string $newPassword): array {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) return ['success' => false, 'error' => 'Admin not found'];
    if (!password_verify($currentPassword, $admin['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect'];
    }
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters'];
    }
    if (strlen($newPassword) > 255) {
        return ['success' => false, 'error' => 'Password is too long'];
    }
    
    // Use bcrypt with appropriate cost (OWASP Password Storage)
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$hash, $adminId]);
    
    return ['success' => true];
}
