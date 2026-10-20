<?php
/**
 * API: record one page view (first-party analytics).
 *
 * This is the only public, unauthenticated write endpoint on the site, so it is
 * deliberately narrow:
 *
 *  - POST only, and it writes exactly one row with no caller-controlled identifiers
 *  - Rate limited per IP, well above real browsing speed but far below flood level
 *  - Every field is validated against a schema and length-capped before it is stored
 *  - Unexpected fields are ignored, never persisted
 *  - Responds 204 with no body, so nothing can be probed through its output
 *
 * PRIVACY: no IP address and no user-agent string is stored. The visitor is
 * represented only by a one-way daily-rotating digest; see visitorHash() below.
 * A row is written only when the caller has consented — the beacon in
 * assets/js/main.js does not fire otherwise.
 */
require_once __DIR__ . '/../config.php';

// No body on any response: the client never needs to read anything back.
http_response_code(204);
header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── Method ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

/*
 * ── Rate limit ──
 * Generous enough that fast browsing never trips it (one beacon per page view),
 * tight enough that a single IP cannot inflate the figures or fill the table.
 * 'html' is not used here: the caller is a beacon, so a 429 with no body is right.
 */
if (isRateLimited('track_' . getClientIp(), PUBLIC_PAGE_MAX_PER_MINUTE, 60)) {
    http_response_code(429);
    exit;
}

// ── Parse body ──
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 2048) {   // a legitimate beacon is ~100 bytes
    http_response_code(400);
    exit;
}
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    exit;
}

/*
 * ── Validate ──
 * Only two fields are accepted. Anything else in the payload is discarded by
 * validateSchema(), so a caller cannot smuggle extra columns in.
 */
$v = validateSchema($input, [
    'path'     => ['type' => 'string', 'required' => true, 'max' => 255],
    'referrer' => ['type' => 'string', 'required' => false, 'max' => 2048, 'default' => ''],
]);

if (!$v['ok']) {
    http_response_code(400);
    exit;
}

/*
 * The path must look like a path this site actually serves. Rejecting anything
 * else stops the table being filled with junk or with attacker-chosen strings
 * that later get rendered in the admin dashboard.
 */
$path = $v['data']['path'];

// Drop any query string or fragment first: visitor-typed values must never be
// stored, and they must not be able to smuggle characters past the check below.
$path = strtok($path, '?#');

/*
 * Accept only shapes this site actually serves: "/" or "/page.php", optionally
 * nested one or more directories deep.
 *
 * The earlier pattern allowed "." and "/" freely, which let "/../../etc/passwd"
 * through. Nothing here touches the filesystem, so that was never a file-read
 * bug, but it did let an anonymous caller write arbitrary strings into the
 * analytics table and therefore into the admin dashboard. Traversal sequences
 * and doubled slashes are now rejected outright rather than merely escaped later.
 */
if ($path === ''
    || $path[0] !== '/'
    || strlen($path) > 255
    || str_contains($path, '..')
    || str_contains($path, '//')
    || !preg_match('#^/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]*(?:\.php)?$#', $path)
) {
    http_response_code(400);
    exit;
}

/*
 * Keep only the referrer's host. The full URL is discarded deliberately — the
 * host answers "where did they come from" without recording the specific page
 * or any search terms it might contain. Own-domain referrals are internal
 * navigation and are stored as NULL so they do not pollute the referrer report.
 */
$referrerHost = null;
$ref = trim((string) ($v['data']['referrer'] ?? ''));
if ($ref !== '') {
    $host = parse_url($ref, PHP_URL_HOST);
    if (is_string($host) && $host !== '') {
        $host = strtolower(ltrim($host, '.'));
        if (strlen($host) <= 255 && preg_match('/^[a-z0-9.-]+$/', $host)) {
            $ownHost = strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
            $referrerHost = ($host === $ownHost) ? null : $host;
        }
    }
}

/**
 * Build a non-reversible, daily-rotating token for this visitor.
 *
 * Inputs are the IP, the user agent and today's date, keyed with a server-side
 * secret. Including the date means the digest changes at midnight, so the same
 * person cannot be followed from one day to the next — it supports "unique
 * visitors today" and nothing more.
 *
 * The key falls back to DB_PASS when ANALYTICS_SALT is not configured. That is a
 * pragmatic choice rather than an ideal one: anyone holding both the database
 * and that key could attempt to confirm whether a *specific* IP visited, since
 * the IPv4 space is small enough to enumerate. It is still a large improvement
 * on storing addresses outright, and setting ANALYTICS_SALT in secrets.php
 * removes the shared-key weakness entirely.
 */
function visitorHash(): string {
    $key = secret('ANALYTICS_SALT', '') ?: DB_PASS;
    $material = getClientIp()
              . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')
              . '|' . gmdate('Y-m-d');
    return hash_hmac('sha256', $material, $key);
}

/**
 * Flag obvious crawlers so the dashboard can report human traffic by default.
 * Bots rarely run JavaScript, so few will ever reach this endpoint; this simply
 * catches the ones that do rather than pretending to be exhaustive.
 */
function looksLikeBot(): bool {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') return true;   // no UA at all is not a browser
    foreach (['bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
              'headless', 'phantomjs', 'lighthouse', 'preview', 'monitor'] as $needle) {
        if (str_contains($ua, $needle)) return true;
    }
    return false;
}

// ── Store ──
try {
    $stmt = $pdo->prepare(
        "INSERT INTO page_views (path, referrer_host, visitor_hash, is_bot, viewed_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$path, $referrerHost, visitorHash(), looksLikeBot() ? 1 : 0]);
} catch (PDOException $e) {
    // Analytics must never be able to break a page or leak schema details.
    // Log it and still answer 204; the visitor is unaffected either way.
    error_log('[analytics] insert failed: ' . $e->getMessage());
}

http_response_code(204);
