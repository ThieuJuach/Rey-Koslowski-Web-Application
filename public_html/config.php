<?php
/**
 * Rey Koslowski Academic Website - Global Configuration
 *
 * SECRETS: no credential is hard-coded in this file. Values are read from real
 * environment variables first, then from secrets.php stored OUTSIDE the web root.
 * See secrets.php for rotation instructions.
 *
 * SECURITY: This file is also protected by .htaccess from direct access.
 */

// ── Error Reporting (OWASP: never expose errors in production) ──
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// ── Create logs directory before anything might need to log ──
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0700, true);
}

/**
 * ── Secret loading ────────────────────────────────────────────────────────
 *
 * Resolution order for every secret:
 *   1. A real environment variable (set in hPanel, or via SetEnv in .htaccess).
 *   2. secrets.php one level above the web root  — the recommended location.
 *   3. secrets.php inside the web root           — fallback for hosts that do
 *      not let you write above public_html. Denied by .htaccess.
 *
 * If a required secret is missing the site stops with a generic message rather
 * than silently falling back to a default password.
 */
$__secrets = [];
foreach ([__DIR__ . '/../secrets.php', __DIR__ . '/secrets.php'] as $__file) {
    if (is_readable($__file)) {
        $__loaded = require $__file;
        if (is_array($__loaded)) {
            $__secrets = $__loaded;
        }
        break;
    }
}

/**
 * Read one secret. Environment wins, then the secrets file, then $default.
 *
 * "Configured but deliberately blank" is distinguished from "not configured at all":
 * a key present in the secrets file with an empty value is honoured as an empty
 * string. That matters for local XAMPP, where the documented setup is a root account
 * with no password — treating blank as missing would make the site refuse to start.
 * A key that is absent everywhere still fails hard when $required is true.
 *
 * @param bool $required Missing (not merely blank) is a fatal misconfiguration.
 */
function secret(string $key, ?string $default = null, bool $required = false): string {
    global $__secrets;

    // getenv() returns false when unset. An env var set to "" counts as configured.
    $value = getenv($key);

    if ($value === false) {
        if (array_key_exists($key, $__secrets)) {
            $value = (string) $__secrets[$key];
        } else {
            $value = null; // Genuinely not configured anywhere.
        }
    }

    if ($value === null) {
        if ($required) {
            error_log("FATAL: required secret '{$key}' is not configured. See secrets.php.");
            http_response_code(500);
            die('The site is not configured correctly. Please contact the administrator.');
        }
        return (string) $default;
    }

    return (string) $value;
}

// ── Database Configuration (never hard-coded) ──
define('DB_HOST',    secret('DB_HOST', '127.0.0.1'));
define('DB_NAME',    secret('DB_NAME', null, true));
define('DB_USER',    secret('DB_USER', null, true));
define('DB_PASS',    secret('DB_PASS', null, true));
define('DB_CHARSET', 'utf8mb4');

// ── Site Configuration ──
define('SITE_URL', rtrim(secret('SITE_URL', 'https://darkblue-badger-609934.hostingersite.com'), '/'));
define('SITE_TITLE', 'Rey Koslowski');
define('SITE_SUBTITLE', 'Professor of Political Science');

// ── File Upload Configuration ──
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg']);
define('ALLOWED_DOC_TYPES', ['application/pdf']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg', 'pdf']);

// ── Rate Limiting ──
// Defaults are deliberately generous for humans and tight for scripts.
define('LOGIN_MAX_ATTEMPTS', 5);      // Max login attempts per IP per window
define('LOGIN_LOCKOUT_MINUTES', 15);  // Lockout duration
define('LOGIN_MAX_PER_ACCOUNT', 10);  // Max attempts against ONE username, any IP
define('CONTACT_MAX_PER_HOUR', 5);    // Contact form submissions per IP per hour
define('API_MAX_PER_MINUTE', 30);     // Authenticated API calls per minute
define('PUBLIC_PAGE_MAX_PER_MINUTE', 120); // Public page views per IP per minute

// ── Outbound Email ──
// SMTP credentials come from the environment / secrets.php, never from source.
// When all three are present the mailer uses authenticated SMTP; otherwise it falls
// back to PHP's mail(). See includes/mailer.php.
define('SMTP_HOST', secret('SMTP_HOST', ''));
define('SMTP_PORT', (int) secret('SMTP_PORT', '465'));
define('SMTP_USER', secret('SMTP_USER', ''));
define('SMTP_PASS', secret('SMTP_PASS', ''));
define('SMTP_CONFIGURED', SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '');

// The "noreply" sender. Defaults to noreply@<site host> so it works with no config,
// but deliverability is much better when this matches a real mailbox on the domain.
define('MAIL_FROM_ADDRESS', secret(
    'MAIL_FROM_ADDRESS',
    'noreply@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost')
));
define('MAIL_FROM_NAME', secret('MAIL_FROM_NAME', SITE_TITLE . ' Admin'));

// ── Two-Factor Authentication (email OTP) ──
// Codes are short-lived by design: a 6-digit code is only 10^6 possibilities, so the
// narrow window plus single-use plus rate limiting is what makes it safe, not length.
define('OTP_TTL_SECONDS', 120);   // 2 minutes
define('OTP_MAX_SENDS_PER_HOUR', 10);  // Anti mail-bombing, per account

// ── Input Validation Limits ──
define('MAX_INPUT_LENGTH', 5000);     // General text input max chars
define('MAX_TITLE_LENGTH', 500);      // Title fields
define('MAX_URL_LENGTH', 2048);       // URL fields
define('MAX_TEXTAREA_LENGTH', 50000); // Textarea / content fields

// ── Session Configuration (OWASP Session Management) ──
ini_set('session.cookie_httponly', 1);     // Prevent JS access to session cookie
ini_set('session.cookie_samesite', 'Lax'); // CSRF protection via SameSite
ini_set('session.use_strict_mode', 1);     // Reject uninitialized session IDs
ini_set('session.use_only_cookies', 1);    // No session ID in URLs
/*
 * Secure cookie flag.
 *
 * Set whenever the request is actually over HTTPS — including behind Hostinger's
 * TLS-terminating load balancer, which forwards plain HTTP internally and sets
 * X-Forwarded-Proto. Production therefore always gets Secure cookies.
 *
 * It is deliberately NOT forced on for plain-HTTP requests: a Secure cookie is never
 * sent back over HTTP, so hard-coding 1 made it impossible to log in to a local
 * XAMPP install over http://localhost — the documented development setup.
 */
$__isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
ini_set('session.cookie_secure', $__isHttps ? 1 : 0);
ini_set('session.gc_maxlifetime', 1800);   // 30 minutes server-side

/*
 * Stop PHP emitting "Cache-Control: no-store, no-cache, must-revalidate" on every
 * page just because a session is open. Those headers disable the back/forward cache,
 * which forced a full reload — and a jump back to the top of the page — every time a
 * visitor pressed Back. The admin area still sends no-store via admin/.htaccess.
 */
session_cache_limiter('');
session_start();

// ── Timezone ──
date_default_timezone_set('America/New_York');

// ── Include database connection and utilities ──
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
