<?php
/**
 * includes/auth.php — Session management & role-based access control.
 * Every protected page starts with:
 *   require_once BASE_PATH . '/includes/auth.php';
 *   require_role('Admin', 'Fleet Safety Staff');
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirect to login page if no one is logged in.
 */
function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . base_url() . '/login.php');
        exit;
    }
}

/**
 * Ensure the current user has one of the allowed roles.
 * Call AFTER require_login() (or standalone — it calls require_login internally).
 *
 * Usage:
 *   require_role('Admin', 'Fleet Safety Staff');
 */
function require_role(string ...$allowedRoles): void
{
    require_login();

    if (!in_array($_SESSION['role_name'], $allowedRoles, true)) {
        http_response_code(403);
        die('<h1>403 — Access Denied</h1>
             <p>You do not have permission to access this page.</p>
             <p>Your role: <strong>' . htmlspecialchars($_SESSION['role_name']) . '</strong></p>
             <p><a href="' . base_url() . '/index.php">Back to Dashboard</a></p>');
    }
}

/**
 * Boolean check — does the current user have one of these roles?
 */
function has_role(string ...$roles): bool
{
    return isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], $roles, true);
}

/**
 * Return the current user's depot_id (or null for admins viewing all depots).
 */
function current_depot_id(): ?int
{
    return $_SESSION['depot_id'] ?? null;
}

/**
 * Is the current user an admin?
 */
function is_admin(): bool
{
    return has_role('Admin');
}

/**
 * Helper: build a URL relative to the app root.
 * Works whether deployed at /SmartFleet/ or at the document root.
 */
function base_url(): string
{
    // Detect the app's subdirectory from the script path
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($scriptName);

    // If we're in a subdirectory like /SmartFleet/includes/, go up
    if (basename($dir) === 'includes' || basename($dir) === 'assignments'
        || basename($dir) === 'maintenance' || basename($dir) === 'telematics'
        || basename($dir) === 'reports') {
        $dir = dirname($dir);
    }

    return rtrim($dir, '/');
}