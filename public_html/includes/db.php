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
    // Log the real error, show generic message to user (OWASP Error Handling)
    error_log("Database connection failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}
