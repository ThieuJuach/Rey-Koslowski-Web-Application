<?php
/**
 * Credentials template — SAFE TO COMMIT. Contains no real values.
 *
 * SETUP
 *   1. Copy this file to `secrets.php` in this same directory
 *      (one level ABOVE public_html/, so the web server can never serve it).
 *   2. Fill in the real values.
 *   3. Save as UTF-8 WITHOUT a BOM, with no blank line after the closing tag.
 *
 * `secrets.php` is listed in .gitignore and must never be committed.
 *
 * Alternatively, set any of these as real environment variables (hPanel →
 * Advanced → Environment Variables). Environment variables take precedence
 * over this file, which is the better option on hosts that support them.
 *
 * A key present here but left blank is treated as "configured and empty",
 * not as missing — that is what allows a local XAMPP root account with no
 * password to work. Keys marked REQUIRED must be present or the site will
 * refuse to start rather than fall back to a default.
 */

return [
    // ── Database (REQUIRED, except DB_HOST) ──────────────────────────────
    'DB_HOST' => '127.0.0.1',          // defaults to 127.0.0.1 if omitted
    'DB_NAME' => 'your_database_name', // REQUIRED
    'DB_USER' => 'your_database_user', // REQUIRED
    'DB_PASS' => 'your_database_password', // REQUIRED (blank is allowed locally)

    // ── Site address ─────────────────────────────────────────────────────
    // Drives canonical URLs, the sitemap, Open Graph tags and the noreply
    // sender address. Set this before doing any SEO work — change it later
    // and every indexed URL moves.
    'SITE_URL' => 'https://reykoslowski.com',

    /*
     * ── Outbound email (admin 2FA codes) ─────────────────────────────────
     *
     * Leave SMTP_* blank to fall back to PHP's mail(). That works, but
     * unauthenticated mail from shared hosting is frequently binned as spam —
     * and a login code in the spam folder means being locked out of your own
     * admin panel.
     *
     * To use authenticated SMTP (recommended):
     *   1. Create a mailbox on the domain, e.g. noreply@reykoslowski.com
     *   2. Put that mailbox's password in SMTP_PASS
     *   3. Admin → Security → "Send me a test code" to confirm it arrives
     *
     * Port 465 = implicit TLS (recommended). Port 587 = STARTTLS.
     */
    'SMTP_HOST' => '',   // e.g. 'smtp.hostinger.com'
    'SMTP_PORT' => '465',
    'SMTP_USER' => '',   // full mailbox address, e.g. 'noreply@reykoslowski.com'
    'SMTP_PASS' => '',   // that mailbox's password

    // Visible sender. Should match SMTP_USER when SMTP is in use so that
    // SPF/DKIM line up. Defaults to noreply@<SITE_URL host> when blank.
    'MAIL_FROM_ADDRESS' => '',
    'MAIL_FROM_NAME'    => 'Site Admin',
];
