<?php
/**
 * API: Reorder sections via drag-and-drop
 *
 * SECURITY: Auth required, CSRF validation, strict table allowlist, per-item
 * validation, rate limiting on IP + account with a graceful 429.
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

enforceRateLimit('api_reorder', API_MAX_PER_MINUTE, 60, 'json');

// Cap the body size before parsing, so a huge payload cannot be used to burn memory.
$rawBody = file_get_contents('php://input', false, null, 0, 256 * 1024);
$input   = json_decode($rawBody ?: '', true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// CSRF for a JSON body: accepted from the X-CSRF-Token header or the body itself.
$csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!is_string($csrfToken) || $csrfToken === ''
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Reload the page.']);
    exit;
}

// Table names are SQL identifiers and cannot be bound as parameters, so they must
// come from a fixed allowlist (OWASP: SQL injection prevention).
$allowedTables = [
    'page_sections', 'publications', 'research_projects', 'courses',
    'media_appearances', 'migration_faculty', 'migration_courses',
    'presentations', 'professional_service_items', 'community_engagement_items',
];

$table = $input['table'] ?? '';
$order = $input['order'] ?? [];

if (!is_string($table) || !in_array($table, $allowedTables, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid table']);
    exit;
}

if (!is_array($order) || $order === []) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No order supplied']);
    exit;
}

if (count($order) > 500) { // Sanity limit
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Too many items']);
    exit;
}

try {
    // One transaction, so a partial failure cannot leave the list half-reordered.
    $pdo->beginTransaction();

    $stmt    = $pdo->prepare("UPDATE `{$table}` SET display_order = ? WHERE id = ?");
    $applied = 0;

    foreach ($order as $item) {
        if (!is_array($item) || !isset($item['id'], $item['order'])) continue;

        $itemId    = sanitizeInt($item['id'], 1);
        $itemOrder = sanitizeInt($item['order'], 0, 9999);

        if ($itemId !== null && $itemOrder !== null) {
            $stmt->execute([$itemOrder, $itemId]);
            $applied++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'updated' => $applied]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("API reorder error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
