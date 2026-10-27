<?php
/**
 * Utility Functions
 * 
 * SECURITY: All output functions escape by default.
 * Rich text uses an allowlist of safe HTML tags.
 */

// ── Output Escaping ──

/**
 * Escape string for safe HTML output (OWASP XSS Prevention)
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── CSRF Token Management (OWASP CSRF Prevention) ──

/**
 * Generate or retrieve the current CSRF token
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field for forms
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Validate CSRF token from POST data
 */
function validateCsrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token or die with 403
 */
function requireCsrf(): void {
    if (!validateCsrf()) {
        http_response_code(403);
        die('Invalid security token. Please refresh and try again.');
    }
}

// ── Rate Limiting (OWASP Brute Force / Resource Exhaustion Prevention) ──
//
// Sliding-window counter backed by one small JSON file per bucket. Every read and
// write happens under an exclusive flock() for the whole read-modify-write cycle,
// so two concurrent requests cannot both read "4 attempts" and both be allowed
// through on a limit of 5 (the previous implementation had exactly that race).

/**
 * Directory holding rate-limit state. Created 0700 on first use.
 */
function rateLimitDir(): string {
    $dir = __DIR__ . '/../logs/ratelimit/';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    return $dir;
}

/**
 * Record an attempt against a bucket and report whether the caller is over limit.
 *
 * @param string $key            Bucket identity, e.g. "login:ip:203.0.113.9"
 * @param int    $maxAttempts    Attempts permitted inside the window
 * @param int    $windowSeconds  Length of the sliding window
 * @param bool   $countThis      false = peek only, do not record an attempt.
 *                               Use false for checks that run on every page view.
 * @return array{allowed:bool, remaining:int, retry_after:int}
 */
function rateLimitCheck(string $key, int $maxAttempts, int $windowSeconds, bool $countThis = true): array {
    $file = rateLimitDir() . hash('sha256', $key) . '.json';
    $now  = time();

    // 'c+' creates the file if absent and does NOT truncate, so we can lock, read,
    // decide and write without ever releasing the lock in between.
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        // If the limiter itself cannot run, fail open rather than locking out the
        // whole site — but leave a trace so the problem is visible.
        error_log("Rate limiter: cannot open {$file}");
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
    }

    flock($fh, LOCK_EX);

    $raw      = stream_get_contents($fh);
    $decoded  = json_decode($raw ?: '[]', true);
    $attempts = is_array($decoded) ? $decoded : [];

    // Drop timestamps that have slid out of the window.
    $attempts = array_values(array_filter(
        $attempts,
        fn($t) => is_int($t) && ($now - $t) < $windowSeconds
    ));

    $overLimit = count($attempts) >= $maxAttempts;

    if (!$overLimit && $countThis) {
        $attempts[] = $now;
    }

    // Only rewrite when something actually changed.
    if (!$overLimit && $countThis) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($attempts));
        fflush($fh);
    }

    flock($fh, LOCK_UN);
    fclose($fh);

    // How long until the oldest attempt leaves the window.
    $retryAfter = 0;
    if ($overLimit && $attempts) {
        $retryAfter = max(1, ($windowSeconds - ($now - min($attempts))));
    }

    return [
        'allowed'     => !$overLimit,
        'remaining'   => max(0, $maxAttempts - count($attempts)),
        'retry_after' => $retryAfter,
    ];
}

/**
 * Backwards-compatible wrapper kept so existing call sites keep working unchanged.
 * @return bool True if the caller is OVER the limit.
 */
function isRateLimited(string $key, int $maxAttempts, int $windowSeconds): bool {
    return !rateLimitCheck($key, $maxAttempts, $windowSeconds)['allowed'];
}

/**
 * Enforce a limit against BOTH the client IP and the logged-in account (when there
 * is one), then respond with a graceful 429 if either bucket is exhausted.
 *
 * Checking two dimensions matters: an IP-only limit lets one attacker rotate IPs
 * against a single account, and an account-only limit lets one IP hammer many
 * accounts. OWASP recommends both.
 *
 * @param string $bucket  Logical endpoint name, e.g. 'api_save', 'contact'
 * @param int    $max     Attempts per window (per dimension)
 * @param int    $window  Window length in seconds
 * @param string $format  'json' for API endpoints, 'html' for pages
 * @param string|null $accountKey Extra identity to limit on (username, admin id).
 *                                Defaults to the logged-in admin, if any.
 */
function enforceRateLimit(
    string $bucket,
    int $max,
    int $window,
    string $format = 'json',
    ?string $accountKey = null
): void {
    $identities = ['ip:' . getClientIp()];

    $account = $accountKey ?? ($_SESSION['admin_id'] ?? null);
    if ($account !== null && $account !== '') {
        // A signed-in admin gets a more generous allowance than an anonymous IP,
        // because legitimate editing is bursty.
        $identities[] = 'user:' . $account;
    }

    $worst = 0;
    foreach ($identities as $i => $identity) {
        // First identity is the IP (strict); an authenticated account gets 2x.
        $limit  = ($i === 0) ? $max : $max * 2;
        $result = rateLimitCheck("{$bucket}:{$identity}", $limit, $window);
        if (!$result['allowed']) {
            $worst = max($worst, $result['retry_after']);
        }
    }

    if ($worst > 0) {
        sendRateLimitResponse($worst, $format);
    }
}

/**
 * Emit a well-formed 429 and stop. Includes Retry-After so well-behaved clients
 * (and Googlebot) know exactly when to come back rather than hammering.
 */
function sendRateLimitResponse(int $retryAfter, string $format = 'json'): void {
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    // Never let an error page be cached and served to someone else.
    header('Cache-Control: no-store');

    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success'     => false,
            'error'       => 'Too many requests. Please slow down and try again shortly.',
            'retry_after' => $retryAfter,
        ]);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        $wait = $retryAfter >= 60
            ? ceil($retryAfter / 60) . ' minute' . (ceil($retryAfter / 60) === 1.0 ? '' : 's')
            : $retryAfter . ' seconds';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<meta name="robots" content="noindex">'
           . '<title>Too many requests</title>'
           . '<style>body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;'
           . 'display:flex;align-items:center;justify-content:center;min-height:100vh;'
           . 'margin:0;background:hsl(38,20%,96%);color:hsl(220,25%,20%)}'
           . 'div{max-width:32rem;padding:2rem;text-align:center}'
           . 'h1{font-size:1.5rem;margin:0 0 .75rem}p{line-height:1.6;margin:0 0 .5rem}'
           . 'a{color:hsl(220,55%,35%)}</style></head><body><div>'
           . '<h1>Too many requests</h1>'
           . '<p>You have made a lot of requests in a short space of time. '
           . 'Please wait about ' . e($wait) . ' and try again.</p>'
           . '<p><a href="' . e(SITE_URL) . '/">Return to the home page</a></p>'
           . '</div></body></html>';
    }
    exit;
}

/**
 * Clear a rate-limit bucket — call after a SUCCESSFUL login so one bad password
 * does not count against the user for the next 15 minutes.
 */
function clearRateLimit(string $key): void {
    $file = rateLimitDir() . hash('sha256', $key) . '.json';
    if (file_exists($file)) unlink($file);
}

