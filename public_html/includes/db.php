<?php
/**
 * Database Connection (PDO)
 * 
 * SECURITY (OWASP):
 * - Uses prepared statements by default (ATTR_EMULATE_PREPARES = false)
 * - Throws exceptions on error for proper handling
 * - Does not expose connection details to end users
 */

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Use real prepared statements
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log the real error, show a generic message to the user (OWASP Error Handling).
    error_log("Database connection failed: " . $e->getMessage());

    /*
     * Status matters as much as the message. die() alone answers 200 OK, which
     * tells a crawler this apology IS the page - Google will index it in place
     * of the real content and drop the URL's ranking. 503 plus Retry-After says
     * "temporarily unavailable, come back", which search engines honour, and
     * noindex stops the error being cached as content if one indexes it anyway.
     */
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 300');
        header('X-Robots-Tag: noindex, nosnippet');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=UTF-8');
    }
    die('<!doctype html><html lang="en"><head><meta charset="utf-8">'
      . '<meta name="robots" content="noindex"><title>Temporarily unavailable</title></head>'
      . '<body><h1>Temporarily unavailable</h1>'
      . '<p>The site is having trouble reaching its database. Please try again shortly.</p>'
      . '</body></html>');
}
