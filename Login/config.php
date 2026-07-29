<?php
/**
 * config.php — Database connection (PDO) via Aiven Cloud MySQL.
 *
 * Credentials are loaded from a .env file in the project root.
 * The .env file is gitignored and must never be committed to GitHub.
 *
 * Setup:
 *   1. Copy .env.example to .env
 *   2. Fill in your Aiven credentials
 *   3. Ensure .env is listed in .gitignore
 */

// ── Load .env file (lightweight parser — no Composer needed) ──
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    http_response_code(500);
    die('<h1>Configuration Error</h1>
         <p>The <code>.env</code> file was not found.</p>
         <p>Copy <code>.env.example</code> to <code>.env</code> and fill in your Aiven credentials.</p>');
}

// Parse .env into $_ENV and getenv()
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    // Skip comments and lines without '='
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $key   = trim($key);
    $value = trim($value);
    // Strip surrounding quotes if present
    if ((strlen($value) >= 2) &&
        (($value[0] === '"' && $value[strlen($value)-1] === '"') ||
         ($value[0] === "'" && $value[strlen($value)-1] === "'"))) {
        $value = substr($value, 1, -1);
    }
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

// ── Read credentials from environment ──
$host    = $_ENV['DB_HOST']    ?? '';
$port    = $_ENV['DB_PORT']    ?? 3306;
$db      = $_ENV['DB_NAME']    ?? 'SmartFleetDatabase';
$user    = $_ENV['DB_USER']    ?? '';
$pass    = $_ENV['DB_PASS']    ?? '';
$charset = 'utf8mb4';

// ── Validate that all required values are present ──
$missing = [];
if ($host === '') $missing[] = 'DB_HOST';
if ($port === '' || $port === 3306) $missing[] = 'DB_PORT';
if ($user === '') $missing[] = 'DB_USER';
if ($pass === '') $missing[] = 'DB_PASS';

if (!empty($missing)) {
    http_response_code(500);
    die('<h1>Configuration Error</h1>
         <p>The following variables are missing or empty in <code>.env</code>:</p>
         <ul><li>' . implode('</li><li>', $missing) . '</li></ul>
         <p>Edit your <code>.env</code> file and fill in the correct Aiven credentials.</p>');
}

// ── Base path constant — every file uses this for includes ──
define('BASE_PATH', __DIR__);

// ── Aiven requires SSL/TLS — set the DSN with SSL flags ──
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Aiven cloud MySQL requires SSL
    PDO::MYSQL_ATTR_SSL_CA => 'C:\xampp\php\extras\ssl\cacert.pem',
    // On Windows XAMPP, use: 'C:\xampp\php\extras\ssl\cacert.pem'
    // Download cacert.pem from https://curl.se/docs/caextract.html if missing
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    // Show a user-friendly message; log the real error for debugging
    error_log('DB Connection failed: ' . $e->getMessage());
    die('<h1>Database Connection Failed</h1>
         <p>Could not connect to the Aiven Cloud MySQL database.</p>
         <p><strong>Common causes:</strong></p>
         <ul>
           <li>Wrong port number in <code>.env</code> (Aiven uses a custom port, not 3306)</li>
           <li>Wrong password or username</li>
           <li>Missing SSL certificate (Aiven requires TLS)</li>
           <li>Your IP is not in the Aiven allowed IP list</li>
         </ul>
         <p>Check <code>.env</code> and your Aiven console, then retry.</p>');
}