/**
 * Housekeeping: delete rate-limit files untouched for over a day. Called
 * opportunistically (roughly 1 request in 200) so the directory cannot grow
 * without bound on a busy site.
 */
function pruneRateLimitFiles(): void {
    if (random_int(1, 200) !== 1) return;

    $cutoff = time() - 86400;
    foreach (glob(rateLimitDir() . '*.json') ?: [] as $file) {
        if (@filemtime($file) < $cutoff) @unlink($file);
    }
}

// ── Input Validation & Sanitization (OWASP Input Validation) ──

/**
 * Sanitize and truncate a text input
 */
function sanitizeInput(string $input, int $maxLength = 0): string {
    $input = trim($input);
    $input = str_replace("\0", '', $input);
    if ($maxLength > 0 && mb_strlen($input) > $maxLength) {
        $input = mb_substr($input, 0, $maxLength);
    }
    return $input;
}

/**
 * Validate and sanitize a URL
 */
function sanitizeUrl(string $url): string|false {
    $url = trim($url);
    if ($url === '') return '';
    if (mb_strlen($url) > MAX_URL_LENGTH) return false;
    if ($url && !preg_match('/^(https?:\/\/|\/)/i', $url)) {
        return false;
    }
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * Validate an email address
 */
function sanitizeEmail(string $email): string|false {
    $email = trim($email);
    if (mb_strlen($email) > 254) return false;
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate an integer input within range
 */
function sanitizeInt($value, int $min = 0, int $max = PHP_INT_MAX): ?int {
    $val = filter_var($value, FILTER_VALIDATE_INT);
    if ($val === false) return null;
    if ($val < $min || $val > $max) return null;
    return $val;
}

/**
 * Validate enum value against allowed list
 */
function sanitizeEnum(string $value, array $allowed, string $default = ''): string {
    return in_array($value, $allowed, true) ? $value : $default;
}

// ── Schema-Based Request Validation (OWASP Input Validation) ──
//
// Declare exactly what an endpoint accepts, then hand the raw request array to
// validateSchema(). Anything not declared is rejected outright (positive security
// model / allowlist), every field is type-checked, and every string has a hard
// length ceiling. Endpoints then work only with the cleaned $result['data'].
//
// Supported rule keys per field:
//   type      string|int|email|url|enum|bool|date  (default: string)
//   required  bool   — missing or empty fails validation
//   max       int    — max characters (string) or max value (int)
//   min       int    — min characters (string) or min value (int)
//   values    array  — permitted values, for type 'enum'
//   default   mixed  — used when the field is absent and not required
//   html      bool   — for strings: allow the safe-tag allowlist through
//                      formatText() instead of stripping every tag
//
// Example:
//   $v = validateSchema($_POST, [
//       'title' => ['type' => 'string', 'required' => true, 'max' => MAX_TITLE_LENGTH],
//       'year'  => ['type' => 'int', 'min' => 1900, 'max' => 2100],
//       'kind'  => ['type' => 'enum', 'values' => ['book', 'article']],
//   ]);
//   if (!$v['ok']) { /* $v['errors'] */ }

/**
 * Validate and clean a request payload against a field schema.
 *
 * @param array $input         Raw input ($_POST, $_GET, decoded JSON body)
 * @param array $schema        Field name => rule array (see above)
 * @param bool  $rejectUnknown Fail if $input contains fields not in $schema
 * @return array{ok:bool, data:array, errors:array<string,string>}
 */
function validateSchema(array $input, array $schema, bool $rejectUnknown = true): array {
    $data   = [];
    $errors = [];

    // 1. Reject anything we did not ask for. csrf_token and the form's action
    //    discriminator are structural, not data, so they are always tolerated.
    if ($rejectUnknown) {
        $structural = ['csrf_token', 'action'];
        foreach (array_keys($input) as $key) {
            if (!isset($schema[$key]) && !in_array($key, $structural, true)) {
                $errors[$key] = 'Unexpected field';
            }
        }
    }

    // 2. Validate each declared field.
    foreach ($schema as $field => $rules) {
        $type     = $rules['type']     ?? 'string';
        $required = $rules['required'] ?? false;
        $present  = array_key_exists($field, $input);
        $raw      = $present ? $input[$field] : null;

        // Arrays and objects are never valid scalar input — reject rather than
        // letting PHP coerce them (which would emit notices and odd values).
        if ($present && (is_array($raw) || is_object($raw))) {
            $errors[$field] = 'Invalid value';
            continue;
        }

        $isEmpty = !$present || $raw === null || (is_string($raw) && trim($raw) === '');

        if ($isEmpty) {
            if ($required) {
                $errors[$field] = 'This field is required';
            } else {
                $data[$field] = $rules['default'] ?? ($type === 'int' ? null : '');
            }
            continue;
        }

        switch ($type) {
            case 'int':
                $val = sanitizeInt($raw, $rules['min'] ?? PHP_INT_MIN, $rules['max'] ?? PHP_INT_MAX);
                if ($val === null) {
                    $errors[$field] = 'Must be a whole number within the allowed range';
                } else {
                    $data[$field] = $val;
                }
                break;

            case 'bool':
                // Accepts 1/0, true/false, "on" (HTML checkboxes), yes/no.
                $data[$field] = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                break;

            case 'email':
                $val = sanitizeEmail((string) $raw);
                if ($val === false) {
                    $errors[$field] = 'Must be a valid email address';
                } else {
                    $data[$field] = $val;
                }
                break;

            case 'url':
                $val = sanitizeUrl((string) $raw);
                if ($val === false) {
                    $errors[$field] = 'Must be a valid http(s) or root-relative URL';
                } else {
                    $data[$field] = $val;
                }
                break;

            case 'enum':
                $allowed = $rules['values'] ?? [];
                if (!in_array((string) $raw, $allowed, true)) {
                    $errors[$field] = 'Not an allowed value';
                } else {
                    $data[$field] = (string) $raw;
                }
                break;

            case 'date':
                // Strict Y-m-d; rejects things like 2026-02-31 that strtotime accepts.
                $d = DateTime::createFromFormat('Y-m-d', (string) $raw);
                if (!$d || $d->format('Y-m-d') !== (string) $raw) {
                    $errors[$field] = 'Must be a date in YYYY-MM-DD format';
                } else {
                    $data[$field] = $d->format('Y-m-d');
                }
                break;

            case 'string':
            default:
                $str = (string) $raw;
                $max = $rules['max'] ?? MAX_INPUT_LENGTH;
                $min = $rules['min'] ?? 0;

                // Length is checked BEFORE truncating, so oversized input is a
                // validation error rather than silently losing the tail.
                if (mb_strlen(trim($str)) > $max) {
                    $errors[$field] = "Must be {$max} characters or fewer";
                    break;
                }
                if (mb_strlen(trim($str)) < $min) {
                    $errors[$field] = "Must be at least {$min} characters";
                    break;
                }

                $clean = sanitizeInput($str, $max);

                // Strip control characters that have no business in text input
                // (keeps tab, newline, carriage return).
                $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);

                $data[$field] = $clean;
                break;
        }
    }

    return ['ok' => empty($errors), 'data' => $data, 'errors' => $errors];
}

/**
 * Validate, or emit a 400 with the field errors and stop. For JSON API endpoints.
 */
function validateSchemaOrFail(array $input, array $schema, bool $rejectUnknown = true): array {
    $result = validateSchema($input, $schema, $rejectUnknown);
    if (!$result['ok']) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid input',
            'fields'  => $result['errors'],
        ]);
        exit;
    }
    return $result['data'];
}

