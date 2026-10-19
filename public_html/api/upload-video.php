<?php
/**
 * API: Upload video file
 *
 * SECURITY: Auth required, CSRF validation (was missing), file type validation,
 * rate limiting on IP + account with a graceful 429.
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

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Reload the page.']);
    exit;
}

// Videos are large, so the ceiling is lower than for images.
enforceRateLimit('upload_video', 10, 60, 'json');

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

// This endpoint takes a file and nothing else.
validateSchemaOrFail($_POST, []);

$file = $_FILES['file'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    echo json_encode([
        'success' => false,
        'error'   => $tooBig ? 'File is larger than the 20MB limit' : 'Upload failed',
    ]);
    exit;
}

if (!in_array($file['type'], ALLOWED_VIDEO_TYPES, true)) {
    http_response_code(415);
    echo json_encode(['success' => false, 'error' => 'Video type not allowed. Use MP4, WebM, or OGG.']);
    exit;
}

$path = handleFileUpload($file, 'videos');

if ($path) {
    echo json_encode(['success' => true, 'path' => $path]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
}
