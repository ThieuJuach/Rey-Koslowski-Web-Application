<?php
/**
 * API: Save page content (AJAX)
 *
 * SECURITY:
 * - Auth required
 * - CSRF token validation
 * - Schema-validated input; unexpected fields are rejected outright
 * - Strict allowlist for table/field names (identifiers cannot be parameterised,
 *   so they must never come from user input unchecked)
 * - Rate limiting on IP + account with a graceful 429
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

// OWASP: all state-changing requests must verify a CSRF token.
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Reload the page.']);
    exit;
}

enforceRateLimit('api_save', API_MAX_PER_MINUTE, 60, 'json');

/*
 * Allowlists for SQL identifiers.
 *
 * Declared before validation so the schema can enumerate them: an out-of-list table
 * or field is now a schema failure with a clear message, rather than reaching the
 * query builder at all.
 */
$allowedTables = [
    'pages', 'page_sections', 'publications', 'research_projects', 'courses',
    'media_appearances', 'site_settings', 'presentations',
    'professional_service_items', 'community_engagement_items',
];
$allowedFields = ['title', 'content', 'description', 'setting_value', 'abstract', 'citation'];

$clean = validateSchemaOrFail($_POST, [
    'table' => ['type' => 'enum', 'required' => true, 'values' => $allowedTables],
    'field' => ['type' => 'enum', 'required' => true, 'values' => $allowedFields],
    'id'    => ['type' => 'int',  'required' => true, 'min' => 1],
    // Long-form content: the allowlist-based sanitiser in formatText() runs at render
    // time, so the raw text is stored here but capped in length.
    'value' => ['type' => 'string', 'max' => MAX_TEXTAREA_LENGTH],
]);

// Belt-and-braces: re-assert membership before interpolating into SQL. The schema
// already guarantees this, but identifier interpolation deserves a second check.
if (!in_array($clean['table'], $allowedTables, true)
    || !in_array($clean['field'], $allowedFields, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `{$clean['table']}` SET `{$clean['field']}` = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$clean['value'], $clean['id']]);
    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
} catch (PDOException $e) {
    error_log("API save-content error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