// ── Text Formatting (Paragraphs, Line Breaks, Safe HTML) ──

/**
 * Allowed HTML tags in rich text (OWASP XSS allowlist)
 */
define('ALLOWED_HTML_TAGS', '<a><p><br><strong><b><em><i><ul><ol><li><h3><h4><h5><h6><blockquote><span><sub><sup><hr>');

/**
 * Format admin-entered text preserving paragraphs, line breaks, caps, and safe hyperlinks.
 * 
 * IMPORTANT: This function preserves the EXACT formatting entered by the admin:
 * - Capitalization is preserved exactly as typed
 * - Paragraph spacing (double newlines) becomes <p> tags
 * - Single newlines become <br> tags
 * - Safe HTML tags (links, bold, italic, lists) are preserved
 * - Dangerous tags/attributes are stripped (OWASP XSS prevention)
 */
function formatText(string $text): string {
    if (empty(trim($text))) return '';
    
    // Step 1: Strip all tags except allowed ones (OWASP XSS allowlist)
    $text = strip_tags($text, ALLOWED_HTML_TAGS);
    
    // Step 2: Remove dangerous attributes from allowed tags
    // Remove all on* event handlers (onclick, onerror, onload, etc.)
    $text = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $text);
    $text = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $text);
    
    // Remove javascript: and data: URIs from href/src attributes
    $text = preg_replace('/href\s*=\s*["\']?\s*javascript\s*:/i', 'href="', $text);
    $text = preg_replace('/href\s*=\s*["\']?\s*data\s*:/i', 'href="', $text);
    $text = preg_replace('/src\s*=\s*["\']?\s*javascript\s*:/i', 'src="', $text);
    $text = preg_replace('/src\s*=\s*["\']?\s*data\s*:/i', 'src="', $text);
    
    // Remove style attributes that could contain expressions
    $text = preg_replace('/\s+style\s*=\s*["\'][^"\']*expression[^"\']*["\']/i', '', $text);
    
    // Step 3: Handle paragraph formatting
    // If text already contains <p> tags, preserve them and convert remaining newlines
    if (stripos($text, '<p>') !== false || stripos($text, '<p ') !== false) {
        return $text;
    }
    
    // Convert double newlines to paragraphs, preserving spacing exactly as admin typed
    $paragraphs = preg_split('/\n\s*\n/', $text);
    if (count($paragraphs) > 1) {
        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') {
                // nl2br preserves single line breaks within paragraphs
                $html .= '<p>' . nl2br($p) . '</p>';
            }
        }
        return $html;
    }
    
    // Single block: convert newlines to <br> preserving line breaks
    return nl2br($text);
}

// ── Database Helper Functions ──

function getSetting(string $key, string $default = ''): string {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }
    return array_key_exists($key, $cache) ? ($cache[$key] ?? $default) : $default;
}

function updateSetting(string $key, string $value): void {
    global $pdo;
    $value = sanitizeInput($value, MAX_TEXTAREA_LENGTH);
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

function getPageContent(string $slug): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND is_visible = 1");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function getPublications(string $type = ''): array {
    global $pdo;
    if ($type) {
        $stmt = $pdo->prepare("SELECT * FROM publications WHERE type = ? AND is_visible = 1 ORDER BY year DESC, display_order ASC");
        $stmt->execute([$type]);
    } else {
        $stmt = $pdo->query("SELECT * FROM publications WHERE is_visible = 1 ORDER BY year DESC, display_order ASC");
    }
    return $stmt->fetchAll();
}

function getCurrentPublications(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM publications WHERE is_current = 1 AND is_visible = 1 ORDER BY year DESC, display_order ASC LIMIT 5")->fetchAll();
}

function getResearchProjects(string $status = ''): array {
    global $pdo;
    if ($status) {
        $stmt = $pdo->prepare("SELECT * FROM research_projects WHERE status = ? AND is_visible = 1 ORDER BY display_order ASC");
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->query("SELECT * FROM research_projects WHERE is_visible = 1 ORDER BY display_order ASC");
    }
    return $stmt->fetchAll();
}

function getCurrentResearch(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM research_projects WHERE (status = 'ongoing' OR is_current = 1) AND is_visible = 1 ORDER BY display_order ASC LIMIT 5")->fetchAll();
}

function getCourses(string $level = ''): array {
    global $pdo;
    if ($level) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE level = ? AND is_visible = 1 ORDER BY display_order ASC");
        $stmt->execute([$level]);
    } else {
        $stmt = $pdo->query("SELECT * FROM courses WHERE is_visible = 1 ORDER BY display_order ASC");
    }
    return $stmt->fetchAll();
}

function getCurrentCourses(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM courses WHERE is_current = 1 AND is_visible = 1 ORDER BY display_order ASC LIMIT 5")->fetchAll();
}

function getMediaAppearances(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM media_appearances WHERE is_visible = 1 ORDER BY year DESC, display_order ASC")->fetchAll();
}

function getMigrationFaculty(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM migration_faculty WHERE is_visible = 1 ORDER BY display_order ASC")->fetchAll();
}

function getMigrationCourses(string $level = ''): array {
    global $pdo;
    if ($level) {
        $stmt = $pdo->prepare("SELECT * FROM migration_courses WHERE level = ? AND is_visible = 1 ORDER BY display_order ASC");
        $stmt->execute([$level]);
    } else {
        $stmt = $pdo->query("SELECT * FROM migration_courses WHERE is_visible = 1 ORDER BY display_order ASC");
    }
    return $stmt->fetchAll();
}

// ── Presentations ──

/**
 * Get all presentations, optionally filtered by status
 */
function getPresentations(string $status = ''): array {
    global $pdo;
    if ($status) {
        $stmt = $pdo->prepare("SELECT * FROM presentations WHERE status = ? AND is_visible = 1 ORDER BY presentation_date DESC, display_order ASC");
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->query("SELECT * FROM presentations WHERE is_visible = 1 ORDER BY presentation_date DESC, display_order ASC");
    }
    return $stmt->fetchAll();
}

/**
 * Get presentations marked as current (for homepage)
 */
function getCurrentPresentations(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM presentations WHERE is_current = 1 AND is_visible = 1 ORDER BY presentation_date ASC LIMIT 5")->fetchAll();
}

// ── Professional Service Items (blog-post style) ──

function getProfessionalServiceItems(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM professional_service_items WHERE is_visible = 1 ORDER BY display_order ASC")->fetchAll();
}

// ── Community Engagement Items (blog-post style) ──

function getCommunityEngagementItems(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM community_engagement_items WHERE is_visible = 1 ORDER BY display_order ASC")->fetchAll();
}

