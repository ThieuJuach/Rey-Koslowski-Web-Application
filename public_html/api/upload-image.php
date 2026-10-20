<?php
/**
 * API: Upload image/PDF file
 *
 * SECURITY:
 * - Auth required
 * - CSRF token validation  (was MISSING — an authenticated admin visiting a hostile
 *   page could previously be made to upload files to this site cross-origin)
 * - File type validation (extension + declared MIME + real content sniff)
 * - File size limit
 * - Rate limiting on IP and on the authenticated account, with a graceful 429
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF must be checked before any side effect, including writing a temp file.
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Reload the page.']);
    exit;
}

// 20 uploads per minute per IP, 40 for the signed-in admin. Sends a 429 + Retry-After.
enforceRateLimit('upload_image', 20, 60, 'json');
pruneRateLimitFiles();

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

// Only 'type' is accepted alongside the file; anything else is rejected.
$clean = validateSchemaOrFail($_POST, [
    'type' => ['type' => 'enum', 'values' => ['image', 'pdf'], 'default' => 'image'],
]);
$type = $clean['type'] ?: 'image';

$file = $_FILES['file'];

// Surface PHP's own upload errors instead of a generic failure.
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    echo json_encode([
        'success' => false,
        'error'   => $tooBig ? 'File is larger than the 20MB limit' : 'Upload failed',
    ]);
    exit;
}

/*
 * Cheap pre-filter on the extension so an obviously wrong file is rejected
 * before it is written anywhere.
 *
 * This deliberately does NOT look at $file['type']: that value comes from the
 * browser and is both spoofable and unreliable - some browsers report
 * application/octet-stream for perfectly valid files, which caused legitimate
 * uploads to be refused here. handleFileUpload() is the authority; it reads the
 * real type from the file's own bytes.
 */
$ext      = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
$expected = $type === 'pdf' ? ['pdf'] : ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($ext, $expected, true)) {
    http_response_code(415);
    echo json_encode([
        'success' => false,
        'error'   => $type === 'pdf'
            ? 'Please choose a PDF file.'
            : 'Please choose a JPG, PNG, GIF or WebP image.',
    ]);
    exit;
}

$path = handleFileUpload($file, $type === 'pdf' ? 'pdfs' : 'images');

if ($path) {
    echo json_encode(['success' => true, 'path' => $path]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload failed. Check file type and size.']);
}
