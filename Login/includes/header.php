<?php
/**
 * includes/header.php — Shared HTML header with role-aware navigation.
 * Expects $pageTitle to be set before including.
 * Expects auth.php to have been included (for has_role() / session data).
 */
if (!isset($pageTitle)) $pageTitle = 'Smart Fleet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Smart Fleet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f0f2f5;
            color: #202124;
        }
        /* ── Top bar ── */
        .topbar {
            background: #1a237e;
            color: #fff;
            padding: 0 24px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .topbar .brand {
            font-size: 1.2rem;
            font-weight: 700;
            text-decoration: none;
            color: #fff;
        }
        .topbar .user-info {
            display: flex;
            align