function getPageImages(string $pageSlug, string $type = ''): array {
    global $pdo;
    if ($type) {
        $stmt = $pdo->prepare("SELECT * FROM page_images WHERE page_slug = ? AND image_type = ? AND is_visible = 1 ORDER BY display_order ASC");
        $stmt->execute([$pageSlug, $type]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM page_images WHERE page_slug = ? AND is_visible = 1 ORDER BY display_order ASC");
        $stmt->execute([$pageSlug]);
    }
    return $stmt->fetchAll();
}

function getPageFeaturedImage(string $pageSlug): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM page_images WHERE page_slug = ? AND image_type = 'featured' AND is_visible = 1 ORDER BY display_order ASC LIMIT 1");
    $stmt->execute([$pageSlug]);
    return $stmt->fetch() ?: null;
}

function getProfileImage(): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM page_images WHERE page_slug = 'home' AND image_type = 'profile' AND is_visible = 1 ORDER BY display_order ASC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

/* NOTE: getBlogPosts(), getGalleries(), getGalleryImages() and getServiceItems()
   were removed here — no page or admin screen called them. Their database tables
   (blog_posts, galleries, gallery_images, service_items) are untouched, so the
   helpers can be restored from version history if those features are ever built. */

//
// Every public page can carry an optional intro paragraph, stored as a row in
// `pages` and edited from that page's admin screen. The pair below exists so the
// otherwise each repeat the same markup, validation and upsert.

/**
 * Delete a file from disk, but only if it genuinely resolves inside uploads/.
 *
 * Lives here rather than in one admin screen because several callers need it:
 * the media manager, and the featured-block editor on every page.
 * realpath() never returns a trailing separator, so comparing against
 * $uploadsDir + separator is enough to require a true child path and stops a
 * sibling directory such as "uploads-old" from matching.
 */
function deleteUploadedFile(string $relativePath): void {
    if (strpos($relativePath, '..') !== false || !str_starts_with($relativePath, 'uploads/')) {
        return;
    }
    $real       = realpath(UPLOAD_DIR . '/../' . $relativePath);
    $uploadsDir = realpath(UPLOAD_DIR);
    if ($real && $uploadsDir && str_starts_with($real, $uploadsDir . DIRECTORY_SEPARATOR)) {
        @unlink($real);
    }
}

// ── Featured Blocks ─────────────────────────────────────────────────────────
//
// An optional highlighted panel sitting between a page's header and its first
// section: a card holding rich text, links and an image.
//
// STORAGE. Text lives in `pages` under the slug "featured-<page>" and the image
// in `page_images` under the same slug. The image_type column is an ENUM
// without a 'block' member - MySQL silently stores '' for an unknown value
// when not in strict mode - so these rows reuse 'featured' and are
// identified by the slug prefix instead. The prefix is
// deliberate. An earlier attempt stored this under the bare page slug, which
// collided with placeholder rows already present in production - "Selected
// publications.", "Contact information." and similar had been sitting unused in
// that table, and giving them somewhere to render surfaced them all at once.
// A separate namespace makes that impossible.

/**
 * Tags permitted inside a featured block.
 *
 * Wider than ALLOWED_HTML_TAGS because this panel is meant to carry images, but
 * still an allowlist: no <script>, <iframe>, <object>, <form> or <style>.
 */
define('FEATURED_BLOCK_TAGS',
    '<a><p><br><strong><b><em><i><ul><ol><li><h3><h4><h5><h6>'
  . '<blockquote><span><sub><sup><hr><img><figure><figcaption>');

/**
 * Sanitise featured-block HTML.
 *
 * Runs the same hardening as formatText() - tag allowlist, on* handlers
 * stripped, javascript:/data: URIs neutralised - and then additionally
 * constrains every <img> to an image this site actually hosts. A remote src
 * would leak visitors' IPs to a third party and lets the panel break if that
 * host disappears, so anything not under uploads/ is dropped.
 */
function formatFeaturedBlock(string $text): string {
    if (trim($text) === '') return '';

    $text = strip_tags($text, FEATURED_BLOCK_TAGS);

    // Event handlers and scriptable URIs (OWASP XSS prevention).
    $text = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $text);
    $text = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $text);
    $text = preg_replace('/(href|src)\s*=\s*["\']?\s*javascript\s*:/i', '$1="', $text);
    $text = preg_replace('/(href|src)\s*=\s*["\']?\s*data\s*:/i', '$1="', $text);
    $text = preg_replace('/\s+style\s*=\s*["\'][^"\']*expression[^"\']*["\']/i', '', $text);

    // Drop any <img> whose src is not a file hosted here.
    $text = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
        if (!preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $m[0], $src)) return '';
        $url = trim($src[1]);
        $host = parse_url(SITE_URL, PHP_URL_HOST);
        $ok = str_starts_with($url, 'uploads/')
           || str_starts_with($url, '/uploads/')
           || ($host && str_starts_with($url, SITE_URL . '/uploads/'));
        if (!$ok) return '';
        // Rebuild from scratch: keeps src and alt, discards every other attribute.
        $alt = preg_match('/\salt\s*=\s*["\']([^"\']*)["\']/i', $m[0], $a) ? $a[1] : '';
        return '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy">';
    }, $text);

    // Paragraph handling, matching formatText().
    if (stripos($text, '<p>') !== false || stripos($text, '<p ') !== false) return $text;
    $paras = preg_split('/\n\s*\n/', $text);
    if (count($paras) > 1) {
        $html = '';
        foreach ($paras as $p) {
            $p = trim($p);
            if ($p !== '') $html .= '<p>' . nl2br($p) . '</p>';
        }
        return $html;
    }
    return nl2br($text);
}

/**
 * Render a page's featured block, or '' when there is nothing to show.
 *
 * Returning '' for an empty or missing row is what keeps the panel invisible
 * until someone actually writes something.
 */
function renderFeaturedBlock(string $page): string {
    $slug = 'featured-' . $page;

    $row  = getPageContent($slug);
    $body = $row ? formatFeaturedBlock((string) $row['content']) : '';

    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT image_path, alt_text, caption FROM page_images
         WHERE page_slug = ? AND image_type = 'featured' AND is_visible = 1
         ORDER BY display_order ASC LIMIT 1"
    );
    $stmt->execute([$slug]);
    $img = $stmt->fetch() ?: null;

    if ($body === '' && !$img) return '';   // nothing set: show nothing at all

    $html = '<div class="container"><div class="content-max">'
          . '<div class="featured-block">';

    if ($img) {
        $html .= '<figure class="featured-block__figure">'
               . '<img src="' . e(SITE_URL . '/' . $img['image_path']) . '" '
               . 'alt="' . e($img['alt_text'] ?? '') . '" loading="lazy" '
               . 'class="featured-block__img">';
        if (!empty($img['caption'])) {
            $html .= '<figcaption class="featured-block__caption">' . e($img['caption']) . '</figcaption>';
        }
        $html .= '</figure>';
    }

    if ($body !== '') {
        $html .= '<div class="featured-block__text prose">' . $body . '</div>';
    }

    return $html . '</div></div></div>';
}

