<?php
/**
 * includes/auth.php — Session management & role-based access control.
 *
 * Supports all 4 roles defined in the Roles table:
 *   1. Admin               — full access to every screen and all depots
 *   2. Fleet Safety Staff  — vehicle assignments, telematics, safety reports
 *   3. Workshop Staff      — maintenance jobs, parts, workshop reports
 *   4. Driver              — reserved for future driver-facing features
 */

// ── Role constants (match role_name in Roles table) ──
define('ROLE_ADMIN',          'Admin');
define('ROLE_FLEET_SAFETY',   'Fleet Safety Staff');
define('ROLE_WORKSHOP',       'Workshop Staff');
define('ROLE_DRIVER',         'Driver');

define('ALL_ROLES', [
    ROLE_ADMIN,
    ROLE_FLEET_SAFETY,
    ROLE_WORKSHOP,
    ROLE_DRIVER,
]);

// ── Start session ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Core access control ──

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . base_url() . '/login.php');
        exit;
    }
}

function require_role(string ...$allowedRoles): void {
    require_login();
    if (!in_array($_SESSION['role_name'], $allowedRoles, true)) {
        http_response_code(403);
        $role = htmlspecialchars($_SESSION['role_name'] ?? 'Unknown');
        echo '<!DOCTYPE html><html><head><title>403</title></head><body>';
        echo '<h1>403 — Access Denied</h1>';
        echo '<p>Your role: <strong>' . $role . '</strong></p>';
        echo '<p><a href="' . base_url() . '/index.php">Back to Dashboard</a></p>';
        echo '</body></html>';
        exit;
    }
}

// ── Boolean role checks ──

function has_role(string ...$roles): bool {
    return isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], $roles, true);
}

function is_admin(): bool       { return has_role(ROLE_ADMIN); }
function is_fleet_safety(): bool { return has_role(ROLE_FLEET_SAFETY); }
function is_workshop(): bool     { return has_role(ROLE_WORKSHOP); }
function is_driver(): bool       { return has_role(ROLE_DRIVER); }

// ── Convenience wrappers ──

function require_admin(): void       { require_role(ROLE_ADMIN); }
function require_fleet_safety(): void { require_role(ROLE_ADMIN, ROLE_FLEET_SAFETY); }
function require_workshop(): void     { require_role(ROLE_ADMIN, ROLE_WORKSHOP); }
function require_driver(): void       { require_role(ROLE_ADMIN, ROLE_DRIVER); }

// ── Session data helpers ──

function current_depot_id(): ?int {
    return $_SESSION['depot_id'] ?? null;
}

function current_depot_name(): ?string {
    return $_SESSION['depot_name'] ?? null;
}

function current_username(): ?string {
    return $_SESSION['username'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['role_name'] ?? null;
}

// ── URL helper ──

function base_url(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($scriptName);
    $subdirs = ['includes', 'assignments', 'maintenance', 'telematics', 'reports'];
    if (in_array(basename($dir), $subdirs, true)) {
        $dir = dirname($dir);
    }
    return rtrim($dir, '/');
}