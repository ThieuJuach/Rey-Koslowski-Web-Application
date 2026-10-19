<?php
/**
 * API: Delete uploaded media file
 *
 * SECURITY: Auth required, CSRF validation (was MISSING — this is a destructive
 * endpoint, so a cross-site POST could previously delete an admin's files),
 * schema-validated input, path-traversal containment, rate limiting.
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

enforceRateLimit('api_delete', API_MAX_PER_MINUTE, 60, 'json');

// Exactly one field is accepted; anything else is a 400.
$clean = validateSchemaOrFail($_POST, [
    'id' => ['type' => 'int', 'required' => true, 'min' => 1],
]);
$id = $clean['id'];

try {
    // Get file path first
    $stmt = $pdo->prepare("SELECT file_path FROM uploads WHERE id = ?");
    $stmt->execute([$id]);
    $upload = $stmt->fetch();

    if (!$upload) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not found']);
        exit;
    }

    // Cheap string checks first (OWASP path traversal).
    $filePath = $upload['file_path'];
    if (strpos($filePath, '..') !== false || !str_starts_with($filePath, 'uploads/')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file path']);
        exit;
    }

    // Then resolve symlinks and confirm the real path is genuinely inside uploads/.
    // The trailing separator matters: without it, "uploads-backup" would also match.
    $realPath   = realpath(__DIR__ . '/../' . $filePath);
    $uploadsDir = realpath(__DIR__ . '/../uploads');

    if ($realPath && $uploadsDir && str_starts_with($realPath, $uploadsDir . DIRECTORY_SEPARATOR)) {
        @unlink($realPath);
    }

    // Delete DB record
    $stmt = $pdo->prepare("DELETE FROM uploads WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("API delete-media error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