/**
 * Handle a submitted featured block. Call before any output.
 *
 * Returns a message for the caller to display, or '' when this request was not
 * a featured-block submission. Guards its own CSRF and rate limit, because the
 * admin screens differ in how they gate POSTs.
 */
function handleFeaturedBlockSave(string $page, string $label): string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save_featured'])) {
        return '';
    }
    requireCsrf();
    enforceRateLimit('admin_featured', 30, 60, 'html');

    $slug = 'featured-' . $page;
    global $pdo;

    // Only the text field is validated here; the file arrives via $_FILES and is
    // handled by handleFileUpload(), which verifies it from its own bytes.
    $v = validateSchema(
        ['block_content' => (string) ($_POST['block_content'] ?? '')],
        ['block_content' => ['type' => 'string', 'max' => MAX_TEXTAREA_LENGTH, 'html' => true]]
    );
    if (!$v['ok']) {
        return 'Could not save: ' . implode('; ', $v['errors']);
    }

    $pdo->prepare(
        "INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()"
    )->execute([$slug, $label . ' feature', $v['data']['block_content']]);

    $notes = ['Featured block saved.'];

    // Remove the image if asked, before considering a replacement.
    if (!empty($_POST['remove_block_image'])) {
        $q = $pdo->prepare("SELECT image_path FROM page_images WHERE page_slug = ? AND image_type = 'featured'");
        $q->execute([$slug]);
        foreach ($q->fetchAll() as $old) {
            deleteUploadedFile($old['image_path']);
        }
        $pdo->prepare("DELETE FROM page_images WHERE page_slug = ? AND image_type = 'featured'")->execute([$slug]);
        $notes[] = 'Image removed.';
    }

    if (!empty($_FILES['block_image']['name'])) {
        $path = handleFileUpload($_FILES['block_image'], 'images');
        if ($path) {
            // One image per block: clear any previous before inserting.
            $pdo->prepare("DELETE FROM page_images WHERE page_slug = ? AND image_type = 'featured'")->execute([$slug]);
            $pdo->prepare(
                "INSERT INTO page_images (page_slug, image_path, alt_text, caption, image_type)
                 VALUES (?, ?, ?, ?, 'featured')"
            )->execute([
                $slug,
                $path,
                sanitizeInput($_POST['block_image_alt'] ?? '', 300),
                sanitizeInput($_POST['block_image_caption'] ?? '', 500),
            ]);
            $notes[] = 'Image uploaded.';
        } else {
            $notes[] = 'The image was rejected - check it is a JPG, PNG, GIF or WebP under 20MB.';
        }
    }

    return implode(' ', $notes);
}

/**
 * The admin editor for a featured block.
 */
function renderFeaturedBlockCard(string $page, string $label): string {
    $slug = 'featured-' . $page;
    $row  = getPageContent($slug);
    $text = e((string) ($row['content'] ?? ''));

    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT image_path, alt_text, caption FROM page_images
         WHERE page_slug = ? AND image_type = 'featured' LIMIT 1"
    );
    $stmt->execute([$slug]);
    $img = $stmt->fetch() ?: null;

    $existing = '';
    if ($img) {
        $existing = '<div class="form-group">'
                  . '<label class="form-label">Current image</label>'
                  . '<img src="' . e(SITE_URL . '/' . $img['image_path']) . '" '
                  . 'style="max-width:220px;height:auto;border-radius:0.375rem;display:block;margin-bottom:0.5rem;">'
                  . '<label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;">'
                  . '<input type="checkbox" name="remove_block_image" value="1"> Remove this image'
                  . '</label></div>';
    }

    return '<div class="admin-card">'
         . '<div class="admin-card__header"><h2 class="admin-card__title">Featured Block</h2></div>'
         . '<p style="color:var(--color-muted);font-size:0.8125rem;margin-bottom:1rem;">'
         . 'A highlighted panel shown near the top of the public ' . e($label) . ' page, '
         . 'between the heading and the content below it. Leave everything blank and '
         . 'nothing appears. Blank lines separate paragraphs; links are allowed, and you '
         . 'can attach one image.</p>'
         . '<form method="POST" enctype="multipart/form-data">'
         . csrfField()
         . '<div class="form-group">'
         . '<label class="form-label" for="block_content_' . e($page) . '">Text</label>'
         . '<textarea class="form-textarea" id="block_content_' . e($page) . '" '
         . 'name="block_content" rows="10" '
         . 'placeholder="Plain text is fine. For a link: &lt;a href=&quot;https://example.com&quot;&gt;link text&lt;/a&gt;">'
         . $text . '</textarea></div>'
         . $existing
         . '<div class="form-group">'
         . '<label class="form-label">' . ($img ? 'Replace image' : 'Image (optional)') . '</label>'
         . '<input class="form-input" type="file" name="block_image" accept="image/*"></div>'
         . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">'
         . '<div class="form-group"><label class="form-label">Image alt text</label>'
         . '<input class="form-input" type="text" name="block_image_alt" maxlength="300" value="'
         . e($img['alt_text'] ?? '') . '"></div>'
         . '<div class="form-group"><label class="form-label">Image caption</label>'
         . '<input class="form-input" type="text" name="block_image_caption" maxlength="500" value="'
         . e($img['caption'] ?? '') . '"></div></div>'
         . '<button type="submit" name="save_featured" value="1" class="btn btn--primary">Save Featured Block</button>'
         . '</form></div>';
}

// ── File Upload Handling (OWASP Unrestricted File Upload Prevention) ──

/**
 * Securely handle file upload with validation
 */
/**
 * Map each permitted extension to the MIME types finfo may legitimately report
 * for it, and the kind of asset it represents.
 *
 * Browsers vary in the Content-Type they attach and finfo is occasionally
 * conservative with container formats, so both this and a signature check are
 * applied rather than either alone. Anything not listed here cannot be uploaded.
 */
function allowedUploadKinds(): array {
    return [
        'jpg'  => ['mimes' => ['image/jpeg'], 'kind' => 'image'],
        'jpeg' => ['mimes' => ['image/jpeg'], 'kind' => 'image'],
        'png'  => ['mimes' => ['image/png'],  'kind' => 'image'],
        'gif'  => ['mimes' => ['image/gif'],  'kind' => 'image'],
        'webp' => ['mimes' => ['image/webp'], 'kind' => 'image'],
        'mp4'  => ['mimes' => ['video/mp4', 'application/mp4'],          'kind' => 'video'],
        'webm' => ['mimes' => ['video/webm', 'application/octet-stream'], 'kind' => 'video'],
        'ogg'  => ['mimes' => ['video/ogg', 'audio/ogg', 'application/ogg'], 'kind' => 'video'],
        'pdf'  => ['mimes' => ['application/pdf'], 'kind' => 'pdf'],
    ];
}

/**
 * Confirm a file's leading bytes match the container its extension claims.
 *
 * finfo alone is not quite enough for video: some encoders produce files it
 * reports as application/octet-stream. Reading the signature directly is both
 * stricter and less likely to reject a legitimate upload.
 */
