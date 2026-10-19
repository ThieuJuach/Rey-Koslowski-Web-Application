# Rey Koslowski Academic Website

A PHP-based academic portfolio website with an admin dashboard for content management.

## Requirements
- PHP 8.0+ (developed against 8.2), MySQL / MariaDB, Apache with `mod_rewrite`,
  `mod_headers`, `mod_deflate` and `mod_expires`
- The `curl` extension (only needed if SMS-based 2FA is enabled)

---

## Configuration & secrets

**No credentials live in the source tree.** `config.php` resolves every secret in
this order:

1. A real environment variable (`DB_PASS`, etc.) — set in Hostinger hPanel
2. `secrets.php` **one level above `public_html/`** — the recommended location
3. `secrets.php` inside `public_html/` — fallback only; denied by `.htaccess`

If a required secret is missing, the site stops with a generic message rather than
falling back to a default.

```
domains/<your-domain>/
├── secrets.php        <-- credentials live HERE, outside the web root
└── public_html/       <-- everything Apache serves
```

Save `secrets.php` as **UTF-8 without a BOM**, with no blank line after `?>`.
Any stray byte before a page's output breaks HTTP headers and the XML sitemap.

### Rotating the database password
The previous password was hard-coded in `config.php` and must be treated as
compromised:

1. hPanel → Databases → MySQL Databases → change the password for the site's database user
2. Put the new value in `secrets.php` (or the `DB_PASS` env var)

---

## Local development (XAMPP)

1. Start Apache and MySQL.
2. Import `database/schema.sql` — note it creates and selects a database named
   `rey_koslowski_site`, then apply `schema_update_v2.sql` and
   `schema_update_indexes.sql` to that same database.
3. Copy `secrets.example.php` to `secrets.php` above the web root and fill it in
   (`secrets.php` is gitignored and must never be committed):
   ```php
   <?php
   return [
       'DB_HOST'  => '127.0.0.1',
       'DB_NAME'  => 'rey_koslowski_site',
       'DB_USER'  => 'root',
       'DB_PASS'  => '',                        // blank is honoured, not treated as missing
       'SITE_URL' => 'http://localhost/rey-koslowski-website',
   ];
   ```
4. Set a password for the `admin` account. The schema ships a placeholder that is
   not a valid hash, so no default login exists — generate your own:
   ```bash
   php -r "echo password_hash('your-password-here', PASSWORD_BCRYPT, ['cost'=>12]);"
   ```
   ```sql
   UPDATE admins SET password_hash = '<paste the hash>' WHERE username = 'admin';
   ```
5. Public site: `http://localhost/rey-koslowski-website/`
   Admin: `.../admin/login.php`

Plain HTTP works locally: the HTTPS redirect in `.htaccess` exempts `localhost` and
`127.0.0.1`, and the session cookie's `Secure` flag is set only on real HTTPS requests.

---

## Project structure

```
public_html/
├── index.php                 # Home page
├── research.php              # Research
├── teachings.php             # Teaching
├── publications.php          # Publications
├── presentations.php         # Presentations
├── media-appearances.php     # Media
├── migration-studies.php     # Migration Studies
├── professional-service.php  # Professional Service
├── community-engagement.php  # Community Engagement
├── contact.php               # Contact details
├── 404.php                   # Custom 404 (real 404 status, noindex)
├── sitemap.php               # Dynamic sitemap; /sitemap.xml rewrites here
├── robots.txt
├── config.php                # Bootstrap: secrets, limits, session, includes
├── .htaccess                 # Redirects, security headers, caching
│
├── admin/                    # Admin dashboard (noindex, no-store)
│   ├── login.php  logout.php  dashboard.php
│   ├── edit-*.php            # One editor per content area
│   ├── media-manager.php     # Central upload/delete for images, PDFs, videos
│   ├── change-password.php
│   └── includes/sidebar.php
│
├── includes/
│   ├── header.php  nav.php  footer.php
│   ├── db.php                # PDO, real prepared statements
│   ├── auth.php              # Login, 2FA/OTP, password change
│   └── functions.php         # Escaping, CSRF, rate limiting, validation, queries
│
├── assets/
│   ├── css/style.css  css/admin.css
│   └── js/main.js     js/admin.js
│
├── api/                      # JSON endpoints (auth + CSRF + rate limit on all)
│   ├── save-content.php  upload-image.php  upload-video.php
│   ├── delete-media.php  reorder-sections.php
│
├── uploads/                  # PHP execution disabled here via .htaccess
│   ├── images/  images/hero/ # hero/ holds the responsive WebP+JPEG derivatives
│   ├── pdfs/  videos/
│
└── database/*.sql
```

---

## Security model

| Area | Approach |
|---|---|
| Secrets | Env vars or `secrets.php` outside the web root; nothing hard-coded |
| SQL | PDO with `ATTR_EMULATE_PREPARES=false`; table/column names from fixed allowlists |
| XSS | `e()` escapes all output; `formatText()` applies a safe-tag allowlist |
| CSRF | Token required on every state-changing request, including all JSON APIs |
| Input | `validateSchema()` — typed, length-capped, unexpected fields rejected |
| Rate limiting | Sliding window under `flock()`, on IP **and** account; graceful 429 + `Retry-After` |
| Auth | bcrypt cost 12, session regeneration, 30-min idle timeout, constant-time user lookup |
| Uploads | Extension + MIME + content sniff, random filenames, PHP execution disabled |
| Headers | CSP, HSTS, nosniff, frame options, referrer and permissions policy |

### Rate limits (defaults in `config.php`)

| Endpoint | Limit (per IP; signed-in admins get 2x) |
|---|---|
| Any public page | 120 / minute |
| Login | 5 / 15 min per IP, 10 / 15 min per username |
| OTP verification | 10 / 5 min per IP and per account |
| Authenticated APIs | 30 / minute |
| Image upload | 20 / minute |
| Video upload | 10 / minute |

### Two-factor authentication
With no SMS provider configured, the OTP is shown on the login screen so the owner
cannot be locked out. Setting `TWILIO_SID`, `TWILIO_AUTH_TOKEN` and
`TWILIO_PHONE_NUMBER` in `secrets.php` switches to real SMS and automatically stops
displaying the code.

### Known remaining gap
The CSP still allows `script-src 'unsafe-inline'`, because several pages use inline
`onclick` / `onsubmit` handlers. Removing it means moving those handlers into
`assets/js/*.js` and adding a per-request nonce.

---

## Performance notes
- The hero photo is served from `uploads/images/hero/` as WebP with a JPEG fallback
  at four widths, preloaded in `<head>`. Regenerate derivatives if the photo changes;
  the original 4160x3120 file is retained.
- Google Fonts requests only the weights the CSS actually uses (400/500/600/700).
- Static assets are cached for a year and marked `immutable`.
