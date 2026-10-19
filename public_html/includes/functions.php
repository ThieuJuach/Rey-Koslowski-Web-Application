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

// ── File Upload Handling (OWASP Unrestricted File Upload Prevention) ──

/**
 * Securely handle file upload with validation
 */
function handleFileUpload(array $file, string $subdir = 'images'): ?string {
    $subdir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subdir);
    
    $uploadDir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_UPLOAD_SIZE) return null;
    if ($file['size'] === 0) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) return null;
    
    $allowedMimes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_DOC_TYPES);
    if (!in_array($file['type'], $allowedMimes, true)) return null;
    
    // For images, verify actual content with getimagesize
    if (in_array($file['type'], ALLOWED_IMAGE_TYPES, true)) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) return null;
    }
    
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        global $pdo;
        $fileType = 'other';
        if (in_array($file['type'], ALLOWED_IMAGE_TYPES)) $fileType = 'image';
        elseif (in_array($file['type'], ALLOWED_VIDEO_TYPES)) $fileType = 'video';
        elseif (in_array($file['type'], ALLOWED_DOC_TYPES)) $fileType = 'pdf';

        $originalName = sanitizeInput(basename($file['name']), 255);

        $stmt = $pdo->prepare("INSERT INTO uploads (filename, original_name, file_type, mime_type, file_size, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $filename,
            $originalName,
            $fileType,
            $file['type'],
            $file['size'],
            'uploads/' . $subdir . '/' . $filename,
            $_SESSION['admin_id'] ?? null
        ]);

        return 'uploads/' . $subdir . '/' . $filename;
    }
    return null;
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
        return '<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/' . e($videoId) . '?rel=0" allowfullscreen loading="lazy" title="Video"></iframe></div>';
    }
    
    // Vimeo: extract video ID
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        $videoId = $m[1];
        return '<div class="video-embed"><iframe src="https://player.vimeo.com/video/' . e($videoId) . '?dnt=1" allowfullscreen loading="lazy" title="Video"></iframe></div>';
    }
    
    // Direct video file (mp4, webm, ogg) — local or remote
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm', 'ogg'], true)) {
        return '<div class="video-embed video-embed--inline"><video controls preload="metadata"><source src="' . e($url) . '" type="video/' . e($ext) . '">Your browser does not support video.</video></div>';
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