function hasExpectedSignature(string $tmpPath, string $ext): bool {
    $fh = @fopen($tmpPath, 'rb');
    if (!$fh) return false;
    $head = fread($fh, 16);
    fclose($fh);
    if ($head === false || strlen($head) < 8) return false;

    switch ($ext) {
        case 'jpg':
        case 'jpeg': return str_starts_with($head, "\xFF\xD8\xFF");
        case 'png':  return str_starts_with($head, "\x89PNG\r\n\x1A\n");
        case 'gif':  return str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a');
        case 'webp': return str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP';
        case 'pdf':  return str_starts_with($head, '%PDF-');
        case 'mp4':  return substr($head, 4, 4) === 'ftyp';          // ISO base media
        case 'webm': return str_starts_with($head, "\x1A\x45\xDF\xA3"); // EBML
        case 'ogg':  return str_starts_with($head, 'OggS');
    }
    return false;
}

/**
 * Largest dimension an uploaded photo is kept at.
 *
 * Nothing in the layout displays an image wider than the full-bleed hero, and
 * the pre-generated hero derivatives top out at 2560px, so anything beyond this
 * is bytes the visitor downloads and the browser then throws away.
 */
define('MAX_IMAGE_DIMENSION', 2560);

/**
 * Re-encode an uploaded image in place, from its decoded pixels.
 *
 * This is the step that makes a "polyglot" upload harmless. A file can be a
 * genuine JPEG — correct signature, correct finfo type, parses cleanly — and
 * still carry arbitrary bytes appended after the image data. Every validation
 * based on reading the file will pass it. Decoding to a pixel buffer and
 * writing a fresh file keeps only the pixels, so anything trailing is gone.
 *
 * Three useful side effects:
 *   - EXIF is dropped, including GPS coordinates that phones embed in photos.
 *     Publishing those alongside an image is a real privacy leak.
 *   - Oversized photos are scaled down to MAX_IMAGE_DIMENSION, so a 6000px
 *     camera original cannot bloat a page or overflow a layout slot.
 *   - EXIF orientation is applied, so portrait phone photos are no longer
 *     displayed on their side.
 *
 * Animated GIFs are deliberately left untouched: GD would flatten them to a
 * single frame, which would silently destroy the upload. They still had to pass
 * the signature, finfo and getimagesize checks to get here.
 *
 * Returns true if the file is safe to keep (whether or not it was rewritten).
 */
function sanitiseUploadedImage(string $path, string $ext): bool {
    if (!extension_loaded('gd')) {
        // Without GD the file is kept as validated. Execution in uploads/ is
        // disabled by .htaccess, so a trailing payload is inert either way.
        error_log('[upload] GD unavailable; image kept without re-encoding');
        return true;
    }

    // Animated GIF: leave alone rather than flatten it.
    if ($ext === 'gif') {
        $blob = @file_get_contents($path);
        if ($blob !== false && substr_count($blob, "\x00\x21\xF9\x04") > 1) {
            return true;
        }
    }

    $info = @getimagesize($path);
    if ($info === false) return false;
    [$width, $height] = $info;
    if ($width < 1 || $height < 1) return false;

    switch ($ext) {
        case 'jpg':
        case 'jpeg': $src = @imagecreatefromjpeg($path); break;
        case 'png':  $src = @imagecreatefrompng($path);  break;
        case 'gif':  $src = @imagecreatefromgif($path);  break;
        case 'webp': $src = function_exists('imagecreatefromwebp')
                          ? @imagecreatefromwebp($path) : null; break;
        default:     return true;   // not an image we handle
    }
    if (!$src) {
        error_log('[upload] could not decode image for re-encoding');
        return false;
    }

    // Apply EXIF orientation before scaling, so the rotation is baked in.
    if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if (in_array($orientation, [3, 6, 8], true)) {
            $angle = $orientation === 3 ? 180 : ($orientation === 6 ? -90 : 90);
            $rotated = @imagerotate($src, $angle, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
                $width  = imagesx($src);
                $height = imagesy($src);
            }
        }
    }

    // Scale down only; never enlarge a small image.
    $scale = min(1, MAX_IMAGE_DIMENSION / max($width, $height));
    $tw = max(1, (int) round($width * $scale));
    $th = max(1, (int) round($height * $scale));

    $dst = imagecreatetruecolor($tw, $th);
    if (!$dst) { imagedestroy($src); return false; }

    // Keep transparency for the formats that support it.
    if (in_array($ext, ['png', 'gif', 'webp'], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $tw, $th, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $width, $height);

    $ok = false;
    switch ($ext) {
        case 'jpg':
        case 'jpeg': $ok = imagejpeg($dst, $path, 85); break;
        case 'png':  $ok = imagepng($dst, $path, 6);   break;
        case 'gif':  $ok = imagegif($dst, $path);      break;
        case 'webp': $ok = function_exists('imagewebp') ? imagewebp($dst, $path, 85) : false; break;
    }

    imagedestroy($src);
    imagedestroy($dst);

    if (!$ok) {
        error_log('[upload] re-encode failed for .' . $ext);
        return false;
    }
    return true;
}

/**
 * Securely handle a file upload and record it in the uploads table.
 *
 * Returns the stored path relative to the web root, or null if the file was
 * rejected. Callers show a generic message; the specific reason is logged.
 *
 * VALIDATION ORDER (OWASP Unrestricted File Upload):
 *   1. A genuine, successful upload
 *   2. Size within limits, measured on disk rather than taken from the request
 *   3. Extension on the allowlist
 *   4. The REAL MIME type, read from the contents with finfo, is one this
 *      extension is permitted to be
 *   5. Leading bytes match the expected container signature
 *   6. Images must additionally parse as images
 *
 * Step 4 is the substantive change. The previous version checked $file['type'],
 * which the browser supplies in the multipart body and a client can set to
 * anything. The extension allowlist and the uploads .htaccess meant it was not
 * exploitable in practice, but the MIME check itself was decorative; it is now
 * derived from the file's own bytes.
 */
