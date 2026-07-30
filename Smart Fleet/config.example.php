<?php
/**
 * config.php — Database connection (PDO)
 * Smart Fleet Management — COS20031
 */
// ── Aiven cloud MySQL (uncomment if connecting to cloud DB) ─────
$host = getenv('DB_HOST') ?: 'your-host-here';
$port = getenv('DB_PORT') ?: 11577; // ← replace with your actual Aiven port
$db   = getenv('DB_NAME') ?: 'SmartFleetDatabase';
$user = getenv('DB_USER') ?: 'your-username-here';
$pass = getenv('DB_PASS');   'your-password-here';
// Base path constant — every file uses this for includes
define('BASE_PATH', __DIR__);

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                     // real prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // In production, log this instead of echoing
    http_response_code(500);
    die('Database connection failed. Check config.php settings.');
}