function handleFileUpload(array $file, string $subdir = 'images'): ?string {
    $subdir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subdir);

    $uploadDir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 1. A genuine, successful upload.
    if (!isset($file['tmp_name'], $file['name'], $file['error'])) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_uploaded_file($file['tmp_name'])) {
        error_log('[upload] rejected: not an uploaded file');
        return null;
    }

    // 2. Size on disk; the value in the request is client-controlled.
    $size = @filesize($file['tmp_name']);
    if ($size === false || $size === 0 || $size > MAX_UPLOAD_SIZE) {
        error_log('[upload] rejected: size ' . var_export($size, true));
        return null;
    }

    // 3. Extension allowlist.
    $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $kinds = allowedUploadKinds();
    if (!isset($kinds[$ext])) {
        error_log('[upload] rejected: extension .' . $ext);
        return null;
    }

    // 4. Real MIME type, read from the contents.
    $realMime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $realMime = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if ($realMime !== '' && !in_array($realMime, $kinds[$ext]['mimes'], true)) {
        error_log('[upload] rejected: .' . $ext . ' reported as ' . $realMime);
        return null;
    }

    // 5. Container signature.
    if (!hasExpectedSignature($file['tmp_name'], $ext)) {
        error_log('[upload] rejected: signature mismatch for .' . $ext);
        return null;
    }

    $fileType = $kinds[$ext]['kind'];

    // 6. Images must actually parse.
    if ($fileType === 'image' && @getimagesize($file['tmp_name']) === false) {
        error_log('[upload] rejected: unreadable image');
        return null;
    }

    // Random name: the original is never used on disk, so a crafted filename
    // cannot traverse directories or overwrite an existing file.
    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log('[upload] move_uploaded_file failed');
        return null;
    }
    @chmod($destination, 0644);   // never executable

    /*
     * Re-encode images from their decoded pixels. A file can be a genuine image
     * and still carry appended bytes that every read-based check accepts; only
     * rewriting it from pixels removes them. Also strips EXIF (GPS included),
     * caps the dimensions, and applies orientation. See sanitiseUploadedImage().
     */
    if ($fileType === 'image' && !sanitiseUploadedImage($destination, $ext)) {
        @unlink($destination);          // never leave a file we could not sanitise
        error_log('[upload] rejected: image failed sanitisation');
        return null;
    }
    // Size is re-read: re-encoding usually changes it.
    $size = @filesize($destination) ?: $size;

    global $pdo;
    $relPath = 'uploads/' . $subdir . '/' . $filename;

    $stmt = $pdo->prepare(
        "INSERT INTO uploads (filename, original_name, file_type, mime_type, file_size, file_path, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $filename,
        sanitizeInput(basename($file['name']), 255),
        $fileType,
        $realMime !== '' ? $realMime : $kinds[$ext]['mimes'][0],
        $size,
        $relPath,
        $_SESSION['admin_id'] ?? null,
    ]);

    return $relPath;
}

/**
 * Build a URL for a static asset with a cache-busting version string.
 *
 * .htaccess tells browsers to cache CSS and JS for a week. Without a version
 * string, a returning visitor keeps the old stylesheet while receiving freshly
 * deployed HTML - so a markup change that renames CSS classes renders unstyled
 * until their cache happens to expire. Appending the file's modification time
 * changes the URL whenever the file changes, so a deploy invalidates the cache
 * for exactly the files that moved and nothing else.
 *
 * Falls back to an unversioned URL if the file cannot be stat'ed, so a missing
 * file can never take the page down.
 *
 * @param string $relPath Path below the web root, e.g. 'assets/css/style.css'
 */
function asset(string $relPath): string {
    $relPath = ltrim($relPath, '/');
    $full    = dirname(__DIR__) . '/' . $relPath;
    $version = @filemtime($full);

    return SITE_URL . '/' . $relPath . ($version ? '?v=' . $version : '');
}

function currentPage(): string {
    $script = basename($_SERVER['SCRIPT_NAME'], '.php');
    return $script === 'index' ? 'home' : $script;
}

/**
 * Get client IP (for rate limiting).
 *
 * Deliberately uses REMOTE_ADDR only. X-Forwarded-For and friends are attacker-
 * controlled headers: trusting them would let anyone bypass every rate limit by
 * sending a fresh fake IP on each request. If this site is ever placed behind a
 * CDN or reverse proxy, add that proxy's addresses to a trusted list here and only
 * then read the forwarded header — never trust it unconditionally.
 */
function getClientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// ── Page PDFs ──

/**
 * Get all PDFs attached to a page
 */
function getPagePdfs(string $pageSlug): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM page_pdfs WHERE page_slug = ? AND is_visible = 1 ORDER BY display_order ASC");
    $stmt->execute([$pageSlug]);
    return $stmt->fetchAll();
}

// ── Video & PDF Rendering Helpers ──

/**
 * Convert a video URL (YouTube, Vimeo, or direct) to an inline embed.
 * Supports: YouTube, Vimeo, and direct .mp4/.webm/.ogg files.
 * Returns HTML string or empty string if no valid video.
 * 
 * SECURITY: URLs are escaped with e() to prevent XSS.
 */
function renderVideoEmbed(string $url): string {
    if (empty(trim($url))) return '';
    
    // YouTube: extract video ID from various URL formats
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $videoId = $m[1];
        return videoFacade('youtube', $videoId);
    }
    
    // Vimeo: extract video ID
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        $videoId = $m[1];
        return videoFacade('vimeo', $videoId);
    }
    
    // Direct video file (mp4, webm, ogg) — local or remote
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm', 'ogg'], true)) {
        return '<div class="video-embed video-embed--inline"><video controls preload="metadata"><source src="' . e($url) . '" type="video/' . e($ext) . '">Your browser does not support video.</video></div>';
    }
    
    return '';
}

/**
 * Local path to a video's poster image, fetching and caching it once.
 *
 * The facade looked dead without a poster - a flat gradient tells a visitor
 * nothing about the video. The obvious fix, pointing <img> straight at
 * i.ytimg.com, would undo the reason the facade exists: that request happens
 * on page load, before anyone has answered the consent banner, and hands the
 * visitor's IP to Google exactly as the old auto-loading iframe did.
 *
 * So the poster is fetched once by the server and stored under uploads. After
 * that it is an ordinary same-origin image. Visitors never contact a third
 * party; the page still looks alive.
 *
 * A failed fetch writes a dated marker so a video whose thumbnail does not
 * exist is not re-requested on every page view. The marker expires after a
 * day, which is enough for a transient outage to heal itself.
 *
 * @return string Web-relative path, or '' when no poster could be obtained.
 */
function videoThumbnail(string $provider, string $videoId): string {
    // The id is already constrained by renderVideoEmbed()'s patterns, but this
    // value is about to be used in a filename and a URL, so re-assert it.
    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $videoId)) return '';
    if (!in_array($provider, ['youtube', 'vimeo'], true)) return '';

    $relDir  = 'uploads/images/video-thumbs';
    $absDir  = __DIR__ . '/../' . $relDir;
    $name    = $provider . '-' . $videoId . '.jpg';
    $absFile = $absDir . '/' . $name;
    $relFile = $relDir . '/' . $name;

    if (is_file($absFile) && filesize($absFile) > 0) {
        return $relFile;
    }

    $marker = $absFile . '.unavailable';
    if (is_file($marker) && (time() - filemtime($marker)) < 86400) {
        return '';
    }

    if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) return '';
    if (!is_writable($absDir)) return '';

    $candidates = [];
    if ($provider === 'youtube') {
        // maxres does not exist for every video; hq always does.
        $candidates[] = 'https://i.ytimg.com/vi/' . $videoId . '/maxresdefault.jpg';
        $candidates[] = 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
    } else {
        $oembed = fetchRemoteBytes(
            'https://vimeo.com/api/oembed.json?url=https%3A//vimeo.com/' . $videoId,
            200000
        );
        if ($oembed !== '') {
            $data = json_decode($oembed, true);
            $thumb = is_array($data) ? ($data['thumbnail_url'] ?? '') : '';
            // Only follow Vimeo's own CDN. The URL comes from a remote response,
            // so without this check it could point the server anywhere.
            $host = strtolower((string) parse_url($thumb, PHP_URL_HOST));
            if ($thumb && ($host === 'i.vimeocdn.com' || str_ends_with($host, '.vimeocdn.com'))) {
                $candidates[] = $thumb;
            }
        }
    }

    foreach ($candidates as $url) {
        $bytes = fetchRemoteBytes($url, 3 * 1024 * 1024);
        if ($bytes === '') continue;

        // Must actually be an image, and not a 120x90 "unavailable" placeholder,
        // which is what YouTube serves for a missing maxresdefault.
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[0] < 200) continue;
        if (!in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) continue;

        if (@file_put_contents($absFile, $bytes) !== false) {
            @chmod($absFile, 0644);
            @unlink($marker);
            return $relFile;
        }
    }

    @file_put_contents($marker, '');
    return '';
}

/**
 * Fetch a small remote file, with a short timeout and a hard size cap.
 *
 * Used only for video posters, whose URLs are built from a validated id and a
 * fixed host. Redirects are not followed: every endpoint used here answers
 * directly, and following them would widen where the server can be sent.
 */
function fetchRemoteBytes(string $url, int $maxBytes): string {
    if (!preg_match('#^https://#i', $url)) return '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; site-thumbnail-cache)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) return '';
        return strlen($body) > $maxBytes ? '' : $body;
    }

    if (!ini_get('allow_url_fopen')) return '';
    $ctx = stream_context_create(['http' => [
        'timeout'       => 6,
        'follow_location' => 0,
        'user_agent'    => 'Mozilla/5.0 (compatible; site-thumbnail-cache)',
    ]]);
    $body = @file_get_contents($url, false, $ctx, 0, $maxBytes + 1);
    if ($body === false) return '';
    return strlen($body) > $maxBytes ? '' : $body;
}

/**
 * A click-to-play stand-in for a third-party video player.
 *
 * Returns markup that contacts nobody. assets/js/main.js replaces it with the
 * real iframe when the button is pressed, so Google or Vimeo only hear from the
 * visitor if they actually choose to watch. That matters for consent - an
 * autoloading iframe sends the IP and User-Agent before the banner is answered -
 * and it removes roughly two dozen requests from a page carrying four videos.
 *
 * The provider and id are the only values interpolated, and both are already
 * constrained: the id comes from a [a-zA-Z0-9_-]{11} or \d+ match in
 * renderVideoEmbed(), and the provider is a literal in this file.
 */
function videoFacade(string $provider, string $videoId): string {
    $label = $provider === 'vimeo' ? 'Vimeo' : 'YouTube';

    /*
     * The poster is a locally cached copy (see videoThumbnail()), so it is a
     * same-origin image like any other. When there is none - a private video,
     * or the server could not reach the CDN - the gradient underneath shows
     * through and the play button still reads clearly against it.
     */
    $poster = videoThumbnail($provider, $videoId);
    $posterTag = $poster === '' ? '' :
        '<img class="video-facade__poster" src="' . e(SITE_URL . '/' . $poster) . '" '
      . 'alt="" loading="lazy" decoding="async" width="1280" height="720">';

    return '<div class="video-embed video-embed--facade" '
         . 'data-video-provider="' . e($provider) . '" '
         . 'data-video-id="' . e($videoId) . '">'
         . $posterTag
         . '<button type="button" class="video-facade__play" '
         . 'aria-label="Play video (loads ' . e($label) . ')">'
         . '<svg width="68" height="48" viewBox="0 0 68 48" aria-hidden="true" focusable="false">'
         . '<path class="video-facade__bg" d="M66.5 7.7a8.6 8.6 0 0 0-6-6C55.2 0 34 0 34 0S12.8 0 7.5 1.7a8.6 8.6 0 0 0-6 6A89.6 89.6 0 0 0 0 24a89.6 89.6 0 0 0 1.5 16.3 8.6 8.6 0 0 0 6 6C12.8 48 34 48 34 48s21.2 0 26.5-1.7a8.6 8.6 0 0 0 6-6A89.6 89.6 0 0 0 68 24a89.6 89.6 0 0 0-1.5-16.3z"/>'
         . '<path d="M45 24 27 14v20z" fill="#fff"/></svg>'
         . '</button>'
         . '<span class="video-facade__note">Plays from ' . e($label) . '</span>'
         . '</div>';
}

/**
 * Escape a URL for an href/src attribute, refusing dangerous schemes.
 *
 * sanitizeUrl() already rejects anything but http(s) and site-relative paths on
 * the way in, but that only protects rows written through the current forms. A
 * value stored before that filter existed, or written by any other route, still
 * reaches a template - and "javascript:alert(1)" in an href runs on click. This
 * is the output-side half of the pair: whatever is in the database, what lands
 * in the attribute is either a safe URL or nothing at all.
 *
 * Returns '' for a rejected URL so callers can treat it as "no link".
 */
function safeUrl(?string $url): string {
    $url = trim((string) $url);
    if ($url === '') return '';

    // Strip control characters first: "java\0script:" and "java\tscript:" are
    // both read as javascript: by some browsers.
    $probe = strtolower(preg_replace('/[\x00-\x20]/', '', $url));

    // Protocol-relative is refused: it can retarget to an attacker's host.
    if (str_starts_with($probe, '//')) return '';

    if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
        return e($url);
    }
    if (preg_match('/^(https?|mailto|tel):/', $probe)) {
        return e($url);
    }
    return '';
}

/**
 * Render a PDF link/viewer for a given path.
 * Shows a "View PDF" button that opens the PDF in-browser or embedded iframe.
 * 
 * SECURITY: Path is escaped with e().
 */
function renderPdfLink(string $path, string $label = 'View PDF'): string {
    if (empty(trim($path))) return '';
    $fullUrl = (str_starts_with($path, 'http') ? $path : SITE_URL . '/' . $path);
    // Open PDF inline in browser viewer (view-only, no download prompt)
    return '<a href="' . e($fullUrl) . '#toolbar=1&navpanes=0&scrollbar=1" target="_blank" rel="noopener" class="pdf-inline__link">'
         . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'
         . e($label) . '</a>';
}

/**
 * Render page PDFs section — view-only list of PDFs attached to a page.
 * PDFs open inline in the browser; no download button is provided.
 */
function renderPagePdfs(string $pageSlug): string {
    $pdfs = getPagePdfs($pageSlug);
    if (empty($pdfs)) return '';
    
    $html = '<div class="page-pdfs"><div class="section__divider"></div><h3 class="subsection-title"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Documents</h3>';
    
    foreach ($pdfs as $pdf) {
        $fullUrl = SITE_URL . '/' . $pdf['pdf_path'];
        $html .= '<div class="page-pdfs__item">';
        $html .= '<svg class="page-pdfs__icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
        $html .= '<div class="page-pdfs__info"><div class="page-pdfs__title">' . e($pdf['title']) . '</div>';
        if (!empty($pdf['description'])) $html .= '<div class="page-pdfs__desc">' . e($pdf['description']) . '</div>';
        $html .= '</div>';
        $html .= '<div class="page-pdfs__actions">';
        // View-only: opens PDF inline in browser tab (no download button)
        $html .= '<a href="' . e($fullUrl) . '#toolbar=1&navpanes=0&scrollbar=1" target="_blank" rel="noopener" class="page-pdfs__btn">View</a>';
        $html .= '</div></div>';
    }
    
    $html .= '</div>';
    return $html;
}
